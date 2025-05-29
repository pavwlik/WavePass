<?php
// ... (začiatok PHP kódu až po definíciu $userIdToEdit) ...
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Restrict Access
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true ||
    !isset($_SESSION["role"]) || strtolower($_SESSION["role"]) !== 'admin') {
    header("location: ../login.php");
    exit;
}

require_once '../db.php';

$sessionAdminFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$sessionAdminUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
$pathPrefix = "../";

$dbErrorMessage = null;
$successMessage = null;
$userToEdit = null; // Inicializácia
$allUnassignedActiveRfids = [];

$userIdToEdit = filter_input(INPUT_GET, 'userID', FILTER_VALIDATE_INT);

if (!$userIdToEdit) {
    $_SESSION['user_operation_status'] = ['type' => 'error', 'text' => 'Invalid or missing User ID for editing.'];
    header("location: admin-manage-users.php?view=all_users");
    exit;
}

// --- HANDLE FORM SUBMISSION (UPDATE USER) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_user' && isset($_POST['user_id_to_update'])) {
    // ... (celá vaša logika pre spracovanie POST requestu zostáva rovnaká) ...
    // Dôležité je, že ak tu nastane $dbErrorMessage, tak $userToEdit zostane null
    // a musíme ho potom naplniť z $_POST pre zobrazenie formulára.
    if ((int)$_POST['user_id_to_update'] !== $userIdToEdit) {
        $dbErrorMessage = "Form submission user ID mismatch.";
    } else {
        $updated_username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS));
        $updated_email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));
        $updated_firstName = trim(filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_SPECIAL_CHARS));
        $updated_lastName = trim(filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_SPECIAL_CHARS));
        $updated_phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS));
        $updated_roleID = filter_input(INPUT_POST, 'roleID', FILTER_SANITIZE_SPECIAL_CHARS);
        $new_password = $_POST['new_password'] ?? null;
        $confirm_password = $_POST['confirm_password'] ?? null;
        $assigned_rfid_id = filter_input(INPUT_POST, 'assigned_rfid_id', FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);


        if (empty($updated_username) || empty($updated_email) || empty($updated_firstName) || empty($updated_lastName) || empty($updated_roleID)) {
            $dbErrorMessage = "Username, Email, First Name, Last Name, and Role are required.";
        } elseif (!in_array($updated_roleID, ['employee', 'admin'])) {
            $dbErrorMessage = "Invalid role selected.";
        } elseif (!empty($new_password) && $new_password !== $confirm_password) {
            $dbErrorMessage = "New passwords do not match.";
        } elseif (strlen($new_password) > 0 && strlen($new_password) < 6) { 
            $dbErrorMessage = "New password must be at least 6 characters long.";
        }


        if (!$dbErrorMessage) {
            try {
                $stmtCheckConflict = $pdo->prepare("SELECT userID FROM users WHERE (username = :username OR email = :email) AND userID != :currentUserID");
                $stmtCheckConflict->execute([ // Použitie poľa pri execute
                    ':username' => $updated_username,
                    ':email' => $updated_email,
                    ':currentUserID' => $userIdToEdit
                ]);
                if ($stmtCheckConflict->fetch()) {
                    $dbErrorMessage = "The updated username or email already exists for another user.";
                } else {
                    $pdo->beginTransaction();

                    $sqlSetParts = [];
                    $paramsToBind = []; // Parameter array for execute

                    $sqlSetParts[] = "username = :username"; $paramsToBind[':username'] = $updated_username;
                    $sqlSetParts[] = "email = :email"; $paramsToBind[':email'] = $updated_email;
                    $sqlSetParts[] = "firstName = :firstName"; $paramsToBind[':firstName'] = $updated_firstName;
                    $sqlSetParts[] = "lastName = :lastName"; $paramsToBind[':lastName'] = $updated_lastName;
                    $sqlSetParts[] = "phone = :phone"; $paramsToBind[':phone'] = $updated_phone ?: null;
                    $sqlSetParts[] = "roleID = :roleID"; $paramsToBind[':roleID'] = $updated_roleID;

                    if (!empty($new_password)) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $sqlSetParts[] = "password = :password";
                        $paramsToBind[':password'] = $hashed_password;
                    }
                    $paramsToBind[':userID_where'] = $userIdToEdit; // Pre WHERE klauzulu

                    $sqlUpdateUser = "UPDATE users SET " . implode(", ", $sqlSetParts) . " WHERE userID = :userID_where";
                    $stmtUpdateUser = $pdo->prepare($sqlUpdateUser);

                    if ($stmtUpdateUser->execute($paramsToBind)) { // Odovzdanie parametrov tu
                        $stmtUnassignOld = $pdo->prepare("UPDATE rfids SET userID = NULL WHERE userID = :userID_unassign");
                        $stmtUnassignOld->execute([':userID_unassign' => $userIdToEdit]);

                        if ($assigned_rfid_id) {
                            $stmtCheckNewRfid = $pdo->prepare("SELECT userID FROM rfids WHERE RFID = :rfid_id_check");
                            $stmtCheckNewRfid->execute([':rfid_id_check' => $assigned_rfid_id]);
                            $rfidOwner = $stmtCheckNewRfid->fetchColumn();

                            if ($rfidOwner === null || $rfidOwner == $userIdToEdit) {
                                $stmtAssignNew = $pdo->prepare("UPDATE rfids SET userID = :userID_assign, is_active = 1 WHERE RFID = :rfid_id_assign");
                                $stmtAssignNew->execute([
                                    ':userID_assign' => $userIdToEdit,
                                    ':rfid_id_assign' => $assigned_rfid_id
                                ]);
                            } else {
                                $pdo->rollBack();
                                $dbErrorMessage = "Selected RFID card is already assigned to another user. Changes rolled back.";
                                goto end_update_processing;
                            }
                        }
                        $pdo->commit();
                        $_SESSION['user_operation_status'] = ['type' => 'success', 'text' => "User '" . htmlspecialchars($updated_username) . "' updated successfully!"];
                        header("location: admin-manage-users.php?view=all_users");
                        exit;
                    } else {
                        $pdo->rollBack();
                        $dbErrorMessage = "Failed to update user.";
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $dbErrorMessage = "Database error updating user: " . $e->getMessage();
                error_log("Admin Edit User - DB Update Error for userID $userIdToEdit: " . $e->getMessage());
            }
            end_update_processing:;
        }
    }
}


