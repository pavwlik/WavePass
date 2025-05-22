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
$messagesWithComments = [];
$usersForAdminForm = []; // Pro admin formulář na výběr uživatele

// Zpracování odeslání nového komentáře
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_comment' && $sessionUserId) {
    $commentMessageID = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
    $commentText = trim(filter_input(INPUT_POST, 'comment_text', FILTER_SANITIZE_SPECIAL_CHARS));

    if ($commentMessageID && !empty($commentText)) {
        try {
            $sqlComment = "INSERT INTO message_comments (messageID, userID, comment_text) VALUES (:messageID, :userID, :comment_text)";
            $stmtComment = $pdo->prepare($sqlComment);
            $stmtComment->bindParam(':messageID', $commentMessageID, PDO::PARAM_INT);
            $stmtComment->bindParam(':userID', $sessionUserId, PDO::PARAM_INT);
            $stmtComment->bindParam(':comment_text', $commentText, PDO::PARAM_STR);
            if ($stmtComment->execute()) {
                $successMessage = "Comment added successfully.";
                // header("Location: messages.php?message_focus=" . $commentMessageID); // Přesměrování pro zobrazení nového komentáře
                // exit;
                // Prozatím nebudeme přesměrovávat, aby se zobrazil success message, ale AJAX by byl lepší.
            } else {
                $dbErrorMessage = "Failed to add comment.";
            }
        } catch (PDOException $e) {
            $dbErrorMessage = "Database error adding comment: " . $e->getMessage();
        }
    } else {
        $dbErrorMessage = "Invalid data for adding comment.";
    }
}

