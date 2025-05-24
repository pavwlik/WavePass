<?php
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

$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$sessionAdminUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;

$dbErrorMessage = null;
$successMessage = null;
$allUsersForSelect = [];
$sentMessagesDetails = [];

// Check for messages from admin-message-edit.php
if (isset($_SESSION['message_operation_status'])) {
    if ($_SESSION['message_operation_status']['type'] === 'success') {
        $successMessage = $_SESSION['message_operation_status']['text'];
    } else {
        $dbErrorMessage = $_SESSION['message_operation_status']['text'];
    }
    unset($_SESSION['message_operation_status']);
}


$activeSection = isset($_GET['section']) ? $_GET['section'] : 'compose';

// --- HANDLE DELETE ACTION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_message_from_history') {
    if (!isset($pdo)) {
        $dbErrorMessage = "Database connection not available.";
    } else {
        $messageIdToDelete = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
        if ($messageIdToDelete) {
            try {
                $pdo->beginTransaction();

                $stmtDeleteReadStatus = $pdo->prepare("DELETE FROM user_message_read_status WHERE messageID = :messageID");
                $stmtDeleteReadStatus->execute([':messageID' => $messageIdToDelete]);

                $stmtDeleteMessage = $pdo->prepare("DELETE FROM messages WHERE messageID = :messageID AND is_system_message = 0");
                $stmtDeleteMessage->execute([':messageID' => $messageIdToDelete]);

                if ($stmtDeleteMessage->rowCount() > 0) {
                    $pdo->commit();
                    $successMessage = "Announcement (ID: $messageIdToDelete) and its read records deleted successfully.";
                } else {
                    $pdo->rollBack();
                    $dbErrorMessage = "Announcement (ID: $messageIdToDelete) not found or could not be deleted.";
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $dbErrorMessage = "Database error deleting announcement: " . $e->getMessage();
                error_log("Admin Messages - DB Error deleting message (ID: $messageIdToDelete): " . $e->getMessage());
            }
        } else {
            $dbErrorMessage = "Invalid message ID provided for deletion.";
        }
    }
    $_GET['section'] = 'history';
    $activeSection = 'history';
}


