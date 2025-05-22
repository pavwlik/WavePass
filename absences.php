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

$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Employee';
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
$sessionRole = isset($_SESSION["role"]) ? $_SESSION["role"] : 'employee';

$dbErrorMessage = null;
$successMessage = null;
$userAbsences = [];

// --- HANDLE NEW ABSENCE REQUEST SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'request_absence' && $sessionUserId) {
    $start_datetime = trim($_POST['absence_start_datetime']);
    $end_datetime = trim($_POST['absence_end_datetime']);
    $absence_type_input = trim($_POST['absence_type']); // Will be used if absence_type column exists
    $reason = trim(filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_SPECIAL_CHARS));
    $notes = trim(filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_SPECIAL_CHARS));

    // Basic Validation
    if (empty($start_datetime) || empty($end_datetime) /* || empty($absence_type_input) */ ) { // Make absence_type optional if it might not exist yet
        $dbErrorMessage = "Start date/time and end date/time are required.";
        if (empty($absence_type_input) && false) { // Temporarily disable this check if column doesn't exist
            $dbErrorMessage = "Start date/time, end date/time, and absence type are required.";
        }
    } elseif (strtotime($start_datetime) === false || strtotime($end_datetime) === false) {
        $dbErrorMessage = "Invalid date/time format.";
    } elseif (strtotime($start_datetime) >= strtotime($end_datetime)) {
        $dbErrorMessage = "End date/time must be after start date/time.";
    } elseif (strtotime($start_datetime) < time() && date('Y-m-d', strtotime($start_datetime)) < date('Y-m-d')) {
        $dbErrorMessage = "Cannot request absence for a past date.";
    } else {
        try {
               // NEW SQL - adding 'absence' field
               $sqlInsertUser = "INSERT INTO users (username, email, password, firstName, lastName, phone, roleID, absence, dateOfCreation) 
               VALUES (:username, :email, :password, :firstName, :lastName, :phone, :roleID, 0, NOW())"; // Set absence to 0 (present)

                $stmtInsertUser = $pdo->prepare($sqlInsertUser);
                $stmtInsertUser->bindParam(':username', $new_username);
                $stmtInsertUser->bindParam(':email', $new_email);
                $stmtInsertUser->bindParam(':password', $hashed_password);
                $stmtInsertUser->bindParam(':firstName', $new_firstName);
                $stmtInsertUser->bindParam(':lastName', $new_lastName);
                $stmtInsertUser->bindParam(':phone', $new_phone);
                $stmtInsertUser->bindParam(':roleID', $new_roleID);
                // No need to bind absence if it's hardcoded as 0 in the query

            if ($stmt->execute()) {
                $successMessage = "Absence request submitted successfully. It is now pending approval.";
            } else {
                $dbErrorMessage = "Failed to submit absence request. Check if 'absence_type' column exists in your 'absence' table.";
            }
        } catch (PDOException $e) {
            $dbErrorMessage = "Database error submitting request: " . $e->getMessage();
        }
    }
}


// --- DATA FETCHING for current user's absences ---
$currentFilter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; 

