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
$sessionRole = isset($_SESSION["role"]) ? $_SESSION["role"] : 'employee';

$dbErrorMessage = null;
$successMessage = null;
$messagesToDisplay = [];
$usersForAdminForm = [];

// Zpracování odeslání nové zprávy (pouze pro admina)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'send_message' && $sessionRole == 'admin') {
    $msgTitle = trim(filter_input(INPUT_POST, 'message_title', FILTER_SANITIZE_SPECIAL_CHARS));
    $msgContent = trim(filter_input(INPUT_POST, 'message_content', FILTER_SANITIZE_SPECIAL_CHARS));
    $msgTarget = filter_input(INPUT_POST, 'message_target', FILTER_SANITIZE_SPECIAL_CHARS);
    $msgTargetSpecificUser = filter_input(INPUT_POST, 'message_target_specific_user', FILTER_VALIDATE_INT);
    $msgType = filter_input(INPUT_POST, 'message_type', FILTER_SANITIZE_SPECIAL_CHARS);
    $msgIsUrgent = isset($_POST['message_is_urgent']) ? 1 : 0;

    if (!empty($msgTitle) && !empty($msgContent) && !empty($msgTarget) && !empty($msgType)) {
        $recipientID = null;
        $recipientRole = null;

        if ($msgTarget == 'everyone') {
            $recipientRole = 'everyone';
        } elseif ($msgTarget == 'all_employees') {
            $recipientRole = 'employee';
        } elseif ($msgTarget == 'all_admins') {
            $recipientRole = 'admin';
        } elseif ($msgTarget == 'specific_user' && $msgTargetSpecificUser) {
            $recipientID = $msgTargetSpecificUser;
        } else {
            $dbErrorMessage = "Invalid message target.";
        }

        if (!$dbErrorMessage) {
            try {
                $sqlNewMsg = "INSERT INTO messages (senderID, recipientID, recipientRole, title, content, message_type, is_urgent, is_system_message, created_at, updated_at)
                              VALUES (:senderID, :recipientID, :recipientRole, :title, :content, :message_type, :is_urgent, 0, NOW(), NOW())";
                $stmtNewMsg = $pdo->prepare($sqlNewMsg);
                
                $stmtNewMsg->bindParam(':senderID', $sessionUserId, PDO::PARAM_INT);
                if ($recipientID !== null) {
                    $stmtNewMsg->bindParam(':recipientID', $recipientID, PDO::PARAM_INT);
                } else {
                    $stmtNewMsg->bindValue(':recipientID', null, PDO::PARAM_NULL);
                }
                if ($recipientRole !== null) {
                    $stmtNewMsg->bindParam(':recipientRole', $recipientRole, PDO::PARAM_STR);
                } else {
                    $stmtNewMsg->bindValue(':recipientRole', null, PDO::PARAM_NULL);
                }
                $stmtNewMsg->bindParam(':title', $msgTitle, PDO::PARAM_STR);
                $stmtNewMsg->bindParam(':content', $msgContent, PDO::PARAM_STR);
                $stmtNewMsg->bindParam(':message_type', $msgType, PDO::PARAM_STR);
                $stmtNewMsg->bindParam(':is_urgent', $msgIsUrgent, PDO::PARAM_INT);
                
                if ($stmtNewMsg->execute()) {
                    $successMessage = "Message sent successfully!";
                     $_POST = []; 
                } else {
                    $dbErrorMessage = "Failed to send message.";
                }
            } catch (PDOException $e) {
                $dbErrorMessage = "Database error sending message: " . $e->getMessage();
            }
        }
    } else {
        $dbErrorMessage = "Missing required fields for new message.";
    }
}

