<?php
// --- PHP KÓD ZOSTÁVA ROVNAKÝ AKO V PRECHÁDZAJÚCEJ ODPOVEDI ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
$readByUserList = [];
$notReadByUserList = [];

$messageIdToEdit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$messageIdToEdit) {
    $_SESSION['message_operation_status'] = ['type' => 'error', 'text' => 'Invalid or missing message ID for editing.'];
    header("location: admin-messages.php?section=history");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_message') {
    if (!isset($pdo)) {
        $_SESSION['message_operation_status'] = ['type' => 'error', 'text' => 'Database connection not available.'];
        header("location: admin-message-edit.php?id=" . $messageIdToEdit);
        exit;
    }

    $title = trim(filter_input(INPUT_POST, 'message_title', FILTER_SANITIZE_SPECIAL_CHARS));
    $content = trim(filter_input(INPUT_POST, 'message_content', FILTER_SANITIZE_SPECIAL_CHARS));
    $isUrgent = filter_input(INPUT_POST, 'is_urgent', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0, 'max_range' => 1]]);
    $isActive = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0, 'max_range' => 1]]);
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
            $sqlUpdate = "UPDATE messages SET
                            title = :title,
                            content = :content,
                            is_urgent = :isUrgent,
                            is_active = :isActive,
                            expires_at = :expiresAt,
                            updated_at = NOW()
                          WHERE messageID = :messageID AND is_system_message = 0";

            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':title', $title, PDO::PARAM_STR);
            $stmtUpdate->bindParam(':content', $content, PDO::PARAM_STR);
            $stmtUpdate->bindParam(':isUrgent', $isUrgent, PDO::PARAM_INT);
            $stmtUpdate->bindParam(':isActive', $isActive, PDO::PARAM_INT);
            $stmtUpdate->bindParam(':expiresAt', $expiresAt, $expiresAt === NULL ? PDO::PARAM_NULL : PDO::PARAM_STR);
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
}

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

        $allSystemUsersStmt = $pdo->query("SELECT userID, firstName, lastName, username, roleID FROM users");
        $allSystemUsersLookup = [];
        foreach($allSystemUsersStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $allSystemUsersLookup[$u['userID']] = $u;
        }
        $potentialRecipientIDs = [];
        if ($messageToEdit['recipientID']) {
            if (isset($allSystemUsersLookup[$messageToEdit['recipientID']])) {
                $potentialRecipientIDs[] = $messageToEdit['recipientID'];
            }
        } elseif ($messageToEdit['recipientRole']) {
            foreach ($allSystemUsersLookup as $userID => $userDetails) {
                if ($messageToEdit['recipientRole'] === 'everyone' || $userDetails['roleID'] === $messageToEdit['recipientRole']) {
                    $potentialRecipientIDs[] = $userID;
                }
            }
        }
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
        $dbErrorMessage = "Database Error loading message details: " . $e->getMessage();
        error_log("Admin Edit Message - DB Load Error for ID $messageIdToEdit: " . $e->getMessage());
        $messageToEdit = null;
    }
} elseif (!isset($pdo)) {
     $dbErrorMessage = "Database connection not available.";
}