// --- LOAD USER DATA FOR EDITING ---
// Načítame dáta, len ak nejde o POST request, ktorý už nastavil chybu
// (pretože ak POST zlyhal, chceme zobraziť dáta z POSTu, nie prepísať dátami z DB)
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !$dbErrorMessage) {
    if (isset($pdo)) {
        try {
            $stmtLoadUser = $pdo->prepare(
                "SELECT u.userID, u.username, u.email, u.firstName, u.lastName, u.phone, u.roleID, u.profile_photo, r.RFID as assigned_rfid_pk_id
                 FROM users u
                 LEFT JOIN rfids r ON u.userID = r.userID
                 WHERE u.userID = :userID_load"
            );
            $stmtLoadUser->bindParam(':userID_load', $userIdToEdit, PDO::PARAM_INT);
            $stmtLoadUser->execute();
            $userToEdit = $stmtLoadUser->fetch(PDO::FETCH_ASSOC); // Tu sa nastavuje $userToEdit

            if (!$userToEdit) {
                // Ak používateľ nebol nájdený pri GET requeste
                $_SESSION['user_operation_status'] = ['type' => 'error', 'text' => "User with ID $userIdToEdit not found."];
                header("location: admin-manage-users.php?view=all_users");
                exit;
            }

            // Načítanie voľných RFID kariet
            $stmtUnassignedRfids = $pdo->prepare(
                "SELECT RFID, rfid_uid, name FROM rfids
                 WHERE (userID IS NULL AND is_active = 1) OR userID = :current_user_id_for_rfid
                 ORDER BY rfid_uid"
            );
            $stmtUnassignedRfids->bindParam(':current_user_id_for_rfid', $userIdToEdit, PDO::PARAM_INT);
            $stmtUnassignedRfids->execute();
            $allUnassignedActiveRfids = $stmtUnassignedRfids->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $dbErrorMessage = "Database Error loading user for editing: " . $e->getMessage();
            error_log("Admin Edit User - DB Load Error for userID $userIdToEdit: " . $e->getMessage());
            $userToEdit = null; // Dôležité, ak načítanie zlyhá
        }
    } elseif (!isset($pdo)) {
         $dbErrorMessage = "Database connection is not available.";
         $userToEdit = null;
    }
}