// --- DATA FETCHING ---
if (isset($pdo)) {
    try {
        if ($activeSection === 'compose') {
            $stmtUsers = $pdo->prepare("SELECT userID, firstName, lastName, username FROM users WHERE userID != :currentAdminID ORDER BY lastName, firstName");
            $stmtUsers->execute([':currentAdminID' => $sessionAdminUserId]);
            $allUsersForSelect = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($activeSection === 'history') {
            $stmtSentMessagesBasic = $pdo->prepare("
                SELECT 
                    m.messageID, m.title, m.content, m.recipientID, m.recipientRole, 
                    m.created_at, m.expires_at, m.is_urgent, m.is_active,
                    u_sender.username AS sender_username,
                    u_recipient.username AS specific_recipient_username,
                    (SELECT COUNT(DISTINCT umrs.userID) FROM user_message_read_status umrs WHERE umrs.messageID = m.messageID AND umrs.is_read = 1) as read_count
                FROM messages m
                LEFT JOIN users u_sender ON m.senderID = u_sender.userID
                LEFT JOIN users u_recipient ON m.recipientID = u_recipient.userID 
                WHERE m.is_system_message = 0 
                ORDER BY m.created_at DESC
            ");
            $stmtSentMessagesBasic->execute();
            $messagesData = $stmtSentMessagesBasic->fetchAll(PDO::FETCH_ASSOC);

            if ($messagesData) {
                $allSystemUsersStmt = $pdo->query("SELECT userID, firstName, lastName, username, roleID FROM users");
                $allSystemUsersLookup = [];
                foreach($allSystemUsersStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
                    $allSystemUsersLookup[$u['userID']] = $u;
                }

                $allReadStatusesStmt = $pdo->query("SELECT messageID, userID FROM user_message_read_status WHERE is_read = 1");
                $messagesReadByUsersLookup = [];
                foreach($allReadStatusesStmt->fetchAll(PDO::FETCH_ASSOC) as $status) {
                    $messagesReadByUsersLookup[$status['messageID']][] = $status['userID'];
                }

                foreach ($messagesData as $msg) {
                    $potentialRecipientIDs = [];
                    if ($msg['recipientID']) {
                        if (isset($allSystemUsersLookup[$msg['recipientID']])) {
                            $potentialRecipientIDs[] = $msg['recipientID'];
                        }
                    } elseif ($msg['recipientRole']) {
                        foreach ($allSystemUsersLookup as $userID => $userDetails) {
                            if ($msg['recipientRole'] === 'everyone' || $userDetails['roleID'] === $msg['recipientRole']) {
                                $potentialRecipientIDs[] = $userID;
                            }
                        }
                    }
                    $msg['total_potential_recipients'] = count($potentialRecipientIDs);
                    $actualReaderIDs = $messagesReadByUsersLookup[$msg['messageID']] ?? [];
                    $msg['read_by_user_details'] = [];
                    $msg['not_read_by_user_details'] = [];

                    foreach ($potentialRecipientIDs as $potentialUserID) {
                        $userDisplayInfo = $allSystemUsersLookup[$potentialUserID] ?? null;
                        if ($userDisplayInfo) {
                             $displayName = htmlspecialchars($userDisplayInfo['firstName'] . " " . $userDisplayInfo['lastName'] . " (" . $userDisplayInfo['username'] . ")");
                            if (in_array($potentialUserID, $actualReaderIDs)) {
                                $msg['read_by_user_details'][] = ['id' => $potentialUserID, 'name' => $displayName];
                            } else {
                                $msg['not_read_by_user_details'][] = ['id' => $potentialUserID, 'name' => $displayName];
                            }
                        }
                    }
                    $sentMessagesDetails[] = $msg;
                }
            }
        }
    } catch (PDOException $e) {
        $dbErrorMessage = "Database Error: " . $e->getMessage();
        error_log("Admin Messages - DB Error: " . $e->getMessage());
    }
} else {
    $dbErrorMessage = "Database connection not available.";
}

// --- HANDLE FORM SUBMISSION (SEND MESSAGE - from Compose section) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'send_message') {
    if (!isset($pdo)) {
        $dbErrorMessage = "Database connection not available. Cannot send message.";
    } else {
        $title = trim(filter_input(INPUT_POST, 'message_title', FILTER_SANITIZE_SPECIAL_CHARS));
        $content = trim(filter_input(INPUT_POST, 'message_content', FILTER_SANITIZE_SPECIAL_CHARS));
        $recipientType = filter_input(INPUT_POST, 'recipient_type', FILTER_SANITIZE_SPECIAL_CHARS);
        $specificUserID = filter_input(INPUT_POST, 'specific_user_id', FILTER_VALIDATE_INT);
        $messageType = 'announcement';
        $isUrgent = isset($_POST['is_urgent']) ? 1 : 0;
        $expiresAtInput = trim($_POST['expires_at'] ?? '');
        $expiresAt = NULL;

        if (!empty($expiresAtInput)) {
            $dateTime = DateTime::createFromFormat('Y-m-d', $expiresAtInput);
            if ($dateTime) {
                $expiresAt = $dateTime->format('Y-m-d 23:59:59'); 
            } else {
                 $dbErrorMessage = "Invalid expiration date format. Please use YYYY-MM-DD.";
            }
        }

        if (empty($title) || empty($content)) {
            $dbErrorMessage = "Message title and content are required.";
        } elseif (empty($recipientType)) {
            $dbErrorMessage = "Please select a recipient type.";
        } elseif ($recipientType === 'specific_user' && (empty($specificUserID) || $specificUserID === false)) {
            $dbErrorMessage = "Please select a valid specific user if 'Specific User' is chosen.";
        } elseif (!$dbErrorMessage) { 
            try {
                // Note: added is_active = 1 for new messages
                $sql = "INSERT INTO messages (senderID, recipientID, recipientRole, title, content, message_type, is_urgent, is_system_message, expires_at, created_at, updated_at, is_active) 
                        VALUES (:senderID, :recipientID, :recipientRole, :title, :content, :messageType, :isUrgent, 0, :expiresAt, NOW(), NOW(), 1)";
                $stmt = $pdo->prepare($sql);

                $paramSenderID = $sessionAdminUserId;
                $paramRecipientID = NULL;
                $paramRecipientRole = NULL;

                if ($recipientType === 'all_users') {
                    $paramRecipientRole = 'everyone';
                } elseif ($recipientType === 'all_employees') {
                    $paramRecipientRole = 'employee';
                } elseif ($recipientType === 'all_admins') {
                    $paramRecipientRole = 'admin';
                } elseif ($recipientType === 'specific_user' && $specificUserID) {
                    $paramRecipientID = $specificUserID;
                } else {
                    $dbErrorMessage = "Invalid recipient selection.";
                }

                if (!$dbErrorMessage) {
                    $stmt->bindParam(':senderID', $paramSenderID, PDO::PARAM_INT);
                    $stmt->bindParam(':recipientID', $paramRecipientID, PDO::PARAM_INT);
                    $stmt->bindParam(':recipientRole', $paramRecipientRole, PDO::PARAM_STR);
                    $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                    $stmt->bindParam(':content', $content, PDO::PARAM_STR);
                    $stmt->bindParam(':messageType', $messageType, PDO::PARAM_STR);
                    $stmt->bindParam(':isUrgent', $isUrgent, PDO::PARAM_INT);
                    $stmt->bindParam(':expiresAt', $expiresAt, PDO::PARAM_STR);

                    if ($stmt->execute()) {
                        $successMessage = "Announcement sent successfully!";
                        $_POST = array(); 
                    } else {
                        $dbErrorMessage = "Failed to send announcement. SQL Error: " . implode(", ", $stmt->errorInfo());
                    }
                }
            } catch (PDOException $e) {
                $dbErrorMessage = "Database error sending announcement: " . $e->getMessage();
                error_log("Admin Messages - DB Error sending message (AdminID: {$sessionAdminUserId}): " . $e->getMessage());
            }
        }
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
$pathPrefix = "../";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?php echo htmlspecialchars($pathPrefix); ?>imgs/logo.png" type="image/x-icon">
    <title>Manage Messages - Admin - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Styles from previous correct version */
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4;
            --primary-color-rgb: 67, 97, 238; 
            --secondary-color: #3f37c9; --dark-color: #1a1a2e;
            --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --danger-color: #F44336;  --info-color: #2196F3;
            --warning-color: #FF9800;
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
        .container { max-width: 1600px; margin: 0 auto; padding: 0 25px; }
        
        .page-header { padding: 2rem 0; margin-bottom: 2rem; background-color:var(--white); box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .page-header h1 { font-size: 1.8rem; color: var(--dark-color); margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); margin-top: 0.2rem; }

        .message-output { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .db-error-message { background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        .success-message { background-color: rgba(76,175,80,0.1); color: var(--success-color); border-left-color: var(--success-color); }

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
        .content-section h2.section-title { font-size: 1.5rem; color: var(--dark-color); margin-bottom: 2rem; padding-bottom: 1.2rem; border-bottom: 1px solid var(--light-gray); }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #333; }
        .form-group input[type="text"], .form-group input[type="date"], .form-group textarea, .form-group select {
            width: 100%; padding: 0.8rem 1rem; border: 1px solid #ccc;
            border-radius: 6px; font-family: inherit; font-size: 0.95rem;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { 
            outline:none; border-color: var(--primary-color); 
            box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb),0.25); 
        }
        .form-group .checkbox-label { display: flex; align-items: center; font-weight: normal; font-size: 0.95rem; }
        .form-group .checkbox-label input[type="checkbox"] { margin-right: 0.75rem; width: auto; transform: scale(1.1);}
        .btn-submit { 
            background-color: var(--primary-color); color: var(--white); border:none; 
            padding: 0.8rem 1.8rem; border-radius: 6px; text-decoration:none; 
            font-weight: 600; cursor:pointer; transition: var(--transition); 
            display: inline-flex; align-items: center; gap: 0.6rem; font-size: 1rem;
        }
        .btn-submit:hover { background-color: var(--primary-dark); transform: translateY(-1px); }
        #specific_user_select_group { display: none; }

        .sent-messages-table-wrapper { overflow-x: auto; }
        .sent-messages-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; margin-top: 1.5rem; table-layout: auto;}
        .sent-messages-table th, .sent-messages-table td { padding: 0.8rem 0.6rem; text-align: left; border-bottom: 1px solid var(--light-gray); vertical-align: top;}
        .sent-messages-table th { background-color: #f9fafb; font-weight: 600; color: var(--gray-color); font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; white-space: nowrap;}
        .sent-messages-table tbody tr:hover { background-color: #f7f9fc; }
        .sent-messages-table td .content-snippet { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display:block; margin-bottom: 5px;}
        .status-dot { height: 9px; width: 9px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .status-dot.active { background-color: var(--success-color); }
        .status-dot.expired { background-color: var(--warning-color); }
        .read-status-indicator { font-size: 0.8rem; color: var(--gray-color); }
        .read-details-toggle { cursor: pointer; color: var(--primary-color); font-size: 0.8rem; text-decoration: underline; }
        .read-details-lists { display: none; margin-top: 8px; padding: 8px; background-color: #f9f9f9; border: 1px solid var(--light-gray); border-radius: 4px; font-size: 0.8rem; max-height: 150px; overflow-y: auto;}
        .read-details-lists strong { display: block; margin-bottom: 4px; }
        .read-details-lists ul { list-style: none; padding-left: 0; margin: 0; }
        .read-details-lists li { padding: 2px 0; }
        .no-messages-info { padding: 2rem; text-align: center; color: var(--gray-color); background-color: #fdfdfd; border: 1px dashed var(--light-gray); border-radius: 6px;}
        
        .actions-cell { white-space: nowrap; text-align: right; }
        .action-btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 5px 8px; margin: 0 3px; border: none;
            border-radius: 4px; cursor: pointer; transition: background-color 0.2s ease;
            text-decoration: none; color: white;
        }
        .action-btn .material-symbols-outlined { font-size: 1.1rem; vertical-align: middle; }
        .action-btn.edit-btn { background-color: var(--info-color); }
        .action-btn.edit-btn:hover { background-color: #1a7cba; }
        .action-btn.delete-btn { background-color: var(--danger-color); }
        .action-btn.delete-btn:hover { background-color: #c03029; }

        @media (max-width: 1200px) { .sent-messages-table td .content-snippet { max-width: 150px; } }
        @media (max-width: 992px) { .account-layout { flex-direction: column; } .account-sidebar { width: 100%; margin-bottom:2rem; flex: 0 0 auto; } }
        @media (max-width: 768px) { .container { padding: 0 15px; } .account-content{padding:1.5rem;} .sent-messages-table { font-size: 0.85rem; } .sent-messages-table th, .sent-messages-table td {padding: 0.6rem 0.4rem;} .sent-messages-table td .content-snippet { max-width: 100px; } .actions-cell .action-btn .material-symbols-outlined { font-size: 1rem; } }
    </style>
</head>
<body>
    <?php require_once $pathPrefix . "components/header-admin.php"; ?>
    <main>
        <div class="page-header">
            <div class="container">
                <h1>Manage Announcements</h1>
                <p class="sub-heading">Compose new announcements or review sent messages with detailed read status.</p>
            </div>
        </div>
        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if ($dbErrorMessage): ?>
                <div class="message-output db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbErrorMessage); ?></div>
            <?php endif; ?>
            <?php if ($successMessage): ?>
                <div class="message-output success-message" role="alert"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <div class="account-layout">
                <aside class="account-sidebar">
                    <h3>Messages Menu</h3>
                    <ul>
                        <li><a href="?section=compose" class="<?php if ($activeSection === 'compose') echo 'active'; ?>"><span class="material-symbols-outlined">edit_square</span> Compose New</a></li>
                        <li><a href="?section=history" class="<?php if ($activeSection === 'history') echo 'active'; ?>"><span class="material-symbols-outlined">history</span> Sent History</a></li>
                    </ul>
                </aside>
                <section class="account-content">
                    <div id="compose-section" class="content-section <?php if ($activeSection === 'compose') echo 'active'; ?>">
                        <h2 class="section-title">Compose New Announcement</h2>
                        <form action="admin-messages.php?section=compose" method="POST" id="sendMessageForm">
                            <input type="hidden" name="action" value="send_message">
                            <div class="form-group">
                                <label for="message_title">Title <span style="color:red;">*</span></label>
                                <input type="text" id="message_title" name="message_title" value="<?php echo isset($_POST['message_title']) ? htmlspecialchars($_POST['message_title']) : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="message_content">Content <span style="color:red;">*</span></label>
                                <textarea id="message_content" name="message_content" rows="6" required><?php echo isset($_POST['message_content']) ? htmlspecialchars($_POST['message_content']) : ''; ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="recipient_type">Send To <span style="color:red;">*</span></label>
                                <select id="recipient_type" name="recipient_type" required onchange="toggleSpecificUserSelect()">
                                    <option value="">-- Select Recipient --</option>
                                    <option value="all_users" <?php echo (isset($_POST['recipient_type']) && $_POST['recipient_type'] == 'all_users') ? 'selected' : ''; ?>>All Users (Everyone)</option>
                                    <option value="all_employees" <?php echo (isset($_POST['recipient_type']) && $_POST['recipient_type'] == 'all_employees') ? 'selected' : ''; ?>>All Employees</option>
                                    <option value="all_admins" <?php echo (isset($_POST['recipient_type']) && $_POST['recipient_type'] == 'all_admins') ? 'selected' : ''; ?>>All Admins</option>
                                    <option value="specific_user" <?php echo (isset($_POST['recipient_type']) && $_POST['recipient_type'] == 'specific_user') ? 'selected' : ''; ?>>Specific User</option>
                                </select>
                            </div>
                            <div class="form-group" id="specific_user_select_group">
                                <label for="specific_user_id">Select Specific User</label>
                                <select id="specific_user_id" name="specific_user_id">
                                    <option value="">-- Select User --</option>
                                    <?php if (!empty($allUsersForSelect)): ?>
                                        <?php foreach ($allUsersForSelect as $user): ?>
                                            <option value="<?php echo $user['userID']; ?>" <?php echo (isset($_POST['specific_user_id']) && $_POST['specific_user_id'] == $user['userID']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['lastName'] . ', ' . $user['firstName'] . ' (' . $user['username'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?> <option value="" disabled>No other users to select</option> <?php endif; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="expires_at">Expires On (Optional)</label>
                                <input type="date" id="expires_at" name="expires_at" value="<?php echo isset($_POST['expires_at']) ? htmlspecialchars($_POST['expires_at']) : ''; ?>">
                                <small>Message will become inactive after this date.</small>
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label"><input type="checkbox" name="is_urgent" value="1" <?php echo isset($_POST['is_urgent']) ? 'checked' : ''; ?>> Mark as Urgent</label>
                            </div>
                            <div class="form-actions" style="text-align:left;"><button type="submit" class="btn-submit"><span class="material-symbols-outlined">send</span> Send Announcement</button></div>
                        </form>
                    </div>
                    <div id="history-section" class="content-section <?php if ($activeSection === 'history') echo 'active'; ?>">
                        <h2 class="section-title">Sent Messages History</h2>
                        <?php if (!empty($sentMessagesDetails)): ?>
                            <div class="sent-messages-table-wrapper">
                                <table class="sent-messages-table">
                                    <thead>
                                        <tr>
                                            <th>Title</th><th>Snippet</th><th>Recipient(s)</th><th>Sent On</th><th>Status</th><th>Read Info</th><th style="text-align:right;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sentMessagesDetails as $msg): ?>
                                            <tr>
                                                <td><?php if ($msg['is_urgent']): ?><span class="material-symbols-outlined" style="color:var(--danger-color); font-size:1.1em; vertical-align:middle;" title="Urgent">priority_high</span><?php endif; ?><?php echo htmlspecialchars($msg['title']); ?></td>
                                                <td><span class="content-snippet" title="<?php echo htmlspecialchars($msg['content']); ?>"><?php echo htmlspecialchars(mb_substr($msg['content'], 0, 40) . (mb_strlen($msg['content']) > 40 ? '...' : '')); ?></span></td>
                                                <td><?php if ($msg['recipientRole']) { echo "Role: " . htmlspecialchars(ucfirst($msg['recipientRole'])); } elseif ($msg['specific_recipient_username']) { echo "User: " . htmlspecialchars($msg['specific_recipient_username']); } else { echo "N/A"; } ?></td>
                                                <td><?php echo date("M d, Y H:i", strtotime($msg['created_at'])); ?></td>
                                                <td><?php $statusClass = 'active'; $statusText = 'Active'; if (!$msg['is_active']) { $statusClass = 'expired'; $statusText = 'Inactive'; } elseif ($msg['expires_at'] && strtotime($msg['expires_at']) < time()) { $statusClass = 'expired'; $statusText = 'Expired'; } echo '<span class="status-dot ' . $statusClass . '"></span> ' . $statusText; ?></td>
                                                <td class="read-status-indicator"><?php echo (int)$msg['read_count']; ?> / <?php echo (int)$msg['total_potential_recipients']; ?> read <br>
                                                    <a href="javascript:void(0);" class="read-details-toggle" data-message-id="<?php echo $msg['messageID']; ?>">Show Details</a>
                                                    <div class="read-details-lists" id="details-list-<?php echo $msg['messageID']; ?>">
                                                        <strong>Read by:</strong>
                                                        <?php if (!empty($msg['read_by_user_details'])): ?><ul><?php foreach($msg['read_by_user_details'] as $reader) { echo '<li>' . $reader['name'] . '</li>'; } ?></ul><?php else: ?><p>None yet.</p><?php endif; ?>
                                                        <hr style="margin: 5px 0;">
                                                        <strong>Not read by:</strong>
                                                        <?php if (!empty($msg['not_read_by_user_details'])): ?><ul><?php foreach($msg['not_read_by_user_details'] as $nonReader) { echo '<li>' . $nonReader['name'] . '</li>'; } ?></ul><?php else: ?><p>All potential recipients have read or none targeted.</p><?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="actions-cell">
                                                    <a href="admin-message-edit.php?id=<?php echo $msg['messageID']; ?>" class="action-btn edit-btn" title="Edit Message"><span class="material-symbols-outlined">edit</span></a>
                                                    <form method="POST" action="admin-messages.php?section=history" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this announcement? This will also remove all read statuses for it. This action cannot be undone.');">
                                                        <input type="hidden" name="action" value="delete_message_from_history"><input type="hidden" name="message_id" value="<?php echo $msg['messageID']; ?>">
                                                        <button type="submit" class="action-btn delete-btn" title="Delete Message"><span class="material-symbols-outlined">delete</span></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?> <p class="no-messages-info">No announcements have been sent yet.</p> <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </main>
    <?php require_once $pathPrefix . "components/footer-admin.php"; ?>
    <script>
        function toggleSpecificUserSelect() {
            const recipientTypeSelect = document.getElementById('recipient_type');
            const specificUserGroup = document.getElementById('specific_user_select_group');
            const specificUserSelect = document.getElementById('specific_user_id');
            if (recipientTypeSelect && specificUserGroup && specificUserSelect) {
                if (recipientTypeSelect.value === 'specific_user') {
                    specificUserGroup.style.display = 'block';
                    specificUserSelect.required = true;
                } else {
                    specificUserGroup.style.display = 'none';
                    specificUserSelect.required = false;
                    if (recipientTypeSelect.value !== 'specific_user') { specificUserSelect.value = ''; }
                }
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            toggleSpecificUserSelect(); 
            const detailToggles = document.querySelectorAll('.read-details-toggle');
            detailToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const messageId = this.dataset.messageId;
                    const detailListDiv = document.getElementById('details-list-' + messageId);
                    if (detailListDiv) {
                        const isHidden = detailListDiv.style.display === 'none' || detailListDiv.style.display === '';
                        detailListDiv.style.display = isHidden ? 'block' : 'none';
                        this.textContent = isHidden ? 'Hide Details' : 'Show Details';
                    }
                });
            });
        });
    </script>
</body>
</html>