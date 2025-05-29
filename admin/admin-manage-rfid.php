<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('RFID_ADDING_API_STATUS_FILE', dirname(__DIR__) . '/rfid_adding_api_status.txt'); // Súbor bude v koreni projektu

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Restrict Access: Ensure only admin can access
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    header("location: ../login.php");
    exit;
}

require_once '../db.php'; // Path relative to admin/ folder

// --- FUNKCIE PRE OVLÁDANIE STAVU RFID API ---
function isRfidAddingApiEnabled() {
    if (!file_exists(RFID_ADDING_API_STATUS_FILE)) {
        @file_put_contents(RFID_ADDING_API_STATUS_FILE, 'enabled');
        return true;
    }
    return trim(@file_get_contents(RFID_ADDING_API_STATUS_FILE)) === 'enabled';
}

function setRfidAddingApiStatus($isEnabled) {
    $statusString = $isEnabled ? 'enabled' : 'disabled';
    return @file_put_contents(RFID_ADDING_API_STATUS_FILE, $statusString) !== false;
}
// --- KONIEC FUNKCIÍ PRE OVLÁDANIE STAVU RFID API ---

$sessionAdminFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$dbErrorMessage = null;
$successMessage = null;
$allRfids = [];
$allUsers = [];

// --- ACTION HANDLING ---
// HANDLE TOGGLE RFID ADDING API STATUS (AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'toggle_rfid_api_status') {
    header('Content-Type: application/json');
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    $newApiState = isset($_POST['enable_api']) && $_POST['enable_api'] === 'true';
    if (setRfidAddingApiStatus($newApiState)) {
        echo json_encode(['status' => 'success', 'message' => 'RFID Adding API status updated.', 'newState' => $newApiState ? 'enabled' : 'disabled']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update RFID Adding API status. Check file permissions for: ' . basename(RFID_ADDING_API_STATUS_FILE)]);
    }
    exit;
}

// HANDLE ADD NEW RFID CARD (FORM SUBMISSION ON THIS PAGE)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_rfid') {
    $rfid_uid_new = trim(filter_input(INPUT_POST, 'rfid_uid', FILTER_SANITIZE_SPECIAL_CHARS));
    $rfid_name_new = trim(filter_input(INPUT_POST, 'rfid_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $rfid_card_type_new = filter_input(INPUT_POST, 'rfid_card_type', FILTER_SANITIZE_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE);
    $rfid_assign_user_new = filter_input(INPUT_POST, 'assign_userID', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $rfid_is_active_new = isset($_POST['is_active']) ? 1 : 0;

    if (empty($rfid_uid_new)) {
        $dbErrorMessage = "RFID URL/UID is required.";
    } else {
        try {
            $stmtCheck = $pdo->prepare("SELECT RFID FROM rfids WHERE rfid_uid = :rfid_uid");
            $stmtCheck->bindParam(':rfid_uid', $rfid_uid_new, PDO::PARAM_STR);
            $stmtCheck->execute();
            if ($stmtCheck->fetch()) {
                $dbErrorMessage = "This RFID URL/UID ('" . htmlspecialchars($rfid_uid_new) . "') already exists.";
            } else {
                $sqlInsert = "INSERT INTO rfids (rfid_uid, name, card_type, userID, is_active)
                              VALUES (:rfid_uid, :name, :card_type, :userID, :is_active)";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->bindParam(':rfid_uid', $rfid_uid_new);
                $stmtInsert->bindParam(':name', $rfid_name_new);
                $stmtInsert->bindParam(':card_type', $rfid_card_type_new);
                $stmtInsert->bindParam(':userID', $rfid_assign_user_new, $rfid_assign_user_new ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmtInsert->bindParam(':is_active', $rfid_is_active_new, PDO::PARAM_INT);
                if ($stmtInsert->execute()) {
                    $successMessage = "New RFID card added successfully! You might need to refresh the 'All Cards' view if it's currently active.";
                } else {
                    $errorInfo = $stmtInsert->errorInfo();
                    $dbErrorMessage = "Failed to add new RFID card. DB Error: " . ($errorInfo[2] ?? "Unknown error");
                }
            }
        } catch (PDOException $e) {
            $dbErrorMessage = "Database error adding RFID card: " . $e->getMessage();
        }
    }
}

// HANDLE QUICK TOGGLE ACTIVE STATUS
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'toggle_rfid_status') {
    $rfid_to_toggle = filter_input(INPUT_POST, 'rfid_id_toggle', FILTER_VALIDATE_INT);
    $current_status = filter_input(INPUT_POST, 'current_status', FILTER_VALIDATE_INT);

    if ($rfid_to_toggle) {
        $new_status = ($current_status == 1) ? 0 : 1;
        try {
            $stmtToggle = $pdo->prepare("UPDATE rfids SET is_active = :new_status WHERE RFID = :rfid_id");
            $stmtToggle->bindParam(':new_status', $new_status, PDO::PARAM_INT);
            $stmtToggle->bindParam(':rfid_id', $rfid_to_toggle, PDO::PARAM_INT);
            if ($stmtToggle->execute()) {
                $successMessage = "RFID card status updated successfully.";
            } else {
                $dbErrorMessage = "Failed to update RFID card status.";
            }
        } catch (PDOException $e) {
            $dbErrorMessage = "Database error toggling status: " . $e->getMessage();
        }
    } else {
        $dbErrorMessage = "Invalid RFID ID for status toggle.";
    }
}

// --- DATA FETCHING ---
$rfidApiCurrentlyEnabled = isRfidAddingApiEnabled();
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmtAllRfids = $pdo->query(
            "SELECT r.RFID, r.rfid_uid, r.name as rfid_name, r.card_type, r.is_active, r.userID,
                    u.firstName, u.lastName, u.username
             FROM rfids r
             LEFT JOIN users u ON r.userID = u.userID
             ORDER BY r.RFID DESC"
        );
        $allRfids = $stmtAllRfids->fetchAll(PDO::FETCH_ASSOC);
        $stmtAllUsers = $pdo->query("SELECT userID, firstName, lastName, username FROM users ORDER BY lastName, firstName");
        $allUsers = $stmtAllUsers->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error fetching RFID data: " . $e->getMessage();
    }
} else {
    $dbErrorMessage = "Database connection is not available.";
}

