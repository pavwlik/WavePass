<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Restrict Access: Ensure only admin can access
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
$sentMessagesData = [];

// Determine current view (send form or overview)
$view = isset($_GET['view']) ? $_GET['view'] : 'overview'; // Default to overview
if (!in_array($view, ['send', 'overview'])) {
    $view = 'overview'; // Sanitize
}
$currentView = $view; // For active sidebar link

// --- DATA FETCHING FOR USER SELECTION DROPDOWN (Only for 'send' view) ---
if ($view === 'send' && isset($pdo) && $sessionAdminUserId) {
    try {
        $stmtUsers = $pdo->prepare("SELECT userID, firstName, lastName, username FROM users WHERE userID != :currentAdminID AND status = 'active' ORDER BY lastName, firstName");
        // Assuming 'status' = 'active' for users who can receive messages. Adjust if column name is different or not used.
        $stmtUsers->execute([':currentAdminID' => $sessionAdminUserId]);
        $allUsersForSelect = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $dbErrorMessage = "Database Error fetching users: " . $e->getMessage();
        error_log("Admin Messages - DB Error fetching users: " . $e->getMessage());
    }
} elseif ($view === 'send' && !isset($pdo)) {
     $dbErrorMessage = "Database connection not available.";
}


// --- HANDLE FORM SUBMISSION (SEND MESSAGE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'send_message') {
    if (!isset($pdo)) {
        $dbErrorMessage = "Database connection not available. Cannot send message.";
    } else {
        $title = trim(filter_input(INPUT_POST, 'message_title', FILTER_SANITIZE_SPECIAL_CHARS));
        $content = trim(filter_input(INPUT_POST, 'message_content', FILTER_SANITIZE_SPECIAL_CHARS)); // Consider allowing some HTML if using a rich editor later
        $recipientType = filter_input(INPUT_POST, 'recipient_type', FILTER_SANITIZE_SPECIAL_CHARS);
        $specificUserID = filter_input(INPUT_POST, 'specific_user_id', FILTER_VALIDATE_INT);
        $messageType = 'announcement';
        $isUrgent = isset($_POST['is_urgent']) ? 1 : 0;

        if (empty($title) || empty($content)) {
            $dbErrorMessage = "Message title and content are required.";
        } elseif (empty($recipientType)) {
            $dbErrorMessage = "Please select a recipient type.";
        } elseif ($recipientType === 'specific_user' && (empty($specificUserID) || $specificUserID === false)) {
            $dbErrorMessage = "Please select a valid specific user if 'Specific User' is chosen.";
        } else {
            try {
                $sql = "INSERT INTO messages (senderID, recipientID, recipientRole, title, content, message_type, is_urgent, is_system_message, expires_at, created_at, updated_at)
                        VALUES (:senderID, :recipientID, :recipientRole, :title, :content, :messageType, :isUrgent, 0, NULL, NOW(), NOW())";
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
                    if ($paramRecipientID !== NULL) {
                        $stmt->bindParam(':recipientID', $paramRecipientID, PDO::PARAM_INT);
                    } else {
                        $stmt->bindValue(':recipientID', NULL, PDO::PARAM_NULL);
                    }
                    if ($paramRecipientRole !== NULL) {
                        $stmt->bindParam(':recipientRole', $paramRecipientRole, PDO::PARAM_STR);
                    } else {
                        $stmt->bindValue(':recipientRole', NULL, PDO::PARAM_NULL);
                    }
                    $stmt->bindParam(':title', $title, PDO::PARAM_STR);
                    $stmt->bindParam(':content', $content, PDO::PARAM_STR);
                    $stmt->bindParam(':messageType', $messageType, PDO::PARAM_STR);
                    $stmt->bindParam(':isUrgent', $isUrgent, PDO::PARAM_INT);

                    if ($stmt->execute()) {
                        $successMessage = "Announcement sent successfully! You can view it in the 'Sent Messages Overview'.";
                        // Optionally redirect to overview:
                        // header("Location: admin-messages.php?view=overview&message_sent=1"); exit;
                        $_POST = array(); // Clear form if staying on send page
                    } else {
                        $dbErrorMessage = "Failed to send announcement. Please check database logs.";
                    }
                }
            } catch (PDOException $e) {
                $dbErrorMessage = "Database error sending announcement: " . $e->getMessage();
                error_log("Admin Messages - DB Error sending message (AdminID: {$sessionAdminUserId}): " . $e->getMessage());
            }
        }
    }
}

