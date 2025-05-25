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

require_once '../db.php'; 

$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'User';
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
$sessionRole = isset($_SESSION["role_name"]) ? htmlspecialchars($_SESSION["role_name"]) : (isset($_SESSION["role"]) ? htmlspecialchars($_SESSION["role"]) : 'employee'); // Použije role_name pokud existuje
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
// Logika pro $projectBasePath, WEB_ROOT_PATH, $fileSystemProfileUploadDir, $webProfileUploadDir zůstává stejná
$projectBasePath = ''; 
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$scriptName = $_SERVER['SCRIPT_NAME']; // např. /admin-profile.php nebo /admin/admin-profile.php
$scriptDir = dirname($scriptName); // např. / nebo /admin

// Pokud je skript v rootu, $projectBasePath je '', jinak je to $scriptDir
// Toto předpokládá, že projekt je přímo v document rootu nebo v podadresáři
// a že admin-profile.php je buď v rootu projektu nebo v přímém podadresáři projektu.
if ($scriptDir === '/' || $scriptDir === '\\') {
    $projectBasePath = '';
} else {
    $projectBasePath = rtrim(str_replace('\\', '/', $scriptDir), '/');
}


if (!defined('WEB_ROOT_PATH')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    // $projectBasePath je již relativní k web rootu
    define('WEB_ROOT_PATH', rtrim($protocol . $host . $projectBasePath, '/') . '/');
}

if (!defined('PROFILE_UPLOAD_DIR_FROM_PROJECT_ROOT')) define('PROFILE_UPLOAD_DIR_FROM_PROJECT_ROOT', 'profile_photos/');
$webProfileUploadDir = WEB_ROOT_PATH . ltrim(PROFILE_UPLOAD_DIR_FROM_PROJECT_ROOT, '/');
// Systémová cesta: $docRoot (např. /var/www/html) + $projectBasePath (např. /bures.pa.2022/wavepass) + /profile_photos/
$fileSystemProfileUploadDir = $docRoot . $projectBasePath . '/' . ltrim(PROFILE_UPLOAD_DIR_FROM_PROJECT_ROOT, '/');
$fileSystemProfileUploadDir = rtrim($fileSystemProfileUploadDir, '/') . '/';


// ... (zbytek PHP logiky pro kontrolu adresáře, nahrávání, načítání dat - zůstává stejný jako v předchozí verzi) ...
// Ensure upload directory exists and is writable - KONTROLA ZŮSTÁVÁ DŮLEŽITÁ
$uploadDirIsOk = false;
if (!is_dir($fileSystemProfileUploadDir)) {
    if (!@mkdir($fileSystemProfileUploadDir, 0775, true)) { 
        $mkdirError = error_get_last();
        error_log("CRITICAL ERROR: Failed to create profile photo directory (" . $fileSystemProfileUploadDir . "). Error: " . ($mkdirError['message'] ?? 'Unknown error'));
        if($activeSection === 'profile' && !$dbErrorMessage) { $dbErrorMessage = "Profile photo directory setup error. Please contact support."; }
    } else {
        if (!is_writable($fileSystemProfileUploadDir)) {
             error_log("WARNING: Profile photo directory created BUT IS NOT WRITABLE: " . $fileSystemProfileUploadDir);
             if($activeSection === 'profile' && !$dbErrorMessage) { $dbErrorMessage = "Profile photo directory setup error (not writable after creation). Please contact support."; }
        } else {
            $uploadDirIsOk = true;
        }
    }
} elseif (!is_writable($fileSystemProfileUploadDir)) {
    error_log("WARNING: Profile photo directory exists but is not writable: " . $fileSystemProfileUploadDir);
    if($activeSection === 'profile' && !$dbErrorMessage) { 
        // $dbErrorMessage = "Profile photo directory is not writable. Please contact support."; 
    }
} else {
    $uploadDirIsOk = true; 
}