// Ak POST zlyhal a $userToEdit nie je z DB, naplníme ho z POST pre formulár
if ($_SERVER["REQUEST_METHOD"] == "POST" && $dbErrorMessage && empty($userToEdit['userID'])) {
    $userToEdit = [
        'username' => $_POST['username'] ?? '',
        'email' => $_POST['email'] ?? '',
        'firstName' => $_POST['firstName'] ?? '',
        'lastName' => $_POST['lastName'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'roleID' => $_POST['roleID'] ?? 'employee',
        'assigned_rfid_pk_id' => $_POST['assigned_rfid_id'] ?? null,
        'profile_photo' => null // Predpokladáme, že pri neúspešnom POSTe sa fotka nemení
    ];
}

$currentPage = "admin-manage-users.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?php echo htmlspecialchars($pathPrefix); ?>imgs/logo.png" type="image/x-icon">
    <title>Edit User - <?php echo $userToEdit && isset($userToEdit['username']) ? htmlspecialchars($userToEdit['username']) : 'N/A'; ?> - Admin - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ... (Vložte sem vaše existujúce CSS štýly) ... */
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
        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; height: 80px; }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 20px; }

        .page-header { padding: 1.5rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header .container {max-width: 100%; padding: 0;} /* Aby sa header roztiahol */
        .page-header h1 { font-size: 1.7rem; margin: 0; text-align: center;}

        .db-error-message, .success-message { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .db-error-message { background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        /* .success-message sa tu nepoužíva, lebo redirectujeme */

        .content-panel { background-color: var(--white); padding: 2rem 2.5rem; border-radius: 10px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); margin-bottom: 2rem; }
        .panel-title { font-size: 1.4rem; color: var(--dark-color); margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom:1px solid var(--light-gray); }

        .form-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 600px) { .form-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem 1.5rem; } }

        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.9rem; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="tel"],
        .form-group select {
            width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--light-gray);
            border-radius: 6px; font-family: inherit; font-size: 0.9rem;
        }
        .form-group input:focus, .form-group select:focus { outline:none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb),0.2); }
        .form-actions { margin-top: 2rem; display: flex; justify-content: flex-start; gap: 10px; }
        .btn-submit, .btn-cancel { padding: 0.7rem 1.5rem; border-radius: 6px; text-decoration:none; font-weight: 500; cursor:pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-size:0.95rem; }
        .btn-submit { background-color: var(--primary-color); color: var(--white); border:none; }
        .btn-submit:hover { background-color: var(--primary-dark); }
        .btn-cancel { background-color: var(--gray-color); color: var(--white); border:none; }
        .btn-cancel:hover { background-color: #5a6268; }
        .password-note { font-size: 0.8rem; color: var(--gray-color); margin-top: 0.3rem; }

        footer { background-color: var(--dark-color); color: var(--white); padding: 2rem 0; margin-top: auto; text-align: center; }
        footer p { margin: 0; font-size: 0.9rem;}
        footer a { color: rgba(255,255,255,0.8); text-decoration:none;}
        footer a:hover { color:var(--white); }
    </style>
</head>
<body>
    <?php require_once $pathPrefix . "components/header-admin.php"; ?>
    <main>
        <div class="page-header">
            <div class="container">
                <h1>Edit User</h1>
            </div>
        </div>

        <div class="container">
            <?php if ($dbErrorMessage): ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbErrorMessage); ?></div>
            <?php endif; ?>

            <?php if ($userToEdit): // Zmenené z $messageToEdit ?>
            <section class="content-panel">
                <h2 class="panel-title">Editing: <?php echo htmlspecialchars(($userToEdit['firstName'] ?? '') . ' ' . ($userToEdit['lastName'] ?? '') . ' (@' . ($userToEdit['username'] ?? 'N/A') . ')'); ?></h2>
                <form action="admin-edit-user.php?userID=<?php echo $userIdToEdit; ?>" method="POST">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id_to_update" value="<?php echo $userIdToEdit; ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username_edit">Username <span style="color:red;">*</span></label>
                            <input type="text" id="username_edit" name="username" value="<?php echo htmlspecialchars($userToEdit['username'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email_edit">Email <span style="color:red;">*</span></label>
                            <input type="email" id="email_edit" name="email" value="<?php echo htmlspecialchars($userToEdit['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="firstName_edit">First Name <span style="color:red;">*</span></label>
                            <input type="text" id="firstName_edit" name="firstName" value="<?php echo htmlspecialchars($userToEdit['firstName'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName_edit">Last Name <span style="color:red;">*</span></label>
                            <input type="text" id="lastName_edit" name="lastName" value="<?php echo htmlspecialchars($userToEdit['lastName'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone_edit">Phone</label>
                            <input type="tel" id="phone_edit" name="phone" value="<?php echo htmlspecialchars($userToEdit['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="roleID_edit">Role <span style="color:red;">*</span></label>
                            <select id="roleID_edit" name="roleID" required>
                                <option value="employee" <?php if (($userToEdit['roleID'] ?? '') == 'employee') echo 'selected'; ?>>Employee</option>
                                <option value="admin" <?php if (($userToEdit['roleID'] ?? '') == 'admin') echo 'selected'; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="new_password_edit">New Password (leave blank to keep current)</label>
                            <input type="password" id="new_password_edit" name="new_password">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password_edit">Confirm New Password</label>
                            <input type="password" id="confirm_password_edit" name="confirm_password">
                             <p class="password-note">If you enter a new password, please also confirm it.</p>
                        </div>
                         <div class="form-group">
                            <label for="assigned_rfid_id_edit">Assigned RFID Card</label>
                            <select id="assigned_rfid_id_edit" name="assigned_rfid_id">
                                <option value="">-- Unassign Card / No Card --</option>
                                <?php foreach ($allUnassignedActiveRfids as $rfid): ?>
                                    <option value="<?php echo $rfid['RFID']; ?>" <?php if (isset($userToEdit['assigned_rfid_pk_id']) && $userToEdit['assigned_rfid_pk_id'] == $rfid['RFID']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($rfid['rfid_uid'] . ($rfid['name'] ? ' (' . $rfid['name'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Select a card to assign it. Choosing "-- Unassign Card --" will remove any currently assigned card.</small>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-submit"><span aria-hidden="true" translate="no" class="material-symbols-outlined">save</span> Save Changes</button>
                        <a href="admin-manage-users.php?view=all_users" class="btn-cancel">Cancel</a>
                    </div>
                </form>
            </section>
            <?php elseif (!$dbErrorMessage): // Ak $userToEdit je null (po oprave názvu) a nie je ani DB chyba, niečo je zle s ID (už by malo byť pokryté presmerovaním) ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> Could not load user data. The user may not exist or you do not have permission. (Ref: Initial Load)</div>
                <p><a href="admin-manage-users.php?view=all_users" class="btn-cancel" style="text-decoration:none;">Back to User List</a></p>
            <?php endif; ?>
        </div>
    </main>

    <?php require_once $pathPrefix . "components/footer-admin.php"; ?>
    <script>
        // Prípadný JavaScript špecifický pre túto stránku
    </script>
</body>
</html>