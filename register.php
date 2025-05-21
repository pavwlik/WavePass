<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php'; // Your database connection. Ensure PDO::ERRMODE_EXCEPTION is set in db.php

// Initialize variables for form fields
$username = $password = $confirm_password = $email = $firstName = $lastName = $rfid = $phone = "";

// Initialize variables for error messages
$username_err = $password_err = $confirm_password_err = $email_err = $firstName_err = $lastName_err = $rfid_err = $phone_err = "";

// Initialize variables for form submission feedback
$feedback_message = "";
$feedback_type = ""; // 'success' or 'error'

// Redirect if already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate username
    $trimmed_username = isset($_POST["username"]) ? trim($_POST["username"]) : '';
    if (empty($trimmed_username)) {
        $username_err = "Please enter a username.";
    } else {
        // It's good practice to wrap DB operations in try-catch if ERRMODE_EXCEPTION is on
        try {
            $sql = "SELECT userID FROM users WHERE username = :username";
            if ($stmt = $pdo->prepare($sql)) {
                $stmt->bindParam(":username", $trimmed_username, PDO::PARAM_STR);
                if ($stmt->execute()) {
                    if ($stmt->rowCount() == 1) {
                        $username_err = "This username is already taken.";
                    } else {
                        $username = $trimmed_username;
                    }
                } else {
                    $username_err = "Oops! Something went wrong checking username.";
                }
                unset($stmt);
            }
        } catch (PDOException $e) {
            error_log("PDOException in username check: " . $e->getMessage());
            $username_err = "Error checking username. Please try again.";
        }
    }

    // Validate First Name
    $trimmed_firstName = isset($_POST["firstName"]) ? trim($_POST["firstName"]) : '';
    if (empty($trimmed_firstName)) {
        $firstName_err = "Please enter your first name.";
    } else {
        $firstName = $trimmed_firstName;
    }

    // Validate Last Name
    $trimmed_lastName = isset($_POST["lastName"]) ? trim($_POST["lastName"]) : '';
    if (empty($trimmed_lastName)) {
        $lastName_err = "Please enter your last name.";
    } else {
        $lastName = $trimmed_lastName;
    }

    // Validate email
    $trimmed_email = isset($_POST["email"]) ? trim($_POST["email"]) : '';
    if (empty($trimmed_email)) {
        $email_err = "Please enter an email address.";
    } elseif (!filter_var($trimmed_email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        try {
            $sql = "SELECT userID FROM users WHERE email = :email";
            if ($stmt = $pdo->prepare($sql)) {
                $stmt->bindParam(":email", $trimmed_email, PDO::PARAM_STR);
                if ($stmt->execute()) {
                    if ($stmt->rowCount() == 1) {
                        $email_err = "This email is already registered.";
                    } else {
                        $email = $trimmed_email;
                    }
                } else {
                    $email_err = "Oops! Something went wrong checking email.";
                }
                unset($stmt);
            }
        } catch (PDOException $e) {
            error_log("PDOException in email check: " . $e->getMessage());
            $email_err = "Error checking email. Please try again.";
        }
    }

    // Validate password
    $trimmed_password = isset($_POST["password"]) ? trim($_POST["password"]) : '';
    if (empty($trimmed_password)) {
        $password_err = "Please enter a password.";
    } elseif (strlen($trimmed_password) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = $trimmed_password;
    }

    // Validate confirm password
    $trimmed_confirm_password = isset($_POST["confirm_password"]) ? trim($_POST["confirm_password"]) : '';
    if (empty($trimmed_confirm_password)) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = $trimmed_confirm_password;
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Passwords did not match.";
        }
    }

    // Optional fields (RFID, Phone)
    $rfid_input = isset($_POST["rfid"]) ? trim($_POST["rfid"]) : ''; // Keep user input for redisplay
    $phone_input = isset($_POST["phone"]) ? trim($_POST["phone"]) : '';
    
    $processed_rfid = null; // This will hold the integer RFID or null for DB

    if (!empty($rfid_input)) {
        if (!filter_var($rfid_input, FILTER_VALIDATE_INT) && $rfid_input !== '0') { // Allow '0'
            $rfid_err = "RFID must be a whole number.";
        } else {
            $rfid_int_val = intval($rfid_input);
            try {
                $sql_check_rfid = "SELECT userID FROM users WHERE RFID = :rfid";
                if ($stmt_check_rfid = $pdo->prepare($sql_check_rfid)) {
                    $stmt_check_rfid->bindParam(":rfid", $rfid_int_val, PDO::PARAM_INT); // Bind as INT
                    if ($stmt_check_rfid->execute()) {
                        if ($stmt_check_rfid->rowCount() > 0) {
                            $rfid_err = "This RFID tag is already in use.";
                        } else {
                            $processed_rfid = $rfid_int_val; // Store validated integer RFID for DB
                        }
                    } else {
                         $rfid_err = "Oops! Something went wrong with RFID check.";
                    }
                    unset($stmt_check_rfid);
                }
            } catch (PDOException $e) {
                error_log("PDOException in RFID check: " . $e->getMessage());
                $rfid_err = "Error checking RFID. Please try again.";
            }
        }
    }
    $rfid = $rfid_input; // For re-populating the form field with original input

    $phone = !empty($phone_input) ? $phone_input : null;


    // Check input errors before inserting in database
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($email_err) && empty($firstName_err) && empty($lastName_err) && empty($rfid_err) && empty($phone_err)) {
        
        $sql = "INSERT INTO users (username, password, firstName, lastName, email, roleID, RFID, phone, absence) 
                VALUES (:username, :password, :firstName, :lastName, :email, :roleID, :rfid, :phone, 0)";
        
        try {
            if ($stmt = $pdo->prepare($sql)) {
                $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
                $stmt->bindParam(":password", $param_password, PDO::PARAM_STR);
                $stmt->bindParam(":firstName", $param_firstName, PDO::PARAM_STR);
                $stmt->bindParam(":lastName", $param_lastName, PDO::PARAM_STR);
                $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
                $stmt->bindParam(":roleID", $param_roleID, PDO::PARAM_STR);
                
                if ($processed_rfid === null) {
                    $stmt->bindParam(":rfid", $processed_rfid, PDO::PARAM_NULL);
                } else {
                    $stmt->bindParam(":rfid", $processed_rfid, PDO::PARAM_INT); // Bind as INT
                }

                if ($phone === null) {
                    $stmt->bindParam(":phone", $phone, PDO::PARAM_NULL);
                } else {
                    $stmt->bindParam(":phone", $phone, PDO::PARAM_STR);
                }
                
                $param_username = $username;
                $param_password = password_hash($password, PASSWORD_DEFAULT);
                $param_firstName = $firstName;
                $param_lastName = $lastName;
                $param_email = $email;
                $param_roleID = 'employee'; // Default role from table structure too
                
                if ($stmt->execute()) {
                    $feedback_message = "Registration successful! You can now <a href='login.php'>login</a>.";
                    $feedback_type = "success";
                    // Clear form fields after successful registration
                    $username = $password = $confirm_password = $email = $firstName = $lastName = $rfid = $phone = ""; 
                } else {
                    // This else might not be reached if ERRMODE_EXCEPTION is on and execute fails
                    $feedback_message = "Registration failed. Please try again. SQL execute error.";
                    $feedback_type = "error";
                    error_log("Registration failed: " . implode(";", $stmt->errorInfo()));
                }
                unset($stmt);
            } else {
                $feedback_message = "Registration failed. Please try again. SQL prepare error.";
                $feedback_type = "error";
            }
        } catch (PDOException $e) {
            error_log("Registration SQL Error: " . $e->getMessage());
            $feedback_message = "Something went wrong with registration. Please try again later.";
            $feedback_type = "error";
        }
    } else {
        if (empty($feedback_message)) { // Only set this if no major DB error message is already set
            $feedback_message = "Please correct the errors in the form.";
            $feedback_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WavePass - Register</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a56d4;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --white: #ffffff;
            --gray-color: #6c757d;
            --light-gray: #e9ecef;
            --danger-color: #f72585;
            --success-color-custom: #28a745; /* Standard green for success */
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: var(--light-color); display: flex; flex-direction: column; min-height: 100vh; overflow-x:hidden; }
        main { flex-grow: 1; padding-top: 80px; /* Account for fixed header */ }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
        
        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; height: 80px; }
        .logo { font-size: 1.8rem; font-weight: 800; color: var(--primary-color); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .logo i { font-size: 1.5rem; }
        .logo span { color: var(--dark-color); font-weight: 600; }
        .nav-links { display: flex; list-style: none; align-items: center; gap: 0.5rem; }
        .nav-links a:not(.btn) { color: var(--dark-color); text-decoration: none; font-weight: 500; padding: 0.7rem 1rem; font-size: 0.95rem; border-radius: 8px; position: relative; transition: color .3s ease, background-color .3s ease; }
        .nav-links a:not(.btn):hover, .nav-links a:not(.btn).active-link { color: var(--primary-color); background-color: rgba(67,97,238,0.07); }
        .nav-links .btn { display: inline-flex; gap: 8px; align-items: center; justify-content: center; padding: 0.7rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: var(--transition); cursor: pointer; font-size: 0.9rem; background-color: var(--primary-color); color: var(--white); box-shadow: 0 4px 14px rgba(67,97,238,0.2); }
        .nav-links .btn .material-symbols-outlined { font-size: 1.2em; vertical-align: middle; margin-right: 4px; }
        .nav-links .btn:hover { background-color: var(--primary-dark); box-shadow: 0 6px 20px rgba(67,97,238,0.3); transform: translateY(-2px); }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0.8rem 2rem; background-color: var(--primary-color); color: var(--white); border: none; border-radius: 8px; text-decoration: none; font-weight: 600; transition: var(--transition); cursor: pointer; box-shadow: 0 4px 14px rgba(67,97,238,0.3); font-size: 0.95rem; }
        .btn i, .btn .material-symbols-outlined { margin-right: 6px; font-size: 1.1em; }
        .btn:hover { background-color: var(--primary-dark); box-shadow: 0 6px 20px rgba(67,97,238,0.4); transform: translateY(-2px); }

        .hamburger { display: none; cursor: pointer; width: 30px; height: 24px; position: relative; z-index: 1001; }
        .hamburger span { display: block; width: 100%; height: 3px; background-color: var(--dark-color); position: absolute; left: 0; transition: var(--transition); transform-origin: center; }
        .hamburger span:nth-child(1) { top: 0; } .hamburger span:nth-child(2) { top: 50%; transform: translateY(-50%); } .hamburger span:nth-child(3) { bottom: 0; }
        .hamburger.active span:nth-child(1) { top: 50%; transform: translateY(-50%) rotate(45deg); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { bottom: 50%; transform: translateY(50%) rotate(-45deg); }

        .mobile-menu { position: fixed; top: 0; left: 0; width: 100%; height: 100vh; background-color: var(--white); z-index: 1000; display: flex; flex-direction: column; justify-content: center; align-items: center; transform: translateX(-100%); transition: transform 0.4s cubic-bezier(0.23,1,0.32,1); padding: 2rem; }
        .mobile-menu.active { transform: translateX(0); }
        .mobile-links { list-style: none; text-align: center; width: 100%; max-width: 300px; }
        .mobile-links li { margin-bottom: 1.5rem; }
        .mobile-links a { color: var(--dark-color); text-decoration: none; font-weight: 600; font-size: 1.2rem; display: inline-block; padding: 0.5rem 1rem; transition: var(--transition); border-radius: 8px; }
        .mobile-links a:hover, .mobile-links a.active-link { color: var(--primary-color); background-color: rgba(67,97,238,0.1); }
        .mobile-menu .btn .material-symbols-outlined { font-size: 1.2em; vertical-align: middle; margin-right: 4px; }
        .mobile-menu .btn { margin-top: 2rem; width: 100%; max-width: 200px; }
        .close-btn { position: absolute; top: 30px; right: 30px; font-size: 1.8rem; color: var(--dark-color); cursor: pointer; transition: var(--transition); line-height: 1; }
        .close-btn:hover { color: var(--primary-color); transform: rotate(90deg); }

        .register-section { display: flex; align-items: center; justify-content: center; padding: 4rem 0; flex-grow: 1; margin-top: 80px; }
        .register-container { background-color: var(--white); padding: 2.5rem 3rem; border-radius: 12px; box-shadow: var(--shadow); width: 100%; max-width: 550px; text-align: center; }
        .register-container h1 { font-size: 1.8rem; margin-bottom: 0.8rem; color: var(--dark-color); }
        .register-container .register-subtitle { font-size: 0.95rem; color: var(--gray-color); margin-bottom: 1.5rem; }
        
        /* Form feedback messages (overall success/error) */
        .form-feedback {
            padding: 0.8rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
            border: 1px solid transparent;
        }
        .form-feedback i { margin-right: 0.5em; }
        .form-success { /* This is your existing class, used for success feedback */
            background-color: rgba(40,167,69,0.1);
            color: var(--success-color-custom);
            border: 1px solid rgba(40,167,69,0.3);
            /* padding, border-radius, margin-bottom, font-size, text-align are already here from your original .form-success */
        }
        .form-success a { color: var(--primary-dark); font-weight: bold; } /* Your existing style for links in success */
        
        .form-danger-msg { /* New class for error feedback box */
            background-color: rgba(247,37,133,0.1); /* Using --danger-color */
            color: var(--danger-color);
            border-color: rgba(247,37,133,0.3);
        }
        .form-danger-msg a { color: var(--danger-color); font-weight: bold; }


        .form-group { margin-bottom: 1.2rem; text-align: left; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--dark-color); font-size: 0.9rem; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"] { width: 100%; padding: 0.8rem 1rem; border: 1px solid var(--light-gray); border-radius: 8px; font-size: 0.9rem; font-family: inherit; color: var(--dark-color); transition: border-color .3s ease, box-shadow .3s ease; background-color: #fdfdff; }
        .form-group input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(67,97,238,0.15); background-color: var(--white); }
        .form-group.has-error input { border-color: var(--danger-color); }
        
        .form-error { color: var(--danger-color); font-size: 0.85rem; margin-top: 0.2rem; display: block; }
        .form-error i { margin-right: 4px; } /* Icon for inline errors */

        .register-container .btn { width: 100%; padding: 0.9rem 2rem; margin-top: 1rem; }
        .login-link { margin-top: 1.5rem; font-size: 0.9rem; }
        .login-link a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .login-link a:hover { text-decoration: underline; }

        footer { background-color: var(--dark-color); color: var(--white); padding: 5rem 0 2rem; flex-shrink: 0; }
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 3rem; margin-bottom: 3rem; }
        .footer-column h3 { font-size: 1.3rem; margin-bottom: 1.8rem; position: relative; padding-bottom: 0.8rem; }
        .footer-column h3::after { content: ''; position: absolute; left: 0; bottom: 0; width: 50px; height: 3px; background-color: var(--primary-color); border-radius: 3px; }
        .footer-links { list-style: none; } .footer-links li { margin-bottom: 0.8rem; }
        .footer-links a { color: rgba(255,255,255,0.8); text-decoration: none; transition: var(--transition); font-size: 0.95rem; display: inline-block; padding: 0.2rem 0; }
        .footer-links a:hover { color: var(--white); transform: translateX(5px); }
        .footer-links a i { margin-right: 0.5rem; width: 20px; text-align: center; }
        .social-links { display: flex; gap: 1.2rem; margin-top: 1.5rem; }
        .social-links a { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background-color: rgba(255,255,255,0.1); color: var(--white); border-radius: 50%; font-size: 1.1rem; transition: var(--transition); }
        .social-links a:hover { background-color: var(--primary-color); transform: translateY(-3px); }
        .footer-bottom { text-align: center; padding-top: 3rem; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.9rem; color: rgba(255,255,255,0.6); }
        .footer-bottom a { color: rgba(255,255,255,0.8); text-decoration: none; transition: var(--transition); }
        .footer-bottom a:hover { color: var(--primary-color); }

        @media (max-width: 768px) { .nav-links { display: none; } .hamburger { display: flex; } .register-section { padding-top: 2rem; margin-top: 80px; } .register-container { padding: 2rem 1.5rem; max-width: 90%; } .register-container h1 { font-size: 1.6rem; } }
        @media (max-width: 576px) { .register-container { padding: 1.5rem 1rem; } .register-container .register-subtitle { margin-bottom: 1.5rem; } }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">
                    <i class="fas fa-chalkboard-teacher"></i>
                    Wave<span>Pass</span>
                </a>
                <ul class="nav-links">
                    <li><a href="index.php#features">Features</a></li>
                    <li><a href="index.php#how-it-works">How It Works</a></li>
                    <li><a href="index.php#about-us">About Us</a></li>
                    <li><a href="index.php#contact">Contact</a></li>
                    <li><a href="index.php#faq">FAQ</a></li>
                    <li class="nav-item-login"><a href="login.php" class="btn"><span class="material-symbols-outlined">account_circle</span> Login</a></li>
                </ul>
                <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
            </nav>
        </div>
        <div class="mobile-menu" id="mobileMenu">
            <span class="close-btn" id="closeMenu"><i class="fas fa-times"></i></span>
            <ul class="mobile-links">
                <li><a href="index.php#features">Features</a></li>
                <li><a href="index.php#how-it-works">How It Works</a></li>
                <li><a href="index.php#about-us">About Us</a></li>
                <li><a href="index.php#contact">Contact</a></li>
                <li><a href="index.php#faq">FAQ</a></li>
            </ul>
            <a href="login.php" class="btn"><span class="material-symbols-outlined">person</span> Login</a>
        </div>
    </header>

    <main>
        <section class="register-section">
            <div class="register-container">
                <h1>Create Account</h1>
                <p class="register-subtitle">Join WavePass today! Fill out the form below to get started.</p>

                <?php if(!empty($feedback_message)): ?>
                    <div class="form-feedback <?php echo ($feedback_type === 'success') ? 'form-success' : 'form-danger-msg'; ?>">
                        <?php if($feedback_type === 'success'): ?>
                            <i class="fas fa-check-circle"></i>
                        <?php else: // 'error' ?>
                            <i class="fas fa-exclamation-triangle"></i>
                        <?php endif; ?>
                        <?php echo $feedback_message; ?>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
                    <div class="form-group <?php echo (!empty($firstName_err)) ? 'has-error' : ''; ?>">
                        <label for="firstName">First Name</label>
                        <input type="text" name="firstName" id="firstName" value="<?php echo htmlspecialchars($firstName); ?>" required>
                        <span class="form-error"><?php if(!empty($firstName_err)) { echo '<i class="fas fa-exclamation-circle"></i> ' . $firstName_err; } ?></span>
                    </div>

                    <div class="form-group <?php echo (!empty($lastName_err)) ? 'has-error' : ''; ?>">
                        <label for="lastName">Last Name</label>
                        <input type="text" name="lastName" id="lastName" value="<?php echo htmlspecialchars($lastName); ?>" required>
                        <span class="form-error"><?php if(!empty($lastName_err)) { echo '<i class="fas fa-exclamation-circle"></i> ' . $lastName_err; } ?></span>
                    </div>

                    <div class="form-group <?php echo (!empty($username_err)) ? 'has-error' : ''; ?>">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" required>
                        <span class="form-error"><?php if(!empty($username_err)) { echo '<i class="fas fa-exclamation-circle"></i> ' . $username_err; } ?></span>
                    </div>

                    <div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        <span class="form-error"><?php if(!empty($email_err)) { echo '<i class="fas fa-exclamation-circle"></i> ' . $email_err; } ?></span>
                    </div>

                    <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
                        <label for="password">Password (min. 6 characters)</label>
                        <input type="password" name="password" id="password" required>
                        <span class="form-error"><?php if(!empty($password_err)) { echo '<i class="fas fa-exclamation-circle"></i> ' . $password_err; } ?></span>
                    </div>

                    <div class="form-group <?php echo (!empty($confirm_password_err)) ? 'has-error' : ''; ?>">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required>
                        <span class="form-error"><?php if(!empty($confirm_password_err)) { echo '<i class="fas fa-exclamation-circle"></i> ' . $confirm_password_err; } ?></span>
                    </div>
                    
                    <div class="form-group <?php echo (!empty($rfid_err)) ? 'has-error' : ''; ?>">
                        <label for="rfid">RFID Tag (Optional, numbers only)</label>
                        <input type="text" name="rfid" id="rfid" value="<?php echo htmlspecialchars($rfid); ?>">
                        <span class="form-error"><?php if(!empty($rfid_err)) { echo '<i class="fas fa-exclamation-circle"></i> ' . $rfid_err; } ?></span>
                    </div>

                    <div class="form-group <?php echo (!empty($phone_err)) ? 'has-error' : ''; ?>"> <!-- Added has-error for consistency if $phone_err is used -->
                        <label for="phone">Phone (Optional)</label>
                        <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($phone); ?>">
                        <span class="form-error"><?php if(!empty($phone_err)) { echo '<i class="fas fa-exclamation-circle"></i> ' . $phone_err; } ?></span>
                    </div>

                    <button type="submit" class="btn"><i class="fas fa-user-plus"></i> Register</button>
                </form>
                <p class="login-link">Already have an account? <a href="login.php">Login here</a>.</p>
            </div>
        </section>
    </main>

     <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>WavePass</h3>
                    <p>Modern attendance tracking solutions for educational institutions of all sizes.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="index.php#features"><i class="fas fa-chevron-right"></i> Features</a></li>
                        <li><a href="index.php#how-it-works"><i class="fas fa-chevron-right"></i> How It Works</a></li>
                        <li><a href="index.php#contact"><i class="fas fa-chevron-right"></i> Contact</a></li>
                        <li><a href="index.php#faq"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                        <li><a href="pricing.php"><i class="fas fa-chevron-right"></i> Pricing</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Resources</h3>
                    <ul class="footer-links">
                        <li><a href="blog.php"><i class="fas fa-chevron-right"></i> Blog</a></li>
                        <li><a href="help.php"><i class="fas fa-chevron-right"></i> Help Center</a></li>
                        <li><a href="webinars.php"><i class="fas fa-chevron-right"></i> Webinars</a></li>
                        <li><a href="api.php"><i class="fas fa-chevron-right"></i> API Documentation</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul class="footer-links">
                        <li><a href="mailto:info@WavePass.com"><i class="fas fa-envelope"></i> info@WavePass.com</a></li>
                        <li><a href="tel:+15551234567"><i class="fas fa-phone"></i> +1 (555) 123-4567</a></li>
                        <li><a href="https://www.google.com/maps/search/?api=1&query=123%20Education%20St%2C%20Boston%2C%20MA%2002115" target="_blank" rel="noopener noreferrer"><i class="fas fa-map-marker-alt"></i> 123 Education St, Boston, MA</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© <?php echo date("Y"); ?> WavePass All rights reserved. | <a href="privacy.php">Privacy Policy</a> | <a href="terms.php">Terms of Service</a></p>
            </div>
        </div>
    </footer>

    <script>
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
            const mobileLinks = document.querySelectorAll('.mobile-menu a');
            mobileLinks.forEach(link => {
                link.addEventListener('click', () => {
                    const href = link.getAttribute('href');
                    let close = false;
                    if (href) {
                        if (href.startsWith('#') || href.startsWith('index.php#')) close = true;
                        else if (href.includes('.php') && !href.startsWith('http')) close = true;
                    }
                    if (link.classList.contains('btn')) close = true;
                    if (close) {
                        hamburger.classList.remove('active');
                        mobileMenu.classList.remove('active');
                        body.style.overflow = '';
                    }
                });
            });
        }
        document.querySelectorAll('a[href^="#"], a[href^="index.php#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#' || href.length === 1) return;
                let targetId;
                let targetPage = window.location.pathname;
                if (href.startsWith('index.php#')) {
                    targetId = href.substring(href.indexOf('#') + 1);
                    targetPage = 'index.php';
                } else if (href.startsWith('#')) {
                    targetId = href.substring(1);
                } else { return; }
                if (!window.location.pathname.endsWith(targetPage) && targetPage === 'index.php') {
                    window.location.href = href; return;
                }
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    e.preventDefault();
                    const headerHeight = document.querySelector('header') ? document.querySelector('header').offsetHeight : 0;
                    const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - headerHeight;
                    window.scrollTo({ top: targetPosition, behavior: 'smooth' });
                }
            });
        });
        const pageHeader = document.querySelector('header');
        if (pageHeader) {
            window.addEventListener('scroll', () => {
                pageHeader.style.boxShadow = (window.scrollY > 10) ? '0 4px 10px rgba(0,0,0,0.05)' : '0 2px 10px rgba(0,0,0,0.05)';
            });
        }
        function setActiveNavLink() {
            const navLinks = document.querySelectorAll('.nav-links a:not(.btn), .mobile-links a:not(.btn)');
            const currentPath = window.location.pathname.split('/').pop();
            const navLoginBtn = document.querySelector('.nav-item-login a.btn'); 
            const mobileLoginBtn = document.querySelector('.mobile-menu a.btn[href="login.php"]');

            navLinks.forEach(link => {
                const linkPath = link.getAttribute('href').split('/').pop().split('#')[0];
                if (link.getAttribute('href').startsWith('index.php#') && currentPath === 'index.php') {
                    link.classList.remove('active-link');
                } else if (linkPath === currentPath && currentPath !== "" && currentPath !== "index.php") {
                    link.classList.add('active-link');
                } else {
                    link.classList.remove('active-link');
                }
            });
            
            if (currentPath === 'register.php') { // Example: if you add a register link and want it active
                // const registerLink = document.querySelector('a[href="register.php"]');
                // if(registerLink) registerLink.classList.add('active-link');
            }

            if (navLoginBtn) navLoginBtn.classList.remove('active-link'); 
            if (mobileLoginBtn) mobileLoginBtn.classList.remove('active-link');
        }
        setActiveNavLink();
    </script>
</body>
</html>