$isUrgentChecked = false;
$isActiveChecked = false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_message') {
    $isUrgentChecked = isset($_POST['is_urgent']) && $_POST['is_urgent'] == '1';
    $isActiveChecked = isset($_POST['is_active']) && $_POST['is_active'] == '1';
} elseif ($messageToEdit) {
    $isUrgentChecked = (bool)$messageToEdit['is_urgent'];
    $isActiveChecked = (bool)$messageToEdit['is_active'];
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
            --success-color: #4CAF50; --danger-color: #F44336; --warning-color: #FF9800;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --present-color-val: 67, 170, 139; --neutral-color-val: 173, 181, 189;
            --danger-color-val: 220, 53, 69; /* Pre urgent - zosúladené s Bootstrap danger */
            --present-color: rgb(var(--present-color-val)); --neutral-color: rgb(var(--neutral-color-val));
            /* --danger-color je už definované, použijeme ho pre urgent-toggle */
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px; } /* Predpokladáme fixnú hlavičku 80px */
        .container { max-width: 900px; margin: 0 auto; padding: 0 20px; }

        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header .container {max-width: 900px;}
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
        .form-control-static { padding-top: 0.8rem; font-size: 0.95rem; color: var(--gray-color); }
        .form-group small { font-size: 0.85rem; color: var(--gray-color); display: block; margin-top: 0.3rem; }


        .form-group-toggle {
            display: flex;
            flex-wrap: wrap; /* Umožní zalomenie na menších obrazovkách */
            align-items: center;
            gap: 10px;
            margin-bottom: 1.5rem;
        }
        .form-group-toggle label {
            margin-bottom: 0;
            font-weight: 600;
            font-size: 0.9rem;
            color: #333;
            flex-shrink: 0;
            margin-right: 5px;
        }
        .form-group-toggle .btn-visual-toggle {
            flex-shrink: 0; /* Toggle button sa nebude zmenšovať */
        }
        .form-group-toggle small {
            font-size: 0.8rem;
            color: var(--gray-color);
            flex-basis: 100%; /* Na najmenších obrazovkách zaberie plnú šírku pod tlačidlom */
            margin-top: 5px;
            padding-left: 0; /* Ak chceme, aby sa zarovnala s labelom hore */
        }
        @media (min-width: 480px) { /* Pre šírky väčšie ako ~480px */
            .form-group-toggle small {
                flex-basis: auto; /* Vrátime späť automatickú šírku */
                margin-left: 10px; /* Vrátime margin, ak je vedľa tlačidla */
                margin-top: 0;
            }
        }


        .btn-visual-toggle {
            position: relative; display: inline-flex; align-items: center;
            width: 110px; height: 30px; border-radius: 15px; border: none; cursor: pointer;
            padding: 0; overflow: hidden; transition: all 0.3s ease;
            background-color: rgba(var(--neutral-color-val), 0.2); font-size: 0.75rem;
        }
        .btn-visual-toggle.is-active {
            background-color: rgba(var(--present-color-val), 0.2);
        }
        .btn-visual-toggle.is-active .toggle-text {
             color: var(--present-color);
        }
        .btn-visual-toggle.urgent-toggle.is-active {
            background-color: rgba(var(--danger-color-val), 0.15);
        }
        .btn-visual-toggle.urgent-toggle.is-active .toggle-text {
            color: rgb(var(--danger-color-val));
        }
        .btn-visual-toggle .toggle-knob {
            position: absolute; left: 3px; width: 24px; height: 24px;
            border-radius: 50%; background-color: var(--white);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.3s ease; z-index: 2;
        }
        .btn-visual-toggle.is-active .toggle-knob { left: calc(100% - 27px); }
        .btn-visual-toggle .toggle-text {
            position: absolute; width: 100%; text-align: center;
            font-weight: 500; transition: all 0.3s ease; z-index: 1;
            color: var(--dark-color);
            line-height: 30px;
        }

        .btn-submit, .btn-cancel {
            padding: 0.8rem 1.8rem; border-radius: 6px; text-decoration:none;
            font-weight: 600; cursor:pointer;
            display: inline-flex; align-items: center; gap: 0.6rem; font-size: 1rem;
        }
        .btn-submit { background-color: var(--primary-color); color: var(--white); border:none; }
        .btn-submit:hover { background-color: var(--primary-dark); }
        .btn-cancel { background-color: var(--gray-color); color: var(--white); border:none; margin-left:10px;}
        .btn-cancel:hover { background-color: #5a6268; }

       .read-status-container { margin-top: 2.5rem; padding-top: 2rem; border-top: 1px solid var(--light-gray); }
       .read-status-container h3 { font-size: 1.3rem; margin-bottom: 1.5rem; color: var(--dark-color); text-align: center; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
       .read-status-container h3 .material-symbols-outlined { font-size: 1.3em; vertical-align: middle; }
       .read-status-columns { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
       @media (min-width: 600px) { .read-status-columns { grid-template-columns: repeat(2, 1fr); gap: 2rem; }  }
       .read-status-column { background-color: var(--white); padding: 1.5rem; border-radius: 8px; border: 1px solid var(--light-gray); box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
       .read-status-column h4 { font-size: 1.05rem; color: var(--primary-color); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--light-gray); display: flex; align-items: center; gap: 0.5rem; }
       .read-status-column h4 .material-symbols-outlined { font-size: 1.3em; }
       .read-status-column ul { list-style: none; padding: 0; margin: 0; max-height: 220px; overflow-y: auto; }
       .read-status-column ul::-webkit-scrollbar { width: 6px; }
       .read-status-column ul::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px;}
       .read-status-column ul::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px;}
       .read-status-column ul::-webkit-scrollbar-thumb:hover { background: #aaa; }
       .read-status-column ul li { padding: 0.6rem 0.5rem; border-bottom: 1px solid var(--light-gray); font-size: 0.9rem; color: var(--dark-color); display: flex; align-items: center; gap: 0.6rem; transition: background-color 0.2s ease; }
       .read-status-column ul li:last-child { border-bottom: none; }
       .read-status-column ul li:hover { background-color: #f0f4ff; }
       .read-status-column ul li .material-symbols-outlined { font-size: 1.2em; color: var(--gray-color); flex-shrink: 0; }
       .read-status-column p { font-size: 0.9rem; color: var(--gray-color); padding: 1rem 0.5rem; text-align: center; border-radius: 4px; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
       .read-status-column p .material-symbols-outlined { font-size: 1.8rem; color: var(--gray-color); }

       .form-actions{
           display:flex;
           align-items:center;
       }
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
                            <label for="message_title_edit">Title <span style="color:red;">*</span></label>
                            <input type="text" id="message_title_edit" name="message_title"
                                   value="<?php echo htmlspecialchars(isset($_POST['message_title']) ? $_POST['message_title'] : $messageToEdit['title']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="message_content_edit">Content <span style="color:red;">*</span></label>
                            <textarea id="message_content_edit" name="message_content" rows="8" required><?php echo htmlspecialchars(isset($_POST['message_content']) ? $_POST['message_content'] : $messageToEdit['content']); ?></textarea>
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
                            <label for="expires_at_edit">Expires On (Optional)</label>
                            <?php
                            $expiresDateValue = '';
                            if (isset($_POST['expires_at'])) { $expiresDateValue = $_POST['expires_at']; }
                            elseif ($messageToEdit['expires_at']) { $expiresDateValue = date('Y-m-d', strtotime($messageToEdit['expires_at'])); }
                            ?>
                            <input type="date" id="expires_at_edit" name="expires_at" value="<?php echo htmlspecialchars($expiresDateValue); ?>">
                            <small>Message will become inactive after this date.</small>
                        </div>

                        <div class="form-group form-group-toggle">
                            <label for="is_urgent_toggle">Mark as Urgent:</label>
                            <input type="hidden" name="is_urgent" id="is_urgent_hidden_input" value="<?php echo $isUrgentChecked ? '1' : '0'; ?>">
                            <button type="button" id="is_urgent_toggle"
                                    class="btn-visual-toggle urgent-toggle <?php echo $isUrgentChecked ? 'is-active' : ''; ?>"
                                    title="<?php echo $isUrgentChecked ? 'Unmark as Urgent' : 'Mark as Urgent'; ?>">
                                <span class="toggle-knob"></span>
                                <span class="toggle-text"><?php echo $isUrgentChecked ? 'Urgent!' : 'Normal'; ?></span>
                            </button>
                        </div>

                        <div class="form-group form-group-toggle">
                            <label for="is_active_toggle_msg">Message is Active:</label>
                            <input type="hidden" name="is_active" id="is_active_hidden_input_msg" value="<?php echo $isActiveChecked ? '1' : '0'; ?>">
                            <button type="button" id="is_active_toggle_msg"
                                    class="btn-visual-toggle <?php echo $isActiveChecked ? 'is-active' : ''; ?>"
                                    title="<?php echo $isActiveChecked ? 'Deactivate' : 'Activate'; ?>">
                                <span class="toggle-knob"></span>
                                <span class="toggle-text"><?php echo $isActiveChecked ? 'Active' : 'Inactive'; ?></span>
                            </button>
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
                        <h3><span class="material-symbols-outlined">visibility</span> Message Read Status</h3>
                        <div class="read-status-columns">
                            <div class="read-status-column">
                                <h4><span class="material-symbols-outlined">task_alt</span>Read By (<?php echo count($readByUserList); ?>)</h4>
                                <?php if (!empty($readByUserList)): ?>
                                    <ul><?php foreach($readByUserList as $reader) { echo '<li><span class="material-symbols-outlined">person</span>' . $reader['name'] . '</li>'; } ?></ul>
                                <?php else: ?>
                                    <p><span class="material-symbols-outlined">mark_email_unread</span>No users have read this message yet.</p>
                                <?php endif; ?>
                            </div>
                            <div class="read-status-column">
                                <h4><span class="material-symbols-outlined">mark_email_unread</span>Not Read By (<?php echo count($notReadByUserList); ?>)</h4>
                                 <?php if (!empty($notReadByUserList)): ?>
                                    <ul><?php foreach($notReadByUserList as $nonReader) { echo '<li><span class="material-symbols-outlined">person_off</span>' . $nonReader['name'] . '</li>'; } ?></ul>
                                <?php else: ?>
                                    <p><span class="material-symbols-outlined">checklist</span>All targeted users have read this message.</p>
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
    document.addEventListener('DOMContentLoaded', function() {
        const isUrgentToggleBtn = document.getElementById('is_urgent_toggle');
        const isUrgentHiddenInput = document.getElementById('is_urgent_hidden_input');

        if (isUrgentToggleBtn && isUrgentHiddenInput) {
            isUrgentToggleBtn.addEventListener('click', function() {
                const currentIsActive = this.classList.contains('is-active');
                const newIsActiveState = !currentIsActive;

                this.classList.toggle('is-active', newIsActiveState);
                this.querySelector('.toggle-text').textContent = newIsActiveState ? 'Urgent!' : 'Normal';
                this.title = newIsActiveState ? 'Click to mark as Normal' : 'Click to mark as Urgent';
                isUrgentHiddenInput.value = newIsActiveState ? '1' : '0';
            });
        }

        const isActiveMsgToggleBtn = document.getElementById('is_active_toggle_msg');
        const isActiveMsgHiddenInput = document.getElementById('is_active_hidden_input_msg');

        if (isActiveMsgToggleBtn && isActiveMsgHiddenInput) {
            isActiveMsgToggleBtn.addEventListener('click', function() {
                const currentIsActive = this.classList.contains('is-active');
                const newIsActiveState = !currentIsActive;

                this.classList.toggle('is-active', newIsActiveState);
                this.querySelector('.toggle-text').textContent = newIsActiveState ? 'Active' : 'Inactive';
                this.title = newIsActiveState ? 'Click to Deactivate' : 'Click to Activate';
                isActiveMsgHiddenInput.value = newIsActiveState ? '1' : '0';
            });
        }
    });
    </script>
</body>
</html>