$currentPage = basename($_SERVER['PHP_SELF']);
// Parameter pre určenie, ktorá sekcia sa má zobraziť. Defaultne 'all_cards'.
$currentView = isset($_GET['view']) ? $_GET['view'] : 'all_cards';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../imgs/logo.png" type="image/x-icon">
    <title>Manage RFID Cards - Admin - WavePass</title>
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
            --active-color: var(--success-color); --inactive-color: var(--gray-color);
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
            --present-color-val: 67, 170, 139; --neutral-color-val: 173, 181, 189;
            --present-color: rgb(var(--present-color-val)); --neutral-color: rgb(var(--neutral-color-val));
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px; }
        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; }
        header .container .navbar { display: flex; justify-content: space-between; align-items: center; height: 80px; }

        .page-header { padding: 1.8rem 0; margin: 1.5rem 0 1.5rem 0; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header .container {max-width: 1400px; margin: 0 auto; padding: 0 20px;}
        .page-header h1 { font-size: 1.7rem; margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }

        .admin-layout-container {
            display: flex;
            flex-direction: column; /* Default pro mobily */
            max-width: 1400px; margin: 0 auto; padding: 0 20px; gap: 1.5rem;
        }
        .admin-sidebar {
            flex-basis: 100%; /* Plná šířka na mobilech */
            background-color: var(--white);
            padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow);
            height: fit-content; /* Aby sa prispôsobil obsahu */
        }
        .admin-content {
            flex-grow: 1;
        }

        @media (min-width: 992px) { /* Pre tablety a väčšie */
            .admin-layout-container {
                flex-direction: row; /* Dvou-sloupcový layout */
            }
            .admin-sidebar {
                flex: 0 0 280px; /* Pevná šírka levého panelu */
            }
        }

        .sidebar-nav-list { list-style: none; padding: 0; }
        .sidebar-nav-list h3 { font-size: 1.1rem; margin-bottom: 1rem; color: var(--dark-color); padding-bottom: 0.7rem; border-bottom: 1px solid var(--light-gray); }
        .sidebar-nav-list li a {
            display: flex; align-items: center; gap: 0.7rem;
            padding: 0.8rem 1rem; text-decoration: none;
            color: var(--dark-color); border-radius: 6px;
            transition: var(--transition); font-weight: 500;
            margin-bottom: 0.3rem;
        }
        .sidebar-nav-list li a:hover, .sidebar-nav-list li a.active-view {
            background-color: rgba(var(--primary-color-rgb), 0.1);
            color: var(--primary-color);
        }
        .sidebar-nav-list li a .material-symbols-outlined { font-size: 1.3em; }


        .db-error-message, .success-message { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .db-error-message { background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        .success-message { background-color: rgba(76,175,80,0.1); color: var(--success-color); border-left-color: var(--success-color); }

        .content-panel { background-color: var(--white); padding: 1.5rem 1.8rem; border-radius: 8px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); margin-bottom: 2rem; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); }
        .panel-title { font-size: 1.3rem; color: var(--dark-color); margin:0; }
        .btn-primary { background-color: var(--primary-color); color: var(--white); border:none; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration:none; font-weight: 500; cursor:pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary:hover { background-color: var(--primary-dark); }
        .btn-edit-rfid { background-color: var(--info-color); color:white; border:none; padding: 0.5rem 0.9rem; border-radius: 5px; cursor:pointer; font-size:0.88rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.4rem; }
        .btn-edit-rfid .material-symbols-outlined {font-size:1.1em;}

        .btn-visual-toggle {
            position: relative; display: inline-flex; align-items: center;
            width: 130px; height: 30px; border-radius: 15px; border: none; cursor: pointer;
            padding: 0; overflow: hidden; transition: var(--transition);
            background-color: rgba(var(--neutral-color-val), 0.2); font-size: 0.8rem;
        }
        .btn-visual-toggle.is-active { background-color: rgba(var(--present-color-val), 0.2); }
        .btn-visual-toggle .toggle-knob {
            position: absolute; left: 3px; width: 24px; height: 24px;
            border-radius: 50%; background-color: var(--white);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: var(--transition); z-index: 2;
        }
        .btn-visual-toggle.is-active .toggle-knob { left: calc(100% - 27px); background-color: var(--white); }
        .btn-visual-toggle .toggle-text {
            position: absolute; width: 100%; text-align: center;
            font-weight: 500; transition: var(--transition); z-index: 1;
            color: var(--neutral-color); line-height: 30px;
        }
        .btn-visual-toggle.is-active .toggle-text { color: var(--present-color); }
        .btn-visual-toggle:hover { box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.1); }
        .btn-visual-toggle.is-active:hover { background-color: rgba(var(--present-color-val), 0.3); }
        .btn-visual-toggle:not(.is-active):hover { background-color: rgba(var(--neutral-color-val), 0.3); }

        .rfid-table-wrapper { overflow-x: auto; }
        .rfid-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .rfid-table th, .rfid-table td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid var(--light-gray); vertical-align: middle;}
        .rfid-table th { background-color: #f9fafb; font-weight: 600; color: var(--gray-color); white-space: nowrap; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; }
        .rfid-table tbody tr:hover { background-color: #f0f4ff; }
        .rfid-table td .status-active { background-color: rgba(var(--present-color-val),0.15); color: var(--present-color); } /* Použité len pre text, nie badge */
        .rfid-table td .status-inactive { background-color: rgba(var(--neutral-color-val),0.15); color: var(--neutral-color); } /* Použité len pre text, nie badge */
        .rfid-table .actions-cell form { display: inline-block; margin-left: 0.3rem; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.9rem; }
        .form-group input[type="text"], .form-group input[type="checkbox"], .form-group select {
            width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--light-gray);
            border-radius: 6px; font-family: inherit; font-size: 0.9rem;
        }
        .form-group input[type="checkbox"] { width: auto; margin-right: 0.5rem; vertical-align: middle;}
        .form-group input:focus, .form-group select:focus { outline:none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb),0.2); }
        .form-actions { margin-top: 1.5rem; text-align: right; }
        .hidden-section { display: none; } /* Na skrytie sekcií */


    </style>
