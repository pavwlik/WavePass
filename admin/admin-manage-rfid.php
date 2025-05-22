<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Restrict Access: Ensure only admin can access
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    header("location: ../login.php"); 
    exit;
}

require_once '../db.php'; // Path relative to admin/ folder

$sessionAdminFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';

$dbErrorMessage = null;
$successMessage = null;
$allRfids = [];
$allUsers = []; // For assigning cards in "Add New" form

// --- ACTION HANDLING ---

// HANDLE ADD NEW RFID CARD
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_rfid') {
    $rfid_url_new = trim(filter_input(INPUT_POST, 'rfid_url', FILTER_SANITIZE_SPECIAL_CHARS));
    $rfid_name_new = trim(filter_input(INPUT_POST, 'rfid_name', FILTER_SANITIZE_SPECIAL_CHARS));
    // Assuming your card_type ENUM matches these values exactly
    $rfid_card_type_new = filter_input(INPUT_POST, 'rfid_card_type', FILTER_SANITIZE_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE);
    $rfid_assign_user_new = filter_input(INPUT_POST, 'assign_userID', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    $rfid_is_active_new = isset($_POST['is_active']) ? 1 : 0;
    // ...
    $stmtInsert->bindParam(':is_active', $rfid_is_active_new, PDO::PARAM_INT);

    if (empty($rfid_url_new)) { // Card type can have a default, name is optional
        $dbErrorMessage = "RFID URL/UID is required.";
    } else {
        try {
            $stmtCheck = $pdo->prepare("SELECT RFID FROM rfids WHERE rfid_url = :rfid_url");
            $stmtCheck->bindParam(':rfid_url', $rfid_url_new, PDO::PARAM_STR);
            $stmtCheck->execute();
            if ($stmtCheck->fetch()) {
                $dbErrorMessage = "This RFID URL/UID ('" . htmlspecialchars($rfid_url_new) . "') already exists.";
            } else {
                $sqlInsert = "INSERT INTO rfids (rfid_url, name, card_type, userID, is_active) 
                              VALUES (:rfid_url, :name, :card_type, :userID, :is_active)";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->bindParam(':rfid_url', $rfid_url_new);
                $stmtInsert->bindParam(':name', $rfid_name_new);
                $stmtInsert->bindParam(':card_type', $rfid_card_type_new); // Uses default if not provided and column allows
                $stmtInsert->bindParam(':userID', $rfid_assign_user_new, $rfid_assign_user_new ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmtInsert->bindParam(':is_active', $rfid_is_active_new, PDO::PARAM_INT);

                if ($stmtInsert->execute()) {
                    $successMessage = "New RFID card added successfully!";
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
    $current_status = filter_input(INPUT_POST, 'current_status', FILTER_VALIDATE_INT); // 0 or 1

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
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // Fetch all RFID cards and join with users table to get assigned user's name
        $stmtAllRfids = $pdo->query(
            "SELECT r.RFID, r.rfid_url, r.name as rfid_name, r.card_type, r.is_active, r.userID,
                    u.firstName, u.lastName, u.username
             FROM rfids r
             LEFT JOIN users u ON r.userID = u.userID
             ORDER BY r.RFID DESC"
        );
        $allRfids = $stmtAllRfids->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all users for the "Assign to User" dropdown in forms
        $stmtAllUsers = $pdo->query("SELECT userID, firstName, lastName, username FROM users ORDER BY lastName, firstName");
        $allUsers = $stmtAllUsers->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error fetching RFID data: " . $e->getMessage();
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
    <title>Manage RFID Cards - Admin - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* === BASIC STYLES (from admin-dashboard.php, ensure paths are relative to admin/ if needed) === */
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --primary-color-rgb: 67, 97, 238;
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --warning-color: #FF9800; --danger-color: #F44336;
            --info-color: #2196F3; 
            --active-color: var(--success-color); --inactive-color: var(--gray-color);
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
            --present-color-val: 67, 170, 139; /* For active badge */
            --neutral-color-val: 173, 181, 189; /* For inactive badge */
            --present-color: rgb(var(--present-color-val));
            --neutral-color: rgb(var(--neutral-color-val));
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px; }
        .container, .page-header .container { max-width: 1440px; margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px; }
        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; }
        header .container .navbar { display: flex; justify-content: space-between; align-items: center; height: 80px; }
        /* Assume full header/nav CSS is in the included header-admin-panel.php */

        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
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
        /* Toggle Button Styles */
.btn-visual-toggle {
    position: relative;
    display: inline-flex;
    align-items: center;
    width: 80px;
    height: 28px;
    border-radius: 15px;
    border: none;
    cursor: pointer;
    padding: 0;
    overflow: hidden;
    transition: var(--transition);
    background-color: rgba(var(--neutral-color-val), 0.2);
}

.btn-visual-toggle.is-active {
    background-color: rgba(var(--present-color-val), 0.2);
}

.btn-visual-toggle .toggle-knob {
    position: absolute;
    left: 3px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background-color: var(--white);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: var(--transition);
    z-index: 2;
}

.btn-visual-toggle.is-active .toggle-knob {
    left: calc(100% - 25px);
    background-color: var(--white);
}

.btn-visual-toggle .toggle-text {
    position: absolute;
    width: 100%;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 500;
    transition: var(--transition);
    z-index: 1;
    color: var(--neutral-color);
}

.btn-visual-toggle.is-active .toggle-text {
    color: var(--present-color);
    padding-right: 25px;
}

.btn-visual-toggle:not(.is-active) .toggle-text {
    padding-left: 25px;
}

/* Hover effects */
.btn-visual-toggle:hover {
    box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.1);
}

.btn-visual-toggle.is-active:hover {
    background-color: rgba(var(--present-color-val), 0.3);
}

.btn-visual-toggle:not(.is-active):hover {
    background-color: rgba(var(--neutral-color-val), 0.3);
}

        .rfid-table-wrapper { overflow-x: auto; }
        .rfid-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .rfid-table th, .rfid-table td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid var(--light-gray); vertical-align: middle;}
        .rfid-table th { background-color: #f9fafb; font-weight: 600; color: var(--gray-color); white-space: nowrap; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; }
        .rfid-table tbody tr:hover { background-color: #f0f4ff; }
        .rfid-table td .status-badge { padding: 0.25rem 0.7rem; border-radius: 15px; font-size: 0.78rem; font-weight: 500; text-transform: capitalize; display:inline-flex; align-items:center; gap:0.3rem; }
        .rfid-table td .status-active { background-color: rgba(var(--present-color-val),0.15); color: var(--present-color); }
        .rfid-table td .status-inactive { background-color: rgba(var(--neutral-color-val),0.15); color: var(--neutral-color); }
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
        
        /* Footer styles */
        footer { /* ... Your full footer styles ... */ }
    </style>
</head>
<body>
    <?php require "../components/header-admin.php"; ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>Manage RFID Cards</h1>
                <p class="sub-heading">Add new cards, assign them to users, and manage their status.</p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if ($dbErrorMessage): ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $dbErrorMessage; ?></div>
            <?php endif; ?>
            <?php if ($successMessage): ?>
                <div class="success-message" role="alert"><i class="fas fa-check-circle"></i> <?php echo $successMessage; ?></div>
            <?php endif; ?>

            <section class="content-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Add New RFID Card</h2>
                </div>
                <form action="admin-manage-rfid.php" method="POST">
                    <input type="hidden" name="action" value="add_rfid">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="rfid_url">RFID URL/UID <span style="color:red;">*</span></label>
                            <input type="text" id="rfid_url" name="rfid_url" placeholder="Scan or enter card UID" required>
                        </div>
                        <div class="form-group">
                            <label for="rfid_name">Card Name/Label (Optional)</label>
                            <input type="text" id="rfid_name" name="rfid_name" placeholder="e.g., John Doe - Main Card">
                        </div>
                        <div class="form-group">
                            <label for="rfid_card_type">Card Type <span style="color:red;">*</span></label>
                            <select id="rfid_card_type" name="rfid_card_type" required>
                                <option value="Primary Access Card">Primary Access Card</option>
                                <option value="Temporary Access Card">Temporary Access Card</option>
                                <option value="Visitor Pass">Visitor Pass</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                         <div class="form-group">
                            <label for="assign_userID">Assign to User (Optional)</label>
                            <select id="assign_userID" name="assign_userID">
                                <option value="">-- Not Assigned --</option>
                                <?php foreach ($allUsers as $user): ?>
                                    <option value="<?php echo $user['userID']; ?>">
                                        <?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName'] . ' (' . $user['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="form-group" style="align-self: center; display:flex; align-items:center;">
                            <input type="checkbox" id="is_active" name="is_active" value="1" checked style="width:auto;">
                            <label for="is_active" style="display:inline; font-weight:normal; margin-bottom:0; margin-left:0.3rem;">Make Active</label>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary"><span class="material-symbols-outlined">add_card</span> Add RFID Card</button>
                    </div>
                </form>
            </section>

            <section class="content-panel">
                <div class="panel-header">
                    <h2 class="panel-title">All RFID Cards (<?php echo count($allRfids); ?>)</h2>
                </div>
                <div class="rfid-table-wrapper">
                    <table class="rfid-table">
                        <thead>
                            <tr>
                                <th>DB ID</th>
                                <th>RFID UID</th>
                                <th>Label</th>
                                <th>Type</th>
                                <th>Assigned To</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($allRfids)): ?>
                                <?php foreach ($allRfids as $rfid): ?>
                                    <tr>
                                        <td><?php echo $rfid['RFID']; ?></td>
                                        <td><img src="../imgs/wavepass_card.png" alt="Card Icon" style="width:20px; height:auto; margin-right:8px; vertical-align:middle;"><?php echo htmlspecialchars($rfid['rfid_url']); ?></td>
                                        <td><?php echo htmlspecialchars($rfid['rfid_name'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($rfid['card_type']); ?></td>
                                        <td>
                                            <?php 
                                            if ($rfid['userID']) {
                                                echo htmlspecialchars($rfid['firstName'] . ' ' . $rfid['lastName'] . ' (#' . $rfid['userID'] .')');
                                            } else {
                                                echo '<span style="color:var(--gray-color);"><em>Unassigned</em></span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <form action="admin-manage-rfid.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle_rfid_status">
                                            <input type="hidden" name="rfid_id_toggle" value="<?php echo $rfid['RFID']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $rfid['is_active']; ?>">
                                            <button type="submit" 
                                                    class="btn-visual-toggle <?php echo $rfid['is_active'] ? 'is-active' : 'is-inactive'; ?>"
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
                                                <span class="material-symbols-outlined">credit_card_gear</span> Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" style="text-align:center; padding: 1.5rem;">No RFID cards found in the system.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <?php require "../components/footer-admin.php"; ?>

    <script>
        // JavaScript for mobile menu (ensure this is in your header or a global script)
    </script>
</body>
</html>