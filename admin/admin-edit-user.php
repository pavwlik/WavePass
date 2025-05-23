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

require_once '../db.php'; // Corrected path

$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$sessionAdminId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null; // Admin's own ID

$dbErrorMessage = null;
$successMessage = null;
$userToEdit = null;
$userCurrentRfid = null;
$availableRfids = []; // Unassigned cards + user's current card

$userIDToEdit = filter_input(INPUT_GET, 'userID', FILTER_VALIDATE_INT);

if (!$userIDToEdit) {
    // No userID provided or invalid, redirect or show error
    header("Location: admin-manage-employees.php?error=No_user_specified");
    exit;
}

// --- HANDLE UPDATE USER FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_user' && isset($_POST['user_id_to_update'])) {
    $uidToUpdate = filter_input(INPUT_POST, 'user_id_to_update', FILTER_VALIDATE_INT);

    if ($uidToUpdate == $userIDToEdit) { // Ensure we are updating the correct user
        $edit_username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS));
        $edit_email = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL));
        $edit_firstName = trim(filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_SPECIAL_CHARS));
        $edit_lastName = trim(filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_SPECIAL_CHARS));
        $edit_phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS));
        $edit_roleID = filter_input(INPUT_POST, 'roleID', FILTER_SANITIZE_SPECIAL_CHARS);
        $edit_rfid_pk_id = filter_input(INPUT_POST, 'rfid_pk_id', FILTER_SANITIZE_SPECIAL_CHARS); // Can be 'none' or an RFID PK ID
        $new_password_edit = $_POST['new_password_edit']; // Optional new password

        // Basic validation
        if (empty($edit_username) || empty($edit_email) || empty($edit_firstName) || empty($edit_lastName) || empty($edit_roleID)) {
            $dbErrorMessage = "Username, Email, First Name, Last Name, and Role are required.";
        } elseif (!in_array($edit_roleID, ['employee', 'admin'])) {
            $dbErrorMessage = "Invalid role selected.";
        } elseif ($edit_roleID === 'admin' && $uidToUpdate == $sessionAdminId && countAdmins($pdo) <= 1) {
             $dbErrorMessage = "Cannot change the role of the last admin. Create another admin first.";
        } else {
            try {
                // Check if new username or email conflicts with ANOTHER user
                $stmtCheck = $pdo->prepare("SELECT userID FROM users WHERE (username = :username OR email = :email) AND userID != :currentUserID");
                $stmtCheck->bindParam(':username', $edit_username, PDO::PARAM_STR);
                $stmtCheck->bindParam(':email', $edit_email, PDO::PARAM_STR);
                $stmtCheck->bindParam(':currentUserID', $uidToUpdate, PDO::PARAM_INT);
                $stmtCheck->execute();
                if ($stmtCheck->fetch()) {
                    $dbErrorMessage = "The new Username or Email is already in use by another account.";
                } else {
                    $pdo->beginTransaction();

                    // Prepare user data update
                    $sqlSetParts = [
                        "username = :username", "email = :email", "firstName = :firstName",
                        "lastName = :lastName", "phone = :phone", "roleID = :roleID"
                    ];
                    $paramsUpdateUser = [
                        ':username' => $edit_username, ':email' => $edit_email,
                        ':firstName' => $edit_firstName, ':lastName' => $edit_lastName,
                        ':phone' => $edit_phone, ':roleID' => $edit_roleID,
                        ':userID' => $uidToUpdate
                    ];

                    // Handle optional password change
                    if (!empty($new_password_edit)) {
                        if (strlen($new_password_edit) < 8) {
                            $dbErrorMessage = "New password must be at least 8 characters long.";
                            $pdo->rollBack();
                            goto end_update_processing;
                        }
                        $hashed_password_edit = password_hash($new_password_edit, PASSWORD_DEFAULT);
                        $sqlSetParts[] = "password = :password";
                        $paramsUpdateUser[':password'] = $hashed_password_edit;
                    }

                    $sqlUpdateUser = "UPDATE users SET " . implode(", ", $sqlSetParts) . " WHERE userID = :userID";
                    $stmtUpdateUser = $pdo->prepare($sqlUpdateUser);
                    
                    if ($stmtUpdateUser->execute($paramsUpdateUser)) {
                        // Handle RFID card assignment
                        // 1. Get user's current assigned RFID (if any)
                        $stmtCurrentRfid = $pdo->prepare("SELECT RFID FROM rfids WHERE userID = :userID");
                        $stmtCurrentRfid->bindParam(':userID', $uidToUpdate, PDO::PARAM_INT);
                        $stmtCurrentRfid->execute();
                        $currentRfidRow = $stmtCurrentRfid->fetch();
                        $currentUserRfidPK = $currentRfidRow ? $currentRfidRow['RFID'] : null;

                        if ($edit_rfid_pk_id === 'none') { // User wants to unassign
                            if ($currentUserRfidPK) {
                                $stmtUnassign = $pdo->prepare("UPDATE rfids SET userID = NULL WHERE RFID = :rfid_pk_id");
                                $stmtUnassign->bindParam(':rfid_pk_id', $currentUserRfidPK, PDO::PARAM_INT);
                                $stmtUnassign->execute();
                            }
                        } elseif (is_numeric($edit_rfid_pk_id) && $edit_rfid_pk_id != $currentUserRfidPK) { // Assigning a new/different card
                            // a. Unassign old card if exists
                            if ($currentUserRfidPK) {
                                $stmtUnassignOld = $pdo->prepare("UPDATE rfids SET userID = NULL WHERE RFID = :rfid_pk_id");
                                $stmtUnassignOld->bindParam(':rfid_pk_id', $currentUserRfidPK, PDO::PARAM_INT);
                                $stmtUnassignOld->execute();
                            }
                            // b. Assign new card (ensure it's available)
                            $stmtCheckNewRfid = $pdo->prepare("SELECT userID FROM rfids WHERE RFID = :rfid_pk_id AND (userID IS NULL OR userID = :current_user_id_for_check)");
                            $stmtCheckNewRfid->bindParam(':rfid_pk_id', $edit_rfid_pk_id, PDO::PARAM_INT);
                            $stmtCheckNewRfid->bindParam(':current_user_id_for_check', $uidToUpdate, PDO::PARAM_INT); // Allows re-assigning their own card if it somehow became unassigned
                            $stmtCheckNewRfid->execute();
                            if ($stmtCheckNewRfid->fetch()) {
                                $stmtAssignNew = $pdo->prepare("UPDATE rfids SET userID = :userID, is_active = 1 WHERE RFID = :rfid_pk_id");
                                $stmtAssignNew->bindParam(':userID', $uidToUpdate, PDO::PARAM_INT);
                                $stmtAssignNew->bindParam(':rfid_pk_id', $edit_rfid_pk_id, PDO::PARAM_INT);
                                $stmtAssignNew->execute();
                            } else {
                                $pdo->rollBack();
                                $dbErrorMessage = "Selected new RFID card is not available. User details updated, but RFID assignment failed.";
                                goto end_update_processing;
                            }
                        }
                        // If $edit_rfid_pk_id is empty or same as current, do nothing with RFID.

                        $pdo->commit();
                        $successMessage = "User details updated successfully!";
                        if (!empty($new_password_edit)) $successMessage .= " Password changed.";
                    } else {
                        $pdo->rollBack();
                        $dbErrorMessage = "Failed to update user details.";
                    }
                }
            } catch (PDOException $e) {
                if($pdo->inTransaction()) $pdo->rollBack();
                $dbErrorMessage = "Database error updating user: " . $e->getMessage();
            }
            end_update_processing:
        }
    } else {
        $dbErrorMessage = "User ID mismatch during update. Operation aborted.";
    }
}