</head>
<body>
    <?php require "../components/header-admin.php"; // Uistite sa, že header-admin.php je správne umiestnený a naštýlovaný ?>

    <main>
        <div class="page-header">
            <div class="container"> <!-- Používame .container z globálnych štýlov pre page-header -->
                <h1>Manage RFID Cards</h1>
                <p class="sub-heading">Add new cards, assign them to users, and manage their status.</p>
            </div>
        </div>

        <div class="admin-layout-container">
            <aside class="admin-sidebar">
                <ul class="sidebar-nav-list">
                    <h3>Card Management</h3>
                    <li>
                        <a href="admin-manage-rfid.php?view=all_cards" class="<?php if ($currentView == 'all_cards') echo 'active-view'; ?>">
                            <span aria-hidden="true" translate="no" class="material-symbols-outlined">badge</span> All RFID Cards
                        </a>
                    </li>
                    <li>
                        <a href="admin-manage-rfid.php?view=add_new" class="<?php if ($currentView == 'add_new') echo 'active-view'; ?>">
                            <span aria-hidden="true" translate="no" class="material-symbols-outlined">add_card</span> Add New Card
                        </a>
                    </li>
                    <h3>Settings</h3>
                    <li>
                        <a href="admin-manage-rfid.php?view=api_control" class="<?php if ($currentView == 'api_control') echo 'active-view'; ?>">
                            <span aria-hidden="true" translate="no" class="material-symbols-outlined">settings_remote</span> API Registration Control
                        </a>
                    </li>
                </ul>
            </aside>

            <div class="admin-content">
                <?php if ($dbErrorMessage): ?>
                    <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbErrorMessage); ?></div>
                <?php endif; ?>
                <?php if ($successMessage): ?>
                    <div class="success-message" role="alert"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>

                <!-- Sekcia API Control -->
                <section id="api-control-section" class="content-panel <?php if ($currentView !== 'api_control') echo 'hidden-section'; ?>" style="background-color: #fffde7; border-left: 4px solid #ffab00;">
                    <div class="panel-header" style="border-bottom: none; margin-bottom: 0.5rem;">
                        <h2 class="panel-title" style="font-size: 1.1rem;"><span aria-hidden="true" translate="no" class="material-symbols-outlined" style="vertical-align:bottom; margin-right:5px; color:var(--warning-color);">settings_remote</span>API Control: RFID Card Registration</h2>
                    </div>
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <p style="margin: 0; font-size: 0.9rem; color: #424242; flex-grow:1;">
                            Enable/disable the ability for external scripts (e.g., Python RFID reader) to add new cards via the <code>permission.php</code> or <code>add_rfid.php</code> API endpoints.
                        </p>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <button id="rfidApiToggleBtn"
                                    class="btn-visual-toggle <?php echo $rfidApiCurrentlyEnabled ? 'is-active' : ''; ?>"
                                    title="Click to <?php echo $rfidApiCurrentlyEnabled ? 'Disable' : 'Enable'; ?> API Registrations">
                                <span class="toggle-knob"></span>
                                <span class="toggle-text">
                                    <?php echo $rfidApiCurrentlyEnabled ? 'API Enabled' : 'API Disabled'; ?>
                                </span>
                            </button>
                            <small id="rfidApiStatusMsg" style="color:var(--gray-color); min-width:100px;"></small>
                        </div>
                    </div>
                    <p style="font-size:0.8rem; color:var(--gray-color); margin-top:0.7rem; padding-top:0.5rem; border-top:1px dashed #eee;">
                        <em>Note: This setting controls whether new, unrecognized RFID cards scanned by an external reader will be automatically added to the system (as inactive). It does <strong>not</strong> affect adding cards manually through the form on this admin page.</em>
                    </p>
                </section>

                <!-- Sekcia Add New RFID Card (Manual Entry) -->
                <!-- Sekcia Add New RFID Card (Manual Entry) - UPRAVENÁ -->
                <section id="add-new-section" class="content-panel <?php if ($currentView !== 'add_new') echo 'hidden-section'; ?>">
                    <div class="panel-header">
                        <h2 class="panel-title">Add New RFID Card (Manual Entry)</h2>
                    </div>
                    <form action="admin-manage-rfid.php?view=add_new" method="POST">
                        <input type="hidden" name="action" value="add_rfid">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="rfid_uid_form">RFID URL/UID <span style="color:red;">*</span></label>
                                <input type="text" id="rfid_uid_form" name="rfid_uid" placeholder="Scan or enter card UID" required>
                            </div>
                            <div class="form-group">
                                <label for="rfid_name_form">Card Name/Label (Optional)</label>
                                <input type="text" id="rfid_name_form" name="rfid_name" placeholder="e.g., John Doe - Main Card">
                            </div>
                            <div class="form-group">
                                <label for="rfid_card_type_form">Card Type <span style="color:red;">*</span></label>
                                <select id="rfid_card_type_form" name="rfid_card_type" required>
                                    <option value="Primary Access Card">Primary Access Card</option>
                                    <option value="Temporary Access Card">Temporary Access Card</option>
                                    <option value="Visitor Pass">Visitor Pass</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                             <div class="form-group">
                                <label for="assign_userID_form">Assign to User (Optional)</label>
                                <select id="assign_userID_form" name="assign_userID">
                                    <option value="">-- Not Assigned --</option>
                                    <?php foreach ($allUsers as $user): ?>
                                        <option value="<?php echo $user['userID']; ?>">
                                            <?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName'] . ' (' . $user['username'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- === NOVÝ TOGGLE BUTTON PRE "MAKE ACTIVE" === -->
                            <div class="form-group form-group-toggle">
                                <label for="is_active_toggle">Status:</label>
                                <!-- Skrytý input na odoslanie hodnoty 0 alebo 1 -->
                                <input type="hidden" name="is_active" id="is_active_hidden_input" value="1">
                                <button type="button" id="is_active_toggle"
                                        class="btn-visual-toggle is-active" 
                                        title="Click to Deactivate">
                                    <span class="toggle-knob"></span>
                                    <span class="toggle-text">Active</span>
                                </button>
                            </div>
                            <!-- === KONIEC NOVÉHO TOGGLE BUTTONU === -->
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary"><span aria-hidden="true" translate="no" class="material-symbols-outlined">add_card</span> Add RFID Card</button>
                        </div>
                    </form>
                </section>

                <!-- Sekcia All RFID Cards - UPRAVENÁ TABUĽKA -->
                <section id="all-cards-section" class="content-panel <?php if ($currentView !== 'all_cards') echo 'hidden-section'; ?>">
                    <div class="panel-header">
                        <h2 class="panel-title">All RFID Cards (<?php echo count($allRfids); ?>)</h2>
                    </div>
                    <div class="rfid-table-wrapper">
                        <table class="rfid-table">
                            <thead>
                                <tr>
                                    <th>DB ID</th>
                                    <th>RFID UID</th>
                                    <th>LABEL</th> 
                                    <th>TYPE</th>
                                    <th>ASSIGNED TO</th>
                                    <th>STATUS</th>
                                    <th>ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($allRfids)): ?>
                                    <?php foreach ($allRfids as $rfid): ?>
                                        <tr>
                                            <td><?php echo $rfid['RFID']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($rfid['rfid_uid']); // SPRÁVNE: RFID UID ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($rfid['rfid_name'] ?: 'N/A'); // SPRÁVNE: Label (názov karty) ?></td>
                                            <td><?php echo htmlspecialchars($rfid['card_type']); // SPRÁVNE: Typ karty ?></td>
                                            <td>
                                                <?php
                                                if ($rfid['userID'] && isset($rfid['firstName'])) { // Pridaná kontrola isset pre firstName
                                                    echo htmlspecialchars($rfid['firstName'] . ' ' . $rfid['lastName'] . ' (#' . $rfid['userID'] .')');
                                                } elseif ($rfid['userID'] && isset($rfid['username'])) { // Fallback na username ak meno nie je
                                                     echo htmlspecialchars($rfid['username'] . ' (#' . $rfid['userID'] .')');
                                                }
                                                else {
                                                    echo '<span style="color:var(--gray-color);"><em>Unassigned</em></span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <form action="admin-manage-rfid.php?view=all_cards" method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_rfid_status">
                                                <input type="hidden" name="rfid_id_toggle" value="<?php echo $rfid['RFID']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $rfid['is_active']; ?>">
                                                <button type="submit"
                                                        class="btn-visual-toggle <?php echo $rfid['is_active'] ? 'is-active' : ''; ?>"
                                                        title="Click to <?php echo $rfid['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                    <span class="toggle-knob"></span>
                                                    <span class="toggle-text">
                                                        <?php echo $rfid['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </button>
                                                </form>
                                            </td>
                                            <td class="actions-cell">
                                                <a href="admin-edit-rfid.php?rfidID=<?php echo $rfid['RFID']; ?>" class="btn-edit-rfid" title="Edit RFID Card">
                                                    <span aria-hidden="true" translate="no" class="material-symbols-outlined">credit_card_gear</span> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" style="text-align:center; padding: 1.5rem;">No RFID cards found in the system. Add one using the 'Add New Card' section.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            </div> <!-- .admin-content -->
        </div> <!-- .admin-layout-container -->
    </main>

    <?php require "../components/footer-admin.php"; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- RFID API TOGGLE SCRIPT (zostáva rovnaký) ---
        const rfidApiToggleButton = document.getElementById('rfidApiToggleBtn');
        const rfidApiStatusMsg = document.getElementById('rfidApiStatusMsg');

        if (rfidApiToggleButton) {
            rfidApiToggleButton.addEventListener('click', function() {
                const isActive = this.classList.contains('is-active');
                const newStateEnable = !isActive;
                rfidApiStatusMsg.textContent = 'Updating...';
                // ... (zvyšok AJAX kódu pre API toggle)
                const formData = new FormData();
                formData.append('action', 'toggle_rfid_api_status');
                formData.append('enable_api', newStateEnable ? 'true' : 'false');
                fetch('admin-manage-rfid.php', { method: 'POST', body: formData })
                .then(response => {
                    if (!response.ok) { return response.text().then(text => { throw new Error(`Server error: ${response.status} ${response.statusText}. Response: ${text.substring(0,200)}...`); }); }
                    return response.json().catch(parseError => { return response.text().then(text => { throw new Error(`Failed to parse JSON. Server responded with: ${text.substring(0,200)}...`); }); });
                })
                .then(data => {
                    if (data.status === 'success') {
                        const apiIsEnabledNow = data.newState === 'enabled';
                        this.classList.toggle('is-active', apiIsEnabledNow);
                        this.querySelector('.toggle-text').textContent = apiIsEnabledNow ? 'API Enabled' : 'API Disabled';
                        this.title = "Click to " + (apiIsEnabledNow ? 'Disable' : 'Enable') + " API Registrations";
                        rfidApiStatusMsg.textContent = 'Status updated!';
                        rfidApiStatusMsg.style.color = 'var(--success-color)';
                    } else {
                        rfidApiStatusMsg.textContent = 'App Error: ' + (data.message || 'Could not update.');
                        rfidApiStatusMsg.style.color = 'var(--danger-color)';
                    }
                })
                .catch(error => {
                    console.error('Error toggling RFID API status:', error);
                    rfidApiStatusMsg.textContent = error.message;
                    rfidApiStatusMsg.style.color = 'var(--danger-color)';
                })
                .finally(() => {
                    setTimeout(() => { rfidApiStatusMsg.textContent = ''; }, 4500);
                });
            });
        }

        // --- NOVÝ JAVASCRIPT PRE "MAKE ACTIVE" TOGGLE BUTTON VO FORMULÁRI ---
        const isActiveToggleBtn = document.getElementById('is_active_toggle');
        const isActiveHiddenInput = document.getElementById('is_active_hidden_input');

        if (isActiveToggleBtn && isActiveHiddenInput) {
            // Inicializácia na základe defaultnej hodnoty (ak je to potrebné, napr. pri editácii)
            // Pre nový záznam je defaultne '1' (Active)
            // const initialIsActive = isActiveHiddenInput.value === '1';
            // isActiveToggleBtn.classList.toggle('is-active', initialIsActive);
            // isActiveToggleBtn.querySelector('.toggle-text').textContent = initialIsActive ? 'Active' : 'Inactive';
            // isActiveToggleBtn.title = initialIsActive ? 'Click to Deactivate' : 'Click to Activate';

            isActiveToggleBtn.addEventListener('click', function() {
                const currentIsActive = this.classList.contains('is-active');
                const newIsActiveState = !currentIsActive;

                this.classList.toggle('is-active', newIsActiveState);
                this.querySelector('.toggle-text').textContent = newIsActiveState ? 'Active' : 'Inactive';
                this.title = newIsActiveState ? 'Click to Deactivate' : 'Click to Activate';
                isActiveHiddenInput.value = newIsActiveState ? '1' : '0';
            });
        }
    });
    </script>
</body>
</html>