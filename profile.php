<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

require_once 'db.php'; 

$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'User';
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
$sessionRole = isset($_SESSION["role"]) ? htmlspecialchars($_SESSION["role"]) : 'employee';
$currentPage = basename($_SERVER['PHP_SELF']); 

$activeSection = isset($_GET['section']) ? $_GET['section'] : 'profile';

$rfidStatusFilter = 'active'; 
if ($activeSection === 'rfid') {
    if (isset($_GET['rfid_status']) && in_array($_GET['rfid_status'], ['active', 'inactive', 'all'])) {
        $rfidStatusFilter = $_GET['rfid_status'];
    }
} else {
    $rfidStatusFilter = null; 
}

$userData = null;
$userRFIDCards = []; 
$dbErrorMessage = null;
$updateMessage = null; 

// --- PATH & CONFIGURATION CONSTANTS ---
$projectBasePath = ''; // Adjust if your project is in a subdirectory of the web root
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$scriptDir = dirname($_SERVER['SCRIPT_FILENAME']);
if (strpos($scriptDir, $docRoot) === 0) {
    $projectBasePathCalc = substr($scriptDir, strlen($docRoot));
    if (basename($_SERVER['SCRIPT_NAME']) != '') { $projectBasePathCalc = dirname($projectBasePathCalc); }
    $projectBasePath = str_replace('\\', '/', $projectBasePathCalc);
    $projectBasePath = rtrim($projectBasePath, '/'); 
}

if (!defined('WEB_ROOT_PATH')) { // Define a base path for web URLs if not already set
    // This attempts to guess the base path. For more robustness, define it explicitly in a config file.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    define('WEB_ROOT_PATH', rtrim($protocol . $host . $projectBasePath, '/') . '/');
}


if (!defined('PROFILE_UPLOAD_DIR_FROM_ROOT')) define('PROFILE_UPLOAD_DIR_FROM_ROOT', 'profile_photos/');
$webProfileUploadDir = WEB_ROOT_PATH . ltrim(PROFILE_UPLOAD_DIR_FROM_ROOT, '/');
$fileSystemProfileUploadDir = $docRoot . $projectBasePath . '/' . ltrim(PROFILE_UPLOAD_DIR_FROM_ROOT, '/');
$fileSystemProfileUploadDir = rtrim($fileSystemProfileUploadDir, '/') . '/';


