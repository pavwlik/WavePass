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
$sessionAdminUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null; // Admin, který posílá

$dbErrorMessage = null;
$successMessage = null;
$allUsersForSelect = []; // Pro výběr konkrétního příjemce

// --- DATA FETCHING FOR FORM (List of users for specific selection) ---
if (isset($pdo)) {
    try {
        // Načtení všech uživatelů pro dropdown (kromě aktuálního admina, pokud nechceme posílat sami sobě)
        // Můžete upravit, pokud admin může posílat i sám sobě.
        $stmtUsers = $pdo->prepare("SELECT userID, firstName, lastName, username FROM users WHERE userID != :currentAdminID ORDER BY lastName, firstName");
        $stmtUsers->execute([':currentAdminID' => $sessionAdminUserId]);
        $allUsersForSelect = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $dbErrorMessage = "Database Error fetching users: " . $e->getMessage();
        error_log("Admin Messages - DB Error fetching users: " . $e->getMessage());
    }
} else {
    $dbErrorMessage = "Database connection not available.";
}


// --- HANDLE FORM SUBMISSION (SEND MESSAGE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'send_message') {
    if (!isset($pdo)) {
        $dbErrorMessage = "Database connection not available. Cannot send message.";
    } else {
        $title = trim(filter_input(INPUT_POST, 'message_title', FILTER_SANITIZE_SPECIAL_CHARS));
        $content = trim(filter_input(INPUT_POST, 'message_content', FILTER_SANITIZE_SPECIAL_CHARS));
        $recipientType = filter_input(INPUT_POST, 'recipient_type', FILTER_SANITIZE_SPECIAL_CHARS); // 'all_users', 'specific_user', 'all_employees', 'all_admins'
        $specificUserID = filter_input(INPUT_POST, 'specific_user_id', FILTER_VALIDATE_INT);
        $messageType = 'announcement'; // Pro adminem poslané zprávy, může být i 'info' nebo 'warning'
        $isUrgent = isset($_POST['is_urgent']) ? 1 : 0;

        if (empty($title) || empty($content)) {
            $dbErrorMessage = "Message title and content are required.";
        } elseif (empty($recipientType)) {
            $dbErrorMessage = "Please select a recipient type.";
        } elseif ($recipientType === 'specific_user' && (empty($specificUserID) || $specificUserID === false)) {
            $dbErrorMessage = "Please select a valid specific user if 'Specific User' is chosen.";
        } else {
            try {
                // Vkládání zprávy do tabulky 'messages'
                // recipientID a recipientRole budou nastaveny podle výběru
                
                $sql = "INSERT INTO messages (senderID, recipientID, recipientRole, title, content, message_type, is_urgent, is_system_message, expires_at, created_at, updated_at) 
                        VALUES (:senderID, :recipientID, :recipientRole, :title, :content, :messageType, :isUrgent, 0, NULL, NOW(), NOW())";
                $stmt = $pdo->prepare($sql);

                $paramSenderID = $sessionAdminUserId;
                $paramRecipientID = NULL;      // Bude NULL, pokud je recipientRole nastaveno
                $paramRecipientRole = NULL;    // Bude NULL, pokud je recipientID nastaveno

                if ($recipientType === 'all_users') {
                    $paramRecipientRole = 'everyone'; // Nebo jiná hodnota ENUMu, která značí všechny
                } elseif ($recipientType === 'all_employees') {
                    $paramRecipientRole = 'employee';
                } elseif ($recipientType === 'all_admins') {
                    $paramRecipientRole = 'admin';
                } elseif ($recipientType === 'specific_user' && $specificUserID) {
                    $paramRecipientID = $specificUserID;
                } else {
                    $dbErrorMessage = "Invalid recipient selection."; // Mělo by být odchyceno dříve
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
                        $successMessage = "Announcement sent successfully!";
                        // Po úspěšném odeslání můžeme vyčistit formulář
                        $_POST = array(); 
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

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../imgs/logo.png" type="image/x-icon">
    <title>Send Announcements - Admin - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Základní styly (podobné jako u admin-manage-employees.php) */
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --primary-color-rgb: 67, 97, 238; /* Pro box-shadow s alpha */
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --warning-color: #FF9800; --danger-color: #F44336;
            --info-color: #2196F3; 
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px;; /* Výška headeru */ }
        .container, .page-header .container { max-width: 900px; /* Mírně užší pro formulář */ margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px; }
        
        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }

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
        .form-group input:focus, 
        .form-group textarea:focus,
        .form-group select:focus { 
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
        .btn-submit .material-symbols-outlined { font-size: 1.3em; }

        #specific_user_select_group { display: none; /* Skryto defaultně */ }
        
        @media (max-width: 768px) {
            .container, .page-header .container { padding-left: 15px; padding-right: 15px; }
            .content-panel { padding: 1.5rem; }
            .panel-title { font-size: 1.25rem; }
        }
    </style>
</head>
<body>
    <?php 
        // Cesta k admin headeru, předpokládá, že admin-messages.php je ve složce 'admin'
        $headerPath = "../components/header-admin.php"; 
        if (file_exists($headerPath)) {
            require_once $headerPath;
        } else {
            echo "<!-- Admin header file not found at " . htmlspecialchars($headerPath) . " -->";
            // Zde by mohl být fallback na obecný header nebo chybová zpráva
        }
    ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>Send Announcements</h1>
                <p class="sub-heading">Compose and send messages to users or user groups.</p>
            </div>
        </div>

        <div class="container">
            <?php if ($dbErrorMessage): ?>
                <div class="message-output db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbErrorMessage); ?></div>
            <?php endif; ?>
            <?php if ($successMessage): ?>
                <div class="message-output success-message" role="alert"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <section class="content-panel">
                <h2 class="panel-title">Compose New Announcement</h2>
                <form action="admin-messages.php" method="POST" id="sendMessageForm">
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
                                <option value="" disabled>No other users found</option>
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
        </div>
    </main>

    <?php 
        $footerPath = "../components/footer-admin.php"; 
        if (file_exists($footerPath)) {
            require_once $footerPath;
        } else {
            echo "<!-- Admin footer file not found at " . htmlspecialchars($footerPath) . " -->";
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
                    // Reset selection if not 'specific_user' to avoid sending to a previously selected user
                    if (recipientTypeSelect.value !== 'specific_user') { 
                        specificUserSelect.value = ''; 
                    }
                }
            }
        }
        // Call on page load in case of pre-filled form (e.g., after error or successful send without redirect)
        document.addEventListener('DOMContentLoaded', toggleSpecificUserSelect);
    </script>

</body>
</html>