// Zpracování odeslání nové zprávy (pouze pro admina)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'send_message' && $sessionRole == 'admin') {
    $msgTitle = trim(filter_input(INPUT_POST, 'message_title', FILTER_SANITIZE_SPECIAL_CHARS));
    $msgContent = trim(filter_input(INPUT_POST, 'message_content', FILTER_SANITIZE_SPECIAL_CHARS)); // Můžete povolit omezené HTML pokud použijete jiný sanitizér
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

        if (!$dbErrorMessage) { // Pokračovat pouze pokud není chyba v cílení
            try {
                $sqlNewMsg = "INSERT INTO messages (senderID, recipientID, recipientRole, title, content, message_type, is_urgent, is_system_message)
                              VALUES (:senderID, :recipientID, :recipientRole, :title, :content, :message_type, :is_urgent, 0)";
                $stmtNewMsg = $pdo->prepare($sqlNewMsg);
                $stmtNewMsg->bindParam(':senderID', $sessionUserId, PDO::PARAM_INT);
                $stmtNewMsg->bindParam(':recipientID', $recipientID, PDO::PARAM_INT);
                $stmtNewMsg->bindParam(':recipientRole', $recipientRole, PDO::PARAM_STR);
                $stmtNewMsg->bindParam(':title', $msgTitle, PDO::PARAM_STR);
                $stmtNewMsg->bindParam(':content', $msgContent, PDO::PARAM_STR);
                $stmtNewMsg->bindParam(':message_type', $msgType, PDO::PARAM_STR);
                $stmtNewMsg->bindParam(':is_urgent', $msgIsUrgent, PDO::PARAM_INT);
                
                if ($stmtNewMsg->execute()) {
                    $successMessage = "Message sent successfully!";
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


// Výběr filtru zobrazení
$currentFilter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // 'all', 'for_you', 'for_everyone'

if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        $sqlWhereClauses = ["m.is_active = TRUE", "(m.expires_at IS NULL OR m.expires_at > NOW())"];
        $params = [':currentUserID' => $sessionUserId, ':currentUserRole' => $sessionRole];

        if ($currentFilter == 'for_you') {
            $sqlWhereClauses[] = "m.recipientID = :currentUserID";
        } elseif ($currentFilter == 'for_everyone') {
            $sqlWhereClauses[] = "m.recipientRole = 'everyone'";
        } else { // 'all' - výchozí
            $sqlWhereClauses[] = "(m.recipientID = :currentUserID OR m.recipientRole = :currentUserRole OR m.recipientRole = 'everyone')";
        }
        
        $sql = "SELECT
                    m.messageID, m.title, m.content, m.message_type, m.is_urgent, m.created_at,
                    m.senderID, u_sender.firstName AS sender_firstName, u_sender.lastName AS sender_lastName,
                    m.recipientID, m.recipientRole,
                    COALESCE(umrs.is_read, 0) AS is_read_by_user
                FROM messages m
                LEFT JOIN users u_sender ON m.senderID = u_sender.userID
                LEFT JOIN user_message_read_status umrs ON m.messageID = umrs.messageID AND umrs.userID = :currentUserID
                WHERE " . implode(" AND ", $sqlWhereClauses) . "
                ORDER BY m.is_urgent DESC, m.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $fetchedMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $unreadMessageIDsToMark = [];

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
            } else { // Systémové nebo obecné admin zprávy
                $messageData['sender_name'] = 'WavePass System';
            }

            // Načtení komentářů pro tuto zprávu
            $stmtComments = $pdo->prepare("SELECT mc.comment_text, mc.created_at AS comment_created_at, u_commenter.firstName AS commenter_firstName, u_commenter.lastName AS commenter_lastName
                                           FROM message_comments mc
                                           JOIN users u_commenter ON mc.userID = u_commenter.userID
                                           WHERE mc.messageID = :messageID
                                           ORDER BY mc.created_at ASC");
            $stmtComments->bindParam(':messageID', $msg['messageID'], PDO::PARAM_INT);
            $stmtComments->execute();
            $messageData['comments'] = $stmtComments->fetchAll(PDO::FETCH_ASSOC);

            $messagesWithComments[] = $messageData;

            if (!$msg['is_read_by_user']) {
                $unreadMessageIDsToMark[] = $msg['messageID'];
            }
        }

        // Označení zobrazených nepřečtených zpráv jako přečtené
        if (!empty($unreadMessageIDsToMark) && $sessionUserId) {
            // ... (kód pro označení jako přečtené zůstává stejný jako v předchozí verzi) ...
            $markReadSql = "INSERT INTO user_message_read_status (userID, messageID, is_read, read_at) VALUES (:userID, :messageID, 1, NOW())
                            ON DUPLICATE KEY UPDATE is_read = 1, read_at = NOW()";
            $stmtMarkRead = $pdo->prepare($markReadSql);
            $stmtMarkRead->bindParam(':userID', $sessionUserId, PDO::PARAM_INT);
            
            foreach ($unreadMessageIDsToMark as $messageIdToMark) {
                $stmtMarkRead->bindParam(':messageID', $messageIdToMark, PDO::PARAM_INT);
                $stmtMarkRead->execute();
            }
        }
        
        // Pokud je admin, načti seznam uživatelů pro formulář
        if ($sessionRole == 'admin') {
            $stmtUsers = $pdo->query("SELECT userID, firstName, lastName, username FROM users ORDER BY lastName, firstName");
            $usersForAdminForm = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
        }


    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error: " . $e->getMessage();
    } catch (Exception $e) {
        $dbErrorMessage = "An application error occurred: " . $e->getMessage();
    }
} else {
    // ... (chybové zprávy pro DB a session zůstávají stejné) ...
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
        /* === Základní styly (převzaté a upravené) === */
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --warning-color: #FF9800; --danger-color: #F44336;
            --info-color: #2196F3; --system-color: #757575;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color);
            background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh;
        }
        main { flex-grow: 1; padding-top: 80px; /* Prostor pro fixní hlavičku */ }
        .container-messages { display: flex; max-width: 1400px; margin: 0 auto; padding: 0 20px; gap: 1.5rem; }
        .messages-sidebar {
            flex: 0 0 280px; /* Pevná šířka levého panelu */
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
            height: fit-content; /* Aby se přizpůsobil obsahu */
            margin-top: 1.5rem; /* Odsazení shora jako u obsahu */
        }

        /*header */
        /* Basic Header Styling (ensure it's fixed or sticky as desired) */