if (!defined('MAX_PHOTO_SIZE')) define('MAX_PHOTO_SIZE', 2 * 1024 * 1024); 
if (!defined('ALLOWED_PHOTO_TYPES')) define('ALLOWED_PHOTO_TYPES', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif']);

if (!is_dir($fileSystemProfileUploadDir)) {
    if (!@mkdir($fileSystemProfileUploadDir, 0775, true)) {
        $mkdirError = error_get_last();
        error_log("CRITICAL ERROR: Failed to create profile photo directory (" . $fileSystemProfileUploadDir . "). Error: " . ($mkdirError['message'] ?? 'Unknown error'));
        if($activeSection === 'profile') { $dbErrorMessage = "Profile photo directory setup error. Please contact support."; }
    }
}

// --- Form Submission Handling (Profile Update & Password Change) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($pdo) && $sessionUserId) {
    // Fetch current photo before any updates, only if needed for deletion logic
    $currentDbUserPhoto = null;
    if (isset($_POST['update_profile']) && (isset($_FILES['profile_photo_input']) && $_FILES['profile_photo_input']['error'] === UPLOAD_ERR_OK)) {
        $stmtCurrentPhoto = $pdo->prepare("SELECT profile_photo FROM users WHERE userID = :userid");
        $stmtCurrentPhoto->bindParam(':userid', $sessionUserId, PDO::PARAM_INT);
        $stmtCurrentPhoto->execute();
        $currentDbUserPhoto = $stmtCurrentPhoto->fetchColumn();
        if($stmtCurrentPhoto) $stmtCurrentPhoto->closeCursor();
    }


    if (isset($_POST['update_profile'])) {
        $newFirstName = trim($_POST['firstName']);
        $newLastName = trim($_POST['lastName']);
        $newEmail = trim($_POST['email']);
        $newPhone = trim($_POST['phone']);
        $newProfilePhotoNameToSave = $currentDbUserPhoto; // Default to current, will be overridden if new photo uploaded

        if (empty($newFirstName) || empty($newLastName) || empty($newEmail)) {
            $updateMessage = ['type' => 'error', 'text' => 'First name, last name, and email are required.'];
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $updateMessage = ['type' => 'error', 'text' => 'Invalid email format.'];
        } else {
            $currentEmailInDB = $_SESSION['email'] ?? ''; // Fallback to session, prefer DB loaded below
            if (isset($userData['email'])) $currentEmailInDB = $userData['email']; // Use if already loaded

            if (strtolower($newEmail) !== strtolower($currentEmailInDB)) {
                $stmtCheckEmail = $pdo->prepare("SELECT userID FROM users WHERE email = :email AND userID != :sessionUserID");
                $stmtCheckEmail->bindParam(':email', $newEmail);
                $stmtCheckEmail->bindParam(':sessionUserID', $sessionUserId, PDO::PARAM_INT);
                $stmtCheckEmail->execute();
                if ($stmtCheckEmail->fetch()) {
                    $updateMessage = ['type' => 'error', 'text' => 'This email address is already in use.'];
                }
            }

            if (!isset($updateMessage)) { 
                $photoUploadProcessedSuccessfully = false; 
                if (isset($_FILES['profile_photo_input']) && $_FILES['profile_photo_input']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['profile_photo_input']['tmp_name'];
                    $fileSize = $_FILES['profile_photo_input']['size'];
                    $fileType = mime_content_type($fileTmpPath);

                    if ($fileSize > MAX_PHOTO_SIZE) {
                        $updateMessage = ['type' => 'error', 'text' => 'Image too large (Max 2MB).'];
                    } elseif (!array_key_exists($fileType, ALLOWED_PHOTO_TYPES)) {
                        $updateMessage = ['type' => 'error', 'text' => 'Invalid file type. Allowed: JPG, PNG, GIF.'];
                    } else {
                        $fileExtension = ALLOWED_PHOTO_TYPES[$fileType];
                        $uploadedFileName = 'user' . $sessionUserId . '_' . bin2hex(random_bytes(12)) . '.' . $fileExtension;
                        $dest_path = $fileSystemProfileUploadDir . $uploadedFileName;

                        if (!is_writable($fileSystemProfileUploadDir)) {
                            $updateMessage = ['type' => 'error', 'text' => 'Upload directory not writable.'];
                            error_log("Upload directory not writable: " . $fileSystemProfileUploadDir);
                        } elseif (move_uploaded_file($fileTmpPath, $dest_path)) {
                            if ($currentDbUserPhoto && $currentDbUserPhoto !== $uploadedFileName && file_exists($fileSystemProfileUploadDir . $currentDbUserPhoto)) {
                                @unlink($fileSystemProfileUploadDir . $currentDbUserPhoto);
                            }
                            $newProfilePhotoNameToSave = $uploadedFileName; 
                            $photoUploadProcessedSuccessfully = true;
                        } else {
                            $updateMessage = ['type' => 'error', 'text' => 'Could not save uploaded file.'];
                            error_log("move_uploaded_file failed for: " . $dest_path);
                        }
                    }
                } elseif (isset($_FILES['profile_photo_input']) && $_FILES['profile_photo_input']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $updateMessage = ['type' => 'error', 'text' => 'Photo upload error. Code: '. $_FILES['profile_photo_input']['error']];
                }

                if (!isset($updateMessage['type']) || $updateMessage['type'] !== 'error') { 
                    try {
                        $paramsToUpdate = [
                            ':firstName' => $newFirstName, ':lastName' => $newLastName,
                            ':email' => $newEmail, ':phone' => $newPhone,
                            ':userid' => $sessionUserId
                        ];
                        $sqlSetParts = ["firstName = :firstName", "lastName = :lastName", "email = :email", "phone = :phone"];

                        if ($photoUploadProcessedSuccessfully && $newProfilePhotoNameToSave) {
                            $sqlSetParts[] = "profile_photo = :profile_photo";
                            $paramsToUpdate[':profile_photo'] = $newProfilePhotoNameToSave;
                        }
                        
                        $sql = "UPDATE users SET " . implode(", ", $sqlSetParts) . " WHERE userID = :userid";
                        $stmt = $pdo->prepare($sql);
                        
                        if ($stmt->execute($paramsToUpdate)) {
                            $_SESSION["first_name"] = $newFirstName; 
                            $_SESSION["email"] = $newEmail;
                            if ($photoUploadProcessedSuccessfully && $newProfilePhotoNameToSave) {
                                $_SESSION["profile_photo"] = $newProfilePhotoNameToSave;
                            }
                            $sessionFirstName = $newFirstName; 
                            $updateMessage = ['type' => 'success', 'text' => 'Profile updated successfully!'];
                        } else {
                            $updateMessage = ['type' => 'error', 'text' => 'Failed to update profile data.'];
                        }
                    } catch (PDOException $e) {
                        $updateMessage = ['type' => 'error', 'text' => 'Database error updating profile: ' . $e->getMessage()];
                    }
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $updateMessage = ['type' => 'error', 'text' => 'All password fields are required.'];
        } elseif (strlen($newPassword) < 8) {
            $updateMessage = ['type' => 'error', 'text' => 'New password must be at least 8 characters.'];
        } elseif ($newPassword !== $confirmPassword) {
            $updateMessage = ['type' => 'error', 'text' => 'New passwords do not match.'];
        } else {
            try {
                $stmtPass = $pdo->prepare("SELECT password FROM users WHERE userID = :userID_param");
                $stmtPass->bindParam(':userID_param', $sessionUserId, PDO::PARAM_INT);
                $stmtPass->execute();
                $userPassData = $stmtPass->fetch();
                
                if ($userPassData && password_verify($currentPassword, $userPassData['password'])) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE users SET password = :newHashedPassword WHERE userID = :userID_param_update");
                    $updateStmt->bindParam(':newHashedPassword', $hashedPassword);
                    $updateStmt->bindParam(':userID_param_update', $sessionUserId, PDO::PARAM_INT);
                    if ($updateStmt->execute()) {
                        $updateMessage = ['type' => 'success', 'text' => 'Password changed successfully!'];
                    } else {
                        $updateMessage = ['type' => 'error', 'text' => 'Failed to update password in database.'];
                    }
                } else {
                    $updateMessage = ['type' => 'error', 'text' => 'Current password is incorrect.'];
                }
            } catch (PDOException $e) {
                $updateMessage = ['type' => 'error', 'text' => 'Database error changing password: ' . $e->getMessage()];
            }
        }
    }
}

// Load/Re-load user data for display
if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        $stmtUserDisplay = $pdo->prepare("SELECT userID, username, firstName, lastName, email, phone, roleID, profile_photo FROM users WHERE userID = :userID_param");
        $stmtUserDisplay->bindParam(':userID_param', $sessionUserId, PDO::PARAM_INT);
        $stmtUserDisplay->execute();
        $userData = $stmtUserDisplay->fetch();

        if ($userData) {
            if (isset($userData['profile_photo'])) { 
                $_SESSION["profile_photo"] = $userData['profile_photo'];
            }
            
            $userRFIDCards = []; 
            if ($activeSection === 'rfid') {
                $sqlRfid = "SELECT RFID, name, card_type, is_active, rfid_url 
                            FROM rfids 
                            WHERE userID = :current_session_userID"; 

                $paramsRfid = [':current_session_userID' => $sessionUserId];

                if ($rfidStatusFilter === 'active') {
                    $sqlRfid .= " AND is_active = 1";
                } elseif ($rfidStatusFilter === 'inactive') {
                    $sqlRfid .= " AND is_active = 0";
                }
                // For 'all', no additional is_active filter needed
                
                $sqlRfid .= " ORDER BY created_at DESC";

                $stmtRfid = $pdo->prepare($sqlRfid);
                $stmtRfid->execute($paramsRfid);
                $rfidDataFromDb = $stmtRfid->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rfidDataFromDb as $cardData) {
                    $userRFIDCards[] = [
                        'id_pk'           => htmlspecialchars($cardData['RFID']),
                        'rfid_identifier' => htmlspecialchars($cardData['rfid_url']),
                        'name'            => isset($cardData['name']) && !empty($cardData['name']) ? htmlspecialchars($cardData['name']) : 'N/A', 
                        'type'            => htmlspecialchars($cardData['card_type']),
                        'status_bool'     => (bool)$cardData['is_active'],
                        'status_text'     => $cardData['is_active'] ? 'Active' : 'Inactive',
                        'status_class'    => $cardData['is_active'] ? 'active' : 'inactive'
                    ];
                }
            }
        } else {
            $dbErrorMessage = "Could not retrieve your user data. User ID " . htmlspecialchars($sessionUserId) . " not found.";
        }
    } catch (PDOException $e) {
        $dbErrorMessage = "Database error on page load: " . $e->getMessage();
    }
} elseif (!$sessionUserId) {
    $dbErrorMessage = "User session is invalid. Please log in again.";
} elseif (!isset($pdo) || !($pdo instanceof PDO)) {
    $dbErrorMessage = "Database connection is not available.";
}