// Zpracování označení zprávy jako přečtené/nepřečtené
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'toggle_read_status' && $sessionUserId) {
    $messageIdToToggle = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
    $currentReadStatus = filter_input(INPUT_POST, 'current_status', FILTER_VALIDATE_INT); 

    if ($messageIdToToggle) {
        $newReadStatus = $currentReadStatus ? 0 : 1;
        try {
            $sqlToggle = "INSERT INTO user_message_read_status (userID, messageID, is_read, read_at) 
                          VALUES (:userID, :messageID, :new_status, NOW())
                          ON DUPLICATE KEY UPDATE is_read = :new_status_update, read_at = NOW()";
            $stmtToggle = $pdo->prepare($sqlToggle);
            $stmtToggle->bindParam(':userID', $sessionUserId, PDO::PARAM_INT);
            $stmtToggle->bindParam(':messageID', $messageIdToToggle, PDO::PARAM_INT);
            $stmtToggle->bindParam(':new_status', $newReadStatus, PDO::PARAM_INT);
            $stmtToggle->bindParam(':new_status_update', $newReadStatus, PDO::PARAM_INT);
            
            if ($stmtToggle->execute()) {
                $successMessage = $newReadStatus ? "Message marked as read." : "Message marked as unread.";
            } else {
                $dbErrorMessage = "Failed to update message read status.";
            }
        } catch (PDOException $e) {
            $dbErrorMessage = "Database error updating read status: " . $e->getMessage();
        }
    } else {
        $dbErrorMessage = "Invalid message ID for toggling read status.";
    }
}


$currentFilter = isset($_GET['filter']) ? $_GET['filter'] : 'unread'; 

