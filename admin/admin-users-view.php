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

$profilePhotoBaseDir_server = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'profile_photos' . DIRECTORY_SEPARATOR;
$profilePhotoBaseDir_web = $pathPrefix . 'profile_photos/';
$defaultAvatar_web = $pathPrefix . 'imgs/default_avatar.jpg';

$adminProfilePhoto_web = $defaultAvatar_web;
if ($sessionAdminUserId && isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmtAdminPhoto = $pdo->prepare("SELECT profile_photo FROM users WHERE userID = :admin_id");
        $stmtAdminPhoto->execute([':admin_id' => $sessionAdminUserId]);
        $adminPhotoFile = $stmtAdminPhoto->fetchColumn();
        if (!empty($adminPhotoFile) && file_exists($profilePhotoBaseDir_server . $adminPhotoFile)) {
            $adminProfilePhoto_web = $profilePhotoBaseDir_web . htmlspecialchars($adminPhotoFile) . '?' . time();
        }
    } catch (PDOException $e) {
        error_log("Error fetching admin profile photo for header: " . $e->getMessage());
    }
}

$filterName = isset($_GET['name']) ? trim($_GET['name']) : '';
$filterRole = isset($_GET['role']) ? trim($_GET['role']) : '';
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterLateDeparture = isset($_GET['late_departure']) ? trim($_GET['late_departure']) : '';

$filtersExpanded = !empty($filterName) || !empty($filterRole) || !empty($filterStatus) || !empty($filterLateDeparture);

$totalUsers = 0;
$usersPresentToday = 0; // For display in stat card
$usersOnLeaveToday = 0; // For display in stat card
$lateDepartureCount = 0;

