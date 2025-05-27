<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] != 'admin') {
    $redirect_url = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true ? 'dashboard.php' : 'login.php';
    header("location: " . $redirect_url . "?error=unauthorized");
    exit;
}

require_once '../db.php'; // Nyní obsahuje $pdo a log_system_event()

$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
// $sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null; // Není přímo použit zde, ale je to OK

$dbErrorMessage = null;
$systemLogs = [];

// Hodnoty pro filtry musí odpovídat ENUM v DB tabulce system_logs.log_type
$currentFilter = isset($_GET['filter']) && !empty($_GET['filter']) ? $_GET['filter'] : 'all';

// Zobrazované názvy pro filtry
$filterDisplayNames = [
    'all' => 'All Events',
    'attendance_log' => 'Attendance',
    'user_management' => 'User Management',
    'card_management' => 'RFID Management',
    'late_departure_notification' => 'Late Departures', // Odpovídá DB ENUM
    'absence_request' => 'Absences',
    'security_event' => 'Security Events',         // Odpovídá DB ENUM
    'message_event' => 'Messages',                // Odpovídá DB ENUM
    'system_task' => 'System Tasks',            // Odpovídá DB ENUM
    'general_info' => 'General Info'              // Odpovídá DB ENUM
];

// Validace filtru: pokud není 'all' a není platný klíč v $filterDisplayNames, reset na 'all'
if ($currentFilter !== 'all' && !array_key_exists($currentFilter, $filterDisplayNames)) {
    error_log("Neznámý filtr historie v admin-system-logs.php: " . htmlspecialchars($currentFilter) . ". Resetuji na 'all'.");
    $currentFilter = 'all';
}


if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $params = [];
        $sqlBase = "SELECT 
                        sl.logID, sl.log_type, sl.action, sl.description,
                        sl.user_id_actor, sl.user_id_target, sl.item_id,
                        sl.old_value, sl.new_value, sl.ipAddress, sl.created_at,
                        actor.username AS actor_username, 
                        actor.firstName AS actor_firstName, 
                        actor.lastName AS actor_lastName,
                        target.username AS target_user_username,
                        target.firstName AS target_user_firstName,
                        target.lastName AS target_user_lastName
                    FROM system_logs sl
                    LEFT JOIN users actor ON sl.user_id_actor = actor.userID
                    LEFT JOIN users target ON sl.user_id_target = target.userID";

        $sqlWhereClauses = [];

        if ($currentFilter != 'all') {
            // $currentFilter je již validován výše
            $sqlWhereClauses[] = "sl.log_type = :log_type_filter";
            $params[':log_type_filter'] = $currentFilter;
        }
        
        // Možnost přidat vyhledávání
        // if (isset($_GET['search']) && !empty($_GET['search'])) {
        //     $searchTerm = '%' . $_GET['search'] . '%';
        //     $sqlWhereClauses[] = "(sl.description LIKE :search OR actor.username LIKE :search OR target.username LIKE :search)";
        //     $params[':search'] = $searchTerm;
        // }

        $sql = $sqlBase;
        if (!empty($sqlWhereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $sqlWhereClauses);
        }
        $sql .= " ORDER BY sl.created_at DESC LIMIT 200"; // Zvažte stránkování pro více než 200 záznamů

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params); 
        $systemLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error fetching system logs: " . $e->getMessage(); 
        error_log($dbErrorMessage);
    }
} else {
    $dbErrorMessage = "Database connection (PDO) is not available.";
    error_log($dbErrorMessage);
}

