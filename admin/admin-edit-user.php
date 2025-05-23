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

// --- HANDLE DELETE RFID CARD ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_rfid') {
    $rfid_pk_to_delete = filter_input(INPUT_POST, 'rfid_pk_id', FILTER_VALIDATE_INT);

    if ($rfid_pk_to_delete && $rfid_pk_to_delete == $rfidIDToEdit) {
        try {
            $stmtDelete = $pdo->prepare("DELETE FROM rfids WHERE RFID = :rfid_id");
            $stmtDelete->bindParam(':rfid_id', $rfid_pk_to_delete, PDO::PARAM_INT);

            if ($stmtDelete->execute()) {
                header("Location: admin-manage-rfid.php?success=RFID_deleted");
                exit;
            } else {
                $dbErrorMessage = "Failed to delete RFID card.";
            }
        } catch (PDOException $e) {
            $dbErrorMessage = "Database error deleting RFID card: " . $e->getMessage();
        }
    } else {
        $dbErrorMessage = "RFID ID mismatch during deletion. Operation aborted.";
    }
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
        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background-color: #f4f6f9;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding-top: 80px; /* Ponecháno pro odsazení obsahu od fixního headeru */
        }
        main { flex-grow: 1; /* padding-top: 80px; bylo zde, přesunuto do body */ }
        .container, .page-header .container { max-width: 1440px; margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px; }
        
        /* Styly specifické pro tuto stránku (formulář, zprávy atd.) */
        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        
        .db-error-message, .success-message { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .db-error-message { background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        .success-message { background-color: rgba(76,175,80,0.1); color: var(--success-color); border-left-color: var(--success-color); }

        .content-panel { background-color: var(--white); padding: 1.5rem 1.8rem; border-radius: 8px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); margin-bottom: 2rem; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); }
        .panel-title { font-size: 1.3rem; color: var(--dark-color); margin:0; }
        
        .btn-primary, .btn-secondary, .btn-danger {
            background-color: var(--primary-color); color: var(--white); border:none;
            padding: 0.7rem 1.5rem; border-radius: 6px; text-decoration:none;
            font-weight: 500; cursor:pointer; transition: var(--transition);
            display: inline-flex; align-items: center; gap: 0.5rem;
        }
        .btn-primary:hover { background-color: var(--primary-dark); }
        .btn-secondary { background-color: var(--gray-color); }
        .btn-secondary:hover { background-color: #5a6268; }
        .btn-danger { background-color: var(--danger-color); }
        .btn-danger:hover { background-color: #d32f2f; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem 1.8rem; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9rem; }
        .form-group input[type="text"], .form-group input[type="checkbox"], .form-group select {
            width: 100%; padding: 0.8rem 1rem; border: 1px solid #ccd0d5;
            border-radius: 6px; font-family: inherit; font-size: 0.95rem;
        }
        .form-group input[type="checkbox"] { width: auto; margin-right: 0.5rem; vertical-align: middle;}
        .form-group input:focus, .form-group select:focus { outline:none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb),0.2); }
        .form-actions { margin-top: 2rem; display:flex; justify-content:space-between; gap:1rem; }

        .toggle-switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc; transition: .4s; border-radius: 34px;
        }
        .toggle-slider:before {
            position: absolute; content: ""; height: 26px; width: 26px;
            left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%;
        }
        input:checked + .toggle-slider { background-color: var(--success-color); }
        input:checked + .toggle-slider:before { transform: translateX(26px); }
        .toggle-label { margin-left: 10px; vertical-align: middle; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0;
                width: 100%; height: 100%; overflow: auto;
                background-color: rgba(0,0,0,0.4); }
        .modal-content {
            background-color: #fefefe; margin: 15% auto; padding: 2rem;
            border: 1px solid #888; width: 90%; max-width: 500px;
            border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .modal-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; }
    </style>
</head>
<body>
    <?php require "../components/header-admin.php"; // Předpokládá, že header-admin.php obsahuje navigaci ?>

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
                         <div class="form-group" style="align-self: center;">
                            <label style="display: flex; align-items: center;">
                                <span class="toggle-switch">
                                    <input type="checkbox" id="is_active" name="is_active" value="1" <?php if($rfidToEdit['is_active']) echo 'checked'; ?>>
                                    <span class="toggle-slider"></span>
                                </span>
                                <span class="toggle-label">Card is Active</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <div>
                            <button type="button" class="btn-danger" onclick="confirmDelete()">
                                <span class="material-symbols-outlined">delete</span> Delete Card
                            </button>
                        </div>
                        <div style="display: flex; gap: 1rem;">
                            <a href="admin-manage-rfid.php" class="btn-secondary"><span class="material-symbols-outlined">arrow_back</span> Cancel</a>
                            <button type="submit" class="btn-primary"><span class="material-symbols-outlined">save</span> Update RFID Card</button>
                        </div>
                    </div>
                </form>

                <!-- Delete Confirmation Modal -->
                <div id="deleteModal" class="modal">
                    <div class="modal-content">
                        <h3>Confirm Deletion</h3>
                        <p>Are you sure you want to permanently delete this RFID card?</p>
                        <p><strong>Card UID:</strong> <?php echo htmlspecialchars($rfidToEdit['rfid_url']); ?></p>
                        <p>This action cannot be undone.</p>

                        <div class="modal-actions">
                            <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                            <form action="admin-edit-rfid.php?rfidID=<?php echo $rfidIDToEdit; ?>" method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="delete_rfid">
                                <input type="hidden" name="rfid_pk_id" value="<?php echo $rfidToEdit['RFID']; ?>">
                                <button type="submit" class="btn-danger">
                                    <span class="material-symbols-outlined">delete_forever</span> Delete Permanently
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
            <?php elseif(!$dbErrorMessage): ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> RFID Card with the specified ID could not be found.</div>
                <a href="admin-manage-rfid.php" class="btn-secondary" style="margin-top:1rem;"><span class="material-symbols-outlined">arrow_back</span> Back to RFID List</a>
            <?php endif; ?>
        </div>
    </main>

    <?php require "../components/footer-admin.php"; // Předpokládá, že footer-admin.php je relevantní ?>

    <script>
        // JavaScript pro modální okno potvrzení smazání
        function confirmDelete() {
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Zavření modálního okna kliknutím mimo něj
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>