if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        $sqlWhereClauses = ["a.userID = :currentUserID"];
        $params = [':currentUserID' => $sessionUserId];

        // Ensure ENUM values here EXACTLY match what's in your database for the 'status' column.
        // Your screenshot shows: enum('pending_approval', 'approved', 'rejected')
        // And default 'pending_approval'.
        if ($currentFilter == 'pending') {
            $sqlWhereClauses[] = "a.status = 'pending_approval'";
        } elseif ($currentFilter == 'approved') {
            $sqlWhereClauses[] = "a.status = 'approved'";
        } elseif ($currentFilter == 'rejected') {
            $sqlWhereClauses[] = "a.status = 'rejected'";
        }
        
        // CORRECTED SQL: Added a.absence_type to the SELECT list
        $sqlFetch = "SELECT a.absenceID, a.absence_start_datetime, a.absence_end_datetime, 
                            a.reason, a.absence_type, a.status, a.notes, a.created_at
                     FROM absence a
                     WHERE " . implode(" AND ", $sqlWhereClauses) . "
                     ORDER BY a.absence_start_datetime DESC, a.created_at DESC";
        
        $stmtFetch = $pdo->prepare($sqlFetch);
        $stmtFetch->execute($params);
        $userAbsences = $stmtFetch->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error fetching absences: " . $e->getMessage();
         // For debugging, you can print the SQL and params
        // $dbErrorMessage .= " SQL: " . $sqlFetch . " Params: " . print_r($params, true);
    }
} else {
    if (!$sessionUserId) $dbErrorMessage = ($dbErrorMessage ? $dbErrorMessage . "<br>" : "") . "User session is invalid.";
    elseif (!isset($pdo) || !($pdo instanceof PDO)) $dbErrorMessage = ($dbErrorMessage ? $dbErrorMessage . "<br>" : "") . "Database connection is not available.";
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="imgs/logo.png" type="image/x-icon"> 
    <title>My Absences - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* === BASIC STYLES (reuse from other pages) === */
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --primary-color-rgb: 67, 97, 238; 
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --warning-color: #FF9800; --danger-color: #F44336;
            --info-color: #2196F3; --system-color: #757575;
            --pending-color: var(--warning-color); 
            --approved-color: var(--success-color);
            --rejected-color: var(--danger-color);
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px; }
        .container, .page-header .container, .container-absences { /* Unified container */
            max-width: 1440px; margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px; 
        }
        .container-absences { display: flex; gap: 1.5rem; } 
        
        /* --- START: HEADER & NAVBAR STYLES (ESSENTIAL) --- */
        header {
            background-color: var(--white);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        header .container { /* Container for navbar items */
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 80px;
        }
        .navbar { /* Use navbar class on the nav element if needed, or style header .container directly */
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%; /* Ensure navbar takes full width of its container */
        }
        .logo {
            font-size: 1.8rem; font-weight: 800; color: var(--primary-color);
            text-decoration: none; display: flex; align-items: center;
        }
        .logo img.logo-img { height: 45px; width: auto; margin-right: 0.6rem; }
        .logo span { color: var(--dark-color); font-weight: 600; }

        .nav-links { display: flex; list-style: none; align-items: center; gap: 0.3rem; margin:0; padding:0; }
        .nav-links a:not(.btn-outline) {
            color: var(--dark-color); text-decoration: none; font-weight: 500;
            padding: 0.6rem 0.9rem; font-size: 0.9rem; border-radius: 6px;
            transition: var(--transition); display: inline-flex; align-items: center;
        }
        .nav-links a:not(.btn-outline):hover, 
        .nav-links a:not(.btn-outline).active-nav-link {
            color: var(--primary-color); background-color: rgba(var(--primary-color-rgb), 0.07);
        }
        .nav-links .btn-outline {
            display: inline-flex; gap: 8px; align-items: center; justify-content: center;
            padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none;
            font-weight: 600; transition: var(--transition); cursor: pointer;
            font-size: 0.85rem; background-color: transparent;
            border: 2px solid var(--primary-color); color: var(--primary-color);
        }
        .nav-links .btn-outline:hover { background-color: var(--primary-color); color: var(--white); transform: translateY(-2px); }
        .nav-user-photo { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; margin-right: 8px; vertical-align: middle; border: 1.5px solid var(--light-gray); }
        .nav-links a .material-symbols-outlined { font-size: 1.4em; vertical-align: middle; margin-right: 6px; line-height: 1; }

        .hamburger { display: none; flex-direction: column; justify-content: space-around; width: 28px; height: 22px; background: transparent; border: none; cursor: pointer; padding: 0; z-index: 1002; }
        .hamburger span { display: block; width: 100%; height: 3px; background-color: var(--dark-color); border-radius: 10px; transition: all 0.3s linear; position: relative; transform-origin: 1px; }
        .hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(1px, -1px); }
        .hamburger.active span:nth-child(2) { opacity: 0; transform: translateX(20px); }
        .hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(2px, 0px); }

        .mobile-menu {
            position: fixed; top: 0; right: -100%; width: 280px; height: 100vh;
            background-color: var(--white); box-shadow: -5px 0 15px rgba(0,0,0,0.1);
            padding: 60px 20px 20px; transition: right 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            z-index: 1001; display: flex; flex-direction: column; overflow-y: auto;
        }
        .mobile-menu.active { right: 0; }
        .mobile-links { list-style: none; padding: 0; margin: 20px 0 0 0; display: flex; flex-direction: column; gap: 0.5rem; flex-grow: 1; }
        .mobile-links li { width: 100%; }
        .mobile-links a { display: flex; align-items: center; padding: 0.8rem 1rem; text-decoration: none; color: var(--dark-color); font-size: 1rem; border-radius: 6px; transition: var(--transition); font-weight: 500; }
        .mobile-links a:hover, .mobile-links a.active-nav-link { color: var(--primary-color); background-color: rgba(var(--primary-color-rgb), 0.07); }
        .mobile-menu .btn-outline { width: 100%; margin-top: auto; padding-top: 0.8rem; padding-bottom: 0.8rem; margin-bottom: 1rem; font-size: 0.9rem; }
        .close-btn { position: absolute; top: 18px; right: 20px; font-size: 1.8rem; color: var(--dark-color); cursor: pointer; background: none; border: none; padding: 5px; }
        .mobile-links a .nav-user-photo.mobile-nav-user-photo { width: 28px; height: 28px; margin-right: 10px; }

        @media (max-width: 992px) { 
            header .container .navbar .nav-links { display: none; } 
            header .container .navbar .hamburger { display: flex; } 
            .container-absences { flex-direction: column; } 
            .absences-sidebar { flex: 0 0 auto; width: 100%; margin-bottom: 1.5rem; }
        }
        /* --- END: HEADER & NAVBAR STYLES --- */
        
        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; margin: 0; } .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        .db-error-message, .success-message { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .db-error-message { background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        .success-message { background-color: rgba(76,175,80,0.1); color: var(--success-color); border-left-color: var(--success-color); }

        .absences-sidebar { flex: 0 0 280px; background-color: var(--white); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); height: fit-content; margin-top: 0; /* Align with page-header bottom */ }
        .absences-sidebar h3 { font-size: 1.2rem; margin-bottom: 1rem; color: var(--dark-color); padding-bottom: 0.5rem; border-bottom: 1px solid var(--light-gray); }
        .filter-list { list-style: none; padding: 0; margin-bottom: 1.5rem; }
        .filter-list li a { display: flex; align-items: center; gap: 0.7rem; padding: 0.8rem 1rem; text-decoration: none; color: var(--dark-color); border-radius: 6px; transition: var(--transition); font-weight: 500; }
        .filter-list li a:hover, .filter-list li a.active-filter { background-color: rgba(var(--primary-color-rgb), 0.1); color: var(--primary-color); }
        .filter-list li a .material-symbols-outlined { font-size: 1.3em; }
        .btn-request-absence { display: block; width: 100%; text-align: center; background-color: var(--primary-color); color: var(--white); padding: 0.8rem 1rem; border-radius: 6px; text-decoration: none; font-weight: 500; transition: var(--transition); }
        .btn-request-absence:hover { background-color: var(--primary-dark); }
        .btn-request-absence .material-symbols-outlined { vertical-align: middle; margin-right: 0.5rem; font-size:1.2em; }

        .absences-content { flex-grow: 1; /* margin-top: 1.5rem; Removed to align with sidebar */ }
        .request-absence-form-panel { background-color: var(--white); padding: 1.5rem 1.8rem; border-radius: 8px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); margin-bottom: 2rem; }
        .request-absence-form-panel h2 { font-size: 1.3rem; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.9rem; }
        .form-group input[type="datetime-local"], .form-group input[type="text"], .form-group select, .form-group textarea { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--light-gray); border-radius: 6px; font-family: inherit; font-size: 0.9rem; background-color: var(--white); transition: border-color 0.2s, box-shadow 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline:none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb),0.2); }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-actions { margin-top: 1.5rem; text-align: right; }
        .form-actions .btn-primary { background-color: var(--primary-color); color: var(--white); border: none; padding: 0.7rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: 0.5rem; }
        .form-actions .btn-primary:hover { background-color: var(--primary-dark); }


        .absences-list { display: flex; flex-direction: column; gap: 1.5rem; }
        .absence-card { background-color: var(--white); border-radius: 8px; box-shadow: var(--shadow); border-left: 5px solid var(--gray-color); padding: 1.5rem; }
        .absence-card.status-pending_approval { border-left-color: var(--pending-color); } /* Matched ENUM */
        .absence-card.status-approved { border-left-color: var(--approved-color); } /* Matched ENUM */
        .absence-card.status-rejected { border-left-color: var(--rejected-color); } /* Matched ENUM */

        .absence-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.8rem; flex-wrap: wrap; gap: 0.5rem;}
        .absence-title { font-size: 1.15rem; font-weight: 600; text-transform: capitalize; }
        .absence-status-badge { padding: 0.3rem 0.8rem; border-radius: 15px; font-size: 0.8rem; font-weight: 600; color: var(--white); text-transform: capitalize; }
        .status-pending_approval { background-color: var(--pending-color); } /* Matched ENUM */
        .status-approved { background-color: var(--approved-color); } /* Matched ENUM */
        .status-rejected { background-color: var(--rejected-color); } /* Matched ENUM */

        .absence-dates { font-size: 0.9rem; color: var(--dark-color); margin-bottom: 0.5rem; font-weight:500; }
        .absence-dates .material-symbols-outlined { font-size: 1.1em; vertical-align: text-bottom; margin-right: 0.3rem; }
        .absence-details p { margin-bottom: 0.3rem; font-size: 0.9rem; }
        .absence-details strong { font-weight: 500; color: var(--gray-color); }
        .no-absences { text-align: center; padding: 3rem 1rem; background-color: var(--white); border-radius: 8px; box-shadow: var(--shadow); color: var(--gray-color); font-size: 1.1rem; }
        .no-absences .material-symbols-outlined { font-size: 3rem; display: block; margin-bottom: 1rem; color: var(--primary-color); }

        footer { /* ... Full footer styles ... */ }
    </style>
