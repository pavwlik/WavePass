<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- SESSION CHECK & ADMIN ROLE CHECK ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    header("location: ../login.php");
    exit;
}

// --- AJAX REQUEST HANDLING FOR ADMIN'S LATE DEPARTURE ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && 
    ($_POST['action'] === 'submit_late_departure' || $_POST['action'] === 'cancel_late_departure')) {
    
    header('Content-Type: application/json');
    if (!file_exists('../db.php')) { 
        echo json_encode(['success' => false, 'message' => 'Database configuration file (db.php) not found for AJAX.']);
        exit;
    }
    require_once '../db.php'; 

    $response = ['success' => false, 'message' => 'An unknown error occurred with the AJAX request for late departure.'];

    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["user_id"])) {
        http_response_code(401); 
        $response['message'] = 'User not authenticated. Please log in.';
        echo json_encode($response);
        exit;
    }
    $adminUserIDForLateAction = (int)$_SESSION["user_id"]; // Admin acts for themselves
    $todayDateForLateAction = date('Y-m-d'); 

    if (!isset($pdo) || !($pdo instanceof PDO)) { 
        http_response_code(503);
        $response['message'] = 'Database connection not available for AJAX processing.';
        echo json_encode($response);
        exit;
    }

    if ($_POST['action'] === 'submit_late_departure') {
        $planned_departure_time_str = trim($_POST['planned_departure_time'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($planned_departure_time_str) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $planned_departure_time_str)) {
            http_response_code(400);
            $response['message'] = 'Invalid planned departure time format. Please use HH:MM.';
            echo json_encode($response);
            exit;
        }
        if (strtotime($planned_departure_time_str) < strtotime('15:30:00')) { // You might want a different time for admins, or make it configurable
            http_response_code(400);
            $response['message'] = 'Planned departure time must be 15:30 or later.';
            echo json_encode($response);
            exit;
        }
        $planned_departure_time_db = date("H:i:s", strtotime($planned_departure_time_str));

        // Check if admin is present if this is a NEW submission
        $stmtCheckPresenceForNew = $pdo->prepare("SELECT logType, logResult FROM attendance_logs WHERE userID = :userid AND DATE(logTime) = :today_date ORDER BY logTime DESC LIMIT 1");
        $stmtCheckPresenceForNew->execute([':userid' => $adminUserIDForLateAction, ':today_date' => $todayDateForLateAction]);
        $latestEventForPresence = $stmtCheckPresenceForNew->fetch(PDO::FETCH_ASSOC);
        $isActuallyPresent = ($latestEventForPresence && $latestEventForPresence['logType'] == 'entry' && $latestEventForPresence['logResult'] == 'granted');
        $stmtCheckPresenceForNew->closeCursor();

        $stmtCheckExisting = $pdo->prepare("SELECT notificationID FROM late_departure_notifications WHERE userID = :userID AND notification_date = :notification_date");
        $stmtCheckExisting->execute([':userID' => $adminUserIDForLateAction, ':notification_date' => $todayDateForLateAction]);
        $existingNotificationCheck = $stmtCheckExisting->fetch(PDO::FETCH_ASSOC);
        $stmtCheckExisting->closeCursor();

        if (!$existingNotificationCheck && !$isActuallyPresent) {
            http_response_code(403); 
            $response['message'] = 'You (Admin) must be currently present to notify a new late exit.';
            echo json_encode($response);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $stmtCheck = $pdo->prepare("SELECT notificationID FROM late_departure_notifications WHERE userID = :userID AND notification_date = :notification_date");
            $stmtCheck->execute([':userID' => $adminUserIDForLateAction, ':notification_date' => $todayDateForLateAction]);
            $existingNotification = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            $stmtCheck->closeCursor();

            if ($existingNotification) {
                $stmt = $pdo->prepare(
                    "UPDATE late_departure_notifications 
                     SET planned_departure_time = :planned_departure_time, notes = :notes, viewed_by_admin = 0, created_at = CURRENT_TIMESTAMP
                     WHERE notificationID = :notificationID"
                ); // viewed_by_admin might be always 1 or irrelevant if admin sets it for themselves
                $stmt->execute([
                    ':planned_departure_time' => $planned_departure_time_db,
                    ':notes' => !empty($notes) ? $notes : null,
                    ':notificationID' => $existingNotification['notificationID']
                ]);
                $response['message'] = 'Your late departure notification updated successfully!';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO late_departure_notifications (userID, notification_date, planned_departure_time, notes)
                     VALUES (:userID, :notification_date, :planned_departure_time, :notes)"
                );
                $stmt->execute([
                    ':userID' => $adminUserIDForLateAction,
                    ':notification_date' => $todayDateForLateAction,
                    ':planned_departure_time' => $planned_departure_time_db,
                    ':notes' => !empty($notes) ? $notes : null
                ]);
                $response['message'] = 'Your late departure notification submitted successfully!';
            }
            $pdo->commit();
            $response['success'] = true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("AJAX Admin Late Departure DB Error (Admin User: {$adminUserIDForLateAction}): " . $e->getMessage());
            http_response_code(500);
            $response['message'] = 'A database error occurred while submitting the notification.';
        }
        
    } elseif ($_POST['action'] === 'cancel_late_departure') {
        try {
            $stmtDelete = $pdo->prepare(
                "DELETE FROM late_departure_notifications 
                 WHERE userID = :userID AND notification_date = :notification_date"
            );
            if ($stmtDelete->execute([':userID' => $adminUserIDForLateAction, ':notification_date' => $todayDateForLateAction])) {
                $response['success'] = true;
                $response['message'] = $stmtDelete->rowCount() > 0 ? 'Your late departure notification cancelled.' : 'No notification to cancel for today.';
            } else {
                http_response_code(500);
                $response['message'] = 'Failed to execute cancellation.';
            }
        } catch (PDOException $e) {
            error_log("AJAX Admin Cancel Late Departure DB Error (Admin User: {$adminUserIDForLateAction}): " . $e->getMessage());
            http_response_code(500);
            $response['message'] = 'A database error occurred during cancellation.';
        }
    }
    echo json_encode($response);
    exit; 
}
// --- END OF AJAX REQUEST HANDLING ---

require_once '../db.php';

// --- SESSION VARIABLES ---
$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;

// --- DATE HANDLING ---
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || strtotime($selectedDate) === false) {
    $selectedDate = date('Y-m-d');
}
$todayDate = date('Y-m-d');
$todayDateTime = date('Y-m-d H:i:s');