// --- DATA FETCHING for the user to be edited ---
if (isset($pdo) && $pdo instanceof PDO && $userIDToEdit) {
    try {
        $stmtUser = $pdo->prepare(
            "SELECT u.userID, u.username, u.email, u.firstName, u.lastName, u.phone, u.roleID, u.dateOfCreation, u.profile_photo,
                    r.RFID as current_rfid_pk_id, r.rfid_url as current_rfid_url, r.name as current_rfid_name
             FROM users u
             LEFT JOIN rfids r ON u.userID = r.userID
             WHERE u.userID = :userID"
        );
        $stmtUser->bindParam(':userID', $userIDToEdit, PDO::PARAM_INT);
        $stmtUser->execute();
        $userToEdit = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$userToEdit) {
            $dbErrorMessage = "User not found.";
            // Optionally redirect if user not found:
            // header("Location: admin-manage-employees.php?error=User_not_found");
            // exit;
        } else {
            // Fetch unassigned RFID cards + the user's current card (if any)
            $sqlAvailableRfids = "SELECT RFID, rfid_url, name FROM rfids WHERE (userID IS NULL AND is_active = 1)";
            if ($userToEdit['current_rfid_pk_id']) {
                $sqlAvailableRfids .= " OR RFID = :currentUserRfidPK";
            }
            $sqlAvailableRfids .= " ORDER BY rfid_url";
            
            $stmtAvailableRfids = $pdo->prepare($sqlAvailableRfids);
            if ($userToEdit['current_rfid_pk_id']) {
                $stmtAvailableRfids->bindParam(':currentUserRfidPK', $userToEdit['current_rfid_pk_id'], PDO::PARAM_INT);
            }
            $stmtAvailableRfids->execute();
            $availableRfids = $stmtAvailableRfids->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error fetching user data: " . $e->getMessage();
    }
} elseif (!$userIDToEdit && !$dbErrorMessage) { // This handles case if userID was not passed initially
    $dbErrorMessage = "No user specified for editing.";
}


