<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Restrict Access: Ensure only admin can access
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    // For admin pages, redirecting to login.php or a specific admin login might be better
    // Also, the login.php needs to know where to redirect admin users.
    // Consider where 'login.php' is relative to this 'admin' folder.
    // If login.php is in the parent directory:
    header("location: ../login.php"); 
    // If login.php is in the root of wavepass:
    // header("location: /bures.pa.2022/wavepass/login.php"); // Or use a defined ROOT_URL constant
    exit;
}

// require_once 'db.php'; // OLD - Incorrect path
require_once '../db.php'; // NEW - Corrected path to go one level up

$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;

$dbErrorMessage = null;
$successMessage = null;
$users = [];
$unassignedRfids = []; // For the add/edit form

// --- ACTION HANDLING (ADD, EDIT, DELETE USER) ---

// HANDLE ADD USER
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $new_username = trim(filter_input(INPUT_POST, 'new_username', FILTER_SANITIZE_SPECIAL_CHARS));
    $new_email = trim(filter_input(INPUT_POST, 'new_email', FILTER_VALIDATE_EMAIL));
    $new_password = $_POST['new_password']; // Password will be hashed
    $new_firstName = trim(filter_input(INPUT_POST, 'new_firstName', FILTER_SANITIZE_SPECIAL_CHARS));
    $new_lastName = trim(filter_input(INPUT_POST, 'new_lastName', FILTER_SANITIZE_SPECIAL_CHARS));
    $new_phone = trim(filter_input(INPUT_POST, 'new_phone', FILTER_SANITIZE_SPECIAL_CHARS));
    $new_roleID = filter_input(INPUT_POST, 'new_roleID', FILTER_SANITIZE_SPECIAL_CHARS); // 'employee' or 'admin'
    $new_rfid_id = filter_input(INPUT_POST, 'new_rfid_id', FILTER_VALIDATE_INT); // This is the ID from 'rfids' table (PK)

    // Basic validation
    if (empty($new_username) || empty($new_email) || empty($new_password) || empty($new_firstName) || empty($new_lastName) || empty($new_roleID)) {
        $dbErrorMessage = "All fields except phone and RFID are required for a new user.";
    } elseif (!in_array($new_roleID, ['employee', 'admin'])) {
        $dbErrorMessage = "Invalid role selected.";
    } else {
        try {
            // Check if username or email already exists
            $stmtCheck = $pdo->prepare("SELECT userID FROM users WHERE username = :username OR email = :email");
            $stmtCheck->bindParam(':username', $new_username, PDO::PARAM_STR);
            $stmtCheck->bindParam(':email', $new_email, PDO::PARAM_STR);
            $stmtCheck->execute();
            if ($stmtCheck->fetch()) {
                $dbErrorMessage = "Username or Email already exists.";
            } else {
                // Hash the password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                $sqlInsertUser = "INSERT INTO users (username, email, password, firstName, lastName, phone, roleID, dateOfCreation) 
                                  VALUES (:username, :email, :password, :firstName, :lastName, :phone, :roleID, NOW())";
                $stmtInsertUser = $pdo->prepare($sqlInsertUser);
                $stmtInsertUser->bindParam(':username', $new_username);
                $stmtInsertUser->bindParam(':email', $new_email);
                $stmtInsertUser->bindParam(':password', $hashed_password);
                $stmtInsertUser->bindParam(':firstName', $new_firstName);
                $stmtInsertUser->bindParam(':lastName', $new_lastName);
                $stmtInsertUser->bindParam(':phone', $new_phone);
                $stmtInsertUser->bindParam(':roleID', $new_roleID);
                
                if ($stmtInsertUser->execute()) {
                    $newlyInsertedUserID = $pdo->lastInsertId();
                    
                    // If an RFID card was selected, assign it to the new user
                    if ($new_rfid_id) {
                        // Check if the RFID card is actually unassigned
                        $stmtCheckRfid = $pdo->prepare("SELECT userID FROM rfids WHERE RFID = :rfid_id AND userID IS NULL");
                        $stmtCheckRfid->bindParam(':rfid_id', $new_rfid_id, PDO::PARAM_INT);
                        $stmtCheckRfid->execute();
                        if ($stmtCheckRfid->fetch()) {
                            $sqlUpdateRfid = "UPDATE rfids SET userID = :userID, is_active = 1 WHERE RFID = :rfid_id";
                            $stmtUpdateRfid = $pdo->prepare($sqlUpdateRfid);
                            $stmtUpdateRfid->bindParam(':userID', $newlyInsertedUserID, PDO::PARAM_INT);
                            $stmtUpdateRfid->bindParam(':rfid_id', $new_rfid_id, PDO::PARAM_INT);
                            $stmtUpdateRfid->execute();
                        } else {
                            // RFID card was not unassigned or doesn't exist, rollback or warn
                            $pdo->rollBack();
                            $dbErrorMessage = "Selected RFID card is not available or already assigned. User not fully created.";
                            goto end_add_user_processing; // Skip success message
                        }
                    }
                    $pdo->commit();
                    $successMessage = "User '$new_username' added successfully!";
                } else {
                    $pdo->rollBack();
                    $dbErrorMessage = "Failed to add user.";
                }
            }
        } catch (PDOException $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $dbErrorMessage = "Database error adding user: " . $e->getMessage();
        }
        end_add_user_processing:
    }
}