$currentPage = basename($_SERVER['PHP_SELF']); // Např. admin-system-logs.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="imgs/logo.png" type="image/x-icon">
    <title>System History - WavePass</title>
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

            /* Log type specific colors - sjednoceno s DB ENUM a filterDisplayNames */
            --log-color-attendance_log: #2196F3; 
            --log-color-user_management: #FF9800;      
            --log-color-card_management: #9C27B0;       
            --log-color-late_departure_notification: #009688; 
            --log-color-absence_request: #E91E63; 
            --log-color-security_event: var(--danger-color);
            --log-color-system_task: var(--system-color);
            --log-color-message_event: var(--info-color);
            --log-color-general_info: #4DB6AC; /* Nová barva pro general_info */
            --log-color-general: var(--gray-color); /* Fallback */
        }
        /* ... (zbytek CSS ponechán, je dlouhý a pro funkčnost není třeba měnit, ale zkontrolujte si barvy --log-color-* výše) ... */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh;}
        main { flex-grow: 1; padding-top: 80px; /* Account for fixed header */ }
        .container, .page-header .container { 
            max-width: 1440px; margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px; 
        }
        .history-container { display: flex; flex-direction: column; gap: 1.5rem; margin-top:1.5rem; }
        
        @media (min-width: 993px) {
            .history-container { flex-direction: row; }
        }
        
        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; margin: 0; } .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        
        .db-error-message { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem; background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }

        .history-sidebar {
            flex-basis: 100%;
            background-color: var(--white); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow);
            height: fit-content;
        }
        @media (min-width: 993px) {
            .history-sidebar { flex: 0 0 280px; }
        }
        .history-sidebar h3 { font-size: 1.2rem; margin-bottom: 1rem; color: var(--dark-color); padding-bottom: 0.5rem; border-bottom: 1px solid var(--light-gray); }
        .filter-list { list-style: none; padding: 0; }
        .filter-list li a {
            display: flex; align-items: center; gap: 0.7rem;
            padding: 0.8rem 1rem; text-decoration: none;
            color: var(--dark-color); border-radius: 6px;
            transition: var(--transition); font-weight: 500;
        }
        .filter-list li a:hover, .filter-list li a.active-filter {
            background-color: rgba(var(--primary-color-rgb), 0.1); 
            color: var(--primary-color);
        }
        .filter-list li a .material-symbols-outlined { font-size: 1.3em; }

        .history-content { flex-grow: 1; }
        .history-log-panel { background-color:var(--white); padding: 1.5rem; border-radius: 8px; box-shadow:var(--shadow); }
        .history-log-panel h2 { font-size: 1.4rem; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); color: var(--dark-color); }
        
        .log-list { list-style: none; padding: 0; }
        .log-item {
            padding: 1rem 0;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }
        .log-item:last-child { border-bottom: none; }
        .log-icon-container {
            flex-shrink: 0;
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--log-color-general); /* Fallback */
            color: var(--white);
        }
        .log-icon-container .material-symbols-outlined,
        .log-icon-container .fas {
            font-size: 1.4rem;
        }
        
        /* Specific icon colors based on log_type - sjednoceno s DB ENUM */
        .log-item[data-log-type="attendance_log"] .log-icon-container { background-color: var(--log-color-attendance_log); }
        .log-item[data-log-type="user_management"] .log-icon-container { background-color: var(--log-color-user_management); }
        .log-item[data-log-type="card_management"] .log-icon-container { background-color: var(--log-color-card_management); }
        .log-item[data-log-type="late_departure_notification"] .log-icon-container { background-color: var(--log-color-late_departure_notification); }
        .log-item[data-log-type="absence_request"] .log-icon-container { background-color: var(--log-color-absence_request); }
        .log-item[data-log-type="security_event"] .log-icon-container { background-color: var(--log-color-security_event); }
        .log-item[data-log-type="system_task"] .log-icon-container { background-color: var(--log-color-system_task); }
        .log-item[data-log-type="message_event"] .log-icon-container { background-color: var(--log-color-message_event); }
        .log-item[data-log-type="general_info"] .log-icon-container { background-color: var(--log-color-general_info); }


        .log-details { flex-grow: 1; }
        .log-action { font-weight: 600; font-size: 1rem; color: var(--dark-color); margin-bottom: 0.25rem; }
        .log-description { font-size: 0.9rem; color: var(--gray-color); margin-bottom: 0.35rem; word-break: break-word; }
        .log-meta { font-size: 0.8rem; color: #777; }
        .log-meta strong { color: #555; }
        .log-meta .actor, .log-meta .affected, .log-meta .affected-entity, .log-meta .ip-address, .log-meta .old-new-values { display: block; margin-bottom: 2px;}
        .log-meta .old-new-values small { display: block; }

        .no-logs { text-align: center; padding: 3rem 1rem; background-color: var(--white); border-radius: 8px; box-shadow: var(--shadow); color: var(--gray-color); font-size: 1.1rem; }
        .no-logs .material-symbols-outlined { font-size: 3rem; display: block; margin-bottom: 1rem; color: var(--primary-color); }

        /* Footer styles */
        footer { background-color: var(--dark-color); color: var(--white); padding: 3rem 0 2rem; margin-top:auto; }
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 2rem; margin-bottom: 2rem; max-width: 1200px; margin-left:auto; margin-right:auto; padding:0 20px; }
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
        $headerPath = "../components/header-admin.php";
        if (file_exists($headerPath)) {
            require_once $headerPath;
        } else {
            echo "<!-- Admin header file not found -->";
        }
    ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>System Event History</h1>
                <p class="sub-heading">Overview of recent system activities and changes.</p>
            </div>
        </div>

        <div class="container history-container">
            <aside class="history-sidebar">
                <h3>Filter History</h3>
                <ul class="filter-list">
                    <!-- Odkazy filtrů nyní používají hodnoty odpovídající DB ENUM a $currentPage pro dynamický název souboru -->
                    <li><a href="<?php echo $currentPage; ?>?filter=all" class="<?php if ($currentFilter == 'all') echo 'active-filter'; ?>"><span class="material-symbols-outlined">history</span> All Events</a></li>
                    <li><a href="<?php echo $currentPage; ?>?filter=attendance_log" class="<?php if ($currentFilter == 'attendance_log') echo 'active-filter'; ?>"><span class="material-symbols-outlined">person_check</span> Attendance</a></li>
                    <li><a href="<?php echo $currentPage; ?>?filter=user_management" class="<?php if ($currentFilter == 'user_management') echo 'active-filter'; ?>"><span class="material-symbols-outlined">manage_accounts</span> User Management</a></li>
                    <li><a href="<?php echo $currentPage; ?>?filter=card_management" class="<?php if ($currentFilter == 'card_management') echo 'active-filter'; ?>"><span class="material-symbols-outlined">credit_card</span> RFID Management</a></li>
                    <li><a href="<?php echo $currentPage; ?>?filter=late_departure_notification" class="<?php if ($currentFilter == 'late_departure_notification') echo 'active-filter'; ?>"><span class="material-symbols-outlined">schedule</span> Late Departures</a></li>
                    <li><a href="<?php echo $currentPage; ?>?filter=absence_request" class="<?php if ($currentFilter == 'absence_request') echo 'active-filter'; ?>"> <span class="material-symbols-outlined">event_busy</span> Absences</a></li>
                    <li><a href="<?php echo $currentPage; ?>?filter=security_event" class="<?php if ($currentFilter == 'security_event') echo 'active-filter'; ?>"><span class="material-symbols-outlined">security</span> Security Events</a></li>
                    <li><a href="<?php echo $currentPage; ?>?filter=message_event" class="<?php if ($currentFilter == 'message_event') echo 'active-filter'; ?>"><span class="material-symbols-outlined">chat</span> Messages</a></li>
                    <li><a href="<?php echo $currentPage; ?>?filter=system_task" class="<?php if ($currentFilter == 'system_task') echo 'active-filter'; ?>"><span class="material-symbols-outlined">settings_system_daydream</span> System Tasks</a></li>
                    <li><a href="<?php echo $currentPage; ?>?filter=general_info" class="<?php if ($currentFilter == 'general_info') echo 'active-filter'; ?>"><span class="material-symbols-outlined">info</span> General Info</a></li>
                </ul>
            </aside>

            <div class="history-content">
                <?php if ($dbErrorMessage): ?>
                    <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbErrorMessage); ?></div>
                <?php endif; ?>

                <section class="history-log-panel">
                     <h2>Activity Feed <span style="color: var(--primary-color); font-weight:500;">(<?php echo htmlspecialchars($filterDisplayNames[$currentFilter] ?? ucfirst(str_replace('_', ' ', $currentFilter))); ?>)</span></h2>
                    <?php if (!empty($systemLogs)): ?>
                        <ul class="log-list">
                            <?php foreach ($systemLogs as $log): 
                                $iconHtml = '<span class="material-symbols-outlined">help_outline</span>'; // Default
                                $logType = $log['log_type'] ?? 'general_info'; // Default na general_info pokud chybí
                                
                                // Výběr ikony na základě log_type - sjednoceno s DB ENUM
                                switch ($logType) {
                                    case 'attendance_log': $iconHtml = '<span class="material-symbols-outlined">person_check</span>'; break;
                                    case 'user_management': $iconHtml = '<span class="material-symbols-outlined">manage_accounts</span>'; break;
                                    case 'card_management': $iconHtml = '<span class="material-symbols-outlined">nfc</span>'; break; // NFC je lepší pro RFID
                                    case 'late_departure_notification': $iconHtml = '<span class="material-symbols-outlined">schedule</span>'; break;
                                    case 'absence_request': $iconHtml = '<span class="material-symbols-outlined">event_busy</span>'; break;
                                    case 'security_event': $iconHtml = '<span class="material-symbols-outlined">security</span>'; break;
                                    case 'message_event': $iconHtml = '<span class="material-symbols-outlined">chat</span>'; break;
                                    case 'system_task': $iconHtml = '<span class="material-symbols-outlined">settings_system_daydream</span>'; break;
                                    case 'general_info': $iconHtml = '<span class="material-symbols-outlined">info</span>'; break;
                                }
                            ?>
                                <li class="log-item" data-log-type="<?php echo htmlspecialchars($logType); ?>">
                                    <div class="log-icon-container">
                                        <?php echo $iconHtml; ?>
                                    </div>
                                    <div class="log-details">
                                        <p class="log-action"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['action'] ?? 'N/A'))); ?></p>
                                        
                                        <p class="log-description"><?php echo nl2br(htmlspecialchars($log['description'] ?? 'No details provided.')); ?></p>
                                        
                                        <?php if (!empty($log['old_value']) || !empty($log['new_value'])): ?>
                                        <div class="log-meta old-new-values">
                                            <?php if (!empty($log['old_value'])): ?>
                                                <small><strong>Old:</strong> <?php echo htmlspecialchars($log['old_value']); ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($log['new_value'])): ?>
                                                <small><strong>New:</strong> <?php echo htmlspecialchars($log['new_value']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>

                                        <p class="log-meta">
                                            <?php if (!empty($log['actor_firstName'])): ?>
                                                <span class="actor"><strong>Action by:</strong> <?php echo htmlspecialchars($log['actor_firstName'] . ' ' . $log['actor_lastName']); ?> (<?php echo htmlspecialchars($log['actor_username'] ?? 'N/A'); ?>)</span>
                                            <?php elseif(!empty($log['user_id_actor'])): ?>
                                                 <span class="actor"><strong>Action by UserID:</strong> <?php echo htmlspecialchars($log['user_id_actor']); ?></span>
                                            <?php else: ?>
                                                 <span class="actor"><strong>Action by:</strong> System</span>
                                            <?php endif; ?>

                                            <?php if (!empty($log['target_user_firstName'])): ?>
                                                <span class="affected"><strong>Affected User:</strong> <?php echo htmlspecialchars($log['target_user_firstName'] . ' ' . $log['target_user_lastName']); ?> (<?php echo htmlspecialchars($log['target_user_username'] ?? 'N/A'); ?>)</span>
                                            <?php elseif(!empty($log['user_id_target'])): ?>
                                                <span class="affected"><strong>Affected UserID:</strong> <?php echo htmlspecialchars($log['user_id_target']); ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($log['item_id'])): ?>
                                                <span class="affected-entity"><strong>Item ID:</strong> <?php echo htmlspecialchars($log['item_id']); ?> (Context: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['log_type'])));?>)</span>
                                            <?php endif; ?>

                                            <span class="log-timestamp"><strong>On:</strong> <?php echo date("M d, Y - H:i:s", strtotime($log['created_at'])); ?></span>
                                            <?php if (!empty($log['ipAddress'])): ?>
                                                | <span class="ip-address"><strong>IP:</strong> <?php echo htmlspecialchars($log['ipAddress']); ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <?php if (!$dbErrorMessage): // Zobrazit "No logs" jen pokud není chyba DB ?>
                        <div class="no-logs">
                            <span class="material-symbols-outlined">manage_history</span>
                            No system events found for the selected filter or no events have been logged yet.
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>

    <?php require_once $pathPrefix . "components/footer-admin.php"; ?>
    <script>
        // Váš stávající JavaScript pro mobilní menu
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.getElementById('hamburger'); 
            const mobileMenu = document.getElementById('mobileMenu'); 
            const body = document.body;

            if (hamburger && mobileMenu) {
                const closeMenuBtnInMobile = mobileMenu.querySelector('.close-btn'); 

                hamburger.addEventListener('click', () => {
                    hamburger.classList.toggle('active');
                    mobileMenu.classList.toggle('active');
                    body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
                });

                if(closeMenuBtnInMobile){
                    closeMenuBtnInMobile.addEventListener('click', () => {
                        hamburger.classList.remove('active');
                        mobileMenu.classList.remove('active');
                        body.style.overflow = '';
                    });
                }
                
                mobileMenu.querySelectorAll('ul.mobile-links a').forEach(link => { 
                    link.addEventListener('click', (e) => {
                         if (mobileMenu.classList.contains('active')) {
                            hamburger.classList.remove('active');
                            mobileMenu.classList.remove('active');
                            body.style.overflow = '';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>