</head>
<body>
    <?php 
      // Determine the correct relative path to components based on current script's location
      $pathToComponents = "";
      if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) { // If in admin subfolder
          $pathToComponents = "../components/";
      } else { // If in root
          $pathToComponents = "components/";
      }
      // Similar logic for imgs if header uses images from root 'imgs' folder
      // For now, assuming header-employee-panel.php handles its own asset paths correctly or is also in components
      require_once $pathToComponents . "header-employee-panel.php"; 
    ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>My Absences</h1>
                <p class="sub-heading">Request new absences and view the status of your requests.</p>
            </div>
        </div>

        <div class="container-absences container"> 
            <aside class="absences-sidebar">
                <h3>Absence Options</h3>
                <a href="#request-form" class="btn-request-absence" style="margin-bottom:1.5rem;">
                    <span class="material-symbols-outlined">add_circle</span> Request New Absence
                </a>
                <h3>Filter Requests</h3>
                <ul class="filter-list">
                    <li><a href="absences.php?filter=all" class="<?php if ($currentFilter == 'all') echo 'active-filter'; ?>"><span class="material-symbols-outlined">list_alt</span> All My Requests</a></li>
                    <li><a href="absences.php?filter=pending" class="<?php if ($currentFilter == 'pending') echo 'active-filter'; ?>"><span class="material-symbols-outlined">pending_actions</span> Pending Approval</a></li>
                    <li><a href="absences.php?filter=approved" class="<?php if ($currentFilter == 'approved') echo 'active-filter'; ?>"><span class="material-symbols-outlined">check_circle</span> Approved</a></li>
                    <li><a href="absences.php?filter=rejected" class="<?php if ($currentFilter == 'rejected') echo 'active-filter'; ?>"><span class="material-symbols-outlined">cancel</span> Rejected</a></li>
                </ul>
            </aside>

            <div class="absences-content">
                <?php if ($dbErrorMessage): ?>
                    <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $dbErrorMessage; ?></div>
                <?php endif; ?>
                <?php if ($successMessage): ?>
                    <div class="success-message" role="alert"><i class="fas fa-check-circle"></i> <?php echo $successMessage; ?></div>
                <?php endif; ?>

                <section class="request-absence-form-panel" id="request-form">
                    <h2>Request New Absence</h2>
                    <form action="absences.php" method="POST">
                        <input type="hidden" name="action" value="request_absence">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="absence_start_datetime">Start Date & Time <span style="color:red;">*</span></label>
                                <input type="datetime-local" id="absence_start_datetime" name="absence_start_datetime" required>
                            </div>
                            <div class="form-group">
                                <label for="absence_end_datetime">End Date & Time <span style="color:red;">*</span></label>
                                <input type="datetime-local" id="absence_end_datetime" name="absence_end_datetime" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="absence_type">Type of Absence <span style="color:red;">*</span></label>
                            <select id="absence_type" name="absence_type" required>
                                <option value="">-- Select Type --</option>
                                <option value="vacation">Vacation (Dovolená)</option>
                                <option value="sickness">Sickness (Nemoc)</option>
                                <option value="medical_appointment">Medical Appointment (Lékař)</option>
                                <option value="personal_leave">Personal Leave</option>
                                <option value="business_trip">Business Trip</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="reason">Reason (Briefly, if 'Other' or more details needed)</label>
                            <input type="text" id="reason" name="reason" placeholder="e.g., Annual leave, Doctor's appointment for check-up">
                        </div>
                        <div class="form-group">
                            <label for="notes">Additional Notes (Optional)</label>
                            <textarea id="notes" name="notes" placeholder="Any extra information for the approver."></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary"><span class="material-symbols-outlined">send</span> Submit Request</button>
                        </div>
                    </form>
                </section>

                <section class="absences-list-panel" style="margin-top: 2rem;">
                     <div class="absences-list">
                        <?php if (!empty($userAbsences)): ?>
                            <?php foreach ($userAbsences as $absence): ?>
                                <article class="absence-card status-<?php echo htmlspecialchars(strtolower(str_replace(' ', '_', $absence['status']))); // Ensure status class is valid ?>">
                                    <div class="absence-header">
                                        <h3 class="absence-title">
                                            <?php 
                                            // Display absence_type if column exists and has a value
                                            if (isset($absence['absence_type']) && !empty($absence['absence_type'])) {
                                                echo htmlspecialchars(str_replace('_', ' ', $absence['absence_type']));
                                            } else {
                                                echo "General Absence"; // Fallback if no type
                                            }
                                            ?>
                                        </h3>
                                        <span class="absence-status-badge status-<?php echo htmlspecialchars(strtolower(str_replace(' ', '_', $absence['status']))); // Ensure status class is valid ?>">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', $absence['status'])); ?>
                                        </span>
                                    </div>
                                    <div class="absence-dates">
                                        <span class="material-symbols-outlined">calendar_month</span>
                                        <?php echo date("D, M j, Y H:i", strtotime($absence['absence_start_datetime'])); ?>
                                        to
                                        <?php echo date("D, M j, Y H:i", strtotime($absence['absence_end_datetime'])); ?>
                                    </div>
                                    <div class="absence-details">
                                        <?php if (!empty($absence['reason'])): ?>
                                            <p><strong>Reason:</strong> <?php echo htmlspecialchars($absence['reason']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($absence['notes'])): ?>
                                            <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($absence['notes'])); ?></p>
                                        <?php endif; ?>
                                        <p><small><strong>Requested on:</strong> <?php echo date("M d, Y H:i", strtotime($absence['created_at'])); ?></small></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                             <?php if (!$dbErrorMessage): ?>
                            <div class="no-absences">
                                <span class="material-symbols-outlined">event_busy</span>
                                You have no absence requests matching the current filter.
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <?php require_once "components/footer-user.php"; ?>

    <script>
        // JavaScript for mobile menu (should be in header or global script)
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');
        const closeMenuBtn = document.getElementById('closeMenu'); // Assuming this ID is in your header component
        const body = document.body;

        if (hamburger && mobileMenu) {
            hamburger.addEventListener('click', () => {
                mobileMenu.classList.toggle('active');
                body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
            });
            if (closeMenuBtn) {
                closeMenuBtn.addEventListener('click', () => {
                    mobileMenu.classList.remove('active');
                    hamburger.classList.remove('active'); // Also toggle hamburger state
                    body.style.overflow = '';
                });
            }
            // Close menu on link click
            mobileMenu.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    if (mobileMenu.classList.contains('active')) {
                        mobileMenu.classList.remove('active');
                        hamburger.classList.remove('active');
                        body.style.overflow = '';
                    }
                });
            });
        }
    </script>
</body>
</html>