// --- DEFAULT VALUES FOR ADMIN'S OWN DISPLAY ---
$adminRfidStatus = "Status Unknown";
$adminRfidStatusClass = "neutral";
$adminActivityForSelectedDate = [];
$dbErrorMessage = null;
$adminCurrentUserData = null;
$existingLateDepartureForAdmin = null; // For Admin's own late departure

// Variables for Late Departures (of other users)
$lateDeparturesTodayDetails = [];
$lateDepartureCount = 0;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $dbErrorMessage = "Database connection is not available. Please check server configuration or db.php.";
} elseif (!$sessionUserId) {
    $dbErrorMessage = "Admin session is invalid or user ID not found. Please log in again.";
} else {
    try {
        // 1. Fetch admin's own dateOfCreation
        $stmtAdminMeta = $pdo->prepare("SELECT dateOfCreation FROM users WHERE userID = :userid_param_meta");
        $stmtAdminMeta->bindParam(':userid_param_meta', $sessionUserId, PDO::PARAM_INT);
        $stmtAdminMeta->execute();
        $adminCurrentUserData = $stmtAdminMeta->fetch(PDO::FETCH_ASSOC);
        $stmtAdminMeta->closeCursor();

        // 2. DETERMINE ADMIN'S OWN CURRENT PRESENCE STATUS
        $stmtAdminLatestEvent = $pdo->prepare(
            "SELECT logTime, logType, logResult FROM attendance_logs
             WHERE userID = :userid_param_event AND DATE(logTime) = :today_date_param_event
             ORDER BY logTime DESC LIMIT 1"
        );
        $stmtAdminLatestEvent->bindParam(':userid_param_event', $sessionUserId, PDO::PARAM_INT);
        $stmtAdminLatestEvent->bindParam(':today_date_param_event', $todayDate);
        $stmtAdminLatestEvent->execute();
        $adminLatestEvent = $stmtAdminLatestEvent->fetch(PDO::FETCH_ASSOC);
        $stmtAdminLatestEvent->closeCursor();

        if ($adminLatestEvent) {
            if ($adminLatestEvent['logType'] == 'entry' && $adminLatestEvent['logResult'] == 'granted') {
                $adminRfidStatus = "Present"; $adminRfidStatusClass = "present";
            } elseif ($adminLatestEvent['logType'] == 'exit' && $adminLatestEvent['logResult'] == 'granted') {
                $adminRfidStatus = "Checked Out"; $adminRfidStatusClass = "checked-out"; // Or "absent"

                // Auto-cancel admin's own late departure if they left earlier
                $stmtCheckAdminPlannedLate = $pdo->prepare(
                    "SELECT planned_departure_time FROM late_departure_notifications 
                     WHERE userID = :admin_userid AND notification_date = :today_date LIMIT 1"
                );
                $stmtCheckAdminPlannedLate->execute([':admin_userid' => $sessionUserId, ':today_date' => $todayDate]);
                $adminPlannedLateInfo = $stmtCheckAdminPlannedLate->fetch(PDO::FETCH_ASSOC);
                $stmtCheckAdminPlannedLate->closeCursor();

                if ($adminPlannedLateInfo) {
                    $actualCheckoutTimeStr = date("H:i:s", strtotime($adminLatestEvent['logTime']));
                    if (strtotime($actualCheckoutTimeStr) < strtotime($adminPlannedLateInfo['planned_departure_time'])) {
                        $stmtCancelAdminLateAuto = $pdo->prepare(
                            "DELETE FROM late_departure_notifications 
                             WHERE userID = :admin_userid AND notification_date = :today_date"
                        );
                        $stmtCancelAdminLateAuto->execute([':admin_userid' => $sessionUserId, ':today_date' => $todayDate]);
                    }
                }
            } elseif ($adminLatestEvent['logResult'] == 'denied') {
                $adminRfidStatus = ($adminLatestEvent['logType'] == 'entry' ? "Entry Denied" : "Exit Denied"); $adminRfidStatusClass = "danger";
            } else {
                $adminRfidStatus = "Status Unknown"; $adminRfidStatusClass = "neutral";
            }
        } else {
            // Check for admin's scheduled absence
            $stmtAdminScheduledAbsenceToday = $pdo->prepare(
                "SELECT absence_type FROM absence WHERE userID = :userid_param_absence
                 AND :today_date_time_param_absence BETWEEN absence_start_datetime AND absence_end_datetime
                 AND status = 'approved' LIMIT 1"
            );
            $stmtAdminScheduledAbsenceToday->bindParam(':userid_param_absence', $sessionUserId, PDO::PARAM_INT);
            $stmtAdminScheduledAbsenceToday->bindParam(':today_date_time_param_absence', $todayDateTime);
            $stmtAdminScheduledAbsenceToday->execute();
            $adminScheduledAbsence = $stmtAdminScheduledAbsenceToday->fetch(PDO::FETCH_ASSOC);
            $stmtAdminScheduledAbsenceToday->closeCursor();
            if ($adminScheduledAbsence) {
                $absenceTypeDisplay = ucfirst(str_replace('_', ' ', $adminScheduledAbsence['absence_type']));
                $adminRfidStatus = "Scheduled " . htmlspecialchars($absenceTypeDisplay); $adminRfidStatusClass = "absent";
            } else {
                $adminRfidStatus = "Not Checked In"; $adminRfidStatusClass = "neutral";
            }
        }
        
        // Fetch Admin's OWN late departure notification
        $stmtExistingLateForAdmin = $pdo->prepare("SELECT planned_departure_time, notes FROM late_departure_notifications WHERE userID = :userid AND notification_date = :today_date LIMIT 1");
        $stmtExistingLateForAdmin->execute([':userid' => $sessionUserId, ':today_date' => $todayDate]);
        $existingLateDepartureForAdmin = $stmtExistingLateForAdmin->fetch(PDO::FETCH_ASSOC);
        $stmtExistingLateForAdmin->closeCursor();


        // Fetch today's late departure notifications (for other users, for the modal)
        $stmtLate = $pdo->prepare(
            "SELECT ldn.userID, ldn.planned_departure_time, ldn.notes, u.firstName, u.lastName
             FROM late_departure_notifications ldn
             JOIN users u ON ldn.userID = u.userID
             WHERE ldn.notification_date = :today_date_late AND ldn.userID != :admin_id_late  -- Exclude admin's own
             ORDER BY ldn.planned_departure_time ASC, u.lastName ASC, u.firstName ASC"
        );
        $stmtLate->bindParam(':today_date_late', $todayDate);
        $stmtLate->bindParam(':admin_id_late', $sessionUserId, PDO::PARAM_INT);
        $stmtLate->execute();
        $lateDeparturesTodayDetails = $stmtLate->fetchAll(PDO::FETCH_ASSOC);
        $lateDepartureCount = count($lateDeparturesTodayDetails);
        $stmtLate->closeCursor();

        // 6. ADMIN'S OWN ACTIVITY SNAPSHOT FOR SELECTED DATE
        // ... (táto časť zostáva rovnaká) ...
        if ($adminCurrentUserData && isset($adminCurrentUserData['dateOfCreation']) && $selectedDate == date("Y-m-d", strtotime($adminCurrentUserData['dateOfCreation']))) {
            $adminActivityForSelectedDate[] = [
                'time' => date("H:i", strtotime($adminCurrentUserData['dateOfCreation'])),
                'original_db_log_type' => 'system_account_created',
                'log_type' => 'System',
                'log_result' => 'Info',
                'details' => 'Your Admin account was created.',
                'rfid_card_uid' => 'N/A',
                'status_class' => 'info'
            ];
        }
        $sqlAdminActivity = "SELECT al.logTime, al.logType, al.logResult, al.rfid_uid_used
                             FROM attendance_logs al
                             WHERE al.userID = :admin_userid_activity AND DATE(al.logTime) = :selected_date_activity
                             ORDER BY al.logTime DESC";
        $stmtAdminActivityLog = $pdo->prepare($sqlAdminActivity);
        $stmtAdminActivityLog->bindParam(':admin_userid_activity', $sessionUserId, PDO::PARAM_INT);
        $stmtAdminActivityLog->bindParam(':selected_date_activity', $selectedDate);
        $stmtAdminActivityLog->execute();

        while ($log = $stmtAdminActivityLog->fetch(PDO::FETCH_ASSOC)) {
            $logTimeFromDB = $log['logTime'] ?? date('Y-m-d H:i:s');
            $logTypeFromDB = $log['logType'] ?? 'undefined';
            $logResultFromDB = $log['logResult'] ?? 'undefined';
            $rfidUidFromDB = $log['rfid_uid_used'] ?? null;
            $logTypeDisplay = 'Event'; $statusClass = 'neutral'; $detailsDisplay = 'Activity recorded.';
            switch ($logTypeFromDB) { /* ... vaša existujúca switch logika ... */ 
                case 'entry': $logTypeDisplay = 'Entry'; $detailsDisplay = ($logResultFromDB == 'granted') ? 'Access granted.' : 'Access attempt denied.'; $statusClass = ($logResultFromDB == 'granted') ? 'present' : 'danger'; break;
                case 'exit': $logTypeDisplay = 'Exit'; $detailsDisplay = ($logResultFromDB == 'granted') ? 'Exit recorded.' : 'Exit attempt denied.'; $statusClass = ($logResultFromDB == 'granted') ? 'checked-out' : 'danger'; break;
                case 'auto_registered': $logTypeDisplay = 'Card Auto-Registered'; $detailsDisplay = 'Card auto-registration event.'; $statusClass = ($logResultFromDB == 'info') ? 'info' : (($logResultFromDB == 'denied') ? 'warning' : 'neutral'); break;
                case 'unknown_card_scan': $logTypeDisplay = 'Unknown Card Scan'; $detailsDisplay = 'Unrecognized card scan attempt.'; $statusClass = 'warning'; break;
                case 'unassigned_card_attempt': $logTypeDisplay = 'Unassigned Card Use'; $detailsDisplay = 'Attempt to use an unassigned card.'; $statusClass = 'danger'; break;
                default: $logTypeDisplay = ucfirst(str_replace('_', ' ', $logTypeFromDB)); $detailsDisplay = 'Unspecified admin activity event.'; if ($logResultFromDB == 'denied') $statusClass = 'danger'; elseif ($logResultFromDB == 'info') $statusClass = 'info'; else $statusClass = 'neutral'; break;
            }
            $adminActivityForSelectedDate[] = [
                'time' => date("H:i", strtotime($logTimeFromDB)), 'original_db_log_type' => $logTypeFromDB,
                'log_type' => htmlspecialchars($logTypeDisplay), 'log_result' => htmlspecialchars(ucfirst($logResultFromDB)),
                'details' => htmlspecialchars($detailsDisplay), 'rfid_card_uid' => !empty($rfidUidFromDB) ? htmlspecialchars($rfidUidFromDB) : 'N/A',
                'status_class' => $statusClass
            ];
        }
        $stmtAdminActivityLog->closeCursor();
        // ... (zvyšok fallback logiky pre adminovu aktivitu zostáva rovnaký) ...
        $adminHasCheckInOutActivity = false;
        foreach($adminActivityForSelectedDate as $act) { if (isset($act['original_db_log_type']) && ($act['original_db_log_type'] === 'entry' || $act['original_db_log_type'] === 'exit')) { $adminHasCheckInOutActivity = true; break; } }
        if ($selectedDate == $todayDate && !$adminHasCheckInOutActivity && $adminRfidStatus !== "Status Unknown" && $adminRfidStatus !== "Not Checked In") {
            if ($adminRfidStatusClass !== 'neutral' || strpos(strtolower($adminRfidStatus), 'scheduled') !== false) {
                $adminActivityForSelectedDate[] = [
                    'time' => date("H:i"), 'original_db_log_type' => 'system_current_status',
                    'log_type' => 'Current Status', 'log_result' => htmlspecialchars($adminRfidStatus),
                    'details' => 'Based on latest system information (admin).', 'rfid_card_uid' => 'N/A',
                    'status_class' => $adminRfidStatusClass
                ];
            }
        }
        if (empty($adminActivityForSelectedDate) && $selectedDate <= $todayDate){
            $stmtAdminSelectedDayAbsence = $pdo->prepare("SELECT absence_type, reason FROM absence WHERE userID = :userid_param AND :selected_date_for_absence_param BETWEEN DATE(absence_start_datetime) AND DATE(absence_end_datetime) AND status = 'approved' LIMIT 1");
            $stmtAdminSelectedDayAbsence->bindParam(':userid_param', $sessionUserId, PDO::PARAM_INT);
            $stmtAdminSelectedDayAbsence->bindParam(':selected_date_for_absence_param', $selectedDate);
            $stmtAdminSelectedDayAbsence->execute();
            $adminSelectedDayAbsenceInfo = $stmtAdminSelectedDayAbsence->fetch(PDO::FETCH_ASSOC);
            $stmtAdminSelectedDayAbsence->closeCursor();
            if($adminSelectedDayAbsenceInfo){
                $absenceTypeDetailForMsg = htmlspecialchars(ucfirst(str_replace('_',' ',$adminSelectedDayAbsenceInfo['absence_type'])));
                $adminActivityForSelectedDate[] = [
                     'time' => '--:--', 'original_db_log_type' => 'system_scheduled_absence',
                     'log_type' => 'Scheduled Absence', 'log_result' => htmlspecialchars($absenceTypeDetailForMsg),
                     'details' => (!empty($adminSelectedDayAbsenceInfo['reason']) ? 'Reason: '.htmlspecialchars($adminSelectedDayAbsenceInfo['reason']) : 'Approved absence'),
                     'rfid_card_uid' => 'N/A', 'status_class' => 'absent'
                    ];
            } else {
                 if(empty($adminActivityForSelectedDate)) {
                    $adminActivityForSelectedDate[] = [
                         'time' => '--:--', 'original_db_log_type' => 'system_no_record',
                         'log_type' => 'No Record', 'log_result' => 'N/A',
                         'details' => 'No personal attendance events logged for this day.',
                         'rfid_card_uid' => 'N/A', 'status_class' => 'neutral'
                        ];
                }
            }
        }
        if (!empty($adminActivityForSelectedDate)) {
            usort($adminActivityForSelectedDate, function($a, $b) {
                $timeAIsSpecial = ($a['time'] === '--:--'); $timeBIsSpecial = ($b['time'] === '--:--');
                if ($timeAIsSpecial && $timeBIsSpecial) return 0; if ($timeAIsSpecial) return 1; if ($timeBIsSpecial) return -1;
                return strtotime($b['time']) - strtotime($a['time']);
            });
        }

    } catch (PDOException $e) {
        error_log("Admin Dashboard DB Error (Admin UserID: {$sessionUserId}, Selected Date: {$selectedDate}): " . $e->getMessage() . " --- SQL Query that failed (if available in trace): " . ($e->getTrace()[0]['args'][0] ?? 'N/A'));
        $dbErrorMessage = "A database error occurred (PDO). Please check the server logs (PHP error log) for the exact SQL error and query. The error message was: " . htmlspecialchars($e->getMessage());
    } catch (Exception $e) {
        error_log("Admin Dashboard App Error (Admin UserID: {$sessionUserId}, Selected Date: {$selectedDate}): " . $e->getMessage());
        $dbErrorMessage = "An application error occurred. Please try again later.";
    }
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../imgs/logo.png" type="image/x-icon">
    <title>Admin Dashboard - <?php echo $sessionFirstName; ?> - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Root variables and base styles from dashboard.php */
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4cc9f0; --warning-color: #f8961e; --danger-color: #f72585; 
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
            --primary-color-val: 67, 97, 238; --present-color-val: 67, 170, 139; 
            --absent-color-val: 214, 40, 40; --info-color-val: 84, 160, 255;    
            --neutral-color-val: 173, 181, 189; --warning-color-val: 248, 150, 30; 
            --danger-color-val: 247, 37, 133; --late-departure-icon-color-val: 23, 162, 184;
            --checked-out-color-val: 214, 40, 40; ; 


            --present-color: rgb(var(--present-color-val)); 
            --absent-color: rgb(var(--absent-color-val)); 
            --info-color: rgb(var(--info-color-val));
            --neutral-color: rgb(var(--neutral-color-val));
            --late-departure-icon-color: rgb(var(--late-departure-icon-color-val));
            --checked-out-color: rgb(var(--checked-out-color-val));
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; padding-top: 80px; }
        main { flex-grow: 1; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
        h1, h2, h3, h4 { font-weight: 700; line-height: 1.2; }
        
        /* Page Header from admin-dashboard */
        .page-header { padding: 1.8rem 0; margin-bottom: 1.8rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; color: var(--dark-color); margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        .db-error-message {background-color: rgba(var(--danger-color-val),0.1); color: var(--danger-color); padding: 1rem; border-left: 4px solid var(--danger-color); margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}

        /* Admin Stats Grid & Stat Card (slight adjustments from dashboard.php might be needed) */
        .admin-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        .stat-card { background-color: var(--white); padding: 1.2rem 1.5rem; border-radius: 8px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1.2rem; transition: var(--transition); border: 1px solid var(--light-gray); position:relative; overflow:hidden;}
        .stat-card::before { content: ''; position:absolute; top:0; left:0; width:5px; height:100%; background-color:transparent; transition: var(--transition); }
        .stat-card:hover{transform: translateY(-4px); box-shadow: 0 6px 28px rgba(0,0,0,0.09);}
        .stat-card .icon { font-size: 2.2rem; padding: 0.8rem; border-radius: 50%; display:flex; align-items:center; justify-content:center; width:55px; height:55px; flex-shrink:0;}
        .stat-card .info { flex-grow: 1; }
        .stat-card .info .value { font-size: 1.8rem; font-weight: 500; color: var(--dark-color); display:block; line-height:1.1; margin-bottom:0.3rem;}
        .stat-card .info .label { font-size: 0.8rem; color: var(--gray-color); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .stat-card-button { background-color: var(--primary-color); color: var(--white); border: none; padding: 0.6rem 1rem; border-radius: 5px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: background-color 0.2s; margin-left: auto; white-space: nowrap; align-self: center; }
        .stat-card .stat-card-button:hover { background-color: var(--primary-dark); }
        
        /* RFID Status specific styles (can be reused from dashboard.php) */
        .stat-card.rfid-status.present::before { background-color: var(--present-color); }
        .stat-card.rfid-status.present .icon { background-color: rgba(var(--present-color-val),0.1); color: var(--present-color); }
        .stat-card.rfid-status.absent::before { background-color: var(--absent-color); }
        .stat-card.rfid-status.absent .icon { background-color: rgba(var(--absent-color-val),0.1); color: var(--absent-color); }
        .stat-card.rfid-status.checked-out::before { background-color: var(--checked-out-color); } /* Ensure this matches admin-dashboard if different from absent */
        .stat-card.rfid-status.checked-out .icon { background-color: rgba(var(--checked-out-color-val),0.15); color: var(--checked-out-color); }
        .stat-card.rfid-status.neutral::before { background-color: var(--neutral-color); }
        .stat-card.rfid-status.neutral .icon { background-color: rgba(var(--neutral-color-val),0.1); color: var(--neutral-color); }
        .stat-card.rfid-status.danger::before { background-color: var(--danger-color); }
        .stat-card.rfid-status.danger .icon { background-color: rgba(var(--danger-color-val),0.1); color: var(--danger-color); }
        
        /* Late Departure Action Card Styles from dashboard.php */
        .stat-card.action-card { align-items: stretch; }
        .stat-card.action-card .info { display: flex; flex-direction: column; justify-content: center; }
        .stat-card.action-card::before { background-color: var(--primary-color); }
        .stat-card.action-card .icon { background-color: rgba(var(--primary-color-val),0.1); color: var(--primary-color); }
        .btn-action-card { background-color: var(--primary-color); color: var(--white); border: none; padding: 0.7rem 1.3rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: var(--transition); display: inline-block; text-align: center; margin-bottom: 0.4rem;}
        .btn-action-card:hover:not(:disabled) { background-color: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(var(--primary-color-val), 0.2);}
        .btn-action-card:disabled { background-color: var(--gray-color); cursor: not-allowed; opacity: 0.7; }
        .stat-card.action-card .info .label { font-size: 0.8rem; color: var(--gray-color); margin-top: 0.2rem; text-transform: none; letter-spacing: normal;}
        .btn-action-card.btn-edit-late { background-color: var(--warning-color); color: var(--dark-color); font-size: 0.85rem; padding: 0.6rem 1rem;}
        .btn-action-card.btn-edit-late:hover { background-color: #e7860a; }
        .stat-card.action-card .info .value#displayedLateTime { font-size: 1.2rem; font-weight: 600; color: var(--primary-color); margin-bottom: 0.5rem;}
        .stat-card.action-card .info small#displayedLateNotes i.fa-sticky-note { color: var(--gray-color); margin-right: 5px; }
        
        .stat-card.admin-late-departures::before { background-color: var(--late-departure-icon-color); }
        .stat-card.admin-late-departures .icon { background-color: rgba(var(--late-departure-icon-color-val),0.1); color: var(--late-departure-icon-color); }

        /* Content Panel & Activity Table Styles (can be reused) */
        .content-panel { background-color: var(--white); padding: 1.5rem 1.8rem; border-radius: 8px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); margin-bottom: 2rem; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); }
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
        .activity-status .material-symbols-outlined { font-size: 1.1em; }
        .activity-status.present { background-color: rgba(var(--present-color-val), 0.15); color: var(--present-color); }
        .activity-status.absent { background-color: rgba(var(--absent-color-val), 0.15); color: var(--absent-color); }
        .activity-status.checked-out { background-color: rgba(var(--checked-out-color-val), 0.15); color: var(--checked-out-color); }
        .activity-status.info { background-color: rgba(var(--info-color-val), 0.15); color: var(--info-color); }
        .activity-status.neutral { background-color: rgba(var(--neutral-color-val),0.15); color: var(--neutral-color);}
        .activity-status.danger { background-color: rgba(var(--danger-color-val),0.15); color: var(--danger-color);} /* Corrected from rgb() */
        .activity-status.warning { background-color: rgba(var(--warning-color-val),0.15); color: var(--warning-color);}
        .activity-table td.rfid-cell { font-family: monospace; font-size: 0.8rem; color: var(--gray-color); }
        .no-activity-msg {text-align:center; padding: 2.5rem 1rem; color:var(--gray-color); font-size:0.95rem; background-color: #fdfdfd; border-radius: 4px; border: 1px dashed var(--light-gray);}
        
        /* Modal Styles from dashboard.php (for late departure) */
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
        .modal-actions .btn-submit:hover:not(:disabled) {background-color: var(--primary-dark); box-shadow: 0 4px 8px rgba(var(--primary-color-val), 0.3); transform: translateY(-1px);}
        .modal-actions .btn-submit:disabled { background-color: var(--gray-color); cursor: not-allowed; }
        .modal-actions .btn-cancel {background-color: var(--light-gray); color: var(--dark-color); border: 1px solid #d3d9df;}
        .modal-actions .btn-cancel:hover {background-color: #d3d9df; }
        .modal-actions .btn-danger.btn-cancel-late { background-color: var(--danger-color); color: var(--white); margin-right: auto; }
        .modal-actions .btn-danger.btn-cancel-late:hover:not(:disabled) { background-color: #d4166a; } /* Adjusted from dashboard.php to use var(--danger-color) consistent variant */
        .modal-actions .btn-danger.btn-cancel-late:disabled { background-color: var(--gray-color); cursor: not-allowed; }
        .form-message {margin-top: 1rem; padding: 0.8rem 1rem; border-radius: 5px; font-size: 0.9rem; display: none; border-left-width: 4px; border-left-style: solid;}
        .form-message.success {background-color: rgba(var(--present-color-val), 0.1); color: var(--present-color); border-left-color: var(--present-color); display: block;}
        .form-message.error {background-color: rgba(var(--danger-color-val), 0.1); color: var(--danger-color); border-left-color: var(--danger-color); display: block;}

        /* Modal for Admin View Late Departures (from admin-dashboard) */
        .admin-view-modal { display: none; position: fixed; z-index: 1070; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7); align-items: center; justify-content: center; padding: 20px;}
        .admin-view-modal.show { display: flex; }
        .admin-view-modal-content { background-color: var(--white); margin: auto; padding: 25px 30px; border-radius: 10px; width: 90%; max-width: 800px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); position: relative; animation: fadeInModal 0.3s ease-out; }
        .admin-view-modal-content h2 { margin-top: 0; margin-bottom: 1.5rem; font-size: 1.6rem; color: var(--primary-color); text-align: center; padding-bottom: 0.8rem; border-bottom: 1px solid var(--light-gray); }
        .admin-view-close-modal-btn { color: var(--gray-color); background: transparent; border: none; position: absolute; top: 15px; right: 18px; font-size: 1.9rem; font-weight: bold; line-height: 1; padding: 0.2rem 0.5rem; cursor: pointer; transition: color 0.2s ease; }
        .admin-view-close-modal-btn:hover { color: var(--dark-color); }
        table.admin-late-departure-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: 0.9rem; }
        table.admin-late-departure-table th, table.admin-late-departure-table td { padding: 0.6rem 0.8rem; text-align: left; border-bottom: 1px solid var(--light-gray); }
        table.admin-late-departure-table th { background-color: #f9fafb; font-weight: 600; color: var(--dark-color); }
        table.admin-late-departure-table tbody tr:hover { background-color: #f5f5f5; }
        .admin-view-modal-actions { margin-top: 1.8rem; text-align: right; }
        .admin-view-btn-secondary { background-color: var(--gray-color); color: var(--white); border: none; padding: 0.7rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; transition: background-color 0.2s; font-size: 0.9rem; }
        .admin-view-btn-secondary:hover { background-color: var(--dark-color); }
    </style>
</head>
<body>
    <?php require_once "../components/header-admin.php"; ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>Admin Dashboard</h1>
                <p class="sub-heading">Overview for <?php echo $sessionFirstName; ?>.</p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if (isset($dbErrorMessage) && $dbErrorMessage): ?>
                <div class="db-error-message" role="alert">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i> <?php echo $dbErrorMessage; ?>
                </div>
            <?php endif; ?>

            <section class="admin-stats-grid">
                 <div class="stat-card rfid-status <?php echo htmlspecialchars($adminRfidStatusClass); ?>">
                    <div class="icon"><span class="material-symbols-outlined">
                        <?php
                            if ($adminRfidStatusClass === "present") echo "admin_panel_settings";
                            elseif ($adminRfidStatusClass === "absent" && strpos($adminRfidStatus, "Scheduled") !== false) echo "event_busy";
                            elseif ($adminRfidStatusClass === "checked-out") echo "logout";
                            elseif ($adminRfidStatusClass === "danger") echo "gpp_maybe";
                            else echo "contactless";
                        ?>
                    </span></div>
                    <div class="info">
                        <span class="value"><?php echo htmlspecialchars($adminRfidStatus); ?></span>
                        <span class="label">Your Current Status</span>
                    </div>
                </div>
                
                <!-- Admin's Late Departure Action Card -->
                <div class="stat-card action-card" id="adminLateDepartureActionCard">
                    <div class="icon"><span class="material-symbols-outlined">schedule_send</span></div>
                    <div class="info">
                        <?php if ($existingLateDepartureForAdmin): ?>
                            <span class="value" id="displayedAdminLateTime">Planned: <?php echo date("H:i", strtotime($existingLateDepartureForAdmin['planned_departure_time'])); ?></span>
                            <?php if (!empty($existingLateDepartureForAdmin['notes'])): ?>
                                <small class="placeholder-text" id="displayedAdminLateNotes" title="<?php echo htmlspecialchars($existingLateDepartureForAdmin['notes']); ?>">
                                    <i class="fas fa-sticky-note"></i> Note recorded
                                </small>
                            <?php endif; ?>
                            <button id="editAdminLateDepartureBtn" class="btn-action-card btn-edit-late">Change / Cancel</button>
                        <?php else: ?>
                            <button id="notifyAdminLateDepartureBtn" class="btn-action-card" <?php if ($adminRfidStatusClass !== 'present') echo 'disabled title="You must be present to notify a new late exit."'; ?>>Notify Your Late Exit</button>
                            <span class="label">
                                <?php if ($adminRfidStatusClass !== 'present'): ?>
                                    You must be present to notify a new late exit.
                                <?php else: ?>
                                    Staying past 15:30? Let us know.
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="stat-card admin-late-departures">
                    <div class="icon"><span class="material-symbols-outlined">history_edu</span></div>
                    <div class="info">
                        <span class="value"><?php echo $lateDepartureCount; ?></span>
                        <span class="label">Other Users Staying Late</span>
                    </div>
                    <?php if ($lateDepartureCount > 0): ?>
                        <button type="button" class="stat-card-button" id="viewLateDeparturesDetailsBtn">View Details</button>
                    <?php endif; ?>
                </div>
            </section>

            <section class="content-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Your Activity for <span id="selectedDateDisplay"><?php echo date("F d, Y", strtotime($selectedDate)); ?></span></h2>
                    <div class="date-navigation">
                        <button class="btn-nav" id="prevDayBtn" title="Previous Day"><span class="material-symbols-outlined">chevron_left</span></button>
                        <input type="date" id="activity-date-selector" value="<?php echo htmlspecialchars($selectedDate); ?>" max="<?php echo $todayDate; ?>">
                        <button class="btn-nav" id="nextDayBtn" title="Next Day" <?php if ($selectedDate >= $todayDate) echo 'disabled'; ?> ><span class="material-symbols-outlined">chevron_right</span></button>
                    </div>
                </div>

                <div class="activity-table-wrapper">
                    <table class="activity-table" id="adminPersonalActivityTable">
                        <thead>
                            <tr>
                                <th>Time</th><th>Type</th><th>Result</th><th>Details</th><th>Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($adminActivityForSelectedDate)): ?>
                                <?php foreach ($adminActivityForSelectedDate as $activity): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($activity['time']); ?></td>
                                        <td>
                                            <span class="activity-status <?php echo htmlspecialchars($activity['status_class'] ?? 'neutral'); ?>">
                                                <?php
                                                $logTypeForIcon = $activity['original_db_log_type'] ?? $activity['log_type'] ?? 'unknown';
                                                $logTypeForIconClean = strtolower(str_replace([' ', '_'], '', $logTypeForIcon));
                                                if ($logTypeForIconClean === 'entry') { echo '<span class="material-symbols-outlined">login</span>'; }
                                                elseif ($logTypeForIconClean === 'exit') { echo '<span class="material-symbols-outlined">logout</span>'; }
                                                elseif ($logTypeForIconClean === 'systemaccountcreated') { echo '<span class="material-symbols-outlined">engineering</span>'; }
                                                elseif ($logTypeForIconClean === 'systemcurrentstatus') { echo '<span class="material-symbols-outlined">update</span>'; }
                                                elseif ($logTypeForIconClean === 'systemscheduledabsence') { echo '<span class="material-symbols-outlined">event_busy</span>'; }
                                                elseif ($logTypeForIconClean === 'systemnorecord') { echo '<span class="material-symbols-outlined">search_off</span>'; }
                                                elseif ($logTypeForIconClean === 'autoregistered') { echo '<span class="material-symbols-outlined">person_add</span>'; }
                                                elseif ($logTypeForIconClean === 'unknowncardscan') { echo '<span class="material-symbols-outlined">contactless_off</span>'; }
                                                elseif ($logTypeForIconClean === 'unassignedcardattempt') { echo '<span class="material-symbols-outlined">no_accounts</span>'; }
                                                else { echo '<span class="material-symbols-outlined">help_outline</span>'; }
                                                ?>
                                                <?php echo htmlspecialchars($activity['log_type'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['log_result'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($activity['details'] ?? 'N/A'); ?></td>
                                        <td class="rfid-cell"><?php echo htmlspecialchars($activity['rfid_card_uid'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="no-activity-msg">No personal activity recorded for you on <?php echo date("M d, Y", strtotime($selectedDate)); ?>.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <!-- MODAL FOR ADMIN'S LATE DEPARTURE NOTIFICATION -->
    <div id="adminSelfLateDepartureModal" class="modal"> <!-- Changed ID -->
        <div class="modal-content">
            <button type="button" class="close-modal-btn" aria-label="Close modal">×</button>
            <h2 id="adminModalTitle">Notify Your Late Departure</h2>
            <p id="adminModalSubtitle">If you plan to stay beyond 15:30, please let us know your estimated departure time for <strong><?php echo date("F d, Y", strtotime($todayDate)); ?></strong>.</p>
            <form id="adminLateDepartureForm">
                <input type="hidden" name="notification_date" value="<?php echo htmlspecialchars($todayDate); ?>">
                <input type="hidden" name="action" value="submit_late_departure">
                <div class="form-group">
                    <label for="admin_planned_departure_time">Planned Departure Time (after 15:30):</label>
                    <input type="time" id="admin_planned_departure_time" name="planned_departure_time" required min="15:30">
                </div>
                <div class="form-group">
                    <label for="admin_departure_notes">Notes (Optional):</label>
                    <textarea id="admin_departure_notes" name="notes" rows="3" placeholder="e.g., Finishing up server maintenance"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-submit">Save Changes</button> 
                    <button type="button" id="adminCancelLateBtn" class="btn-danger btn-cancel-late" style="display: none;">Cancel Late Departure</button>
                    <button type="button" class="btn-cancel close-modal-btn">Close</button>
                </div>
                <div id="adminModalFormMessage" class="form-message" role="alert"></div>
            </form>
        </div>
    </div>

    <!-- MODAL FOR OTHER USERS' LATE DEPARTURE DETAILS -->
    <div id="viewUsersLateDeparturesModal" class="admin-view-modal"> <!-- Changed ID for clarity -->
        <div class="admin-view-modal-content">
            <button type="button" class="admin-view-close-modal-btn" aria-label="Close modal">×</button>
            <h2>Users Staying Late Today</h2>
            <?php if ($lateDepartureCount > 0 && !empty($lateDeparturesTodayDetails)): ?>
                <div class="activity-table-wrapper">
                    <table class="admin-late-departure-table">
                        <thead><tr><th>User</th><th>Planned Departure</th><th>Notes</th></tr></thead>
                        <tbody>
                            <?php foreach ($lateDeparturesTodayDetails as $departure): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($departure['firstName'] . ' ' . $departure['lastName']); ?></td>
                                    <td><?php echo date("H:i", strtotime($departure['planned_departure_time'])); ?></td>
                                    <td>
                                        <?php if (!empty($departure['notes'])): echo htmlspecialchars($departure['notes']);
                                              else: echo '<span style="color: var(--gray-color); font-style:italic;">No notes</span>';
                                        endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: var(--gray-color); margin-top: 1rem;">No other users have notified a late departure for today.</p>
            <?php endif; ?>
            <div class="admin-view-modal-actions">
                <button type="button" class="admin-view-btn-secondary admin-view-close-modal-btn">Close</button>
            </div>
        </div>
    </div>

    <?php require_once "../components/footer-admin.php"; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const body = document.body;
    const dateSelector = document.getElementById('activity-date-selector');
    const prevDayBtn = document.getElementById('prevDayBtn');
    const nextDayBtn = document.getElementById('nextDayBtn');
    const todayISO = new Date().toISOString().split('T')[0];

    function navigateToDate(dateString) {
        window.location.href = `admin-dashboard.php?date=${dateString}`;
    }

    if (dateSelector) {
        dateSelector.max = todayISO;
        dateSelector.addEventListener('change', function() { navigateToDate(this.value); });
        nextDayBtn.disabled = dateSelector.value >= todayISO;
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
    }

    // --- ADMIN'S OWN LATE DEPARTURE MODAL SCRIPT ---
    const isAdminCurrentlyPresent = <?php echo json_encode($adminRfidStatusClass === 'present'); ?>;
    const notifyAdminLateBtn = document.getElementById('notifyAdminLateDepartureBtn'); 
    const editAdminLateBtn = document.getElementById('editAdminLateDepartureBtn');     

    const adminLateModal = document.getElementById('adminSelfLateDepartureModal');
    const adminModalCloseButtons = adminLateModal ? adminLateModal.querySelectorAll('.close-modal-btn') : [];
    const adminLateForm = document.getElementById('adminLateDepartureForm');
    const adminModalMsgDiv = document.getElementById('adminModalFormMessage');
    const adminPlannedTimeInput = document.getElementById('admin_planned_departure_time');
    const adminNotesInput = document.getElementById('admin_departure_notes');
    const adminCancelLateBtnInModal = document.getElementById('adminCancelLateBtn');
    const adminModalTitleEl = document.getElementById('adminModalTitle');
    const adminModalSubmitButton = adminLateModal ? adminLateModal.querySelector('.btn-submit') : null;

    const existingLatePHPForAdmin = <?php echo json_encode($existingLateDepartureForAdmin); ?> || null; 

    function openAdminLateModal(isEditing = false) {
        if (!isEditing && !isAdminCurrentlyPresent) {
            alert("You (Admin) must be currently present to notify a new late departure.");
            return;
        }
        if (!adminLateModal || !adminPlannedTimeInput || !adminNotesInput || !adminCancelLateBtnInModal || !adminModalTitleEl || !adminModalSubmitButton) {
            console.error("One or more admin late departure modal elements are missing.");
            return;
        }
        adminLateModal.classList.add('show');
        body.style.overflow = 'hidden'; 

        if (adminModalMsgDiv) { adminModalMsgDiv.textContent = ''; adminModalMsgDiv.className = 'form-message'; }
        if (adminLateForm) adminLateForm.reset();
        
        const actionInput = adminLateForm.querySelector('input[name="action"]');
        if (actionInput) actionInput.value = 'submit_late_departure';

        if (isEditing && existingLatePHPForAdmin) {
            adminModalTitleEl.textContent = 'Change Your Late Departure';
            adminModalSubmitButton.textContent = 'Save Changes';
            if(existingLatePHPForAdmin.planned_departure_time) adminPlannedTimeInput.value = existingLatePHPForAdmin.planned_departure_time.substring(0,5);
            adminNotesInput.value = existingLatePHPForAdmin.notes || '';
            adminCancelLateBtnInModal.style.display = 'inline-block';
        } else {
            adminModalTitleEl.textContent = 'Notify Your Late Departure';
            adminModalSubmitButton.textContent = 'Submit Notification';
            const now = new Date(); let defaultHours = 15; let defaultMinutes = 30;
            const notificationDateHiddenInput = adminLateModal.querySelector('input[name="notification_date"]');
            if (notificationDateHiddenInput && notificationDateHiddenInput.value === todayISO) {
                 if (now.getHours() > 15 || (now.getHours() === 15 && now.getMinutes() > 30)) {
                    let suggestedTime = new Date(now.getTime() + 30 * 60000); 
                    defaultHours = suggestedTime.getHours(); defaultMinutes = Math.ceil(suggestedTime.getMinutes() / 15) * 15; 
                    if (defaultMinutes >= 60) { defaultHours = (defaultHours + 1) % 24; defaultMinutes = 0; }
                    if (defaultHours < 15 || (defaultHours === 15 && defaultMinutes < 30) ) { defaultHours = 15; defaultMinutes = 30; }
                }
            }
            adminPlannedTimeInput.value = `${String(defaultHours).padStart(2, '0')}:${String(defaultMinutes).padStart(2, '0')}`;
            adminNotesInput.value = '';
            adminCancelLateBtnInModal.style.display = 'none';
        }
        adminPlannedTimeInput.min = "15:30"; 
    }

    if (notifyAdminLateBtn) { notifyAdminLateBtn.addEventListener('click', () => openAdminLateModal(false)); }
    if (editAdminLateBtn) { editAdminLateBtn.addEventListener('click', () => openAdminLateModal(true)); }

    if (adminLateModal) {
        adminModalCloseButtons.forEach(btn => {
            btn.addEventListener('click', () => { adminLateModal.classList.remove('show'); body.style.overflow = ''; });
        });
        window.addEventListener('click', (event) => {
            if (event.target === adminLateModal) { adminLateModal.classList.remove('show'); body.style.overflow = ''; }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && adminLateModal.classList.contains('show')) { adminLateModal.classList.remove('show'); body.style.overflow = ''; }
        });
    }

    if (adminLateForm && adminModalMsgDiv && adminModalSubmitButton) {
        adminLateForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            const plannedTime = formData.get('planned_departure_time');
            
            adminModalMsgDiv.textContent = ''; adminModalMsgDiv.className = 'form-message';
            if (!plannedTime) { adminModalMsgDiv.textContent = 'Please enter planned departure time.'; adminModalMsgDiv.classList.add('error'); return; }
            const [hours, minutes] = plannedTime.split(':').map(Number);
            if (hours < 15 || (hours === 15 && minutes < 30)) { adminModalMsgDiv.textContent = 'Planned departure time must be 15:30 or later.'; adminModalMsgDiv.classList.add('error'); return; }
            
            adminModalSubmitButton.disabled = true;
            const originalSubmitButtonText = adminModalSubmitButton.innerHTML;
            adminModalSubmitButton.innerHTML = 'Saving... <i class="fas fa-spinner fa-spin"></i>';

            fetch('admin-dashboard.php', { method: 'POST', body: formData }) // Fetch to admin-dashboard.php
            .then(response => {
                if (!response.ok) { return response.json().then(errData => { throw { status: response.status, data: errData }; });}
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    adminModalMsgDiv.textContent = data.message || 'Operation successful!'; adminModalMsgDiv.classList.add('success');
                    setTimeout(() => { window.location.reload(); }, 1500); 
                } else {
                    adminModalMsgDiv.textContent = data.message || 'An error occurred.'; adminModalMsgDiv.classList.add('error');
                }
            })
            .catch(error => { 
                console.error('Admin Late Form submission error:', error);
                if (error && error.data && error.data.message) adminModalMsgDiv.textContent = error.data.message;
                else if (error && error.message) adminModalMsgDiv.textContent = error.message;
                else adminModalMsgDiv.textContent = 'A network or server error occurred.';
                adminModalMsgDiv.classList.add('error');
             })
            .finally(() => { adminModalSubmitButton.disabled = false; adminModalSubmitButton.innerHTML = originalSubmitButtonText; });
        });
    }

    if (adminCancelLateBtnInModal) {
        adminCancelLateBtnInModal.addEventListener('click', function() {
            if (!confirm("Are you sure you want to cancel your late departure notification for today?")) return;
            if (adminModalMsgDiv) { adminModalMsgDiv.textContent = ''; adminModalMsgDiv.className = 'form-message'; }
            const formData = new FormData(); formData.append('action', 'cancel_late_departure');
            this.disabled = true; const originalButtonText = this.innerHTML;
            this.innerHTML = 'Cancelling... <i class="fas fa-spinner fa-spin"></i>';

            fetch('admin-dashboard.php', { method: 'POST', body: formData }) // Fetch to admin-dashboard.php
            .then(response => {
                if (!response.ok) { return response.json().then(errData => { throw { status: response.status, data: errData }; });}
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if(adminModalMsgDiv) { adminModalMsgDiv.textContent = data.message || 'Late departure cancelled.'; adminModalMsgDiv.classList.add('success');}
                    setTimeout(() => { window.location.reload(); }, 1500); 
                } else {
                    if(adminModalMsgDiv) { adminModalMsgDiv.textContent = data.message || 'Failed to cancel.'; adminModalMsgDiv.classList.add('error'); }
                }
            })
            .catch(error => { 
                console.error('Error cancelling admin late departure:', error);
                if(adminModalMsgDiv) {
                    if (error && error.data && error.data.message) adminModalMsgDiv.textContent = error.data.message;
                    else if (error && error.message) adminModalMsgDiv.textContent = error.message;
                    else adminModalMsgDiv.textContent = 'A network or server error occurred.';
                    adminModalMsgDiv.classList.add('error');
                }
            })
            .finally(() => { this.disabled = false; this.innerHTML = originalButtonText; });
        });
    }

    // --- MODAL FOR VIEWING OTHER USERS' LATE DEPARTURES ---
    const viewUsersLateDeparturesBtn = document.getElementById('viewLateDeparturesDetailsBtn');
    const usersLateDeparturesModal = document.getElementById('viewUsersLateDeparturesModal'); // Corrected ID
    const usersLateModalCloseButtons = usersLateDeparturesModal ? usersLateDeparturesModal.querySelectorAll('.admin-view-close-modal-btn') : [];

    if (viewUsersLateDeparturesBtn && usersLateDeparturesModal) {
        viewUsersLateDeparturesBtn.addEventListener('click', () => {
            usersLateDeparturesModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        });
    }

    if (usersLateModalCloseButtons.length > 0) {
        usersLateModalCloseButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                if (usersLateDeparturesModal) {
                    usersLateDeparturesModal.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });
        });
    }

    if (usersLateDeparturesModal) {
        window.addEventListener('click', (event) => {
            if (event.target === usersLateDeparturesModal) {
                usersLateDeparturesModal.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && usersLateDeparturesModal.classList.contains('show')) {
                usersLateDeparturesModal.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    }
});
</script>
</body>
</html>