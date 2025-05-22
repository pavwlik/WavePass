<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- SESSION CHECK ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

require_once 'db.php'; // Your database connection

// --- SESSION VARIABLES ---
$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Employee';
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
$sessionRole = isset($_SESSION["role"]) ? strtolower($_SESSION["role"]) : 'employee'; // Ensure role is lowercase

// --- ROLE-BASED HEADER INCLUSION ---
// Determine which header to include based on user role
if ($sessionRole === 'admin') {
    require_once 'components/header-admin.php';
} else {
    require_once 'components/header-employee.php';
}

// --- DATE HANDLING ---
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$todayDate = date('Y-m-d');
$todayDateTime = date('Y-m-d H:i:s');

// --- DEFAULT VALUES FOR DISPLAY ---
$rfidStatus = "Status Unknown"; 
$rfidStatusClass = "neutral";
$absencesThisMonthCountDisplay = "0 Requests"; 
$unreadMessagesCount = 0; 
$upcomingLeaveDisplay = "None upcoming"; 
$activityForSelectedDate = [];
$dbErrorMessage = null;
$currentUserData = null; 

if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        // 1. Fetch user's dateOfCreation for activity snapshot
        $stmtUserMeta = $pdo->prepare("SELECT dateOfCreation FROM users WHERE userID = :userid");
        if ($stmtUserMeta) {
            $stmtUserMeta->execute([':userid' => $sessionUserId]);
            $currentUserData = $stmtUserMeta->fetch();
            $stmtUserMeta->closeCursor();
        }

        // --- 2. DETERMINE CURRENT PRESENCE STATUS (for today) ---
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
                } elseif ($latestEvent['logType'] == 'exit') {
                    $rfidStatus = "Checked Out";
                    $rfidStatusClass = "absent"; 
                } elseif ($latestEvent['logResult'] == 'denied') {
                    $rfidStatus = "Access Denied";
                    $rfidStatusClass = "danger"; // Use danger class for denied
                } else {
                    // Should not happen with current enum, but good for robustness
                    $rfidStatus = "Status Unknown"; 
                    $rfidStatusClass = "neutral";
                }
            } else {
                // No attendance log events for today. Check the 'absence' table for scheduled absences.
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
                        // No log for today and no scheduled absence. Fallback to users.absence flag (if used).
                        // For this dashboard, "Not Checked In" is more direct if no explicit absence.
                        $rfidStatus = "Not Checked In";
                        $rfidStatusClass = "neutral"; 
                    }
                }
            }
        }

        // --- 3. APPROVED ABSENCES THIS MONTH ---
        $currentMonthStart = date('Y-m-01 00:00:00');
        $currentMonthEnd = date('Y-m-t 23:59:59'); 
        $stmtAbsenceCount = $pdo->prepare(
            "SELECT COUNT(DISTINCT DATE(absence_start_datetime)) -- Count distinct days for multi-day absences
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
            $absencesThisMonthCountDisplay = $absencesThisMonthCountValue ? $absencesThisMonthCountValue . " Approved" : "0 Approved";
            $stmtAbsenceCount->closeCursor();
        }

        $stmtUnreadMessages = $pdo->prepare(
            " SELECT COUNT(m.messageID) 
             FROM messages m
             LEFT JOIN user_message_read_status umrs ON m.messageID = umrs.messageID AND umrs.userID = :userid1
             WHERE m.recipientID = :userid2
             AND m.is_active = 1
             AND (umrs.is_read IS NULL OR umrs.is_read = 0)
             AND (m.expires_at IS NULL OR m.expires_at > NOW())"
         );
         
         // And update the execute call:
         $stmtUnreadMessages->execute([
             ':userid1' => $sessionUserId,
             ':userid2' => $sessionUserId
         ]);

        // --- 5. UPCOMING LEAVE ---
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

        // --- 6. ACTIVITY SNAPSHOT FOR SELECTED DATE ---
        // Account Creation
        if ($currentUserData && $selectedDate == date("Y-m-d", strtotime($currentUserData['dateOfCreation']))) {
             $activityForSelectedDate[] = [
                 'time' => date("H:i", strtotime($currentUserData['dateOfCreation'])),
                 'type' => 'Account Registered',
                 'details' => 'Your WavePass account was created.',
                 'rfid_card' => 'N/A', 
                 'status_class' => 'info'
                ];
        }

        // Attendance Logs for selected date
        $stmtActivityLog = $pdo->prepare(
            "SELECT logTime, logType, logResult 
             FROM attendance_logs
             WHERE userID = :userid AND DATE(logTime) = :selected_date
             ORDER BY logTime ASC"
        );
        if ($stmtActivityLog) {
            $stmtActivityLog->execute([
                ':userid' => $sessionUserId,
                ':selected_date' => $selectedDate
            ]);
            while ($log = $stmtActivityLog->fetch(PDO::FETCH_ASSOC)) {
                $activityTypeDisplay = 'Unknown Event';
                $statusClass = 'neutral';
                if ($log['logType'] == 'entry') {
                    if ($log['logResult'] == 'granted') {
                        $activityTypeDisplay = 'Check In';
                        $statusClass = 'present';
                    } else {
                        $activityTypeDisplay = 'Check In Denied';
                        $statusClass = 'danger';
                    }
                } elseif ($log['logType'] == 'exit') {
                    $activityTypeDisplay = 'Check Out';
                    $statusClass = 'absent';
                }
                
                $details = 'Access: ' . htmlspecialchars(ucfirst($log['logResult'] ?? 'N/A'));

                $activityForSelectedDate[] = [
                    'time' => date("H:i", strtotime($log['logTime'])),
                    'type' => $activityTypeDisplay,
                    'details' => $details, 
                    'rfid_card' => 'System Log', // Or specific card ID if available and needed
                    'status_class' => $statusClass
                ];
            }
            $stmtActivityLog->closeCursor();
        }
        
        // If it's today and no specific attendance_logs yet, but we determined current status, add it.
        $hasCheckInOutActivity = false;
        foreach($activityForSelectedDate as $act) {
            if (strpos($act['type'], 'Check In') !== false || strpos($act['type'], 'Check Out') !== false) {
                $hasCheckInOutActivity = true;
                break;
            }
        }
        if ($selectedDate == $todayDate && !$hasCheckInOutActivity && $rfidStatus !== "Status Unknown" && $rfidStatus !== "Not Checked In") {
             // Avoid adding "Not Checked In" if there's no activity, as "No Specific Record" will cover it.
             // Only add if there's a determined status like Present, Checked Out, Scheduled Absence based on today's logic.
             if ($rfidStatusClass !== 'neutral' || strpos(strtolower($rfidStatus), 'scheduled') !== false) {
                 $activityForSelectedDate[] = [
                     'time' => date("H:i"), // Current time, or '--:--' if preferred
                     'type' => $rfidStatus,
                     'details' => 'Based on current system status.',
                     'rfid_card' => 'N/A', 
                     'status_class' => $rfidStatusClass
                    ];
             }
        }


        // Message if no specific records for the day (after checking logs and creation)
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
                    ':userid' => $sessionUserId,
                    ':selected_date_for_absence' => $selectedDate
                ]);
                $selectedDayAbsenceInfo = $stmtSelectedDayAbsence->fetch();
                $stmtSelectedDayAbsence->closeCursor();

                if($selectedDayAbsenceInfo){
                    $absenceTypeDetailForMsg = htmlspecialchars(ucfirst(str_replace('_',' ',$selectedDayAbsenceInfo['absence_type'])));
                    $activityForSelectedDate[] = [
                         'time' => '--:--', 
                         'type' => 'Scheduled Absence', 
                         'details' => 'Type: ' . $absenceTypeDetailForMsg . ($selectedDayAbsenceInfo['reason'] ? ' - Reason: '.htmlspecialchars($selectedDayAbsenceInfo['reason']) : ''), 
                         'rfid_card' => 'N/A', 
                         'status_class' => 'absent'
                        ];
                } else {
                     $activityForSelectedDate[] = [
                         'time' => '--:--', 
                         'type' => 'No Specific Record', 
                         'details' => 'No attendance events or approved absences logged for this day.', 
                         'rfid_card' => 'N/A', 
                         'status_class' => 'neutral'
                        ];
                }
            }
        }
        // Sort activity by time to ensure chronological order if items were added out of sequence
        if (!empty($activityForSelectedDate)) {
            usort($activityForSelectedDate, function($a, $b) {
                if ($a['time'] === '--:--') return -1; // Put '--:--' (like 'No Record') at the beginning or end
                if ($b['time'] === '--:--') return 1;
                return strtotime($a['time']) - strtotime($b['time']);
            });
        }


    } catch (PDOException $e) {
        error_log("Dashboard DB Error (User: {$sessionUserId}, Date: {$selectedDate}): " . $e->getMessage());
        $dbErrorMessage = "A database error occurred. Please try again later. If the problem persists, contact support.";
        $dbErrorMessage = "Database Query Error: " . $e->getMessage();
    } catch (Exception $e) {
        error_log("Dashboard App Error (User: {$sessionUserId}, Date: {$selectedDate}): " . $e->getMessage());
        $dbErrorMessage = "An application error occurred. Please try again later.";
        // For development: $dbErrorMessage = "Application Error: " . $e->getMessage();
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
                --warning-color: #f8961e; /* Orange for warnings */
                --danger-color: #f72585; /* Pink/Red for danger/denied */
                --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                --transition: all 0.3s ease;
                
                --present-color-val: 67, 170, 139; /* Green */
                --absent-color-val: 214, 40, 40;   /* Red */
                --info-color-val: 84, 160, 255;    /* Blue */
                --neutral-color-val: 173, 181, 189; /* Gray */
                --warning-color-val: 248, 150, 30; /* RGB for var(--warning-color) */
                --danger-color-val: 247, 37, 133; /* RGB for var(--danger-color) */

                --present-color: rgb(var(--present-color-val)); 
                --absent-color: rgb(var(--absent-color-val)); 
                --info-color: rgb(var(--info-color-val));
                --neutral-color: rgb(var(--neutral-color-val));
            }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                line-height: 1.6; color: var(--dark-color); background-color: var(--light-color);
                overflow-x: hidden; scroll-behavior: smooth; display: flex; flex-direction: column; min-height: 100vh;
            }
            main { flex-grow: 1; padding-top: 80px; background-color: #f4f6f9; }
            .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
            h1, h2, h3, h4 { font-weight: 700; line-height: 1.2; }

            header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; transition: var(--transition); }
            .navbar { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; height: 80px; }
            .logo { font-size: 1.8rem; font-weight: 800; color: var(--primary-color); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
            .logo i { font-size: 1.5rem; } 
            .logo span { color: var(--dark-color); font-weight: 600; }
            .nav-links { display: flex; list-style: none; align-items: center; gap: 0.5rem; transition: var(--transition); }
            .nav-links a:not(.btn) { color: var(--dark-color); text-decoration: none; font-weight: 500; transition: color var(--transition), background-color var(--transition); padding: 0.7rem 1rem; font-size: 0.95rem; border-radius: 8px; position: relative; }
            .nav-links a:not(.btn):hover, .nav-links a.active-nav-link { color: var(--primary-color); background-color: rgba(67,97,238,0.07); }
            .nav-links a:not(.btn)::after { display: none; }
            .nav-links .btn, .nav-links .btn-outline { display: inline-flex; gap: 8px; align-items: center; justify-content: center; padding: 0.7rem 1.2rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: var(--transition); cursor: pointer; text-align: center; font-size: 0.9rem; }
            .nav-links .btn { background-color: var(--primary-color); color: var(--white); box-shadow: 0 4px 14px rgba(67,97,238,0.2); }
            .nav-links .btn:hover { background-color: var(--primary-dark); box-shadow: 0 6px 20px rgba(67,97,238,0.3); transform: translateY(-2px); }
            .nav-links .btn-outline { background-color: transparent; border: 2px solid var(--primary-color); color: var(--primary-color); box-shadow: none; }
            .nav-links .btn-outline:hover { background-color: var(--primary-color); color: var(--white); transform: translateY(-2px); }
            .nav-links .material-symbols-outlined { font-size: 1.2em; vertical-align:text-bottom; margin-right:4px;}

            .hamburger { display: none; cursor: pointer; width: 30px; height: 24px; position: relative; z-index: 1001; transition: var(--transition); }
            .hamburger span { display: block; width: 100%; height: 3px; background-color: var(--dark-color); position: absolute; left: 0; transition: var(--transition); transform-origin: center; }
            .hamburger span:nth-child(1) { top: 0; } .hamburger span:nth-child(2) { top: 50%; transform: translateY(-50%); } .hamburger span:nth-child(3) { bottom: 0; }
            .hamburger.active span:nth-child(1) { top: 50%; transform: translateY(-50%) rotate(45deg); } .hamburger.active span:nth-child(2) { opacity: 0; } .hamburger.active span:nth-child(3) { bottom: 50%; transform: translateY(50%) rotate(-45deg); }
            @media (max-width: 992px) { .nav-links { display: none; } .hamburger { display: flex; flex-direction: column; justify-content: space-between; } }

            .mobile-menu { position: fixed; top: 0; left: 0; width: 100%; height: 100vh; background-color: var(--white); z-index: 1000; display: flex; flex-direction: column; justify-content: center; align-items: center; transform: translateX(-100%); transition: transform 0.4s cubic-bezier(0.23, 1, 0.32, 1); padding: 2rem; }
            .mobile-menu.active { transform: translateX(0); }
            .mobile-links { list-style: none; text-align: center; width: 100%; max-width: 300px; padding:0; }
            .mobile-links li { margin-bottom: 1.5rem; }
            .mobile-links a { color: var(--dark-color); text-decoration: none; font-weight: 600; font-size: 1.2rem; display: inline-block; padding: 0.5rem 1rem; transition: var(--transition); border-radius: 8px; }
            .mobile-links a:hover, .mobile-links a.active-nav-link { color: var(--primary-color); background-color: rgba(67,97,238,0.1); }
            .mobile-menu .btn-outline { margin-top: 2rem; width: 100%; max-width: 200px; }
            .close-btn { position: absolute; top: 30px; right: 30px; font-size: 1.8rem; color: var(--dark-color); cursor: pointer; transition: var(--transition); }
            .close-btn:hover { color: var(--primary-color); transform: rotate(90deg); }
            
            /* Dashboard Specific Styles */
            .page-header { padding: 1.8rem 0; margin-bottom: 1.8rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
            .page-header h1 { font-size: 1.7rem; color: var(--dark-color); margin: 0; }
            .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
            .db-error-message {background-color: rgba(var(--danger-color-val),0.1); color: var(--danger-color); padding: 1rem; border-left: 4px solid var(--danger-color); margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}

            .employee-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.2rem; margin-bottom: 2.5rem; }
            .stat-card { background-color: var(--white); padding: 1.2rem 1.4rem; border-radius: 8px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1.2rem; transition: var(--transition); border: 1px solid var(--light-gray); position:relative; overflow:hidden;}
            .stat-card::before { content: ''; position:absolute; top:0; left:0; width:5px; height:100%; background-color:transparent; transition: var(--transition); }
            .stat-card:hover{transform: translateY(-4px); box-shadow: 0 6px 28px rgba(0,0,0,0.09);}
            .stat-card .icon { font-size: 2rem; padding: 0.7rem; border-radius: 50%; display:flex; align-items:center; justify-content:center; width:50px; height:50px; flex-shrink:0;}
            .stat-card .info .value { font-size: 1.4rem; font-weight: 700; color: var(--dark-color); display:block; line-height:1.2; margin-bottom:0.2rem;}
            .stat-card .info .label { font-size: 0.85rem; color: var(--gray-color); text-transform: uppercase; letter-spacing: 0.3px; }
            
            .stat-card.rfid-status.present::before { background-color: var(--present-color); }
            .stat-card.rfid-status.present .icon { background-color: rgba(var(--present-color-val),0.1); color: var(--present-color); }
            .stat-card.rfid-status.absent::before { background-color: var(--absent-color); }
            .stat-card.rfid-status.absent .icon { background-color: rgba(var(--absent-color-val),0.1); color: var(--absent-color); }
            .stat-card.rfid-status.neutral::before { background-color: var(--neutral-color); }
            .stat-card.rfid-status.neutral .icon { background-color: rgba(var(--neutral-color-val),0.1); color: var(--neutral-color); }
            .stat-card.rfid-status.danger::before { background-color: var(--danger-color); }
            .stat-card.rfid-status.danger .icon { background-color: rgba(var(--danger-color-val),0.1); color: var(--danger-color); }
            
            .stat-card.absences-count::before { background-color: var(--absent-color); } /* Using absent color for absences */
            .stat-card.absences-count .icon { background-color: rgba(var(--absent-color-val),0.1); color: var(--absent-color); }
            .stat-card.unread-messages::before { background-color: var(--warning-color); } 
            .stat-card.unread-messages .icon { background-color: rgba(var(--warning-color-val),0.1); color: var(--warning-color); } 
            .stat-card.upcoming-leave::before { background-color: var(--info-color); } 
            .stat-card.upcoming-leave .icon { background-color: rgba(var(--info-color-val),0.1); color: var(--info-color); }
            .stat-card.upcoming-leave .info .value { font-size: 1.1rem; font-weight: 500; }

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
            
            footer { background-color: var(--dark-color); color: var(--white); padding: 5rem 0 2rem; margin-top:auto;}
            .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 3rem; margin-bottom: 3rem; }
            .footer-column h3 { font-size: 1.3rem; margin-bottom: 1.8rem; position: relative; padding-bottom: 0.8rem; }
            .footer-column h3::after { content: ''; position: absolute; left: 0; bottom: 0; width: 50px; height: 3px; background-color: var(--primary-color); border-radius: 3px; }
            .footer-links { list-style: none; padding:0; } .footer-links li { margin-bottom: 0.8rem; }
            .footer-links a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); font-size: 0.95rem; display: inline-block; padding: 0.2rem 0; }
            .footer-links a:hover { color: var(--white); transform: translateX(5px); }
            .footer-links a i { margin-right: 0.5rem; width: 20px; text-align: center; } 
            .social-links { display: flex; gap: 1.2rem; margin-top: 1.5rem; padding:0; }
            .social-links a { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background-color: rgba(255, 255, 255, 0.1); color: var(--white); border-radius: 50%; font-size: 1.1rem; transition: var(--transition); }
            .social-links a:hover { background-color: var(--primary-color); transform: translateY(-3px); }
            .footer-bottom { text-align: center; padding-top: 3rem; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.9rem; color: rgba(255, 255, 255, 0.6); }
            .footer-bottom a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); }
            .footer-bottom a:hover { color: var(--primary-color); }
        </style>
