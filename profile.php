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

// Initialize variables from session for header and page
$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Employee';
$sessionUserId = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : null;
$sessionRole = isset($_SESSION["role"]) ? htmlspecialchars($_SESSION["role"]) : 'employee';
$currentPage = basename($_SERVER['PHP_SELF']); // For active nav link in header

$activeSection = isset($_GET['section']) ? $_GET['section'] : 'profile';
$rfidStatusFilter = isset($_GET['rfid_status']) && in_array($_GET['rfid_status'], ['active', 'inactive']) 
                    ? $_GET['rfid_status'] 
                    : ($activeSection === 'rfid' ? 'active' : null); 
if ($activeSection === 'rfid' && $rfidStatusFilter === null) {
    $rfidStatusFilter = 'active';
}

$userData = null;
$userRFIDCards = []; 
$dbErrorMessage = null;
$updateMessage = null; 

// Configuration Constants
if (!defined('PROFILE_UPLOAD_DIR')) define('PROFILE_UPLOAD_DIR', 'profile_photos/');
if (!defined('MAX_PHOTO_SIZE')) define('MAX_PHOTO_SIZE', 2 * 1024 * 1024); // 2 MB
if (!defined('ALLOWED_PHOTO_TYPES')) define('ALLOWED_PHOTO_TYPES', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif']);

if (!file_exists(PROFILE_UPLOAD_DIR)) {
    if (!mkdir(PROFILE_UPLOAD_DIR, 0775, true)) {
        $dbErrorMessage = "CRITICAL ERROR: Failed to create profile photo directory (" . PROFILE_UPLOAD_DIR . "). Please check server permissions.";
    }
}

// --- Form Submission Handling ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($pdo) && $sessionUserId) {
    $stmtCurrentDataForPost = $pdo->prepare("SELECT profile_photo FROM users WHERE userID = :userid");
    $stmtCurrentDataForPost->bindParam(':userid', $sessionUserId, PDO::PARAM_INT);
    $stmtCurrentDataForPost->execute();
    $currentDbUserDataForPost = $stmtCurrentDataForPost->fetch();
    $currentProfilePhotoFilenameDB = $currentDbUserDataForPost ? $currentDbUserDataForPost['profile_photo'] : null;
    if($stmtCurrentDataForPost) $stmtCurrentDataForPost->closeCursor();

    if (isset($_POST['update_profile'])) {
        $newFirstName = trim($_POST['firstName']);
        $newLastName = trim($_POST['lastName']);
        $newEmail = trim($_POST['email']);
        $newPhone = trim($_POST['phone']);
        $newProfilePhotoNameToSave = null; 

        if (empty($newFirstName) || empty($newLastName) || empty($newEmail)) {
            $updateMessage = ['type' => 'error', 'text' => 'First name, last name, and email are required.'];
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $updateMessage = ['type' => 'error', 'text' => 'Invalid email format.'];
        } else {
            $photoUploadProcessedSuccessfully = false; // Tracks if a photo was uploaded AND processed ok
            if (isset($_FILES['profile_photo_input']) && $_FILES['profile_photo_input']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['profile_photo_input']['tmp_name'];
                $fileName = basename($_FILES['profile_photo_input']['name']);
                $fileSize = $_FILES['profile_photo_input']['size'];
                $fileType = mime_content_type($fileTmpPath);

                if ($fileSize > MAX_PHOTO_SIZE) {
                    $updateMessage = ['type' => 'error', 'text' => 'Image is too large (Max 2MB).'];
                } elseif (!array_key_exists($fileType, ALLOWED_PHOTO_TYPES)) {
                    $updateMessage = ['type' => 'error', 'text' => 'Invalid file type. Allowed: JPG, PNG, GIF. Detected: ' . $fileType];
                } else {
                    $fileExtension = ALLOWED_PHOTO_TYPES[$fileType];
                    $newProfilePhotoNameToSave = 'user' . $sessionUserId . '_' . bin2hex(random_bytes(12)) . '.' . $fileExtension; // Longer random string
                    $dest_path = PROFILE_UPLOAD_DIR . $newProfilePhotoNameToSave;

                    if (!is_writable(PROFILE_UPLOAD_DIR)) {
                         $updateMessage = ['type' => 'error', 'text' => 'Upload directory is not writable. Cannot save photo.'];
                         $newProfilePhotoNameToSave = null;
                    } elseif (move_uploaded_file($fileTmpPath, $dest_path)) {
                        if ($currentProfilePhotoFilenameDB && $currentProfilePhotoFilenameDB !== $newProfilePhotoNameToSave && file_exists(PROFILE_UPLOAD_DIR . $currentProfilePhotoFilenameDB)) {
                            @unlink(PROFILE_UPLOAD_DIR . $currentProfilePhotoFilenameDB);
                        }
                        $photoUploadProcessedSuccessfully = true;
                    } else {
                        $updateMessage = ['type' => 'error', 'text' => 'Could not save uploaded file (move_uploaded_file failed).'];
                        $newProfilePhotoNameToSave = null;
                    }
                }
            } elseif (isset($_FILES['profile_photo_input']) && $_FILES['profile_photo_input']['error'] !== UPLOAD_ERR_NO_FILE) {
                 $updateMessage = ['type' => 'error', 'text' => 'Profile photo upload error. Code: '. $_FILES['profile_photo_input']['error']];
            }

            // Update DB only if there wasn't a fatal error during text validation or photo processing
            if (!isset($updateMessage) || ($updateMessage['type'] !== 'error' && $photoUploadProcessedSuccessfully) || (!isset($updateMessage) && !$photoUploadProcessedSuccessfully && (!isset($_FILES['profile_photo_input']) || $_FILES['profile_photo_input']['error'] == UPLOAD_ERR_NO_FILE) )) {
                try {
                    $paramsToUpdate = [
                        ':firstName' => $newFirstName, ':lastName' => $newLastName,
                        ':email' => $newEmail, ':phone' => $newPhone,
                        ':userid' => $sessionUserId
                    ];
                    $sqlSetParts = ["firstName = :firstName", "lastName = :lastName", "email = :email", "phone = :phone"];

                    if ($newProfilePhotoNameToSave) {
                        $sqlSetParts[] = "profile_photo = :profile_photo";
                        $paramsToUpdate[':profile_photo'] = $newProfilePhotoNameToSave;
                    }
                    // Example for a "remove photo" checkbox
                    // elseif (isset($_POST['remove_current_photo']) && $_POST['remove_current_photo'] == '1' && $currentProfilePhotoFilenameDB) {
                    //    $sqlSetParts[] = "profile_photo = NULL";
                    //    if (file_exists(PROFILE_UPLOAD_DIR . $currentProfilePhotoFilenameDB)) {
                    //        @unlink(PROFILE_UPLOAD_DIR . $currentProfilePhotoFilenameDB);
                    //    }
                    // }


                    $sql = "UPDATE users SET " . implode(", ", $sqlSetParts) . " WHERE userID = :userid";
                    $stmt = $pdo->prepare($sql);
                    
                    if ($stmt->execute($paramsToUpdate)) {
                        $_SESSION["first_name"] = $newFirstName;
                        $_SESSION["email"] = $newEmail;
                        if ($newProfilePhotoNameToSave) {
                            $_SESSION["profile_photo"] = $newProfilePhotoNameToSave;
                        } elseif (isset($_POST['remove_current_photo']) && $_POST['remove_current_photo'] == '1') {
                            $_SESSION["profile_photo"] = null;
                        }
                        $sessionFirstName = $newFirstName;
                        $updateMessage = ['type' => 'success', 'text' => 'Profile updated successfully!'];
                        if ($newProfilePhotoNameToSave) $updateMessage['text'] .= ' New photo applied.';
                    } else {
                        $updateMessage = ['type' => 'error', 'text' => 'Failed to update profile data in the database.'];
                    }
                } catch (PDOException $e) {
                    $updateMessage = ['type' => 'error', 'text' => 'Database error: ' . $e->getMessage()];
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $updateMessage = ['type' => 'error', 'text' => 'All password fields are required.'];
        } elseif ($newPassword !== $confirmPassword) {
            $updateMessage = ['type' => 'error', 'text' => 'New passwords do not match.'];
        } elseif (strlen($newPassword) < 8) {
            $updateMessage = ['type' => 'error', 'text' => 'Password must be at least 8 characters long.'];
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE userID = ?");
                $stmt->execute([$sessionUserId]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($currentPassword, $user['password'])) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE userID = ?");
                    if ($updateStmt->execute([$hashedPassword, $sessionUserId])) {
                        $updateMessage = ['type' => 'success', 'text' => 'Password changed successfully!'];
                    } else {
                        $updateMessage = ['type' => 'error', 'text' => 'Failed to update password.'];
                    }
                } else {
                    $updateMessage = ['type' => 'error', 'text' => 'Current password is incorrect.'];
                }
            } catch (PDOException $e) {
                $updateMessage = ['type' => 'error', 'text' => 'Database error: ' . $e->getMessage()];
            }
        }
    }
}