if (!defined('MAX_PHOTO_SIZE')) define('MAX_PHOTO_SIZE', 2 * 1024 * 1024); 
if (!defined('ALLOWED_PHOTO_TYPES')) define('ALLOWED_PHOTO_TYPES', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif']);
if (!defined('DEFAULT_AVATAR_FILENAME')) define('DEFAULT_AVATAR_FILENAME', 'default_avatar.jpg');

// --- Form Submission Handling ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($pdo) && $sessionUserId) {
    // ... (kompletní logika zpracování formuláře z předchozí odpovědi) ...
    $stmtCurrentUserData = $pdo->prepare("SELECT email, profile_photo FROM users WHERE userID = :userid");
    $stmtCurrentUserData->execute([':userid' => $sessionUserId]);
    $currentUserDataForUpdate = $stmtCurrentUserData->fetch();
    $currentDbUserPhoto = $currentUserDataForUpdate ? $currentUserDataForUpdate['profile_photo'] : null;
    if($stmtCurrentUserData) $stmtCurrentUserData->closeCursor();

    if (isset($_POST['update_profile'])) {
        $newFirstName = trim($_POST['firstName']);
        $newLastName = trim($_POST['lastName']);
        $newEmail = trim($_POST['email']);
        $newPhone = trim($_POST['phone']);
        $newProfilePhotoNameToSave = $currentDbUserPhoto; 

        if (empty($newFirstName) || empty($newLastName) || empty($newEmail)) {
            $updateMessage = ['type' => 'error', 'text' => 'First name, last name, and email are required.'];
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $updateMessage = ['type' => 'error', 'text' => 'Invalid email format.'];
        } else {
            $currentEmailInDB = $currentUserDataForUpdate ? $currentUserDataForUpdate['email'] : ($_SESSION['email'] ?? '');
            if (strtolower($newEmail) !== strtolower($currentEmailInDB)) {
                $stmtCheckEmail = $pdo->prepare("SELECT userID FROM users WHERE LOWER(email) = LOWER(:email) AND userID != :sessionUserID");
                $stmtCheckEmail->execute([':email'=> $newEmail, ':sessionUserID' => $sessionUserId]);
                if ($stmtCheckEmail->fetch()) {
                    $updateMessage = ['type' => 'error', 'text' => 'This email address is already in use.'];
                }
                if($stmtCheckEmail) $stmtCheckEmail->closeCursor();
            }
        }
            // --- Photo Upload Logic ---
            if (!isset($updateMessage)) { 
                $photoUploadProcessedSuccessfully = false;
                if (isset($_FILES['profile_photo_input']) && $_FILES['profile_photo_input']['error'] === UPLOAD_ERR_OK) {
                    if (!$uploadDirIsOk) { 
                         $updateMessage = ['type' => 'error', 'text' => 'Cannot upload photo: Directory issue. Please contact support.'];
                    } else {
                        $fileTmpPath = $_FILES['profile_photo_input']['tmp_name'];
                        $fileSize = $_FILES['profile_photo_input']['size'];
                        $fileType = mime_content_type($fileTmpPath);

                        if ($fileSize > MAX_PHOTO_SIZE) {
                            $updateMessage = ['type' => 'error', 'text' => 'Image too large (Max 2MB).'];
                        } elseif (!array_key_exists($fileType, ALLOWED_PHOTO_TYPES)) {
                            $updateMessage = ['type' => 'error', 'text' => 'Invalid file type. Allowed: JPG, PNG, GIF. Detected: ' . htmlspecialchars($fileType)];
                        } else {
                            $fileExtension = ALLOWED_PHOTO_TYPES[$fileType];
                            $uploadedFileName = 'user' . $sessionUserId . '_' . time() . '.' . $fileExtension; 
                            $dest_path = $fileSystemProfileUploadDir . $uploadedFileName;

                            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                                if ($currentDbUserPhoto && 
                                    $currentDbUserPhoto !== DEFAULT_AVATAR_FILENAME && 
                                    $currentDbUserPhoto !== $uploadedFileName && 
                                    file_exists($fileSystemProfileUploadDir . $currentDbUserPhoto)) {
                                    @unlink($fileSystemProfileUploadDir . $currentDbUserPhoto);
                                }
                                $newProfilePhotoNameToSave = $uploadedFileName; 
                                $photoUploadProcessedSuccessfully = true;
                            } else {
                                $uploadError = error_get_last();
                                $updateMessage = ['type' => 'error', 'text' => 'Could not save uploaded file. Server error.'];
                                error_log("move_uploaded_file failed for: " . $dest_path . " Error: " . ($uploadError['message'] ?? 'OS error'));
                            }
                        }
                    }
                } elseif (isset($_FILES['profile_photo_input']) && $_FILES['profile_photo_input']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $updateMessage = ['type' => 'error', 'text' => 'Photo upload error. Code: '. $_FILES['profile_photo_input']['error']];
                }

                // --- Database Update Logic ---
                if (!isset($updateMessage['type']) || $updateMessage['type'] !== 'error') { 
                    try {
                        $paramsToUpdate = [
                            ':firstName' => $newFirstName, ':lastName' => $newLastName,
                            ':email' => $newEmail, ':phone' => $newPhone, 
                            ':userid' => $sessionUserId
                        ];
                        $sqlSetParts = ["firstName = :firstName", "lastName = :lastName", "email = :email", "phone = :phone"];

                        if ($photoUploadProcessedSuccessfully && $newProfilePhotoNameToSave && $newProfilePhotoNameToSave !== $currentDbUserPhoto) {
                            $sqlSetParts[] = "profile_photo = :profile_photo";
                            $paramsToUpdate[':profile_photo'] = $newProfilePhotoNameToSave;
                        }
                        
                        if (count($sqlSetParts) > 0) { 
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
                        } else if (!isset($updateMessage)) { 
                             $updateMessage = ['type' => 'info', 'text' => 'No changes were made to your profile.'];
                        }
                    } catch (PDOException $e) {
                        error_log("DB Error updating profile {$sessionUserId}: " . $e->getMessage());
                        $updateMessage = ['type' => 'error', 'text' => 'Database error updating profile. Please try again.'];
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
                $stmtPass->execute([':userID_param' => $sessionUserId]);
                $userPassData = $stmtPass->fetch();
                if($stmtPass) $stmtPass->closeCursor();
                
                if ($userPassData && password_verify($currentPassword, $userPassData['password'])) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE users SET password = :newHashedPassword WHERE userID = :userID_param_update");
                    $updateStmt->execute([
                        ':newHashedPassword' => $hashedPassword,
                        ':userID_param_update' => $sessionUserId
                    ]);
                    if ($updateStmt->rowCount() > 0) {
                        $updateMessage = ['type' => 'success', 'text' => 'Password changed successfully!'];
                    } else {
                        $updateMessage = ['type' => 'error', 'text' => 'Failed to update password. No changes made or an issue occurred.'];
                    }
                } else {
                    $updateMessage = ['type' => 'error', 'text' => 'Current password is incorrect.'];
                }
            } catch (PDOException $e) {
                error_log("DB Error changing password for user {$sessionUserId}: " . $e->getMessage());
                $updateMessage = ['type' => 'error', 'text' => 'Database error changing password. Please try again.'];
            }
        }
    }
}


