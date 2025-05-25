<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- AJAX REQUEST HANDLING FOR LATE DEPARTURE ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && 
    ($_POST['action'] === 'submit_late_departure' || $_POST['action'] === 'cancel_late_departure')) {
    
    header('Content-Type: application/json');
    if (!file_exists('db.php')) { 
        echo json_encode(['success' => false, 'message' => 'Database configuration file not found.']);
        exit;
    }
    require_once 'db.php'; 

    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["user_id"])) {
        http_response_code(401); 
        $response['message'] = 'User not authenticated. Please log in.';
        echo json_encode($response);
        exit;
    }
    $userID = (int)$_SESSION["user_id"];
    $todayDateForAction = date('Y-m-d'); 

    if ($_POST['action'] === 'submit_late_departure') {
        $planned_departure_time_str = trim($_POST['planned_departure_time'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($planned_departure_time_str) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $planned_departure_time_str)) {
            http_response_code(400);
            $response['message'] = 'Invalid planned departure time format. Please use HH:MM.';
            echo json_encode($response);
            exit;
        }
        if (strtotime($planned_departure_time_str) < strtotime('15:30:00')) {
            http_response_code(400);
            $response['message'] = 'Planned departure time must be 15:30 or later.';
            echo json_encode($response);
            exit;
        }
        $planned_departure_time_db = date("H:i:s", strtotime($planned_departure_time_str));

        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $pdo->beginTransaction();
                $stmtCheck = $pdo->prepare("SELECT notificationID FROM late_departure_notifications WHERE userID = :userID AND notification_date = :notification_date");
                $stmtCheck->execute([':userID' => $userID, ':notification_date' => $todayDateForAction]);
                $existingNotification = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                $stmtCheck->closeCursor();

                if ($existingNotification) {
                    $stmt = $pdo->prepare(
                        "UPDATE late_departure_notifications 
                         SET planned_departure_time = :planned_departure_time, notes = :notes, viewed_by_admin = 0, created_at = CURRENT_TIMESTAMP
                         WHERE notificationID = :notificationID"
                    );
                    $stmt->execute([
                        ':planned_departure_time' => $planned_departure_time_db,
                        ':notes' => !empty($notes) ? $notes : null,
                        ':notificationID' => $existingNotification['notificationID']
                    ]);
                    $response['message'] = 'Late departure notification updated successfully!';
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO late_departure_notifications (userID, notification_date, planned_departure_time, notes)
                         VALUES (:userID, :notification_date, :planned_departure_time, :notes)"
                    );
                    $stmt->execute([
                        ':userID' => $userID,
                        ':notification_date' => $todayDateForAction,
                        ':planned_departure_time' => $planned_departure_time_db,
                        ':notes' => !empty($notes) ? $notes : null
                    ]);
                    $response['message'] = 'Late departure notification submitted successfully!';
                }
                $pdo->commit();
                $response['success'] = true;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("AJAX Late Departure DB Error (User: {$userID}): " . $e->getMessage());
                http_response_code(500);
                $response['message'] = 'A database error occurred.';
            }
        } else {
            http_response_code(503);
            $response['message'] = 'Database connection not available for AJAX.';
        }
    } elseif ($_POST['action'] === 'cancel_late_departure') {
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $stmtDelete = $pdo->prepare(
                    "DELETE FROM late_departure_notifications 
                     WHERE userID = :userID AND notification_date = :notification_date"
                );
                if ($stmtDelete->execute([':userID' => $userID, ':notification_date' => $todayDateForAction])) {
                    $response['success'] = true;
                    $response['message'] = $stmtDelete->rowCount() > 0 ? 'Late departure notification cancelled.' : 'No notification to cancel.';
                } else {
                    http_response_code(500);
                    $response['message'] = 'Failed to cancel notification.';
                }
            } catch (PDOException $e) {
                error_log("AJAX Cancel Late Departure DB Error (User: {$userID}): " . $e->getMessage());
                http_response_code(500);
                $response['message'] = 'A database error occurred during cancellation.';
            }
        } else {
            http_response_code(503);
            $response['message'] = 'Database connection not available for AJAX.';
        }
    }
    echo json_encode($response);
    exit; 
}
// --- END OF AJAX REQUEST HANDLING ---


// --- REGULAR PAGE LOAD LOGIC ---
require_once 'db.php'; 

// --- SESSION VARIABLES ---
$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Employee';
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
$sessionRole = isset($_SESSION["role"]) ? strtolower($_SESSION["role"]) : 'employee'; 

// --- DATE HANDLING ---
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$todayDate = date('Y-m-d');
$todayDateTime = date('Y-m-d H:i:s');

// --- DEFAULT VALUES FOR DISPLAY ---
$rfidStatus = "Status Unknown"; 
$rfidStatusClass = "neutral";
$absencesThisMonthCountDisplay = "0 Days Approved";
$unreadMessagesCount = 0; 
$upcomingLeaveDisplay = "None upcoming"; 
$activityForSelectedDate = [];
$dbErrorMessage = null;
$currentUserData = null; 
$existingLateDeparture = null; 