// Load/Re-load user data for display
if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        $stmtUserDisplay = $pdo->prepare("SELECT userID, username, firstName, lastName, email, phone, roleID, RFID, profile_photo FROM users WHERE userID = ?");
        $stmtUserDisplay->execute([$sessionUserId]);
        $userData = $stmtUserDisplay->fetch();

        if ($userData) {
            $_SESSION["profile_photo"] = $userData['profile_photo']; // Ensure session has the latest
            $userRFIDCards = []; 
            if ($activeSection === 'rfid') {
                if ($rfidStatusFilter === 'active') {
                    if (!empty($userData['RFID'])) { 
                        $userRFIDCards[] = [
                            'id' => htmlspecialchars($userData['RFID']), 'type' => 'Primary Access Card',
                            'status' => 'Active', 'status_class' => 'active'
                        ];
                    }
                } 
                // Placeholder for inactive cards (requires separate table logic)
            }
        } else {
            $dbErrorMessage = "Could not retrieve your user data for display.";
        }
    } catch (PDOException $e) {
        $dbErrorMessage = "Database error on page load: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="imgs/logo.png" type="image/x-icon">
    <title>My Account - <?php echo $sessionFirstName; ?> - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4;
            --secondary-color: #3f37c9; --dark-color: #1a1a2e;
            --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --danger-color: #F44336;  
            --present-color-rgb: 67, 170, 139; /* For rgba backgrounds */
            --info-color-rgb: 33, 150, 243;
            --present-color: rgb(var(--present-color-rgb));
            --info-color: rgb(var(--info-color-rgb));
             --neutral-color-rgb: 173, 181, 189;
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
        .container { max-width: 1400px; margin: 0 auto; padding: 0 25px; }
        h1,h2,h3,h4 {font-weight: 600; letter-spacing: -0.5px;}

        /* NAVBAR STYLES (Consistent with index.php theme) */
        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; height: 80px; }
        .logo { font-size: 1.8rem; font-weight: 800; color: var(--primary-color); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .logo img.logo-img { height: 50px; width: auto; vertical-align: middle; margin-right: 0.5rem; } /* For image logo */
        .logo i.fas { font-size: 1.5rem; } /* For FontAwesome icon logo */
        .logo span { color: var(--dark-color); font-weight: 600; }
        .nav-links { display: flex; list-style: none; align-items: center; gap: 0.5rem; }
        .nav-links a:not(.btn) { color: var(--dark-color); text-decoration: none; font-weight: 500; padding: 0.7rem 1rem; font-size: 0.95rem; border-radius: 8px; position: relative; transition: var(--transition); display:inline-flex; align-items:center; }
        .nav-links a:not(.btn):hover, .nav-links a.active-nav-link { color: var(--primary-color); background-color: rgba(var(--primary-color, 67, 97, 238),0.07); }
        .nav-links .material-symbols-outlined { font-size: 1.3em; vertical-align:text-bottom; margin-right:5px;}
        .nav-user-photo {width: 28px; height: 28px; border-radius: 50%; object-fit: cover; margin-right: 8px; vertical-align: middle; border: 1px solid var(--light-gray);}


        /* General Button Styles (Theme Consistent) */
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 500; /* Adjusted font-weight */
            text-decoration: none; cursor: pointer; transition: var(--transition);
            gap: 0.6rem; border: 2px solid transparent; font-size: 0.9rem; /* Adjusted font-size */
            line-height: 1.5; /* Ensure text aligns well */
        }
        .btn-primary { background-color: var(--primary-color); color: var(--white); border-color: var(--primary-color); box-shadow: 0 2px 5px rgba(var(--primary-color, 67, 97, 238), 0.2);}
        .btn-primary:hover { background-color: var(--primary-dark); border-color: var(--primary-dark); transform:translateY(-1px); box-shadow: 0 4px 8px rgba(var(--primary-color, 67, 97, 238), 0.3);}
        .btn-outline { background-color: transparent; border-color: var(--primary-color); color: var(--primary-color); }
        .btn-outline:hover { background-color: var(--primary-color); color: var(--white); transform:translateY(-1px); }
        .nav-links .btn-outline { padding: 0.6rem 1.1rem; /* Slightly smaller for navbar context */ }
        .btn .material-symbols-outlined, .btn i.fas { font-size: 1.2em; }
        
        /* Hamburger & Mobile Menu (Consistent with index.php) */
        .hamburger { display: none; cursor:pointer; } @media (max-width: 992px) { .nav-links { display: none; } .hamburger { display: flex; flex-direction:column; justify-content:space-around; width:30px; height:24px; position:relative; z-index:1001;} .hamburger span{display:block;width:100%;height:3px;background-color:var(--dark-color);position:absolute;left:0;transition:var(--transition);transform-origin:center} .hamburger span:nth-child(1){top:0} .hamburger span:nth-child(2){top:50%;transform:translateY(-50%)} .hamburger span:nth-child(3){bottom:0} .hamburger.active span:nth-child(1){top:50%;transform:translateY(-50%) rotate(45deg)} .hamburger.active span:nth-child(2){opacity:0} .hamburger.active span:nth-child(3){bottom:50%;transform:translateY(50%) rotate(-45deg)}}
        .mobile-menu { position: fixed; top: 0; left: 0; width: 100%; height: 100vh; background-color: var(--white); z-index: 1000; display: flex; flex-direction: column; justify-content: center; align-items: center; transform: translateX(-100%); transition: transform 0.4s cubic-bezier(0.23, 1, 0.32, 1); padding: 2rem; }
        .mobile-menu.active { transform: translateX(0); }
        .mobile-links { list-style: none; text-align: center; width: 100%; max-width: 300px; padding:0; } .mobile-links li { margin-bottom: 1.5rem; }
        .mobile-links a { color: var(--dark-color); text-decoration: none; font-weight: 600; font-size: 1.2rem; display: inline-block; padding: 0.5rem 1rem; transition: var(--transition); border-radius: 8px; }
        .mobile-links a:hover, .mobile-links a.active-nav-link { color: var(--primary-color); background-color: rgba(67,97,238,0.1); }
        .mobile-menu .btn-outline { margin-top: 2rem; width: 100%; max-width: 200px; padding: 0.7rem 1.2rem; font-size:0.9rem;}
        .close-btn { position: absolute; top: 30px; right: 30px; font-size: 1.8rem; color: var(--dark-color); cursor: pointer; transition: var(--transition); }
        .close-btn:hover { color: var(--primary-color); transform: rotate(90deg); }

        .page-header { padding: 2rem 0; margin-bottom: 2rem; background-color:var(--white); box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .page-header h1 { font-size: 1.8rem; color: var(--dark-color); margin: 0; }
        .db-error-message, .update-message { padding: 1rem 1.2rem; border-radius: 6px; margin-bottom: 1.5rem; font-size:0.9rem; border-left-width: 5px; border-left-style:solid; display:flex; align-items:center; gap:0.8rem;}
        .db-error-message i, .update-message i { font-size:1.2em; }
        .update-message.error { background-color: #ffebee; color: var(--danger-color); border-left-color:var(--danger-color); } /* From index.php example */
        .update-message.success { background-color: #e8f5e9; color: var(--success-color); border-left-color: var(--success-color); } /* From index.php example */

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
        .profile-picture-placeholder { width: 120px; height: 120px; border-radius:50%; background-color:var(--light-gray); display:flex; align-items:center; justify-content:center; margin:0 auto 0rem; border:4px solid var(--white);box-shadow: 0 4px 15px rgba(0,0,0,0.1);}
        .profile-picture-placeholder .material-symbols-outlined { font-size:4rem; color:var(--gray-color); }
        .profile-upload-actions { }
        .profile-upload-actions label.btn-outline {font-size:0.9rem; padding:0.7rem 1.2rem; } /* Using btn-outline */
        .profile-upload-actions input[type="file"] { display:none;}
        .profile-upload-actions .placeholder-text {font-size:0.8rem; color:var(--gray-color); margin-top:0.5rem; display:block;}


        .form-group { margin-bottom: 1.5rem;}
        .profile-info-form label, .change-password-form label {display:block; margin-bottom:0.5rem; font-weight:500; font-size:0.9rem; color: var(--dark-color);}
        .profile-info-form input[type="text"], .profile-info-form input[type="email"], .profile-info-form input[type="tel"],
        .change-password-form input[type="password"] {
            width: 100%; padding: 0.85rem 1.2rem; border:1px solid #d0d5dd; border-radius:6px; font-size:0.95rem;
            transition: var(--transition); box-shadow: 0 1px 2px rgba(0,0,0,0.04); background-color:var(--white);
        }
        .profile-info-form input:focus, .change-password-form input:focus {border-color:var(--primary-color); box-shadow: 0 0 0 3.5px rgba(var(--primary-color,67,97,238),0.2);}
        .form-group input[readonly] { background-color: #f0f2f5; cursor:not-allowed; color:var(--gray-color); }
        
        .form-actions { margin-top:2rem; text-align:right; }
        .form-actions .btn {min-width: 160px;}


        /* RFID Card Section */
        .rfid-filter-container { display: flex; align-items: center; gap: 0.8rem; margin-bottom: 1.8rem; padding-bottom: 1rem; border-bottom: 1px solid var(--light-gray); }
        .rfid-filter-container label { font-weight: 500; font-size: 0.95rem; color: var(--gray-color); }
        .rfid-filter-container select { padding: 0.7rem 1rem; border: 1px solid #ccd0d5; border-radius: 6px; font-size: 0.9rem; background-color: var(--white); box-shadow: 0 1px 2px rgba(0,0,0,0.03); min-width: 200px; cursor: pointer; transition: var(--transition); }
        .rfid-filter-container select:focus { border-color:var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-color, 67, 97, 238),0.2); outline:none; }

        .rfid-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.8rem; }
        .rfid-card-item { background-color: var(--white); border: 1px solid #e0e4e8; border-radius: 10px; padding: 1.5rem; text-align: center; transition: var(--transition); box-shadow: 0 3px 12px rgba(0,0,0,0.05); }
        .rfid-card-item:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.08); transform: translateY(-4px);}
        .rfid-card-image { width: 100%; max-width: 230px; height: auto; border-radius: 8px; margin-bottom: 1.2rem; border: 1px solid #d0d5dd; display:block; margin-left:auto; margin-right:auto; background-color:var(--light-gray);}
        .rfid-card-info h4 { font-size:1.05rem; color:var(--dark-color); margin-bottom:0.4rem; font-weight:600;}
        .rfid-card-info p { font-size:0.9rem; color:var(--gray-color); margin-bottom:0.3rem;}
        .rfid-card-status { display:inline-flex; align-items:center; gap:0.5rem; font-size:0.85rem; font-weight:500; padding:0.35rem 0.9rem; border-radius:20px; margin-top:0.8rem; border:1px solid transparent;}
        .rfid-card-status.active { background-color:rgba(var(--present-color-rgb),0.1); color:var(--present-color); border-color: rgba(var(--present-color-rgb),0.3);}
        .rfid-card-status.inactive { background-color:rgba(var(--neutral-color-rgb),0.1); color:var(--neutral-color); border-color: rgba(var(--neutral-color-rgb),0.3);}
        .rfid-card-status .material-symbols-outlined { font-size:1.2em; }
        .placeholder-text {color: var(--gray-color); font-style: italic; font-size: 0.8rem;}
        .no-activity-msg {text-align:center; padding: 2.5rem 1rem; color:var(--gray-color); font-size:0.95rem; background-color: #fdfdfd; border-radius: 4px; border: 1px dashed var(--light-gray);}


        @media (max-width: 992px) { .account-layout { flex-direction: column; } .account-sidebar { width: 100%; margin-bottom:2rem; } }
        @media (max-width: 768px) { .profile-info-form .form-row { flex-direction:column; gap:0; margin-bottom:0;} .profile-info-form .form-row .form-group {margin-bottom:1.5rem;} .profile-picture-group{flex-direction:column; align-items:center; gap:1rem;}.account-content{padding:1.5rem;} }
        /* FOOTER (same as index.php) */
        footer { background-color: var(--dark-color); color: var(--white); padding: 5rem 0 2rem; margin-top:auto;}
        /* ... Full Footer Styles ... */
    </style>
</head>
<body>
    <?php require "components/header-employee-panel.php"; ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>My Account</h1>
                <p class="sub-heading">Manage your profile, password, and RFID cards.</p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if ($dbErrorMessage): ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $dbErrorMessage; ?></div>
            <?php endif; ?>
            <?php if ($updateMessage): ?>
                <div class="update-message <?php echo $updateMessage['type']; ?>" role="alert">
                    <i class="<?php echo ($updateMessage['type'] === 'success' ? 'fas fa-check-circle' : 'fas fa-times-circle'); ?>"></i> 
                    <?php echo htmlspecialchars($updateMessage['text']); ?>
                </div>
            <?php endif; ?>

            <div class="account-layout">
                <aside class="account-sidebar">
                    <h3>Settings</h3>
                    <ul>
                        <li><a href="?section=profile" class="<?php if ($activeSection === 'profile') echo 'active'; ?>"><span class="material-symbols-outlined">manage_accounts</span> Profile Information</a></li>
                        <li><a href="?section=password" class="<?php if ($activeSection === 'password') echo 'active'; ?>"><span class="material-symbols-outlined">lock_reset</span> Change Password</a></li>
                        <li><a href="?section=rfid" class="<?php if ($activeSection === 'rfid') echo 'active'; ?>"><span class="material-symbols-outlined">credit_card</span> My RFID Cards</a></li>
                    </ul>
                </aside>

                <section class="account-content">
                    <div id="profile-section" class="content-section <?php if ($activeSection === 'profile') echo 'active'; ?>">
                        <h2>Profile Details</h2>
                        <?php if ($userData): ?>
                            <form method="POST" action="profile.php?section=profile" class="profile-info-form" enctype="multipart/form-data">
                                 <input type="hidden" name="current_profile_photo_filename" value="<?php echo htmlspecialchars($userData['profile_photo'] ?? ''); ?>">
                                <div class="profile-picture-group">
                                    <div class="profile-picture-display">
                                        <img src="<?php 
                                            $photoDisplayPath = PROFILE_UPLOAD_DIR . 'default_avatar.png'; // Default image
                                            if (!empty($userData['profile_photo'])) {
                                                $safeFileName = basename($userData['profile_photo']);
                                                $potentialPath = PROFILE_UPLOAD_DIR . $safeFileName;
                                                if (file_exists($potentialPath)) {
                                                    $photoDisplayPath = htmlspecialchars($potentialPath);
                                                }
                                            }
                                            echo $photoDisplayPath . '?' . time(); // Cache buster for updated images
                                        ?>" alt="Profile Picture" class="profile-picture" id="profileImagePreview">
                                    </div>
                                    <div class="profile-upload-actions">
                                        <label for="profile_photo_input" class="btn btn-outline profile-upload-btn">
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
                                    <button type="submit" name="update_profile" class="btn btn-primary form-submit-btn"><span class="material-symbols-outlined">save</span> Save Profile</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div id="password-section" class="content-section <?php if ($activeSection === 'password') echo 'active'; ?>">
                         <h2>Change Your Password</h2>
                         <form method="POST" action="profile.php?section=password" class="change-password-form">
                            <div class="form-group"><label for="current_password">Current Password</label><input type="password" id="current_password" name="current_password" required></div>
                            <div class="form-group"><label for="new_password">New Password</label><input type="password" id="new_password" name="new_password" required minlength="8" placeholder="Minimum 8 characters"></div>
                            <div class="form-group"><label for="confirm_password">Confirm Password</label><input type="password" id="confirm_password" name="confirm_password" required></div>
                            <div class="form-actions">
                                <button type="submit" name="change_password" class="btn btn-primary form-submit-btn"><span class="material-symbols-outlined">lock_reset</span> Set New Password</button>
                            </div>
                        </form>
                    </div>

                    <div id="rfid-section" class="content-section <?php if ($activeSection === 'rfid') echo 'active'; ?>">
                        <h2>My RFID Cards</h2>
                        <div class="rfid-filter-container">
                            <label for="rfid_status_filter">View:</label>
                            <select id="rfid_status_filter" name="rfid_status_filter" onchange="filterRfidCards(this.value)">
                                <option value="active" <?php if ($rfidStatusFilter === 'active') echo 'selected'; ?>>Active Cards</option>
                                <option value="inactive" <?php if ($rfidStatusFilter === 'inactive') echo 'selected'; ?>>Inactive Cards</option>
                            </select>
                        </div>

                        <?php if (!empty($userRFIDCards)): ?>
                            <div class="rfid-cards-grid">
                                <?php foreach($userRFIDCards as $card): ?>
                                <div class="rfid-card-item">
                                    <img src="imgs/wavepass_card.png" alt="WavePass RFID Card" class="rfid-card-image">
                                    <div class="rfid-card-info">
                                        <h4>Card ID: <?php echo $card['id']; ?></h4>
                                        <p>Type: <?php echo htmlspecialchars($card['type']); ?></p>
                                        <p class="rfid-card-status <?php echo $card['status_class']; ?>">
                                            <span class="material-symbols-outlined"><?php echo ($card['status_class'] === 'active' ? 'verified_user' : 'do_not_disturb_on'); ?></span>
                                            <?php echo $card['status']; ?>
                                        </p>
                                    </div>
                                </div>  
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-activity-msg">
                                <?php 
                                if ($rfidStatusFilter === 'active') echo 'You currently have no active RFID card registered.';
                                elseif ($rfidStatusFilter === 'inactive') echo 'No inactive RFID cards on record. (Feature requires expanded DB schema).';
                                else echo 'No RFID cards match your filter.';
                                ?>
                            </p>
                        <?php endif; ?>
                         <p class="placeholder-text" style="margin-top:1.8rem; text-align:center; font-size:0.85rem;">
                            <i class="fas fa-info-circle"></i> For RFID card issues or requests, contact administration.
                         </p>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
             <div class="footer-bottom" style="padding:2rem 0; border-top: 1px solid rgba(var(--dark-color),0.1); text-align:center;">
                <p>© <?php echo date("Y"); ?> WavePass. All rights reserved. | <a href="privacy.php" style="color:var(--gray-color); text-decoration:none;">Privacy Policy</a> | <a href="terms.php" style="color:var(--gray-color); text-decoration:none;">Terms of Service</a></p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile Menu Toggle 
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');
        const closeMenu = document.getElementById('closeMenu');
        const body = document.body;

        if (hamburger && mobileMenu && closeMenu) {
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                mobileMenu.classList.toggle('active');
                body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
            });
            closeMenu.addEventListener('click', () => {
                hamburger.classList.remove('active');
                mobileMenu.classList.remove('active');
                body.style.overflow = '';
            });
            mobileMenu.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    if (!link.getAttribute('href').startsWith('#') || link.getAttribute('href') === '#') {
                         if (mobileMenu.classList.contains('active')) {
                            hamburger.classList.remove('active');
                            mobileMenu.classList.remove('active');
                            body.style.overflow = '';
                        }
                    }
                });
            });
        }

        // Header shadow
        const headerEl = document.querySelector('header');
        if (headerEl) { 
            window.addEventListener('scroll', () => {
                headerEl.style.boxShadow = (window.scrollY > 10) ? 
                    (getComputedStyle(document.documentElement).getPropertyValue('--shadow').trim() || '0 4px 10px rgba(0,0,0,0.05)') : 
                    '0 2px 10px rgba(0,0,0,0.05)';
            });
        }

        // Preview profile photo before upload
        const profilePhotoInput = document.getElementById('profile_photo_input');
        const imagePreview = document.getElementById('profileImagePreview'); // Ensure this ID is on your <img> tag
        const placeholderDiv = document.querySelector('.profile-picture-placeholder');


        if (profilePhotoInput && imagePreview) {
            profilePhotoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        imagePreview.src = event.target.result;
                        if(placeholderDiv) placeholderDiv.style.display = 'none'; // Hide placeholder if it was shown
                        imagePreview.style.display = 'block'; // Ensure image is visible
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // RFID Filter Function
        function filterRfidCards(status) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('section', 'rfid'); // Keep us on the RFID section
            currentUrl.searchParams.set('rfid_status', status);
            window.location.href = currentUrl.toString();
        }
    </script>
</body>
</html>