// Load/Re-load user data for display
if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        $stmtUserDisplay = $pdo->prepare(
            "SELECT userID, username, firstName, lastName, email, phone, profile_photo, roleID 
             FROM users
             WHERE userID = :userID_param"
        );
        $stmtUserDisplay->bindParam(':userID_param', $sessionUserId, PDO::PARAM_INT);
        $stmtUserDisplay->execute();
        $userData = $stmtUserDisplay->fetch();

        if ($userData) {
            if (isset($userData['roleID'])) { 
                $_SESSION["role_name"] = $userData['roleID']; 
                $sessionRole = htmlspecialchars($userData['roleID']); 
            }
            if (empty($userData['profile_photo'])) {
                $userData['profile_photo'] = DEFAULT_AVATAR_FILENAME; 
            }
            if (isset($userData['profile_photo']) && (!isset($_SESSION['profile_photo']) || $_SESSION['profile_photo'] !== $userData['profile_photo'])) { 
                $_SESSION["profile_photo"] = $userData['profile_photo'];
            }
            
            // RFID Cards loading (only if section is 'rfid')
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
                $sqlRfid .= " ORDER BY created_at DESC";

                $stmtRfid = $pdo->prepare($sqlRfid);
                $stmtRfid->execute($paramsRfid);
                $rfidDataFromDb = $stmtRfid->fetchAll(PDO::FETCH_ASSOC);
                if ($stmtRfid) $stmtRfid->closeCursor();

                foreach ($rfidDataFromDb as $cardData) {
                    $userRFIDCards[] = [
                        'id_pk'           => htmlspecialchars($cardData['RFID']),
                        'rfid_identifier' => htmlspecialchars($cardData['rfid_url'] ?? $cardData['RFID']),
                        'name'            => isset($cardData['name']) && !empty($cardData['name']) ? htmlspecialchars($cardData['name']) : 'N/A', 
                        'type'            => htmlspecialchars($cardData['card_type'] ?? 'Standard'),
                        'status_bool'     => (bool)$cardData['is_active'],
                        'status_text'     => $cardData['is_active'] ? 'Active' : 'Inactive',
                        'status_class'    => $cardData['is_active'] ? 'active' : 'inactive'
                    ];
                }
            }
        } else {
            $dbErrorMessage = "Could not retrieve your user data. User ID " . htmlspecialchars($sessionUserId) . " not found.";
        }
        if($stmtUserDisplay) $stmtUserDisplay->closeCursor(); 

    } catch (PDOException $e) {
        error_log("DB Error loading user data {$sessionUserId} in admin-profile.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        $dbErrorMessage = "Database error on page load: " . htmlspecialchars($e->getMessage()) . ". Please contact support.";
    }
} elseif (!$sessionUserId) {
    $dbErrorMessage = "User session is invalid. Please log in again.";
} elseif (!isset($pdo) || !($pdo instanceof PDO)) {
    $dbErrorMessage = "Database connection is not available.";
}

