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

// Initialize variables
$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Employee';
$sessionUserId = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : null;
$sessionRole = isset($_SESSION["role"]) ? htmlspecialchars($_SESSION["role"]) : 'employee';

$activeSection = isset($_GET['section']) ? $_GET['section'] : 'profile';

$userData = null;
$userRFIDCards = []; 
$dbErrorMessage = null;
$updateMessage = null; 

// Configuration
define('PROFILE_UPLOAD_DIR', 'profile_photos/');
define('MAX_PHOTO_SIZE', 2 * 1024 * 1024); // 2 MB
define('ALLOWED_PHOTO_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif'
]);

// Create upload directory if it doesn't exist
if (!file_exists(PROFILE_UPLOAD_DIR)) {
    if (!mkdir(PROFILE_UPLOAD_DIR, 0755, true)) {
        $dbErrorMessage = "Failed to create profile photo directory.";
    }
}

// Form Submission Handling
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($pdo) && $sessionUserId) {
    if (isset($_POST['update_profile'])) {
        $newFirstName = trim($_POST['firstName']);
        $newLastName = trim($_POST['lastName']);
        $newEmail = trim($_POST['email']);
        $newPhone = trim($_POST['phone']);
        $newProfilePhotoName = null;

        // Basic Validation
        if (empty($newFirstName) || empty($newLastName) || empty($newEmail)) {
            $updateMessage = ['type' => 'error', 'text' => 'First name, last name, and email are required.'];
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $updateMessage = ['type' => 'error', 'text' => 'Invalid email format.'];
        } else {
            // Handle Profile Photo Upload
            if (isset($_FILES['profile_photo_input']) && $_FILES['profile_photo_input']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['profile_photo_input']['tmp_name'];
                $fileName = $_FILES['profile_photo_input']['name'];
                $fileSize = $_FILES['profile_photo_input']['size'];
                $fileType = $_FILES['profile_photo_input']['type'];
                
                // Verify file type and size
                if ($fileSize > MAX_PHOTO_SIZE) {
                    $updateMessage = ['type' => 'error', 'text' => 'Image file is too large. Max size is 2MB.'];
                } elseif (!array_key_exists($fileType, ALLOWED_PHOTO_TYPES)) {
                    $updateMessage = ['type' => 'error', 'text' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.'];
                } else {
                    // Generate secure filename
                    $fileExtension = ALLOWED_PHOTO_TYPES[$fileType];
                    $newProfilePhotoName = 'user_' . $sessionUserId . '_' . bin2hex(random_bytes(8)) . '.' . $fileExtension;
                    $dest_path = PROFILE_UPLOAD_DIR . $newProfilePhotoName;

                    // Move uploaded file
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        // Delete old photo if exists
                        if (!empty($userData['profile_photo']) && file_exists(PROFILE_UPLOAD_DIR . $userData['profile_photo'])) {
                            @unlink(PROFILE_UPLOAD_DIR . $userData['profile_photo']);
                        }
                    } else {
                        $updateMessage = ['type' => 'error', 'text' => 'Error saving uploaded file. Please try again.'];
                        $newProfilePhotoName = null;
                    }
                }
            } elseif ($_FILES['profile_photo_input']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Handle file upload errors
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                ];
                $errorText = $uploadErrors[$_FILES['profile_photo_input']['error']] ?? 'Unknown upload error';
                $updateMessage = ['type' => 'error', 'text' => 'Profile photo upload failed: ' . $errorText];
            }

            // Proceed with DB update if no errors
            if (!isset($updateMessage)) {
                try {
                    // Prepare SQL based on whether we have a new photo
                    if ($newProfilePhotoName) {
                        $sql = "UPDATE users SET firstName = ?, lastName = ?, email = ?, phone = ?, profile_photo = ? WHERE userID = ?";
                        $params = [$newFirstName, $newLastName, $newEmail, $newPhone, $newProfilePhotoName, $sessionUserId];
                    } else {
                        $sql = "UPDATE users SET firstName = ?, lastName = ?, email = ?, phone = ? WHERE userID = ?";
                        $params = [$newFirstName, $newLastName, $newEmail, $newPhone, $sessionUserId];
                    }
                    
                    $stmt = $pdo->prepare($sql);
                    $success = $stmt->execute($params);

                    if ($success) {
                        // Update session data
                        $_SESSION["first_name"] = $newFirstName;
                        $_SESSION["email"] = $newEmail;
                        if ($newProfilePhotoName) {
                            $_SESSION["profile_photo"] = $newProfilePhotoName;
                        }
                        
                        $updateMessage = ['type' => 'success', 'text' => 'Profile updated successfully!'];
                        
                        // Refresh user data
                        $stmtUser = $pdo->prepare("SELECT userID, username, firstName, lastName, email, phone, roleID, RFID, profile_photo FROM users WHERE userID = ?");
                        $stmtUser->execute([$sessionUserId]);
                        $userData = $stmtUser->fetch();
                    } else {
                        $updateMessage = ['type' => 'error', 'text' => 'Failed to update profile.'];
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
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE userID = ?");
                $stmt->execute([$sessionUserId]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($currentPassword, $user['password'])) {
                    // Update password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE userID = ?");
                    $updateSuccess = $updateStmt->execute([$hashedPassword, $sessionUserId]);
                    
                    if ($updateSuccess) {
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

// Load user data
if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        $stmtUser = $pdo->prepare("SELECT userID, username, firstName, lastName, email, phone, roleID, RFID, profile_photo FROM users WHERE userID = ?");
        $stmtUser->execute([$sessionUserId]);
        $userData = $stmtUser->fetch();

        if ($userData) {
            $_SESSION["profile_photo"] = $userData['profile_photo'];
            if (!empty($userData['RFID'])) {
                $userRFIDCards[] = [
                    'id' => htmlspecialchars($userData['RFID']),
                    'type' => 'Primary Access Card',
                    'status' => 'Active',
                    'status_class' => 'active'
                ];
            }
        } else {
            $dbErrorMessage = "Could not retrieve your user data.";
        }
    } catch (PDOException $e) {
        $dbErrorMessage = "Database error: " . $e->getMessage();
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
        /* [Previous CSS styles remain the same until .btn styles] */

        /* Improved Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.7rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline {
            background-color: transparent;
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: var(--white);
            border-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #d32f2f;
            border-color: #d32f2f;
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: var(--white);
            border-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #388e3c;
            border-color: #388e3c;
        }
        
        .btn i, .btn .material-symbols-outlined {
            font-size: 1.1em;
        }
        
        /* Form button specific styles */
        .form-submit-btn {
            width: 100%;
            padding: 0.9rem;
            font-size: 1rem;
            margin-top: 1.5rem;
        }
        
        /* Profile upload button */
        .profile-upload-btn {
            padding: 0.7rem 1.2rem;
            font-size: 0.9rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .btn {
                padding: 0.7rem 1.2rem;
                font-size: 0.9rem;
            }
        }

        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4;
            --secondary-color: #3f37c9; --dark-color: #1a1a2e;
            --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; 
            --danger-color: #F44336;  
            --present-color: rgb(67, 170, 139); /* Maintained for consistency if used elsewhere */
            --info-color: rgb(33, 150, 243);   /* Standard info blue */
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

        /* NAVBAR STYLES (Using index.php styles) */
        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; height: 80px; }
        .logo { font-size: 1.8rem; font-weight: 800; color: var(--primary-color); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .logo i { font-size: 1.5rem; } 
        .logo span { color: var(--dark-color); font-weight: 600; }
        .nav-links { display: flex; list-style: none; align-items: center; gap: 0.5rem; }
        .nav-links a:not(.btn) { color: var(--dark-color); text-decoration: none; font-weight: 500; padding: 0.7rem 1rem; font-size: 0.95rem; border-radius: 8px; position: relative; transition: var(--transition); }
        .nav-links a:not(.btn):hover, .nav-links a.active-nav-link { color: var(--primary-color); background-color: rgba(67,97,238,0.07); }
        .nav-links .btn-outline { background-color: transparent; border: 2px solid var(--primary-color); color: var(--primary-color); display: inline-flex; gap: 8px; align-items: center; justify-content: center; padding: 0.7rem 1.2rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: var(--transition); }
        .nav-links .btn-outline:hover { background-color: var(--primary-color); color: var(--white); }
        .nav-links .material-symbols-outlined { font-size: 1.2em; vertical-align:text-bottom; margin-right:4px;}
        .hamburger { display: none; cursor:pointer; } @media (max-width: 992px) { .nav-links { display: none; } .hamburger { display: flex; flex-direction:column; justify-content:space-around; width:30px; height:24px; position:relative; z-index:1001;} .hamburger span{display:block;width:100%;height:3px;background-color:var(--dark-color);position:absolute;left:0;transition:var(--transition);transform-origin:center} .hamburger span:nth-child(1){top:0} .hamburger span:nth-child(2){top:50%;transform:translateY(-50%)} .hamburger span:nth-child(3){bottom:0} .hamburger.active span:nth-child(1){top:50%;transform:translateY(-50%) rotate(45deg)} .hamburger.active span:nth-child(2){opacity:0} .hamburger.active span:nth-child(3){bottom:50%;transform:translateY(50%) rotate(-45deg)}}
        .mobile-menu { position: fixed; top: 0; left: 0; width: 100%; height: 100vh; background-color: var(--white); z-index: 1000; display: flex; flex-direction: column; justify-content: center; align-items: center; transform: translateX(-100%); transition: transform 0.4s cubic-bezier(0.23, 1, 0.32, 1); padding: 2rem; }
        .mobile-menu.active { transform: translateX(0); }
        .mobile-links { list-style: none; text-align: center; width: 100%; max-width: 300px; padding:0; } .mobile-links li { margin-bottom: 1.5rem; }
        .mobile-links a { color: var(--dark-color); text-decoration: none; font-weight: 600; font-size: 1.2rem; display: inline-block; padding: 0.5rem 1rem; transition: var(--transition); border-radius: 8px; }
        .mobile-links a:hover, .mobile-links a.active-nav-link { color: var(--primary-color); background-color: rgba(67,97,238,0.1); }
        .mobile-menu .btn-outline { margin-top: 2rem; width: 100%; max-width: 200px; padding: 0.7rem 1.2rem; font-size:0.9rem;}
        .close-btn { position: absolute; top: 30px; right: 30px; font-size: 1.8rem; color: var(--dark-color); cursor: pointer; transition: var(--transition); }
        .close-btn:hover { color: var(--primary-color); transform: rotate(90deg); }
        /* END NAVBAR */

        .page-header { padding: 2rem 0; margin-bottom: 2rem; background-color:var(--white); box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .page-header h1 { font-size: 1.8rem; color: var(--dark-color); margin: 0; }
        .db-error-message, .update-message { padding: 1rem 1.2rem; border-radius: 6px; margin-bottom: 1.5rem; font-size:0.9rem; border-left-width: 5px; border-left-style:solid; display:flex; align-items:center; gap:0.8rem;}
        .db-error-message i, .update-message i { font-size:1.2em; }
        .db-error-message, .update-message.error { background-color: #ffe3e3; color: var(--danger-color); border-left-color:var(--danger-color); }
        .update-message.success { background-color: #e6ffed; color: var(--success-color); border-left-color: var(--success-color); }


        .account-layout { display: flex; gap: 2.5rem; padding-top: 1.5rem; }
        .account-sidebar { flex: 0 0 280px; background-color: var(--sidebar-bg); padding: 1.8rem; border-radius: 10px; box-shadow: var(--shadow); align-self: flex-start; }
        .account-sidebar h3 { font-size: 1.2rem; color: var(--dark-color); margin-bottom: 1.8rem; padding-bottom: 1rem; border-bottom: 1px solid var(--light-gray); }
        .account-sidebar ul { list-style: none; padding: 0; margin: 0; }
        .account-sidebar ul li a { display: flex; align-items: center; gap: 0.9rem; padding: 0.9rem 1.2rem; text-decoration: none; color: var(--gray-color); font-weight: 500; font-size: 0.95rem; border-radius: 7px; transition: var(--transition); border-left: 4px solid transparent; margin-bottom:0.4rem;}
        .account-sidebar ul li a:hover { background-color: var(--sidebar-link-hover-bg); color: var(--primary-color); border-left-color: var(--primary-color);}
        .account-sidebar ul li a.active { background-color: var(--sidebar-link-active-bg); color: var(--primary-color); font-weight: 600; border-left-color: var(--sidebar-link-active-border); }
        .account-sidebar ul li a .material-symbols-outlined { font-size: 1.4em; }

        .account-content { flex-grow: 1; background-color: var(--content-bg); padding: 2rem 2.5rem; border-radius: 10px; box-shadow: var(--shadow); }
        .content-section { display: none; } .content-section.active { display: block; }
        .content-section h2 { font-size: 1.6rem; color: var(--primary-dark); margin-bottom: 2rem; padding-bottom: 1.2rem; border-bottom: 1px solid var(--light-gray); }

        .profile-info-form .form-row { display: flex; gap: 1.5rem; margin-bottom: 1.5rem;}
        .profile-info-form .form-row .form-group { flex: 1; margin-bottom:0;}
        .profile-picture-group { display:flex; align-items:flex-start; gap:1.5rem; margin-bottom:2rem;}
        .profile-picture-display { text-align:center; }
        .profile-picture { width: 130px; height: 130px; border-radius:50%; object-fit:cover; border:4px solid var(--white); margin-bottom:0.8rem; box-shadow: 0 3px 10px rgba(0,0,0,0.15);}
        .profile-picture-placeholder { width: 130px; height: 130px; border-radius:50%; background-color:var(--light-gray); display:flex; align-items:center; justify-content:center; margin:0 auto 0.8rem; border:4px solid var(--white);box-shadow: 0 3px 10px rgba(0,0,0,0.1);}
        .profile-picture-placeholder .material-symbols-outlined { font-size:3.8rem; color:var(--gray-color); }
        .profile-upload-actions label.btn { font-size:0.85rem; padding:0.5rem 1rem; display:inline-flex; align-items:center; gap:0.4rem; }
        .profile-upload-actions input[type="file"] { display:none;}
        .profile-upload-actions .placeholder-text {font-size:0.75rem; color:var(--gray-color); margin-top:0.4rem; display:block;}


        .profile-info-form label, .change-password-form label {display:block; margin-bottom:0.6rem; font-weight:500; font-size:0.9rem; color: var(--dark-color);}
        .profile-info-form input[type="text"], .profile-info-form input[type="email"], .profile-info-form input[type="tel"],
        .change-password-form input[type="password"] {
            width: 100%; padding: 0.8rem 1.1rem; border:1px solid #ccd0d5; border-radius:6px; font-size:0.95rem;
            transition: var(--transition); box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }
        .profile-info-form input:focus, .change-password-form input:focus {border-color:var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-color-val, 67, 97, 238),0.2);}
        .form-group input[readonly] { background-color: #f0f2f5; cursor:not-allowed; opacity:0.8; border-color:#e0e0e0; }
        .btn.form-submit-btn { margin-top:1.5rem; padding:0.8rem 2rem; font-size:1rem; width:auto; min-width:180px; background-color: var(--primary-color); color:var(--white); }
        .btn.form-submit-btn:hover { background-color:var(--primary-dark); }
        .btn.form-submit-btn .material-symbols-outlined {margin-right:0.5rem;}


        .rfid-cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: 1.8rem; }
        .rfid-card-item {
            background-color: var(--white); border: 1px solid #e0e4e8;
            border-radius: 10px; padding: 1.5rem; text-align: center; transition: var(--transition);
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }
        .rfid-card-item:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.08); transform: translateY(-4px);}
        .rfid-card-image { width: 100%; max-width: 230px; height: auto; border-radius: 8px; margin-bottom: 1.2rem; border: 1px solid #d0d5dd; display:block; margin-left:auto; margin-right:auto; background-color:var(--light-gray);}
        .rfid-card-info h4 { font-size:1.1rem; color:var(--dark-color); margin-bottom:0.4rem; font-weight:600;}
        .rfid-card-info p { font-size:0.9rem; color:var(--gray-color); margin-bottom:0.3rem;}
        .rfid-card-status { display:inline-flex; align-items:center; gap:0.5rem; font-size:0.85rem; font-weight:500; padding:0.35rem 0.9rem; border-radius:20px; margin-top:0.8rem; border:1px solid transparent;}
        .rfid-card-status.active { background-color:rgba(var(--present-color-val,67, 170, 139),0.1); color:var(--present-color); border-color: rgba(var(--present-color-val),0.3);}
        .rfid-card-status.inactive { background-color:rgba(var(--neutral-color-val, 173, 181, 189),0.1); color:var(--neutral-color); border-color: rgba(var(--neutral-color-val),0.3);}
        .rfid-card-status .material-symbols-outlined { font-size:1.2em; }

        @media (max-width: 992px) { .account-layout { flex-direction: column; } .account-sidebar { width: 100%; margin-bottom:2rem; } }
        @media (max-width: 768px) { .profile-info-form .form-row { flex-direction:column; gap:0; margin-bottom:0;} .profile-info-form .form-row .form-group {margin-bottom:1.2rem;} .profile-picture-group{flex-direction:column; align-items:center;}}
        /* FOOTER (same as before) */
                /* Footer */
                footer { background-color: var(--dark-color); color: var(--white); padding: 5rem 0 2rem; }
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 3rem; margin-bottom: 3rem; }
        .footer-column h3 { font-size: 1.3rem; margin-bottom: 1.8rem; position: relative; padding-bottom: 0.8rem; }
        .footer-column h3::after { content: ''; position: absolute; left: 0; bottom: 0; width: 50px; height: 3px; background-color: var(--primary-color); border-radius: 3px; }
        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: 0.8rem; }
        .footer-links a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); font-size: 0.95rem; display: inline-block; padding: 0.2rem 0; }
        .footer-links a:hover { color: var(--white); transform: translateX(5px); }
        .footer-links a i { margin-right: 0.5rem; width: 20px; text-align: center; }
        .social-links { display: flex; gap: 1.2rem; margin-top: 1.5rem; }
        .social-links a { 
            display: inline-flex; align-items: center; justify-content: center; 
            width: 40px; height: 40px; background-color: rgba(255, 255, 255, 0.1); 
            color: var(--white); border-radius: 50%; font-size: 1.1rem; transition: var(--transition); 
        }
        .social-links a:hover { background-color: var(--primary-color); transform: translateY(-3px); }
        .footer-bottom { text-align: center; padding-top: 3rem; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.9rem; color: rgba(255, 255, 255, 0.6); }
        .footer-bottom a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); }
        .footer-bottom a:hover { color: var(--primary-color); }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .hero h1 { font-size: 2.5rem; }
            .section-title h2 { font-size: 2rem; }
            .contact-container { grid-template-columns: 1fr; gap: 2.5rem; }
            .contact-info { text-align: center; }
            .contact-info .contact-details { justify-content: center; }
            .contact-details { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; }
            .contact-detail { flex-direction: column; align-items: center; text-align: center; max-width: 200px; }
            .team-grid { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); } /* Adjust for tablets */
        }

        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hamburger { display: flex; flex-direction: column; justify-content: space-between; }
            .hero { padding-top: 8rem; padding-bottom: 4rem; }
            .hero h1 { font-size: 2.2rem; }
            .hero p { font-size: 1.1rem; }
            .hero-buttons { flex-direction: column; align-items: center; }
            .btn, .nav-links .btn, .mobile-menu .btn, .contact-form .btn  { width: 100%; max-width: 300px; }
            .btn-outline, .nav-links .btn-outline, .mobile-menu .btn-outline { width: 100%; max-width: 300px;}
            .nav-links .btn, .nav-links .btn-outline { max-width: 180px; width: auto; }
            .section { padding: 4rem 0; }
            .section-title h2 { font-size: 1.8rem; }
            .section-title p { font-size: 1rem; }
            .steps-grid { grid-template-columns: 1fr; gap: 1.5rem; }
            .team-grid { grid-template-columns: 1fr; } /* Stack team members on smaller screens */
            .footer-content { grid-template-columns: 1fr 1fr; }
        }

         @media (max-width: 576px) {
            .hero h1 { font-size: 2rem; }
            .section-title h2 { font-size: 1.6rem; }
            .feature-card { padding: 2rem 1.5rem; }
            .team-member-card { padding: 1.5rem; }
            .member-image-placeholder { width: 100px; height: 100px; font-size: 2.5rem; }
            .member-image-placeholder i { font-size: 3rem; }
            .footer-content { grid-template-columns: 1fr; }
            .contact-form { padding: 2rem 1.5rem; }
            .btn, .btn-outline, 
            .nav-links .btn, .nav-links .btn-outline, 
            .mobile-menu .btn, .mobile-menu .btn-outline, 
            .hero-buttons .btn, .hero-buttons .btn-outline,
            .contact-form .btn { max-width: 100%; }
         }

    </style>
