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
// Správné načtení role z DB bude níže, toto je jen fallback
$sessionRole = isset($_SESSION["role_name"]) ? htmlspecialchars($_SESSION["role_name"]) : (isset($_SESSION["role"]) ? htmlspecialchars($_SESSION["role"]) : 'employee');
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
$projectBasePath = ''; 
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/'); // např. /var/www/html
$scriptRelativePath = dirname($_SERVER['SCRIPT_NAME']); // např. /bures.pa.2022/wavepass nebo / pokud je skript v rootu
$projectBasePath = rtrim(str_replace('\\', '/', $scriptRelativePath), '/'); // Cesta od web rootu k adresáři projektu

if (!defined('WEB_ROOT_PATH')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    define('WEB_ROOT_PATH', rtrim($protocol . $host . $projectBasePath, '/') . '/'); // Celá URL k rootu projektu
}

// Název adresáře pro nahrávání fotek (relativní k rootu projektu)
if (!defined('PROFILE_UPLOAD_DIR_FROM_PROJECT_ROOT')) define('PROFILE_UPLOAD_DIR_FROM_PROJECT_ROOT', 'profile_photos/');

// Webová cesta k adresáři pro nahrávání (pro <img> src)
$webProfileUploadDir = WEB_ROOT_PATH . ltrim(PROFILE_UPLOAD_DIR_FROM_PROJECT_ROOT, '/');

// Systémová (absolutní) cesta k adresáři pro nahrávání (pro PHP operace se soubory)
$fileSystemProfileUploadDir = $docRoot . $projectBasePath . '/' . ltrim(PROFILE_UPLOAD_DIR_FROM_PROJECT_ROOT, '/');
$fileSystemProfileUploadDir = rtrim($fileSystemProfileUploadDir, '/') . '/';


// Ensure upload directory exists and is writable - KONTROLA ZŮSTÁVÁ DŮLEŽITÁ
$uploadDirIsOk = false;
if (!is_dir($fileSystemProfileUploadDir)) {
    if (!@mkdir($fileSystemProfileUploadDir, 0775, true)) { // Pokus o vytvoření s oprávněními 0775
        $mkdirError = error_get_last();
        error_log("CRITICAL ERROR: Failed to create profile photo directory (" . $fileSystemProfileUploadDir . "). Error: " . ($mkdirError['message'] ?? 'Unknown error'));
        if($activeSection === 'profile') { $dbErrorMessage = "Profile photo directory setup error. Please contact support."; }
    } else {
        // Check writability after creation (it should be writable by the user who created it - web server user)
        if (!is_writable($fileSystemProfileUploadDir)) {
             error_log("WARNING: Profile photo directory created BUT IS NOT WRITABLE: " . $fileSystemProfileUploadDir);
             if($activeSection === 'profile') { $dbErrorMessage = "Profile photo directory setup error (not writable after creation). Please contact support."; }
        } else {
            $uploadDirIsOk = true;
        }
    }
} elseif (!is_writable($fileSystemProfileUploadDir)) {
    error_log("WARNING: Profile photo directory exists but is not writable: " . $fileSystemProfileUploadDir);
    if($activeSection === 'profile' && !$dbErrorMessage) { // Zobrazit jen pokud už není jiná chyba
        // $dbErrorMessage = "Profile photo directory is not writable. Please contact support."; 
        // Toto chybové hlášení se zobrazuje, i když je chyba v oprávněních na serveru,
        // takže ho ponecháme, ale hlavní oprava je na serveru.
    }
} else {
    $uploadDirIsOk = true; // Adresář existuje a je zapisovatelný
}