// HANDLE DELETE USER (Example, add confirmation in JS)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_user') {
    $user_id_to_delete = filter_input(INPUT_POST, 'user_id_to_delete', FILTER_VALIDATE_INT);
    if ($user_id_to_delete && $user_id_to_delete != $sessionUserId) { // Prevent admin from deleting themselves
        try {
            $pdo->beginTransaction();
            // Unassign RFID card first
            $stmtUnassign = $pdo->prepare("UPDATE rfids SET userID = NULL WHERE userID = :userID");
            $stmtUnassign->bindParam(':userID', $user_id_to_delete, PDO::PARAM_INT);
            $stmtUnassign->execute();

            // Then delete user
            $stmtDelete = $pdo->prepare("DELETE FROM users WHERE userID = :userID");
            $stmtDelete->bindParam(':userID', $user_id_to_delete, PDO::PARAM_INT);
            if ($stmtDelete->execute()) {
                $pdo->commit();
                $successMessage = "User deleted successfully.";
            } else {
                $pdo->rollBack();
                $dbErrorMessage = "Failed to delete user.";
            }
        } catch (PDOException $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $dbErrorMessage = "Database error deleting user: " . $e->getMessage();
        }
    } elseif ($user_id_to_delete == $sessionUserId) {
        $dbErrorMessage = "You cannot delete your own account.";
    } else {
        $dbErrorMessage = "Invalid user ID for deletion.";
    }
}


// --- DATA FETCHING ---
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // Fetch all users with their assigned RFID card UID (if any)
        $stmtAllUsers = $pdo->query(
            "SELECT u.userID, u.username, u.email, u.firstName, u.lastName, u.phone, u.roleID, u.dateOfCreation, r.rfid_url, r.RFID as rfid_pk_id
             FROM users u
             LEFT JOIN rfids r ON u.userID = r.userID 
             ORDER BY u.lastName, u.firstName"
        );
        $users = $stmtAllUsers->fetchAll(PDO::FETCH_ASSOC);

        // Fetch unassigned RFID cards for the dropdown in add/edit forms
        // (Assuming rfid_url is the human-readable ID you want to show)
        $stmtUnassignedRfids = $pdo->query("SELECT RFID, rfid_url, name FROM rfids WHERE userID IS NULL AND is_active = 1 ORDER BY rfid_url");
        $unassignedRfids = $stmtUnassignedRfids->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error fetching data: " . $e->getMessage();
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
    <title>manage users - Admin - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* === BASIC STYLES (from admin-dashboard.php) === */
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
        main { flex-grow: 1; padding-top: 80px;; }
        .container, .page-header .container { max-width: 1440px; margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px; }
        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 80px; }
        .nav-links { display: flex; /* ... */ } .hamburger { display: none; /* ... */ } .mobile-menu { /* ... */ }
        @media (max-width: 992px) { .nav-links { display: none; } .hamburger { display: flex; } }

        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        .db-error-message, .success-message { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .db-error-message { background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        .success-message { background-color: rgba(76,175,80,0.1); color: var(--success-color); border-left-color: var(--success-color); }

        /* === manage users SPECIFIC STYLES === */
        .content-panel { background-color: var(--white); padding: 1.5rem 1.8rem; border-radius: 8px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); margin-bottom: 2rem; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); }
        .panel-title { font-size: 1.3rem; color: var(--dark-color); margin:0; }
        .btn-primary { background-color: var(--primary-color); color: var(--white); border:none; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration:none; font-weight: 500; cursor:pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary:hover { background-color: var(--primary-dark); }
        .btn-danger { background-color: var(--danger-color); color:white; border:none; padding: 0.4rem 0.8rem; border-radius: 4px; cursor:pointer; font-size:0.85rem; }
        .btn-edit { background-color: var(--info-color); color:white; border:none; padding: 0.4rem 0.8rem; border-radius: 4px; cursor:pointer; font-size:0.85rem; text-decoration:none; margin-right:0.5rem; }

        .users-table-wrapper { overflow-x: auto; }
        .users-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .users-table th, .users-table td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid var(--light-gray); }
        .users-table th { background-color: #f9fafb; font-weight: 600; color: var(--gray-color); white-space: nowrap; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; }
        .users-table tbody tr:hover { background-color: #f0f4ff; }
        .users-table td .role-badge { padding: 0.2rem 0.6rem; border-radius: 10px; font-size: 0.75rem; font-weight: 500; text-transform: capitalize; }
        .users-table td .role-admin { background-color: rgba(var(--secondary-color-rgb, 63,55,201), 0.15); color: var(--secondary-color); }
        .users-table td .role-employee { background-color: rgba(var(--info-color-val), 0.15); color: var(--info-color); }
        .users-table .actions-cell form { display: inline-block; }

        /* Add/Edit User Form Styles */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.9rem; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="tel"],
        .form-group select {
            width: 100%; padding: 0.7rem; border: 1px solid var(--light-gray);
            border-radius: 6px; font-family: inherit; font-size: 0.9rem;
        }
        .form-group input:focus, .form-group select:focus { outline:none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb),0.2); }
        .form-actions { margin-top: 1.5rem; text-align: right; }
        
        /* Footer styles */
        footer { background-color: var(--dark-color); color: var(--white); padding: 5rem 0 2rem; margin-top:auto; }
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 3rem; margin-bottom: 3rem; }
        .footer-column h3 { font-size: 1.3rem; margin-bottom: 1.8rem; position: relative; padding-bottom: 0.8rem; }
        .footer-column h3::after { content: ''; position: absolute; left: 0; bottom: 0; width: 50px; height: 3px; background-color: var(--primary-color); border-radius: 3px; }
        .footer-links { list-style: none; padding:0; } .footer-links li { margin-bottom: 0.8rem; }
        .footer-links a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); font-size: 0.95rem; display: inline-block; padding: 0.2rem 0; }
        .footer-links a:hover { color: var(--white); transform: translateX(5px); }
        .footer-links a i { margin-right: 0.5rem; width: 20px; text-align: center; } 
        .social-links { display: flex; gap: 1.2rem; margin-top: 1.5rem; padding:0; }
        .social-links a { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background-color: rgba(255, 255, 255, 0.1); color: var(--white); border-radius: 50%; font-size: 1.1rem; transition: var(--transition); }
        .social-links a:hover { background-color: var(--primary-color); transform: translateY(-3px); }
        .footer-bottom { text-align: center; padding-top: 3rem; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.9rem; color: rgba(255, 255, 255, 0.6); }
        .footer-bottom a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); }
        .footer-bottom a:hover { color: var(--primary-color); }

    </style>