header {
    background-color: var(--white); /* Or your header background */
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    position: fixed; /* This makes the header stay at the top */
    width: 100%;
    top: 0;
    z-index: 1000; /* Ensure it's above other content, but below active mobile menu if menu is separate */
}

.navbar { /* Container for logo, nav-links, hamburger */
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 80px; /* Adjust to your header height */
    /* Assuming .container class inside header handles width/padding */
}

/* Desktop Navigation Links */
.nav-links {
    display: flex; /* Visible on desktop */
    list-style: none;
    align-items: center;
    /* Add your existing .nav-links styling here (gap, link colors, etc.) */
}

/* Hamburger Icon */
.hamburger {
    display: none; /* Hidden by default on desktop */
    cursor: pointer;
    /* Add your existing .hamburger styling (size, bar styles, active state for 'X') */
    /* Example for basic structure */
    flex-direction: column;
    justify-content: space-around; /* Or space-between */
    width: 30px; /* Adjust */
    height: 24px; /* Adjust */
    z-index: 1001; /* Needs to be above mobile menu if menu is also fixed */
}

.hamburger span {
    display: block;
    width: 100%;
    height: 3px;
    background-color: var(--dark-color); /* Or your hamburger bar color */
    border-radius: 3px;
    transition: all 0.3s linear;
}

/* Mobile Menu Container - CRITICAL for fixing the issue */
.mobile-menu {
    position: fixed; /* Makes it an overlay, removed from normal document flow */
    top: 0;
    left: 0; /* Start from left edge for translateX */
    width: 100%; /* Full width overlay */
    height: 100vh; /* Full height overlay */
    background-color: var(--white); /* Or your mobile menu background */
    z-index: 1000; /* High z-index to be on top of page content */
    
    display: flex;
    flex-direction: column;
    /* Adjust alignment as per your design (e.g., for centered links) */
    align-items: center; 
    justify-content: center; /* Or flex-start if you have a close button at top */
    padding: 2rem; /* Example padding */

    transform: translateX(-100%); /* Hides the menu off-screen to the left by default */
    transition: transform 0.3s ease-in-out; /* Smooth transition for sliding in/out */
    overflow-y: auto; /* Allow scrolling if mobile menu content is long */
}

.mobile-menu.active {
    transform: translateX(0); /* Slides the menu into view when .active class is added by JS */
}

/* Mobile Menu Links Styling */
.mobile-menu .mobile-links {
    list-style: none;
    padding: 0;
    margin: 0;
    text-align: center; /* Or left, depending on your design */
    /* Add your existing .mobile-links styling (width, etc.) */
}

.mobile-menu .mobile-links li {
    margin-bottom: 1rem; /* Example spacing */
}

.mobile-menu .mobile-links a {
    display: block; /* Make links easy to tap */
    padding: 0.8rem 1rem; /* Example padding */
    text-decoration: none;
    color: var(--dark-color); /* Link color */
    font-size: 1.1rem; /* Example font size */
    /* Add your existing .mobile-links a styling (hover effects, active link style) */
}

/* Close button inside mobile menu (if you have one) */
.mobile-menu .close-btn {
    position: absolute;
    top: 20px; /* Adjust as needed */
    right: 20px; /* Adjust as needed */
    cursor: pointer;
    /* Add styling for your close icon (font size, color) */
}

