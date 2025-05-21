<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// --- Authorization Check ---
// Redirect if user is not logged in or is not an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
if ($_SESSION["role"] !== 'admin') {
    // You could redirect to dashboard with an error message
    // For simplicity, just showing an error here.
    die("ACCESS DENIED: You do not have permission to view this page.");
}
// --- End Authorization Check ---


$username_err = $password_err = $email_err = $firstName_err = $lastName_err = $roleID_err = $rfid_err = $phone_err = "";
$username = $email = $firstName = $lastName = $roleID = $rfid = $phone = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        // Check if username is already taken
        $sql_check_username = "SELECT userID FROM users WHERE username = :username";
        if ($stmt_check = $pdo->prepare($sql_check_username)) {
            $stmt_check->bindParam(":username", trim($_POST["username"]), PDO::PARAM_STR);
            if ($stmt_check->execute()) {
                if ($stmt_check->rowCount() == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Oops! Something went wrong with username check. Please try again later.";
            }
            unset($stmt_check);
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate first name
    if (empty(trim($_POST["firstName"]))) {
        $firstName_err = "Please enter a first name.";
    } else {
        $firstName = trim($_POST["firstName"]);
    }

    // Validate last name
    if (empty(trim($_POST["lastName"]))) {
        $lastName_err = "Please enter a last name.";
    } else {
        $lastName = trim($_POST["lastName"]);
    }

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email address.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Check if email is already taken
        $sql_check_email = "SELECT userID FROM users WHERE email = :email";
        if ($stmt_check_email = $pdo->prepare($sql_check_email)) {
            $stmt_check_email->bindParam(":email", trim($_POST["email"]), PDO::PARAM_STR);
            if ($stmt_check_email->execute()) {
                if ($stmt_check_email->rowCount() == 1) {
                    $email_err = "This email address is already registered.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Oops! Something went wrong with email check. Please try again later.";
            }
            unset($stmt_check_email);
        }
    }
    
    // Validate Role ID
    $allowed_roles = ['employee', 'admin']; // Define allowed roles
    if (empty(trim($_POST["roleID"]))) {
        $roleID_err = "Please select a role.";
    } elseif (!in_array(trim($_POST["roleID"]), $allowed_roles)) {
        $roleID_err = "Invalid role selected.";
    } else {
        $roleID = trim($_POST["roleID"]);
    }

    // RFID (Optional)
    $rfid = trim($_POST["rfid"]);
    if (!empty($rfid)) {
        // Optional: Add validation for RFID format if needed
        // Check if RFID is already taken (if it must be unique)
        $sql_check_rfid = "SELECT userID FROM users WHERE RFID = :rfid";
        if ($stmt_check_rfid = $pdo->prepare($sql_check_rfid)) {
            $stmt_check_rfid->bindParam(":rfid", $rfid, PDO::PARAM_STR);
            if ($stmt_check_rfid->execute()) {
                if ($stmt_check_rfid->rowCount() > 0) {
                    $rfid_err = "This RFID tag is already assigned.";
                }
            } else {
                 echo "Oops! Something went wrong with RFID check. Please try again later.";
            }
            unset($stmt_check_rfid);
        }
    }


    // Phone (Optional)
    $phone = trim($_POST["phone"]);
    // Optional: Add validation for phone format if needed

    // If there are no errors, proceed to insert into database
    if (empty($username_err) && empty($password_err) && empty($email_err) && empty($firstName_err) && empty($lastName_err) && empty($roleID_err) && empty($rfid_err)) {
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $sql_insert = "INSERT INTO users (username, password, firstName, lastName, email, roleID, RFID, phone, absence) 
                       VALUES (:username, :password, :firstName, :lastName, :email, :roleID, :rfid, :phone, 0)";
        
        if ($stmt_insert = $pdo->prepare($sql_insert)) {
            $stmt_insert->bindParam(":username", $username, PDO::PARAM_STR);
            $stmt_insert->bindParam(":password", $hashed_password, PDO::PARAM_STR);
            $stmt_insert->bindParam(":firstName", $firstName, PDO::PARAM_STR);
            $stmt_insert->bindParam(":lastName", $lastName, PDO::PARAM_STR);
            $stmt_insert->bindParam(":email", $email, PDO::PARAM_STR);
            $stmt_insert->bindParam(":roleID", $roleID, PDO::PARAM_STR); // ENUM is treated as string
            
            // Bind optional fields carefully
            $param_rfid = !empty($rfid) ? $rfid : null;
            $stmt_insert->bindParam(":rfid", $param_rfid, PDO::PARAM_STR);
            
            $param_phone = !empty($phone) ? $phone : null;
            $stmt_insert->bindParam(":phone", $param_phone, PDO::PARAM_STR);

            if ($stmt_insert->execute()) {
                $success_message = "Employee added successfully! Username: " . htmlspecialchars($username);
                // Clear form fields after successful insertion
                $username = $email = $firstName = $lastName = $roleID = $rfid = $phone = ""; 
            } else {
                echo "Something went wrong with database insertion. Please try again later.";
            }
            unset($stmt_insert);
        }
    }
    // Close connection (not strictly necessary for PDO with script termination, but good practice in some contexts)
    // unset($pdo);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee - WavePass Admin</title>
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
             --success-color: #4caf50; /* Green for success */
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: var(--light-color); display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 100px; /* Adjusted for fixed header + page title */ }
        .container { max-width: 800px; margin: 0 auto; padding: 0 20px; }

        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; height: 80px; max-width: 1400px; margin: 0 auto; padding: 0 20px;}
        .logo { font-size: 1.8rem; font-weight: 800; color: var(--primary-color); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; }
        .logo i { font-size: 1.5rem; }
        .logo span { color: var(--dark-color); font-weight: 600; }
        .nav-links { display: flex; list-style: none; align-items: center; gap: 0.5rem; }
        .nav-links a { color: var(--dark-color); text-decoration: none; font-weight: 500; padding: 0.7rem 1rem; font-size: 0.95rem; border-radius: 8px; position: relative; transition: color .3s ease, background-color .3s ease; }
        .nav-links a:hover, .nav-links a.active-link { color: var(--primary-color); background-color: rgba(67,97,238,0.07); }
        .nav-links .btn { display: inline-flex; gap: 8px; align-items: center; justify-content: center; padding: 0.7rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: var(--transition); cursor: pointer; font-size: 0.9rem; background-color: var(--primary-color); color: var(--white); box-shadow: 0 4px 14px rgba(67,97,238,0.2); }
        .nav-links .btn .material-symbols-outlined { font-size: 1.2em; vertical-align: middle; margin-right: 4px; }
        .nav-links .btn:hover { background-color: var(--primary-dark); box-shadow: 0 6px 20px rgba(67,97,238,0.3); transform: translateY(-2px); }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0.8rem 2rem; background-color: var(--primary-color); color: var(--white); border: none; border-radius: 8px; text-decoration: none; font-weight: 600; transition: var(--transition); cursor: pointer; box-shadow: 0 4px 14px rgba(67,97,238,0.3); font-size: 0.95rem; }
        .btn i, .btn .material-symbols-outlined { margin-right: 6px; font-size: 1.1em; }
        .btn:hover { background-color: var(--primary-dark); box-shadow: 0 6px 20px rgba(67,97,238,0.4); transform: translateY(-2px); }
        
        .page-title-bar { padding: 1.5rem 0; background-color: var(--white); border-bottom: 1px solid var(--light-gray); margin-bottom: 2rem; }
        .page-title-bar h1 { font-size: 1.8rem; color: var(--dark-color); margin: 0; }

        .form-container { background-color: var(--white); padding: 2.5rem; border-radius: 12px; box-shadow: var(--shadow); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.6rem; font-weight: 600; color: var(--dark-color); font-size: 0.9rem; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group select {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--dark-color);
            transition: border-color .3s ease, box-shadow .3s ease;
            background-color: #fdfdff;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67,97,238,0.15);
            background-color: var(--white);
        }
        .form-error { color: var(--danger-color); font-size: 0.85rem; margin-top: 0.3rem; }
        .success-message {
            background-color: rgba(var(--success-color), 0.15); /* Use RGB for alpha */
            color: var(--success-color);
            border: 1px solid rgba(var(--success-color), 0.3);
            padding: 0.8rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: left;
        }
        .success-message i { margin-right: 0.5rem; }


        .hamburger { display: none; } /* No hamburger for admin pages by default, or implement as needed */
        @media (max-width: 768px) {
            /* If you want a hamburger for admin pages, define its styles here */
            .page-title-bar h1 { font-size: 1.5rem; }
            .form-container { padding: 1.5rem; }
        }

    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="dashboard.php" class="logo">
                    <i class="fas fa-user-shield"></i> <!-- Admin-like icon -->
                    Wave<span>Pass Admin</span>
                </a>
                <ul class="nav-links">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="add_employee.php" class="active-link">Add Employee</a></li>
                    <li><a href="view_employees.php">View Employees</a></li> 
                    <li><a href="logout.php" class="btn"><span class="material-symbols-outlined">logout</span> Logout</a></li>
                </ul>
                <!-- Hamburger can be added here if needed for admin panel responsiveness -->
            </nav>
        </div>
    </header>

    <main>
        <div class="page-title-bar">
            <div class="container">
                <h1>Add New Employee</h1>
            </div>
        </div>

        <div class="container">
            <div class="form-container">
                <?php if(!empty($success_message)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" required>
                        <span class="form-error"><?php echo $username_err; ?></span>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" required>
                        <span class="form-error"><?php echo $password_err; ?></span>
                    </div>

                    <div class="form-group">
                        <label for="firstName">First Name</label>
                        <input type="text" name="firstName" id="firstName" value="<?php echo htmlspecialchars($firstName); ?>" required>
                        <span class="form-error"><?php echo $firstName_err; ?></span>
                    </div>

                    <div class="form-group">
                        <label for="lastName">Last Name</label>
                        <input type="text" name="lastName" id="lastName" value="<?php echo htmlspecialchars($lastName); ?>" required>
                        <span class="form-error"><?php echo $lastName_err; ?></span>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        <span class="form-error"><?php echo $email_err; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="roleID">Role</label>
                        <select name="roleID" id="roleID" required>
                            <option value="">Select Role...</option>
                            <option value="employee" <?php echo ($roleID == 'employee') ? 'selected' : ''; ?>>Employee</option>
                            <option value="admin" <?php echo ($roleID == 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                        <span class="form-error"><?php echo $roleID_err; ?></span>
                    </div>

                    <div class="form-group">
                        <label for="rfid">RFID Tag (Optional)</label>
                        <input type="text" name="rfid" id="rfid" value="<?php echo htmlspecialchars($rfid); ?>">
                        <span class="form-error"><?php echo $rfid_err; ?></span>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone (Optional)</label>
                        <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($phone); ?>">
                        <span class="form-error"><?php echo $phone_err; ?></span>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn"><i class="fas fa-user-plus"></i> Add Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <!-- You can include a common footer if you have one -->
    <!-- <footer> <div class="container"><p>© WavePass Admin</p></div> </footer> -->

    <script>
        // Basic JS for admin panel if needed, e.g., mobile menu for admin header
        // For now, keeping it minimal as the main login page script is not directly applicable here.
    </script>
</body>
</html>