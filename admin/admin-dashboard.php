<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- SESSION CHECK & ADMIN ROLE CHECK ---
// Ensure user is logged in AND is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    // If not admin, or not logged in, redirect to login.
    // If already in /admin/ folder, login.php should be ../login.php
    // If this admin-dashboard itself was accidentally placed in root, it would be login.php
    // Based on your structure, it's in /admin/
    header("location: ../login.php");
    exit;
}

require_once '../db.php'; // Correct path since this file is in /admin/

// --- SESSION VARIABLES ---
// For admin, $sessionFirstName might be their actual name or a generic "Admin"
$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null; // Admin's own userID
$sessionRole = isset($_SESSION["role"]) ? strtolower($_SESSION["role"]) : 'employee'; // Should be 'admin' here

// --- DATE HANDLING ---
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$todayDate = date('Y-m-d');
$todayDateTime = date('Y-m-d H:i:s');

// --- DEFAULT VALUES FOR DISPLAY ---
$rfidStatus = "Status Unknown";
$rfidStatusClass = "neutral";
// $absencesThisMonthCountDisplay = "0 Requests"; // REMOVED for admin dashboard view
$unreadMessagesCount = 0;
// $upcomingLeaveDisplay = "None upcoming"; // REMOVED for admin dashboard view
$activityForSelectedDate = [];
$dbErrorMessage = null;
$currentUserData = null;