/* Media Query for Responsive Behavior - CRITICAL */
@media (max-width: 992px) { /* Adjust this breakpoint to match your design */
    .nav-links {
        display: none; /* Hide desktop links on smaller screens */
    }
    .hamburger {
        display: flex; /* Show hamburger icon on smaller screens */
    }
    /* If .mobile-menu is not full width, you might need to adjust its appearance here */
}
        
        .messages-sidebar h3 { font-size: 1.2rem; margin-bottom: 1rem; color: var(--dark-color); padding-bottom: 0.5rem; border-bottom: 1px solid var(--light-gray); }
        .filter-list { list-style: none; padding: 0; }
        .filter-list li a {
            display: flex; align-items: center; gap: 0.7rem;
            padding: 0.8rem 1rem; text-decoration: none;
            color: var(--dark-color); border-radius: 6px;
            transition: var(--transition); font-weight: 500;
        }
        .filter-list li a:hover, .filter-list li a.active-filter {
            background-color: rgba(var(--primary-color-rgb, 67, 97, 238), 0.1); /* Použijte RGB pro alpha */
            color: var(--primary-color);
        }
        .filter-list li a .material-symbols-outlined { font-size: 1.3em; }

        .messages-content { flex-grow: 1; }
        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header .container {max-width: 1400px; margin: 0 auto; padding: 0 20px;} /* Aby page-header kontejner seděl s .container-messages */
        .page-header h1 { font-size: 1.7rem; } .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        
        .db-error-message, .success-message { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .db-error-message { background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        .success-message { background-color: rgba(76,175,80,0.1); color: var(--success-color); border-left-color: var(--success-color); }


        /* === Styly pro zprávy a komentáře (podobné jako v předchozí verzi, mírně upravené) === */
        .messages-list { display: flex; flex-direction: column; gap: 1.5rem; margin-top: 1rem; }
        .message-card {
            background-color: var(--white); border-radius: 8px; box-shadow: var(--shadow);
            border-left: 5px solid var(--info-color); padding: 1.5rem;
        }
        .message-card.type-announcement { border-left-color: var(--primary-color); }
        .message-card.type-warning { border-left-color: var(--warning-color); }
        .message-card.type-system { border-left-color: var(--system-color); }
        .message-card.is-urgent { border-left-color: var(--danger-color); background-color: rgba(244,67,54,0.03); }
        .message-card.is-unread .message-header .message-title::before {
            content: "NEW"; background-color: var(--danger-color); color: var(--white);
            font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 3px;
            margin-right: 0.7rem; vertical-align: middle;
        }
        .message-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.8rem; flex-wrap: wrap; gap: 0.5rem;}
        .message-title { font-size: 1.25rem; font-weight: 600; }
        .message-meta { font-size: 0.8rem; color: var(--gray-color); text-align: right; flex-shrink: 0; }
        .message-meta .target-audience { display: block; font-weight: 500; color: var(--secondary-color); margin-bottom: 0.2rem; }
        .message-meta .sender-name, .message-meta .message-date { display: block; }
        .message-content { font-size: 0.95rem; color: #333; line-height: 1.7; margin-bottom: 1.5rem; }
        .no-messages { text-align: center; padding: 3rem 1rem; background-color: var(--white); border-radius: 8px; box-shadow: var(--shadow); color: var(--gray-color); font-size: 1.1rem; }
        .no-messages .material-symbols-outlined { font-size: 3rem; display: block; margin-bottom: 1rem; color: var(--primary-color); }

        /* Komentáře */
        .comments-section { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--light-gray); }
        .comments-section h4 { font-size: 1rem; margin-bottom: 0.8rem; color: var(--dark-color); }
        .comment {
            background-color: #f9f9f9; padding: 0.8rem 1rem; border-radius: 6px;
            margin-bottom: 0.8rem; border: 1px solid var(--light-gray);
        }
        .comment-meta { font-size: 0.75rem; color: var(--gray-color); margin-bottom: 0.3rem; }
        .comment-meta .commenter-name { font-weight: 600; color: var(--dark-color); }
        .comment-text { font-size: 0.9rem; }
        .no-comments { font-size: 0.85rem; color: var(--gray-color); font-style: italic; }

        .add-comment-form textarea {
            width: 100%; padding: 0.7rem; border: 1px solid var(--light-gray);
            border-radius: 6px; font-family: inherit; font-size: 0.9rem;
            margin-bottom: 0.5rem; min-height: 60px; resize: vertical;
        }
        .add-comment-form .btn-submit-comment { /* Použijte existující .btn styly nebo vlastní */
            background-color: var(--primary-color); color: var(--white);
            border: none; padding: 0.6rem 1.2rem; border-radius: 6px;
            font-weight: 500; cursor: pointer; transition: var(--transition);
        }
        .add-comment-form .btn-submit-comment:hover { background-color: var(--primary-dark); }
        
        /* Formulář pro admina na posílání zpráv */
        .admin-send-message-panel {
            background-color: var(--white); padding: 1.5rem; border-radius: 8px;
            box-shadow: var(--shadow); margin-top: 1.5rem; /* Stejné odsazení jako sidebar */
        }
        .admin-send-message-panel h3 { font-size: 1.2rem; margin-bottom: 1rem; color: var(--dark-color); padding-bottom: 0.5rem; border-bottom: 1px solid var(--light-gray); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.9rem; }
        .form-group input[type="text"], .form-group textarea, .form-group select {
            width: 100%; padding: 0.7rem; border: 1px solid var(--light-gray);
            border-radius: 6px; font-family: inherit; font-size: 0.9rem;
        }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-group input[type="checkbox"] { margin-right: 0.5rem; vertical-align: middle; }
        .form-group .btn-send-message {
             background-color: var(--success-color); color: var(--white);
            border: none; padding: 0.7rem 1.5rem; border-radius: 6px;
            font-weight: 600; cursor: pointer; transition: var(--transition); font-size: 0.95rem;
        }
        .form-group .btn-send-message:hover { opacity:0.9; transform: translateY(-1px); }
        #specificUserSelectContainer { display: none; margin-top: 0.5rem; }

        /* Footer styly (převzít z dashboard.php) */
        footer { background-color: var(--dark-color); color: var(--white); padding: 3rem 0 2rem; margin-top:auto;}
        /* ... zbytek footer stylů ... */
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 2rem; margin-bottom: 2rem; }
        .footer-column h3 { font-size: 1.1rem; margin-bottom: 1rem; position: relative; padding-bottom: 0.5rem; }
        .footer-column h3::after { content: ''; position: absolute; left: 0; bottom: 0; width: 40px; height: 2px; background-color: var(--primary-color); }
        .footer-links { list-style: none; padding:0; } .footer-links li { margin-bottom: 0.6rem; }
        .footer-links a { color: rgba(255, 255, 255, 0.7); text-decoration: none; transition: var(--transition); font-size: 0.9rem; }
        .footer-links a:hover { color: var(--white); }
        .footer-links a i { margin-right: 0.4rem; width: 18px; text-align: center; } 
        .social-links { display: flex; gap: 1rem; margin-top: 1rem; padding:0; }
        .social-links a { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; background-color: rgba(255, 255, 255, 0.1); color: var(--white); border-radius: 50%; font-size: 1rem; transition: var(--transition); }
        .social-links a:hover { background-color: var(--primary-color); }
        .footer-bottom { text-align: center; padding-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.85rem; color: rgba(255, 255, 255, 0.6); }
        .footer-bottom a { color: rgba(255, 255, 255, 0.7); text-decoration: none; } .footer-bottom a:hover { color: var(--primary-color); }

    </style>
