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

require_once '../db.php'; 

$sessionAdminFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';

$dbErrorMessage = null;
$successMessage = null;
$rfidToEdit = null;
$allUsers = []; // For assigning cards

$rfidIDToEdit = filter_input(INPUT_GET, 'rfidID', FILTER_VALIDATE_INT);

if (!$rfidIDToEdit) {
    header("Location: admin-manage-rfid.php?error=No_rfid_specified");
    exit;
}

// --- HANDLE UPDATE RFID FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_rfid') {
    $rfid_pk_to_update = filter_input(INPUT_POST, 'rfid_pk_id', FILTER_VALIDATE_INT);

    if ($rfid_pk_to_update && $rfid_pk_to_update == $rfidIDToEdit) {
        $edit_rfid_url = trim(filter_input(INPUT_POST, 'rfid_url', FILTER_SANITIZE_SPECIAL_CHARS));
        $edit_rfid_name = trim(filter_input(INPUT_POST, 'rfid_name', FILTER_SANITIZE_SPECIAL_CHARS));
        $edit_card_type = filter_input(INPUT_POST, 'card_type', FILTER_SANITIZE_SPECIAL_CHARS);
        $edit_assign_userID = filter_input(INPUT_POST, 'assign_userID', FILTER_SANITIZE_SPECIAL_CHARS); // Will be 'none' or user ID
        $edit_is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($edit_rfid_url) || empty($edit_card_type)) {
            $dbErrorMessage = "RFID URL/UID and Card Type are required.";
        } else {
            try {
                // Check if the new rfid_url (if changed) conflicts with another card
                $stmtCheckUrl = $pdo->prepare("SELECT RFID FROM rfids WHERE rfid_url = :rfid_url AND RFID != :current_rfid_id");
                $stmtCheckUrl->bindParam(':rfid_url', $edit_rfid_url);
                $stmtCheckUrl->bindParam(':current_rfid_id', $rfid_pk_to_update, PDO::PARAM_INT);
                $stmtCheckUrl->execute();

                if ($stmtCheckUrl->fetch()) {
                    $dbErrorMessage = "The RFID URL/UID '" . htmlspecialchars($edit_rfid_url) . "' is already in use by another card.";
                } else {
                    $assignedUserIDForSQL = ($edit_assign_userID === 'none' || empty($edit_assign_userID)) ? null : (int)$edit_assign_userID;

                    $sqlUpdate = "UPDATE rfids SET 
                                    rfid_url = :rfid_url, 
                                    name = :name, 
                                    card_type = :card_type, 
                                    userID = :userID, 
                                    is_active = :is_active 
                                  WHERE RFID = :rfid_pk_id";
                    $stmtUpdate = $pdo->prepare($sqlUpdate);
                    $stmtUpdate->bindParam(':rfid_url', $edit_rfid_url);
                    $stmtUpdate->bindParam(':name', $edit_rfid_name);
                    $stmtUpdate->bindParam(':card_type', $edit_card_type);
                    $stmtUpdate->bindParam(':userID', $assignedUserIDForSQL, $assignedUserIDForSQL ? PDO::PARAM_INT : PDO::PARAM_NULL);
                    $stmtUpdate->bindParam(':is_active', $edit_is_active, PDO::PARAM_INT);
                    $stmtUpdate->bindParam(':rfid_pk_id', $rfid_pk_to_update, PDO::PARAM_INT);

                    if ($stmtUpdate->execute()) {
                        $successMessage = "RFID card details updated successfully!";
                    } else {
                        $dbErrorMessage = "Failed to update RFID card.";
                    }
                }
            } catch (PDOException $e) {
                $dbErrorMessage = "Database error updating RFID card: " . $e->getMessage();
            }
        }
    } else {
        $dbErrorMessage = "RFID ID mismatch during update. Operation aborted.";
    }
}