// Cesta k výchozímu avataru pro zobrazení - default avatar je nyní v adresáři profile_photos
$defaultAvatarWebPath = $webProfileUploadDir . DEFAULT_AVATAR_FILENAME; 
$defaultAvatarFileSystemPath = $fileSystemProfileUploadDir . DEFAULT_AVATAR_FILENAME;


// Path prefix for including components
$pathPrefix = ""; 
if (strpos($_SERVER['SCRIPT_FILENAME'], DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR) !== false) {
    $pathPrefix = "../"; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?php echo htmlspecialchars($pathPrefix); ?>imgs/logo.png" type="image/x-icon">
    <title>My Account - <?php echo $sessionFirstName; ?> - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ... (Vaše CSS styly, včetně .profile-picture atd.) ... */
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4;
            --primary-color-rgb: 67, 97, 238; /* For rgba */
            --secondary-color: #3f37c9; --dark-color: #1a1a2e;
            --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --danger-color: #F44336;  --info-color: #2196F3;
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
        main { flex-grow: 1; padding-top: 80px;; }
        .container { max-width: 1440px; margin: 0 auto; padding: 0 25px; }
        
        .page-header { padding: 2rem 0; margin-bottom: 2rem; background-color:var(--white); box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .page-header h1 { font-size: 1.8rem; color: var(--dark-color); margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); margin-top: 0.2rem; }

        .db-error-message, .update-message { padding: 1rem 1.2rem; border-radius: 6px; margin-bottom: 1.5rem; font-size:0.9rem; border-left-width: 5px; border-left-style:solid; display:flex; align-items:center; gap:0.8rem;}
        .db-error-message i, .update-message i { font-size:1.2em; }
        .update-message.error, .db-error-message { background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color:var(--danger-color); }
        .update-message.success { background-color: rgba(76,175,80,0.1); color: var(--success-color); border-left-color: var(--success-color); }
        .update-message.info { background-color: rgba(33,150,243,0.1); color: var(--info-color); border-left-color:var(--info-color); }


        .account-layout { display: flex; gap: 2.5rem; padding-top: 1.5rem; }
        .account-sidebar { flex: 0 0 280px; background-color: var(--sidebar-bg); padding: 1.8rem; border-radius: 10px; box-shadow: var(--shadow); align-self: flex-start; }
        .account-sidebar h3 { font-size: 1.1rem; color: var(--gray-color); text-transform:uppercase; letter-spacing:0.5px; margin-bottom: 1.5rem; padding-bottom: 0.8rem; border-bottom: 1px solid var(--light-gray); }
        .account-sidebar ul { list-style: none; padding: 0; margin: 0; }
        .account-sidebar ul li a { display: flex; align-items: center; gap: 0.9rem; padding: 0.85rem 1.1rem; text-decoration: none; color: #555; font-weight: 500; font-size: 0.93rem; border-radius: 7px; transition: var(--transition); border-left: 4px solid transparent; margin-bottom:0.5rem;}
        .account-sidebar ul li a:hover { background-color: var(--sidebar-link-hover-bg); color: var(--primary-color); border-left-color: var(--primary-color);}
        .account-sidebar ul li a.active { background-color: var(--sidebar-link-active-bg); color: var(--primary-color); font-weight: 600; border-left-color: var(--sidebar-link-active-border); }
        .account-sidebar ul li a .material-symbols-outlined { font-size: 1.3em; color:var(--gray-color); transition:var(--transition);}
        .account-sidebar ul li a:hover .material-symbols-outlined, .account-sidebar ul li a.active .material-symbols-outlined { color:var(--primary-color); }

        .account-content { flex-grow: 1; background-color: var(--content-bg); padding: 2rem 2.5rem; border-radius: 10px; box-shadow: var(--shadow); min-height: 400px;}
        .content-section { display: none; animation: fadeIn 0.4s ease forwards; } 
        .content-section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .content-section h2 { font-size: 1.5rem; color: var(--dark-color); margin-bottom: 2rem; padding-bottom: 1.2rem; border-bottom: 1px solid var(--light-gray); }

        .profile-info-form .form-row { display: flex; gap: 1.8rem; margin-bottom: 0;}
        .profile-info-form .form-row .form-group { flex: 1; margin-bottom:1.5rem;}
        
        .profile-picture-group { display:flex; align-items:center; gap:2rem; margin-bottom:2.5rem; padding-bottom:2rem; border-bottom: 1px solid var(--light-gray); }
        .profile-picture-display { text-align:center; flex-shrink:0; }
        .profile-picture { width: 140px; height: 140px; border-radius:50%; object-fit:cover; border:3px solid var(--primary-color); box-shadow: 0 4px 15px rgba(0,0,0,0.12);}
        
        .profile-upload-actions input[type="file"]#profile_photo_input { display: none; }
        .profile-upload-actions .btn-outline { cursor: pointer; }
        .profile-upload-actions small { display: block; font-size: 0.8rem; color: var(--gray-color); margin-top: 0.5rem; }


        .form-group { margin-bottom: 1.5rem;}
        .form-label {display:block; margin-bottom:0.5rem; font-weight:500; font-size:0.9rem; color: var(--dark-color);}
        .form-control { 
            width: 100%; padding: 0.85rem 1.2rem; border:1px solid #d0d5dd; border-radius:6px; 
            font-size:0.95rem; transition: var(--transition); box-shadow: 0 1px 2px rgba(0,0,0,0.04); 
            background-color:var(--white); 
        }
        .form-control:focus {border-color:var(--primary-color); box-shadow: 0 0 0 3.5px rgba(var(--primary-color-rgb),0.2); outline:none;}
        .form-control[readonly] { background-color: #f0f2f5; cursor:not-allowed; color:var(--gray-color); }
        
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 0.75rem 1.5rem; font-size: 0.9rem; font-weight: 600;
            border-radius: 6px; cursor: pointer; transition: var(--transition);
            text-decoration: none; border: 2px solid transparent;
        }
        .btn-primary { background-color: var(--primary-color); color: white; border-color: var(--primary-color); }
        .btn-primary:hover { background-color: var(--primary-dark); transform: translateY(-1px); }
        .btn-outline { background-color: transparent; border-color: var(--primary-color); color: var(--primary-color); }
        .btn-outline:hover { background-color: var(--primary-color); color: var(--white); transform: translateY(-1px); }
        .form-actions { margin-top:2rem; text-align:right; display:flex; justify-content:flex-end; gap: 1rem;}
        .form-actions .btn {min-width: 160px;}

        .rfid-filter-container { display: flex; align-items: center; gap: 0.8rem; margin-bottom: 1.8rem; padding-bottom: 1rem; border-bottom: 1px solid var(--light-gray); }
        .rfid-filter-container label { font-weight: 500; font-size: 0.95rem; color: var(--gray-color); }
        .rfid-filter-container select { padding: 0.7rem 1rem; border: 1px solid #ccd0d5; border-radius: 6px; font-size: 0.9rem; background-color: var(--white); box-shadow: 0 1px 2px rgba(0,0,0,0.03); min-width: 200px; cursor: pointer; transition: var(--transition); }
        .rfid-filter-container select:focus { border-color:var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb),0.2); outline:none; }

        .rfid-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.8rem; }
        .rfid-card-item { background-color: var(--white); border: 1px solid #e0e4e8; border-radius: 10px; padding: 1.5rem; text-align: center; transition: var(--transition); box-shadow: 0 3px 12px rgba(0,0,0,0.05); display:flex; flex-direction:column; align-items:center;}
        .rfid-card-item:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.08); transform: translateY(-4px);}
        .rfid-card-image { width: 100%; max-width: 230px; height: auto; border-radius: 8px; margin-bottom: 1.2rem; border: 1px solid #d0d5dd; display:block; background-color:var(--light-gray);}
        .rfid-card-info h4 { font-size:1.05rem; color:var(--dark-color); margin-bottom:0.4rem; font-weight:600; word-break: break-all;}
        .rfid-card-info p { font-size:0.9rem; color:var(--gray-color); margin-bottom:0.3rem;}
        .rfid-card-status { display:inline-flex; align-items:center; gap:0.5rem; font-size:0.85rem; font-weight:500; padding:0.35rem 0.9rem; border-radius:20px; margin-top:0.8rem; border:1px solid transparent;}
        .rfid-card-status.active { background-color:rgba(var(--present-color-rgb),0.1); color:var(--present-color); border-color: rgba(var(--present-color-rgb),0.3);}
        .rfid-card-status.inactive { background-color:rgba(var(--neutral-color-rgb),0.1); color:var(--neutral-color); border-color: rgba(var(--neutral-color-rgb),0.3);}
        .rfid-card-status .material-symbols-outlined { font-size:1.2em; }
        .no-cards-msg {text-align:center; padding: 2.5rem 1rem; color:var(--gray-color); font-size:0.95rem; background-color: #fdfdfd; border-radius: 4px; border: 1px dashed var(--light-gray);}
        .placeholder-text { color: var(--gray-color); font-size:0.85rem; font-style:italic;}
        
        
        @media (max-width: 992px) { 
            .account-layout { flex-direction: column; } 
            .account-sidebar { width: 100%; margin-bottom:2rem; flex: 0 0 auto; }
        }
        @media (max-width: 768px) { 
            .profile-info-form .form-row { flex-direction:column; gap:0; margin-bottom:0;} 
            .profile-info-form .form-row .form-group {margin-bottom:1.5rem;} 
            .profile-picture-group{flex-direction:column; align-items:center; gap:1.5rem;}
            .account-content{padding:1.5rem;} 
            .rfid-cards-grid { grid-template-columns: 1fr; } 
            .form-actions {justify-content: center; flex-direction:column; gap:0.8rem;}
            .form-actions .btn { width:100%;}
        }
    </style>
</head>
<body>
        <!-- header !-->
        <?php require "../components/header-admin.php"; ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>My Account</h1>
                <p class="sub-heading">Manage your profile, password, and RFID cards.</p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if ($dbErrorMessage && empty($userData)): ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbErrorMessage); ?></div>
            <?php endif; ?>
            <?php if ($updateMessage): ?>
                <div class="update-message <?php echo htmlspecialchars($updateMessage['type']); ?>" role="alert">
                    <i class="<?php echo ($updateMessage['type'] === 'success' ? 'fas fa-check-circle' : ($updateMessage['type'] === 'info' ? 'fas fa-info-circle' : 'fas fa-times-circle')); ?>"></i> 
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
                        <?php 
                        // Použijeme $sessionRole, která je nastavena na začátku skriptu z $_SESSION['role_name'] nebo $_SESSION['roleID']
                        if (strtolower($sessionRole) === 'admin'): 
                        ?>
                            <li style="margin-top: 1.5rem; border-top:1px solid var(--light-gray); padding-top:1rem;">
                                <a href="<?php echo htmlspecialchars($pathPrefix); ?>admin-panel.php" style="color: var(--secondary-color); font-weight:bold;">
                                    <span class="material-symbols-outlined">admin_panel_settings</span> Admin Panel
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </aside>

                <section class="account-content">
                    <!-- Sekce Profile Details -->
                    <div id="profile-section" class="content-section <?php if ($activeSection === 'profile') echo 'active'; ?>">
                        <h2>Profile Details</h2>
                        <form method="POST" action="admin-profile.php?section=profile" class="profile-info-form" enctype="multipart/form-data">
                            <div class="profile-picture-group">
                                <div class="profile-picture-display">
                                    <img src="<?php 
                                        $photoToDisplay = $defaultAvatarWebPath; 
                                        if (!empty($userData['profile_photo']) && $userData['profile_photo'] !== DEFAULT_AVATAR_FILENAME) {
                                            $userPhotoFileName = basename($userData['profile_photo']);
                                            $userPhotoFilesystemPath = $fileSystemProfileUploadDir . $userPhotoFileName;
                                            if (file_exists($userPhotoFilesystemPath)) {
                                                $photoToDisplay = $webProfileUploadDir . $userPhotoFileName; 
                                            } else {
                                                 error_log("Photo file missing for user {$sessionUserId}: {$userPhotoFilesystemPath}. Using default.");
                                            }
                                        } elseif (empty($userData['profile_photo']) && !file_exists($defaultAvatarFileSystemPath)) {
                                            error_log("Default avatar MISSING: {$defaultAvatarFileSystemPath}");
                                        }
                                        echo htmlspecialchars($photoToDisplay) . '?' . time(); 
                                    ?>" alt="Profile Picture" class="profile-picture" id="profileImagePreview">
                                </div>
                                <div class="profile-upload-actions">
                                    <label for="profile_photo_input" class="btn btn-outline"> 
                                        <span class="material-symbols-outlined">photo_camera</span> Update photo
                                    </label>
                                    <input type="file" name="profile_photo_input" id="profile_photo_input" accept="image/jpeg,image/png,image/gif">
                                    <small>Max 2MB. JPG, PNG, GIF.</small>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label for="firstName" class="form-label">First Name</label><input type="text" id="firstName" name="firstName" class="form-control" value="<?php echo htmlspecialchars($userData['firstName']); ?>" required></div>
                                <div class="form-group"><label for="lastName" class="form-label">Last Name</label><input type="text" id="lastName" name="lastName" class="form-control" value="<?php echo htmlspecialchars($userData['lastName']); ?>" required></div>
                            </div>
                            <div class="form-group"><label class="form-label">Role</label><input type="text" class="form-control" value="<?php echo ucfirst(htmlspecialchars($userData['roleID'] ?? $sessionRole)); // roleID z users tabulky ?>" readonly ></div>
                            <div class="form-row">
                                <div class="form-group"><label for="email" class="form-label">Email</label><input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($userData['email']); ?>" required></div>
                                <div class="form-group"><label for="phone" class="form-label">Phone</label><input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($userData['phone'] ?: ''); ?>" placeholder="Optional"></div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="update_profile" class="btn btn-primary"><span class="material-symbols-outlined">save</span> Save Changes</button>
                            </div>
                        </form>
                    </div>

                    <!-- Sekce Change Password -->
                    <div id="password-section" class="content-section <?php if ($activeSection === 'password') echo 'active'; ?>">
                         <h2>Change Your Password</h2>
                         <form method="POST" action="admin-profile.php?section=password" class="change-password-form">
                            <div class="form-group"><label for="current_password" class="form-label">Current Password</label><input type="password" id="current_password" name="current_password" class="form-control" required></div>
                            <div class="form-group"><label for="new_password" class="form-label">New Password</label><input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" placeholder="Minimum 8 characters"></div>
                            <div class="form-group"><label for="confirm_password" class="form-label">Confirm Password</label><input type="password" id="confirm_password" name="confirm_password" class="form-control" required></div>
                            <div class="form-actions">
                                <button type="submit" name="change_password" class="btn btn-primary"><span class="material-symbols-outlined">lock_reset</span> Change Password</button>
                            </div>
                        </form>
                    </div>

                    <!-- Sekce My RFID Cards -->
                    <div id="rfid-section" class="content-section <?php if ($activeSection === 'rfid') echo 'active'; ?>">
                        <h2>My RFID Cards</h2>
                        <div class="rfid-filter-container">
                            <label for="rfid_status_filter">View:</label>
                            <select id="rfid_status_filter" name="rfid_status_filter" onchange="filterRfidCards(this.value)">
                                <option value="all" <?php if ($rfidStatusFilter === 'all' || $rfidStatusFilter === null) echo 'selected'; ?>>All My Cards</option>
                                <option value="active" <?php if ($rfidStatusFilter === 'active') echo 'selected'; ?>>Active Cards</option>
                                <option value="inactive" <?php if ($rfidStatusFilter === 'inactive') echo 'selected'; ?>>Inactive Cards</option>
                            </select>
                        </div>

                        <?php if (!empty($userRFIDCards)): ?>
                            <div class="rfid-cards-grid">
                                <?php foreach($userRFIDCards as $card): ?>
                                <div class="rfid-card-item">
                                    <img src="<?php echo htmlspecialchars($pathPrefix); ?>imgs/wavepass_card.png" alt="WavePass RFID Card" class="rfid-card-image">
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
            <?php elseif (!$userData && $dbErrorMessage ): ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbErrorMessage); ?></div>
                <p><a href="<?php echo htmlspecialchars($pathPrefix); ?>dashboard.php" class="btn btn-primary" style="margin-top:1rem;">Back to Dashboard</a></p>
            <?php elseif (!$userData && !$dbErrorMessage): ?>
                 <p class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> Error: Unable to load user data. Session might be invalid or user not found.</p>
            <?php endif; ?>
        </div>
    </main>

    <?php require_once "../components/footer-admin.php"; ?>

    <script>
        // ... (Váš existující JavaScript pro menu, náhled fotky, filtrování RFID) ...
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.getElementById('hamburger');
            const mobileMenu = document.getElementById('mobileMenu');
            const body = document.body;

            if (hamburger && mobileMenu) {
                const closeMenuBtnInMobile = mobileMenu.querySelector('.close-btn');

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
                    link.addEventListener('click', (e) => {
                        if (link.getAttribute('href') === '#' && e) { e.preventDefault(); }
                         if (mobileMenu.classList.contains('active')) {
                            hamburger.classList.remove('active');
                            mobileMenu.classList.remove('active');
                            body.style.overflow = '';
                        }
                    });
                });
            }

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
        });
            
        function filterRfidCards(status) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('section', 'rfid'); 
            if (status === 'all') { 
                currentUrl.searchParams.delete('rfid_status');
            } else {
                currentUrl.searchParams.set('rfid_status', status);
            }
            window.location.href = currentUrl.toString();
        }
    </script>
</body>
</html>