if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        // 1. Fetch user's dateOfCreation
        $stmtUserMeta = $pdo->prepare("SELECT dateOfCreation FROM users WHERE userID = :userid");
        if ($stmtUserMeta) {
            $stmtUserMeta->execute([':userid' => $sessionUserId]);
            $currentUserData = $stmtUserMeta->fetch();
            $stmtUserMeta->closeCursor();
        }

        // 2. DETERMINE CURRENT PRESENCE STATUS
        $stmtLatestEvent = $pdo->prepare(
            "SELECT logType, logResult 
            FROM attendance_logs
            WHERE userID = :userid AND DATE(logTime) = :today_date
            ORDER BY logTime DESC
            LIMIT 1"
        );
        if ($stmtLatestEvent) {
            $stmtLatestEvent->execute([
                ':userid' => $sessionUserId,
                ':today_date' => $todayDate
            ]);
            $latestEvent = $stmtLatestEvent->fetch();
            $stmtLatestEvent->closeCursor();

            if ($latestEvent) {
                if ($latestEvent['logType'] == 'entry' && $latestEvent['logResult'] == 'granted') {
                    $rfidStatus = "Present";
                    $rfidStatusClass = "present";
                } elseif ($latestEvent['logType'] == 'exit' && $latestEvent['logResult'] == 'granted') {
                    $rfidStatus = "Checked Out";
                    $rfidStatusClass = "absent"; 
                } elseif ($latestEvent['logResult'] == 'denied') {
                    $rfidStatus = ($latestEvent['logType'] == 'entry' ? "Check In Denied" : "Exit Denied");
                    $rfidStatusClass = "danger"; 
                } else {
                    $rfidStatus = "Status Unknown"; 
                    $rfidStatusClass = "neutral";
                }
            } else {
                $stmtScheduledAbsenceToday = $pdo->prepare(
                    "SELECT absence_type FROM absence
                    WHERE userID = :userid 
                    AND :today_date_time BETWEEN absence_start_datetime AND absence_end_datetime
                    AND status = 'approved' 
                    LIMIT 1"
                );
                if ($stmtScheduledAbsenceToday) {
                    $stmtScheduledAbsenceToday->execute([
                        ':userid' => $sessionUserId,
                        ':today_date_time' => $todayDateTime
                    ]);
                    $scheduledAbsence = $stmtScheduledAbsenceToday->fetch();
                    $stmtScheduledAbsenceToday->closeCursor();

                    if ($scheduledAbsence) {
                        $absenceTypeDisplay = ucfirst(str_replace('_', ' ', $scheduledAbsence['absence_type']));
                        $rfidStatus = "Scheduled " . htmlspecialchars($absenceTypeDisplay);
                        $rfidStatusClass = "absent"; 
                    } else {
                        $rfidStatus = "Not Checked In";
                        $rfidStatusClass = "neutral"; 
                    }
                }
            }
        }

        // 3. APPROVED ABSENCES THIS MONTH
        $currentMonthStart = date('Y-m-01 00:00:00');
        $currentMonthEnd = date('Y-m-t 23:59:59'); 
        $stmtAbsenceCount = $pdo->prepare(
            "SELECT COUNT(DISTINCT DATE(absence_start_datetime)) 
            FROM absence 
            WHERE userID = :userid 
            AND status = 'approved' 
            AND (
                (absence_start_datetime <= :month_end AND absence_end_datetime >= :month_start)
            )"
        );
        if ($stmtAbsenceCount) {
            $stmtAbsenceCount->execute([
                ':userid' => $sessionUserId,
                ':month_start' => $currentMonthStart,
                ':month_end' => $currentMonthEnd
            ]);
            $absencesThisMonthCountValue = $stmtAbsenceCount->fetchColumn();
            $absencesThisMonthCountDisplay = $absencesThisMonthCountValue ? $absencesThisMonthCountValue . ($absencesThisMonthCountValue == 1 ? " Day" : " Days") . " Approved" : "0 Days Approved";
            $stmtAbsenceCount->closeCursor();
        }

        // 4. UNREAD MESSAGES COUNT
        $stmtUnread = $pdo->prepare("
            SELECT COUNT(DISTINCT m.messageID) 
            FROM messages m
            LEFT JOIN user_message_read_status umrs ON m.messageID = umrs.messageID AND umrs.userID = :current_user_id_for_read_status
            WHERE 
                m.is_active = TRUE AND 
                (m.expires_at IS NULL OR m.expires_at > NOW()) AND
                (
                    m.recipientID = :current_user_id_recipient OR 
                    m.recipientRole = :current_user_role OR
                    m.recipientRole = 'everyone'
                ) AND
                (umrs.is_read IS NULL OR umrs.is_read = 0)
        ");
        if ($stmtUnread) {
            $stmtUnread->execute([
                ':current_user_id_for_read_status' => $sessionUserId,
                ':current_user_id_recipient' => $sessionUserId,
                ':current_user_role' => $sessionRole
            ]);
            $unreadMessagesCount = (int)$stmtUnread->fetchColumn();
            $stmtUnread->closeCursor();
        }

        // 5. UPCOMING LEAVE
        $stmtUpcomingLeave = $pdo->prepare(
            "SELECT absence_type, absence_start_datetime
            FROM absence
            WHERE userID = :userid
            AND status = 'approved'
            AND absence_start_datetime > :now
            ORDER BY absence_start_datetime ASC
            LIMIT 1"
        );
        if ($stmtUpcomingLeave) {
            $stmtUpcomingLeave->execute([
                ':userid' => $sessionUserId,
                ':now' => $todayDateTime
            ]);
            $upcoming = $stmtUpcomingLeave->fetch();
            if ($upcoming) {
                $leaveType = ucfirst(str_replace('_', ' ', $upcoming['absence_type']));
                $leaveDate = date("M d, Y", strtotime($upcoming['absence_start_datetime']));
                $upcomingLeaveDisplay = htmlspecialchars($leaveType) . " on " . $leaveDate;
            }
            $stmtUpcomingLeave->closeCursor();
        }

        // ** Fetch existing late departure notification for today **
        $stmtExistingLate = $pdo->prepare(
            "SELECT planned_departure_time, notes 
             FROM late_departure_notifications
             WHERE userID = :userid AND notification_date = :today_date
             LIMIT 1"
        );
        if ($stmtExistingLate) {
            $stmtExistingLate->execute([':userid' => $sessionUserId, ':today_date' => $todayDate]);
            $existingLateDeparture = $stmtExistingLate->fetch(PDO::FETCH_ASSOC);
            $stmtExistingLate->closeCursor();
        }

        // 6. ACTIVITY SNAPSHOT FOR SELECTED DATE
        if ($currentUserData && $selectedDate == date("Y-m-d", strtotime($currentUserData['dateOfCreation']))) {
            $activityForSelectedDate[] = [
                'time' => date("H:i", strtotime($currentUserData['dateOfCreation'])),
                'log_type' => 'System', 'log_result' => 'Info', 
                'details' => 'Your WavePass account was created.', 'rfid_card' => 'N/A', 'status_class' => 'info'
            ];
        }
        $stmtActivityLog = $pdo->prepare(
            "SELECT logTime, logType, logResult FROM attendance_logs
            WHERE userID = :userid AND DATE(logTime) = :selected_date ORDER BY logTime ASC"
        );
        if ($stmtActivityLog) {
            $stmtActivityLog->execute([':userid' => $sessionUserId, ':selected_date' => $selectedDate]);
            while ($log = $stmtActivityLog->fetch(PDO::FETCH_ASSOC)) {
                $logTypeDisplay = 'Unknown'; $statusClass = 'neutral';
                if ($log['logType'] == 'entry') {
                    $logTypeDisplay = 'Check In'; $statusClass = ($log['logResult'] == 'granted') ? 'present' : 'danger';
                } elseif ($log['logType'] == 'exit') {
                    $logTypeDisplay = 'Check Out'; $statusClass = ($log['logResult'] == 'granted') ? 'absent' : 'danger';
                }
                $activityForSelectedDate[] = [
                    'time' => date("H:i", strtotime($log['logTime'])),
                    'log_type' => htmlspecialchars($logTypeDisplay), 
                    'log_result' => htmlspecialchars(ucfirst($log['logResult'] ?? 'N/A')), 
                    'details' => 'Attempted access.', 'rfid_card' => 'System Log', 'status_class' => $statusClass
                ];
            }
            $stmtActivityLog->closeCursor();
        }
        $hasCheckInOutActivity = false;
        foreach($activityForSelectedDate as $act) {
            if (isset($act['log_type']) && (strpos($act['log_type'], 'Check In') !== false || strpos($act['log_type'], 'Check Out') !== false)) {
                $hasCheckInOutActivity = true;
                break;
            }
        }
        if ($selectedDate == $todayDate && !$hasCheckInOutActivity && $rfidStatus !== "Status Unknown" && $rfidStatus !== "Not Checked In") {
            if ($rfidStatusClass !== 'neutral' || strpos(strtolower($rfidStatus), 'scheduled') !== false) {
                $activityForSelectedDate[] = [
                    'time' => date("H:i"), 'log_type' => 'Current Status', 
                    'log_result' => htmlspecialchars($rfidStatus),
                    'details' => 'Based on latest system information.', 'rfid_card' => 'N/A', 'status_class' => $rfidStatusClass
                ];
            }
        }
        if (empty($activityForSelectedDate) && $selectedDate <= $todayDate){
            $stmtSelectedDayAbsence = $pdo->prepare(
                "SELECT absence_type, reason FROM absence WHERE userID = :userid 
                AND :selected_date_for_absence BETWEEN DATE(absence_start_datetime) AND DATE(absence_end_datetime)
                AND status = 'approved' LIMIT 1"
            );
            if ($stmtSelectedDayAbsence) {
                $stmtSelectedDayAbsence->execute([':userid' => $sessionUserId, ':selected_date_for_absence' => $selectedDate]);
                $selectedDayAbsenceInfo = $stmtSelectedDayAbsence->fetch();
                $stmtSelectedDayAbsence->closeCursor();
                if($selectedDayAbsenceInfo){
                    $absenceTypeDetailForMsg = htmlspecialchars(ucfirst(str_replace('_',' ',$selectedDayAbsenceInfo['absence_type'])));
                    $activityForSelectedDate[] = [
                        'time' => '--:--', 'log_type' => 'Scheduled Absence', 
                        'log_result' => htmlspecialchars($absenceTypeDetailForMsg),
                        'details' => ($selectedDayAbsenceInfo['reason'] ? 'Reason: '.htmlspecialchars($selectedDayAbsenceInfo['reason']) : 'Approved absence'), 
                        'rfid_card' => 'N/A', 'status_class' => 'absent'
                    ];
                } else {
                    $activityForSelectedDate[] = [
                        'time' => '--:--', 'log_type' => 'No Record', 'log_result' => 'N/A',
                        'details' => 'No attendance events or approved absences logged for this day.', 
                        'rfid_card' => 'N/A', 'status_class' => 'neutral'
                    ];
                }
            }
        }
        if (!empty($activityForSelectedDate)) {
            usort($activityForSelectedDate, function($a, $b) {
                if ($a['time'] === '--:--') return -1; if ($b['time'] === '--:--') return 1;
                return strtotime($a['time']) - strtotime($b['time']);
            });
        }

    } catch (PDOException $e) {
        error_log("Dashboard Page DB Error (User: {$sessionUserId}, Date: {$selectedDate}): " . $e->getMessage());
        $dbErrorMessage = "A database error occurred. Please try again later.";
    } catch (Exception $e) {
        error_log("Dashboard Page App Error (User: {$sessionUserId}, Date: {$selectedDate}): " . $e->getMessage());
        $dbErrorMessage = "An application error occurred. Please try again later.";
    }
} else {
    if (!isset($pdo) || !($pdo instanceof PDO)) $dbErrorMessage = ($dbErrorMessage ? $dbErrorMessage . "<br>" : "") . "Database connection not available.";
    if (!$sessionUserId) $dbErrorMessage = ($dbErrorMessage ? $dbErrorMessage . "<br>" : "") . "User session is invalid (or user ID not found).";
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="imgs/logo.png" type="image/x-icon"> 
    <title>My WavePass Dashboard - <?php echo $sessionFirstName; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee; 
            --primary-dark: #3a56d4;
            --secondary-color: #3f37c9;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --gray-color: #6c757d;
            --light-gray: #e9ecef;
            --white: #ffffff;
            --success-color: #4cc9f0; 
            --warning-color: #f8961e; 
            --danger-color: #f72585; 
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            
            --primary-color-val: 67, 97, 238;
            --present-color-val: 67, 170, 139; 
            --absent-color-val: 214, 40, 40;   
            --info-color-val: 84, 160, 255;    
            --neutral-color-val: 173, 181, 189; 
            --warning-color-val: 248, 150, 30; 
            --danger-color-val: 247, 37, 133; 

            --present-color: rgb(var(--present-color-val)); 
            --absent-color: rgb(var(--absent-color-val)); 
            --info-color: rgb(var(--info-color-val));
            --neutral-color: rgb(var(--neutral-color-val));
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            padding-top: 80px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6; color: var(--dark-color); background-color: var(--light-color);
            overflow-x: hidden; scroll-behavior: smooth; display: flex; flex-direction: column; min-height: 100vh;
        }
        main { 
            flex-grow: 1; 
            background-color: #f4f6f9; 
            /* padding-top už je na body, zde není potřeba, pokud main není sám o sobě posunutý */
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
        h1, h2, h3, h4 { font-weight: 700; line-height: 1.2; }

        header { 
            background-color: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            width: 100%;
            position: fixed; 
            top: 0;
            left: 0; 
            z-index: 1000;
            transition: var(--transition);
            height: 80px; 
        }
        .navbar { 
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px; 
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
        }
        .logo { font-size: 1.8rem; font-weight: 800; color: var(--primary-color); text-decoration: none; display: flex; align-items: center; gap: 0.5rem;}
        .logo img { height: 30px; margin-right: 0.5rem; } 
        .logo span { color: var(--dark-color); font-weight: 600; }
        .nav-links { display: none; list-style: none; align-items: center; gap: 0.5rem; }
        .nav-links a:not(.btn) { color: var(--dark-color); text-decoration: none; font-weight: 500; transition: color var(--transition), background-color var(--transition); padding: 0.7rem 1rem; font-size: 0.95rem; border-radius: 8px; position: relative;}
        .nav-links a:not(.btn):hover, .nav-links a.active-nav-link { color: var(--primary-color); background-color: rgba(var(--primary-color-val), 0.07); }
        .nav-links .btn, .nav-links .btn-outline { display: inline-flex; gap: 8px; align-items: center; justify-content: center; padding: 0.7rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: var(--transition); cursor: pointer; text-align: center; font-size: 0.9rem; }
        .nav-links .btn { background-color: var(--primary-color); color: var(--white); box-shadow: 0 4px 14px rgba(var(--primary-color-val), 0.2);}
        .nav-links .btn:hover{ background-color: var(--primary-dark); box-shadow: 0 6px 20px rgba(var(--primary-color-val), 0.3); transform: translateY(-2px);}
        .nav-links .btn-outline { background-color: transparent; border: 2px solid var(--primary-color); color: var(--primary-color); box-shadow: none;}
        .nav-links .btn-outline:hover { background-color: var(--primary-color); color: var(--white); transform: translateY(-2px);}
        .hamburger { display: flex; flex-direction: column; justify-content: space-around; cursor: pointer; width: 30px; height: 24px; position: relative; z-index: 1001; background: none; border: none; padding: 0;}
        .hamburger span { display: block; width: 100%; height: 3px; background-color: var(--dark-color); position: absolute; left: 0; transition: var(--transition); transform-origin: center; }
        .hamburger span:nth-child(1) { top: 0; } .hamburger span:nth-child(2) { top: 50%; transform: translateY(-50%); } .hamburger span:nth-child(3) { bottom: 0; }
        .hamburger.active span:nth-child(1) { top: 50%; transform: translateY(-50%) rotate(45deg); } .hamburger.active span:nth-child(2) { opacity: 0; } .hamburger.active span:nth-child(3) { bottom: 50%; transform: translateY(50%) rotate(-45deg); }
        .mobile-menu { position: fixed; top: 0; left: 0; width: 100%; height: 100vh; background-color: var(--white); z-index: 1000; display: flex; flex-direction: column; justify-content: center; align-items: center; transform: translateX(-100%); transition: transform 0.4s cubic-bezier(0.23, 1, 0.32, 1); padding: 2rem;}
        .mobile-menu.active { transform: translateX(0); }
        .mobile-links { list-style: none; text-align: center; width: 100%; max-width: 300px; padding:0; }
        .mobile-links li { margin-bottom: 1.5rem; }
        .mobile-links a { color: var(--dark-color); text-decoration: none; font-weight: 600; font-size: 1.2rem; display: block; padding: 0.5rem 1rem; transition: var(--transition); border-radius: 8px;}
        .mobile-links a:hover, .mobile-links a.active-nav-link { color: var(--primary-color); background-color: rgba(var(--primary-color-val), 0.1); }
        .mobile-menu .btn-outline { margin-top: 2rem; width: 100%; max-width: 200px; }
        .close-btn { position: absolute; top: 20px; right: 20px; font-size: 2rem; color: var(--dark-color); cursor: pointer; transition: var(--transition); background: none; border: none; padding: 0.5rem; line-height: 1; }
        .close-btn:hover { color: var(--primary-color); transform: rotate(90deg); }
        @media (min-width: 993px) { .nav-links { display: flex; } .hamburger { display: none; } }

        .page-header { 
            padding: 1.5rem 0; 
            background-color:var(--white); 
            box-shadow: 0 1px 3px rgba(0,0,0,0.03); 
            /* NENÍ FIXNÍ - bude součástí toku pod hlavním headerem */
            /* margin-bottom: 1.8rem; Ponecháno, pokud je pod ním další obsah */
        }
        .page-header h1 { font-size: 1.7rem; color: var(--dark-color); margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        
        .db-error-message {background-color: rgba(var(--danger-color-val),0.1); color: var(--danger-color); padding: 1rem; border-left: 4px solid var(--danger-color); margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}

        .employee-stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 1.2rem; 
            margin-top: 1.8rem; /* Odsazení od .page-header, pokud není fixní */
            margin-bottom: 2.5rem; 
        }
        .stat-card { background-color: var(--white); padding: 1.2rem 1.4rem; border-radius: 8px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1.2rem; transition: var(--transition); border: 1px solid var(--light-gray); position:relative; overflow:hidden;}
        .stat-card::before { content: ''; position:absolute; top:0; left:0; width:5px; height:100%; background-color:transparent; transition: var(--transition); }
        .stat-card:hover{transform: translateY(-4px); box-shadow: 0 6px 28px rgba(0,0,0,0.09);}
        .stat-card .icon { font-size: 2rem; padding: 0.7rem; border-radius: 50%; display:flex; align-items:center; justify-content:center; width:50px; height:50px; flex-shrink:0;}
        .stat-card .info { flex-grow: 1; }
        .stat-card .info .value { font-size: 1.4rem; font-weight: 500; color: var(--dark-color); display:block; line-height:1.2; margin-bottom:0.2rem;}
        .stat-card .info .label { font-size: 0.85rem; color: var(--gray-color); text-transform: uppercase; letter-spacing: 0.3px; }
        .stat-card.rfid-status.present::before { background-color: var(--present-color); }
        .stat-card.rfid-status.present .icon { background-color: rgba(var(--present-color-val),0.1); color: var(--present-color); }
        .stat-card.rfid-status.absent::before { background-color: var(--absent-color); }
        .stat-card.rfid-status.absent .icon { background-color: rgba(var(--absent-color-val),0.1); color: var(--absent-color); }
        .stat-card.rfid-status.neutral::before { background-color: var(--neutral-color); }
        .stat-card.rfid-status.neutral .icon { background-color: rgba(var(--neutral-color-val),0.1); color: var(--neutral-color); }
        .stat-card.rfid-status.danger::before { background-color: var(--danger-color); }
        .stat-card.rfid-status.danger .icon { background-color: rgba(var(--danger-color-val),0.1); color: var(--danger-color); }
        .stat-card.absences-count::before { background-color: var(--absent-color); } 
        .stat-card.absences-count .icon { background-color: rgba(var(--absent-color-val),0.1); color: var(--absent-color); }
        .stat-card.unread-messages::before { background-color: var(--warning-color); } 
        .stat-card.unread-messages .icon { background-color: rgba(var(--warning-color-val),0.1); color: var(--warning-color); } 
        .stat-card.upcoming-leave::before { background-color: var(--info-color); } 
        .stat-card.upcoming-leave .icon { background-color: rgba(var(--info-color-val),0.1); color: var(--info-color); }
        .stat-card.upcoming-leave .info .value { font-size: 1.1rem; font-weight: 500; }
        .stat-card.action-card { align-items: stretch; }
        .stat-card.action-card .info { display: flex; flex-direction: column; justify-content: center; }
        .stat-card.action-card::before { background-color: var(--primary-color); }
        .stat-card.action-card .icon { background-color: rgba(var(--primary-color-val),0.1); color: var(--primary-color); }
        .btn-action-card { background-color: var(--primary-color); color: var(--white); border: none; padding: 0.7rem 1.3rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: var(--transition); display: inline-block; text-align: center; margin-bottom: 0.4rem;}
        .btn-action-card:hover { background-color: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(var(--primary-color-val), 0.2);}
        .stat-card.action-card .info .label { font-size: 0.8rem; color: var(--gray-color); margin-top: 0.2rem; text-transform: none; letter-spacing: normal;}
        .btn-action-card.btn-edit-late { background-color: var(--warning-color); color: var(--dark-color); font-size: 0.85rem; padding: 0.6rem 1rem;}
        .btn-action-card.btn-edit-late:hover { background-color: #e7860a; }
        .stat-card.action-card .info .value#displayedLateTime { font-size: 1.2rem; font-weight: 600; color: var(--primary-color); margin-bottom: 0.5rem;}
        .stat-card.action-card .info small#displayedLateNotes i.fa-sticky-note { color: var(--gray-color); margin-right: 5px; }

        .content-panel { background-color: var(--white); padding: 1.5rem 1.8rem; border-radius: 8px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); }
        .panel-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); gap: 1rem; }
        .panel-title { font-size: 1.3rem; color: var(--dark-color); margin:0; }
        .date-navigation { display: flex; align-items: center; gap: 0.5rem; }
        .date-navigation .btn-nav { background-color:var(--light-color); border:1px solid var(--light-gray); color:var(--dark-color); padding:0.4rem 0.6rem; border-radius:4px; cursor:pointer; transition:var(--transition); }
        .date-navigation .btn-nav:hover:not(:disabled) { background-color:var(--primary-color); color:var(--white); border-color:var(--primary-color); }
        .date-navigation .btn-nav:disabled { background-color: var(--light-gray); color: var(--gray-color); cursor: not-allowed; border-color: var(--light-gray); }
        .date-navigation .btn-nav .material-symbols-outlined {font-size:1.2em; vertical-align:middle;}
        .date-navigation input[type="date"] { padding: 0.4rem 0.7rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.9rem; background-color: var(--white); height:36px; text-align:center;}
        .activity-table-wrapper { overflow-x: auto; min-height: 150px; }
        .activity-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .activity-table th, .activity-table td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid var(--light-gray); }
        .activity-table th { background-color: #f9fafb; font-weight: 500; color: var(--gray-color); white-space: nowrap; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; }
        .activity-table tbody tr:last-child td { border-bottom:none; }
        .activity-table tbody tr:hover { background-color: #f0f4ff; }
        .activity-status { display: inline-flex; align-items:center; gap:0.4rem; padding: 0.25rem 0.7rem; border-radius: 15px; font-size: 0.78rem; font-weight: 500; white-space: nowrap; }
        .activity-status.present { background-color: rgba(var(--present-color-val), 0.15); color: var(--present-color); } 
        .activity-status.absent { background-color: rgba(var(--absent-color-val), 0.15); color: var(--absent-color); }
        .activity-status.info { background-color: rgba(var(--info-color-val), 0.15); color: var(--info-color); }
        .activity-status.neutral { background-color: rgba(var(--neutral-color-val),0.15); color: var(--neutral-color);}
        .activity-status.danger { background-color: rgba(var(--danger-color-val),0.15); color: var(--danger-color);}
        .activity-table td.rfid-cell { font-family: monospace; font-size: 0.8rem; color: var(--gray-color); }
        .no-activity-msg {text-align:center; padding: 2.5rem 1rem; color:var(--gray-color); font-size:0.95rem; background-color: #fdfdfd; border-radius: 4px; border: 1px dashed var(--light-gray);}
        .placeholder-text {color: var(--gray-color); font-style: italic; font-size: 0.8rem;}

        /* Modal Styles */
        .modal {display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center;}
        .modal.show {display: flex; }
        .modal-content {background-color: var(--white); margin: auto; padding: 25px 30px; border: 1px solid var(--light-gray); border-radius: 10px; width: 90%; max-width: 520px; box-shadow: 0 8px 25px rgba(0,0,0,0.15); position: relative; animation: fadeInModal 0.3s ease-out;}
        @keyframes fadeInModal {from {opacity: 0; transform: translateY(-30px) scale(0.95);} to {opacity: 1; transform: translateY(0) scale(1);}}
        .modal .close-modal-btn { color: var(--gray-color); background: transparent; border: none; position: absolute; top: 12px; right: 15px; font-size: 1.8rem; font-weight: bold; line-height: 1; padding: 0.2rem 0.5rem; cursor: pointer; transition: color 0.2s ease;}
        .modal .close-modal-btn:hover, .modal .close-modal-btn:focus {color: var(--dark-color);}
        .modal h2 {margin-top: 0; margin-bottom: 0.8rem; font-size: 1.6rem; color: var(--primary-color); font-weight: 600;}
        .modal p {font-size: 0.9rem; color: var(--gray-color); margin-bottom: 1.8rem; line-height: 1.5;}
        .modal .form-group {margin-bottom: 1.2rem;}
        .modal label {display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.9rem; color: var(--dark-color);}
        .modal input[type="time"], .modal textarea {width: 100%; padding: 0.7rem 0.9rem; border: 1px solid #ced4da; border-radius: 6px; font-size: 0.95rem; transition: border-color 0.2s ease, box-shadow 0.2s ease; background-color: #fdfdfd;}
        .modal input[type="time"]:focus, .modal textarea:focus {border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(var(--primary-color-val), 0.15); background-color: var(--white);}
        .modal textarea {min-height: 80px; resize: vertical;}
        .modal-actions {margin-top: 2rem; display: flex; justify-content: flex-end; gap: 0.8rem;}
        .modal-actions .btn-submit, .modal-actions .btn-cancel, .modal-actions .btn-danger {padding: 0.7rem 1.4rem; border-radius: 6px; font-weight: 500; cursor: pointer; transition: var(--transition); border: none; font-size: 0.9rem;}
        .modal-actions .btn-submit {background-color: var(--primary-color); color: var(--white); box-shadow: 0 2px 5px rgba(var(--primary-color-val), 0.2);}
        .modal-actions .btn-submit:hover {background-color: var(--primary-dark); box-shadow: 0 4px 8px rgba(var(--primary-color-val), 0.3); transform: translateY(-1px);}
        .modal-actions .btn-cancel {background-color: var(--light-gray); color: var(--dark-color); border: 1px solid #d3d9df;}
        .modal-actions .btn-cancel:hover {background-color: #d3d9df; }
        .modal-actions .btn-danger.btn-cancel-late { background-color: var(--danger-color); color: var(--white); margin-right: auto; }
        .modal-actions .btn-danger.btn-cancel-late:hover { background-color: #d4166a; }
        .form-message {margin-top: 1rem; padding: 0.8rem 1rem; border-radius: 5px; font-size: 0.9rem; display: none; border-left-width: 4px; border-left-style: solid;}
        .form-message.success {background-color: rgba(var(--present-color-val), 0.1); color: var(--present-color); border-left-color: var(--present-color); display: block;}
        .form-message.error {background-color: rgba(var(--danger-color-val), 0.1); color: var(--danger-color); border-left-color: var(--danger-color); display: block;}
    </style>
</head>
<body>
    <!-- Header -->
    <?php require "components/header-admin.php"; ?>

    <main>
        <div class="page-header"> <!-- This .page-header is NOT fixed by default -->
            <div class="container">
                <h1>Hello, <?php echo $sessionFirstName; ?>!</h1>
                <p class="sub-heading">Your personal attendance summary and daily activity.</p>
            </div>
        </div>

        <!-- This container holds the stats grid and the activity panel -->
        <div class="container content-wrapper"> 
            <?php if (isset($dbErrorMessage) && $dbErrorMessage): ?>
                <div class="db-error-message" role="alert" style="grid-column: 1 / -1;"> 
                    <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i> <?php echo htmlspecialchars($dbErrorMessage); ?>
                </div>
            <?php endif; ?>

            <section class="employee-stats-grid">
                <div class="stat-card rfid-status <?php echo htmlspecialchars($rfidStatusClass); ?>">
                    <div class="icon"><span class="material-symbols-outlined">
                        <?php 
                            if ($rfidStatusClass === "present") echo "person_check";
                            elseif ($rfidStatusClass === "absent") echo "person_off";
                            elseif ($rfidStatusClass === "danger") echo "gpp_maybe";
                            else echo "contactless"; 
                        ?>
                    </span></div>
                    <div class="info">
                        <span class="value"><?php echo htmlspecialchars($rfidStatus); ?></span>
                        <span class="label">Your Current Status</span>
                    </div>
                </div>
                <div class="stat-card unread-messages">
                    <div class="icon"><span class="material-symbols-outlined">mark_chat_unread</span></div>
                    <div class="info">
                        <span class="value"><?php echo $unreadMessagesCount; ?></span>
                        <span class="label">Unread Messages</span>
                    </div>
                </div>
                <div class="stat-card upcoming-leave">
                    <div class="icon"><span class="material-symbols-outlined">flight_takeoff</span></div>
                    <div class="info">
                        <span class="value"><?php echo htmlspecialchars($upcomingLeaveDisplay); ?></span>
                        <span class="label">Upcoming Leave</span>
                    </div>
                </div>
                
                <div class="stat-card action-card" id="lateDepartureActionCard">
                    <div class="icon"><span class="material-symbols-outlined">schedule_send</span></div>
                    <div class="info">
                        <?php if ($existingLateDeparture): ?>
                            <span class="value" id="displayedLateTime">Planned: <?php echo date("H:i", strtotime($existingLateDeparture['planned_departure_time'])); ?></span>
                            <?php if (!empty($existingLateDeparture['notes'])): ?>
                                <small class="placeholder-text" id="displayedLateNotes" title="<?php echo htmlspecialchars($existingLateDeparture['notes']); ?>">
                                    <i class="fas fa-sticky-note"></i> Note recorded
                                </small>
                            <?php endif; ?>
                            <button id="editLateDepartureBtn" class="btn-action-card btn-edit-late">Change / Cancel</button>
                        <?php else: ?>
                            <button id="notifyLateDepartureBtn" class="btn-action-card">Notify Late Departure</button>
                            <span class="label">Staying past 15:30? Let us know.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="content-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Activity for <span id="selectedDateDisplay"><?php echo date("F d, Y", strtotime($selectedDate)); ?></span></h2>
                    <div class="date-navigation">
                        <button class="btn-nav" id="prevDayBtn" title="Previous Day"><span class="material-symbols-outlined">chevron_left</span></button>
                        <input type="date" id="activity-date-selector" value="<?php echo htmlspecialchars($selectedDate); ?>" max="<?php echo $todayDate; ?>">
                        <button class="btn-nav" id="nextDayBtn" title="Next Day" <?php if ($selectedDate >= $todayDate) echo 'disabled'; ?>><span class="material-symbols-outlined">chevron_right</span></button>
                    </div>
                </div>
                
                <div class="activity-table-wrapper">
                    <table class="activity-table" id="employeeActivityTable_singleDay">
                        <thead>
                            <tr>
                                <th>Time</th> 
                                <th>Type</th>
                                <th>Result</th>
                                <th>Details</th>
                                <th>Source</th> 
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($activityForSelectedDate)): ?>
                                <?php foreach ($activityForSelectedDate as $activity): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($activity['time']); ?></td>
                                        <td>
                                            <span class="activity-status <?php echo htmlspecialchars($activity['status_class']); ?>">
                                                <?php echo htmlspecialchars($activity['log_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['log_result']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['details']); ?></td>
                                        <td class="rfid-cell"><?php echo htmlspecialchars($activity['rfid_card']); ?></td> 
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-activity-msg">No specific activity recorded for <?php echo date("M d, Y", strtotime($selectedDate)); ?>.</td>  
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div> <!-- Konec .content-wrapper -->
    </main>

    <!-- MODAL FOR LATE DEPARTURE NOTIFICATION -->
    <div id="lateDepartureModal" class="modal">
        <div class="modal-content">
            <button type="button" class="close-modal-btn" aria-label="Close modal">×</button>
            <h2 id="modalTitle">Notify Late Departure</h2>
            <p id="modalSubtitle">If you plan to stay beyond 15:30, please let us know your estimated departure time for <strong><?php echo date("F d, Y", strtotime($todayDate)); ?></strong>.</p>
            <form id="lateDepartureForm">
                <input type="hidden" name="notification_date" value="<?php echo htmlspecialchars($todayDate); ?>">
                <input type="hidden" name="action" value="submit_late_departure">

                <div class="form-group">
                    <label for="planned_departure_time">Planned Departure Time (after 15:30):</label>
                    <input type="time" id="planned_departure_time" name="planned_departure_time" required min="15:30">
                </div>
                <div class="form-group">
                    <label for="departure_notes">Notes (Optional):</label>
                    <textarea id="departure_notes" name="notes" rows="3" placeholder="e.g., Finishing up a project"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-submit">Save Changes</button> 
                    <button type="button" id="cancelLateBtn" class="btn-danger btn-cancel-late" style="display: none;">Cancel Late Departure</button>
                    <button type="button" class="btn-cancel close-modal-btn">Close</button>
                </div>
                <div id="modalFormMessage" class="form-message" role="alert"></div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <?php require "components/footer-admin.php"; ?>

<script>
        
        const headerEl = document.querySelector('header');
        if (headerEl) {
            // Stávající logika pro stín headeru při skrolování, pokud byla
            // const initialHeaderShadow = getComputedStyle(headerEl).boxShadow;
            // window.addEventListener('scroll', () => {
            //     if (window.scrollY > 10) { 
            //         headerEl.style.boxShadow = '0 4px 10px rgba(0, 0, 0, 0.05)'; 
            //     } else {
            //         headerEl.style.boxShadow = initialHeaderShadow; 
            //     }
            // });
        }

        const dateSelector = document.getElementById('activity-date-selector');
        const prevDayBtn = document.getElementById('prevDayBtn');
        const nextDayBtn = document.getElementById('nextDayBtn');
        const todayISO = new Date().toISOString().split('T')[0];

        function navigateToDate(dateString) {
            window.location.href = `dashboard.php?date=${dateString}`;
        }

        if (dateSelector) {
            dateSelector.addEventListener('change', function() { navigateToDate(this.value); });
            dateSelector.max = todayISO; 
        }
        if (prevDayBtn) {
            prevDayBtn.addEventListener('click', function() {
                const currentDate = new Date(dateSelector.value); 
                currentDate.setDate(currentDate.getDate() - 1); 
                navigateToDate(currentDate.toISOString().split('T')[0]);
            });
        }
        if (nextDayBtn) {
            nextDayBtn.addEventListener('click', function() {
                if (dateSelector.value < todayISO) {
                    const currentDate = new Date(dateSelector.value);
                    currentDate.setDate(currentDate.getDate() + 1);
                    navigateToDate(currentDate.toISOString().split('T')[0]);
                }
            });
            if (dateSelector && nextDayBtn) nextDayBtn.disabled = dateSelector.value >= todayISO;
        }

        // --- LATE DEPARTURE MODAL SCRIPT ---
        const notifyLateBtn = document.getElementById('notifyLateDepartureBtn'); 
        const editLateBtn = document.getElementById('editLateDepartureBtn');     

        const lateModal = document.getElementById('lateDepartureModal');
        const modalCloseButtons = lateModal ? lateModal.querySelectorAll('.close-modal-btn') : [];
        const lateForm = document.getElementById('lateDepartureForm');
        const modalMsgDiv = document.getElementById('modalFormMessage');
        const plannedTimeInput = document.getElementById('planned_departure_time');
        const notesInput = document.getElementById('departure_notes');
        const cancelLateBtnInModal = document.getElementById('cancelLateBtn');
        const modalTitleEl = document.getElementById('modalTitle');
        const modalSubmitButton = lateModal ? lateModal.querySelector('.btn-submit') : null;

        const existingLatePHP = <?php echo json_encode($existingLateDeparture); ?> || null; 

        function openLateModal(isEditing = false) {
            if (!lateModal || !plannedTimeInput || !notesInput || !cancelLateBtnInModal || !modalTitleEl || !modalSubmitButton) {
                console.error("One or more modal elements are missing from the DOM.");
                return;
            }
            lateModal.classList.add('show');
            document.body.style.overflow = 'hidden';

            if (modalMsgDiv) {
                modalMsgDiv.textContent = '';
                modalMsgDiv.className = 'form-message';
            }
            if (lateForm) lateForm.reset();
            
            const actionInput = lateForm.querySelector('input[name="action"]');
            if (actionInput) actionInput.value = 'submit_late_departure';

            if (isEditing && existingLatePHP) {
                modalTitleEl.textContent = 'Change Late Departure';
                modalSubmitButton.textContent = 'Save Changes';
                if(existingLatePHP.planned_departure_time) plannedTimeInput.value = existingLatePHP.planned_departure_time.substring(0,5);
                notesInput.value = existingLatePHP.notes || '';
                cancelLateBtnInModal.style.display = 'inline-block';
            } else {
                modalTitleEl.textContent = 'Notify Late Departure';
                modalSubmitButton.textContent = 'Submit Notification';
                const now = new Date();
                let defaultHours = 15;
                let defaultMinutes = 30;
                const notificationDateHiddenInput = lateModal.querySelector('input[name="notification_date"]');

                if (notificationDateHiddenInput && notificationDateHiddenInput.value === todayISO) {
                     if (now.getHours() > 15 || (now.getHours() === 15 && now.getMinutes() > 30)) {
                        let suggestedTime = new Date(now.getTime() + 30 * 60000);
                        defaultHours = suggestedTime.getHours();
                        defaultMinutes = Math.ceil(suggestedTime.getMinutes() / 15) * 15;
                        if (defaultMinutes >= 60) {
                            defaultHours = (defaultHours + 1) % 24;
                            defaultMinutes = 0;
                        }
                        if (defaultHours < 15 || (defaultHours === 15 && defaultMinutes < 30) ) {
                            defaultHours = 15;
                            defaultMinutes = 30;
                        }
                    }
                }
                plannedTimeInput.value = `${String(defaultHours).padStart(2, '0')}:${String(defaultMinutes).padStart(2, '0')}`;
                notesInput.value = '';
                cancelLateBtnInModal.style.display = 'none';
            }
            plannedTimeInput.min = "15:30";
        }

        if (notifyLateBtn) { 
            notifyLateBtn.addEventListener('click', () => openLateModal(false));
        }
        if (editLateBtn) { 
            editLateBtn.addEventListener('click', () => openLateModal(true));
        }

        if (lateModal) {
            modalCloseButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    lateModal.classList.remove('show');
                    document.body.style.overflow = '';
                });
            });
            window.addEventListener('click', (event) => {
                if (event.target === lateModal) {
                    lateModal.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });
        }

        if (lateForm && modalMsgDiv && modalSubmitButton) {
            lateForm.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const plannedTime = formData.get('planned_departure_time');
                
                modalMsgDiv.textContent = '';
                modalMsgDiv.className = 'form-message';

                if (!plannedTime) { modalMsgDiv.textContent = 'Please enter a planned departure time.'; modalMsgDiv.classList.add('error'); return; }
                if (plannedTime < "15:30") { modalMsgDiv.textContent = 'Planned departure time must be 15:30 or later.'; modalMsgDiv.classList.add('error'); return; }
                
                modalSubmitButton.disabled = true;

                fetch('dashboard.php', { method: 'POST', body: formData })
                .then(response => {
                    if (!response.ok) { return response.json().then(errData => { throw { status: response.status, data: errData }; });}
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        modalMsgDiv.textContent = data.message || 'Operation successful!';
                        modalMsgDiv.classList.add('success');
                        setTimeout(() => { window.location.reload(); }, 1500); 
                    } else {
                        modalMsgDiv.textContent = data.message || 'An error occurred.';
                        modalMsgDiv.classList.add('error');
                    }
                })
                .catch(error => { 
                    console.error('Form submission error:', error);
                    if (error && error.data && error.data.message) modalMsgDiv.textContent = error.data.message;
                    else if (error && error.message) modalMsgDiv.textContent = error.message;
                    else modalMsgDiv.textContent = 'A network or server error occurred.';
                    modalMsgDiv.classList.add('error');
                 })
                .finally(() => { modalSubmitButton.disabled = false; });
            });
        }

        if (cancelLateBtnInModal) {
            cancelLateBtnInModal.addEventListener('click', function() {
                if (!confirm("Are you sure you want to cancel your late departure notification for today?")) {
                    return;
                }
                if (modalMsgDiv) {
                     modalMsgDiv.textContent = '';
                     modalMsgDiv.className = 'form-message';
                }

                const formData = new FormData();
                formData.append('action', 'cancel_late_departure');
                // notification_date se na serveru vezme jako $todayDateForAction
                
                this.disabled = true;

                fetch('dashboard.php', { method: 'POST', body: formData })
                .then(response => {
                    if (!response.ok) { return response.json().then(errData => { throw { status: response.status, data: errData }; });}
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        if(modalMsgDiv) {
                            modalMsgDiv.textContent = data.message || 'Late departure cancelled.';
                            modalMsgDiv.classList.add('success');
                        }
                        setTimeout(() => { window.location.reload(); }, 1500); 
                    } else {
                        if(modalMsgDiv) {
                            modalMsgDiv.textContent = data.message || 'Failed to cancel.';
                            modalMsgDiv.classList.add('error');
                        }
                    }
                })
                .catch(error => { 
                    console.error('Error cancelling late departure:', error);
                    if(modalMsgDiv) {
                        if (error && error.data && error.data.message) modalMsgDiv.textContent = error.data.message;
                        else if (error && error.message) modalMsgDiv.textContent = error.message;
                        else modalMsgDiv.textContent = 'A network or server error occurred.';
                        modalMsgDiv.classList.add('error');
                    }
                })
                .finally(() => { this.disabled = false; });
            });
        }
    });
</script>
</body>
</html>