// --- DATA FETCHING for the RFID card to be edited ---
if (isset($pdo) && $pdo instanceof PDO && $rfidIDToEdit) {
    try {
        $stmtRfid = $pdo->prepare(
            "SELECT RFID, rfid_url, name, card_type, is_active, userID 
             FROM rfids 
             WHERE RFID = :rfidID_param"
        );
        $stmtRfid->bindParam(':rfidID_param', $rfidIDToEdit, PDO::PARAM_INT);
        $stmtRfid->execute();
        $rfidToEdit = $stmtRfid->fetch(PDO::FETCH_ASSOC);

        if (!$rfidToEdit) {
            $dbErrorMessage = "RFID Card with ID " . htmlspecialchars($rfidIDToEdit) . " not found.";
        } else {
            // Fetch all users for the "Assign to User" dropdown
            $stmtAllUsers = $pdo->query("SELECT userID, firstName, lastName, username FROM users ORDER BY lastName, firstName");
            $allUsers = $stmtAllUsers->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error fetching RFID card data: " . $e->getMessage();
        $rfidToEdit = null; 
    }
} elseif (!$rfidIDToEdit && !$dbErrorMessage) {
    $dbErrorMessage = "No RFID Card specified for editing.";
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../imgs/logo.png" type="image/x-icon"> 
    <title>Edit RFID Card - <?php echo $rfidToEdit ? htmlspecialchars($rfidToEdit['rfid_url']) : 'Card Not Found'; ?> - Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reuse styles from admin-manage-employees.php or global admin CSS */
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --primary-color-rgb: 67, 97, 238;
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --warning-color: #FF9800; --danger-color: #F44336;
            --info-color: #2196F3; 
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px; }
        .container, .page-header .container { max-width: 1440px; margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px; }
        header { /* ... Your header styles ... */ }
        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        .db-error-message, .success-message { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .db-error-message { background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        .success-message { background-color: rgba(76,175,80,0.1); color: var(--success-color); border-left-color: var(--success-color); }

        .content-panel { background-color: var(--white); padding: 1.5rem 1.8rem; border-radius: 8px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); margin-bottom: 2rem; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); }
        .panel-title { font-size: 1.3rem; color: var(--dark-color); margin:0; }
        .btn-primary, .btn-secondary { background-color: var(--primary-color); color: var(--white); border:none; padding: 0.7rem 1.5rem; border-radius: 6px; text-decoration:none; font-weight: 500; cursor:pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary:hover { background-color: var(--primary-dark); }
        .btn-secondary { background-color: var(--gray-color); }
        .btn-secondary:hover { background-color: #5a6268; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem 1.8rem; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9rem; }
        .form-group input[type="text"], .form-group input[type="checkbox"], .form-group select {
            width: 100%; padding: 0.8rem 1rem; border: 1px solid #ccd0d5; 
            border-radius: 6px; font-family: inherit; font-size: 0.95rem;
        }
        .form-group input[type="checkbox"] { width: auto; margin-right: 0.5rem; vertical-align: middle;}
        .form-group input:focus, .form-group select:focus { outline:none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb),0.2); }
        .form-actions { margin-top: 2rem; display:flex; justify-content:flex-end; gap:1rem; }
        
        footer { /* ... Your full footer styles ... */ }
    </style>
</head>
<body>
    <?php require "../components/header-admin.php"; ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>Edit RFID Card</h1>
                <p class="sub-heading">
                    <?php echo $rfidToEdit ? 'Card UID: ' . htmlspecialchars($rfidToEdit['rfid_url']) : 'RFID Card Management'; ?>
                </p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if ($dbErrorMessage): ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $dbErrorMessage; ?></div>
            <?php endif; ?>
            <?php if ($successMessage): ?>
                <div class="success-message" role="alert"><i class="fas fa-check-circle"></i> <?php echo $successMessage; ?></div>
            <?php endif; ?>

            <?php if ($rfidToEdit): ?>
            <section class="content-panel">
                <form action="admin-edit-rfid.php?rfidID=<?php echo $rfidIDToEdit; ?>" method="POST">
                    <input type="hidden" name="action" value="update_rfid">
                    <input type="hidden" name="rfid_pk_id" value="<?php echo $rfidToEdit['RFID']; ?>">
                    
                    <div class="panel-header">
                        <h2 class="panel-title">Card Details (DB ID: <?php echo $rfidToEdit['RFID']; ?>)</h2>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="rfid_url">RFID URL/UID <span style="color:red;">*</span></label>
                            <input type="text" id="rfid_url" name="rfid_url" value="<?php echo htmlspecialchars($rfidToEdit['rfid_url']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="rfid_name">Card Name/Label (Optional)</label>
                            <input type="text" id="rfid_name" name="rfid_name" value="<?php echo htmlspecialchars($rfidToEdit['name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="card_type">Card Type <span style="color:red;">*</span></label>
                            <select id="card_type" name="card_type" required>
                                <option value="Primary Access Card" <?php if($rfidToEdit['card_type'] == 'Primary Access Card') echo 'selected'; ?>>Primary Access Card</option>
                                <option value="Temporary Access Card" <?php if($rfidToEdit['card_type'] == 'Temporary Access Card') echo 'selected'; ?>>Temporary Access Card</option>
                                <option value="Visitor Pass" <?php if($rfidToEdit['card_type'] == 'Visitor Pass') echo 'selected'; ?>>Visitor Pass</option>
                                <option value="Other" <?php if($rfidToEdit['card_type'] == 'Other') echo 'selected'; ?>>Other</option>
                            </select>
                        </div>
                         <div class="form-group">
                            <label for="assign_userID">Assign to User</label>
                            <select id="assign_userID" name="assign_userID">
                                <option value="none" <?php if (empty($rfidToEdit['userID'])) echo 'selected'; ?>>-- Unassigned --</option>
                                <?php foreach ($allUsers as $user): ?>
                                    <option value="<?php echo $user['userID']; ?>" <?php if ($rfidToEdit['userID'] == $user['userID']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName'] . ' (' . $user['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="form-group" style="align-self: center; display:flex; align-items:center;">
                            <input type="checkbox" id="is_active" name="is_active" value="1" <?php if($rfidToEdit['is_active']) echo 'checked'; ?> style="width:auto;">
                            <label for="is_active" style="display:inline; font-weight:normal; margin-bottom:0; margin-left:0.3rem;">Card is Active</label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="admin-manage-rfid.php" class="btn-secondary"><span class="material-symbols-outlined">arrow_back</span> Cancel / Back to List</a>
                        <button type="submit" class="btn-primary"><span class="material-symbols-outlined">save</span> Update RFID Card</button>
                    </div>
                </form>
            </section>
            <?php elseif(!$dbErrorMessage): ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> RFID Card with the specified ID could not be found.</div>
                <a href="admin-manage-rfid.php" class="btn-secondary" style="margin-top:1rem;"><span class="material-symbols-outlined">arrow_back</span> Back to RFID List</a>
            <?php endif; ?>
        </div>
    </main>

    <?php require "../components/footer-admin.php"; ?>

    <script>
        // JavaScript for mobile menu (ensure this is in your header or a global script)
    </script>
</body>
</html>