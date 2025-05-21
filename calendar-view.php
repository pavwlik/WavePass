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
$dbErrorMessage = null;
$currentUserData = null; // For current absence status

// --- Calendar Logic ---
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($month < 1 || $month > 12) $month = date('m');
if ($year < 1970 || $year > 2038) $year = date('Y'); // Reasonable range

$monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
$firstDayOfMonth = date('N', mktime(0, 0, 0, $month, 1, $year)); // 1 (Mon) - 7 (Sun)
$daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth == 0) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth == 13) {
    $nextMonth = 1;
    $nextYear++;
}

// --- Fetch data relevant for the calendar (e.g., current absence status) ---
$dailyData = []; // This array would hold events/logs for each day of the month

if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        // Fetch current user's 'absence' flag
        $stmtUser = $pdo->prepare("SELECT absence FROM users WHERE userID = :userid");
        $stmtUser->bindParam(':userid', $sessionUserId, PDO::PARAM_INT);
        $stmtUser->execute();
        $currentUserData = $stmtUser->fetch();
        if($stmtUser) $stmtUser->closeCursor();

        // --- PLACEHOLDER: Query for actual events/logs for the displayed month ---
        // Example: Fetch approved leave requests for the current user for the displayed month
        // $monthStartDate = "$year-$month-01";
        // $monthEndDate = "$year-$month-$daysInMonth";
        // $sqlLeave = "SELECT start_date, end_date, leave_type FROM leave_requests 
        //              WHERE user_id = :userid AND status = 'approved' AND 
        //              ((start_date BETWEEN :month_start AND :month_end) OR (end_date BETWEEN :month_start AND :month_end) OR (start_date < :month_start AND end_date > :month_end))";
        // $stmtLeave = $pdo->prepare($sqlLeave);
        // if ($stmtLeave) {
        //     $stmtLeave->bindParam(':userid', $sessionUserId, PDO::PARAM_INT);
        //     $stmtLeave->bindParam(':month_start', $monthStartDate, PDO::PARAM_STR);
        //     $stmtLeave->bindParam(':month_end', $monthEndDate, PDO::PARAM_STR);
        //     $stmtLeave->execute();
        //     while ($leave = $stmtLeave->fetch()) {
        //         $current = new DateTime($leave['start_date']);
        //         $end = new DateTime($leave['end_date']);
        //         $end->modify('+1 day'); // Include the end date itself
        //         $interval = new DateInterval('P1D');
        //         $period = new DatePeriod($current, $interval, $end);
        //         foreach ($period as $date) {
        //             $dayNum = $date->format('j');
        //             if ($date->format('Y-m') == "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT)) { // Check if the date is in the current viewing month
        //                  if (!isset($dailyData[$dayNum])) $dailyData[$dayNum] = [];
        //                  $dailyData[$dayNum][] = ['type' => 'leave', 'text' => ucfirst($leave['leave_type']) . ' Leave'];
        //             }
        //         }
        //     }
        //     $stmtLeave->closeCursor();
        // }

        // You would similarly query 'attendance_logs' for check-in/out, 'events' for meetings etc.
        // and populate $dailyData[$dayNumber][] with event objects/arrays.

    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error: " . $e->getMessage();
    }
} else {
    if (!isset($pdo) || !($pdo instanceof PDO)) $dbErrorMessage = "Database connection unavailable.";
    if (!$sessionUserId) $dbErrorMessage = "User session invalid.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Calendar - <?php echo $monthName . " " . $year; ?> - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4;
            --secondary-color: #3f37c9; --dark-color: #1a1a2e;
            --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color-val: 76, 201, 240; --warning-color-val: 248, 150, 30;
            --danger-color-val: 247, 37, 133; --info-color-val: 84, 160, 255;
            --present-color-val: 67, 170, 139; --absent-color-val: 214, 40, 40;
            --event-color-val: 108, 92, 231; /* A purple for general events */

            --success-color: rgb(var(--success-color-val)); 
            --warning-color: rgb(var(--warning-color-val)); 
            --danger-color: rgb(var(--danger-color-val)); 
            --info-color: rgb(var(--info-color-val)); 
            --present-color: rgb(var(--present-color-val)); 
            --absent-color: rgb(var(--absent-color-val));
            --event-color: rgb(var(--event-color-val));
            
            --shadow: 0 4px 25px rgba(0,0,0,0.08); --transition: all 0.3s ease-in-out;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
        h1,h2,h3,h4 {font-weight: 600;}

        /* NAVBAR STYLES (Same as dashboard2.php) */
        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; transition: var(--transition); }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; height: 80px; }
        .logo { font-size: 1.8rem; font-weight: 800; color: var(--primary-color); text-decoration: none; display: flex; align-items: center; }
        .logo-img { height: 50px; width: auto; vertical-align: middle; margin-right: 0.5rem; }
        .logo span { color: var(--dark-color); font-weight: 600; }
        .nav-links { display: flex; list-style: none; align-items: center; gap: 0.5rem; }
        .nav-links a:not(.btn) { color: var(--dark-color); text-decoration: none; font-weight: 500; padding: 0.7rem 1rem; font-size: 0.9rem; border-radius: 6px; transition: var(--transition); }
        .nav-links a:not(.btn):hover, .nav-links a.active { color: var(--primary-color); background-color: rgba(var(--primary-color-val), 0.07); }
        .nav-links .btn-outline { background-color: transparent; border: 1.5px solid var(--primary-color); color: var(--primary-color); display: inline-flex; gap: 6px; align-items: center; justify-content: center; padding: 0.6rem 1.1rem; border-radius: 6px; text-decoration: none; font-weight: 500; transition: var(--transition); cursor: pointer; text-align: center; font-size: 0.85rem;}
        .nav-links .btn-outline:hover { background-color: var(--primary-color); color: var(--white); }
        .nav-links .material-symbols-outlined { font-size: 1.2em; vertical-align: text-bottom; margin-right: 4px; }
        .hamburger { display: none; /* ... */ } @media (max-width: 992px) { .nav-links { display: none; } .hamburger { display: flex; } }
        .mobile-menu { /* ... */ } .mobile-menu.active { /* ... */ } .mobile-links { /* ... */ } .mobile-links a { /* ... */ } .mobile-menu .btn-outline { /* ... */ } .close-btn { /* ... */ }
        /* END OF NAVBAR STYLES */

        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; color: var(--dark-color); margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        .db-error-message {background-color: #fff3f3; color: #d32f2f; padding: 1rem; border-left: 4px solid #d32f2f; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}


        /* CALENDAR STYLES */
        .calendar-container {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        .calendar-header h2 {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin: 0;
        }
        .calendar-nav a {
            background-color: var(--light-color);
            border: 1px solid var(--light-gray);
            color: var(--dark-color);
            padding: 0.5rem 0.8rem;
            border-radius: 5px;
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
        }
        .calendar-nav a:hover {
            background-color: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
        }
        .calendar-nav a .material-symbols-outlined {
            font-size: 1.2em;
        }
        .calendar-nav a:first-child { margin-right: 0.5rem; }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px; /* Creates thin grid lines */
            background-color: var(--light-gray); /* Grid line color */
            border: 1px solid var(--light-gray);
        }
        .calendar-weekday, .calendar-day {
            background-color: var(--white);
            padding: 0.5rem;
            text-align: center;
            min-height: 100px; /* Minimum height for days */
            display: flex;
            flex-direction: column;
        }
        .calendar-weekday {
            font-weight: 600;
            color: var(--gray-color);
            font-size: 0.85rem;
            padding: 0.6rem 0.4rem;
            background-color: #f9fafb;
            min-height: auto;
        }
        .calendar-day .day-number {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
            display: block;
            text-align: right;
            color: var(--dark-color);
        }
        .calendar-day.other-month .day-number {
            color: var(--gray-color);
            opacity: 0.6;
        }
        .calendar-day.current-day .day-number {
            background-color: var(--primary-color);
            color: var(--white);
            border-radius: 50%;
            width: 28px;
            height: 28px;
            line-height: 28px;
            text-align: center;
            display: inline-block; /* Or flex to center better if needed */
            float:right;
            font-size:0.85rem;
        }
        .day-info {
            font-size: 0.75rem;
            margin-top: 0.2rem;
            text-align: left;
            flex-grow: 1; /* Allows it to take available space */
            overflow-y: auto; /* Scroll if too many items */
            max-height: 70px; /* Limit height of event list */
        }
        .day-info p {
            margin-bottom: 0.2rem;
            padding: 0.15rem 0.3rem;
            border-radius: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .day-info .status-present { background-color: rgba(var(--present-color-val), 0.1); color: var(--present-color); }
        .day-info .status-absent { background-color: rgba(var(--absent-color-val), 0.1); color: var(--absent-color); }
        .day-info .event-leave { background-color: rgba(var(--info-color-val),0.1); color: var(--info-color); border-left:2px solid var(--info-color);}
        .day-info .event-general { background-color: rgba(var(--event-color-val),0.1); color: var(--event-color); border-left:2px solid var(--event-color);}


        @media (max-width: 768px) {
            .calendar-weekday { font-size: 0.75rem; padding: 0.4rem 0.2rem; }
            .calendar-day { min-height: 80px; padding: 0.3rem; }
            .calendar-day .day-number { font-size:0.8rem; width:24px; height:24px; line-height:24px;}
            .day-info { font-size: 0.7rem; max-height: 50px; }
            .calendar-header h2 {font-size: 1.3rem;}
            .calendar-nav a {padding: 0.4rem 0.6rem; font-size:0.8rem;}
        }
        @media (max-width: 480px) {
            .calendar-weekday { display:none; } /* Hide full weekday names */
            .calendar-grid { grid-template-columns: repeat(7, 1fr); } /* Keep 7 columns */
            /* Alternatively, make it 1 column and scroll, but that's less "calendar-like" */
            .calendar-day::before { /* Show abbreviated weekday */
                content: attr(data-weekday-short);
                font-size: 0.65rem;
                color: var(--gray-color);
                font-weight: 500;
                display: block;
                text-align: left;
                margin-bottom:0.2rem;
            }
             .calendar-day { min-height: 70px; }
        }

        /* FOOTER (Same as dashboard2.php) */
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
                    <li><a href="dashboard2.php">My Dashboard</a></li>
                    <li><a href="calendar-view.php" class="active">Calendar</a></li>
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
             <li><a href="dashboard2.php">My Dashboard</a></li>
             <li><a href="calendar-view.php" class="active">Calendar</a></li>
             <li><a href="my_attendance_log.php">Attendance Log</a></li>
             <li><a href="request_leave.php">Request Leave</a></li>
             <li><a href="profile.php">My Profile</a></li>
        </ul>
        <a href="logout.php" class="btn btn-outline" style="margin-top:1rem;">Logout</a>
    </div>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>My Calendar</h1>
                <p class="sub-heading">Monthly overview of your schedule and attendance notes.</p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if ($dbErrorMessage): ?>
                <div class="db-error-message" role="alert">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i> <?php echo $dbErrorMessage; ?>
                </div>
            <?php endif; ?>

            <div class="calendar-container">
                <div class="calendar-header">
                    <h2><?php echo $monthName . " " . $year; ?></h2>
                    <div class="calendar-nav">
                        <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" title="Previous Month"><span class="material-symbols-outlined">chevron_left</span> Prev</a>
                        <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" title="Next Month">Next <span class="material-symbols-outlined">chevron_right</span></a>
                    </div>
                </div>

                <div class="calendar-grid">
                    <?php
                    $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    foreach ($weekdays as $weekday) {
                        echo "<div class='calendar-weekday'>$weekday</div>";
                    }

                    // Empty cells for the first week before the 1st day
                    for ($i = 1; $i < $firstDayOfMonth; $i++) {
                        echo "<div class='calendar-day other-month'></div>";
                    }

                    // Fill in the days of the month
                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $currentDayFull = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                        $isToday = ($currentDayFull == date('Y-m-d'));
                        $dayClass = $isToday ? 'calendar-day current-day' : 'calendar-day';
                        $weekdayShort = date('D', strtotime($currentDayFull)); // For data-attribute

                        echo "<div class='{$dayClass}' data-weekday-short='{$weekdayShort}'>";
                        echo "<span class='day-number'>$day</span>";
                        echo "<div class='day-info'>";

                        // --- Display info for the day ---
                        // This is where you'd iterate through $dailyData[$day] if populated from DB
                        if (isset($dailyData[$day]) && !empty($dailyData[$day])) {
                            foreach($dailyData[$day] as $event) {
                                $eventTypeClass = 'event-general'; // Default
                                if (isset($event['type'])) {
                                    if ($event['type'] === 'leave') $eventTypeClass = 'event-leave';
                                    // Add more type checks for 'meeting', 'task_due' etc.
                                }
                                echo "<p class='{$eventTypeClass}'>" . htmlspecialchars($event['text']) . "</p>";
                            }
                        } else {
                             // Placeholder/Simulated info if no specific events from $dailyData
                            if ($isToday && $currentUserData) {
                                if ($currentUserData['absence'] == 0) {
                                    echo "<p class='status-present'>Status: Present</p>";
                                } else {
                                    echo "<p class='status-absent'>Status: Absent</p>";
                                }
                            } else if ($day % 7 == 0 && $month == date('m') && $year == date('Y') && $day > date('d')){ // Example placeholder
                               // echo "<p class='event-general'>Team Sync-Up</p>";
                            }
                        }
                        echo "</div>"; // end .day-info
                        echo "</div>"; // end .calendar-day
                    }

                    // Empty cells for the last week after the last day
                    $totalCells = ( ($firstDayOfMonth-1) + $daysInMonth );
                    $remainingCells = (7 - ($totalCells % 7));
                    if($remainingCells < 7) { // only add if not a full week already
                        for ($i = 0; $i < $remainingCells; $i++) {
                            echo "<div class='calendar-day other-month'></div>";
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer (Full HTML for footer) -->
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
        // Mobile Menu Toggle (Same as dashboard2.php)
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');
        const closeMenu = document.getElementById('closeMenu');
        const body = document.body;

        if (hamburger && mobileMenu) {
            hamburger.onclick = () => { mobileMenu.classList.toggle('active'); body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden':''; hamburger.classList.toggle('active');}
            if(closeMenu) closeMenu.onclick = () => { mobileMenu.classList.remove('active'); body.style.overflow = ''; hamburger.classList.remove('active');}
            mobileMenu.querySelectorAll('a').forEach(link => link.onclick = () => {mobileMenu.classList.remove('active'); body.style.overflow = ''; hamburger.classList.remove('active'); });
        }

        // Header Shadow on Scroll (Same as dashboard2.php)
        const headerEl = document.querySelector('header');
        if (headerEl) { window.addEventListener('scroll', () => { headerEl.style.boxShadow = (window.scrollY > 10) ? '0 3px 10px rgba(0,0,0,0.07)' : '0 2px 6px rgba(0,0,0,0.05)'; }); }
    </script>
</body>
</html>