if (!defined('MAX_PHOTO_SIZE')) define('MAX_PHOTO_SIZE', 2 * 1024 * 1024); 
if (!defined('ALLOWED_PHOTO_TYPES')) define('ALLOWED_PHOTO_TYPES', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif']);
if (!defined('DEFAULT_AVATAR_FILENAME')) define('DEFAULT_AVATAR_FILENAME', 'default_avatar.jpg'); // Název výchozího avataru

// --- Form Submission Handling ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($pdo) && $sessionUserId) {
    
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

        // ... (Validace jména, příjmení, emailu - zůstává stejná) ...
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
            if (!isset($updateMessage)) { // Proceed only if no prior validation errors
                $photoUploadProcessedSuccessfully = false;
                if (isset($_FILES['profile_photo_input']) && $_FILES['profile_photo_input']['error'] === UPLOAD_ERR_OK) {
                    if (!$uploadDirIsOk) { // Znovu zkontrolujeme stav adresáře
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
                            $uploadedFileName = 'user' . $sessionUserId . '_' . time() . '.' . $fileExtension; // Jednodušší unikátní název
                            $dest_path = $fileSystemProfileUploadDir . $uploadedFileName;

                            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                                // Delete old photo if it exists, is not the default, and is different from new one
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
                            ':email' => $newEmail, ':phone' => $newPhone, // Phone can be empty
                            ':userid' => $sessionUserId
                        ];
                        $sqlSetParts = ["firstName = :firstName", "lastName = :lastName", "email = :email", "phone = :phone"];

                        // Add profile_photo to update only if a new one was successfully processed AND it's different from the old one
                        if ($photoUploadProcessedSuccessfully && $newProfilePhotoNameToSave && $newProfilePhotoNameToSave !== $currentDbUserPhoto) {
                            $sqlSetParts[] = "profile_photo = :profile_photo";
                            $paramsToUpdate[':profile_photo'] = $newProfilePhotoNameToSave;
                        }
                        
                        if (!empty($sqlSetParts)) { // Only update if there's something to update
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
                        } else if (!isset($updateMessage)) { // No data changes and no photo upload
                             $updateMessage = ['type' => 'info', 'text' => 'No changes were made to your profile.'];
                        }
                    } catch (PDOException $e) {
                        error_log("DB Error updating profile {$sessionUserId}: " . $e->getMessage());
                        $updateMessage = ['type' => 'error', 'text' => 'Database error updating profile. Please try again.'];
                    }
                }
            }
        
    } elseif (isset($_POST['change_password'])) {
        // ... (logika pro změnu hesla zůstává stejná) ...
    }
}

