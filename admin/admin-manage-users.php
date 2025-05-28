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

$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
$pathPrefix = "../"; // Pre cesty k assetom z /admin/ adresára o úroveň vyššie

$dbErrorMessage = null;
$successMessage = null;
$users = [];

// Cesty pre profilové fotky
$profilePhotoBaseDir_server = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'profile_photos' . DIRECTORY_SEPARATOR; // Serverová cesta pre file_exists
$profilePhotoBaseDir_web = $pathPrefix . 'profile_photos/'; // Webová cesta pre src atribut
$defaultAvatar_web = $pathPrefix . 'imgs/default_avatar.jpg'; // Webová cesta pre default avatar


// --- ACTION HANDLING (ADD, DELETE USER) ---

// HANDLE ADD USER
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $new_username = trim(filter_input(INPUT_POST, 'new_username', FILTER_SANITIZE_SPECIAL_CHARS));
    $new_email = trim(filter_input(INPUT_POST, 'new_email', FILTER_VALIDATE_EMAIL));
    $new_password = $_POST['new_password']; // Password will be hashed
    $new_firstName = trim(filter_input(INPUT_POST, 'new_firstName', FILTER_SANITIZE_SPECIAL_CHARS));
    $new_lastName = trim(filter_input(INPUT_POST, 'new_lastName', FILTER_SANITIZE_SPECIAL_CHARS));
    $new_phone = trim(filter_input(INPUT_POST, 'new_phone', FILTER_SANITIZE_SPECIAL_CHARS));
    $new_roleID = filter_input(INPUT_POST, 'new_roleID', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($new_username) || empty($new_email) || empty($new_password) || empty($new_firstName) || empty($new_lastName) || empty($new_roleID)) {
        $dbErrorMessage = "All fields except phone are required for a new user.";
    } elseif (!in_array($new_roleID, ['employee', 'admin'])) {
        $dbErrorMessage = "Invalid role selected.";
    } else {
        try {
            $stmtCheck = $pdo->prepare("SELECT userID FROM users WHERE username = :username OR email = :email");
            $stmtCheck->bindParam(':username', $new_username, PDO::PARAM_STR);
            $stmtCheck->bindParam(':email', $new_email, PDO::PARAM_STR);
            $stmtCheck->execute();
            if ($stmtCheck->fetch()) {
                $dbErrorMessage = "Username or Email already exists.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
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
                    $successMessage = "User '$new_username' added successfully!";
                } else {
                    $dbErrorMessage = "Failed to add user.";
                }
            }
        } catch (PDOException $e) {
            $dbErrorMessage = "Database error adding user: " . $e->getMessage();
        }
    }
}

// HANDLE DELETE USER
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_user') {
    $user_id_to_delete = filter_input(INPUT_POST, 'user_id_to_delete', FILTER_VALIDATE_INT);
    if ($user_id_to_delete && $user_id_to_delete != $sessionUserId) {
        try {
            $pdo->beginTransaction();
            $stmtUnassign = $pdo->prepare("UPDATE rfids SET userID = NULL WHERE userID = :userID_param");
            $stmtUnassign->bindParam(':userID_param', $user_id_to_delete, PDO::PARAM_INT);
            $stmtUnassign->execute();

            $stmtDelete = $pdo->prepare("DELETE FROM users WHERE userID = :userID_param");
            $stmtDelete->bindParam(':userID_param', $user_id_to_delete, PDO::PARAM_INT);
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
        // Fetch all users with their assigned RFID card UID and profile photo
        $stmtAllUsers = $pdo->query(
            "SELECT u.userID, u.username, u.email, u.firstName, u.lastName, u.phone, u.roleID, u.dateOfCreation,
                    u.profile_photo, -- Pridaný stĺpec
                    r.rfid_uid
             FROM users u
             LEFT JOIN rfids r ON u.userID = r.userID
             ORDER BY u.lastName, u.firstName"
        );
        $users = $stmtAllUsers->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error fetching users: " . $e->getMessage();
    }
} else {
    $dbErrorMessage = "Database connection is not available.";
}

