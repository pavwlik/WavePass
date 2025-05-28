<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true ||
    !isset($_SESSION["role"]) || strtolower($_SESSION["role"]) !== 'admin') {
    header("location: ../login.php");
    exit;
}

require_once '../db.php';

$sessionAdminFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$sessionAdminUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
$pathPrefix = "../";

$usersData = [];
$dbErrorMessage = null;
$todayDate = date('Y-m-d');

// Cesty pre profilové fotky
$profilePhotoBaseDir_server = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'profile_photos' . DIRECTORY_SEPARATOR;
$profilePhotoBaseDir_web = $pathPrefix . 'profile_photos/';
$defaultAvatar_web = $pathPrefix . 'imgs/default_avatar.jpg'; // Uistite sa, že tento obrázok existuje

$totalUsers = 0;
$usersPresentToday = 0;
$usersOnLeaveToday = 0;
$lateDepartureCount = 0;

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // 1. Fetch all users (vrátane profilovej fotky)
        $stmtUsers = $pdo->query("SELECT userID, firstName, lastName, email, username, roleID, profile_photo FROM users ORDER BY lastName, firstName");
        $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
        $totalUsers = count($users);

        // 2. Fetch all primary, active RFIDs
        $stmtRfids = $pdo->query("SELECT userID, name AS rfid_name, rfid_uid FROM rfids WHERE card_type = 'Primary Access Card' AND is_active = 1");
        $primaryRfids = [];
        foreach ($stmtRfids->fetchAll(PDO::FETCH_ASSOC) as $rfid) {
            $primaryRfids[$rfid['userID']] = $rfid;
        }

        // 3. Fetch latest attendance logs for today for all users
        $stmtLatestLogs = $pdo->prepare(
            "SELECT al.userID, al.logType, al.logResult, al.logTime, al.rfid_uid_used
             FROM attendance_logs al
             INNER JOIN (
                 SELECT userID, MAX(logTime) as max_logTime
                 FROM attendance_logs
                 WHERE DATE(logTime) = :today_date
                 GROUP BY userID
             ) latest_today ON al.userID = latest_today.userID AND al.logTime = latest_today.max_logTime"
        );
        $stmtLatestLogs->execute([':today_date' => $todayDate]);
        $latestLogs = [];
        foreach ($stmtLatestLogs->fetchAll(PDO::FETCH_ASSOC) as $log) {
            $latestLogs[$log['userID']] = $log;
        }

        // 4. Fetch all active approved absences for today
        $stmtAbsences = $pdo->prepare(
            "SELECT userID, absence_type
             FROM absence
             WHERE :today_date_check BETWEEN DATE(absence_start_datetime) AND DATE(absence_end_datetime)
               AND status = 'approved'"
        );
        $stmtAbsences->execute([':today_date_check' => $todayDate]);
        $approvedAbsences = [];
        foreach ($stmtAbsences->fetchAll(PDO::FETCH_ASSOC) as $absence) {
            $approvedAbsences[$absence['userID']] = $absence;
        }

        // 5. Fetch today's late departure notifications
        $stmtLateDepartures = $pdo->prepare(
            "SELECT userID, planned_departure_time, notes
             FROM late_departure_notifications
             WHERE notification_date = :today_date"
        );
        $stmtLateDepartures->execute([':today_date' => $todayDate]);
        $lateDepartureNotifications = [];
        foreach ($stmtLateDepartures->fetchAll(PDO::FETCH_ASSOC) as $notification) {
            $lateDepartureNotifications[$notification['userID']] = $notification;
        }
        $lateDepartureCount = count($lateDepartureNotifications);


        // 6. Combine data for each user
        foreach ($users as $user) {
            if ($user['userID'] == $sessionAdminUserId && $totalUsers > 1) { // Preskočíme admina, ak nie je jediný používateľ
                // $totalUsers--; // Ak ho preskakujeme, znížime celkový počet pre štatistiku
                // continue;
            }
            $userData = $user;

            // Profilová fotka
            $userPhotoSrc = $defaultAvatar_web;
            if (!empty($user['profile_photo']) && file_exists($profilePhotoBaseDir_server . $user['profile_photo'])) {
                $userPhotoSrc = $profilePhotoBaseDir_web . htmlspecialchars($user['profile_photo']);
            }
            $userData['profile_photo_url'] = $userPhotoSrc . '?' . time();


            $userData['rfid_name_assigned'] = $primaryRfids[$user['userID']]['rfid_name'] ?? 'N/A';
            $userData['rfid_uid_assigned'] = $primaryRfids[$user['userID']]['rfid_uid'] ?? 'N/A';

            $status = "Not Checked In";
            $statusClass = "neutral";
            $lastAction = "N/A";
            $lastActionTime = "--:--";
            $rfidUsedForLastAction = "N/A";

            if (isset($latestLogs[$user['userID']])) {
                $log = $latestLogs[$user['userID']];
                $lastActionTime = date("H:i", strtotime($log['logTime']));
                $rfidUsedForLastAction = $log['rfid_uid_used'] ?? 'N/A';

                if ($log['logResult'] == 'granted') {
                    if ($log['logType'] == 'entry') {
                        $status = "Present"; $statusClass = "present"; $lastAction = "Entry";
                        if (!isset($approvedAbsences[$user['userID']])) { // Počítame len ak nie sú zároveň na absencii
                            $usersPresentToday++;
                        }
                    } elseif ($log['logType'] == 'exit') {
                        $status = "Checked Out"; $statusClass = "checked-out"; $lastAction = "Exit";
                    }
                } elseif ($log['logResult'] == 'denied') {
                    $status = "Access Denied"; $statusClass = "danger";
                    $lastAction = ($log['logType'] == 'entry' ? "Entry Attempt" : "Exit Attempt");
                } else {
                    $status = "Status Unknown"; $statusClass = "neutral"; $lastAction = ucfirst($log['logType']);
                }
            } elseif (isset($approvedAbsences[$user['userID']])) {
                $absence = $approvedAbsences[$user['userID']];
                $absenceTypeDisplay = ucfirst(str_replace('_', ' ', $absence['absence_type']));
                $status = "On Leave (" . htmlspecialchars($absenceTypeDisplay) . ")";
                $statusClass = "info"; $lastAction = "N/A";
                $usersOnLeaveToday++;
            }

            $userData['current_status_display'] = $status;
            $userData['status_class_display'] = $statusClass;
            $userData['last_action_display'] = $lastAction;
            $userData['last_action_time_display'] = $lastActionTime;
            $userData['rfid_used_last_action'] = $rfidUsedForLastAction;

            $plannedDepartureOutput = "N/A";
            if (isset($lateDepartureNotifications[$user['userID']])) {
                $plannedDepartureTimeFormatted = date("H:i", strtotime($lateDepartureNotifications[$user['userID']]['planned_departure_time']));
                $plannedDepartureOutput = $plannedDepartureTimeFormatted;
                if (!empty($lateDepartureNotifications[$user['userID']]['notes'])) {
                    $plannedDepartureOutput .= " <i class='fas fa-sticky-note has-tooltip' title='" . htmlspecialchars($lateDepartureNotifications[$user['userID']]['notes']) . "'></i>";
                }
            }
            $userData['planned_departure_info'] = $plannedDepartureOutput;

            $usersData[] = $userData;
        }

    } catch (PDOException $e) {
        error_log("Admin Panel DB Error: " . $e->getMessage() . " --- SQL: " . ($e->getTrace()[0]['args'][0] ?? 'N/A'));
        $dbErrorMessage = "A database error occurred. Please check server logs.";
    } catch (Exception $e) {
        error_log("Admin Panel App Error: " . $e->getMessage());
        $dbErrorMessage = "An application error occurred.";
    }
} elseif (!isset($pdo)) {
    $dbErrorMessage = "Database connection is not available.";
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?php echo htmlspecialchars($pathPrefix); ?>imgs/logo.png" type="image/x-icon">
    <title>Admin Panel - User Overview - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4;
            --secondary-color: #3f37c9; /* Pre admin rolu - badge */
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #28a745; /* Zelená pre "Present" */
            --warning-color: #ffc107; /* Žltá pre "Checked Out" */
            --info-color: #17a2b8;   /* Modrozelená pre "On Leave" */
            --danger-color: #dc3545;  /* Červená pre "Access Denied" */
            --neutral-color: #6c757d; /* Šedá pre "Not Checked In" / "N/A" */
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.07);
            --transition: all 0.3s ease;

            --present-color-val: 40, 167, 69;
            --checked-out-color-val: 214, 40, 40;
            --info-color-val: 23, 162, 184;
            --neutral-color-val: 108, 117, 125;
            --danger-color-val: 220, 53, 69;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px; }
        header { /* Predpokladáme, že header má svoje štýly z header-admin.php */ }
        .container { max-width: 1500px; /* Mierne širší pre admin panel */ margin: 0 auto; padding: 0 20px; }

        .page-header { padding: 1.5rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.8rem; margin: 0; }
        .page-header .sub-heading { font-size: 0.95rem; color: var(--gray-color); margin-top: 0.25rem; }

        .db-error-message {background-color: rgba(var(--danger-color-val),0.1); color: var(--danger-color); padding: 1rem; border-left: 4px solid var(--danger-color); margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}

        .stats-overview-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.2rem; margin-bottom: 2rem; }
        .stat-item { background-color: var(--white); padding: 1.2rem 1.5rem; border-radius: 8px; box-shadow: var(--shadow); text-align: left; display: flex; flex-direction: column; justify-content: space-between; border-left: 5px solid var(--primary-color); }
        .stat-item .stat-label { font-size: 0.85rem; color: var(--gray-color); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
        .stat-item .stat-value { font-size: 2.2rem; font-weight: 600; color: var(--dark-color); display: block; line-height:1.1; }
        .stat-item.present-stat { border-left-color: var(--success-color); }
        .stat-item.present-stat .stat-value { color: var(--success-color); }
        .stat-item.on-leave-stat { border-left-color: var(--info-color); }
        .stat-item.on-leave-stat .stat-value { color: var(--info-color); }
        .stat-item.late-stat { border-left-color: var(--warning-color); }
        .stat-item.late-stat .stat-value { color: var(--warning-color); }


        .content-panel { background-color: var(--white); padding: 1.5rem 1.8rem; border-radius: 8px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); }
        .panel-title { font-size: 1.3rem; color: var(--dark-color); margin:0; }

        .users-table-wrapper { overflow-x: auto; }
        .users-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; } /* Zväčšený default font */
        .users-table th, .users-table td { padding: 0.9rem 1.1rem; text-align: left; border-bottom: 1px solid var(--light-gray); white-space: nowrap; vertical-align: middle; }
        .users-table th { background-color: #f9fafb; font-weight: 600; /* Tučnejšie hlavičky */ color: var(--gray-color); font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; }
        .users-table tbody tr:last-child td { border-bottom:none; }
        .users-table tbody tr:hover { background-color: #f0f4ff; }

        .users-table .profile-photo-cell { display: flex; align-items: center; gap: 12px; /* Väčšia medzera */ }
        .users-table .profile-photo { width: 36px; height: 36px; /* Väčšie fotky */ border-radius: 50%; object-fit: cover; border: 2px solid var(--light-gray); }
        .users-table .user-name-role .user-name { font-weight: 500; display:block; line-height:1.2; }
        .users-table .user-name-role .user-role-badge {
            font-size: 0.7rem;
            padding: 2px 6px; /* Menší padding pre badge */
            border-radius: 4px;
            color: var(--white);
            text-transform: uppercase; /* Všetko veľké pre badge */
            letter-spacing: 0.5px;
            display: inline-block;
            margin-top: 2px; /* Malý odstup od mena */
        }
        .users-table .user-role-badge.admin { background-color: var(--secondary-color); }
        .users-table .user-role-badge.employee { background-color: var(--info-color); }

        .status-badge { display: inline-block; padding: 0.35rem 0.9rem; /* Trochu väčší padding */ border-radius: 18px; /* Zaoblenejší */ font-size: 0.78rem; font-weight: 500; white-space: normal; line-height: 1.3; text-align: center; min-width: 110px; /* Konzistentná šírka */}
        .status-badge.present { background-color: rgba(var(--present-color-val), 0.15); color: rgb(var(--present-color-val)); }
        .status-badge.absent { background-color: rgba(var(--neutral-color-val),0.15); color: rgb(var(--neutral-color-val));} /* Šedá pre Not Checked In */
        .status-badge.checked-out { background-color: rgba(var(--checked-out-color-val), 0.15); color: rgb(var(--checked-out-color-val));}
        .status-badge.info { background-color: rgba(var(--info-color-val), 0.15); color: rgb(var(--info-color-val)); }
        .status-badge.danger { background-color: rgba(var(--danger-color-val),0.15); color: rgb(var(--danger-color-val));}

        .users-table td.rfid-uid-cell { font-family: 'Courier New', Courier, monospace; color: var(--gray-color); font-size: 0.85rem; }
        .users-table td.last-action-time { color: var(--gray-color); font-size: 0.85rem; }
        .planned-departure-cell { font-style: italic;}
        .planned-departure-cell .fa-sticky-note { margin-left: 5px; cursor: help; color: var(--gray-color); font-size: 0.9em; }
        .no-users-msg {text-align:center; padding: 2.5rem 1rem; color:var(--gray-color); font-size:0.95rem; background-color: #fdfdfd; border-radius: 4px; border: 1px dashed var(--light-gray);}
        footer { background-color: var(--dark-color); color: var(--white); padding: 2rem 0; margin-top: auto; text-align: center; }
        footer p { margin: 0; font-size: 0.9rem;}
        footer a { color: rgba(255,255,255,0.8); text-decoration:none;}
        footer a:hover { color:var(--white); }

    </style>
</head>
<body>
    <?php require_once "../components/header-admin.php"; ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>User Activity Overview</h1>
                <p class="sub-heading">Live status and daily summary for all users.</p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if (isset($dbErrorMessage) && $dbErrorMessage): ?>
                <div class="db-error-message" role="alert">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i> <?php echo htmlspecialchars($dbErrorMessage); ?>
                </div>
            <?php endif; ?>

            <section class="stats-overview-grid">
                <div class="stat-item">
                    <span class="stat-label">Total Users</span>
                    <span class="stat-value"><?php echo $totalUsers; ?></span>
                </div>
                <div class="stat-item present-stat">
                    <span class="stat-label">Present Today</span>
                    <span class="stat-value"><?php echo $usersPresentToday; ?></span>
                </div>
                <div class="stat-item on-leave-stat">
                    <span class="stat-label">On Leave Today</span>
                    <span class="stat-value"><?php echo $usersOnLeaveToday; ?></span>
                </div>
                <div class="stat-item late-stat">
                     <span class="stat-label">Late Exits Notified</span>
                    <span class="stat-value"><?php echo $lateDepartureCount; ?></span>
                </div>
            </section>

            <section class="content-panel">
                <div class="panel-header">
                    <h2 class="panel-title">User Status - <?php echo date("F d, Y"); ?></h2>
                    <!-- Filter/Search by user can be added here if needed -->
                </div>

                <div class="users-table-wrapper">
                    <table class="users-table" id="userStatusTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Current Status</th>
                                <th>Last Scan (Time)</th>
                                <th>Source (Card UID)</th>
                                <th>Planned Exit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($usersData)): ?>
                                <?php foreach ($usersData as $user): ?>
                                    <tr>
                                        <td class="profile-photo-cell">
                                            <img src="<?php echo htmlspecialchars($user['profile_photo_url']); ?>" alt="Photo" class="profile-photo">
                                            <div class="user-name-role">
                                                <span class="user-name"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></span>
                                                <span class="user-role-badge <?php echo htmlspecialchars(strtolower($user['roleID'])); ?>"><?php echo htmlspecialchars($user['roleID']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo htmlspecialchars($user['status_class_display']); ?>">
                                                <?php echo htmlspecialchars($user['current_status_display']); ?>
                                            </span>
                                        </td>
                                        <td class="last-action-time">
                                            <?php echo htmlspecialchars($user['last_action_display']); ?>
                                            <?php if ($user['last_action_time_display'] !== '--:--') echo ' at ' . htmlspecialchars($user['last_action_time_display']); ?>
                                        </td>
                                        <td class="rfid-uid-cell"><?php echo htmlspecialchars($user['rfid_used_last_action']); ?></td>
                                        <td class="planned-departure-cell">
                                            <?php echo $user['planned_departure_info']; // Obsahuje HTML pre ikonu ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php if (!$dbErrorMessage): ?>
                                <tr>
                                    <td colspan="6" class="no-users-msg">No users found in the system.</td>
                                </tr>
                                <?php endif; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <?php require_once "../components/footer-admin.php"; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // JavaScript pre túto stránku (napr. pre tooltipy, ak by ste ich chceli dynamickejšie)
        // Príklad pre jednoduché natívne tooltipy (ak title atribut nestačí)
        const tooltips = document.querySelectorAll('.has-tooltip');
        tooltips.forEach(tip => {
            // Tu by bola logika pre zobrazenie vlastného tooltipu, ak je to potrebné.
            // Pre jednoduchosť sa spoliehame na natívny title atribút.
        });
    });
</script>
</body>
</html>