<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Restrict Access: Ensure only admin can access
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || 
    !isset($_SESSION["role"]) || strtolower($_SESSION["role"]) !== 'admin') {
    header("location: ../login.php"); 
    exit;
}

require_once '../db.php'; 

$sessionAdminFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$sessionAdminUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;

$dbErrorMessage = null;
$successMessage = null;
$allAbsenceRequests = [];

// --- HANDLE ACTION (APPROVE/REJECT) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_absence_id']) && isset($_POST['action_type']) && isset($pdo)) {
    $absence_id_to_action = filter_input(INPUT_POST, 'action_absence_id', FILTER_VALIDATE_INT);
    $action_type = $_POST['action_type']; // 'approve' or 'reject'

    if ($absence_id_to_action && ($action_type === 'approve' || $action_type === 'reject')) {
        try {
            $new_status = ($action_type === 'approve') ? 'approved' : 'rejected';
            
            $stmtGetUserID = $pdo->prepare("SELECT userID FROM absence WHERE absenceID = :absenceID");
            $stmtGetUserID->bindParam(':absenceID', $absence_id_to_action, PDO::PARAM_INT);
            $stmtGetUserID->execute();
            $absence_user = $stmtGetUserID->fetch(PDO::FETCH_ASSOC);

            if ($absence_user && isset($absence_user['userID'])) {
                $pdo->beginTransaction(); 

                $sqlAction = "UPDATE absence SET status = :new_status, updated_at = NOW() WHERE absenceID = :absenceID AND status = 'pending_approval'";
                $stmtAction = $pdo->prepare($sqlAction);
                $stmtAction->bindParam(':new_status', $new_status, PDO::PARAM_STR);
                $stmtAction->bindParam(':absenceID', $absence_id_to_action, PDO::PARAM_INT);

                if ($stmtAction->execute()) {
                    if ($stmtAction->rowCount() > 0) {
                        $user_absence_status_update_needed = true;
                        $user_final_absence_state = 0; // Assume present

                        if ($new_status === 'approved') {
                            $user_final_absence_state = 1; // On approved absence
                        } else { // 'rejected'
                            // Check if user has any *other* active approved absence
                            $stmtCheckOtherApproved = $pdo->prepare("SELECT COUNT(*) FROM absence 
                                                                    WHERE userID = :userID 
                                                                    AND status = 'approved' 
                                                                    AND :now BETWEEN absence_start_datetime AND absence_end_datetime
                                                                    AND absenceID != :current_rejected_id"); // Exclude the one just rejected
                            $stmtCheckOtherApproved->execute([
                                ':userID' => $absence_user['userID'],
                                ':now' => date('Y-m-d H:i:s'),
                                ':current_rejected_id' => $absence_id_to_action
                            ]);
                            if ($stmtCheckOtherApproved->fetchColumn() > 0) {
                                $user_final_absence_state = 1; // Still on another approved absence
                            }
                        }
                        
                        if ($user_absence_status_update_needed) {
                            $sqlUpdateUserAbsence = $pdo->prepare("UPDATE users SET absence = :absence_status WHERE userID = :userID");
                            $sqlUpdateUserAbsence->bindParam(':absence_status', $user_final_absence_state, PDO::PARAM_INT);
                            $sqlUpdateUserAbsence->bindParam(':userID', $absence_user['userID'], PDO::PARAM_INT);
                            $sqlUpdateUserAbsence->execute();
                        }

                        $pdo->commit(); 
                        $successMessage = "Absence request (ID: {$absence_id_to_action}) has been " . htmlspecialchars($new_status) . ". User status potentially updated.";
                    } else {
                        $pdo->rollBack();
                        $dbErrorMessage = "Could not update absence request (ID: {$absence_id_to_action}). It might have been already processed or does not exist.";
                    }
                } else {
                    $pdo->rollBack();
                    $dbErrorMessage = "Failed to update absence request status.";
                }
            } else {
                 $dbErrorMessage = "Could not find user for absence request ID: {$absence_id_to_action}.";
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $dbErrorMessage = "Database error processing action: " . $e->getMessage();
            error_log("Admin Manage Absences - DB Error processing action: " . $e->getMessage());
        }
    } else {
        $dbErrorMessage = "Invalid action or absence ID.";
    }
}


// --- DATA FETCHING for absence requests ---
$currentFilter = isset($_GET['filter']) ? $_GET['filter'] : 'pending_approval'; 

if (isset($pdo)) {
    try {
        $sqlWhereClauses = [];
        $params = []; 

        if ($currentFilter == 'pending_approval') {
            $sqlWhereClauses[] = "a.status = :status_filter";
            $params[':status_filter'] = 'pending_approval';
        } elseif ($currentFilter == 'approved') {
            $sqlWhereClauses[] = "a.status = :status_filter";
            $params[':status_filter'] = 'approved';
        } elseif ($currentFilter == 'rejected') {
            $sqlWhereClauses[] = "a.status = :status_filter";
            $params[':status_filter'] = 'rejected';
        }
        
        $sqlBase = "SELECT a.absenceID, a.absence_start_datetime, a.absence_end_datetime, 
                           a.reason, a.absence_type, a.status, a.notes, a.created_at,
                           u.firstName, u.lastName, u.username
                    FROM absence a
                    JOIN users u ON a.userID = u.userID";
        
        $sqlFetch = $sqlBase;
        if (!empty($sqlWhereClauses)) {
            $sqlFetch .= " WHERE " . implode(" AND ", $sqlWhereClauses);
        }
        $sqlFetch .= " ORDER BY CASE a.status WHEN 'pending_approval' THEN 1 WHEN 'approved' THEN 2 WHEN 'rejected' THEN 3 ELSE 4 END, a.created_at DESC, a.absence_start_datetime DESC";
        
        $stmtFetch = $pdo->prepare($sqlFetch);
        $stmtFetch->execute($params); 
        $allAbsenceRequests = $stmtFetch->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error fetching absence requests: " . $e->getMessage();
        error_log("Admin Manage Absences - DB Error fetching requests: " . $e->getMessage());
    }
} else {
    $dbErrorMessage = "Database connection not available.";
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../imgs/logo.png" type="image/x-icon">
    <title>Manage Absences - Admin - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --primary-color-rgb: 67, 97, 238; 
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --warning-color: #FF9800; --danger-color: #F44336;
            --info-color: #2196F3; 
            --pending-color: var(--warning-color); 
            --approved-color: var(--success-color);
            --rejected-color: var(--danger-color);
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px;; }
        
        .page-container { 
            max-width: 1440px; margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px;
            display: flex; gap: 1.8rem; margin-top: 1.5rem; align-items: flex-start;
        }
        .container, .page-header .container { 
             max-width: 1440px; margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px;
        }

        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }

        .message-output { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .db-error-message { background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        .success-message { background-color: rgba(76,175,80,0.1); color: var(--success-color); border-left-color: var(--success-color); }

        .sidebar { 
            flex: 0 0 280px; 
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
            height: fit-content; 
        }
        .sidebar h3 {
            font-size: 1.25rem; margin-bottom: 1.2rem; color: var(--dark-color);
            padding-bottom: 0.6rem; border-bottom: 1px solid var(--light-gray);
        }
        .filter-list { list-style: none; padding: 0; margin: 0; }
        .filter-list li a {
            display: flex; align-items: center; gap: 0.8rem;
            padding: 0.85rem 1.1rem; text-decoration: none;
            color: var(--dark-color); border-radius: 6px;
            transition: var(--transition); font-weight: 500; font-size: 0.95rem;
        }
        .filter-list li a:hover, .filter-list li a.active-filter {
            background-color: rgba(var(--primary-color-rgb), 0.1);
            color: var(--primary-color);
            font-weight: 600;
        }
        .filter-list li a .material-symbols-outlined { font-size: 1.4em; }

        .main-content { 
            flex-grow: 1; 
            background-color: var(--white);
            padding: 1.5rem 1.8rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
            border: 1px solid var(--light-gray);
        }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); }
        .panel-title { font-size: 1.3rem; color: var(--dark-color); margin:0; }
        
        .absences-table-wrapper { overflow-x: auto; } /* Potrebné pre horizontálne skrolovanie na veľmi malých obrazovkách, ak by sa nezmestilo ani "Card View" */
        .absences-table { width: 100%; border-collapse: collapse; }
        .absences-table th, .absences-table td { padding: 0.9rem 1rem; text-align: left; border-bottom: 1px solid var(--light-gray); font-size:0.9rem; vertical-align: middle;}
        .absences-table th { background-color: #f9fafb; font-weight: 600; color: var(--gray-color); text-transform: uppercase; letter-spacing: 0.5px; font-size:0.8rem;}
        /* .absences-table tbody tr:hover { background-color: #f5f7fa; } */ /* Hover môže byť mätúci pri card view */
        
        .status-badge { padding: 0.3rem 0.7rem; border-radius: 15px; font-size: 0.75rem; font-weight: 600; color: var(--white); text-transform: capitalize; display:inline-block; }
        .status-pending_approval { background-color: var(--pending-color); }
        .status-approved { background-color: var(--approved-color); }
        .status-rejected { background-color: var(--rejected-color); }

        .action-buttons form { margin-bottom: 0.3rem; display: block; /* Aby boli tlačidlá pod sebou v card view */ } 
        .action-buttons button, .action-buttons .btn-disabled {
            padding: 0.4rem 0.8rem; width: 100%;
            border: none; border-radius: 5px;
            cursor: pointer; font-size: 0.8rem; font-weight: 500; transition: var(--transition);
            display: inline-flex; align-items: center; justify-content: center; gap: 0.3rem;
        }
        .action-buttons .btn-approve { background-color: var(--success-color); color: white; }
        .action-buttons .btn-approve:hover { background-color: #3e8e41; }
        .action-buttons .btn-reject { background-color: var(--danger-color); color: white; }
        .action-buttons .btn-reject:hover { background-color: #c21807; }
        .action-buttons .btn-disabled { background-color: #ccc; color: #666; cursor: not-allowed; }

        .notes-tooltip { position: relative; display: inline-block; }
        .notes-tooltip .tooltip-text {
            visibility: hidden; width: 250px; background-color: #333; color: #fff;
            text-align: left; border-radius: 6px; padding: 8px; position: absolute;
            z-index: 10; bottom: 125%; left: 50%; margin-left: -125px; opacity: 0;
            transition: opacity 0.3s; font-size: 0.8rem; line-height: 1.4; white-space: pre-wrap;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .notes-tooltip:hover .tooltip-text { visibility: visible; opacity: 1; }
        .notes-tooltip .material-symbols-outlined { cursor: help; color: var(--info-color); font-size: 1.2rem; vertical-align: middle;}

        .no-requests { text-align: center; padding: 2rem; color: var(--gray-color); }
        
        /* --- RESPONSIVE TABLE STYLES ("Card View") --- */
        @media screen and (max-width: 768px) {
            .absences-table thead {
                display: none; /* Skryjeme hlavičku tabuľky */
            }
            .absences-table, .absences-table tbody, .absences-table tr, .absences-table td {
                display: block; /* Všetko bude blokový element */
                width: 100% !important;
            }
            .absences-table tr {
                margin-bottom: 1rem; /* Odsadenie medzi "kartami" */
                border: 1px solid var(--light-gray);
                border-radius: 6px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                padding: 0.8rem;
            }
            .absences-table td {
                text-align: right; /* Hodnoty zarovnáme doprava */
                padding-left: 50%; /* Vytvoríme miesto pre label */
                position: relative;
                border-bottom: 1px dashed var(--light-gray); /* Jemnejšie oddelenie v rámci karty */
            }
            .absences-table td:last-child {
                border-bottom: none; /* Posledná bunka v karte bez spodnej čiary */
            }
            .absences-table td::before {
                content: attr(data-label); /* Zobrazíme text z data-label */
                position: absolute;
                left: 10px; /* Odsadenie labelu zľava */
                width: calc(50% - 20px); /* Šírka labelu */
                padding-right: 10px;
                font-weight: 600;
                text-align: left;
                color: var(--dark-color);
            }
            .action-buttons { /* Akcie zarovnáme naľavo na mobiloch pre lepšiu prehľadnosť */
                text-align: left;
                padding-left: 0; /* Reset paddingu z td */
            }
            .action-buttons td::before { content: ""; } /* Pre akcie nechceme label */

            .action-buttons form {
                display: inline-block; /* Aby boli tlačidlá vedľa seba, ak sa zmestia */
                width: auto; /* Šírka sa prispôsobí obsahu tlačidla */
                margin-right: 0.5rem;
            }
             .action-buttons button, .action-buttons .btn-disabled {
                width: auto; /* Prispôsobenie šírky pre mobil */
                padding: 0.5rem 0.8rem;
            }
            .absences-table td.action-buttons::before { /* Odstráni label pre action buttons aj na mobiloch */
                content: "";
            }
             /* Skrytie ID a Requested On stĺpcov, ak sú menej dôležité na mobile */
            .absences-table td[data-label="ID"],
            .absences-table td[data-label="Requested"] {
                display: none;
            }
        }
        @media (max-width: 992px) { 
            .page-container { flex-direction: column; }
            .sidebar { width: 100%; margin-bottom: 1.5rem; }
        }

    </style>
</head>
<body>
    <?php require "../components/header-admin.php"; ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>Manage Absence Requests</h1>
                <p class="sub-heading">Review and process employee absence requests.</p>
            </div>
        </div>

        <div class="page-container"> 
            <aside class="sidebar">
                <h3>Filter Requests</h3>
                <ul class="filter-list">
                    <li><a href="admin-manage-absence.php?filter=pending_approval" class="<?php if ($currentFilter == 'pending_approval') echo 'active-filter'; ?>"><span class="material-symbols-outlined">pending_actions</span> Pending</a></li>
                    <li><a href="admin-manage-absence.php?filter=approved" class="<?php if ($currentFilter == 'approved') echo 'active-filter'; ?>"><span class="material-symbols-outlined">check_circle</span> Approved</a></li>
                    <li><a href="admin-manage-absence.php?filter=rejected" class="<?php if ($currentFilter == 'rejected') echo 'active-filter'; ?>"><span class="material-symbols-outlined">cancel</span> Rejected</a></li>
                    <li><a href="admin-manage-absence.php?filter=all" class="<?php if ($currentFilter == 'all') echo 'active-filter'; ?>"><span class="material-symbols-outlined">list_alt</span> All Requests</a></li>
                </ul>
            </aside>

            <div class="main-content">
                <?php if (isset($dbErrorMessage) && $dbErrorMessage): ?>
                    <div class="message-output db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbErrorMessage); ?></div>
                <?php endif; ?>
                <?php if (isset($successMessage) && $successMessage): ?>
                    <div class="message-output success-message" role="alert"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>

                <div class="panel-header" style="margin-bottom: 0.5rem;"> 
                    <h2 class="panel-title">Absence Requests <span style="color: var(--primary-color); font-weight:500;">(<?php echo ucfirst(str_replace('_', ' ', $currentFilter)); ?>)</span></h2>
                </div>

                <div class="absences-table-wrapper">
                    <table class="absences-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Reason</th>
                                <th>Notes</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($allAbsenceRequests)): ?>
                                <?php foreach ($allAbsenceRequests as $request): ?>
                                    <tr>
                                        <td data-label="ID"><?php echo htmlspecialchars($request['absenceID']); ?></td>
                                        <td data-label="Employee"><?php echo htmlspecialchars($request['firstName'] . ' ' . $request['lastName']); ?> <br><small>(@<?php echo htmlspecialchars($request['username']); ?>)</small></td>
                                        <td data-label="Type"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $request['absence_type']))); ?></td>
                                        <td data-label="From"><?php echo date("d M Y H:i", strtotime($request['absence_start_datetime'])); ?></td>
                                        <td data-label="To"><?php echo date("d M Y H:i", strtotime($request['absence_end_datetime'])); ?></td>
                                        <td data-label="Reason">
                                            <?php 
                                                $reason_full = htmlspecialchars($request['reason'] ?? '');
                                                $reason_short = mb_substr($reason_full, 0, 25); // Použitie mb_substr pre multibyte znaky
                                                if (mb_strlen($reason_full) > 25) {
                                                    echo $reason_short . '...';
                                                } else {
                                                    echo $reason_full ?: '-';
                                                }
                                            ?>
                                        </td>
                                        <td data-label="Notes">
                                            <?php if (!empty($request['notes'])): ?>
                                                <div class="notes-tooltip">
                                                    <span class="material-symbols-outlined">chat_bubble</span>
                                                    <span class="tooltip-text"><?php echo nl2br(htmlspecialchars($request['notes'])); ?></span>
                                                </div>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Status"><span class="status-badge status-<?php echo htmlspecialchars(strtolower($request['status'])); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $request['status']))); ?></span></td>
                                        <td data-label="Requested"><?php echo date("d M Y", strtotime($request['created_at'])); ?></td>
                                        <td data-label="Actions" class="action-buttons">
                                            <?php if ($request['status'] === 'pending_approval'): ?>
                                                <form action="admin-manage-absence.php?filter=<?php echo htmlspecialchars($currentFilter); ?>" method="POST" style="display:inline-block; margin-bottom:0.3rem;">
                                                    <input type="hidden" name="action_absence_id" value="<?php echo $request['absenceID']; ?>">
                                                    <button type="submit" name="action_type" value="approve" class="btn-approve" onclick="return confirm('Are you sure you want to approve this absence request?');">
                                                        <span class="material-symbols-outlined" style="font-size:1em;">check</span> Approve
                                                    </button>
                                                </form>
                                                <form action="admin-manage-absence.php?filter=<?php echo htmlspecialchars($currentFilter); ?>" method="POST" style="display:inline-block;">
                                                    <input type="hidden" name="action_absence_id" value="<?php echo $request['absenceID']; ?>">
                                                    <button type="submit" name="action_type" value="reject" class="btn-reject" onclick="return confirm('Are you sure you want to reject this absence request?');">
                                                        <span class="material-symbols-outlined" style="font-size:1em;">close</span> Reject
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="btn-disabled">Processed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="no-requests">No absence requests found for the filter "<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $currentFilter))); ?>".</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div> 
        </div> 
    </main>

    <?php require_once "../components/footer-admin.php"; ?>

    <script>
        // Hamburger menu script (ak nie je už v header-admin.php)
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');
        const closeMenuBtn = document.getElementById('closeMenu'); 
        const body = document.body;

        if (hamburger && mobileMenu) {
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                mobileMenu.classList.toggle('active');
                body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
                hamburger.setAttribute('aria-expanded', mobileMenu.classList.contains('active'));
                mobileMenu.setAttribute('aria-hidden', !mobileMenu.classList.contains('active'));
            });
            if (closeMenuBtn) {
                closeMenuBtn.addEventListener('click', () => {
                    mobileMenu.classList.remove('active');
                    hamburger.classList.remove('active'); 
                    body.style.overflow = '';
                    hamburger.setAttribute('aria-expanded', 'false');
                    mobileMenu.setAttribute('aria-hidden', 'true');
                    if (hamburger) hamburger.focus(); 
                });
            }
            mobileMenu.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    if (mobileMenu.classList.contains('active')) {
                        mobileMenu.classList.remove('active');
                        hamburger.classList.remove('active');
                        body.style.overflow = '';
                        hamburger.setAttribute('aria-expanded', 'false');
                        mobileMenu.setAttribute('aria-hidden', 'true');
                    }
                });
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && mobileMenu.classList.contains('active')) {
                    if(closeMenuBtn) closeMenuBtn.click(); else hamburger.click();
                }
            });
        }
    </script>
</body>
</html>