// Determine correct path for assets and component includes
$pathPrefix = ""; // Assume profile.php is in root
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $pathPrefix = "../"; // If profile.php were in admin/
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?php echo $pathPrefix; ?>imgs/logo.png" type="image/x-icon">
    <title>My Account - <?php echo $sessionFirstName; ?> - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ... (Your existing CSS, including the corrected .container and header .container .navbar styles) ... */
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4;
            --primary-color-rgb: 67, 97, 238; /* For rgba */
            --secondary-color: #3f37c9; --dark-color: #1a1a2e;
            --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --danger-color: #F44336;  
            --present-color-rgb: 67, 170, 139; 
            --info-color-rgb: 33, 150, 243;
            --neutral-color-rgb: 173, 181, 189;
            --present-color: rgb(var(--present-color-rgb));
            --info-color: rgb(var(--info-color-rgb));
            --neutral-color: rgb(var(--neutral-color-rgb));
            --shadow: 0 5px 25px rgba(0,0,0,0.07);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --sidebar-bg: var(--white);
            --sidebar-link-hover-bg: #f0f4ff; 
            --sidebar-link-active-bg: #e6eaff; 
            --sidebar-link-active-border: var(--primary-color);
            --content-bg: var(--white);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.65; color: var(--dark-color); background-color: #f4f7fc; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px; }
        .container { max-width: 1440px; margin: 0 auto; padding: 0 25px; } /* Universal container */
        
        /* HEADER STYLES */
        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; }
        header .container { display: flex; justify-content: space-between; align-items: center; height: 80px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; width:100%;}
        .logo { font-size: 1.8rem; font-weight: 800; color: var(--primary-color); text-decoration: none; display: flex; align-items: center; }
        .logo img.logo-img { height: 45px; margin-right: 0.6rem; }
        .logo span { color: var(--dark-color); font-weight: 600; }
        .nav-links { display: flex; list-style: none; align-items: center; gap: 0.3rem; margin:0; padding:0;}
        .nav-links a:not(.btn-outline) { color: var(--dark-color); text-decoration: none; font-weight: 500; padding: 0.6rem 0.9rem; font-size: 0.9rem; border-radius: 6px; transition: var(--transition); display: inline-flex; align-items: center; }
        .nav-links a:not(.btn-outline):hover, .nav-links a.active-nav-link { color: var(--primary-color); background-color: rgba(var(--primary-color-rgb), 0.07); }
        .nav-links .btn-outline { display: inline-flex; gap: 8px; align-items: center; justify-content: center; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; transition: var(--transition); font-size: 0.85rem; background-color: transparent; border: 2px solid var(--primary-color); color: var(--primary-color); }
        .nav-links .btn-outline:hover { background-color: var(--primary-color); color: var(--white); }
        .nav-user-photo { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; margin-right: 8px; vertical-align: middle; border: 1.5px solid var(--light-gray); }
        .nav-links a .material-symbols-outlined { font-size: 1.4em; vertical-align: middle; margin-right: 6px; line-height: 1; }
        .hamburger { display: none; } 
        .mobile-menu { display: none; } 
        @media (max-width: 992px) { 
            header .container .navbar .nav-links { display: none; } 
            header .container .navbar .hamburger { display: flex; flex-direction:column; justify-content:space-around; width:28px; height:22px; cursor:pointer; background:transparent; border:none; padding:0; z-index:1002;}
            header .container .navbar .hamburger span { display:block; width:100%; height:3px; background-color:var(--dark-color);border-radius:10px; transition:all .3s linear; position:relative; transform-origin:1px;}
            header .container .navbar .hamburger.active span:nth-child(1){transform:rotate(45deg) translate(1px,-1px);}
            header .container .navbar .hamburger.active span:nth-child(2){opacity:0;transform:translateX(20px);}
            header .container .navbar .hamburger.active span:nth-child(3){transform:rotate(-45deg) translate(2px,0px);}
            .mobile-menu {position:fixed;top:0;right:-100%;width:280px;height:100vh;background-color:var(--white);box-shadow:-5px 0 15px rgba(0,0,0,.1);padding:60px 20px 20px;transition:right .4s cubic-bezier(.23,1,.32,1);z-index:1001;display:flex;flex-direction:column;overflow-y:auto}
            .mobile-menu.active{right:0}
            .mobile-links{list-style:none;padding:0;margin:20px 0 0;display:flex;flex-direction:column;gap:.5rem;flex-grow:1}
            .mobile-links li{width:100%}
            .mobile-links a{display:flex;align-items:center;padding:.8rem 1rem;text-decoration:none;color:var(--dark-color);font-size:1rem;border-radius:6px;transition:var(--transition);font-weight:500}
            .mobile-links a:hover,.mobile-links a.active-nav-link{color:var(--primary-color);background-color:rgba(var(--primary-color-rgb),.07)}
            .mobile-menu .btn-outline{width:100%;margin-top:auto;padding-top:.8rem;padding-bottom:.8rem;margin-bottom:1rem;font-size:.9rem}
            .close-btn{position:absolute;top:18px;right:20px;font-size:1.8rem;color:var(--dark-color);cursor:pointer;background:0 0;border:none;padding:5px}
            .mobile-links a .nav-user-photo.mobile-nav-user-photo{width:28px;height:28px;margin-right:10px}
        }
        /* END HEADER STYLES */

        .page-header { padding: 2rem 0; margin-bottom: 2rem; background-color:var(--white); box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .page-header h1 { font-size: 1.8rem; color: var(--dark-color); margin: 0; }
        .db-error-message, .update-message { padding: 1rem 1.2rem; border-radius: 6px; margin-bottom: 1.5rem; font-size:0.9rem; border-left-width: 5px; border-left-style:solid; display:flex; align-items:center; gap:0.8rem;}
        .db-error-message i, .update-message i { font-size:1.2em; }
        .update-message.error, .db-error-message { background-color: #ffebee; color: var(--danger-color); border-left-color:var(--danger-color); }
        .update-message.success { background-color: #e8f5e9; color: var(--success-color); border-left-color: var(--success-color); }

        .account-layout { display: flex; gap: 2.5rem; padding-top: 1.5rem; }
        .account-sidebar { flex: 0 0 280px; background-color: var(--sidebar-bg); padding: 1.8rem; border-radius: 10px; box-shadow: var(--shadow); align-self: flex-start; }
        .account-sidebar h3 { font-size: 1.1rem; color: var(--gray-color); text-transform:uppercase; letter-spacing:0.5px; margin-bottom: 1.5rem; padding-bottom: 0.8rem; border-bottom: 1px solid var(--light-gray); }
        .account-sidebar ul { list-style: none; padding: 0; margin: 0; }
        .account-sidebar ul li a { display: flex; align-items: center; gap: 0.9rem; padding: 0.85rem 1.1rem; text-decoration: none; color: #555; font-weight: 500; font-size: 0.93rem; border-radius: 7px; transition: var(--transition); border-left: 4px solid transparent; margin-bottom:0.5rem;}
        .account-sidebar ul li a:hover { background-color: var(--sidebar-link-hover-bg); color: var(--primary-color); border-left-color: var(--primary-color);}
        .account-sidebar ul li a.active { background-color: var(--sidebar-link-active-bg); color: var(--primary-color); font-weight: 600; border-left-color: var(--sidebar-link-active-border); }
        .account-sidebar ul li a .material-symbols-outlined { font-size: 1.3em; color:var(--gray-color); transition:var(--transition);}
        .account-sidebar ul li a:hover .material-symbols-outlined, .account-sidebar ul li a.active .material-symbols-outlined { color:var(--primary-color); }

        .account-content { flex-grow: 1; background-color: var(--content-bg); padding: 2rem 2.5rem; border-radius: 10px; box-shadow: var(--shadow); }
        .content-section { display: none; } .content-section.active { display: block; }
        .content-section h2 { font-size: 1.5rem; color: var(--dark-color); margin-bottom: 2rem; padding-bottom: 1.2rem; border-bottom: 1px solid var(--light-gray); }

        .profile-info-form .form-row { display: flex; gap: 1.8rem; margin-bottom: 0;}
        .profile-info-form .form-row .form-group { flex: 1; margin-bottom:1.5rem;}
        .profile-picture-group { display:flex; align-items:center; gap:2rem; margin-bottom:2.5rem; padding-bottom:2rem; border-bottom: 1px solid var(--light-gray); }
        .profile-picture-display { text-align:center; flex-shrink:0; }
        .profile-picture { width: 120px; height: 120px; border-radius:50%; object-fit:cover; border:4px solid var(--white); margin-bottom:0rem; box-shadow: 0 4px 15px rgba(0,0,0,0.12);}
        .profile-upload-actions label.btn-outline {font-size:0.9rem; padding:0.7rem 1.2rem; border: 2px solid var(--primary-color); color:var(--primary-color)} 
        .profile-upload-actions label.btn-outline:hover {background-color:var(--primary-color); color:var(--white);}
        .profile-upload-actions input[type="file"] { display:none;}
        .profile-upload-actions .placeholder-text {font-size:0.8rem; color:var(--gray-color); margin-top:0.5rem; display:block;}

        .form-group { margin-bottom: 1.5rem;}
        .profile-info-form label, .change-password-form label {display:block; margin-bottom:0.5rem; font-weight:500; font-size:0.9rem; color: var(--dark-color);}
        .profile-info-form input[type="text"], .profile-info-form input[type="email"], .profile-info-form input[type="tel"],
        .change-password-form input[type="password"] { width: 100%; padding: 0.85rem 1.2rem; border:1px solid #d0d5dd; border-radius:6px; font-size:0.95rem; transition: var(--transition); box-shadow: 0 1px 2px rgba(0,0,0,0.04); background-color:var(--white); }
        .profile-info-form input:focus, .change-password-form input:focus {border-color:var(--primary-color); box-shadow: 0 0 0 3.5px rgba(67, 97, 238,0.2);} /* Corrected var */
        .form-group input[readonly] { background-color: #f0f2f5; cursor:not-allowed; color:var(--gray-color); }
        .form-actions { margin-top:2rem; text-align:right; }
        .form-actions .btn {min-width: 160px; padding: 0.75rem 1.5rem; font-size:0.9rem;}

        .rfid-filter-container { display: flex; align-items: center; gap: 0.8rem; margin-bottom: 1.8rem; padding-bottom: 1rem; border-bottom: 1px solid var(--light-gray); }
        .rfid-filter-container label { font-weight: 500; font-size: 0.95rem; color: var(--gray-color); }
        .rfid-filter-container select { padding: 0.7rem 1rem; border: 1px solid #ccd0d5; border-radius: 6px; font-size: 0.9rem; background-color: var(--white); box-shadow: 0 1px 2px rgba(0,0,0,0.03); min-width: 200px; cursor: pointer; transition: var(--transition); }
        .rfid-filter-container select:focus { border-color:var(--primary-color); box-shadow: 0 0 0 3px rgba(67, 97, 238,0.2); outline:none; } /* Corrected var */

        .rfid-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.8rem; }
        .rfid-card-item { background-color: var(--white); border: 1px solid #e0e4e8; border-radius: 10px; padding: 1.5rem; text-align: center; transition: var(--transition); box-shadow: 0 3px 12px rgba(0,0,0,0.05); }
        .rfid-card-item:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.08); transform: translateY(-4px);}
        .rfid-card-image { width: 100%; max-width: 230px; height: auto; border-radius: 8px; margin-bottom: 1.2rem; border: 1px solid #d0d5dd; display:block; margin-left:auto; margin-right:auto; background-color:var(--light-gray);}
        .rfid-card-info h4 { font-size:1.05rem; color:var(--dark-color); margin-bottom:0.4rem; font-weight:600;}
        .rfid-card-info p { font-size:0.9rem; color:var(--gray-color); margin-bottom:0.3rem;}
        .rfid-card-status { display:inline-flex; align-items:center; gap:0.5rem; font-size:0.85rem; font-weight:500; padding:0.35rem 0.9rem; border-radius:20px; margin-top:0.8rem; border:1px solid transparent;}
        .rfid-card-status.active { background-color:rgba(var(--present-color-rgb),0.1); color:var(--present-color); border-color: rgba(var(--present-color-rgb),0.3);}
        .rfid-card-status.inactive { background-color:rgba(var(--neutral-color-rgb),0.1); color:var(--neutral-color); border-color: rgba(var(--neutral-color-rgb),0.3);}
        .rfid-card-status .material-symbols-outlined { font-size:1.2em; }
        .no-cards-msg {text-align:center; padding: 2.5rem 1rem; color:var(--gray-color); font-size:0.95rem; background-color: #fdfdfd; border-radius: 4px; border: 1px dashed var(--light-gray);}
        
        footer { /* ... Your full footer styles ... */ }
        @media (max-width: 992px) { .account-layout { flex-direction: column; } .account-sidebar { width: 100%; margin-bottom:2rem; } }
        @media (max-width: 768px) { .profile-info-form .form-row { flex-direction:column; gap:0; margin-bottom:0;} .profile-info-form .form-row .form-group {margin-bottom:1.5rem;} .profile-picture-group{flex-direction:column; align-items:center; gap:1rem;}.account-content{padding:1.5rem;} }
 
        @media (max-width: 992px) { 
            .account-layout { flex-direction: column; } 
            .account-sidebar { width: 100%; margin-bottom:2rem; flex: 0 0 auto; } /* Adjust flex for stacking */
        }
        @media (max-width: 768px) { 
            .profile-info-form .form-row { flex-direction:column; gap:0; margin-bottom:0;} 
            .profile-info-form .form-row .form-group {margin-bottom:1.5rem;} 
            .profile-picture-group{flex-direction:column; align-items:center; gap:1rem;}
            .account-content{padding:1.5rem;} 
            .rfid-cards-grid { grid-template-columns: 1fr; } /* Stack RFID cards on small screens */
        }
    </style>
</head>
<body>
    <?php require_once $pathPrefix . "components/header-employee-panel.php"; ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>My Account</h1>
                <p class="sub-heading">Manage your profile, password, and RFID cards.</p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if ($dbErrorMessage && empty($userData)): ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $dbErrorMessage; ?></div>
            <?php elseif ($updateMessage): ?>
                <div class="update-message <?php echo $updateMessage['type']; ?>" role="alert">
                    <i class="<?php echo ($updateMessage['type'] === 'success' ? 'fas fa-check-circle' : 'fas fa-times-circle'); ?>"></i> 
                    <?php echo htmlspecialchars($updateMessage['text']); ?>
                </div>
            <?php endif; ?>

            <?php if ($userData): ?>
            <div class="account-layout">
                <aside class="account-sidebar">
                    <h3>Settings</h3>
                    <ul>
                        <li><a href="?section=profile" class="<?php if ($activeSection === 'profile') echo 'active'; ?>"><span class="material-symbols-outlined">manage_accounts</span> Profile Information</a></li>
                        <li><a href="?section=password" class="<?php if ($activeSection === 'password') echo 'active'; ?>"><span class="material-symbols-outlined">lock_reset</span> Change Password</a></li>
                        <li><a href="?section=rfid" class="<?php if ($activeSection === 'rfid') echo 'active'; ?>"><span class="material-symbols-outlined">credit_card</span> My RFID Cards</a></li>
                        <?php if ($sessionRole === 'admin'): ?>
                            <li style="margin-top: 1.5rem; border-top:1px solid var(--light-gray); padding-top:1rem;">
                                <a href="<?php echo $pathPrefix; ?>admin-dashboard.php" style="color: var(--secondary-color); font-weight:bold;">
                                    <span class="material-symbols-outlined">admin_panel_settings</span> Admin Panel
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </aside>

                <section class="account-content">
                    <div id="profile-section" class="content-section <?php if ($activeSection === 'profile') echo 'active'; ?>">
                        <h2>Profile Details</h2>
                        <form method="POST" action="profile.php?section=profile" class="profile-info-form" enctype="multipart/form-data">
                                 <input type="hidden" name="current_profile_photo_filename" value="<?php echo htmlspecialchars($userData['profile_photo'] ?? ''); ?>">
                                <div class="profile-picture-group">
                                    <div class="profile-picture-display">
                                        <img src="<?php 
                                            $defaultAvatarWebPath = $webProfileUploadDir . 'default_avatar.png';
                                            $photoDisplayPath = $defaultAvatarWebPath; 
                                            
                                            if (!empty($userData['profile_photo'])) {
                                                $safeFileName = basename($userData['profile_photo']); // Sanitize
                                                $potentialUserPhotoWebPath = $webProfileUploadDir . $safeFileName;
                                                if (file_exists($fileSystemProfileUploadDir . $safeFileName)) {
                                                    $photoDisplayPath = htmlspecialchars($potentialUserPhotoWebPath);
                                                }
                                            }
                                            echo $photoDisplayPath . '?' . time(); 
                                        ?>" alt="Profile Picture" class="profile-picture" id="profileImagePreview">
                                    </div>
                                    <div class="profile-upload-actions">
                                        <label for="profile_photo_input" class="btn btn-outline"> 
                                            <span class="material-symbols-outlined">photo_camera</span> Update photo
                                        </label>
                                        <input type="file" name="profile_photo_input" id="profile_photo_input" accept="image/jpeg, image/png, image/gif">
                                        <small class="placeholder-text">Max 2MB. JPG, PNG, GIF.</small>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group"><label for="firstName">First Name</label><input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($userData['firstName']); ?>" required></div>
                                    <div class="form-group"><label for="lastName">Last Name</label><input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($userData['lastName']); ?>" required></div>
                                </div>
                                <div class="form-group"><label>Role</label><input type="text" value="<?php echo ucfirst(htmlspecialchars($userData['roleID'])); ?>" readonly ></div>
                                <div class="form-row">
                                    <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required></div>
                                    <div class="form-group"><label for="phone">Phone</label><input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?: ''); ?>" placeholder="Optional"></div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" name="update_profile" class="btn btn-primary"><span class="material-symbols-outlined">save</span> Save Profile</button>
                                </div>
                            </form>
                    </div>

                    <div id="password-section" class="content-section <?php if ($activeSection === 'password') echo 'active'; ?>">
                         <h2>Change Your Password</h2>
                         <form method="POST" action="profile.php?section=password" class="change-password-form">
                            <div class="form-group"><label for="current_password">Current Password</label><input type="password" id="current_password" name="current_password" required></div>
                            <div class="form-group"><label for="new_password">New Password</label><input type="password" id="new_password" name="new_password" required minlength="8" placeholder="Minimum 8 characters"></div>
                            <div class="form-group"><label for="confirm_password">Confirm Password</label><input type="password" id="confirm_password" name="confirm_password" required></div>
                            <div class="form-actions">
                                <button type="submit" name="change_password" class="btn btn-primary"><span class="material-symbols-outlined">lock_reset</span> Set New Password</button>
                            </div>
                        </form>
                    </div>

                    <div id="rfid-section" class="content-section <?php if ($activeSection === 'rfid') echo 'active'; ?>">
                        <h2>My RFID Cards</h2>
                        <div class="rfid-filter-container">
                            <label for="rfid_status_filter">View:</label>
                            <select id="rfid_status_filter" name="rfid_status_filter" onchange="filterRfidCards(this.value)">
                                <option value="all" <?php if ($rfidStatusFilter === 'all') echo 'selected'; ?>>All My Cards</option>
                                <option value="active" <?php if ($rfidStatusFilter === 'active') echo 'selected'; ?>>Active Cards</option>
                                <option value="inactive" <?php if ($rfidStatusFilter === 'inactive') echo 'selected'; ?>>Inactive Cards</option>
                            </select>
                        </div>

                        <?php if (!empty($userRFIDCards)): ?>
                            <div class="rfid-cards-grid">
                                <?php foreach($userRFIDCards as $card): ?>
                                <div class="rfid-card-item">
                                    <img src="<?php echo $pathPrefix; ?>imgs/wavepass_card.png" alt="WavePass RFID Card" class="rfid-card-image">
                                    <div class="rfid-card-info">
                                    <h4>Card UID: <?php echo $card['rfid_identifier']; ?></h4>
                                    <?php if ($card['name'] !== 'N/A'): ?>
                                        <p>Label: <?php echo $card['name']; ?></p>
                                    <?php endif; ?>
                                    <p>Type: <?php echo $card['type']; ?></p>
                                    <p class="rfid-card-status <?php echo $card['status_class']; ?>">
                                        <span class="material-symbols-outlined"><?php echo ($card['status_bool'] ? 'verified_user' : 'do_not_disturb_on'); ?></span>
                                        <?php echo $card['status_text']; ?>
                                    </p>
                                </div>
                                </div>  
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-cards-msg">
                                <?php 
                                if ($rfidStatusFilter === 'active') echo 'You have no active RFID cards assigned.';
                                elseif ($rfidStatusFilter === 'inactive') echo 'You have no inactive RFID cards assigned.';
                                else echo 'You have no RFID cards assigned.';
                                ?>
                            </p>
                        <?php endif; ?>
                         <p class="placeholder-text" style="margin-top:1.8rem; text-align:center; font-size:0.85rem;">
                            <i class="fas fa-info-circle"></i> For new cards or issues, please contact administration.
                         </p>
                    </div>
                </section>
            </div>
            <?php elseif ($dbErrorMessage && !$userData): ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $dbErrorMessage; ?></div>
                <p><a href="<?php echo $pathPrefix; ?>dashboard.php" class="btn btn-primary" style="margin-top:1rem;">Back to Dashboard</a></p>
            <?php endif; ?>
        </div>
    </main>

    <?php 
        $footerPath = $pathPrefix . "components/footer.php"; 
        // If you have a specific admin footer and are in admin section, you might adjust:
        // if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
        //    $footerPath = $pathPrefix . "components/footer-admin.php"; 
        // }
        require_once $footerPath; 
    ?>

    <script>
        // Ensure your global header/mobile menu JS is included, either here or in the header component
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');
        // const closeMenu = document.getElementById('closeMenu'); // Make sure this ID exists in your header
        const body = document.body;

        if (hamburger && mobileMenu) {
            const closeMenuBtnInMobile = mobileMenu.querySelector('.close-btn'); // Or by ID if it has one

            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                mobileMenu.classList.toggle('active');
                body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
            });

            if(closeMenuBtnInMobile){
                closeMenuBtnInMobile.addEventListener('click', () => {
                    hamburger.classList.remove('active');
                    mobileMenu.classList.remove('active');
                    body.style.overflow = '';
                });
            }
            
            mobileMenu.querySelectorAll('ul.mobile-links a').forEach(link => {
                link.addEventListener('click', () => {
                     if (mobileMenu.classList.contains('active')) {
                        hamburger.classList.remove('active');
                        mobileMenu.classList.remove('active');
                        body.style.overflow = '';
                    }
                });
            });
        }

        // Header shadow
        const headerEl = document.querySelector('header');
        if (headerEl) { 
            let initialHeaderShadow = getComputedStyle(headerEl).boxShadow;
            window.addEventListener('scroll', () => {
                headerEl.style.boxShadow = (window.scrollY > 10) ? 
                    (getComputedStyle(document.documentElement).getPropertyValue('--shadow').trim() || '0 4px 10px rgba(0,0,0,0.05)') : 
                    initialHeaderShadow;
            });
        }

        // Profile photo preview
        const profilePhotoInput = document.getElementById('profile_photo_input');
        const imagePreview = document.getElementById('profileImagePreview');
        
        if (profilePhotoInput && imagePreview) {
            profilePhotoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        imagePreview.src = event.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // RFID Filter Function
        function filterRfidCards(status) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('section', 'rfid'); 
            if (status === 'all') { 
                currentUrl.searchParams.delete('rfid_status'); // Remove to show all for user
            } else {
                currentUrl.searchParams.set('rfid_status', status);
            }
            window.location.href = currentUrl.toString();
        }
    </script>
</body>
</html>