// Load/Re-load user data for display
if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        // Získání dat uživatele VČETNĚ ROLE přímo z tabulky 'users'
        $stmtUserDisplay = $pdo->prepare(
            "SELECT userID, username, firstName, lastName, email, phone, profile_photo, roleID 
             FROM users
             WHERE userID = :userID_param"  // Odstraněn LEFT JOIN a r.roleName
        );
        $stmtUserDisplay->bindParam(':userID_param', $sessionUserId, PDO::PARAM_INT);
        $stmtUserDisplay->execute();
        $userData = $stmtUserDisplay->fetch();

        if ($userData) {
            // Aktualizace session role jménem, pokud je dostupné
            // Sloupec 'roleID' z tabulky 'users' obsahuje přímo název role ('employee' nebo 'admin')
            if (isset($userData['roleID'])) { 
                $_SESSION["role_name"] = $userData['roleID']; // Uložte si název role
                $sessionRole = htmlspecialchars($userData['roleID']); // Aktualizace pro zobrazení na stránce
            }
            
            // Nastavení výchozího avataru v $userData, pokud není fotka
            if (empty($userData['profile_photo'])) {
                $userData['profile_photo'] = DEFAULT_AVATAR_FILENAME; 
            }
            // Synchronizace session fotky
             if (isset($userData['profile_photo']) && (!isset($_SESSION['profile_photo']) || $_SESSION['profile_photo'] !== $userData['profile_photo'])) { 
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
                
                $sqlRfid .= " ORDER BY created_at DESC";

                $stmtRfid = $pdo->prepare($sqlRfid);
                $stmtRfid->execute($paramsRfid); // Předpokládá se, že $paramsRfid je definováno
                $rfidDataFromDb = $stmtRfid->fetchAll(PDO::FETCH_ASSOC);

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
                 if($stmtRfid) $stmtRfid->closeCursor(); // Přidáno uzavření kurzoru
            }
        } else {
            $dbErrorMessage = "Could not retrieve your user data. User ID " . htmlspecialchars($sessionUserId) . " not found.";
        }
        if($stmtUserDisplay) $stmtUserDisplay->closeCursor(); // Přidáno uzavření kurzoru

    } catch (PDOException $e) {
        error_log("DB Error loading user data {$sessionUserId}: " . $e->getMessage());
        $dbErrorMessage = "Database error on page load: " . htmlspecialchars($e->getMessage()) . ". Please contact support.";
        error_log("Database Error in profile.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    }
} 
 elseif (!$sessionUserId) {
    // Toto by se nemělo stát, pokud je session check na začátku
    $dbErrorMessage = "User session is invalid. Please log in again.";
    // header("location: login.php"); exit;
} elseif (!isset($pdo) || !($pdo instanceof PDO)) {
    $dbErrorMessage = "Database connection is not available.";
}

// Cesta k výchozímu avataru pro zobrazení
$defaultAvatarWebPath = WEB_ROOT_PATH . 'imgs/' . DEFAULT_AVATAR_FILENAME; // Použijte imgs/ pro default avatar
$defaultAvatarFileSystemPath = $docRoot . $projectBasePath . '/imgs/' . DEFAULT_AVATAR_FILENAME;


// Determine correct path for component includes based on current script's location relative to project root
$pathPrefix = ""; // Assume profile.php is in project root
// If SCRIPT_NAME is /user/profile.php and project root is web root, pathPrefix needs to be ""
// If SCRIPT_NAME is /admin/module/profile.php and project root is web root, pathPrefix needs to be ""
// The key is the relative path from the current script to the project root.
// The current $projectBasePath is path from web_document_root to project_root.
// So, $pathPrefix should be calculated based on how deep the current script is within the project.

// More robust $pathPrefix calculation:
$pathFromProjectRootToCurrentScriptDir = substr(dirname($_SERVER['SCRIPT_FILENAME']), strlen($docRoot . $projectBasePath));
$depth = substr_count(trim($pathFromProjectRootToCurrentScriptDir, '/'), '/');
if (trim($pathFromProjectRootToCurrentScriptDir, '/') != '') $depth += 1;
$pathPrefix = str_repeat('../', $depth);


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
    /* Enhanced Button Styles */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0.8rem 1.5rem;
        font-size: 0.95rem;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        border: 2px solid transparent;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .btn-primary {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    .btn-primary:hover {
        background-color: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(67, 97, 238, 0.3);
    }

    .btn-outline {
        background-color: transparent;
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
    }

    .btn-outline:hover {
        background-color: var(--primary-color);
        color: white;
        transform: translateY(-2px);
    }

    .btn-icon {
        padding: 0.7rem;
        width: 40px;
        height: 40px;
        border-radius: 50%;
    }

    /* Form Improvements */
    .form-actions {
        margin-top: 2.5rem;
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
    }

    /* Enhanced Input Fields */
    .form-group input:not([readonly]) {
        background-color: #f8f9fa;
        transition: all 0.3s ease;
    }

    .form-group input:focus:not([readonly]) {
        background-color: white;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
    }

    /* Card Improvements */
    .rfid-card-item {
        position: relative;
        overflow: hidden;
        border: none;
    }

    .rfid-card-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
    }

    .rfid-card-image {
        transition: transform 0.3s ease;
    }

    .rfid-card-item:hover .rfid-card-image {
        transform: scale(1.05);
    }

    /* Section Transitions */
    .content-section {
        animation: fadeIn 0.4s ease forwards;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0.4rem 0.9rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .status-badge.active {
        background-color: rgba(76, 175, 80, 0.1);
        color: #2e7d32;
    }

    .status-badge.inactive {
        background-color: rgba(244, 67, 54, 0.1);
        color: #c62828;
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .form-actions {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
        }
        
        .rfid-card-item {
            padding: 1.2rem;
        }
    }

    /* Loading State */
    .btn-loading {
        position: relative;
        pointer-events: none;
    }

    .btn-loading::after {
        content: '';
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 1s ease infinite;
        margin-left: 8px;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

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




        /* Profile Picture Upload Styles */
        .profile-upload-actions {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }
        
        #profile_photo_input {
            display: none;
        }
        
        .profile-upload-actions .btn-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background-color: transparent;
            font-weight: 600;
        }
        
        .profile-upload-actions .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .profile-upload-actions small {
            display: block;
            font-size: 0.8rem;
            color: var(--gray-color);
            margin-top: 0.2rem;
        }
        
        .profile-picture {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
        }
        
        .profile-picture:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        /* Form Button Improvements */
        .form-actions .btn-primary {
            padding: 0.9rem 1.8rem;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .form-actions .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .profile-picture-group {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-upload-actions {
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <?php 
        $headerComponent = $pathPrefix . "components/header-employee-panel.php";
        if (file_exists($headerComponent)) {
            require_once $headerComponent; 
        } else {
            echo "<!-- Header not found: " . htmlspecialchars($headerComponent) . " -->";
            echo "<header>HEADER MISSING</header>"; // Fallback
        }
    ?>

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
                        <?php // Zde předpokládáme, že $sessionRole je již název role (např. "admin", "employee")
                        if (strtolower($sessionRole) === 'admin'): ?>
                            <li style="margin-top: 1.5rem; border-top:1px solid var(--light-gray); padding-top:1rem;">
                                <a href="<?php echo htmlspecialchars($pathPrefix); ?>admin-dashboard.php" style="color: var(--secondary-color); font-weight:bold;">
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
                            <div class="profile-picture-group">
                                <div class="profile-picture-display">
                                    <img src="<?php 
                                        $photoToDisplay = $defaultAvatarWebPath; // Výchozí avatar z imgs/
                                        if (!empty($userData['profile_photo']) && $userData['profile_photo'] !== DEFAULT_AVATAR_FILENAME) {
                                            $userPhotoFileName = basename($userData['profile_photo']);
                                            $userPhotoFilesystemPath = $fileSystemProfileUploadDir . $userPhotoFileName;
                                            if (file_exists($userPhotoFilesystemPath)) {
                                                $photoToDisplay = $webProfileUploadDir . $userPhotoFileName; // Fotka z profile_photos/
                                            } else {
                                                 error_log("Photo file missing for user {$sessionUserId}: {$userPhotoFilesystemPath}. Using default.");
                                            }
                                        } elseif (empty($userData['profile_photo']) && !file_exists($defaultAvatarFileSystemPath)) {
                                            // Pokud i výchozí avatar v imgs/ chybí
                                            error_log("Default avatar MISSING: {$defaultAvatarFileSystemPath}");
                                            // $photoToDisplay zůstává $defaultAvatarWebPath, prohlížeč ukáže broken image
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
                                <div class="form-group"><label for="firstName">First Name</label><input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($userData['firstName']); ?>" required></div>
                                <div class="form-group"><label for="lastName">Last Name</label><input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($userData['lastName']); ?>" required></div>
                            </div>
                            <div class="form-group"><label>Role</label><input type="text" value="<?php echo ucfirst(htmlspecialchars($userData['roleName'] ?? $sessionRole)); ?>" readonly ></div>
                            <div class="form-row">
                                <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required></div>
                                <div class="form-group"><label for="phone">Phone</label><input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?: ''); ?>" placeholder="Optional"></div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="update_profile" class="btn btn-primary"><span class="material-symbols-outlined">save</span> Save Changes</button>
                            </div>
                        </form>
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

    <?php 
        $footerPath = $pathPrefix . "components/footer.php"; 
        require_once $footerPath; 
    ?>

    <script>
        // Mobile menu functionality
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
                link.addEventListener('click', () => {
                     if (mobileMenu.classList.contains('active')) {
                        hamburger.classList.remove('active');
                        mobileMenu.classList.remove('active');
                        body.style.overflow = '';
                    }
                });
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
                currentUrl.searchParams.delete('rfid_status');
            } else {
                currentUrl.searchParams.set('rfid_status', status);
            }
            window.location.href = currentUrl.toString();
        }
    </script>
</body>
</html>