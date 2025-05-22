<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Restrict Access: Ensure only admin can access
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    header("location: login.php"); 
    exit;
}

require_once 'db.php'; 

$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;

$dbErrorMessage = null;

// Initialize statistics variables
$stats = [
    'total_employees' => 0,
    'total_admins' => 0,
    'employees_present' => 0,
    'employees_absent' => 0,
    'total_rfid_cards' => 0,
    'assigned_rfid_cards' => 0,
    'unassigned_rfid_cards' => 0,
    'active_rfid_cards' => 0,
    'pending_absences' => 0,
    'recent_activity' => [] 
];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // Fetch User Stats
        $stmtUsers = $pdo->query("SELECT roleID, absence, COUNT(*) as count FROM users GROUP BY roleID, absence");
        $userCounts = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($userCounts as $row) {
            if ($row['roleID'] == 'employee') {
                $stats['total_employees'] += $row['count'];
                if ($row['absence'] == 0) {
                    $stats['employees_present'] += $row['count'];
                } else {
                    $stats['employees_absent'] += $row['count'];
                }
            } elseif ($row['roleID'] == 'admin') {
                $stats['total_admins'] += $row['count'];
            }
        }

        // Fetch RFID Card Stats 
        // CORRECTED TABLE NAME: Changed 'rfid_cards' to 'rfids'
        $stmtRfidTotal = $pdo->query("SELECT COUNT(*) FROM rfids"); // <<<< CHANGED HERE
        if ($stmtRfidTotal) $stats['total_rfid_cards'] = $stmtRfidTotal->fetchColumn(); else $stats['total_rfid_cards'] = 0;

        // CORRECTED TABLE NAME and COLUMN NAME (assuming assigned_userID is userID in rfids table)
        // Your screenshot shows a column named 'userID' in the 'rfids' table for the assigned user.
        $stmtRfidAssigned = $pdo->query("SELECT COUNT(*) FROM rfids WHERE userID IS NOT NULL"); // <<<< CHANGED HERE and assigned_userID to userID
        if ($stmtRfidAssigned) $stats['assigned_rfid_cards'] = $stmtRfidAssigned->fetchColumn(); else $stats['assigned_rfid_cards'] = 0;
        
        $stats['unassigned_rfid_cards'] = $stats['total_rfid_cards'] - $stats['assigned_rfid_cards'];

        // CORRECTED TABLE NAME and COLUMN NAME (assuming is_active column exists in rfids table)
        // Your screenshot shows a column 'is_active' in the 'rfids' table.
        $stmtRfidActive = $pdo->query("SELECT COUNT(*) FROM rfids WHERE is_active = 1"); // <<<< CHANGED HERE
        if ($stmtRfidActive) $stats['active_rfid_cards'] = $stmtRfidActive->fetchColumn(); else $stats['active_rfid_cards'] = 0;
        
        
        // Fetch Pending Absence Requests 
        $stmtPendingAbsences = $pdo->query("SELECT COUNT(*) FROM absence WHERE status = 'pending_approval' OR status = 'ceka_na_schvaleni'");
        if ($stmtPendingAbsences) { 
            $stats['pending_absences'] = $stmtPendingAbsences->fetchColumn();
        } else {
            $stats['pending_absences'] = 0; 
        }

    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error: " . $e->getMessage();
    } catch (Exception $e) {
        $dbErrorMessage = "An application error occurred: " . $e->getMessage();
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
    <link rel="icon" href="imgs/logo.png" type="image/x-icon"> 
    <title>Admin Dashboard - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reusing styles from your employee dashboard and messages page for consistency */
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
        main { flex-grow: 1; padding-top: 80px; /* Space for fixed header */ }
        
        .container, .page-header .container {
            max-width: 1440px; /* 1400px content + 20px padding each side */
            margin-left: auto; margin-right: auto;
            padding-left: 20px; padding-right: 20px;
        }

        /* Header Styles (Assume from header-employee-panel.php or a global CSS) */
        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 80px; }
        .nav-links { display: flex; list-style: none; align-items: center; /* ... */ }
        .hamburger { display: none; cursor: pointer; /* ... */ }
        .mobile-menu { position: fixed; /* ... full mobile menu styles ... */ transform: translateX(-100%); }
        .mobile-menu.active { transform: translateX(0); }
        @media (max-width: 992px) { .nav-links { display: none; } .hamburger { display: flex; } }

        /* Page Header */
        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
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
        .stat-card:hover{transform: translateY(-4px); box-shadow: 0 6px 28px rgba(0,0,0,0.09);}
        .stat-card .icon { 
            font-size: 2rem; padding: 0.8rem; border-radius: 50%; 
            display:flex; align-items:center; justify-content:center; 
            width:55px; height:55px; flex-shrink:0;
        }
        .stat-card .info .value { font-size: 1.6rem; font-weight: 700; color: var(--dark-color); display:block; line-height:1.2; margin-bottom:0.2rem;}
        .stat-card .info .label { font-size: 0.9rem; color: var(--gray-color); /*text-transform: uppercase; letter-spacing: 0.3px;*/ }
        
        /* Stat Card Colors (example palette) */
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

        /* Footer styles (ensure these are complete as per your index.php or global CSS) */
        footer { background-color: var(--dark-color); color: var(--white); padding: 5rem 0 2rem; margin-top:auto; }
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
                <h1>Admin Dashboard</h1>
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
                 <div class="stat-card total-employees" style="--primary-color: var(--secondary-color); --primary-color-rgb: 63,55,201;"> <!-- Inline style for quick color change, better in CSS -->
                    <div class="icon"><span class="material-symbols-outlined">admin_panel_settings</span></div>
                    <div class="info">
                        <span class="value"><?php echo $stats['total_admins']; ?></span>
                        <span class="label">Total Admins</span>
                    </div>
                </div>
                <div class="stat-card present-employees">
                    <div class="icon"><span class="material-symbols-outlined">person_check</span></div>
                    <div class="info">
                        <span class="value"><?php echo $stats['employees_present']; ?></span>
                        <span class="label">Employees Currently Present</span>
                    </div>
                </div>
                <div class="stat-card absent-employees">
                    <div class="icon"><span class="material-symbols-outlined">person_off</span></div>
                    <div class="info">
                        <span class="value"><?php echo $stats['employees_absent']; ?></span>
                        <span class="label">Employees Currently Absent</span>
                    </div>
                </div>
                <div class="stat-card rfid-cards">
                    <div class="icon"><span class="material-symbols-outlined">badge</span></div>
                    <div class="info">
                        <span class="value"><?php echo $stats['total_rfid_cards']; ?></span>
                        <span class="label">Total RFID Cards</span>
                    </div>
                </div>
                <div class="stat-card rfid-cards">
                    <div class="icon"><span class="material-symbols-outlined">how_to_reg</span></div>
                    <div class="info">
                        <span class="value"><?php echo $stats['assigned_rfid_cards']; ?></span>
                        <span class="label">Assigned RFID Cards</span>
                    </div>
                </div>
                 <div class="stat-card rfid-cards">
                    <div class="icon"><span class="material-symbols-outlined">unpublished</span></div>
                    <div class="info">
                        <span class="value"><?php echo $stats['unassigned_rfid_cards']; ?></span>
                        <span class="label">Unassigned RFID Cards</span>
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
                    <a href="admin/admin-manage-users.php" class="action-link-card">
                        <div class="icon"><span class="material-symbols-outlined">manage_accounts</span></div>
                        <div class="text">
                            <h3>Manage Employees</h3>
                            <p>Add, edit, and view employee details.</p>
                        </div>
                    </a>
                    <a href="admin/admin-manage-rfid.php" class="action-link-card">
                        <div class="icon"><span class="material-symbols-outlined">contactless</span></div>
                        <div class="text">
                            <h3>Manage RFID Cards</h3>
                            <p>Assign, activate, or deactivate RFID cards.</p>
                        </div>
                    </a>
                    <a href="messages.php" class="action-link-card"> <!-- Assuming admins use the same messages page -->
                        <div class="icon"><span class="material-symbols-outlined">chat</span></div>
                        <div class="text">
                            <h3>Manage Messages</h3>
                            <p>Send announcements and warnings to users.</p>
                        </div>
                    </a>
                     <a href="admin/admin-manage-absences.php" class="action-link-card">
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

            <!-- You could add a section for recent activity here if $stats['recent_activity'] is populated -->

        </div>
    </main>

    <?php require "components/footer-admin.php"; ?>

    <script>
        // Standard Mobile Menu Toggle (if not already in header-employee-panel.php)
        const hamburger = document.getElementById('hamburger'); // Assuming ID from header
        const mobileMenu = document.getElementById('mobileMenu'); // Assuming ID from header
        const body = document.body;

        if (hamburger && mobileMenu) {
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                mobileMenu.classList.toggle('active');
                body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
            });
            
            // If you have a close button inside the mobile menu:
            const closeMenuBtn = mobileMenu.querySelector('.close-btn'); // Or specific ID
            if (closeMenuBtn) {
                closeMenuBtn.addEventListener('click', () => {
                    hamburger.classList.remove('active');
                    mobileMenu.classList.remove('active');
                    body.style.overflow = '';
                });
            }
             mobileMenu.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    // Close menu if a link is clicked (optional, good for SPA-like feel or # links)
                    if (mobileMenu.classList.contains('active')) {
                        hamburger.classList.remove('active');
                        mobileMenu.classList.remove('active');
                        body.style.overflow = '';
                    }
                });
            });
        }

        // Header shadow on scroll (if not already in header-employee-panel.php)
        const headerEl = document.querySelector('header');
        if (headerEl) { 
            let initialHeaderShadow = getComputedStyle(headerEl).boxShadow;
            window.addEventListener('scroll', () => {
                let scrollShadow = getComputedStyle(document.documentElement).getPropertyValue('--shadow').trim() || '0 4px 10px rgba(0,0,0,0.05)';
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