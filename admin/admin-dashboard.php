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

require_once '../db.php'; 

// --- SESSION VARIABLES ---
$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
$sessionRole = isset($_SESSION["role"]) ? strtolower($_SESSION["role"]) : 'admin'; // Ensuring it is admin

// --- DATE HANDLING ---
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$todayDate = date('Y-m-d');
$todayDateTime = date('Y-m-d H:i:s');

// --- DEFAULT VALUES FOR DISPLAY ---
$rfidStatus = "Status Unknown";
$rfidStatusClass = "neutral";
$unreadMessagesCount = 0;
$activityForSelectedDate = [];
$dbErrorMessage = null;
$currentUserData = null;

// Variables for Late Departures
$lateDeparturesTodayDetails = [];
$lateDepartureCount = 0;

if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        // 1. Fetch admin's own dateOfCreation
        $stmtUserMeta = $pdo->prepare("SELECT dateOfCreation FROM users WHERE userID = :userid");
        if ($stmtUserMeta) {
            $stmtUserMeta->execute([':userid' => $sessionUserId]);
            $currentUserData = $stmtUserMeta->fetch();
            $stmtUserMeta->closeCursor();
        }

        // 2. DETERMINE ADMIN'S OWN CURRENT PRESENCE STATUS
        $stmtLatestEvent = $pdo->prepare(
            "SELECT logType, logResult FROM attendance_logs
             WHERE userID = :userid AND DATE(logTime) = :today_date
             ORDER BY logTime DESC LIMIT 1"
        );
        if ($stmtLatestEvent) {
            $stmtLatestEvent->execute([':userid' => $sessionUserId, ':today_date' => $todayDate]);
            $latestEvent = $stmtLatestEvent->fetch();
            $stmtLatestEvent->closeCursor();
            if ($latestEvent) {
                if ($latestEvent['logType'] == 'entry' && $latestEvent['logResult'] == 'granted') {
                    $rfidStatus = "Present"; $rfidStatusClass = "present";
                } elseif ($latestEvent['logType'] == 'exit' && $latestEvent['logResult'] == 'granted') {
                    $rfidStatus = "Checked Out"; $rfidStatusClass = "absent";
                } elseif ($latestEvent['logResult'] == 'denied') {
                    $rfidStatus = ($latestEvent['logType'] == 'entry' ? "Check In Denied" : "Exit Denied"); $rfidStatusClass = "danger";
                } else {
                    $rfidStatus = "Status Unknown"; $rfidStatusClass = "neutral";
                }
            } else {
                $stmtScheduledAbsenceToday = $pdo->prepare(
                    "SELECT absence_type FROM absence WHERE userID = :userid
                     AND :today_date_time BETWEEN absence_start_datetime AND absence_end_datetime
                     AND status = 'approved' LIMIT 1"
                );
                if ($stmtScheduledAbsenceToday) {
                    $stmtScheduledAbsenceToday->execute([':userid' => $sessionUserId, ':today_date_time' => $todayDateTime]);
                    $scheduledAbsence = $stmtScheduledAbsenceToday->fetch();
                    $stmtScheduledAbsenceToday->closeCursor();
                    if ($scheduledAbsence) {
                        $absenceTypeDisplay = ucfirst(str_replace('_', ' ', $scheduledAbsence['absence_type']));
                        $rfidStatus = "Scheduled " . htmlspecialchars($absenceTypeDisplay); $rfidStatusClass = "absent";
                    } else {
                        $rfidStatus = "Not Checked In"; $rfidStatusClass = "neutral";
                    }
                }
            }
        }

        // 4. UNREAD MESSAGES COUNT for Admin
        $stmtUnread = $pdo->prepare("
            SELECT COUNT(DISTINCT m.messageID) FROM messages m
            LEFT JOIN user_message_read_status umrs ON m.messageID = umrs.messageID AND umrs.userID = :current_user_id_for_read_status
            WHERE m.is_active = TRUE AND (m.expires_at IS NULL OR m.expires_at > NOW())
            AND (m.recipientID = :current_user_id_recipient OR m.recipientRole = :current_user_role OR m.recipientRole = 'everyone')
            AND (umrs.is_read IS NULL OR umrs.is_read = 0)"
        );
        if ($stmtUnread) {
            $stmtUnread->execute([
                ':current_user_id_for_read_status' => $sessionUserId,
                ':current_user_id_recipient' => $sessionUserId,
                ':current_user_role' => $sessionRole 
            ]);
            $unreadMessagesCount = (int)$stmtUnread->fetchColumn();
            $stmtUnread->closeCursor();
        }

        // *** Fetch today's late departure notifications with user names ***
        $stmtLate = $pdo->prepare(
            "SELECT ldn.userID, ldn.planned_departure_time, ldn.notes, u.firstName, u.lastName
             FROM late_departure_notifications ldn
             JOIN users u ON ldn.userID = u.userID
             WHERE ldn.notification_date = :today_date
             ORDER BY ldn.planned_departure_time ASC, u.lastName ASC, u.firstName ASC"
        );
        $stmtLate->execute([':today_date' => $todayDate]);
        $lateDeparturesTodayDetails = $stmtLate->fetchAll(PDO::FETCH_ASSOC);
        $lateDepartureCount = count($lateDeparturesTodayDetails);
        $stmtLate->closeCursor();


        // 6. ADMIN'S OWN ACTIVITY SNAPSHOT FOR SELECTED DATE
        // ... (stávající kód pro $activityForSelectedDate - beze změny) ...
        if ($currentUserData && $selectedDate == date("Y-m-d", strtotime($currentUserData['dateOfCreation']))) {
             $activityForSelectedDate[] = [
                 'time' => date("H:i", strtotime($currentUserData['dateOfCreation'])), 'log_type' => 'System',
                 'log_result' => 'Info', 'details' => 'Your WavePass account was created.',
                 'rfid_card' => 'N/A', 'status_class' => 'info'
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
                $hasCheckInOutActivity = true; break;
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
        error_log("Admin Dashboard DB Error (User: {$sessionUserId}, Date: {$selectedDate}): " . $e->getMessage());
        $dbErrorMessage = "A database error occurred. Please try again later.";
    } catch (Exception $e) {
        error_log("Admin Dashboard App Error (User: {$sessionUserId}, Date: {$selectedDate}): " . $e->getMessage());
        $dbErrorMessage = "An application error occurred. Please try again later.";
    }
} else {
    if (!isset($pdo) || !($pdo instanceof PDO)) $dbErrorMessage = ($dbErrorMessage ? $dbErrorMessage . "<br>" : "") . "Database connection is not available.";
    if (!$sessionUserId) $dbErrorMessage = ($dbErrorMessage ? $dbErrorMessage . "<br>" : "") . "User session is invalid (or user ID not found).";
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
            :root {
                --primary-color: #4361ee;
                --primary-dark: #3a56d4;
                --secondary-color: #3f37c9;
                --dark-color: #1a1a2e;
                --light-color: #f8f9fa;
                --gray-color: #6c757d;
                --light-gray: #e9ecef;
                --white: #ffffff;
                /* --success-color: #4cc9f0; REMOVED - Using true-success-color */
                --true-success-color: #28a745; /* Green for success messages */
                --warning-color: #f8961e;
                --danger-color: #f72585; /* Original danger */
                --info-color-custom: #007bff; /* Standard Info Blue */
                --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                --transition: all 0.3s ease;

                --present-color-val: 67, 170, 139;
                --absent-color-val: 214, 40, 40;   /* This was red, for "Checked Out" might be better yellow/orange */
                --checked-out-color-val: 255, 193, 7; /* Bootstrap warning yellow for Checked Out */
                --info-color-val: 84, 160, 255;    /* For "On Leave" */
                --neutral-color-val: 173, 181, 189;
                --warning-color-val: 248, 150, 30; /* For unread messages */
                --late-departure-icon-color-val: 23, 162, 184; /* Bootstrap info teal for late departures */
                --danger-color-val: 247, 37, 133;  /* For Denied Access (original danger) */
                --true-danger-color-val: 220, 53, 69; /* Bootstrap danger red */


                --present-color: rgb(var(--present-color-val));
                --absent-color: rgb(var(--absent-color-val)); /* Kept for "Scheduled Absence" if red is desired */
                --checked-out-color: rgb(var(--checked-out-color-val)); /* New for "Checked Out" */
                --info-color: rgb(var(--info-color-val)); /* For "On Leave" */
                --neutral-color: rgb(var(--neutral-color-val));
                --late-departure-icon-color: rgb(var(--late-departure-icon-color-val));
            }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9;
                display: flex; flex-direction: column; min-height: 100vh;
                padding-top: 80px; 
            }
            main { flex-grow: 1; }
            .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
            h1, h2, h3, h4 { font-weight: 700; line-height: 1.2; }

            .page-header { padding: 1.8rem 0; margin-bottom: 1.8rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
            .page-header h1 { font-size: 1.7rem; color: var(--dark-color); margin: 0; }
            .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
            .db-error-message {background-color: rgba(var(--true-danger-color-val),0.1); color: rgb(var(--true-danger-color-val)); padding: 1rem; border-left: 4px solid rgb(var(--true-danger-color-val)); margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}

            .admin-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 1.5rem;
                margin-bottom: 2.5rem;
            }
             .stat-card { 
                background-color: var(--white); padding: 1.2rem 1.5rem; 
                border-radius: 8px; box-shadow: var(--shadow); 
                display: flex; align-items: center; gap: 1.2rem; 
                transition: var(--transition); border: 1px solid var(--light-gray); 
                position:relative; overflow:hidden;
            }
            .stat-card::before { content: ''; position:absolute; top:0; left:0; width:5px; height:100%; background-color:transparent; transition: var(--transition); }
            .stat-card:hover{transform: translateY(-4px); box-shadow: 0 6px 28px rgba(0,0,0,0.09);}
            .stat-card .icon { font-size: 2.2rem; padding: 0.8rem; border-radius: 50%; display:flex; align-items:center; justify-content:center; width:55px; height:55px; flex-shrink:0;}
            .stat-card .info { flex-grow: 1; }
            .stat-card .info .value { font-size: 1.8rem; font-weight: 500; color: var(--dark-color); display:block; line-height:1.1; margin-bottom:0.3rem;}
            .stat-card .info .label { font-size: 0.8rem; color: var(--gray-color); text-transform: uppercase; letter-spacing: 0.5px; }

            .stat-card .stat-card-button {
                background-color: var(--primary-color); color: var(--white); border: none; 
                padding: 0.6rem 1rem; border-radius: 5px; font-size: 0.85rem; 
                font-weight: 500; cursor: pointer; transition: background-color 0.2s;
                margin-left: auto; 
                white-space: nowrap;
                align-self: center; 
            }
            .stat-card .stat-card-button:hover { background-color: var(--primary-dark); }

            /* RFID Status Card */
            .stat-card.rfid-status.present::before { background-color: var(--present-color); }
            .stat-card.rfid-status.present .icon { background-color: rgba(var(--present-color-val),0.1); color: var(--present-color); }
            .stat-card.rfid-status.absent::before { background-color: var(--absent-color); } /* Used for Scheduled Absence, a red tone */
            .stat-card.rfid-status.absent .icon { background-color: rgba(var(--absent-color-val),0.1); color: var(--absent-color); }
            .stat-card.rfid-status.checked-out::before { background-color: var(--checked-out-color); } /* New class for checked-out */
            .stat-card.rfid-status.checked-out .icon { background-color: rgba(var(--checked-out-color-val),0.15); color: var(--checked-out-color); }
            .stat-card.rfid-status.neutral::before { background-color: var(--neutral-color); }
            .stat-card.rfid-status.neutral .icon { background-color: rgba(var(--neutral-color-val),0.1); color: var(--neutral-color); }
            .stat-card.rfid-status.danger::before { background-color: rgb(var(--danger-color-val)); } /* Original red for denied access */
            .stat-card.rfid-status.danger .icon { background-color: rgba(var(--danger-color-val),0.1); color: rgb(var(--danger-color-val)); }
            
            /* Unread Messages Card */
            .stat-card.unread-messages::before { background-color: var(--warning-color); }
            .stat-card.unread-messages .icon { background-color: rgba(var(--warning-color-val),0.1); color: var(--warning-color); }

            /* Late Departures Card */
            .stat-card.admin-late-departures::before { background-color: var(--late-departure-icon-color); }
            .stat-card.admin-late-departures .icon { background-color: rgba(var(--late-departure-icon-color-val),0.1); color: var(--late-departure-icon-color); }


            .content-panel { background-color: var(--white); padding: 1.5rem 1.8rem; border-radius: 8px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); }
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
            .activity-status.present { background-color: rgba(var(--present-color-val), 0.15); color: var(--present-color); }
            .activity-status.absent { background-color: rgba(var(--absent-color-val), 0.15); color: var(--absent-color); } /* Red as per original absent */
            .activity-status.checked-out { background-color: rgba(var(--checked-out-color-val), 0.15); color: var(--checked-out-color); } /* Yellow for checked out */
            .activity-status.info { background-color: rgba(var(--info-color-val), 0.15); color: var(--info-color); }
            .activity-status.neutral { background-color: rgba(var(--neutral-color-val),0.15); color: var(--neutral-color);}
            .activity-status.danger { background-color: rgba(var(--danger-color-val),0.15); color: rgb(var(--danger-color-val));} /* original red */
            .activity-table td.rfid-cell { font-family: monospace; font-size: 0.8rem; color: var(--gray-color); }
            .no-activity-msg {text-align:center; padding: 2.5rem 1rem; color:var(--gray-color); font-size:0.95rem; background-color: #fdfdfd; border-radius: 4px; border: 1px dashed var(--light-gray);}

            /* === Styly pro Admin Modal === */
            .admin-view-modal { display: none; position: fixed; z-index: 1070; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7); align-items: center; justify-content: center; padding: 20px;}
            .admin-view-modal.show { display: flex; }
            .admin-view-modal-content { background-color: var(--white); margin: auto; padding: 25px 30px; border-radius: 10px; width: 90%; max-width: 800px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); position: relative; animation: fadeInModal 0.3s ease-out; }
            @keyframes fadeInModal { from {opacity: 0; transform: translateY(-30px) scale(0.95);} to {opacity: 1; transform: translateY(0) scale(1);} }
            .admin-view-modal-content h2 { margin-top: 0; margin-bottom: 1.5rem; font-size: 1.6rem; color: var(--primary-color); text-align: center; padding-bottom: 0.8rem; border-bottom: 1px solid var(--light-gray); }
            .admin-view-close-modal-btn { color: var(--gray-color); background: transparent; border: none; position: absolute; top: 15px; right: 18px; font-size: 1.9rem; font-weight: bold; line-height: 1; padding: 0.2rem 0.5rem; cursor: pointer; transition: color 0.2s ease; }
            .admin-view-close-modal-btn:hover {  }
            
            table.admin-late-departure-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: 0.9rem; }
            table.admin-late-departure-table th, table.admin-late-departure-table td { padding: 0.6rem 0.8rem; text-align: left; border-bottom: 1px solid var(--light-gray); }
            table.admin-late-departure-table th { background-color: #f9fafb; font-weight: 600; color: var(--dark-color); }
            table.admin-late-departure-table tbody tr:hover { background-color: #f5f5f5; }
            .admin-note-icon-table { margin-left: 8px; cursor: help; color: var(--gray-color); font-size: 0.9em; }
            .admin-late-departure-list { /* Used if you prefer UL over table */ list-style: none; padding: 0; margin: 0 0 1.5rem 0; max-height: 350px; overflow-y: auto; border: 1px solid var(--light-gray); border-radius: 6px; }
            .admin-late-departure-list li { padding: 0.8rem 1rem; border-bottom: 1px solid var(--light-gray); font-size: 0.95rem; display: flex; justify-content: space-between; align-items: center; }
            .admin-late-departure-list li:last-child { border-bottom: none; }
            .admin-late-departure-list li strong { color: var(--dark-color); margin-right: 0.5rem; }
            .admin-note-icon { margin-left: 8px; cursor: help; color: var(--gray-color); font-size: 0.9em; }


            .admin-view-modal-actions { margin-top: 1.8rem; text-align: right; }
            .admin-view-btn-secondary { background-color: var(--gray-color); color: var(--white); border: none; padding: 0.7rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; transition: background-color 0.2s; font-size: 0.9rem; }
            .admin-view-btn-secondary:hover { background-color: var(--dark-color); }

    </style>
</head>
<body>
    <?php require_once "../components/header-admin.php" ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>Welcome, <?php echo $sessionFirstName; ?>!</h1>
                <p class="sub-heading">This is your personal dashboard view within the admin area.</p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if (isset($dbErrorMessage) && $dbErrorMessage): ?>
                <div class="db-error-message" role="alert">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i> <?php echo htmlspecialchars($dbErrorMessage); ?>
                </div>
            <?php endif; ?>

            <section class="admin-stats-grid">
                <div class="stat-card rfid-status <?php echo htmlspecialchars($rfidStatusClass); ?>">
                    <div class="icon"><span class="material-symbols-outlined">
                        <?php
                            if ($rfidStatusClass === "present") echo "person_check";
                            elseif ($rfidStatusClass === "absent" && strpos($rfidStatus, "Scheduled") !== false) echo "event_busy"; // For Scheduled Absence
                            elseif ($rfidStatusClass === "absent") echo "logout"; // For Checked Out
                            elseif ($rfidStatusClass === "danger") echo "gpp_maybe";
                            else echo "contactless"; // For Not Checked In / Unknown
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

                <!-- Stat Card pro pozdní odchody -->
                <div class="stat-card admin-late-departures">
                    <div class="icon"><span class="material-symbols-outlined">history_edu</span></div>
                    <div class="info">
                        <span class="value"><?php echo $lateDepartureCount; ?></span>
                        <span class="label">Users Staying Late Today</span>
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
                                <th>Time</th> <th>Type</th> <th>Result</th> <th>Details</th> <th>Source</th>
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
        </div>
    </main>

    <!-- MODAL FOR LATE DEPARTURE DETAILS -->
    <div id="adminLateDeparturesModal" class="admin-view-modal">
        <div class="admin-view-modal-content">
            <button type="button" class="admin-view-close-modal-btn" aria-label="Close modal">×</button>
            <h2>Users Staying Late Today</h2>
            
            <?php if ($lateDepartureCount > 0 && !empty($lateDeparturesTodayDetails)): ?>
                <div class="activity-table-wrapper"> <!-- Použití existujícího wrapperu pro tabulku -->
                    <table class="admin-late-departure-table"> <!-- Nová třída pro specifické styly tabulky v modalu -->
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Planned Departure</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lateDeparturesTodayDetails as $departure): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($departure['firstName'] . ' ' . $departure['lastName']); ?></td>
                                    <td><?php echo date("H:i", strtotime($departure['planned_departure_time'])); ?></td>
                                    <td>
                                        <?php if (!empty($departure['notes'])): ?>
                                            <?php echo htmlspecialchars($departure['notes']); ?>
                                        <?php else: ?>
                                            <span style="color: var(--gray-color); font-style:italic;">No notes</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: var(--gray-color); margin-top: 1rem;">No users have notified a late departure for today.</p>
            <?php endif; ?>
            
            <div class="admin-view-modal-actions">
                <button type="button" class="admin-view-btn-secondary admin-view-close-modal-btn">Close</button>
            </div>
        </div>
    </div>

    <?php require_once "../components/footer-admin.php"; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dateSelector = document.getElementById('activity-date-selector');
        const prevDayBtn = document.getElementById('prevDayBtn');
        const nextDayBtn = document.getElementById('nextDayBtn');
        const todayISO = new Date().toISOString().split('T')[0];

        function navigateToDate(dateString) {
            window.location.href = `admin-dashboard.php?date=${dateString}`;
        }

        function updateNextDayButtonState() {
            if (nextDayBtn && dateSelector) {
                nextDayBtn.disabled = dateSelector.value >= todayISO;
            }
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
            updateNextDayButtonState();
        }

        // Late Departures Modal JS
        const viewLateDeparturesBtn = document.getElementById('viewLateDeparturesDetailsBtn');
        const lateDeparturesModal = document.getElementById('adminLateDeparturesModal');
        const lateModalCloseButtons = lateDeparturesModal ? lateDeparturesModal.querySelectorAll('.admin-view-close-modal-btn') : [];

        if (viewLateDeparturesBtn && lateDeparturesModal) {
            viewLateDeparturesBtn.addEventListener('click', () => {
                lateDeparturesModal.classList.add('show');
                document.body.style.overflow = 'hidden'; 
            });
        }

        if (lateModalCloseButtons.length > 0) {
            lateModalCloseButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    if (lateDeparturesModal) {
                        lateDeparturesModal.classList.remove('show');
                        document.body.style.overflow = '';
                    }
                });
            });
        }

        if (lateDeparturesModal) {
            window.addEventListener('click', (event) => {
                if (event.target === lateDeparturesModal) { 
                    lateDeparturesModal.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && lateDeparturesModal.classList.contains('show')) {
                    lateDeparturesModal.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });
        }
    });
</script>
</body>
</html>