</head>
<body>
    <?php require "components/header-employee-panel.php"; ?>

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
                    <li><a href="messages.php?filter=all" class="<?php if ($currentFilter == 'all') echo 'active-filter'; ?>">
                        <span class="material-symbols-outlined">mail</span> All Messages</a>
                    </li>
                    <li><a href="messages.php?filter=for_you" class="<?php if ($currentFilter == 'for_you') echo 'active-filter'; ?>">
                        <span class="material-symbols-outlined">person</span> For You</a>
                    </li>
                    <li><a href="messages.php?filter=for_everyone" class="<?php if ($currentFilter == 'for_everyone') echo 'active-filter'; ?>">
                        <span class="material-symbols-outlined">groups</span> For Everyone</a>
                    </li>
                </ul>

                <?php if ($sessionRole == 'admin'): ?>
                <div class="admin-send-message-panel" style="margin-top: 2rem;">
                    <h3><span class="material-symbols-outlined" style="vertical-align:bottom; margin-right:5px;">send</span> Send New Message</h3>
                    <form action="messages.php" method="POST">
                        <input type="hidden" name="action" value="send_message">
                        <div class="form-group">
                            <label for="message_title">Title:</label>
                            <input type="text" id="message_title" name="message_title" required>
                        </div>
                        <div class="form-group">
                            <label for="message_content">Content:</label>
                            <textarea id="message_content" name="message_content" rows="4" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="message_target">Target:</label>
                            <select id="message_target" name="message_target" required onchange="toggleSpecificUserSelect(this.value)">
                                <option value="everyone">Everyone</option>
                                <option value="all_employees">All Employees</option>
                                <option value="all_admins">All Admins</option>
                                <option value="specific_user">Specific User</option>
                            </select>
                        </div>
                        <div class="form-group" id="specificUserSelectContainer">
                            <label for="message_target_specific_user">Select User:</label>
                            <select id="message_target_specific_user" name="message_target_specific_user">
                                <option value="">-- Select User --</option>
                                <?php foreach ($usersForAdminForm as $user): ?>
                                    <option value="<?php echo $user['userID']; ?>">
                                        <?php echo htmlspecialchars($user['lastName'] . ', ' . $user['firstName'] . ' (' . $user['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="message_type">Message Type:</label>
                            <select id="message_type" name="message_type" required>
                                <option value="info">Info</option>
                                <option value="announcement">Announcement</option>
                                <option value="warning">Warning</option>
                                <option value="system">System</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <input type="checkbox" id="message_is_urgent" name="message_is_urgent" value="1">
                            <label for="message_is_urgent">Mark as Urgent</label>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn-send-message">Send Message</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </aside>

            <div class="messages-content">
                <?php if ($dbErrorMessage): ?>
                    <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $dbErrorMessage; ?></div>
                <?php endif; ?>
                <?php if ($successMessage): ?>
                    <div class="success-message" role="alert"><i class="fas fa-check-circle"></i> <?php echo $successMessage; ?></div>
                <?php endif; ?>

                <section class="messages-list">
                    <?php if (!empty($messagesWithComments)): ?>
                        <?php foreach ($messagesWithComments as $message): ?>
                            <article class="message-card type-<?php echo htmlspecialchars($message['message_type']); ?> <?php if ($message['is_urgent']) echo 'is-urgent'; ?> <?php if (!$message['is_read_by_user']) echo 'is-unread'; ?>" id="message-<?php echo $message['messageID']; ?>">
                                <div class="message-header">
                                    <h2 class="message-title"><?php echo htmlspecialchars($message['title']); ?></h2>
                                    <div class="message-meta">
                                        <span class="target-audience"><?php echo htmlspecialchars($message['target_audience_display']); ?></span>
                                        <span class="sender-name">From: <?php echo htmlspecialchars($message['sender_name']); ?></span>
                                        <span class="message-date"><?php echo date("M d, Y - H:i", strtotime($message['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                                </div>

                                <div class="comments-section">
                                    <h4>Comments (<?php echo count($message['comments']); ?>)</h4>
                                    <?php if (!empty($message['comments'])): ?>
                                        <?php foreach ($message['comments'] as $comment): ?>
                                            <div class="comment">
                                                <div class="comment-meta">
                                                    <span class="commenter-name"><?php echo htmlspecialchars($comment['commenter_firstName'] . ' ' . $comment['commenter_lastName']); ?></span>
                                                    - <span class="comment-date"><?php echo date("M d, Y H:i", strtotime($comment['comment_created_at'])); ?></span>
                                                </div>
                                                <p class="comment-text"><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="no-comments">No comments yet.</p>
                                    <?php endif; ?>

                                    <form action="messages.php?filter=<?php echo $currentFilter; ?>#message-<?php echo $message['messageID']; ?>" method="POST" class="add-comment-form" style="margin-top: 1rem;">
                                        <input type="hidden" name="action" value="add_comment">
                                        <input type="hidden" name="message_id" value="<?php echo $message['messageID']; ?>">
                                        <textarea name="comment_text" placeholder="Write a comment..." rows="2" required></textarea>
                                        <button type="submit" class="btn-submit-comment">Add Comment</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php if (!$dbErrorMessage && !$successMessage): // Nezobrazovat, pokud je chyba nebo úspěšná zpráva, která by mohla znamenat, že tam zprávy jsou ?>
                        <div class="no-messages">
                            <span class="material-symbols-outlined">mark_email_unread</span>
                            No messages found for the selected filter.
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>

    <footer>
       <!-- ... obsah patičky z dashboard.php ... -->
        <div class="container">
            <div class="footer-content">
                <div class="footer-column"><h3>WavePass</h3><p>Modern attendance tracking...</p><div class="social-links"><a href="#"><i class="fab fa-facebook-f"></i></a><a href="#"><i class="fab fa-twitter"></i></a><a href="#"><i class="fab fa-linkedin-in"></i></a><a href="#"><i class="fab fa-instagram"></i></a></div></div>
                <div class="footer-column"><h3>Quick Links</h3><ul class="footer-links"><li><a href="index.php#features"><i class="fas fa-chevron-right"></i> Features</a></li><li><a href="index.php#how-it-works"><i class="fas fa-chevron-right"></i> How It Works</a></li><li><a href="pricing.php"><i class="fas fa-chevron-right"></i> Pricing</a></li><li><a href="index.php#contact"><i class="fas fa-chevron-right"></i> Contact</a></li><li><a href="index.php#faq"><i class="fas fa-chevron-right"></i> FAQ</a></li><li><a href="help.php"><i class="fas fa-chevron-right"></i> Help Center</a></li></ul></div>
                <div class="footer-column"><h3>Resources</h3><ul class="footer-links"><li><a href="blog.php"><i class="fas fa-chevron-right"></i> Blog</a></li><li><a href="help.php"><i class="fas fa-chevron-right"></i> Help Center</a></li><li><a href="webinars.php"><i class="fas fa-chevron-right"></i> Webinars</a></li><li><a href="api.php"><i class="fas fa-chevron-right"></i> API Documentation</a></li></ul></div>
                <div class="footer-column"><h3>Contact Info</h3><ul class="footer-links"><li><a href="mailto:info@WavePass.com"><i class="fas fa-envelope"></i> info@WavePass.com</a></li><li><a href="tel:+15551234567"><i class="fas fa-phone"></i> +1 (555) 123-4567</a></li><li><a href="https://www.google.com/maps/search/?api=1&query=123%20Education%20St%2C%20Boston%2C%20MA%2002115" target="_blank" rel="noopener noreferrer"><i class="fas fa-map-marker-alt"></i> 123 Education St...</a></li></ul></div>
            </div>
            <div class="footer-bottom">
                <p>© <?php echo date("Y"); ?> WavePass. All rights reserved. | <a href="privacy.php">Privacy Policy</a> | <a href="terms.php">Terms of Service</a></p>
            </div>
        </div>
    </footer>

    <script>
        // Mobilní menu a header shadow (převzít z dashboard.php, pokud nejsou globální)
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');
        // const closeMenu = document.getElementById('closeMenu'); // Ujistěte se, že máte closeMenu ID v header-employee-panel.php
        const body = document.body;

        if (hamburger && mobileMenu) { // closeMenu je zde volitelné
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                mobileMenu.classList.toggle('active');
                body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
            });
            // Pokud máte closeMenu tlačítko v mobilním menu, přidejte jeho event listener zde
            const closeMenuInMobile = mobileMenu.querySelector('.close-btn'); // Nebo jakékoli jiné ID
            if(closeMenuInMobile) {
                closeMenuInMobile.addEventListener('click', () => {
                    hamburger.classList.remove('active');
                    mobileMenu.classList.remove('active');
                    body.style.overflow = '';
                });
            }
        }

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

        // JavaScript pro admin formulář - zobrazení/skrytí výběru uživatele
        function toggleSpecificUserSelect(targetValue) {
            const container = document.getElementById('specificUserSelectContainer');
            const selectUser = document.getElementById('message_target_specific_user');
            if (targetValue === 'specific_user') {
                container.style.display = 'block';
                selectUser.required = true;
            } else {
                container.style.display = 'none';
                selectUser.required = false;
                selectUser.value = ''; // Reset výběru
            }
        }
        // Inicializace pro případ, že by stránka byla načtena s již vybranou možností (méně pravděpodobné bez POSTu)
        const initialTarget = document.getElementById('message_target');
        if (initialTarget) {
            toggleSpecificUserSelect(initialTarget.value);
        }

    </script>
</body>
</html>