if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        // 1. Fetch admin's own dateOfCreation for activity snapshot
        $stmtUserMeta = $pdo->prepare("SELECT dateOfCreation FROM users WHERE userID = :userid");
        if ($stmtUserMeta) {
            $stmtUserMeta->execute([':userid' => $sessionUserId]);
            $currentUserData = $stmtUserMeta->fetch();
            $stmtUserMeta->closeCursor();
        }

        // --- 2. DETERMINE ADMIN'S OWN CURRENT PRESENCE STATUS (for today) ---
        // This remains as admin might also clock in/out
        $stmtLatestEvent = $pdo->prepare(
            "SELECT logType, logResult
             FROM attendance_logs
             WHERE userID = :userid AND DATE(logTime) = :today_date
             ORDER BY logTime DESC
             LIMIT 1"
        );
        if ($stmtLatestEvent) {
            $stmtLatestEvent->execute([
                ':userid' => $sessionUserId, // Admin's own user ID
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
                // No attendance log today, check for scheduled absence (admin might have one)
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

        // --- 3. APPROVED ABSENCES THIS MONTH ---
        // This section is REMOVED for the admin's personal dashboard view.
        // Admin would typically view this for OTHERS in a dedicated management section.

        // --- 4. UNREAD MESSAGES COUNT for Admin ---
        // This remains relevant for the admin.
        $stmtUnread = $pdo->prepare("
            SELECT COUNT(DISTINCT m.messageID)
            FROM messages m
            LEFT JOIN user_message_read_status umrs ON m.messageID = umrs.messageID AND umrs.userID = :current_user_id_for_read_status
            WHERE
                m.is_active = TRUE AND
                (m.expires_at IS NULL OR m.expires_at > NOW()) AND
                (
                    m.recipientID = :current_user_id_recipient OR
                    m.recipientRole = :current_user_role OR /* Catches messages to 'admin' role */
                    m.recipientRole = 'everyone'
                ) AND
                (umrs.is_read IS NULL OR umrs.is_read = 0)
        ");
        if ($stmtUnread) {
            $stmtUnread->execute([
                ':current_user_id_for_read_status' => $sessionUserId,
                ':current_user_id_recipient' => $sessionUserId,
                ':current_user_role' => $sessionRole // This should be 'admin'
            ]);
            $unreadMessagesCount = (int)$stmtUnread->fetchColumn();
            $stmtUnread->closeCursor();
        }

        // --- 5. UPCOMING LEAVE for Admin ---
        // This section is REMOVED for the admin's personal dashboard view.


        // --- 6. ADMIN'S OWN ACTIVITY SNAPSHOT FOR SELECTED DATE ---
        // This shows the admin's own check-ins/outs or account creation.
        if ($currentUserData && $selectedDate == date("Y-m-d", strtotime($currentUserData['dateOfCreation']))) {
             $activityForSelectedDate[] = [
                 'time' => date("H:i", strtotime($currentUserData['dateOfCreation'])),
                 'log_type' => 'System',
                 'log_result' => 'Info',
                 'details' => 'Your WavePass account was created.',
                 'rfid_card' => 'N/A',
                 'status_class' => 'info'
                ];
        }
        $stmtActivityLog = $pdo->prepare(
            "SELECT logTime, logType, logResult
             FROM attendance_logs
             WHERE userID = :userid AND DATE(logTime) = :selected_date
             ORDER BY logTime ASC"
        );
        if ($stmtActivityLog) {
            $stmtActivityLog->execute([
                ':userid' => $sessionUserId, // Admin's own userID
                ':selected_date' => $selectedDate
            ]);
            while ($log = $stmtActivityLog->fetch(PDO::FETCH_ASSOC)) {
                $logTypeDisplay = 'Unknown';
                $statusClass = 'neutral';

                if ($log['logType'] == 'entry') {
                    $logTypeDisplay = 'Check In';
                    $statusClass = ($log['logResult'] == 'granted') ? 'present' : 'danger';
                } elseif ($log['logType'] == 'exit') {
                    $logTypeDisplay = 'Check Out';
                    $statusClass = ($log['logResult'] == 'granted') ? 'absent' : 'danger';
                }

                $activityForSelectedDate[] = [
                    'time' => date("H:i", strtotime($log['logTime'])),
                    'log_type' => htmlspecialchars($logTypeDisplay),
                    'log_result' => htmlspecialchars(ucfirst($log['logResult'] ?? 'N/A')),
                    'details' => 'Attempted access.',
                    'rfid_card' => 'System Log',
                    'status_class' => $statusClass
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
                     'time' => date("H:i"),
                     'log_type' => 'Current Status',
                     'log_result' => htmlspecialchars($rfidStatus),
                     'details' => 'Based on latest system information.',
                     'rfid_card' => 'N/A',
                     'status_class' => $rfidStatusClass
                    ];
             }
        }
         if (empty($activityForSelectedDate) && $selectedDate <= $todayDate){
            $stmtSelectedDayAbsence = $pdo->prepare(
                "SELECT absence_type, reason FROM absence
                 WHERE userID = :userid
                   AND :selected_date_for_absence BETWEEN DATE(absence_start_datetime) AND DATE(absence_end_datetime)
                   AND status = 'approved'
                 LIMIT 1"
            );
            if ($stmtSelectedDayAbsence) {
                $stmtSelectedDayAbsence->execute([
                    ':userid' => $sessionUserId, // Admin's own userID
                    ':selected_date_for_absence' => $selectedDate
                ]);
                $selectedDayAbsenceInfo = $stmtSelectedDayAbsence->fetch();
                $stmtSelectedDayAbsence->closeCursor();

                if($selectedDayAbsenceInfo){
                    $absenceTypeDetailForMsg = htmlspecialchars(ucfirst(str_replace('_',' ',$selectedDayAbsenceInfo['absence_type'])));
                    $activityForSelectedDate[] = [
                         'time' => '--:--',
                         'log_type' => 'Scheduled Absence',
                         'log_result' => htmlspecialchars($absenceTypeDetailForMsg),
                         'details' => ($selectedDayAbsenceInfo['reason'] ? 'Reason: '.htmlspecialchars($selectedDayAbsenceInfo['reason']) : 'Approved absence'),
                         'rfid_card' => 'N/A',
                         'status_class' => 'absent'
                        ];
                } else {
                     $activityForSelectedDate[] = [
                         'time' => '--:--',
                         'log_type' => 'No Record',
                         'log_result' => 'N/A',
                         'details' => 'No attendance events or approved absences logged for this day.',
                         'rfid_card' => 'N/A',
                         'status_class' => 'neutral'
                        ];
                }
            }
        }
        if (!empty($activityForSelectedDate)) {
            usort($activityForSelectedDate, function($a, $b) {
                if ($a['time'] === '--:--') return -1;
                if ($b['time'] === '--:--') return 1;
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
    <!-- Použijte cestu ../imgs/logo.png, protože tento soubor je v /admin/ -->
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
                --success-color: #4cc9f0; /* Consider changing this for actual success like green */
                --true-success-color: #28a745; /* Green for success messages */
                --warning-color: #f8961e;
                --danger-color: #f72585;
                --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                --transition: all 0.3s ease;

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
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; /* Consistent with other dashboards */
                display: flex; flex-direction: column; min-height: 100vh;
                padding-top: 80px; /* Assuming header height is 80px */
            }
            main { flex-grow: 1; /* padding-top: 80px; Removed as body has it */ }
            .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
            h1, h2, h3, h4 { font-weight: 700; line-height: 1.2; }

            /* Styles from employee dashboard, adapted */
            .page-header { padding: 1.8rem 0; margin-bottom: 1.8rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
            .page-header h1 { font-size: 1.7rem; color: var(--dark-color); margin: 0; }
            .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
            .db-error-message {background-color: rgba(var(--danger-color-val),0.1); color: var(--danger-color); padding: 1rem; border-left: 4px solid var(--danger-color); margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}

            .admin-stats-grid { /* Changed class name for clarity */
                display: grid;
                /* Adjust grid for potentially fewer items - e.g., 2 items per row */
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 1.2rem;
                margin-bottom: 2.5rem;
            }
            /* If only 2 items, you might want them to not take full width */
            @media (min-width: 768px) { /* Example for 2 items centered */
                .admin-stats-grid.two-items {
                    grid-template-columns: repeat(2, minmax(300px, 0.4fr)); /* Adjust fraction for desired width */
                    justify-content: center; /* Center the grid items if they don't fill */
                }
            }


            .stat-card { background-color: var(--white); padding: 1.2rem 1.4rem; border-radius: 8px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1.2rem; transition: var(--transition); border: 1px solid var(--light-gray); position:relative; overflow:hidden;}
            .stat-card::before { content: ''; position:absolute; top:0; left:0; width:5px; height:100%; background-color:transparent; transition: var(--transition); }
            .stat-card:hover{transform: translateY(-4px); box-shadow: 0 6px 28px rgba(0,0,0,0.09);}
            .stat-card .icon { font-size: 2rem; padding: 0.7rem; border-radius: 50%; display:flex; align-items:center; justify-content:center; width:50px; height:50px; flex-shrink:0;}
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

            .stat-card.unread-messages::before { background-color: var(--warning-color); }
            .stat-card.unread-messages .icon { background-color: rgba(var(--warning-color-val),0.1); color: var(--warning-color); }

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
            .activity-status.absent { background-color: rgba(var(--absent-color-val), 0.15); color: var(--absent-color); }
            .activity-status.info { background-color: rgba(var(--info-color-val), 0.15); color: var(--info-color); }
            .activity-status.neutral { background-color: rgba(var(--neutral-color-val),0.15); color: var(--neutral-color);}
            .activity-status.danger { background-color: rgba(var(--danger-color-val),0.15); color: var(--danger-color);}
            .activity-table td.rfid-cell { font-family: monospace; font-size: 0.8rem; color: var(--gray-color); }

            .no-activity-msg {text-align:center; padding: 2.5rem 1rem; color:var(--gray-color); font-size:0.95rem; background-color: #fdfdfd; border-radius: 4px; border: 1px dashed var(--light-gray);}
            .placeholder-text {color: var(--gray-color); font-style: italic; font-size: 0.8rem;}

    </style>
</head>
<body>
    <!-- Header: Uses header-admin.php which handles its own styling and JS -->
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

            <!-- If you have only 2 cards, you can add a class to the grid like 'two-items' -->
            <section class="admin-stats-grid <?php echo ($sessionRole === 'admin' && !isset($absencesThisMonthCountDisplay) && !isset($upcomingLeaveDisplay)) ? 'two-items' : ''; ?>">
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

                <?php /* These are commented out for admin dashboard, but kept for reference if needed
                <div class="stat-card absences-count">
                    <div class="icon"><span class="material-symbols-outlined">calendar_month</span></div>
                    <div class="info">
                        <span class="value"><?php echo htmlspecialchars($absencesThisMonthCountDisplay); ?></span>
                        <span class="label">Absences <small class="placeholder-text"></small></span>
                    </div>
                </div>
                <div class="stat-card upcoming-leave">
                    <div class="icon"><span class="material-symbols-outlined">flight_takeoff</span></div>
                    <div class="info">
                        <span class="value"><?php echo htmlspecialchars($upcomingLeaveDisplay); ?></span>
                        <span class="label">Upcoming Leave</span>
                    </div>
                </div>
                */ ?>
            </section>

            <section class="content-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Your Activity for <span id="selectedDateDisplay"><?php echo date("F d, Y", strtotime($selectedDate)); ?></span></h2>
                    <div class="date-navigation">
                        <button class="btn-nav" id="prevDayBtn" title="Previous Day"><span class="material-symbols-outlined">chevron_left</span></button>
                        <input type="date" id="activity-date-selector" value="<?php echo htmlspecialchars($selectedDate); ?>">
                        <button class="btn-nav" id="nextDayBtn" title="Next Day"><span class="material-symbols-outlined">chevron_right</span></button>
                    </div>
                </div>

                <div class="activity-table-wrapper">
                    <table class="activity-table" id="adminPersonalActivityTable">
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
        </div>
    </main>

    <!-- Footer: Uses footer-admin.php which handles its own styling -->
    <?php require_once "../components/footer-admin.php"; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Hamburger & Mobile menu script is assumed to be in header-admin.php
        // Header shadow on scroll is assumed to be in header-admin.php

        const dateSelector = document.getElementById('activity-date-selector');
        const prevDayBtn = document.getElementById('prevDayBtn');
        const nextDayBtn = document.getElementById('nextDayBtn');
        const todayISO = new Date().toISOString().split('T')[0];

        function navigateToDate(dateString) {
            // Ensure the link stays on admin-dashboard.php
            window.location.href = `admin-dashboard.php?date=${dateString}`;
        }

        function updateNextDayButtonState() {
            if (nextDayBtn && dateSelector) {
                nextDayBtn.disabled = dateSelector.value >= todayISO;
            }
        }

        if (dateSelector) {
            dateSelector.addEventListener('change', function() {
                navigateToDate(this.value);
            });
            // Prevent selecting future dates
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
            // Initial state for the next day button
            updateNextDayButtonState();
        }
    });
</script>
</body>
</html>