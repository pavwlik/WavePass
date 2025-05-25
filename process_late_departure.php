<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["user_id"])) {
    http_response_code(401); // Unauthorized
    $response['message'] = 'User not authenticated. Please log in.';
    echo json_encode($response);
    exit;
}

$userID = (int)$_SESSION["user_id"];
$notification_date_str = trim($_POST['notification_date'] ?? '');
$planned_departure_time_str = trim($_POST['planned_departure_time'] ?? '');
$notes = trim($_POST['notes'] ?? ''); // Can be empty, will be handled by PARAM_NULL or default DB value

// --- Validation ---
if (empty($notification_date_str) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $notification_date_str)) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Invalid notification date format provided.';
    echo json_encode($response);
    exit;
}

// Validate time format (HH:MM)
if (empty($planned_departure_time_str) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $planned_departure_time_str)) {
    http_response_code(400);
    $response['message'] = 'Invalid planned departure time format. Please use HH:MM.';
    echo json_encode($response);
    exit;
}

// Check if time is 15:30 or later
if (strtotime($planned_departure_time_str) < strtotime('15:30:00')) {
    http_response_code(400);
    $response['message'] = 'Planned departure time must be 15:30 or later.';
    echo json_encode($response);
    exit;
}
// Format time for DB (TIME type in MySQL accepts HH:MM and will store as HH:MM:SS with SS as 00)
$planned_departure_time_db = date("H:i:s", strtotime($planned_departure_time_str));


if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $pdo->beginTransaction();

        // Check if a notification for this user and date already exists
        $stmtCheck = $pdo->prepare("SELECT notificationID FROM late_departure_notifications WHERE userID = :userID AND notification_date = :notification_date");
        $stmtCheck->execute([':userID' => $userID, ':notification_date' => $notification_date_str]);
        $existingNotification = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        $stmtCheck->closeCursor(); // Good practice

        if ($existingNotification) {
            // Update existing notification
            $stmt = $pdo->prepare(
                "UPDATE late_departure_notifications 
                 SET planned_departure_time = :planned_departure_time, notes = :notes, viewed_by_admin = 0, created_at = CURRENT_TIMESTAMP
                 WHERE notificationID = :notificationID"
            );
            $stmt->execute([
                ':planned_departure_time' => $planned_departure_time_db,
                ':notes' => !empty($notes) ? $notes : null, // Set to NULL if empty
                ':notificationID' => $existingNotification['notificationID']
            ]);
            $response['message'] = 'Your late departure notification has been updated successfully!';
        } else {
            // Insert new notification
            $stmt = $pdo->prepare(
                "INSERT INTO late_departure_notifications (userID, notification_date, planned_departure_time, notes)
                 VALUES (:userID, :notification_date, :planned_departure_time, :notes)"
            );
            $stmt->execute([
                ':userID' => $userID,
                ':notification_date' => $notification_date_str,
                ':planned_departure_time' => $planned_departure_time_db,
                ':notes' => !empty($notes) ? $notes : null // Set to NULL if empty
            ]);
            $response['message'] = 'Late departure notification submitted successfully!';
        }
        
        $pdo->commit();
        $response['success'] = true;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Late Departure DB Error (User: {$userID}): " . $e->getMessage());
        http_response_code(500); // Internal Server Error
        $response['message'] = 'A database error occurred while processing your request.';
        // For debugging, you might include $e->getMessage(), but not in production.
        // $response['debug_message'] = $e->getMessage(); 
    }
} else {
    http_response_code(503); // Service Unavailable
    $response['message'] = 'Database connection not available.';
}

echo json_encode($response);
exit;
?>