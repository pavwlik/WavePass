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

require_once '../db.php'; // Correct path since this file is in /admin/

$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$todayDate = date('Y-m-d');
$todayDateTime = date('Y-m-d H:i:s');

$usersData = [];
$dbErrorMessage = null;

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // 1. Fetch all users
        $stmtUsers = $pdo->query("SELECT userID, firstName, lastName, email, username, role FROM users ORDER BY lastName, firstName");
        $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch all primary, active RFIDs
        $stmtRfids = $pdo->query("SELECT userID, name, rfid_url FROM rfids WHERE card_type = 'Primary Access Card' AND is_active = 1");
        $primaryRfids = [];
        foreach ($stmtRfids->fetchAll(PDO::FETCH_ASSOC) as $rfid) {
            $primaryRfids[$rfid['userID']] = $rfid;
        }

        // 3. Fetch latest attendance logs for today for all users
        $stmtLatestLogs = $pdo->prepare(
            "SELECT al.userID, al.logType, al.logResult, al.logTime
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

        // 5. Combine data for each user
        foreach ($users as $user) {
            $userData = $user; // Includes userID, firstName, lastName, email, username, role

            $userData['rfid_name'] = $primaryRfids[$user['userID']]['name'] ?? 'N/A';
            $userData['rfid_uid'] = $primaryRfids[$user['userID']]['rfid_url'] ?? 'N/A';

            $status = "Absent (Not Checked In)";
            $statusClass = "neutral";
            $lastAction = "N/A";
            $lastActionTime = "--:--";

            if (isset($latestLogs[$user['userID']])) {
                $log = $latestLogs[$user['userID']];
                $lastActionTime = date("H:i", strtotime($log['logTime']));

                if ($log['logResult'] == 'granted') {
                    if ($log['logType'] == 'entry') {
                        $status = "Present";
                        $statusClass = "present";
                        $lastAction = "Entry";
                    } elseif ($log['logType'] == 'exit') {
                        $status = "Absent (Checked Out)";
                        $statusClass = "absent"; // Or a different class like 'checked-out'
                        $lastAction = "Exit";
                    }
                } elseif ($log['logResult'] == 'denied') {
                    $status = "Access Denied";
                    $statusClass = "danger";
                    $lastAction = ($log['logType'] == 'entry' ? "Entry Attempt" : "Exit Attempt");
                }
            } elseif (isset($approvedAbsences[$user['userID']])) {
                $absence = $approvedAbsences[$user['userID']];
                $absenceTypeDisplay = ucfirst(str_replace('_', ' ', $absence['absence_type']));
                $status = "On Leave (" . htmlspecialchars($absenceTypeDisplay) . ")";
                $statusClass = "info"; // Or a specific 'on-leave' class
                $lastAction = "N/A";
            }
            // If no log and no absence, defaults remain: "Absent (Not Checked In)", "neutral", "N/A"

            $userData['current_status'] = $status;
            $userData['status_class'] = $statusClass;
            $userData['last_action'] = $lastAction;
            $userData['last_action_time'] = $lastActionTime;

            $usersData[] = $userData;
        }

    } catch (PDOException $e) {
        error_log("Admin Users Page DB Error: " . $e->getMessage());
        $dbErrorMessage = "A database error occurred while fetching user data. Please try again later.";
    } catch (Exception $e) {
        error_log("Admin Users Page App Error: " . $e->getMessage());
        $dbErrorMessage = "An application error occurred. Please try again later.";
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
    <link rel="icon" href="../imgs/logo.png" type="image/x-icon">
    <title>User Overview - WavePass</title>
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
            --success-color: #28a745; /* True Green for success */
            --warning-color: #f8961e;
            --danger-color: #f72585; /* Original danger */
            --info-color-custom: #007bff; /* Standard Info Blue */
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;

            --present-color-val: 40, 167, 69; /* Green (Bootstrap success) */
            --absent-color-val: 255, 193, 7;  /* Yellow (Bootstrap warning for checked-out) */
            --neutral-color-val: 108, 117, 125; /* Gray (Bootstrap secondary for not checked in) */
            --info-color-val: 0, 123, 255;    /* Blue (Bootstrap info for on leave) */
            --danger-color-val: 220, 53, 69;   /* Red (Bootstrap danger for denied) */

            --present-color: rgb(var(--present-color-val));
            --absent-color: rgb(var(--absent-color-val)); /* This will be for 'Checked Out' */
            --neutral-color: rgb(var(--neutral-color-val)); /* This for 'Not Checked In' */
            --info-color: rgb(var(--info-color-val)); /* This for 'On Leave' */
            --true-danger-color: rgb(var(--danger-color-val)); /* This for 'Access Denied' */
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9;
            display: flex; flex-direction: column; min-height: 100vh;
            padding-top: 80px; /* Assuming header height */
        }
        main { flex-grow: 1; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }

        .page-header { padding: 1.8rem 0; margin-bottom: 1.8rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; color: var(--dark-color); margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        .db-error-message {background-color: rgba(var(--danger-color-val),0.1); color: var(--true-danger-color); padding: 1rem; border-left: 4px solid var(--true-danger-color); margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}

        .content-panel { background-color: var(--white); padding: 1.5rem 1.8rem; border-radius: 8px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); }
        .panel-title { font-size: 1.3rem; color: var(--dark-color); margin:0; }

        .users-table-wrapper { overflow-x: auto; }
        .users-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .users-table th, .users-table td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid var(--light-gray); white-space: nowrap; }
        .users-table th { background-color: #f9fafb; font-weight: 500; color: var(--gray-color); font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; }
        .users-table tbody tr:last-child td { border-bottom:none; }
        .users-table tbody tr:hover { background-color: #f0f4ff; }

        .status-badge { display: inline-flex; align-items:center; gap:0.4rem; padding: 0.3rem 0.8rem; border-radius: 15px; font-size: 0.78rem; font-weight: 500; }
        .status-badge.present { background-color: rgba(var(--present-color-val), 0.15); color: var(--present-color); }
        .status-badge.absent { background-color: rgba(var(--absent-color-val), 0.15); color: var(--absent-color); } /* For Checked Out */
        .status-badge.neutral { background-color: rgba(var(--neutral-color-val),0.15); color: var(--neutral-color);} /* For Not Checked In */
        .status-badge.info { background-color: rgba(var(--info-color-val), 0.15); color: var(--info-color); } /* For On Leave */
        .status-badge.danger { background-color: rgba(var(--danger-color-val),0.15); color: var(--true-danger-color);} /* For Access Denied */

        .users-table td.rfid-uid { font-family: monospace; color: var(--secondary-color); font-size: 0.85rem; }
        .no-users-msg {text-align:center; padding: 2.5rem 1rem; color:var(--gray-color); font-size:0.95rem; background-color: #fdfdfd; border-radius: 4px; border: 1px dashed var(--light-gray);}
        .user-role {
            font-size: 0.75rem;
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            color: var(--white);
            margin-left: 8px;
            text-transform: capitalize;
        }
        .user-role.admin { background-color: var(--primary-color); }
        .user-role.employee { background-color: var(--secondary-color); } /* Or another color */
        .user-role.manager { background-color: var(--warning-color); color: var(--dark-color); } /* Example */


    </style>
</head>
<body>
    <?php require_once "../components/header-admin.php" ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>User Overview</h1>
                <p class="sub-heading">Current status and RFID details for all users.</p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if (isset($dbErrorMessage) && $dbErrorMessage): ?>
                <div class="db-error-message" role="alert">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i> <?php echo htmlspecialchars($dbErrorMessage); ?>
                </div>
            <?php endif; ?>

            <section class="content-panel">
                <div class="panel-header">
                    <h2 class="panel-title">All Users (<?php echo count($usersData); ?>)</h2>
                    <!-- Add filters or search here in the future if needed -->
                </div>

                <div class="users-table-wrapper">
                    <table class="users-table" id="allUsersTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email / Username</th>
                                <th>Current Status</th>
                                <th>Last Action</th>
                                <th>Time</th>
                                <th>Primary Card Name</th>
                                <th>Primary Card UID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($usersData)): ?>
                                <?php foreach ($usersData as $user): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?>
                                            <span class="user-role <?php echo htmlspecialchars(strtolower($user['role'])); ?>"><?php echo htmlspecialchars($user['role']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email'] ?: $user['username']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo htmlspecialchars($user['status_class']); ?>">
                                                <?php echo htmlspecialchars($user['current_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['last_action']); ?></td>
                                        <td><?php echo htmlspecialchars($user['last_action_time']); ?></td>
                                        <td><?php echo htmlspecialchars($user['rfid_name']); ?></td>
                                        <td class="rfid-uid"><?php echo htmlspecialchars($user['rfid_uid']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php if (!$dbErrorMessage): // Only show "no users" if there wasn't a DB error already displayed ?>
                                <tr>
                                    <td colspan="7" class="no-users-msg">No users found in the system.</td>
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
    // Basic JS, if any, can go here. For now, not much is needed for this static table.
    // Sorting or filtering could be added in the future.
    document.addEventListener('DOMContentLoaded', function() {
        // Hamburger & Mobile menu script is assumed to be in header-admin.php
        // Header shadow on scroll is assumed to be in header-admin.php
    });
</script>
</body>
</html>