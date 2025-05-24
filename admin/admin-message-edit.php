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

$sessionAdminUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
$pathPrefix = "../"; 

$dbErrorMessage = null;
$messageToEdit = null;
$allUsersForSelect = [];
$readByUserList = [];
$notReadByUserList = [];


$messageIdToEdit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$messageIdToEdit) {
    $_SESSION['message_operation_status'] = ['type' => 'error', 'text' => 'Invalid or missing message ID for editing.'];
    header("location: admin-messages.php?section=history");
    exit;
}

// --- HANDLE FORM SUBMISSION (UPDATE MESSAGE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_message') {
    if (!isset($pdo)) {
        $_SESSION['message_operation_status'] = ['type' => 'error', 'text' => 'Database connection not available.'];
        header("location: admin-message-edit.php?id=" . $messageIdToEdit); 
        exit;
    }

    $title = trim(filter_input(INPUT_POST, 'message_title', FILTER_SANITIZE_SPECIAL_CHARS));
    $content = trim(filter_input(INPUT_POST, 'message_content', FILTER_SANITIZE_SPECIAL_CHARS));
    // Recipient type and specific user ID are NOT editable in this version to simplify read status management.
    // If you want them editable, you need to decide how to handle existing read statuses.
    $isUrgent = isset($_POST['is_urgent']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
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
    }

    if (!$dbErrorMessage) {
        try {
            // For this edit page, we are NOT allowing recipient changes to keep read status logic simpler.
            // If recipient changes were allowed, the logic for user_message_read_status would be complex.
            $sqlUpdate = "UPDATE messages SET 
                            title = :title, 
                            content = :content, 
                            is_urgent = :isUrgent, 
                            is_active = :isActive,
                            expires_at = :expiresAt, 
                            updated_at = NOW()
                          WHERE messageID = :messageID AND is_system_message = 0"; // Ensure only announcements
            
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':title', $title, PDO::PARAM_STR);
            $stmtUpdate->bindParam(':content', $content, PDO::PARAM_STR);
            $stmtUpdate->bindParam(':isUrgent', $isUrgent, PDO::PARAM_INT);
            $stmtUpdate->bindParam(':isActive', $isActive, PDO::PARAM_INT);
            $stmtUpdate->bindParam(':expiresAt', $expiresAt, PDO::PARAM_STR);
            $stmtUpdate->bindParam(':messageID', $messageIdToEdit, PDO::PARAM_INT);

            if ($stmtUpdate->execute()) {
                if ($stmtUpdate->rowCount() > 0) {
                     $_SESSION['message_operation_status'] = ['type' => 'success', 'text' => "Announcement (ID: $messageIdToEdit) updated successfully!"];
                } else {
                     $_SESSION['message_operation_status'] = ['type' => 'info', 'text' => "No changes were made to announcement (ID: $messageIdToEdit)."];
                }
                header("location: admin-messages.php?section=history");
                exit;
            } else {
                $dbErrorMessage = "Failed to update announcement. SQL Error: " . implode(", ", $stmtUpdate->errorInfo());
            }
        } catch (PDOException $e) {
            $dbErrorMessage = "Database error updating announcement: " . $e->getMessage();
            error_log("Admin Edit Message - DB Error (ID: $messageIdToEdit): " . $e->getMessage());
        }
    }
    // If error, fall through to reload form with current POST data and error message
}


