<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Restrict Access: Ensure only admin can access
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    $loginPath = '';
    if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
        $loginPath = '../login.php';
    } else {
        $loginPath = 'login.php';
    }
    if (file_exists(dirname(__FILE__) . '/' . $loginPath)) {
         header("location: " . $loginPath);
    } else {
        die("Error: Login page not found (tried: " . htmlspecialchars(dirname(__FILE__) . '/' . $loginPath) . ")");
    }
    exit;
}

// Připojení k databázi
$dbPath = '';
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $dbPath = '../db.php';
} else {
    $dbPath = 'db.php';
}
if (file_exists(dirname(__FILE__) . '/' . $dbPath)) {
    require_once dirname(__FILE__) . '/' . $dbPath;
} else {
    die("Error: Database configuration file not found (tried: " . htmlspecialchars(dirname(__FILE__) . '/' . $dbPath) . ")");
}

$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;

$dbErrorMessage = null;
$stats = [
    'total_employees' => 0,
    'total_admins' => 0,
    'employees_present' => 0, // Ti, co jsou PŘIHLÁŠENI a NEMAJÍ aktivní absenci
    'employees_on_approved_absence' => 0, // Nová statistika: Ti, co MAJÍ aktivní schválenou absenci
    'assigned_rfid_cards' => 0,
    'unassigned_rfid_cards' => 0,
    'active_rfid_cards' => 0,
    'pending_absences' => 0,
    // 'recent_activity' => [] // Pokud budete potřebovat
];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $todayDate = date('Y-m-d');
        $nowDateTime = date('Y-m-d H:i:s');

        // -------------------------------------
        // Fetch Total Admins and Total Employees
        // -------------------------------------
        $stmtUserCounts = $pdo->query("SELECT roleID, COUNT(*) as count FROM users GROUP BY roleID");
        $userRoleCounts = $stmtUserCounts->fetchAll(PDO::FETCH_KEY_PAIR); // roleID jako klíč, count jako hodnota

        $stats['total_admins'] = isset($userRoleCounts['admin']) ? (int)$userRoleCounts['admin'] : 0;
        $stats['total_employees'] = isset($userRoleCounts['employee']) ? (int)$userRoleCounts['employee'] : 0;

        // -------------------------------------
        // Determine Employees Currently Present and On Approved Absence
        // -------------------------------------
        $stats['employees_present'] = 0;
        $stats['employees_on_approved_absence'] = 0;

        if ($stats['total_employees'] > 0) {
            $stmtEmployeesDetails = $pdo->query("SELECT userID FROM users WHERE LOWER(roleID) = 'employee'");
            $employeesList = $stmtEmployeesDetails->fetchAll(PDO::FETCH_ASSOC);

            // Připravené dotazy
            $sqlLatestLog = "SELECT logType, logResult 
                             FROM attendance_logs
                             WHERE userID = :employeeID AND DATE(logTime) = :today_date
                             ORDER BY logTime DESC
                             LIMIT 1";
            $stmtLatestLog = $pdo->prepare($sqlLatestLog);

            $sqlActiveAbsence = "SELECT COUNT(*) 
                                 FROM absence
                                 WHERE userID = :employeeID 
                                   AND status = 'approved' 
                                   AND :now_datetime BETWEEN absence_start_datetime AND absence_end_datetime";
            $stmtActiveAbsence = $pdo->prepare($sqlActiveAbsence);

            foreach ($employeesList as $employee) {
                $employeeID = $employee['userID'];
                
                // 1. Zkontrolovat aktivní schválenou absenci pro aktuální čas
                $stmtActiveAbsence->execute([
                    ':employeeID' => $employeeID,
                    ':now_datetime' => $nowDateTime
                ]);
                $hasActiveApprovedAbsence = (int)$stmtActiveAbsence->fetchColumn() > 0;

                if ($hasActiveApprovedAbsence) {
                    $stats['employees_on_approved_absence']++;
                    // Pokud má aktivní schválenou absenci, není "present" z hlediska check-inu
                } else {
                    // 2. Pokud nemá aktivní schválenou absenci, zkontrolovat attendance_logs
                    $stmtLatestLog->execute([
                        ':employeeID' => $employeeID,
                        ':today_date' => $todayDate
                    ]);
                    $latestLog = $stmtLatestLog->fetch(PDO::FETCH_ASSOC);

                    if ($latestLog && $latestLog['logType'] == 'entry' && $latestLog['logResult'] == 'granted') {
                        $stats['employees_present']++;
                    }
                    // Pokud poslední log není entry/granted, nebo žádný log dnes, a nemá aktivní absenci,
                    // pak není ani "present" ani "on_approved_absence". Může být "absent" v obecném smyslu,
                    // ale pro účely těchto dvou statistik se nezapočítá.
                }
            }
        }

        // -------------------------------------
        // Fetch RFID Card Statistics
        // -------------------------------------
        $stmtRfidAssigned = $pdo->query("SELECT COUNT(*) FROM rfids WHERE userID IS NOT NULL"); 
        $stats['assigned_rfid_cards'] = $stmtRfidAssigned ? (int)$stmtRfidAssigned->fetchColumn() : 0;
        
        $stmtRfidUnassigned = $pdo->query("SELECT COUNT(*) FROM rfids WHERE userID IS NULL");
        $stats['unassigned_rfid_cards'] = $stmtRfidUnassigned ? (int)$stmtRfidUnassigned->fetchColumn() : 0;

        $stmtRfidActive = $pdo->query("SELECT COUNT(*) FROM rfids WHERE is_active = 1"); 
        $stats['active_rfid_cards'] = $stmtRfidActive ? (int)$stmtRfidActive->fetchColumn() : 0;
        
        // -------------------------------------
        // Fetch Pending Absence Requests (pouze budoucí nebo právě probíhající)
        // -------------------------------------
        // Correct way to prepare and execute with parameters
        $stmtPendingAbsences = $pdo->prepare("SELECT COUNT(*) FROM absence 
                                            WHERE status = 'pending_approval' 
                                            AND absence_end_datetime >= :now_date");
        if ($stmtPendingAbsences) {
            $stmtPendingAbsences->execute([':now_date' => $todayDate]);
            $stats['pending_absences'] = (int)$stmtPendingAbsences->fetchColumn();
        } else {
            $stats['pending_absences'] = 0;
        }


    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error: " . $e->getMessage();
        error_log("Admin Dashboard - PDOException: " . $e->getMessage());
    } catch (Exception $e) { 
        $dbErrorMessage = "An application error occurred: " . $e->getMessage();
        error_log("Admin Dashboard - Exception: " . $e->getMessage());
    }
} else {
    $dbErrorMessage = "Database connection is not available.";
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="./imgs/logo.png" type="image/x-icon"> 
    <title>Admin Dashboard - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --primary-color-rgb: 67, 97, 238;
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --warning-color: #FF9800; --danger-color: #F44336;
            --info-color: #2196F3; --system-color: #757575;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
            
            --present-color-val: 67, 170, 139; --absent-color-val: 214, 40, 40; 
            --info-color-val: 84, 160, 255; --neutral-color-val: 173, 181, 189; 
            --present-color: rgb(var(--present-color-val)); --absent-color: rgb(var(--absent-color-val)); 
            --neutral-color: rgb(var(--neutral-color-val));
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9;
            overflow-x: hidden; scroll-behavior: smooth; display: flex; flex-direction: column; min-height: 100vh;
        }
        main { flex-grow: 1; padding-top: 80px; }
        


        @media (max-width: 992px) { 
            .nav-links { display: none; } 
            .hamburger { display: flex; flex-direction: column; justify-content: space-between; }
        }

        /* Page Header */
        .page-header { padding: 1.8rem 0; margin-bottom: 1.8rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; color: var(--dark-color); margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        .db-error-message {background-color: rgba(var(--danger-color-val, 247, 37, 133),0.1); color: var(--danger-color); padding: 1rem; border-left: 4px solid var(--danger-color); margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}

        /* Admin Dashboard Specific Styles */
        .admin-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }

        .stat-card { 
            background-color: var(--white); padding: 1.5rem; border-radius: 8px; 
            box-shadow: var(--shadow); display: flex; align-items: center; gap: 1.2rem; 
            transition: var(--transition); border: 1px solid var(--light-gray); 
            position:relative; overflow:hidden;
        }
        .stat-card::before { content: ''; position:absolute; top:0; left:0; width:5px; height:100%; background-color:transparent; transition: var(--transition); }
        .stat-card:hover{transform: translateY(-4px); box-shadow: 0 6px 28px rgba(0,0,0,0.09); cursor: pointer;}
        .stat-card .icon { 
            font-size: 2rem; padding: 0.8rem; border-radius: 50%; 
            display:flex; align-items:center; justify-content:center; 
            width:55px; height:55px; flex-shrink:0;
        }
        .stat-card .info .value { font-size: 1.6rem; font-weight: 700; color: var(--dark-color); display:block; line-height:1.2; margin-bottom:0.2rem;}
        .stat-card .info .label { font-size: 0.9rem; color: var(--gray-color); }
        
        .stat-card.total-employees::before { background-color: var(--primary-color); }
        .stat-card.total-employees .icon { background-color: rgba(var(--primary-color-rgb),0.1); color: var(--primary-color); }
        
        .stat-card.present-employees::before { background-color: var(--present-color); }
        .stat-card.present-employees .icon { background-color: rgba(var(--present-color-val),0.1); color: var(--present-color); }
        
        .stat-card.absent-employees::before { background-color: var(--absent-color); }
        .stat-card.absent-employees .icon { background-color: rgba(var(--absent-color-val),0.1); color: var(--absent-color); }

        .stat-card.rfid-cards::before { background-color: var(--secondary-color); }
        .stat-card.rfid-cards .icon { background-color: rgba(63,55,201,0.1); color: var(--secondary-color); }
        
        .stat-card.pending-absences::before { background-color: var(--warning-color); }
        .stat-card.pending-absences .icon { background-color: rgba(255,152,0,0.1); color: var(--warning-color); }

        .admin-actions-panel {
            background-color: var(--white); padding: 1.5rem; border-radius: 8px;
            box-shadow: var(--shadow); margin-bottom: 2.5rem;
        }
        .admin-actions-panel h2 {
            font-size: 1.4rem; margin-bottom: 1.5rem; color: var(--dark-color);
            padding-bottom: 0.8rem; border-bottom: 1px solid var(--light-gray);
        }
        .action-links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        .action-link-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.2rem;
            background-color: var(--light-color);
            border-radius: 6px;
            text-decoration: none;
            color: var(--dark-color);
            transition: var(--transition);
            border: 1px solid var(--light-gray);
        }
        .action-link-card:hover {
            background-color: rgba(var(--primary-color-rgb), 0.07);
            border-color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
        }
        .action-link-card .icon {
            font-size: 1.8rem;
            color: var(--primary-color);
            background-color: rgba(var(--primary-color-rgb), 0.1);
            padding: 0.6rem;
            border-radius: 50%;
            width: 45px; height: 45px;
            display: flex; align-items: center; justify-content: center;
        }
        .action-link-card .text h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.2rem;
        }
        .action-link-card .text p {
            font-size: 0.85rem;
            color: var(--gray-color);
        }

        /* Footer styles */
        footer { background-color: var(--dark-color); color: var(--white); padding: 3rem 0 2rem; margin-top:auto; }
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 2rem; margin-bottom: 2rem; }
        .footer-column h3 { font-size: 1.2rem; margin-bottom: 1.2rem; position: relative; padding-bottom: 0.6rem; }
        .footer-column h3::after { content: ''; position: absolute; left: 0; bottom: 0; width: 40px; height: 2px; background-color: var(--primary-color); border-radius: 3px; }
        .footer-links { list-style: none; padding:0; } .footer-links li { margin-bottom: 0.6rem; }
        .footer-links a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); font-size: 0.9rem; display: inline-block; padding: 0.1rem 0; }
        .footer-links a:hover { color: var(--white); transform: translateX(4px); }
        .footer-links a i { margin-right: 0.4rem; width: 18px; text-align: center; } 
        .social-links { display: flex; gap: 1rem; margin-top: 1rem; padding:0; }
        .social-links a { display: inline-flex; align-items: center; justify-content: center; width: 35px; height: 35px; background-color: rgba(255, 255, 255, 0.1); color: var(--white); border-radius: 50%; font-size: 1rem; transition: var(--transition); }
        .social-links a:hover { background-color: var(--primary-color); transform: translateY(-2px); }
        .footer-bottom { text-align: center; padding-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.85rem; color: rgba(255, 255, 255, 0.6); }
        .footer-bottom a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); }
        .footer-bottom a:hover { color: var(--primary-color); }

    </style>