// --- DATA FETCHING FOR SENT MESSAGES OVERVIEW (Only for 'overview' view) ---
if ($view === 'overview' && isset($pdo) && $sessionAdminUserId) {
    try {
        $stmtSent = $pdo->prepare("
            SELECT
                m.messageID, m.title, m.content, m.recipientID, m.recipientRole, m.created_at, m.is_urgent,
                u_recipient.username as specificRecipientUsername,
                u_recipient.firstName as specificRecipientFirstName,
                u_recipient.lastName as specificRecipientLastName
            FROM messages m
            LEFT JOIN users u_recipient ON m.recipientID = u_recipient.userID
            WHERE m.senderID = :adminID
            ORDER BY m.created_at DESC
        ");
        $stmtSent->execute([':adminID' => $sessionAdminUserId]);
        $messages = $stmtSent->fetchAll(PDO::FETCH_ASSOC);

        foreach ($messages as $msg) {
            $messageID = $msg['messageID'];
            $readCount = 0;
            $totalRecipients = 0;
            $recipientDisplay = "N/A";

            // Get read count
            $stmtReadCount = $pdo->prepare("SELECT COUNT(*) as count FROM user_message_read_status WHERE messageID = :messageID AND is_read = 1");
            $stmtReadCount->execute([':messageID' => $messageID]);
            $readCountResult = $stmtReadCount->fetch(PDO::FETCH_ASSOC);
            if ($readCountResult) {
                $readCount = (int)$readCountResult['count'];
            }

            // Determine total recipients
            if ($msg['recipientID'] && $msg['specificRecipientUsername']) {
                // Assuming user exists and is active if recipientID is set and join was successful
                $totalRecipients = 1;
                $recipientDisplay = "User: " . htmlspecialchars($msg['specificRecipientLastName'] . ', ' . $msg['specificRecipientFirstName'] . ' (' . $msg['specificRecipientUsername'] . ')');
            } elseif ($msg['recipientID']) { // User specified but details not found (e.g. user deleted)
                $totalRecipients = 1; // Still counts as 1 target
                $recipientDisplay = "User ID: " . htmlspecialchars($msg['recipientID']) . " (Details N/A)";
            }
            elseif ($msg['recipientRole']) {
                $roleCountQuery = "";
                $roleCondition = ""; // For role in users table
                $recipientDisplay = "Group: ";

                switch ($msg['recipientRole']) {
                    case 'everyone':
                        // Assuming 'status' = 'active' for users. If not, remove "AND status = 'active'"
                        $roleCountQuery = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
                        $recipientDisplay .= "Everyone";
                        break;
                    case 'employee':
                        // Assumes users table has a 'role' column with 'employee'
                        $roleCountQuery = "SELECT COUNT(*) as count FROM users WHERE role = 'employee' AND status = 'active'";
                        $recipientDisplay .= "All Employees";
                        break;
                    case 'admin':
                        // Assumes users table has a 'role' column with 'admin'
                        $roleCountQuery = "SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active'";
                        $recipientDisplay .= "All Admins";
                        break;
                }

                if (!empty($roleCountQuery)) {
                    $stmtTotal = $pdo->prepare($roleCountQuery);
                    $stmtTotal->execute();
                    $totalResult = $stmtTotal->fetch(PDO::FETCH_ASSOC);
                    if ($totalResult) {
                        $totalRecipients = (int)$totalResult['count'];
                    }
                } else {
                    $recipientDisplay .= "Unknown Role Target";
                }
            }

            $sentMessagesData[] = [
                'messageID' => $msg['messageID'],
                'title' => $msg['title'],
                'content_snippet' => mb_substr(strip_tags($msg['content']), 0, 100) . (mb_strlen(strip_tags($msg['content'])) > 100 ? '...' : ''),
                'created_at' => date("M j, Y, g:i a", strtotime($msg['created_at'])),
                'is_urgent' => $msg['is_urgent'],
                'recipient_info' => $recipientDisplay,
                'read_status' => $readCount . " / " . $totalRecipients
            ];
        }
    } catch (PDOException $e) {
        $dbErrorMessage = "Database Error fetching sent messages: " . $e->getMessage();
        error_log("Admin Messages - DB Error fetching sent messages: " . $e->getMessage());
    }
} elseif ($view === 'overview' && !isset($pdo)) {
    $dbErrorMessage = "Database connection not available.";
}


$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../imgs/logo.png" type="image/x-icon">
    <title><?php echo ($view === 'send' ? 'Send Announcements' : 'Messages Overview'); ?> - Admin - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --primary-color-rgb: 67, 97, 238;
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --warning-color: #FF9800; --danger-color: #F44336;
            --info-color: #2196F3;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
            --sidebar-bg: #2c3e50; --sidebar-link-color: #bdc3c7; --sidebar-link-hover-bg: #34495e; --sidebar-link-active-color: #ffffff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }

        .admin-dashboard-container { display: flex; flex-grow: 1; }
        .admin-sidebar {
            width: 250px; background-color: var(--sidebar-bg); padding: 20px 0; color: var(--light-color); flex-shrink: 0;
        }
        .admin-sidebar .sidebar-header { padding: 0 20px 20px 20px; margin-bottom:15px; border-bottom: 1px solid #34495e; text-align: center; }
        .admin-sidebar .sidebar-header h3 { color: var(--white); font-size: 1.3rem; margin-bottom:5px; }
        .admin-sidebar .sidebar-header p { font-size:0.8rem; color: var(--sidebar-link-color); }

        .admin-sidebar nav ul { list-style: none; }
        .admin-sidebar nav ul li a {
            display: flex; align-items: center; gap: 12px; padding: 12px 20px;
            color: var(--sidebar-link-color); text-decoration: none; font-size: 0.95rem;
            border-left: 3px solid transparent; transition: all 0.2s ease;
        }
        .admin-sidebar nav ul li a .material-symbols-outlined { font-size: 1.4em; }
        .admin-sidebar nav ul li a:hover {
            background-color: var(--sidebar-link-hover-bg); color: var(--sidebar-link-active-color);
        }
        .admin-sidebar nav ul li a.active {
            background-color: var(--primary-color); color: var(--sidebar-link-active-color);
            border-left-color: var(--light-color); font-weight: 500;
        }

        .admin-main-content { flex-grow: 1; overflow-y: auto; background-color: #f4f6f9; }

        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header .container { max-width: 1100px; margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px; }
        .page-header h1 { font-size: 1.7rem; margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }

        /* Container for form or table */
        .admin-main-content .container { max-width: 1100px; /* Wider for overview table */ margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px; }
        .send-message-container { max-width: 900px; } /* Narrower for form */

        .message-output { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .db-error-message { background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        .success-message { background-color: rgba(76,175,80,0.1); color: var(--success-color); border-left-color: var(--success-color); }

        .content-panel { background-color: var(--white); padding: 2rem 2.5rem; border-radius: 10px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); margin-bottom: 2rem; }
        .panel-title { font-size: 1.4rem; color: var(--dark-color); margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom:1px solid var(--light-gray); }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #333; }
        .form-group input[type="text"],
        .form-group textarea,
        .form-group select {
            width: 100%; padding: 0.8rem 1rem; border: 1px solid #ccc;
            border-radius: 6px; font-family: inherit; font-size: 0.95rem;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline:none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb),0.25);
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
        .btn-submit .material-symbols-outlined { font-size: 1.3em; }

        #specific_user_select_group { display: none; }

        /* Sent Messages Table Styles */
        .sent-messages-table {
            width: 100%; border-collapse: collapse; margin-top: 1rem;
            background-color: var(--white); box-shadow: var(--shadow);
            border-radius: 8px; overflow: hidden; border: 1px solid var(--light-gray);
        }
        .sent-messages-table th, .sent-messages-table td {
            padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--light-gray);
        }
        .sent-messages-table thead th {
            background-color: #f8f9fa; /* Lighter than --light-gray for header cells */
            font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: var(--gray-color);
            position: sticky; top: 0; /* If table is scrollable within a container */
        }
        .sent-messages-table tbody tr:last-child td { border-bottom: none; }
        .sent-messages-table tbody tr:hover { background-color: #fdfdff; } /* Very subtle hover */
        .sent-messages-table td { font-size: 0.9rem; color: #333; vertical-align: top; }
        .sent-messages-table .title-cell.is-urgent strong { color: var(--danger-color); }
        .sent-messages-table .title-cell.is-urgent strong::before { content: "❗"; margin-right: 6px; }
        .sent-messages-table .message-content-snippet {
            font-size: 0.85rem; color: #555; margin-top: 4px; display: block;
            max-width: 400px; /* Limit width of snippet if needed */
            white-space: normal; /* Allow wrapping */
        }
        .sent-messages-table .date-cell { font-size: 0.85rem; color: var(--gray-color); white-space:nowrap; }
        .sent-messages-table .status-cell { font-weight: 500; white-space:nowrap;}
        .sent-messages-table .recipient-cell { font-size: 0.85rem; color: #444; }

        @media (max-width: 992px) { /* Adjust breakpoint for sidebar changes */
            .admin-sidebar { width: 220px; }
            .admin-main-content .container, .page-header .container { max-width: 95%; padding-left:15px; padding-right:15px; }
        }
        @media (max-width: 768px) {
            .admin-dashboard-container { flex-direction: column; }
            .admin-sidebar { width: 100%; height: auto; padding-bottom:0; }
            .admin-sidebar nav ul { display: flex; overflow-x: auto; padding: 5px 10px; }
            .admin-sidebar nav ul li a { padding: 10px 15px; border-left:none; border-bottom:3px solid transparent;}
            .admin-sidebar nav ul li a.active { border-bottom-color: var(--light-color); border-left-color:transparent; }
            .admin-sidebar .sidebar-header { display:none; } /* Hide header on mobile to save space */

            .content-panel { padding: 1.5rem; }
            .panel-title { font-size: 1.25rem; }
            .sent-messages-table { display: block; overflow-x: auto; } /* Allow horizontal scroll for table */
            .sent-messages-table th, .sent-messages-table td { white-space: nowrap; } /* Prevent text wrapping in cells on small screens if table scrolls */
            .sent-messages-table .message-content-snippet { white-space: normal; max-width: 250px; }
        }
    </style>
</head>
<body>
    <?php
        $headerPath = "../components/header-admin.php";
        if (file_exists($headerPath)) {
            require_once $headerPath;
        } else {
            echo "<!-- Admin header file not found: " . htmlspecialchars($headerPath) . " -->";
        }
    ?>

    <div class="admin-dashboard-container">
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h3>Admin Panel</h3>
                <p>Messaging Module</p>
            </div>
            <nav>
                <ul>
                    <li>
                        <a href="admin-messages.php?view=overview" class="<?php echo ($currentView === 'overview' ? 'active' : ''); ?>">
                            <span class="material-symbols-outlined">list_alt</span> Sent Messages
                        </a>
                    </li>
                    <li>
                        <a href="admin-messages.php?view=send" class="<?php echo ($currentView === 'send' ? 'active' : ''); ?>">
                            <span class="material-symbols-outlined">send</span> Send New Message
                        </a>
                    </li>
                    <!-- Add more admin links here if needed -->
                </ul>
            </nav>
        </aside>

        <main class="admin-main-content">
            <div class="page-header">
                <div class="container">
                    <h1><?php echo ($view === 'send' ? 'Send New Announcement' : 'Sent Messages Overview'); ?></h1>
                    <p class="sub-heading">
                        <?php echo ($view === 'send' ? 'Compose and send messages to users or user groups.' : 'Review messages you\'ve sent and their read status.'); ?>
                    </p>
                </div>
            </div>

            <div class="container <?php echo ($view === 'send' ? 'send-message-container' : ''); ?>">
                <?php if ($dbErrorMessage): ?>
                    <div class="message-output db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbErrorMessage); ?></div>
                <?php endif; ?>
                <?php if ($successMessage): ?>
                    <div class="message-output success-message" role="alert"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['message_sent']) && $_GET['message_sent'] == '1' && !$successMessage && !$dbErrorMessage): ?>
                    <div class="message-output success-message" role="alert"><i class="fas fa-check-circle"></i> Message was sent successfully!</div>
                <?php endif; ?>


                <?php if ($view === 'send'): ?>
                <section class="content-panel">
                    <h2 class="panel-title">Compose New Announcement</h2>
                    <form action="admin-messages.php?view=send" method="POST" id="sendMessageForm">
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
                                <?php else: ?>
                                    <option value="" disabled>No other active users found to select</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_urgent" value="1" <?php echo isset($_POST['is_urgent']) ? 'checked' : ''; ?>>
                                Mark as Urgent
                            </label>
                        </div>

                        <div class="form-actions" style="text-align:left;">
                            <button type="submit" class="btn-submit">
                                <span class="material-symbols-outlined">send</span> Send Announcement
                            </button>
                        </div>
                    </form>
                </section>
                <?php elseif ($view === 'overview'): ?>
                <section class="content-panel">
                    <h2 class="panel-title">Sent Messages Log</h2>
                    <?php if (empty($sentMessagesData) && !$dbErrorMessage): ?>
                        <p>You have not sent any messages yet.</p>
                    <?php elseif (!empty($sentMessagesData)): ?>
                        <div style="overflow-x: auto;"> <!-- Wrapper for table responsiveness -->
                            <table class="sent-messages-table">
                                <thead>
                                    <tr>
                                        <th>Message</th>
                                        <th>Sent To</th>
                                        <th>Date Sent</th>
                                        <th>Read Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sentMessagesData as $msg): ?>
                                    <tr class="<?php echo $msg['is_urgent'] ? 'is-urgent-row' : ''; ?>">
                                        <td class="title-cell <?php echo $msg['is_urgent'] ? 'is-urgent' : ''; ?>">
                                            <strong><?php echo htmlspecialchars($msg['title']); ?></strong>
                                            <span class="message-content-snippet"><?php echo htmlspecialchars($msg['content_snippet']); ?></span>
                                        </td>
                                        <td class="recipient-cell"><?php echo htmlspecialchars($msg['recipient_info']); ?></td>
                                        <td class="date-cell"><?php echo htmlspecialchars($msg['created_at']); ?></td>
                                        <td class="status-cell"><?php echo htmlspecialchars($msg['read_status']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
            </div> <!-- /.container -->
        </main> <!-- /.admin-main-content -->
    </div> <!-- /.admin-dashboard-container -->

    <?php
        $footerPath = "../components/footer-admin.php";
        if (file_exists($footerPath)) {
            require_once $footerPath;
        } else {
            echo "<!-- Admin footer file not found: " . htmlspecialchars($footerPath) . " -->";
        }
    ?>

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
                    if (recipientTypeSelect.value !== 'specific_user') {
                        specificUserSelect.value = '';
                    }
                }
            }
        }
        // Call on page load if on 'send' view and form might be pre-filled
        <?php if ($view === 'send'): ?>
        document.addEventListener('DOMContentLoaded', toggleSpecificUserSelect);
        <?php endif; ?>
    </script>

</body>
</html>