$currentPage = basename($_SERVER['PHP_SELF']);
$currentView = isset($_GET['view']) ? $_GET['view'] : 'all_users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?php echo htmlspecialchars($pathPrefix); ?>imgs/logo.png" type="image/x-icon">
    <title>Manage Users - Admin - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --primary-color-rgb: 67, 97, 238;
            --secondary-color-rgb: 63, 55, 201; /* Pre admin badge */
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --warning-color: #FF9800; --danger-color: #F44336;
            --info-color: #2196F3;
            --info-color-val: 33, 150, 243; /* Pre employee badge */
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px; } /* Predpokladáme fixnú hlavičku 80px */
        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; height: 80px; }
        /* Vložte CSS pre .navbar, .logo atď. z vášho header-admin.php */
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }

        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header .container {max-width: 1400px;} /* Zosúladenie s .admin-layout-container */
        .page-header h1 { font-size: 1.7rem; margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }

        .admin-layout-container { display: flex; flex-direction: column; max-width: 1400px; margin: 0 auto; padding: 0 20px; gap: 1.5rem; }
        .admin-sidebar { flex-basis: 100%; background-color: var(--white); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); height: fit-content; }
        .admin-content { flex-grow: 1; }

        @media (min-width: 992px) {
            .admin-layout-container { flex-direction: row; }
            .admin-sidebar { flex: 0 0 280px; }
        }

        .sidebar-nav-list { list-style: none; padding: 0; }
        .sidebar-nav-list h3 { font-size: 1.1rem; margin-bottom: 1rem; color: var(--dark-color); padding-bottom: 0.7rem; border-bottom: 1px solid var(--light-gray); }
        .sidebar-nav-list li a { display: flex; align-items: center; gap: 0.7rem; padding: 0.8rem 1rem; text-decoration: none; color: var(--dark-color); border-radius: 6px; transition: var(--transition); font-weight: 500; margin-bottom: 0.3rem; }
        .sidebar-nav-list li a:hover, .sidebar-nav-list li a.active-view { background-color: rgba(var(--primary-color-rgb), 0.1); color: var(--primary-color); }
        .sidebar-nav-list li a .material-symbols-outlined { font-size: 1.3em; }

        .db-error-message, .success-message { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .db-error-message { background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        .success-message { background-color: rgba(76,175,80,0.1); color: var(--success-color); border-left-color: var(--success-color); }

        .content-panel { background-color: var(--white); padding: 1.5rem 1.8rem; border-radius: 8px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); margin-bottom: 2rem; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); }
        .panel-title { font-size: 1.3rem; color: var(--dark-color); margin:0; }
        .btn-primary { background-color: var(--primary-color); color: var(--white); border:none; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration:none; font-weight: 500; cursor:pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary:hover { background-color: var(--primary-dark); }
        .btn-danger { background-color: var(--danger-color); color:white; border:none; padding: 0.4rem 0.8rem; border-radius: 4px; cursor:pointer; font-size:0.85rem; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-edit { background-color: var(--info-color); color:white; border:none; padding: 0.4rem 0.8rem; border-radius: 4px; cursor:pointer; font-size:0.85rem; text-decoration:none; margin-right:0.5rem; display: inline-flex; align-items: center; gap: 0.3rem;}
        .btn-edit .material-symbols-outlined, .btn-danger .material-symbols-outlined { font-size: 1em; vertical-align: text-bottom;}

        .users-table-wrapper { overflow-x: auto; }
        .users-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .users-table th, .users-table td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid var(--light-gray); vertical-align: middle; }
        .users-table th { background-color: #f9fafb; font-weight: 600; color: var(--gray-color); white-space: nowrap; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; }
        .users-table tbody tr:hover { background-color: #f0f4ff; }
        .users-table td .role-badge { padding: 0.2rem 0.6rem; border-radius: 10px; font-size: 0.75rem; font-weight: 500; text-transform: capitalize; }
        .users-table td .role-admin { background-color: rgba(var(--secondary-color-rgb), 0.15); color: var(--secondary-color); }
        .users-table td .role-employee { background-color: rgba(var(--info-color-val), 0.15); color: var(--info-color); }
        .users-table .actions-cell form { display: inline-block; margin: 0; }
        .users-table .actions-cell a, .users-table .actions-cell button { margin-right: 0.3rem;}
        .users-table .actions-cell a:last-child, .users-table .actions-cell button:last-child { margin-right: 0;}
        .users-table .profile-photo-small { width: 30px; height: 30px; border-radius: 50%; margin-right: 10px; object-fit: cover; vertical-align: middle; border: 1px solid var(--light-gray); }
        .users-table td.full-name-cell { display: flex; align-items: center; }


        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem 1.5rem; }
        .form-group { margin-bottom: 1rem; }
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
        .form-actions { margin-top: 1.5rem; text-align: right; }
        .hidden-section { display: none; }

    </style>
</head>
<body>
    <?php require "../components/header-admin.php"; ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>Manage Users</h1>
                <p class="sub-heading">Add, view, edit, and manage user accounts.</p>
            </div>
        </div>

        <div class="admin-layout-container">
            <aside class="admin-sidebar">
                <ul class="sidebar-nav-list">
                    <h3>User Accounts</h3>
                    <li>
                        <a href="admin-manage-users.php?view=all_users" class="<?php if ($currentView == 'all_users') echo 'active-view'; ?>">
                            <span class="material-symbols-outlined">group</span> All Users
                        </a>
                    </li>
                    <li>
                        <a href="admin-manage-users.php?view=add_user" class="<?php if ($currentView == 'add_user') echo 'active-view'; ?>">
                            <span class="material-symbols-outlined">person_add</span> Add New User
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

                <section id="add-user-section" class="content-panel <?php if ($currentView !== 'add_user') echo 'hidden-section'; ?>">
                    <div class="panel-header">
                        <h2 class="panel-title">Add New Employee/Admin</h2>
                    </div>
                    <form action="admin-manage-users.php?view=add_user" method="POST">
                        <input type="hidden" name="action" value="add_user">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="new_username_form">Username <span style="color:red;">*</span></label>
                                <input type="text" id="new_username_form" name="new_username" required>
                            </div>
                            <div class="form-group">
                                <label for="new_email_form">Email <span style="color:red;">*</span></label>
                                <input type="email" id="new_email_form" name="new_email" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password_form">Password <span style="color:red;">*</span></label>
                                <input type="password" id="new_password_form" name="new_password" required>
                            </div>
                            <div class="form-group">
                                <label for="new_firstName_form">First Name <span style="color:red;">*</span></label>
                                <input type="text" id="new_firstName_form" name="new_firstName" required>
                            </div>
                            <div class="form-group">
                                <label for="new_lastName_form">Last Name <span style="color:red;">*</span></label>
                                <input type="text" id="new_lastName_form" name="new_lastName" required>
                            </div>
                            <div class="form-group">
                                <label for="new_phone_form">Phone</label>
                                <input type="tel" id="new_phone_form" name="new_phone">
                            </div>
                            <div class="form-group">
                                <label for="new_roleID_form">Role <span style="color:red;">*</span></label>
                                <select id="new_roleID_form" name="new_roleID" required>
                                    <option value="employee" selected>Employee</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary"><span class="material-symbols-outlined">person_add</span> Add User</button>
                        </div>
                    </form>
                </section>

                <section id="all-users-section" class="content-panel <?php if ($currentView !== 'all_users') echo 'hidden-section'; ?>">
                    <div class="panel-header">
                        <h2 class="panel-title">Existing Users (<?php echo count($users); ?>)</h2>
                    </div>
                    <div class="users-table-wrapper">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $user): ?>
                                        <?php
                                        $userPhotoSrc = $defaultAvatar_web; // Predvolený avatar
                                        if (!empty($user['profile_photo']) && file_exists($profilePhotoBaseDir_server . $user['profile_photo'])) {
                                            $userPhotoSrc = $profilePhotoBaseDir_web . htmlspecialchars($user['profile_photo']);
                                        }
                                        $userPhotoSrc .= '?' . time(); // Cache busting
                                        ?>
                                        <tr>
                                            <td class="full-name-cell">
                                                <img src="<?php echo $userPhotoSrc; ?>" alt="Profile Photo" class="profile-photo-small">
                                                <span><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></span>
                                            </td>
                                            <td><a href="mailto:<?php echo htmlspecialchars($user['email']); ?>"><?php echo htmlspecialchars($user['email']); ?></a></td>
                                            <td><?php echo htmlspecialchars($user['phone'] ?: 'N/A'); ?></td>
                                            <td>
                                                <span class="role-badge role-<?php echo strtolower(htmlspecialchars($user['roleID'])); ?>">
                                                    <?php echo htmlspecialchars($user['roleID']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date("M d, Y", strtotime($user['dateOfCreation'])); ?></td>
                                            <td class="actions-cell">
                                                <a href="admin-edit-user.php?userID=<?php echo $user['userID']; ?>" class="btn-edit" title="Edit User">
                                                    <span class="material-symbols-outlined">edit</span>
                                                </a>
                                                <?php if ($user['userID'] != $sessionUserId): ?>
                                                <form action="admin-manage-users.php?view=all_users" method="POST" onsubmit="return confirm('Are you sure you want to delete this user and unassign their RFID card? This action cannot be undone.');" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id_to_delete" value="<?php echo $user['userID']; ?>">
                                                    <button type="submit" class="btn-danger" title="Delete User">
                                                        <span class="material-symbols-outlined">delete</span>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" style="text-align:center; padding: 1.5rem;">No users found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <?php require "../components/footer-admin.php"; ?>
    <script>
        // Prípadný JavaScript špecifický pre túto stránku
    </script>
</body>
</html>