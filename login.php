<?php
// --- SESSION MANAGEMENT ---
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- DATABASE CONNECTION ---
require_once 'db.php'; // Ensure this path is correct and db.php works

// --- INITIALIZE VARIABLES ---
$login_error = '';
$email_value = ''; // To retain email in form on error

// --- REDIRECT IF ALREADY LOGGED IN ---
if (isset($_SESSION['user_id']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    // Check role and redirect accordingly
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') { // Check if role is set and is 'admin'
        header("Location: admin-dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retain email value for the form
    if(isset($_POST["email"])) {
        $email_value = htmlspecialchars(trim($_POST["email"]));
    }

    // Validate email and password presence
    if (empty(trim($_POST["email"])) || empty(trim($_POST["password"]))) {
        $login_error = "Please enter both email and password.";
    } else {
        $email = trim($_POST["email"]);
        $password_attempt = trim($_POST["password"]);

        try {
            // Prepare SQL to prevent SQL injection
            $sql = "SELECT userID, username, password, firstName, lastName, roleID, email FROM users WHERE email = :email LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();

            // Check if email exists
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch as associative array

                // Verify password
                // $user['password'] MUST contain a hash created by password_hash()
                if (password_verify($password_attempt, $user['password'])) {
                    // Password is correct, start a new session and store user data
                    
                    // Regenerate session ID for security before setting session data
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['userID'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['first_name'] = $user['firstName'];
                    $_SESSION['last_name'] = $user['lastName'];
                    $_SESSION['role'] = $user['roleID']; // 'admin' or 'employee'
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['loggedin'] = true;

                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit;
                } else {
                    // Password is not valid
                    $login_error = "Incorrect password. Please try again.";
                }
            } else {
                // No account found with that email
                $login_error = "No account found with that email address.";
            }
        } catch (PDOException $e) {
            // Log the detailed error to a server log file, not to the user
            // error_log("Login PDOException: " . $e->getMessage());
            $login_error = "Something went wrong. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WavePass - Login</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a56d4;
            --secondary-color: #3f37c9;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --gray-color: #6c757d;
            --light-gray: #e9ecef;
            --white: #ffffff;
            --success-color: #4cc9f0; /* Consider a more standard green: #28a745 */
            --warning-color: #f8961e;
            --danger-color: #f72585; /* Consider a more standard red: #dc3545 */
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: var(--light-color); display: flex; flex-direction: column; min-height: 100vh; overflow-x: hidden; }
        main { flex-grow: 1; /* Account for fixed header */ }
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
        .hamburger span:nth-child(1) { top: 0; }
        .hamburger span:nth-child(2) { top: 50%; transform: translateY(-50%); }
        .hamburger span:nth-child(3) { bottom: 0; }
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
        .login-section { display: flex; align-items: center; justify-content: center; padding: 4rem 0; flex-grow: 1; margin-top: 80px; /* ensure no overlap with fixed header */}
        .login-container { background-color: var(--white); padding: 2.5rem 3rem; border-radius: 12px; box-shadow: var(--shadow); width: 100%; max-width: 450px; text-align: center; }
        .login-container h1 { font-size: 1.8rem; margin-bottom: 0.8rem; color: var(--dark-color); }
        .login-container .login-subtitle { font-size: 0.95rem; color: var(--gray-color); margin-bottom: 1.5rem; }
        .login-error-message { background-color: rgba(247,37,133,0.1); color: var(--danger-color); border: 1px solid rgba(247,37,133,0.3); padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: left; display: flex; align-items: center; }
        .login-error-message i { margin-right: 0.5rem; font-size: 1.1em; }
        .form-group { margin-bottom: 1.5rem; text-align: left; }
        .form-group label { display: block; margin-bottom: 0.6rem; font-weight: 600; color: var(--dark-color); font-size: 0.9rem; }
        .form-group input[type="email"], .form-group input[type="password"] { width: 100%; padding: 0.9rem 1.2rem; border: 1px solid var(--light-gray); border-radius: 8px; font-size: 0.95rem; font-family: inherit; color: var(--dark-color); transition: border-color .3s ease, box-shadow .3s ease; background-color: #fdfdff; }
        .form-group input[type="email"]:focus, .form-group input[type="password"]:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(67,97,238,0.15); background-color: var(--white); }
        .login-container .btn { width: 100%; padding: 0.9rem 2rem; margin-top: 0.5rem; }
        .login-options { margin-top: 1.5rem; font-size: 0.85rem; }
        .login-options a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .login-options a:hover { text-decoration: underline; }
        .login-options .separator { color: var(--gray-color); margin: 0 0.5rem; }
        footer { background-color: var(--dark-color); color: var(--white); padding: 5rem 0 2rem; flex-shrink: 0; /* Prevent footer from overlapping content */ }
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
        @media (max-width: 768px) { .nav-links { display: none; } .hamburger { display: flex; } .login-section { padding-top: 2rem; margin-top: 80px; } .login-container { padding: 2rem 1.5rem; max-width: 90%; } .login-container h1 { font-size: 1.6rem; } }
        @media (max-width: 576px) { .login-container { padding: 1.5rem 1rem; } .login-container .login-subtitle { margin-bottom: 1.5rem; } .login-error-message { font-size: 0.85rem; padding: 0.7rem 0.9rem; } }
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
                    <li class="nav-item-login"><a href="login.php" class="btn active-link"><span class="material-symbols-outlined">account_circle</span> Login</a></li>
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
            <a href="login.php" class="btn active-link"><span class="material-symbols-outlined">person</span> Login</a>
        </div>
    </header>

    <main>
        <section class="login-section">
            <div class="login-container">
                <h1>Welcome Back!</h1>
                <p class="login-subtitle">Please enter your credentials to access your dashboard.</p>
                <?php if (!empty($login_error)): ?>
                    <div class="login-error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($login_error); ?>
                    </div>
                <?php endif; ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" novalidate>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required value="<?php echo $email_value; ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> Login</button>
                </form>
                <div class="login-options">
                    <a href="forgot-password.php">Forgot Password?</a>
                    <span class="separator">|</span>
                    <a href="register.php">Don't have an account?</a>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <?php  require_once "components/footer.php"; ?>

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
            if (currentPath === 'login.php') {
                if (navLoginBtn) navLoginBtn.classList.add('active-link');
                if (mobileLoginBtn) mobileLoginBtn.classList.add('active-link');
            } else {
                if (navLoginBtn) navLoginBtn.classList.remove('active-link');
                if (mobileLoginBtn) mobileLoginBtn.classList.remove('active-link');
            }
        }
        setActiveNavLink();
    </script>
</body>
</html>