// --- LOAD MESSAGE DATA & READ STATUSES FOR EDITING (on GET or if POST had error) ---
if (isset($pdo)) {
    try {
        $stmtLoad = $pdo->prepare("SELECT * FROM messages WHERE messageID = :messageID AND is_system_message = 0");
        $stmtLoad->execute([':messageID' => $messageIdToEdit]);
        $messageToEdit = $stmtLoad->fetch(PDO::FETCH_ASSOC);

        if (!$messageToEdit) {
            $_SESSION['message_operation_status'] = ['type' => 'error', 'text' => "Announcement (ID: $messageIdToEdit) not found or cannot be edited."];
            header("location: admin-messages.php?section=history");
            exit;
        }

        // Fetch all users for efficient lookup
        $allSystemUsersStmt = $pdo->query("SELECT userID, firstName, lastName, username, roleID FROM users");
        $allSystemUsersLookup = [];
        foreach($allSystemUsersStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $allSystemUsersLookup[$u['userID']] = $u;
        }

        // Determine potential recipients for this message
        $potentialRecipientIDs = [];
        if ($messageToEdit['recipientID']) { // Specific user
            if (isset($allSystemUsersLookup[$messageToEdit['recipientID']])) {
                $potentialRecipientIDs[] = $messageToEdit['recipientID'];
            }
        } elseif ($messageToEdit['recipientRole']) { // Role-based or everyone
            foreach ($allSystemUsersLookup as $userID => $userDetails) {
                if ($messageToEdit['recipientRole'] === 'everyone' || $userDetails['roleID'] === $messageToEdit['recipientRole']) {
                    $potentialRecipientIDs[] = $userID;
                }
            }
        }

        // Fetch IDs of users who have read this message
        $stmtReadUsers = $pdo->prepare("SELECT userID FROM user_message_read_status WHERE messageID = :messageID AND is_read = 1");
        $stmtReadUsers->execute([':messageID' => $messageIdToEdit]);
        $actualReaderIDs = $stmtReadUsers->fetchAll(PDO::FETCH_COLUMN);

        foreach ($potentialRecipientIDs as $potentialUserID) {
            $userDisplayInfo = $allSystemUsersLookup[$potentialUserID] ?? null;
            if ($userDisplayInfo) {
                 $displayName = htmlspecialchars($userDisplayInfo['firstName'] . " " . $userDisplayInfo['lastName'] . " (" . $userDisplayInfo['username'] . ")");
                if (in_array($potentialUserID, $actualReaderIDs)) {
                    $readByUserList[] = ['id' => $potentialUserID, 'name' => $displayName];
                } else {
                    $notReadByUserList[] = ['id' => $potentialUserID, 'name' => $displayName];
                }
            }
        }

    } catch (PDOException $e) {
        // Set $dbErrorMessage but don't redirect, show error on this page
        $dbErrorMessage = "Database Error loading message details: " . $e->getMessage();
        error_log("Admin Edit Message - DB Load Error for ID $messageIdToEdit: " . $e->getMessage());
        $messageToEdit = null; // Prevent form rendering if data load failed catastrophically
    }
} elseif (!isset($pdo)) {
     $dbErrorMessage = "Database connection not available.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="<?php echo htmlspecialchars($pathPrefix); ?>imgs/logo.png" type="image/x-icon">
    <title>Edit Announcement - Admin - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4;
            --primary-color-rgb: 67, 97, 238; 
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --danger-color: #F44336;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px; }
        .container { max-width: 900px; margin: 0 auto; padding: 0 20px; }
        
        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; margin: 0; }

        .message-output { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .db-error-message { background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        
        .content-panel { background-color: var(--white); padding: 2rem 2.5rem; border-radius: 10px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); margin-bottom: 2rem; }
        .panel-title { font-size: 1.4rem; color: var(--dark-color); margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom:1px solid var(--light-gray); }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #333; }
        .form-group input[type="text"], .form-group input[type="date"], .form-group textarea, .form-group select {
            width: 100%; padding: 0.8rem 1rem; border: 1px solid #ccc;
            border-radius: 6px; font-family: inherit; font-size: 0.95rem;
        }
        .form-group textarea { min-height: 150px; resize: vertical; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { 
            outline:none; border-color: var(--primary-color); 
            box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb),0.25); 
        }
        .form-group .checkbox-label { display: flex; align-items: center; font-weight: normal; font-size: 0.95rem; }
        .form-group .checkbox-label input[type="checkbox"] { margin-right: 0.75rem; width: auto; transform: scale(1.1);}
        .form-control-static { padding-top: 0.8rem; font-size: 0.95rem; color: var(--gray-color); }

        .btn-submit, .btn-cancel { 
            padding: 0.8rem 1.8rem; border-radius: 6px; text-decoration:none; 
            font-weight: 600; cursor:pointer; 
            display: inline-flex; align-items: center; gap: 0.6rem; font-size: 1rem;
        }
        .btn-submit { background-color: var(--primary-color); color: var(--white); border:none; }
        .btn-submit:hover { background-color: var(--primary-dark); }
        .btn-cancel { background-color: var(--gray-color); color: var(--white); border:none; margin-left:10px;}
        .btn-cancel:hover { background-color: #5a6268; }
        
        .read-status-container { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--light-gray); }
        .read-status-container h3 { font-size: 1.2rem; margin-bottom: 1rem; color: var(--dark-color); }
        .read-status-columns { display: flex; gap: 2rem; }
        .read-status-column { flex: 1; }
        .read-status-column ul { list-style: disc; padding-left: 20px; font-size: 0.9rem; max-height: 200px; overflow-y: auto; border: 1px solid var(--light-gray); padding: 10px; border-radius: 4px; background-color: #f9f9f9;}
        .read-status-column li { margin-bottom: 0.3rem; }

    </style>
</head>
<body>
    <?php require_once $pathPrefix . "components/header-admin.php"; ?>
    <main>
        <div class="page-header">
            <div class="container">
                <h1>Edit Announcement</h1>
            </div>
        </div>
        <div class="container">
            <?php if ($dbErrorMessage && !$messageToEdit): ?>
                <div class="message-output db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbErrorMessage); ?></div>
                <p><a href="admin-messages.php?section=history" class="btn-cancel" style="text-decoration:none;">Back to History</a></p>
            <?php elseif ($messageToEdit): ?>
                <?php if ($dbErrorMessage): ?>
                    <div class="message-output db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbErrorMessage); ?></div>
                <?php endif; ?>

                <section class="content-panel">
                    <h2 class="panel-title">Editing Announcement ID: <?php echo htmlspecialchars($messageToEdit['messageID']); ?></h2>
                    <form action="admin-message-edit.php?id=<?php echo $messageIdToEdit; ?>" method="POST" id="editMessageForm">
                        <input type="hidden" name="action" value="update_message">

                        <div class="form-group">
                            <label for="message_title">Title <span style="color:red;">*</span></label>
                            <input type="text" id="message_title" name="message_title" 
                                   value="<?php echo htmlspecialchars(isset($_POST['message_title']) ? $_POST['message_title'] : $messageToEdit['title']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="message_content">Content <span style="color:red;">*</span></label>
                            <textarea id="message_content" name="message_content" rows="8" required><?php echo htmlspecialchars(isset($_POST['message_content']) ? $_POST['message_content'] : $messageToEdit['content']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Current Recipients</label>
                            <p class="form-control-static">
                                <?php 
                                if ($messageToEdit['recipientID'] && isset($allSystemUsersLookup[$messageToEdit['recipientID']])) {
                                    $recipientUser = $allSystemUsersLookup[$messageToEdit['recipientID']];
                                    echo "Specific User: " . htmlspecialchars($recipientUser['firstName'] . " " . $recipientUser['lastName'] . " (" . $recipientUser['username'] . ")");
                                } elseif ($messageToEdit['recipientRole']) {
                                    echo "Role: " . htmlspecialchars(ucfirst($messageToEdit['recipientRole']));
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </p>
                            <small>Recipient type cannot be changed after sending to maintain consistency of read statuses.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="expires_at">Expires On (Optional)</label>
                            <?php
                            $expiresDateValue = '';
                            if (isset($_POST['expires_at'])) { $expiresDateValue = $_POST['expires_at']; } 
                            elseif ($messageToEdit['expires_at']) { $expiresDateValue = date('Y-m-d', strtotime($messageToEdit['expires_at'])); }
                            ?>
                            <input type="date" id="expires_at" name="expires_at" value="<?php echo htmlspecialchars($expiresDateValue); ?>">
                            <small>Message will become inactive after this date.</small>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_urgent" value="1" 
                                    <?php echo (isset($_POST['is_urgent']) ? 'checked' : ($messageToEdit['is_urgent'] ? 'checked' : '')); ?>>
                                Mark as Urgent
                            </label>
                        </div>
                         <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_active" value="1" 
                                    <?php echo (isset($_POST['is_active']) ? 'checked' : ($messageToEdit['is_active'] ? 'checked' : '')); ?>>
                                Message is Active
                            </label>
                            <small>Uncheck to manually deactivate the message.</small>
                        </div>

                        <div class="form-actions" style="text-align:left;">
                            <button type="submit" class="btn-submit">
                                <span class="material-symbols-outlined">save</span> Update Announcement
                            </button>
                            <a href="admin-messages.php?section=history" class="btn-cancel">Cancel</a>
                        </div>
                    </form>

                    <div class="read-status-container">
                        <h3>Read Status</h3>
                        <div class="read-status-columns">
                            <div class="read-status-column">
                                <h4>Read By (<?php echo count($readByUserList); ?>)</h4>
                                <?php if (!empty($readByUserList)): ?>
                                    <ul><?php foreach($readByUserList as $reader) { echo '<li>' . $reader['name'] . '</li>'; } ?></ul>
                                <?php else: ?>
                                    <p>No users have read this message yet.</p>
                                <?php endif; ?>
                            </div>
                            <div class="read-status-column">
                                <h4>Not Read By (<?php echo count($notReadByUserList); ?>)</h4>
                                 <?php if (!empty($notReadByUserList)): ?>
                                    <ul><?php foreach($notReadByUserList as $nonReader) { echo '<li>' . $nonReader['name'] . '</li>'; } ?></ul>
                                <?php else: ?>
                                    <p>All potential recipients have read this message, or no other users were targeted.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>
    <?php require_once $pathPrefix . "components/footer-admin.php"; ?>
    <script>
        // No specific JS needed for this page other than global scripts in header/footer
        // The toggleSpecificUserSelect is not needed here as recipients are not editable.
    </script>
</body>
</html>