// Function to count admins (to prevent deleting/demoting the last admin)
function countAdmins($pdo_conn) {
    try {
        $stmt = $pdo_conn->query("SELECT COUNT(*) FROM users WHERE roleID = 'admin'");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Log error, return a high number to prevent accidental deletion if query fails
        error_log("countAdmins PDOException: " . $e->getMessage());
        return 999; 
    }
}


$currentPage = basename($_SERVER['PHP_SELF']); // For active nav link
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../imgs/logo.png" type="image/x-icon"> 
    <title>Edit User - <?php echo $userToEdit ? htmlspecialchars($userToEdit['username']) : 'N/A'; ?> - Admin - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* === BASIC STYLES (from admin-dashboard.php / admin-manage-employees.php) === */
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --primary-color-rgb: 67, 97, 238;
            --secondary-color-rgb: 63,55,201; /* For admin role badge */
            --info-color-val: 33, 150, 243; /* For employee role badge */
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --warning-color: #FF9800; --danger-color: #F44336;
            --info-color: #2196F3; 
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px;; }
        .container, .page-header .container { max-width: 1440px; margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px; }
        /* Header and Nav styles should be inherited from included header or global CSS */
        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; }
        /* Ensure .navbar is within a .container in header for width control */
        header .container .navbar { display: flex; justify-content: space-between; align-items: center; height: 80px; }

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
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="tel"],
        .form-group select {
            width: 100%; padding: 0.8rem 1rem; border: 1px solid #ccd0d5; /* Slightly darker border */
            border-radius: 6px; font-family: inherit; font-size: 0.95rem;
            background-color: var(--white); transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus, .form-group select:focus { outline:none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb),0.2); }
        .form-actions { margin-top: 2rem; display:flex; justify-content:flex-end; gap:1rem; }
        
        /* Footer styles */
        footer { background-color: var(--dark-color); color: var(--white); padding: 3rem 0 2rem; margin-top:auto; }
        footer .container {max-width: 1440px;} /* Consistent width for footer content */
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 2rem; margin-bottom: 2rem; }
        .footer-column h3 { font-size: 1.1rem; margin-bottom: 1rem; position: relative; padding-bottom: 0.5rem; }
        .footer-column h3::after { content: ''; position: absolute; left: 0; bottom: 0; width: 40px; height: 2px; background-color: var(--primary-color); }
        .footer-links { list-style: none; padding:0; } .footer-links li { margin-bottom: 0.6rem; }
        .footer-links a { color: rgba(255, 255, 255, 0.7); text-decoration: none; transition: var(--transition); font-size: 0.9rem; }
        .footer-links a:hover { color: var(--white); }
        .footer-links a i { margin-right: 0.4rem; width: 18px; text-align: center; } 
        .social-links { display: flex; gap: 1rem; margin-top: 1rem; padding:0; }
        .social-links a { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; background-color: rgba(255, 255, 255, 0.1); color: var(--white); border-radius: 50%; font-size: 1rem; transition: var(--transition); }
        .social-links a:hover { background-color: var(--primary-color); }
        .footer-bottom { text-align: center; padding-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.85rem; color: rgba(255, 255, 255, 0.6); }
        .footer-bottom a { color: rgba(255, 255, 255, 0.7); text-decoration: none; } .footer-bottom a:hover { color: var(--primary-color); }

    </style>