</head>
<body>
    <?php require "components/header-employee-panel.php"; ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>My Account</h1>
                <p class="sub-heading">Manage your profile information, password, and active RFID cards.</p>
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
                    <!-- Profile Information Section -->
                    <div id="profile-section" class="content-section <?php if ($activeSection === 'profile') echo 'active'; ?>">
                        <h2>Profile Details</h2>
                        <?php if ($userData): ?>
                            <form method="POST" action="profile.php?section=profile" class="profile-info-form" enctype="multipart/form-data">
                                <div class="profile-picture-group">
                                    <div class="profile-picture-display">
                                        <?php 
                                        $photoToDisplay = '';
                                        if (!empty($userData['profile_photo'])) {
                                            $safeFileName = basename($userData['profile_photo']);
                                            $potentialPath = PROFILE_UPLOAD_DIR . $safeFileName;
                                            if (file_exists($potentialPath)) {
                                                $photoToDisplay = htmlspecialchars($potentialPath);
                                            }
                                        }
                                        if ($photoToDisplay): 
                                        ?>
                                            <img src="<?php echo $photoToDisplay; ?>?<?php echo time(); ?>" alt="Profile Picture" class="profile-picture">
                                        <?php else: ?>
                                            <div class="profile-picture-placeholder"><span class="material-symbols-outlined">person</span></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="profile-upload-actions">
                                        <label for="profile_photo_input" class="btn btn-outline profile-upload-btn">
                                            <span class="material-symbols-outlined">photo_camera</span> Upload New Photo
                                        </label>
                                        <input type="file" name="profile_photo_input" id="profile_photo_input" accept="image/jpeg, image/png, image/gif">
                                        <small class="placeholder-text">Max 2MB. JPG, PNG, GIF accepted.</small>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="firstName">First Name</label>
                                        <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($userData['firstName']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="lastName">Last Name</label>
                                        <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($userData['lastName']); ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Role</label>
                                    <input type="text" value="<?php echo ucfirst(htmlspecialchars($userData['roleID'])); ?>" readonly >
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="phone">Phone Number</label>
                                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?: ''); ?>" placeholder="Optional">
                                    </div>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary form-submit-btn">
                                    <span class="material-symbols-outlined">save</span> Save Profile
                                </button>
                            </form>
                        <?php else: ?>
                            <p class="no-activity-msg">Could not load your profile information. Please try refreshing the page or contact support if the issue persists.</p>
                        <?php endif; ?>
                    </div>

                    <div id="password-section" class="content-section <?php if ($activeSection === 'password') echo 'active'; ?>">
                        <h2>Change Your Password</h2>
                        <form method="POST" action="profile.php?section=password" class="change-password-form">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required minlength="8" placeholder="Minimum 8 characters">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary form-submit-btn">
                                <span class="material-symbols-outlined">lock_reset</span> Set New Password
                            </button>
                        </form>
                    </div>

                    <div id="rfid-section" class="content-section <?php if ($activeSection === 'rfid') echo 'active'; ?>">
                        <h2>My Active RFID Cards</h2>
                        <?php if (!empty($userRFIDCards)): ?>
                            <div class="rfid-cards-grid">
                                <?php foreach($userRFIDCards as $card): ?>
                                <div class="rfid-card-item">
                                    <img src="imgs/wavepass_card.png" alt="WavePass RFID Card Visual" class="rfid-card-image">
                                    <div class="rfid-card-info">
                                        <h4>Card ID: <?php echo $card['id']; ?></h4>
                                        <p>Assignment: <?php echo htmlspecialchars($card['type']); ?></p>
                                        <p class="rfid-card-status <?php echo $card['status_class']; ?>">
                                            <span class="material-symbols-outlined"><?php echo ($card['status_class'] === 'active' ? 'verified' : 'disabled_by_default'); ?></span>
                                            <?php echo $card['status']; ?>
                                        </p>
                                    </div>
                                </div>  
                                <?php endforeach; ?>
                            </div>
                        <?php elseif($userData && empty($userData['RFID'])): ?>
                             <p class="no-activity-msg" style="background-color:transparent; border:none; padding:1rem 0;">You currently have no RFID card registered with your account.</p>
                        <?php else: ?>
                            <p class="no-activity-msg" style="background-color:transparent; border:none; padding:1rem 0;">Could not load RFID card information or no cards are assigned.</p>
                        <?php endif; ?>
                         <p class="placeholder-text" style="margin-top:1.8rem; text-align:center; font-size:0.85rem;">
                            <i class="fas fa-info-circle"></i> If you need a new RFID card or if your card is lost/stolen, please contact your school's administration office.
                         </p>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <!-- footer !-->
    <?php require "components/footer.php"; ?>

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
        if (profilePhotoInput) {
            profilePhotoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const img = document.querySelector('.profile-picture') || 
                                   document.querySelector('.profile-picture-placeholder');
                        if (img) {
                            if (img.classList.contains('profile-picture-placeholder')) {
                                // Replace placeholder with actual image
                                const parent = img.parentNode;
                                const newImg = document.createElement('img');
                                newImg.src = event.target.result;
                                newImg.className = 'profile-picture';
                                newImg.alt = 'Profile Picture Preview';
                                parent.replaceChild(newImg, img);
                            } else {
                                // Update existing image
                                img.src = event.target.result;
                            }
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>
</html>