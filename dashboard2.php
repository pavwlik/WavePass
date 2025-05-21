<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

require_once 'db.php'; // Assumes $pdo is defined here

$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Employee';
$sessionUserId = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : null;

// --- Date handling for single day view ---
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
// Validate $selectedDate format if necessary, e.g., using DateTime::createFromFormat

// Initialize Stat Card Variables
$rfidStatus = "Status Unknown";
$rfidStatusClass = "neutral";
$absencesThisMonthCount = "N/A";
$warningMessagesCount = 0;
$upcomingLeaveDisplay = "None upcoming";

// Activity & Error Variables
$activityForSelectedDate = [];
$dbErrorMessage = null;
$currentUserData = null; // For the users.absence flag

if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        // Fetch current user's 'absence' flag for RFID status
        $stmtUser = $pdo->prepare("SELECT absence, dateOfCreation FROM users WHERE userID = :userid");
        $stmtUser->bindParam(':userid', $sessionUserId, PDO::PARAM_INT);
        $stmtUser->execute();
        $currentUserData = $stmtUser->fetch();

        if ($currentUserData) {
            // 1. RFID Status (Simulated - based on current 'absence' flag)
            if ($currentUserData['absence'] == 0) {
                $rfidStatus = "Checked In / Present";
                $rfidStatusClass = "present";
            } else {
                $rfidStatus = "Checked Out / Absent";
                $rfidStatusClass = "absent";
            }

            // 2. Absences This Month (Placeholder - requires 'attendance_logs' table)
            $currentMonthStart = date('Y-m-01');
            $currentMonthEnd = date('Y-m-t');
            // $sqlAbsences = "SELECT COUNT(DISTINCT log_date) as count FROM attendance_logs WHERE user_id = :userid AND status = 'absent' AND log_date BETWEEN :start_date AND :end_date";
            // $stmtAbsences = $pdo->prepare($sqlAbsences);
            // if ($stmtAbsences) {
            //     $stmtAbsences->bindParam(':userid', $sessionUserId, PDO::PARAM_INT);
            //     $stmtAbsences->bindParam(':start_date', $currentMonthStart, PDO::PARAM_STR);
            //     $stmtAbsences->bindParam(':end_date', $currentMonthEnd, PDO::PARAM_STR);
            //     $stmtAbsences->execute();
            //     $absenceData = $stmtAbsences->fetch();
            //     $absencesThisMonthCount = $absenceData ? $absenceData['count'] : 0;
            //     $stmtAbsences->closeCursor();
            // } else { $absencesThisMonthCount = "Query Error"; }
             // Simplified for now:
            $absencesThisMonthCount = ($currentUserData['absence'] == 1) ? "1 (Current)" : "0 (Current)";


            // 3. Unread Warning Messages (Placeholder - requires 'user_messages' table)
            // $sqlMessages = "SELECT COUNT(*) as count FROM user_messages WHERE user_id_receiver = :userid AND read_at IS NULL";
            // $stmtMessages = $pdo->prepare($sqlMessages);
            // if ($stmtMessages) {
            //    $stmtMessages->bindParam(':userid', $sessionUserId, PDO::PARAM_INT);
            //    $stmtMessages->execute();
            //    $msgData = $stmtMessages->fetch();
            //    $warningMessagesCount = $msgData ? $msgData['count'] : 0;
            //    $stmtMessages->closeCursor();
            // }

            // 4. Upcoming Approved Leave (Placeholder - requires 'leave_requests' table)
            $nextWeekStart = date('Y-m-d');
            $nextWeekEnd = date('Y-m-d', strtotime('+7 days'));
            // $sqlLeave = "SELECT start_date, leave_type FROM leave_requests WHERE user_id = :userid AND status = 'approved' AND start_date BETWEEN :start_date AND :end_date ORDER BY start_date ASC LIMIT 1";
            // $stmtLeave = $pdo->prepare($sqlLeave);
            // if ($stmtLeave) {
            //     $stmtLeave->bindParam(':userid', $sessionUserId, PDO::PARAM_INT);
            //     $stmtLeave->bindParam(':start_date', $nextWeekStart, PDO::PARAM_STR);
            //     $stmtLeave->bindParam(':end_date', $nextWeekEnd, PDO::PARAM_STR);
            //     $stmtLeave->execute();
            //     $leaveData = $stmtLeave->fetch();
            //     if ($leaveData) {
            //         $upcomingLeaveDisplay = ucfirst($leaveData['leave_type']) . " on " . date("M d", strtotime($leaveData['start_date']));
            //     }
            //     $stmtLeave->closeCursor();
            // }


            // --- Activity for Selected Date ---
            // This part heavily depends on the 'attendance_logs' table for real data.
            // For now, we'll show the simplified snapshot IF the selectedDate is today or registration date.
            if ($selectedDate == date("Y-m-d", strtotime($currentUserData['dateOfCreation']))) {
                 $activityForSelectedDate[] = ['date' => $selectedDate, 'type' => 'Account Registered', 'details' => 'Your WavePass account was created on this day.', 'status_class' => 'info'];
            }
            if ($selectedDate == date("Y-m-d")) { // If looking at today
                 $activityForSelectedDate[] = ['date' => $selectedDate, 'type' => 'Current System Status', 'details' => 'Live presence flag: ' . ($currentUserData['absence'] == 1 ? 'Absent' : 'Present'), 'status_class' => $currentUserData['absence'] == 1 ? 'absent' : 'present'];
            }
            
            // Example of fetching from a real attendance_logs table for the $selectedDate
            /*
            $sqlActivity = "SELECT log_date, status, check_in_time, check_out_time, notes
                            FROM attendance_logs
                            WHERE user_id = :userid AND log_date = :selected_date ORDER BY check_in_time ASC";
            $stmtActivity = $pdo->prepare($sqlActivity);
            if ($stmtActivity) {
                $stmtActivity->bindParam(':userid', $sessionUserId, PDO::PARAM_INT);
                $stmtActivity->bindParam(':selected_date', $selectedDate, PDO::PARAM_STR);
                $stmtActivity->execute();
                $activityForSelectedDate = []; // Reset for actual logs
                while ($log = $stmtActivity->fetch()) {
                    $activityForSelectedDate[] = [
                        'date' => date("M d, Y", strtotime($log['log_date'])),
                        'type' => ucfirst(str_replace('_', ' ', $log['status'])),
                        'details' => 'In: ' . ($log['check_in_time'] ? date("h:i A", strtotime($log['check_in_time'])) : '-') .
                                     ' | Out: ' . ($log['check_out_time'] ? date("h:i A", strtotime($log['check_out_time'])) : '-') .
                                     ($log['notes'] ? ' | Note: ' . htmlspecialchars($log['notes']) : ''),
                        'status_class' => strtolower($log['status'])
                    ];
                }
                if (empty($activityForSelectedDate) && $selectedDate <= date('Y-m-d')) { // Only if no specific logs but date is valid
                     $activityForSelectedDate[] = ['date' => $selectedDate, 'type' => 'No Record', 'details' => 'No specific attendance events logged for this day.', 'status_class' => 'neutral'];
                }
                $stmtActivity->closeCursor();
            }
            */
             if (empty($activityForSelectedDate) && ($selectedDate == date("Y-m-d", strtotime($currentUserData['dateOfCreation'])) || $selectedDate == date("Y-m-d"))){
                // This means the simplified data wasn't for selected day, and no real logs yet
             } else if (empty($activityForSelectedDate)){
                 $activityForSelectedDate[] = ['date' => $selectedDate, 'type' => 'No Record', 'details' => 'No activity data available for this day.', 'status_class' => 'neutral'];
             }


        } else {
            $dbErrorMessage = "Could not retrieve your user data.";
        }
        if($stmtUser) $stmtUser->closeCursor();

    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error: " . $e->getMessage();
        error_log("PDOException in Employee Dashboard (User: $sessionUserId): " . $e->getMessage());
    } catch (Exception $e) {
        $dbErrorMessage = "An application error occurred: " . $e->getMessage();
        error_log("Exception in Employee Dashboard (User: $sessionUserId): " . $e->getMessage());
    }
} else {
    if (!isset($pdo) || !($pdo instanceof PDO)) $dbErrorMessage = ($dbErrorMessage ? $dbErrorMessage . "<br>" : "") . "Database connection is not available.";
    if (!$sessionUserId) $dbErrorMessage = ($dbErrorMessage ? $dbErrorMessage . "<br>" : "") . "User session is invalid.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My WavePass Dashboard - <?php echo $sessionFirstName; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4;
            --secondary-color: #3f37c9; --dark-color: #1a1a2e;
            --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color-val: 76, 201, 240; /* Light Blue for generic success */
            --warning-color-val: 248, 150, 30;  /* Orange for Warning Messages */
            --danger-color-val: 247, 37, 133; 
            --info-color-val: 84, 160, 255;     /* Blue for Upcoming Leave */
            --neutral-color-val: 173, 181, 189; 
            --present-color-val: 67, 170, 139; /* Greenish for RFID Present */
            --absent-color-val: 214, 40, 40; /* Reddish for RFID Absent / Absence Count */

            --success-color: rgb(var(--success-color-val)); 
            --warning-color: rgb(var(--warning-color-val)); 
            --danger-color: rgb(var(--danger-color-val)); 
            --info-color: rgb(var(--info-color-val)); 
            --neutral-color: rgb(var(--neutral-color-val));
            --present-color: rgb(var(--present-color-val)); 
            --absent-color: rgb(var(--absent-color-val)); 
            
            --shadow: 0 4px 25px rgba(0,0,0,0.08); --transition: all 0.3s ease-in-out; /* Softer, larger shadow */
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; /* Slightly different bg */ display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; } /* Updated max-width */
        h1,h2,h3,h4 {font-weight: 600;}

        /* NAVBAR STYLES (Same as previous, from assistent.html) */
        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; transition: var(--transition); }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; height: 80px; }
        .logo { font-size: 1.8rem; font-weight: 800; color: var(--primary-color); text-decoration: none; display: flex; align-items: center; }
        .logo-img { height: 50px; width: auto; vertical-align: middle; margin-right: 0.5rem; }
        .logo span { color: var(--dark-color); font-weight: 600; }
        .nav-links { display: flex; list-style: none; align-items: center; gap: 0.5rem; }
        .nav-links a:not(.btn) { color: var(--dark-color); text-decoration: none; font-weight: 500; padding: 0.7rem 1rem; font-size: 0.9rem; border-radius: 6px; transition: var(--transition); }
        .nav-links a:not(.btn):hover, .nav-links a.active { color: var(--primary-color); background-color: rgba(var(--primary-color-val), 0.07); }
        .nav-links .btn-outline { background-color: transparent; border: 1.5px solid var(--primary-color); color: var(--primary-color); box-shadow: none; display: inline-flex; gap: 6px; align-items: center; justify-content: center; padding: 0.6rem 1.1rem; border-radius: 6px; text-decoration: none; font-weight: 500; transition: var(--transition); cursor: pointer; text-align: center; font-size: 0.85rem;}
        .nav-links .btn-outline:hover { background-color: var(--primary-color); color: var(--white); }
        .nav-links .material-symbols-outlined { font-size: 1.2em; vertical-align: text-bottom; margin-right: 4px; }
        .hamburger { display: none; /* ... */ } @media (max-width: 992px) { .nav-links { display: none; } .hamburger { display: flex; } }
        /* Mobile Menu styles are assumed to be the same as previously provided */
        .mobile-menu { position: fixed; top: 0; left: 0; width: 100%; height: 100vh; background-color: var(--white); z-index: 1000; display: flex; flex-direction: column; align-items: center; transform: translateX(-100%); transition: transform 0.4s cubic-bezier(0.23, 1, 0.32, 1); padding: 70px 1rem 2rem 1rem; overflow-y: auto; }
        .mobile-menu.active { transform: translateX(0); }
        .mobile-links { list-style: none; text-align: left; width: 100%; max-width: 320px; padding: 0; margin-top: 1rem; }
        .mobile-links li { margin-bottom: 0; }
        .mobile-links a { color: var(--dark-color); text-decoration: none; font-weight: 500; font-size: 1.05rem; display: block; padding: 0.9rem 1.2rem; transition: color var(--transition), background-color var(--transition); border-bottom: 1px solid var(--light-gray); border-radius: 0; }
        .mobile-links li:first-child a { border-top: 1px solid var(--light-gray); }
        .mobile-links a:hover, .mobile-links a.active { color: var(--primary-color); background-color: rgba(var(--primary-color-val), 0.07); }
        .mobile-menu .btn-outline { margin-top: 1.5rem; width: 100%; max-width: 280px; padding: 0.8rem 1.5rem; font-size: 0.95rem; text-align: center; }
        .close-btn { position: absolute; top: 25px; right: 25px; font-size: 1.6rem; color: var(--dark-color); cursor: pointer; transition: var(--transition); padding: 0.5rem; line-height: 1; }
        .close-btn:hover { color: var(--primary-color); transform: rotate(90deg); }
        /* END OF NAVBAR STYLES */


        .page-header { padding: 1.8rem 0; margin-bottom: 1.8rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; color: var(--dark-color); margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }

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
        
        .stat-card.absences-count::before { background-color: var(--absent-color); }
        .stat-card.absences-count .icon { background-color: rgba(var(--absent-color-val),0.1); color: var(--absent-color); }
        .stat-card.warnings::before { background-color: var(--warning-color); }
        .stat-card.warnings .icon { background-color: rgba(var(--warning-color-val),0.1); color: var(--warning-color); }
        .stat-card.upcoming-leave::before { background-color: var(--info-color); }
        .stat-card.upcoming-leave .icon { background-color: rgba(var(--info-color-val),0.1); color: var(--info-color); }
        .stat-card.upcoming-leave .info .value { font-size: 1.1rem; font-weight: 500; }


        .content-panel { background-color: var(--white); padding: 1.5rem 1.8rem; border-radius: 8px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); }
        .panel-title { font-size: 1.3rem; color: var(--dark-color); margin:0; }
        .date-navigation { display: flex; align-items: center; gap: 0.5rem; }
        .date-navigation .btn-nav { background-color:var(--light-color); border:1px solid var(--light-gray); color:var(--dark-color); padding:0.4rem 0.6rem; border-radius:4px; cursor:pointer; transition:var(--transition); }
        .date-navigation .btn-nav:hover { background-color:var(--primary-color); color:var(--white); border-color:var(--primary-color); }
        .date-navigation .btn-nav .material-symbols-outlined {font-size:1.2em; vertical-align:middle;}
        .date-navigation input[type="date"] { padding: 0.4rem 0.7rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.9rem; background-color: var(--white); height:36px; text-align:center;}

        .activity-table-wrapper { overflow-x: auto; min-height: 150px; /* Ensure some space even if empty */ }
        .activity-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .activity-table th, .activity-table td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid var(--light-gray); }
        .activity-table th { background-color: #f9fafb; font-weight: 500; color: var(--gray-color); white-space: nowrap; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; }
        .activity-table tbody tr:last-child td { border-bottom:none; }
        .activity-table tbody tr:hover { background-color: #f0f4ff; }
        .activity-status { display: inline-flex; align-items:center; gap:0.4rem; padding: 0.25rem 0.7rem; border-radius: 15px; font-size: 0.78rem; font-weight: 500; white-space: nowrap; }
        /* Dot styling for status can be kept same or refined */
        .activity-status.present { background-color: rgba(var(--present-color-val), 0.15); color: var(--present-color); } 
        .activity-status.absent { background-color: rgba(var(--absent-color-val), 0.15); color: var(--absent-color); }
        .activity-status.info { background-color: rgba(var(--info-color-val), 0.15); color: var(--info-color); }
        .activity-status.neutral { background-color: rgba(var(--neutral-color-val),0.15); color: var(--neutral-color);}


        .no-activity-msg {text-align:center; padding: 2.5rem 1rem; color:var(--gray-color); font-size:0.95rem; background-color: #fdfdfd; border-radius: 4px; border: 1px dashed var(--light-gray);}
        .placeholder-text {color: var(--gray-color); font-style: italic; font-size: 0.8rem;} /* Smaller placeholder text */
        .db-error-message {background-color: #fff3f3; color: #d32f2f; padding: 1rem; border-left: 4px solid #d32f2f; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}

        /* FOOTER (Same as before) */
        footer { background-color: var(--dark-color); color: var(--white); padding: 5rem 0 2rem; margin-top:auto;}
        /* ... full footer styles ... */
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">
                    <img src="imgs/logo.png" alt="WavePass Logo" class="logo-img">
                    Wave<span>Pass</span>
                </a>
                <ul class="nav-links">
                    <li><a href="dashboard2.php" class="active">My Dashboard</a></li> 
                    <li><a href="my_attendance_log.php">Attendance Log</a></li>
                    <li><a href="request_leave.php">Request Leave</a></li>
                    <li><a href="profile.php"><span class="material-symbols-outlined">account_circle</span><?php echo $sessionFirstName; ?></a></li>
                    <li><a href="logout.php" class="btn btn-outline">Logout</a></li> 
                </ul>
                <div class="hamburger" id="hamburger">
                    <span></span><span></span><span></span>
                </div>
            </nav>
        </div>
    </header>
    <div class="mobile-menu" id="mobileMenu"> 
        <span class="close-btn" id="closeMenu"><i class="fas fa-times"></i></span>
        <ul class="mobile-links">
             <li><a href="dashboard2.php" class="active">My Dashboard</a></li>
             <li><a href="my_attendance_log.php">Attendance Log</a></li>
             <li><a href="request_leave.php">Request Leave</a></li>
             <li><a href="profile.php">My Profile</a></li>
        </ul>
        <a href="logout.php" class="btn btn-outline" style="margin-top:1rem;">Logout</a>
    </div>

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
                    <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i> <?php echo $dbErrorMessage; ?>
                </div>
            <?php endif; ?>

            <section class="employee-stats-grid">
                <div class="stat-card rfid-status <?php echo $rfidStatusClass; ?>">
                    <div class="icon"><span class="material-symbols-outlined">
                        <?php echo ($rfidStatusClass === "present" ? "person_check" : ($rfidStatusClass === "absent" ? "person_off" : "contactless")); ?>
                    </span></div>
                    <div class="info">
                        <span class="value"><?php echo htmlspecialchars($rfidStatus); ?></span>
                        <span class="label">Your Current Status</span>
                    </div>
                </div>
                <div class="stat-card absences-count">
                    <div class="icon"><span class="material-symbols-outlined">calendar_month</span></div>
                    <div class="info">
                        <span class="value"><?php echo htmlspecialchars($absencesThisMonthCount); ?></span>
                        <span class="label">Absence Indicator <small class="placeholder-text">(this month)</small></span>
                    </div>
                </div>
                <div class="stat-card warnings">
                    <div class="icon"><span class="material-symbols-outlined">chat_error</span></div> 
                    <div class="info">
                        <span class="value"><?php echo $warningMessagesCount; ?></span>
                        <span class="label">Unread Warnings <small class="placeholder-text">(N/A)</small></span>
                    </div>
                </div>
                <div class="stat-card upcoming-leave">
                    <div class="icon"><span class="material-symbols-outlined">flight_takeoff</span></div>
                    <div class="info">
                        <span class="value"><?php echo htmlspecialchars($upcomingLeaveDisplay); ?></span>
                        <span class="label">Upcoming Leave <small class="placeholder-text">(next 7 days)</small></span>
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
                 <p class="placeholder-text" style="margin-bottom:1.2rem; font-size:0.8rem;"><i class="fas fa-info-circle"></i> This view shows basic activity. For a comprehensive history, visit the <a href="my_attendance_log.php?date=<?php echo $selectedDate; ?>" style="color:var(--primary-color)">Full Attendance Log</a>.</p>

                <div class="activity-table-wrapper">
                    <table class="activity-table" id="employeeActivityTable_singleDay">
                        <thead>
                            <tr>
                                <!-- <th>Time</th> Can be added if attendance_logs has check_in_time -->
                                <th>Activity Type</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($activityForSelectedDate)): ?>
                                <?php foreach ($activityForSelectedDate as $activity): ?>
                                    <tr>
                                        <td>
                                            <span class="activity-status <?php echo htmlspecialchars($activity['status_class']); ?>">
                                                <?php echo htmlspecialchars($activity['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['details']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="no-activity-msg">No specific activity recorded for <?php echo date("M d, Y", strtotime($selectedDate)); ?>.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <!-- Footer (Full HTML for footer should be placed here) -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column"><h3>WavePass</h3><p>Modern attendance tracking...</p><div class="social-links"><a href="#"><i class="fab fa-facebook-f"></i></a><a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-linkedin-in"></i></a><a href="#"><i class="fab fa-instagram"></i></a></div></div>
                <div class="footer-column"><h3>Quick Links</h3><ul class="footer-links"><li><a href="index.php#features"><i class="fas fa-chevron-right"></i> Features</a></li><li><a href="index.php#how-it-works"><i class="fas fa-chevron-right"></i> How It Works</a></li><li><a href="pricing.php"><i class="fas fa-chevron-right"></i> Pricing</a></li><li><a href="index.php#contact"><i class="fas fa-chevron-right"></i> Contact</a></li><li><a href="index.php#faq"><i class="fas fa-chevron-right"></i> FAQ</a></li><li><a href="help.php"><i class="fas fa-chevron-right"></i> Help Center</a></li></ul></div>
                <div class="footer-column"><h3>Resources</h3><ul class="footer-links"><li><a href="blog.php"><i class="fas fa-chevron-right"></i> Blog</a></li><li><a href="help.php"><i class="fas fa-chevron-right"></i> Help Center</a></li><li><a href="webinars.php"><i class="fas fa-chevron-right"></i> Webinars</a></li><li><a href="api.php"><i class="fas fa-chevron-right"></i> API Documentation</a></li></ul></div>
                <div class="footer-column"><h3>Contact Info</h3><ul class="footer-links"><li><a href="mailto:info@WavePass.com"><i class="fas fa-envelope"></i> info@WavePass.com</a></li><li><a href="tel:+15551234567"><i class="fas fa-phone"></i> +1 (555) 123-4567</a></li><li><a href="https://www.google.com/maps/search/?api=1&query=123%20Education%20St%2C%20Boston%2C%20MA%2002115" target="_blank" rel="noopener noreferrer"><i class="fas fa-map-marker-alt"></i> 123 Education St...</a></li></ul></div>
            </div>
            <div class="footer-bottom">
                <p>© <?php echo date("Y"); ?> WavePass. All rights reserved. | <a href="privacy.php">Privacy Policy</a> | <a href="terms.php">Terms of Service</a></p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile Menu Toggle (Same as before)
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');
        const closeMenu = document.getElementById('closeMenu');
        const body = document.body;

        if (hamburger && mobileMenu && closeMenu) {
            hamburger.addEventListener('click', () => { /* ... */ });
            closeMenu.addEventListener('click', () => { /* ... */ });
            mobileMenu.querySelectorAll('a').forEach(link => { link.addEventListener('click', () => { /* ... */ }); });
        }
        if (hamburger && mobileMenu) { // Simplified for brevity
            hamburger.onclick = () => { mobileMenu.classList.toggle('active'); body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden':''; hamburger.classList.toggle('active');}
            if(closeMenu) closeMenu.onclick = () => { mobileMenu.classList.remove('active'); body.style.overflow = ''; hamburger.classList.remove('active');}
             mobileMenu.querySelectorAll('a').forEach(link => link.onclick = () => {mobileMenu.classList.remove('active'); body.style.overflow = ''; hamburger.classList.remove('active'); });
        }


        const headerEl = document.querySelector('header');
        if (headerEl) { window.addEventListener('scroll', () => { headerEl.style.boxShadow = (window.scrollY > 10) ? '0 3px 10px rgba(0,0,0,0.07)' : '0 2px 6px rgba(0,0,0,0.05)'; }); }

        // Date Navigation for Single Day View
        const dateSelector = document.getElementById('activity-date-selector');
        const prevDayBtn = document.getElementById('prevDayBtn');
        const nextDayBtn = document.getElementById('nextDayBtn');

        function navigateToDate(dateString) {
            window.location.href = `dashboard2.php?date=${dateString}`;
        }

        if (dateSelector) {
            dateSelector.addEventListener('change', function() {
                navigateToDate(this.value);
            });
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
                const currentDate = new Date(dateSelector.value);
                // Prevent navigating to future dates beyond today for "activity"
                if (currentDate.toISOString().split('T')[0] < new Date().toISOString().split('T')[0]) {
                    currentDate.setDate(currentDate.getDate() + 1);
                    navigateToDate(currentDate.toISOString().split('T')[0]);
                } else {
                    alert("Cannot view future dates for activity log.");
                }
            });
        }

        // The applyTableFilters() function is less relevant for a single-day view
        // unless you plan to filter specific event types within that day from a more detailed log.
        // For now, it can be removed or significantly simplified if not used.
        // function applyTableFilters() { ... }

    </script>
</body>
</html>