$activeQuery = null;
$paramsUsers = [];

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $sqlUsers = "SELECT u.userID, u.firstName, u.lastName, u.username, u.roleID, u.profile_photo FROM users u WHERE 1=1";

        if (!empty($filterName)) {
            $sqlUsers .= " AND (u.firstName LIKE :name_fn OR u.lastName LIKE :name_ln OR u.username LIKE :name_un)";
            $paramsUsers[':name_fn'] = "%" . $filterName . "%";
            $paramsUsers[':name_ln'] = "%" . $filterName . "%";
            $paramsUsers[':name_un'] = "%" . $filterName . "%";
        }
        if (!empty($filterRole)) {
            $sqlUsers .= " AND LOWER(u.roleID) = :role";
            $paramsUsers[':role'] = strtolower($filterRole);
        }
        if (!empty($filterLateDeparture)) {
            if ($filterLateDeparture === 'yes') {
                $sqlUsers .= " AND EXISTS (SELECT 1 FROM late_departure_notifications ldn WHERE ldn.userID = u.userID AND ldn.notification_date = :today_date_for_late_filter)";
                $paramsUsers[':today_date_for_late_filter'] = $todayDate;
            } elseif ($filterLateDeparture === 'no') {
                $sqlUsers .= " AND NOT EXISTS (SELECT 1 FROM late_departure_notifications ldn WHERE ldn.userID = u.userID AND ldn.notification_date = :today_date_for_late_filter)";
                $paramsUsers[':today_date_for_late_filter'] = $todayDate;
            }
        }
        $sqlUsers .= " ORDER BY u.lastName, u.firstName";
        $activeQuery = $sqlUsers;
        
        $stmtUsers = $pdo->prepare($sqlUsers);
        $stmtUsers->execute($paramsUsers);
        $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
        
        // Total users overall (not just matching filter for this stat)
        $stmtTotalAllUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(roleID) = 'employee'"); // Count only employees for this stat typically
        $totalUsers = $stmtTotalAllUsers->fetchColumn();
        // If you want total of ALL users (admin + employee) then:
        // $stmtTotalAllUsers = $pdo->query("SELECT COUNT(*) FROM users");
        // $totalUsers = $stmtTotalAllUsers->fetchColumn();


        $stmtLateDepartureCountAll = $pdo->prepare("SELECT COUNT(DISTINCT userID) FROM late_departure_notifications WHERE notification_date = :today_date_stat");
        $stmtLateDepartureCountAll->execute([':today_date_stat' => $todayDate]);
        $lateDepartureCount = $stmtLateDepartureCountAll->fetchColumn();

        $stmtAbsences = $pdo->prepare(
            "SELECT userID, absence_type
             FROM absence
             WHERE :today_date_absences BETWEEN DATE(absence_start_datetime) AND DATE(absence_end_datetime)
               AND status = 'approved'"
        );
        $stmtAbsences->execute([':today_date_absences' => $todayDate]);
        $approvedAbsences = [];
        foreach ($stmtAbsences->fetchAll(PDO::FETCH_ASSOC) as $absence) {
            $approvedAbsences[$absence['userID']] = $absence;
        }

        $stmtLatestLogs = $pdo->prepare(
            "SELECT al.userID, al.logType, al.logResult, al.logTime, al.rfid_uid_used
             FROM attendance_logs al
             INNER JOIN (
                 SELECT userID, MAX(logTime) as max_logTime
                 FROM attendance_logs
                 WHERE DATE(logTime) = :today_date_logs
                 GROUP BY userID
             ) latest_today ON al.userID = latest_today.userID AND al.logTime = latest_today.max_logTime"
        );
        $stmtLatestLogs->execute([':today_date_logs' => $todayDate]);
        $latestLogs = [];
        foreach ($stmtLatestLogs->fetchAll(PDO::FETCH_ASSOC) as $log) {
            $latestLogs[$log['userID']] = $log;
        }

        $stmtLateDeparturesInfo = $pdo->prepare(
            "SELECT userID, planned_departure_time, notes
             FROM late_departure_notifications
             WHERE notification_date = :today_date_late_info"
        );
        $stmtLateDeparturesInfo->execute([':today_date_late_info' => $todayDate]);
        $lateDepartureNotifications = [];
        foreach ($stmtLateDeparturesInfo->fetchAll(PDO::FETCH_ASSOC) as $notification) {
            $lateDepartureNotifications[$notification['userID']] = $notification;
        }
        
        $tempUsersData = [];
        $currentUsersPresent_forStatCard = 0; // Specific for stat card counting (employees only)
        $currentUsersOnLeave_forStatCard = 0; // Specific for stat card counting (employees only)

        foreach ($users as $user) {
            $userData = $user;
            $userPhotoSrc = $defaultAvatar_web;
            if (!empty($user['profile_photo']) && file_exists($profilePhotoBaseDir_server . $user['profile_photo'])) {
                $userPhotoSrc = $profilePhotoBaseDir_web . htmlspecialchars($user['profile_photo']);
            }
            $userData['profile_photo_url'] = $userPhotoSrc . '?' . time();

            $status = "Not Checked In"; $statusClass = "neutral";
            $lastAction = "N/A"; $lastActionTime = "--:--"; $rfidUsedForLastAction = "N/A";
            $isCurrentlyPresent = false; $isCurrentlyOnLeave = false; $isCurrentlyCheckedOut = false;
            $isEmployee = (strtolower($user['roleID']) == 'employee');

            if (isset($approvedAbsences[$user['userID']])) {
                $absenceItem = $approvedAbsences[$user['userID']];
                $absenceTypeDisplay = ucfirst(str_replace('_', ' ', $absenceItem['absence_type']));
                $status = "On Leave";
                $statusClass = "on-leave";
                $lastAction = htmlspecialchars($absenceTypeDisplay);
                if ($isEmployee) $currentUsersOnLeave_forStatCard++;
                $isCurrentlyOnLeave = true;
            }

            if (isset($latestLogs[$user['userID']])) {
                $log = $latestLogs[$user['userID']];
                $currentLastActionTime = date("H:i", strtotime($log['logTime']));
                $currentRfidUsedForLastAction = $log['rfid_uid_used'] ?? 'N/A';

                if ($log['logResult'] == 'granted') {
                    if ($log['logType'] == 'entry') {
                        if (!$isCurrentlyOnLeave) {
                            $status = "Present"; $statusClass = "present";
                            if ($isEmployee) $currentUsersPresent_forStatCard++;
                            $isCurrentlyPresent = true;
                        }
                        $lastAction = "Entry";
                        $lastActionTime = $currentLastActionTime;
                        $rfidUsedForLastAction = $currentRfidUsedForLastAction;
                    } elseif ($log['logType'] == 'exit') {
                        if (!$isCurrentlyOnLeave) {
                           $status = "Checked Out"; $statusClass = "checked-out";
                           $isCurrentlyCheckedOut = true;
                        }
                        $lastAction = "Exit";
                        $lastActionTime = $currentLastActionTime;
                        $rfidUsedForLastAction = $currentRfidUsedForLastAction;
                    }
                } elseif ($log['logResult'] == 'denied') {
                     if (!$isCurrentlyOnLeave) {
                        $status = "Access Denied"; $statusClass = "danger";
                     }
                    $lastAction = ($log['logType'] == 'entry' ? "Entry Attempt" : "Exit Attempt");
                    $lastActionTime = $currentLastActionTime;
                    $rfidUsedForLastAction = $currentRfidUsedForLastAction;
                } else {
                     if (!$isCurrentlyOnLeave) {
                        $status = "Status Unknown"; $statusClass = "neutral";
                     }
                    $lastAction = ucfirst($log['logType']);
                    $lastActionTime = $currentLastActionTime;
                    $rfidUsedForLastAction = $currentRfidUsedForLastAction;
                }
            }
            
            if (!empty($filterStatus)) {
                if ($filterStatus === 'present' && !$isCurrentlyPresent) continue;
                if ($filterStatus === 'on_leave' && !$isCurrentlyOnLeave) continue;
                if ($filterStatus === 'checked_out' && !$isCurrentlyCheckedOut) continue;
                if ($filterStatus === 'not_checked_in' && ($isCurrentlyPresent || $isCurrentlyOnLeave || $isCurrentlyCheckedOut)) continue;
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
                    $noteTitle = htmlspecialchars($lateDepartureNotifications[$user['userID']]['notes']);
                    $plannedDepartureOutput .= " <span class='tooltip-trigger' tabindex='0'><i class='fas fa-sticky-note'></i><span class='tooltip-content'>{$noteTitle}</span></span>";
                }
            }
            $userData['planned_departure_info'] = $plannedDepartureOutput;
            $tempUsersData[] = $userData;
        }
        $usersData = $tempUsersData;
        // Assign calculated counts for stat cards
        $usersPresentToday = $currentUsersPresent_forStatCard;
        $usersOnLeaveToday = $currentUsersOnLeave_forStatCard; // CORRECTED ASSIGNMENT

    } catch (PDOException $e) {
        error_log("Admin Users View DB Error: " . $e->getMessage() . " --- SQL: " . ($activeQuery ?? 'Could not get active query') . " --- Params: " . json_encode($paramsUsers));
        $dbErrorMessage = "A database error occurred. Please check server logs for more details. Ensure your database user has correct permissions and the database schema matches the queries.";
    } catch (Exception $e) {
        error_log("Admin Users View App Error: " . $e->getMessage());
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
    <title>User Activity Overview - Admin - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="<?php echo htmlspecialchars($pathPrefix); ?>css/header-styles.css"> 
    <link rel="stylesheet" href="<?php echo htmlspecialchars($pathPrefix); ?>css/main-styles.css">
    <style>
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4;
            --primary-color-rgb: 67, 97, 238;
            --secondary-color: #3f37c9; 
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); 
            --transition: all 0.3s ease;

            --present-color-val: 40, 167, 69;
            --checked-out-color-val: 108, 117, 125; 
            --on-leave-color-val: 23, 162, 184;   
            --danger-color-val: 220, 53, 69;
            --neutral-color-val: 173, 181, 189; 

            --stat-primary-color-val: var(--primary-color-rgb); 
            --stat-success-color-val: 67, 170, 139; 
            --stat-info-color-val: 23, 162, 184;    
            --late-departure-icon-color-val: 240, 173, 78; /* Example: Warning Yellow/Orange */
            
            --stat-total-users-color: var(--primary-color);
            --stat-present-color: rgb(var(--stat-success-color-val));
            --stat-on-leave-color: rgb(var(--stat-info-color-val));
            --stat-late-exit-color: rgb(var(--late-departure-icon-color-val)); 

            --employee-badge-bg: #e0f3ff;
            --employee-badge-text: #007bff;
            --admin-badge-bg: #e9d8fd;
            --admin-badge-text: #6f42c1;
        }
        body {font-family: 'Inter', sans-serif; padding-top: 80px; background-color: #f4f7fc; } 
        main { padding-bottom: 3rem; }
        
        .page-header h1 { font-size: 2rem; font-weight: 700; }
        .page-header .sub-heading { font-size: 1rem; color: var(--gray-color); }
        .db-error-message {background-color: rgba(var(--danger-color-val),0.1); color: rgb(var(--danger-color-val)); padding: 1rem; border-left: 4px solid rgb(var(--danger-color-val)); margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}

        .admin-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        .stat-card { background-color: var(--white); padding: 1.2rem 1.5rem; border-radius: 8px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1.2rem; transition: var(--transition); border: 1px solid var(--light-gray); position:relative; overflow:hidden;}
        .stat-card::before { content: ''; position:absolute; top:0; left:0; width:5px; height:100%; background-color:transparent; transition: var(--transition); }
        .stat-card:hover{transform: translateY(-4px); box-shadow: 0 6px 28px rgba(0,0,0,0.09);}
        .stat-card .icon { font-size: 2.2rem; padding: 0.8rem; border-radius: 50%; display:flex; align-items:center; justify-content:center; width:55px; height:55px; flex-shrink:0; }
        .stat-card .info .value { font-size: 1.8rem; font-weight: 500; color: var(--dark-color); display:block; line-height:1.1; margin-bottom:0.3rem;}
        .stat-card .info .label { font-size: 0.8rem; color: var(--gray-color); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card.total-users::before { background-color: var(--stat-total-users-color); }
        .stat-card.total-users .icon { background-color: rgba(var(--stat-primary-color-val),0.1); color: var(--stat-total-users-color); }
        .stat-card.present-stat::before { background-color: var(--stat-present-color); }
        .stat-card.present-stat .icon { background-color: rgba(var(--stat-success-color-val),0.1); color: var(--stat-present-color); }
        .stat-card.on-leave-stat::before { background-color: var(--stat-on-leave-color); }
        .stat-card.on-leave-stat .icon { background-color: rgba(var(--stat-info-color-val),0.1); color: var(--stat-on-leave-color); }
        .stat-card.late-stat::before { background-color: var(--stat-late-exit-color); }
        .stat-card.late-stat .icon { background-color: rgba(var(--late-departure-icon-color-val),0.1); color: var(--stat-late-exit-color); }

        .users-list-panel { background-color: var(--white); border-radius: 8px; box-shadow: var(--shadow); padding: 0; margin-bottom: 2rem; border: 1px solid var(--light-gray); }
        .users-list-panel .panel-header { padding: 1.2rem 1.5rem; margin-bottom:0; display: flex; justify-content: space-between; align-items: center;}
        .users-list-panel .panel-header .panel-title { font-size: 1.3rem; color: var(--dark-color); margin:0; }
        
        .filters-toggle-section { padding: 0.8rem 1.5rem; border-top: 1px solid var(--light-gray); border-bottom: 1px solid var(--light-gray); display: flex; justify-content: space-between; align-items: center; cursor: pointer; background-color: #fdfdff; }
        .filters-toggle-section h3 { font-size: 1.05rem; color: var(--dark-color); margin: 0; font-weight: 600; }
        .filters-toggle-section .toggle-icon { font-size: 1.5rem; color: var(--gray-color); transition: transform 0.3s ease; }
        .filters-toggle-section.expanded .toggle-icon { transform: rotate(180deg); }
        
        .filters-body { padding: 1.5rem; display: none; flex-wrap: wrap; gap: 1.2rem 1.5rem; align-items: flex-end; border-bottom: 1px solid var(--light-gray); background-color: #fdfdff; }
        .filters-body.expanded { display: flex; } 

        .filter-group { display: flex; flex-direction: column; min-width: 180px; flex-grow:1;}
        .filter-group label { font-size: 0.8rem; color: var(--gray-color); margin-bottom: 0.4rem; font-weight: 500; }
        .filter-group input[type="text"], .filter-group select { padding: 0.7rem 0.9rem; border: 1px solid #ced4da; border-radius: 6px; font-size: 0.9rem; background-color: var(--white); }
        .filter-group input[type="text"]:focus, .filter-group select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.2rem rgba(var(--primary-color-rgb), 0.25); outline: 0; }
        .filter-buttons-group { display: flex; gap: 0.8rem; align-items: flex-end; flex-grow: 0.3; margin-top: 1.2rem; } 
        .filter-buttons-group button, .filter-buttons-group a.button { padding: 0.7rem 1.3rem; color: var(--white); border: none; border-radius: 6px; cursor: pointer; font-weight: 500; transition: background-color 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.9rem; }
        .filter-buttons-group button { background-color: var(--primary-color); }
        .filter-buttons-group button:hover { background-color: var(--primary-dark); }
        .filter-buttons-group a.button.clear { background-color: var(--gray-color); }
        .filter-buttons-group a.button.clear:hover { background-color: #5a6268; }
        .filter-buttons-group .material-symbols-outlined { font-size: 1.2em;}

        .users-table-wrapper { overflow-x: auto; padding: 0.5rem; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;}
        .users-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; min-width: 800px; } 
        .users-table th, .users-table td { padding: 0.9rem 1.1rem; text-align: left; border-bottom: 1px solid var(--light-gray); white-space: nowrap; vertical-align: middle; }
        .users-table th { background-color: #f8f9fc; font-weight: 600; color: var(--dark-color); font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; }
        .users-table tbody tr:last-child td { border-bottom:none; }
        .users-table tbody tr:hover { background-color: #eff3f9; }
        .users-table .profile-photo-cell { display: flex; align-items: center; gap: 0.8rem; }
        .users-table .profile-photo { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.1);}
        .users-table .user-name-role .user-name { font-weight: 600; display:block; line-height:1.3; color: var(--dark-color); font-size: 0.95rem;}
        .users-table .user-name-role .user-role-badge { font-size: 0.8rem; padding: 0.25rem 0.8rem; border-radius: 12px; font-weight: 500; text-transform: capitalize; display: inline-block; margin-top: 4px; line-height: 1.2; }
        .users-table .user-role-badge.admin { background-color: var(--admin-badge-bg); color: var(--admin-badge-text); }
        .users-table .user-role-badge.employee { background-color: var(--employee-badge-bg); color: var(--employee-badge-text); }
        
        .status-badge { display: inline-flex; align-items:center; gap: 0.4rem; padding: 0.4rem 0.9rem; border-radius: 16px; font-size: 0.8rem; font-weight: 500; white-space: nowrap; text-align: center; min-width: 125px; justify-content: center; } 
        .status-badge .material-symbols-outlined { font-size: 1.1em; margin-right:2px;}
        .status-badge.present { background-color: rgba(var(--present-color-val), 0.15); color: rgb(var(--present-color-val)); }
        .status-badge.checked-out { background-color: rgba(var(--checked-out-color-val), 0.2); color: rgb(var(--checked-out-color-val));}
        .status-badge.on-leave { background-color: rgba(var(--on-leave-color-val), 0.15); color: rgb(var(--on-leave-color-val));}
        .status-badge.danger { background-color: rgba(var(--danger-color-val),0.15); color: rgb(var(--danger-color-val));}
        .status-badge.neutral { background-color: rgba(var(--neutral-color-val),0.15); color: rgb(var(--neutral-color-val));}
        
        .users-table td.rfid-uid-cell { color: #555; font-size: 0.85rem; }
        .users-table td.last-action-time { color: var(--gray-color); font-size: 0.85rem; }
        .planned-departure-cell .fa-sticky-note, .planned-departure-cell .tooltip-trigger .fa-sticky-note { margin-left: 6px; cursor: help; color: var(--primary-color); font-size: 1em; opacity: 0.7; }
        .planned-departure-cell .fa-sticky-note:hover { opacity: 1; }
        
        .tooltip-trigger { position: relative; display: inline-block; cursor: help; }
        .tooltip-content { visibility: hidden; opacity: 0; background-color: var(--dark-color); color: var(--light-color); text-align: left; border-radius: 6px; padding: 10px 14px; position: absolute; z-index: 10; bottom: 130%; left: 50%; transform: translateX(-50%); font-size: 0.85rem; white-space: normal; width: 220px; transition: opacity 0.25s, visibility 0.25s; box-shadow: 0 3px 12px rgba(0,0,0,0.2); line-height: 1.5; }
        .tooltip-content::after { content: ""; position: absolute; top: 100%; left: 50%; margin-left: -6px; border-width: 6px; border-style: solid; border-color: var(--dark-color) transparent transparent transparent; }
        .tooltip-trigger:hover .tooltip-content, .tooltip-trigger:focus .tooltip-content { visibility: visible; opacity: 1; }

        @media screen and (max-width: 1024px) { 
            .filter-group { min-width: calc(33.33% - 1rem); }
        }
         @media screen and (max-width: 880px) { 
            .filter-group { min-width: calc(50% - 1rem); } 
         }
         @media screen and (max-width: 600px) { 
            .filters-body { flex-direction: column; align-items: stretch; }
            .filter-group { width: 100%; min-width: unset; }
            .filter-buttons-group { width: 100%; justify-content: flex-start; margin-top: 1rem;}
            .admin-stats-grid { grid-template-columns: 1fr; } 
            .page-header h1 { font-size: 1.6rem;}
            .page-header .sub-heading { font-size: 0.9rem;}
        }
    </style>
</head>
<body>
    <?php require "../components/header-admin.php"; ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>User Activity Overview</h1>
                <p class="sub-heading">Live status and daily summary for all users as of <?php echo date("F d, Y"); ?>.</p>
            </div>
        </div>

        <div class="container">
            <?php if (isset($dbErrorMessage) && $dbErrorMessage): ?>
                <div class="db-error-message" role="alert">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i> <?php echo htmlspecialchars($dbErrorMessage); ?>
                </div>
            <?php endif; ?>

            <section class="admin-stats-grid">
                <div class="stat-card total-users">
                    <div class="icon"><span aria-hidden="true" translate="no" class="material-symbols-outlined">groups</span></div>
                    <div class="info">
                        <span class="value"><?php echo $totalUsers; ?></span>
                        <span class="label">Total Employees</span>
                    </div>
                </div>
                <div class="stat-card present-stat">
                    <div class="icon"><span aria-hidden="true" translate="no" class="material-symbols-outlined">person_check</span></div>
                    <div class="info">
                        <span class="value"><?php echo $usersPresentToday; ?></span>
                        <span class="label">Employees Present</span>
                    </div>
                </div>
                <div class="stat-card on-leave-stat">
                    <div class="icon"><span aria-hidden="true" translate="no" class="material-symbols-outlined">event_busy</span></div>
                     <div class="info">
                        <span class="value"><?php echo $usersOnLeaveToday; ?></span>
                        <span class="label">Employees On Leave</span>
                    </div>
                </div>
                <div class="stat-card late-stat">
                    <div class="icon"><span aria-hidden="true" translate="no" class="material-symbols-outlined">schedule_send</span></div>
                    <div class="info">
                        <span class="value"><?php echo $lateDepartureCount; ?></span>
                        <span class="label">Late Exits Notified</span>
                    </div>
                </div>
            </section>

            <section class="users-list-panel">
                <div class="panel-header">
                    <h2 class="panel-title">User Status List (<?php echo count($usersData); ?> matching)</h2>
                </div>

                <div class="filters-toggle-section <?php if($filtersExpanded) echo 'expanded'; ?>" id="filtersHeaderToggle" role="button" aria-expanded="<?php echo $filtersExpanded ? 'true' : 'false'; ?>" aria-controls="filtersBody" tabindex="0">
                    <h3><span aria-hidden="true" translate="no" class="material-symbols-outlined" style="font-size:1.2em; vertical-align:middle; margin-right:5px;">filter_list</span>Filter Users</h3>
                    <span aria-hidden="true" translate="no" class="material-symbols-outlined toggle-icon">expand_more</span>
                </div>
                <div class="filters-body <?php if($filtersExpanded) echo 'expanded'; ?>" id="filtersBody" role="region" aria-labelledby="filtersHeaderToggle">
                    <form method="GET" action="admin-users-view.php" style="display:contents; width:100%;">
                        <div class="filter-group">
                            <label for="filterName">Name/Username</label>
                            <input type="text" id="filterName" name="name" value="<?php echo htmlspecialchars($filterName); ?>" placeholder="Search name or username...">
                        </div>
                        <div class="filter-group">
                            <label for="filterRole">Role</label>
                            <select id="filterRole" name="role">
                                <option value="">All Roles</option>
                                <option value="admin" <?php if ($filterRole === 'admin') echo 'selected'; ?>>Admin</option>
                                <option value="employee" <?php if ($filterRole === 'employee') echo 'selected'; ?>>Employee</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filterStatus">Current Status</label>
                            <select id="filterStatus" name="status">
                                <option value="">All Statuses</option>
                                <option value="present" <?php if ($filterStatus === 'present') echo 'selected'; ?>>Present</option>
                                <option value="on_leave" <?php if ($filterStatus === 'on_leave') echo 'selected'; ?>>On Leave</option>
                                <option value="checked_out" <?php if ($filterStatus === 'checked_out') echo 'selected'; ?>>Checked Out</option>
                                <option value="not_checked_in" <?php if ($filterStatus === 'not_checked_in') echo 'selected'; ?>>Not Checked In</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filterLateDeparture">Planned Late Exit (Today)</label>
                            <select id="filterLateDeparture" name="late_departure">
                                <option value="">Any</option>
                                <option value="yes" <?php if ($filterLateDeparture === 'yes') echo 'selected'; ?>>Yes</option>
                                <option value="no" <?php if ($filterLateDeparture === 'no') echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        <div class="filter-buttons-group">
                            <button type="submit"><span aria-hidden="true" translate="no" class="material-symbols-outlined">search</span>Filter</button>
                            <?php if (!empty($filterName) || !empty($filterRole) || !empty($filterStatus) || !empty($filterLateDeparture)): ?>
                                <a href="admin-users-view.php" class="button clear"><span aria-hidden="true" translate="no" class="material-symbols-outlined">clear_all</span>Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="users-table-wrapper">
                    <table class="users-table" id="userStatusTable">
                        <thead>
                            <tr>
                                <th>Name & Role</th>
                                <th>Current Status</th>
                                <th>Last Action (Time)</th>
                                <th>Source (Card UID)</th>
                                <th>Planned Exit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($usersData)): ?>
                                <?php foreach ($usersData as $user): ?>
                                    <tr>
                                        <td data-label="Name & Role" class="profile-photo-cell">
                                            <img src="<?php echo htmlspecialchars($user['profile_photo_url']); ?>" alt="Photo" class="profile-photo">
                                            <div class="user-name-role">
                                                <span class="user-name"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></span>
                                                <span class="user-role-badge <?php echo htmlspecialchars(strtolower($user['roleID'])); ?>"><?php echo htmlspecialchars(ucfirst($user['roleID'])); ?></span>
                                            </div>
                                        </td>
                                        <td data-label="Current Status">
                                            <span class="status-badge <?php echo htmlspecialchars($user['status_class_display']); ?>">
                                                <?php 
                                                    $statusIcon = "help_outline"; 
                                                    switch ($user['status_class_display']) {
                                                        case 'present': $statusIcon = "person_check"; break;
                                                        case 'checked-out': $statusIcon = "logout"; break;
                                                        case 'on-leave': $statusIcon = "event_busy"; break;
                                                        case 'danger': $statusIcon = "gpp_maybe"; break;
                                                        case 'neutral': $statusIcon = "person_off"; break;
                                                    }
                                                ?>
                                                <span aria-hidden="true" translate="no" class="material-symbols-outlined"><?php echo $statusIcon; ?></span>
                                                <?php echo htmlspecialchars($user['current_status_display']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Last Action (Time)" class="last-action-time">
                                            <?php echo htmlspecialchars($user['last_action_display']); ?>
                                            <?php if ($user['last_action_time_display'] !== '--:--') echo ' @ ' . htmlspecialchars($user['last_action_time_display']); ?>
                                        </td>
                                        <td data-label="Source (Card UID)" class="rfid-uid-cell"><?php echo htmlspecialchars($user['rfid_used_last_action']); ?></td>
                                        <td data-label="Planned Exit" class="planned-departure-cell">
                                            <?php echo $user['planned_departure_info']; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php if (!$dbErrorMessage): ?>
                                <tr>
                                    <td colspan="5" class="no-users-msg" style="text-align: center; padding: 2rem;">
                                        <span aria-hidden="true" translate="no" class="material-symbols-outlined" style="font-size: 3rem; display:block; margin-bottom:0.5rem;">search_off</span>
                                        No users found matching your criteria. Try adjusting the filters.
                                    </td>
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
        const filtersHeaderToggle = document.getElementById('filtersHeaderToggle');
        const filtersBody = document.getElementById('filtersBody');

        if (filtersHeaderToggle && filtersBody) {
            const toggleFilters = () => {
                const isExpanded = filtersBody.classList.toggle('expanded');
                filtersHeaderToggle.classList.toggle('expanded', isExpanded);
                filtersHeaderToggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
            };

            filtersHeaderToggle.addEventListener('click', toggleFilters);
            filtersHeaderToggle.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleFilters();
                }
            });
        }

        const mobileNavToggle = document.querySelector('.mobile-nav-toggle');
        const mainNav = document.querySelector('.main-nav');
        if (mobileNavToggle && mainNav) {
            mobileNavToggle.addEventListener('click', function() {
                const isExpanded = mobileNavToggle.getAttribute('aria-expanded') === 'true' || false;
                mobileNavToggle.setAttribute('aria-expanded', !isExpanded);
                mainNav.classList.toggle('active'); 
                 document.body.classList.toggle('mobile-nav-active');
            });
        }

        const tooltipTriggers = document.querySelectorAll('.tooltip-trigger');
        tooltipTriggers.forEach(trigger => {
            trigger.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    // This simply ensures the element is focused if not already,
                    // relying on CSS :focus for tooltip visibility.
                    if (document.activeElement !== trigger) {
                        trigger.focus();
                    }
                    // If your tooltips are JS-driven, you'd toggle visibility here.
                }
            });
        });
    });
</script>
</body>
</html>