</head>
<body>
    <?php require "../components/header-admin.php"; // Ensure this header is suitable for admin or create an admin-specific one ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>Manage users</h1>
                <p class="sub-heading">Add, view, edit, and manage user accounts and RFID assignments.</p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if ($dbErrorMessage): ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $dbErrorMessage; ?></div>
            <?php endif; ?>
            <?php if ($successMessage): ?>
                <div class="success-message" role="alert"><i class="fas fa-check-circle"></i> <?php echo $successMessage; ?></div>
            <?php endif; ?>

            <!-- Add New User Form -->
            <section class="content-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Add New Employee/Admin</h2>
                </div>
                <form action="admin-manage-users.php" method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="new_username">Username <span style="color:red;">*</span></label>
                            <input type="text" id="new_username" name="new_username" required>
                        </div>
                        <div class="form-group">
                            <label for="new_email">Email <span style="color:red;">*</span></label>
                            <input type="email" id="new_email" name="new_email" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Password <span style="color:red;">*</span></label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_firstName">First Name <span style="color:red;">*</span></label>
                            <input type="text" id="new_firstName" name="new_firstName" required>
                        </div>
                        <div class="form-group">
                            <label for="new_lastName">Last Name <span style="color:red;">*</span></label>
                            <input type="text" id="new_lastName" name="new_lastName" required>
                        </div>
                        <div class="form-group">
                            <label for="new_phone">Phone</label>
                            <input type="tel" id="new_phone" name="new_phone">
                        </div>
                        <div class="form-group">
                            <label for="new_roleID">Role <span style="color:red;">*</span></label>
                            <select id="new_roleID" name="new_roleID" required>
                                <option value="employee">Employee</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary"><span class="material-symbols-outlined">person_add</span> Add User</button>
                    </div>
                </form>
            </section>

            <!-- List of Existing Users -->
            <section class="content-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Existing Users (<?php echo count($users); ?>)</h2>
                    <!-- Search/Filter options can go here -->
                </div>
                <div class="users-table-wrapper">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>RFID (URL/Name)</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['userID']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?></td>
                                        <td>
                                            <span class="role-badge role-<?php echo strtolower($user['roleID']); ?>">
                                                <?php echo htmlspecialchars($user['roleID']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($user['rfid_url']) {
                                                echo htmlspecialchars($user['rfid_url']);
                                                // You might want to fetch the RFID card's 'name' here too if 'rfids' table has it and you store rfid_pk_id
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo date("M d, Y", strtotime($user['dateOfCreation'])); ?></td>
                                        <td class="actions-cell">
                                            <a href="admin-edit-user.php?userID=<?php echo $user['userID']; ?>" class="btn-edit" title="Edit User"><span class="material-symbols-outlined" style="font-size:1em; vertical-align:middle;">edit</span></a>
                                            <?php if ($user['userID'] != $sessionUserId): // Prevent self-deletion ?>
                                            <form action="admin-manage-users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id_to_delete" value="<?php echo $user['userID']; ?>">
                                                <button type="submit" class="btn-danger" title="Delete User"><span class="material-symbols-outlined" style="font-size:1em; vertical-align:middle;">delete</span></button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9" style="text-align:center; padding: 1.5rem;">No users found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <?php require "../components/footer-admin.php"; ?>

</body>
</html>