if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        $params = [':currentUserID_for_join' => $sessionUserId];
        $sqlWhereClauses = ["m.is_active = TRUE", "(m.expires_at IS NULL OR m.expires_at > NOW())"];
        
        $targetClauses = "(m.recipientID = :currentUserID_target OR m.recipientRole = :currentUserRole_target OR m.recipientRole = 'everyone')";
        $params[':currentUserID_target'] = $sessionUserId;
        $params[':currentUserRole_target'] = $sessionRole;
        
        if ($currentFilter == 'for_you') {
            $targetClauses = "m.recipientID = :currentUserID_target";
        } elseif ($currentFilter == 'for_everyone') {
            $targetClauses = "m.recipientRole = 'everyone'";
        }
        $sqlWhereClauses[] = $targetClauses;

        if ($currentFilter == 'read') {
            $sqlWhereClauses[] = "umrs.is_read = 1";
        } elseif ($currentFilter == 'unread') {
            $sqlWhereClauses[] = "(umrs.is_read = 0 OR umrs.is_read IS NULL)";
        }
        // For 'all', no specific read/unread filter is added here, relies on sorting.
        
        $sql = "SELECT
                    m.messageID, m.title, m.content, m.message_type, m.is_urgent, m.created_at,
                    m.senderID, u_sender.firstName AS sender_firstName, u_sender.lastName AS sender_lastName,
                    m.recipientID, m.recipientRole,
                    COALESCE(umrs.is_read, 0) AS is_read_by_user
                FROM messages m
                LEFT JOIN users u_sender ON m.senderID = u_sender.userID
                LEFT JOIN user_message_read_status umrs ON m.messageID = umrs.messageID AND umrs.userID = :currentUserID_for_join
                WHERE " . implode(" AND ", $sqlWhereClauses) . "
                ORDER BY is_read_by_user ASC, m.is_urgent DESC, m.created_at DESC"; 
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params); 
        $fetchedMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($fetchedMessages as $msg) {
            $messageData = $msg;
            if ($msg['recipientID'] == $sessionUserId) {
                $messageData['target_audience_display'] = 'For you';
            } elseif ($msg['recipientRole'] == 'everyone') {
                $messageData['target_audience_display'] = 'For everyone';
            } elseif ($msg['recipientRole'] == $sessionRole) {
                $messageData['target_audience_display'] = 'For all ' . htmlspecialchars(ucfirst($sessionRole)) . 's';
            } else {
                $messageData['target_audience_display'] = 'General';
            }
            if ($msg['senderID']) {
                $messageData['sender_name'] = trim(htmlspecialchars($msg['sender_firstName'] . ' ' . $msg['sender_lastName']));
            } else { 
                $messageData['sender_name'] = 'WavePass System';
            }
            $messagesToDisplay[] = $messageData;
        }
        
        if ($sessionRole == 'admin') {
            if (!$dbErrorMessage) { 
                $stmtUsers = $pdo->query("SELECT userID, firstName, lastName, username FROM users WHERE userID != " . (int)$sessionUserId . " ORDER BY lastName, firstName");
                $usersForAdminForm = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
            }
        }

    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error: " . $e->getMessage(); 
    } catch (Exception $e) {
        $dbErrorMessage = "An application error occurred: " . $e->getMessage();
    }
} else {
    if (!isset($pdo) || !($pdo instanceof PDO)) $dbErrorMessage = ($dbErrorMessage ? $dbErrorMessage . "<br>" : "") . "Database connection is not available.";
    if (!$sessionUserId) $dbErrorMessage = ($dbErrorMessage ? $dbErrorMessage . "<br>" : "") . "User session is invalid.";
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="imgs/logo.png" type="image/x-icon"> 
    <title>Messages - <?php echo $sessionFirstName; ?> - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color-val: 76, 175, 80;
            --warning-color-val: 255, 152, 0;
            --danger-color-val: 244, 67, 54;
            --info-color-val: 33, 150, 243;
            --system-color-val: 117, 117, 117;

            --success-color: rgb(var(--success-color-val));
            --warning-color: rgb(var(--warning-color-val));
            --danger-color: rgb(var(--danger-color-val));
            --info-color: rgb(var(--info-color-val));
            --system-color: rgb(var(--system-color-val));
            
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
            --primary-color-rgb: 67, 97, 238; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color);
            background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh;
        }
        main { flex-grow: 1; padding-top: 80px; }
        .container-messages { 
            display: flex; 
            flex-direction: column;
            max-width: 1400px; margin: 0 auto; padding: 0 20px; gap: 1.5rem; 
        }
        .messages-sidebar {
            flex-basis: 100%; 
            background-color: var(--white);
            padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow);
            height: fit-content; margin-top: 1.5rem; 
        }
        .messages-content { 
            flex-grow: 1; 
            margin-top: 0; 
        }

        @media (min-width: 993px) { 
            .container-messages { flex-direction: row; }
            .messages-sidebar { flex: 0 0 280px; margin-top: 0; }
            .messages-content { margin-top: 0; }
        }

        header {
            background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: fixed; width: 100%; top: 0; z-index: 1000;
        }
        .navbar-container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 80px; }
        .logo { font-size: 1.8rem; font-weight: 800; color: var(--primary-color); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .logo img { height: 30px; margin-right: 0.5rem; }
        .logo span { color: var(--dark-color); font-weight: 600; }
        .nav-links { display: none; list-style: none; align-items: center; gap: 0.5rem; }
        .nav-links a { color: var(--dark-color); text-decoration: none; font-weight: 500; padding: 0.7rem 1rem; border-radius: 8px; transition: var(--transition); }
        .nav-links a:hover, .nav-links a.active-nav-link { color: var(--primary-color); background-color: rgba(var(--primary-color-rgb), 0.1); }
        .nav-links .btn, .nav-links .btn-outline { padding: 0.6rem 1.2rem; font-size: 0.9rem; }
        .hamburger { display: flex; flex-direction:column; justify-content:space-around; cursor: pointer; width: 30px; height: 24px; position: relative; border: none; background: transparent; padding: 0; }
        .hamburger span { display: block; width: 100%; height: 3px; background-color: var(--dark-color); position: absolute; left: 0; transition: var(--transition); transform-origin: center; }
        .hamburger span:nth-child(1) { top: 0; } .hamburger span:nth-child(2) { top: 50%; transform: translateY(-50%); } .hamburger span:nth-child(3) { bottom: 0; }
        .hamburger.active span:nth-child(1) { top: 50%; transform: translateY(-50%) rotate(45deg); } .hamburger.active span:nth-child(2) { opacity: 0; } .hamburger.active span:nth-child(3) { bottom: 50%; transform: translateY(50%) rotate(-45deg); }
        .mobile-menu { position: fixed; top: 0; left: 0; width: 100%; height: 100vh; background-color: var(--white); z-index: 999; display: flex; flex-direction: column; align-items: center; justify-content: center; transform: translateX(-100%); transition: transform 0.3s ease-in-out; padding: 2rem; }
        .mobile-menu.active { transform: translateX(0); }
        .mobile-links { list-style: none; text-align: center; width: 100%; max-width: 300px; padding:0; }
        .mobile-links li { margin-bottom: 1.5rem; }
        .mobile-links a { color: var(--dark-color); text-decoration: none; font-weight: 600; font-size: 1.2rem; display: block; padding: 0.5rem 1rem; transition: var(--transition); border-radius: 8px; }
        .mobile-links a:hover, .mobile-links a.active-nav-link { color: var(--primary-color); background-color: rgba(var(--primary-color-rgb), 0.1); }
        .close-btn { position: absolute; top: 20px; right: 20px; font-size: 2rem; color: var(--dark-color); cursor: pointer; transition: var(--transition); background: none; border: none; padding: 0.5rem; line-height: 1; }
        .close-btn:hover { color: var(--primary-color); transform: rotate(90deg); }
        @media (min-width: 993px) { .nav-links { display: flex; } .hamburger { display: none; } }
        
        .messages-sidebar h3 { font-size: 1.2rem; margin-bottom: 1rem; color: var(--dark-color); padding-bottom: 0.5rem; border-bottom: 1px solid var(--light-gray); }
        .filter-list { list-style: none; padding: 0; }
        .filter-list li a { display: flex; align-items: center; gap: 0.7rem; padding: 0.8rem 1rem; text-decoration: none; color: var(--dark-color); border-radius: 6px; transition: var(--transition); font-weight: 500; }
        .filter-list li a:hover, .filter-list li a.active-filter { background-color: rgba(var(--primary-color-rgb), 0.07); color: var(--primary-color); }
        .filter-list li a .material-symbols-outlined { font-size: 1.3em; }

        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header .container {max-width: 1400px; margin: 0 auto; padding: 0 20px;} 
        .page-header h1 { font-size: 1.7rem; } .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        
        .db-error-message, .success-message { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .db-error-message { background-color: rgba(var(--danger-color-val),0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        .success-message { background-color: rgba(var(--success-color-val),0.1); color: var(--success-color); border-left-color: var(--success-color); }

        .messages-list { display: flex; flex-direction: column; gap: 1.5rem; } 
        .message-card {
            background-color: var(--white); border-radius: 10px; box-shadow: var(--shadow);
            border-left: 6px solid var(--info-color); padding: 1.5rem 1.8rem;
            position: relative; transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
        }
        .message-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .message-card.type-announcement { border-left-color: var(--primary-color); }
        .message-card.type-warning { border-left-color: var(--warning-color); }
        .message-card.type-system { border-left-color: var(--system-color); }
        .message-card.is-urgent { 
            border-left-color: var(--danger-color); 
            background-color: rgba(var(--danger-color-val), 0.04); 
        }
        .message-card.is-urgent .message-title { color: var(--danger-color); }

        .message-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;}
        .message-title-wrapper { display: flex; align-items: center; gap: 0.8rem; flex-grow: 1; }
        .new-badge {
            background-color: var(--danger-color); color: var(--white);
            font-size: 0.7rem; font-weight: bold; padding: 3px 8px; border-radius: 5px;
            text-transform: uppercase; letter-spacing: 0.5px; line-height: 1;
        }
        .message-title { font-size: 1.3rem; font-weight: 600; margin:0; }
        
        .message-meta { font-size: 0.85rem; color: var(--gray-color); text-align: right; flex-shrink: 0; line-height: 1.4; }
        .message-meta .target-audience { display: block; font-weight: 500; color: var(--secondary-color); margin-bottom: 0.1rem; text-transform: capitalize; }
        .message-meta .sender-name, .message-meta .message-date { display: block; }
        
        .message-content { font-size: 0.95rem; color: #444; line-height: 1.7; margin-bottom: 1.2rem; clear: both; padding-top: 0.5rem; }
        
        .no-messages { text-align: center; padding: 3rem 1rem; background-color: var(--white); border-radius: 8px; box-shadow: var(--shadow); color: var(--gray-color); font-size: 1.1rem; }
        .no-messages .material-symbols-outlined { font-size: 3rem; display: block; margin-bottom: 1rem; color: var(--primary-color); }
        
        .message-actions { margin-top: 1.2rem; text-align: right; border-top: 1px solid var(--light-gray); padding-top: 1.2rem;}
        .btn-toggle-read {
            background-color: var(--light-gray); color: var(--dark-color);
            border: 1px solid #ccc; padding: 0.5rem 1rem;
            border-radius: 6px; font-size: 0.85rem; cursor: pointer;
            transition: var(--transition); display: inline-flex; align-items: center;
            font-weight: 500;
        }
        .btn-toggle-read:hover { border-color: var(--gray-color); background-color: #dde2e7;}
        .btn-toggle-read .material-symbols-outlined { font-size: 1.2em; vertical-align: middle; margin-right: 0.4rem; }
        
        .message-card.is-unread .btn-toggle-read {
            background-color: var(--primary-color); color: var(--white); border-color: var(--primary-color);
        }
        .message-card.is-unread .btn-toggle-read:hover { background-color: var(--primary-dark); }
        
        .message-card:not(.is-unread) .btn-toggle-read { 
             background-color: var(--white); color: var(--primary-color); border-color: var(--primary-color);
        }
        .message-card:not(.is-unread) .btn-toggle-read:hover {
            background-color: rgba(var(--primary-color-rgb), 0.05);
        }

        .admin-send-message-panel { background-color: var(--white); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); margin-top: 1.5rem; }
        .admin-send-message-panel h3 { font-size: 1.2rem; margin-bottom: 1rem; color: var(--dark-color); padding-bottom: 0.5rem; border-bottom: 1px solid var(--light-gray); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.9rem; }
        .form-group input[type="text"], .form-group textarea, .form-group select { width: 100%; padding: 0.7rem; border: 1px solid var(--light-gray); border-radius: 6px; font-family: inherit; font-size: 0.9rem; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline:none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb),0.2); }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-group input[type="checkbox"] { margin-right: 0.5rem; vertical-align: middle; }
        .form-group .btn-send-message { background-color: var(--success-color); color: var(--white); border: none; padding: 0.7rem 1.5rem; border-radius: 6px; font-weight: 600; cursor: pointer; transition: var(--transition); font-size: 0.95rem; }
        .form-group .btn-send-message:hover { opacity:0.9; transform: translateY(-1px); }
        #specificUserSelectContainer { display: none; margin-top: 0.5rem; }

        footer { background-color: var(--dark-color); color: var(--white); padding: 3rem 0 1.5rem; margin-top: auto; }
        .footer-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; text-align: center; }
        .footer-bottom { padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.9rem; color: rgba(255,255,255,0.7); }
        .footer-bottom a { color: rgba(255,255,255,0.9); text-decoration:none; }
        .footer-bottom a:hover { color:var(--white); }
    </style>
</head>
<body>
    <?php require "components/header-admin.php"; // Assuming this contains the header HTML, including user profile image if available ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>Messages</h1>
                <p class="sub-heading">Stay updated with important announcements and warnings.</p>
            </div>
        </div>

        <div class="container-messages">
            <aside class="messages-sidebar">
                <h3>Filter Messages</h3>
                <ul class="filter-list">
                     <li><a href="messages.php?filter=unread" class="<?php if ($currentFilter == 'unread') echo 'active-filter'; ?>">
                        <span aria-hidden="true" translate="no" class="material-symbols-outlined">mark_email_unread</span> Unread Messages</a>
                    </li>
                    <li><a href="messages.php?filter=all" class="<?php if ($currentFilter == 'all') echo 'active-filter'; ?>">
                        <span aria-hidden="true" translate="no" class="material-symbols-outlined">all_inbox</span> All Messages</a>
                    </li>
                     <li><a href="messages.php?filter=read" class="<?php if ($currentFilter == 'read') echo 'active-filter'; ?>">
                        <span aria-hidden="true" translate="no" class="material-symbols-outlined">drafts</span> Read Messages</a>
                    </li>
                    <li><a href="messages.php?filter=for_you" class="<?php if ($currentFilter == 'for_you') echo 'active-filter'; ?>">
                        <span aria-hidden="true" translate="no" class="material-symbols-outlined">person</span> For You</a>
                    </li>
                    <li><a href="messages.php?filter=for_everyone" class="<?php if ($currentFilter == 'for_everyone') echo 'active-filter'; ?>">
                        <span aria-hidden="true" translate="no" class="material-symbols-outlined">campaign</span> For Everyone</a>
                    </li>
                </ul>

                <?php if ($sessionRole == 'admin'): ?>
                <div class="admin-send-message-panel">
                    <h3>Send New Message</h3>
                    <form action="messages.php" method="POST">
                        <input type="hidden" name="action" value="send_message">
                        <div class="form-group">
                            <label for="message_title">Title</label>
                            <input type="text" id="message_title" name="message_title" required>
                        </div>
                        <div class="form-group">
                            <label for="message_content">Content</label>
                            <textarea id="message_content" name="message_content" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="message_target">Target Audience</label>
                            <select id="message_target" name="message_target" required onchange="toggleSpecificUserSelect(this.value)">
                                <option value="everyone">Everyone</option>
                                <option value="all_employees">All Employees</option>
                                <option value="all_admins">All Admins</option>
                                <option value="specific_user">Specific User</option>
                            </select>
                        </div>
                        <div class="form-group" id="specificUserSelectContainer">
                            <label for="message_target_specific_user">Select User</label>
                            <select id="message_target_specific_user" name="message_target_specific_user">
                                <option value="">-- Select User --</option>
                                <?php foreach ($usersForAdminForm as $user): ?>
                                    <option value="<?php echo $user['userID']; ?>"><?php echo htmlspecialchars($user['lastName'] . ', ' . $user['firstName'] . ' (' . $user['username'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="form-group">
                            <label for="message_type">Message Type</label>
                            <select id="message_type" name="message_type" required>
                                <option value="info">Information</option>
                                <option value="announcement">Announcement</option>
                                <option value="warning">Warning</option>
                                <option value="system">System Update</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><input type="checkbox" name="message_is_urgent" value="1"> Urgent Message</label>
                        </div>
                        <div classs="form-group">
                            <button type="submit" class="btn-send-message"><i class="fas fa-paper-plane"></i> Send Message</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </aside>

            <div class="messages-content">
                <?php if ($dbErrorMessage): ?>
                    <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbErrorMessage); ?></div>
                <?php endif; ?>
                <?php if ($successMessage): ?>
                    <div class="success-message" role="alert"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>

                <section class="messages-list">
                    <?php if (!empty($messagesToDisplay)): ?>
                        <?php foreach ($messagesToDisplay as $message): ?>
                            <article class="message-card type-<?php echo htmlspecialchars($message['message_type']); ?> <?php if ($message['is_urgent']) echo 'is-urgent'; ?> <?php if (!$message['is_read_by_user']) echo 'is-unread'; ?>" id="message-<?php echo $message['messageID']; ?>">
                                <div class="message-header">
                                    <div class="message-title-wrapper">
                                        <?php if (!$message['is_read_by_user']): ?>
                                            <span class="new-badge">New</span>
                                        <?php endif; ?>
                                        <h2 class="message-title"><?php echo htmlspecialchars($message['title']); ?></h2>
                                    </div>
                                    <div class="message-meta">
                                        <span class="target-audience"><?php echo htmlspecialchars($message['target_audience_display']); ?></span>
                                        <span class="sender-name">From: <?php echo htmlspecialchars($message['sender_name']); ?></span>
                                        <span class="message-date"><?php echo date("M d, Y - H:i", strtotime($message['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                                </div>
                                <div class="message-actions">
                                    <form action="messages.php?filter=<?php echo htmlspecialchars($currentFilter); ?>#message-<?php echo $message['messageID']; ?>" method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_read_status">
                                        <input type="hidden" name="message_id" value="<?php echo $message['messageID']; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $message['is_read_by_user']; ?>">
                                        <button type="submit" class="btn-toggle-read">
                                            <?php if ($message['is_read_by_user']): ?>
                                                <span aria-hidden="true" translate="no" class="material-symbols-outlined">mark_email_unread</span> Mark as Unread
                                            <?php else: ?>
                                                <span aria-hidden="true" translate="no" class="material-symbols-outlined">drafts</span> Mark as Read
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php if (!$dbErrorMessage): ?>
                        <div class="no-messages">
                            <span aria-hidden="true" translate="no" class="material-symbols-outlined">forum</span>
                            No messages found for the selected filter.
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>

    <?php require_once "components/footer-main.php"; ?>

    <script>
        const headerEl = document.querySelector('header');
        if (headerEl) { 
            let initialHeaderShadow = getComputedStyle(headerEl).boxShadow;
            window.addEventListener('scroll', () => {
                let scrollShadow = getComputedStyle(document.documentElement).getPropertyValue('--shadow').trim() || '0 4px 10px rgba(0,0,0,0.05)';
                if (window.scrollY > 10) {
                    headerEl.style.boxShadow = scrollShadow; 
                } else {
                    headerEl.style.boxShadow = initialHeaderShadow;
                }
            });
        }

        function toggleSpecificUserSelect(targetValue) {
            const container = document.getElementById('specificUserSelectContainer');
            const selectUser = document.getElementById('message_target_specific_user');
            if(container && selectUser){ 
                if (targetValue === 'specific_user') {
                    container.style.display = 'block';
                    selectUser.required = true;
                } else {
                    container.style.display = 'none';
                    selectUser.required = false;
                    selectUser.value = ''; 
                }
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const initialTarget = document.getElementById('message_target');
            if (initialTarget) {
                toggleSpecificUserSelect(initialTarget.value);
            }

            // Hamburger Menu Functionality
            const hamburger = document.querySelector('.hamburger');
            const mobileMenu = document.querySelector('.mobile-menu');
            const closeMenuBtn = mobileMenu ? mobileMenu.querySelector('.close-btn') : null;
            const mobileLinks = mobileMenu ? mobileMenu.querySelectorAll('.mobile-links a') : [];

            if (hamburger && mobileMenu) {
                hamburger.addEventListener('click', () => {
                    hamburger.classList.toggle('active');
                    mobileMenu.classList.toggle('active');
                    document.body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
                });
            }
            if (closeMenuBtn) {
                closeMenuBtn.addEventListener('click', () => {
                    if(hamburger) hamburger.classList.remove('active');
                    mobileMenu.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }
            mobileLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if(hamburger) hamburger.classList.remove('active');
                    mobileMenu.classList.remove('active');
                    document.body.style.overflow = '';
                });
            });
        });
    </script>
</body>
</html>