</head>
<body>
    <?php require "../components/header-admin.php"; ?>


    <main>
        <div class="page-header">
            <div class="container">
                <h1>Edit User: <?php echo $userToEdit ? htmlspecialchars($userToEdit['firstName'] . ' ' . $userToEdit['lastName']) : 'User Not Found'; ?></h1>
                <p class="sub-heading">Modify user details, role, and RFID card assignment.</p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if ($dbErrorMessage): ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $dbErrorMessage; ?></div>
            <?php endif; ?>
            <?php if ($successMessage): ?>
                <div class="success-message" role="alert"><i class="fas fa-check-circle"></i> <?php echo $successMessage; ?></div>
            <?php endif; ?>

            <?php if ($userToEdit): ?>
            <section class="content-panel">
                <form action="admin-edit-user.php?userID=<?php echo $userIDToEdit; ?>" method="POST">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id_to_update" value="<?php echo $userToEdit['userID']; ?>">
                    
                    <div class="panel-header">
                        <h2 class="panel-title">User Information</h2>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username">Username <span style="color:red;">*</span></label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($userToEdit['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email <span style="color:red;">*</span></label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userToEdit['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="firstName">First Name <span style="color:red;">*</span></label>
                            <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($userToEdit['firstName']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name <span style="color:red;">*</span></label>
                            <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($userToEdit['lastName']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userToEdit['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="roleID">Role <span style="color:red;">*</span></label>
                            <select id="roleID" name="roleID" required <?php if ($userToEdit['userID'] == $sessionAdminId && countAdmins($pdo) <= 1) echo 'disabled title="Cannot change role of the last admin."'; ?>>
                                <option value="employee" <?php if ($userToEdit['roleID'] == 'employee') echo 'selected'; ?>>Employee</option>
                                <option value="admin" <?php if ($userToEdit['roleID'] == 'admin') echo 'selected'; ?>>Admin</option>
                            </select>
                             <?php if ($userToEdit['userID'] == $sessionAdminId && countAdmins($pdo) <= 1): ?>
                                <small style="color:var(--gray-color); display:block; margin-top:0.3rem;">You cannot change your own role as the only administrator.</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="panel-header" style="margin-top:2rem;">
                        <h2 class="panel-title">RFID Card Assignment</h2>
                    </div>
                    <div class="form-group">
                        <label for="rfid_pk_id">Assign/Change RFID Card</label>
                        <select id="rfid_pk_id" name="rfid_pk_id">
                            <option value="none" <?php if (!$userToEdit['current_rfid_pk_id']) echo 'selected'; ?>>-- Unassign / None --</option>
                            <?php foreach ($availableRfids as $rfid): ?>
                                <option value="<?php echo $rfid['RFID']; ?>" <?php if ($userToEdit['current_rfid_pk_id'] == $rfid['RFID']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($rfid['rfid_url'] . ($rfid['name'] ? ' - ' . $rfid['name'] : '')); ?>
                                    <?php if ($userToEdit['current_rfid_pk_id'] == $rfid['RFID']) echo ' (Current)'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($userToEdit['current_rfid_url']): ?>
                        <small style="color:var(--gray-color); display:block; margin-top:0.3rem;">Currently assigned: <?php echo htmlspecialchars($userToEdit['current_rfid_url'] . ($userToEdit['current_rfid_name'] ? ' - ' . $userToEdit['current_rfid_name'] : '')); ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="panel-header" style="margin-top:2rem;">
                        <h2 class="panel-title">Change Password (Optional)</h2>
                    </div>
                     <div class="form-group">
                        <label for="new_password_edit">New Password</label>
                        <input type="password" id="new_password_edit" name="new_password_edit" placeholder="Leave blank to keep current password">
                        <small style="color:var(--gray-color); display:block; margin-top:0.3rem;">Min 8 characters. Only fill if you want to change the password.</small>
                    </div>


                    <div class="form-actions">
                        <a href="admin-manage-employees.php" class="btn-secondary"><span class="material-symbols-outlined">arrow_back</span> Cancel</a>
                        <button type="submit" class="btn-primary"><span class="material-symbols-outlined">save</span> Update User</button>
                    </div>
                </form>
            </section>
            <?php elseif(!$dbErrorMessage): // Show this only if userToEdit is null AND no DB error was already set ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> User with the specified ID could not be found.</div>
                <a href="admin-manage-employees.php" class="btn-secondary" style="margin-top:1rem;"><span class="material-symbols-outlined">arrow_back</span> Back to User List</a>
            <?php endif; ?>
        </div>
    </main>

    <?php require "../components/footer-admin.php"; // Or your generic footer ?>

    <script>
        // Basic mobile menu toggle (if not in a global JS file included by header)
        // ... (Copy JS for mobile menu from admin-manage-employees.php if needed) ...
    </script>
</body>
</html>