</head>
<body>
        <!-- header !-->
        <?php require "components/header-admin.php"; ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>Hello, <?php echo $sessionFirstName; ?>!</h1>
                <p class="sub-heading">Your personal attendance summary and daily activity.</p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if ($dbErrorMessage): ?>
                <div class="db-error-message" role="alert">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i> <?php echo htmlspecialchars($dbErrorMessage); ?>
                </div>
            <?php endif; ?>

            <section class="employee-stats-grid">
                <div class="stat-card rfid-status <?php echo $rfidStatusClass; ?>">
                    <div class="icon"><span class="material-symbols-outlined">
                        <?php 
                            if ($rfidStatusClass === "present") echo "person_check";
                            elseif ($rfidStatusClass === "absent") echo "person_off";
                            elseif ($rfidStatusClass === "danger") echo "gpp_maybe"; // Icon for access denied
                            else echo "contactless"; // Default/Neutral
                        ?>
                    </span></div>
                    <div class="info">
                        <span class="value"><?php echo htmlspecialchars($rfidStatus); ?></span>
                        <span class="label">Your Current Status</span>
                    </div>
                </div>
                <div class="stat-card absences-count">
                    <div class="icon"><span class="material-symbols-outlined">calendar_month</span></div>
                    <div class="info">
                        <span class="value"><?php echo htmlspecialchars($absencesThisMonthCountDisplay); ?></span>
                        <span class="label">Absences <small class="placeholder-text">(this month)</small></span>
                    </div>
                </div>
                <div class="stat-card unread-messages"> <!-- Changed class from warnings -->
                    <div class="icon"><span class="material-symbols-outlined">mark_chat_unread</span></div> <!-- Icon for unread messages -->
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
            </section>

            <section class="content-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Activity for <span id="selectedDateDisplay"><?php echo date("F d, Y", strtotime($selectedDate)); ?></span></h2>
                    <div class="date-navigation">
                        <button class="btn-nav" id="prevDayBtn" title="Previous Day"><span class="material-symbols-outlined">chevron_left</span></button>
                        <input type="date" id="activity-date-selector" value="<?php echo $selectedDate; ?>">
                        <button class="btn-nav" id="nextDayBtn" title="Next Day"><span class="material-symbols-outlined">chevron_right</span></button>
                    </div>
                </div>
                <p class="placeholder-text" style="margin-bottom:1.2rem; font-size:0.8rem;">
                    <i class="fas fa-info-circle"></i> This view shows basic activity. For a comprehensive history, visit the 
                    <a href="my_attendance_log.php?date=<?php echo $selectedDate; ?>" style="color:var(--primary-color)">Full Attendance Log</a>.
                </p>

                <div class="activity-table-wrapper">
                    <table class="activity-table" id="employeeActivityTable_singleDay">
                        <thead>
                            <tr>
                                <th>Time</th> 
                                <th>Activity Type</th>
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
                                                <?php echo htmlspecialchars($activity['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['details']); ?></td>
                                        <td class="rfid-cell"><?php echo htmlspecialchars($activity['rfid_card']); ?></td> 
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="no-activity-msg">No specific activity recorded for <?php echo date("M d, Y", strtotime($selectedDate)); ?>.</td>  
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <!-- Footer -->
    <?php  require_once "components/footer.php"; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');
        const closeMenu = document.getElementById('closeMenu');
        const body = document.body;

        if (hamburger && mobileMenu && closeMenu) {
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                mobileMenu.classList.toggle('active');
                body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
            });

            closeMenu.addEventListener('click', () => {
                hamburger.classList.remove('active');
                mobileMenu.classList.remove('active');
                body.style.overflow = '';
            });

            mobileMenu.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', (e) => {
                    // Allow navigation for actual links, prevent for '#'
                    if (link.getAttribute('href') === '#' && e) {
                        e.preventDefault(); 
                    }
                    if (mobileMenu.classList.contains('active')) {
                        hamburger.classList.remove('active');
                        mobileMenu.classList.remove('active');
                        body.style.overflow = '';
                    }
                });
            });
        }

        const headerEl = document.querySelector('header');
        if (headerEl) { 
            const initialHeaderShadow = getComputedStyle(headerEl).boxShadow;
            window.addEventListener('scroll', () => {
                let scrollShadow = getComputedStyle(document.documentElement).getPropertyValue('--shadow').trim() || '0 4px 10px rgba(0,0,0,0.05)';
                if (window.scrollY > 10) {
                    headerEl.style.boxShadow = scrollShadow; 
                } else {
                    headerEl.style.boxShadow = initialHeaderShadow;
                }
            });
        }

        const dateSelector = document.getElementById('activity-date-selector');
        const prevDayBtn = document.getElementById('prevDayBtn');
        const nextDayBtn = document.getElementById('nextDayBtn');
        const todayISO = new Date().toISOString().split('T')[0];

        function navigateToDate(dateString) {
            window.location.href = `dashboard.php?date=${dateString}`;
        }

        function updateNextDayButtonState() {
            if (nextDayBtn && dateSelector) {
                // Dates are compared as YYYY-MM-DD strings for simplicity
                nextDayBtn.disabled = dateSelector.value >= todayISO;
            }
        }

        if (dateSelector) {
            dateSelector.addEventListener('change', function() { 
                navigateToDate(this.value); 
            });
            // Set max attribute to prevent selecting future dates in the picker
            dateSelector.max = todayISO;
        }
        if (prevDayBtn) {
            prevDayBtn.addEventListener('click', function() {
                // Date manipulation must be careful with timezones if not using UTC
                const currentDate = new Date(dateSelector.value); // Assumes local timezone from picker
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
            updateNextDayButtonState(); // Initial check
        }
    });
</script>
</body>
</html>