</head>
<body>
    <?php 
        // Dynamické načítání headeru na základě role
        $header_to_load = 'components/header-admin.php'; // Výchozí pro admina
        // Předpoklad: admin-dashboard.php je v rootu nebo ve složce 'admin'
        // Pokud je ve složce admin, cesta k 'components' je '../components/'
        if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) { // Jsme ve složce /admin/
            $header_to_load = '../components/header-admin.php';
        }
        
        if (file_exists($header_to_load)) {
            require_once $header_to_load;
        } else {
            echo "<!-- Header file not found at: " . htmlspecialchars($header_to_load) . " -->";
            // Fallback or error handling
        }
    ?> 
    <main>
        <div class="page-header">
            <div class="container">
                <h1>Admin Panel</h1>
                <p class="sub-heading">Welcome, <?php echo $sessionFirstName; ?>! Overview of the system.</p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if ($dbErrorMessage): ?>
                <div class="db-error-message" role="alert">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i> <?php echo $dbErrorMessage; ?>
                </div>
            <?php endif; ?>

            <section class="admin-stats-grid">
                <div class="stat-card total-employees">
                    <div class="icon"><span class="material-symbols-outlined">groups</span></div>
                    <div class="info">
                        <span class="value"><?php echo $stats['total_employees']; ?></span>
                        <span class="label">Total Employees</span>
                    </div>
                </div>
                 <div class="stat-card total-employees" style="--primary-color: var(--secondary-color); --primary-color-rgb: 63,55,201;">
                    <div class="icon"><span class="material-symbols-outlined">how_to_reg</span></div>
                        <div class="info">
                            <span class="value"><?php echo $stats['assigned_rfid_cards']; ?></span>
                            <span class="label">Assigned RFID Cards</span>
                        </div>
                </div>
                <div class="stat-card present-employees">
                    <div class="icon"><span class="material-symbols-outlined">person_check</span></div>
                    <div class="info">
                        <span class="value"><?php echo $stats['employees_present']; ?></span>
                        <span class="label">Employees Currently Present</span>
                    </div>
                </div>
                <!-- Stávající karta pro zaměstnance na schválené absenci -->
                <div class="stat-card absent-employees"> <!-- Můžete si ponechat třídu .absent-employees nebo ji změnit -->
                    <div class="icon"><span class="material-symbols-outlined">event_busy</span></div> <!-- Ikona pro plánovanou absenci -->
                    <div class="info">
                        <span class="value"><?php echo $stats['employees_on_approved_absence']; ?></span>
                        <span class="label">Employees on Absence</span>
                    </div>
                </div>
                <!-- ODSTRANĚNÝ BLOK PRO TOTAL RFID CARDS -->
                <div class="stat-card rfid-cards">
                <div class="icon"><span class="material-symbols-outlined">admin_panel_settings</span></div>
                    <div class="info">
                        <span class="value"><?php echo $stats['total_admins']; ?></span>
                        <span class="label">Total Admins</span>
                    </div>
                </div>
                 <div class="stat-card rfid-cards">
                    <div class="icon"><span class="material-symbols-outlined">unpublished</span></div>
                    <div class="info">
                        <span class="value"><?php echo $stats['unassigned_rfid_cards']; ?></span>
                        <span class="label">Unassigned RFID Cards</span>
                    </div>
                </div>
                 <div class="stat-card rfid-cards" style="--secondary-color: var(--present-color);">
                    <div class="icon"><span class="material-symbols-outlined">rss_feed</span></div>
                    <div class="info">
                        <span class="value"><?php echo $stats['active_rfid_cards']; ?></span>
                        <span class="label">Active RFID Cards</span>
                    </div>
                </div>
                <div class="stat-card pending-absences">
                    <div class="icon"><span class="material-symbols-outlined">event_busy</span></div>
                    <div class="info">
                        <span class="value"><?php echo $stats['pending_absences']; ?></span>
                        <span class="label">Pending Absence Requests</span>
                    </div>
                </div>
            </section>

            <section class="admin-actions-panel">
                <h2>Quick Actions & Management</h2>
                <div class="action-links-grid">
                <a href="admin-users-view.php" class="action-link-card">
                        <div class="icon"><span class="material-symbols-outlined">manage_accounts</span></div>
                        <div class="text">
                            <h3>All users views</h3>
                            <p>View all..</p>
                        </div>
                    </a>
                    <a href="admin-manage-users.php" class="action-link-card">
                        <div class="icon"><span class="material-symbols-outlined">manage_accounts</span></div>
                        <div class="text">
                            <h3>Manage Employees</h3>
                            <p>Add, edit, and view employee details.</p>
                        </div>
                    </a>
                    <a href="admin-manage-rfid.php" class="action-link-card">
                        <div class="icon"><span class="material-symbols-outlined">contactless</span></div>
                        <div class="text">
                            <h3>Manage RFID Cards</h3>
                            <p>Assign, activate, or deactivate RFID cards.</p>
                        </div>
                    </a>
                    <a href="admin-messages.php" class="action-link-card">
                        <div class="icon"><span class="material-symbols-outlined">chat</span></div>
                        <div class="text">
                            <h3>Manage Messages</h3>
                            <p>Send announcements and warnings to users.</p>
                        </div>
                    </a>
                     <a href="admin-manage-absence.php" class="action-link-card">
                        <div class="icon"><span class="material-symbols-outlined">rule</span></div>
                        <div class="text">
                            <h3>Approve Absences</h3>
                            <p>Review and approve/reject leave requests.</p>
                        </div>
                    </a>
                    <a href="admin-system-logs.php" class="action-link-card">
                        <div class="icon"><span class="material-symbols-outlined">receipt_long</span></div>
                        <div class="text">
                            <h3>System Logs</h3>
                            <p>View important system activity and audit trails.</p>
                        </div>
                    </a>
                    <a href="admin-settings.php" class="action-link-card">
                        <div class="icon"><span class="material-symbols-outlined">settings</span></div>
                        <div class="text">
                            <h3>System Settings</h3>
                            <p>Configure application-wide settings.</p>
                        </div>
                    </a>
                </div>
            </section>

        </div>
    </main>
    <?php 
        $footer_to_load = 'components/footer-admin.php';
        if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
            $footer_to_load = '../components/footer-admin.php';
        }
        if (file_exists($footer_to_load)) {
            require_once $footer_to_load;
        } else {
            echo "<!-- Footer file not found at: " . htmlspecialchars($footer_to_load) . " -->";
        }
    ?>

    <script>
        const hamburger = document.getElementById('hamburger'); 
        const mobileMenu = document.getElementById('mobileMenu'); 
        const closeMenuBtn = document.getElementById('closeMenu'); 
        const body = document.body;
        
        if (hamburger && mobileMenu) { 
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                mobileMenu.classList.toggle('active');
                hamburger.setAttribute('aria-expanded', mobileMenu.classList.contains('active'));
                mobileMenu.setAttribute('aria-hidden', !mobileMenu.classList.contains('active'));
                body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
            });
            
            if(closeMenuBtn) {
                closeMenuBtn.addEventListener('click', () => {
                    hamburger.classList.remove('active');
                    mobileMenu.classList.remove('active');
                    hamburger.setAttribute('aria-expanded', 'false');
                    mobileMenu.setAttribute('aria-hidden', 'true');
                    body.style.overflow = '';
                    if (hamburger) hamburger.focus();
                });
            }
            
            const mobileNavLinks = mobileMenu.querySelectorAll('a');
            mobileNavLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    if (mobileMenu.classList.contains('active')) {
                        hamburger.classList.remove('active');
                        mobileMenu.classList.remove('active');
                        hamburger.setAttribute('aria-expanded', 'false');
                        mobileMenu.setAttribute('aria-hidden', 'true');
                        body.style.overflow = '';
                    }
                });
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && mobileMenu.classList.contains('active')) {
                    if(closeMenuBtn) { closeMenuBtn.click(); } else { hamburger.click(); }
                }
            });
        }
        
        const headerEl = document.querySelector('header');
        if (headerEl) { 
            let initialHeaderShadow = getComputedStyle(headerEl).boxShadow;
            let scrollShadow = getComputedStyle(document.documentElement).getPropertyValue('--shadow').trim() || '0 4px 10px rgba(0,0,0,0.05)';

            window.addEventListener('scroll', () => {
                if (window.scrollY > 10) {
                    headerEl.style.boxShadow = scrollShadow; 
                } else {
                    headerEl.style.boxShadow = initialHeaderShadow; 
                }
            });
        }
    </script>
</body>
</html>