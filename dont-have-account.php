<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WavePass - Account Assistance</title>
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
            --success-color: #4cc9f0; /* Hex for success */
            --success-color-rgb: 76, 201, 240; /* RGB for success */
            --warning-color: #f8961e;
            --danger-color: #f72585; /* Hex for danger */
            --danger-color-rgb: 247, 37, 133; /* RGB for danger */
            --info-color: #54a0ff;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background-color: var(--light-color);
            overflow-x: hidden;
            scroll-behavior: smooth;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        main {
            flex-grow: 1;
            padding-top: 80px;; /* Height of the fixed header */
        }

        h1, h2, h3, h4 {
            font-weight: 700;
            line-height: 1.2;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }


        /* Hamburger Menu */
        .hamburger { display: none; cursor: pointer; width: 30px; height: 24px; position: relative; z-index: 1001; transition: var(--transition); }
        .hamburger span { display: block; width: 100%; height: 3px; background-color: var(--dark-color); position: absolute; left: 0; transition: var(--transition); transform-origin: center; }
        .hamburger span:nth-child(1) { top: 0; }
        .hamburger span:nth-child(2) { top: 50%; transform: translateY(-50%); }
        .hamburger span:nth-child(3) { bottom: 0; }
        .hamburger.active span:nth-child(1) { top: 50%; transform: translateY(-50%) rotate(45deg); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { bottom: 50%; transform: translateY(50%) rotate(-45deg); }

        /* Mobile Menu Panel */
        .mobile-menu { position: fixed; top: 0; left: 0; width: 100%; height: 100vh; background-color: var(--white); z-index: 1000; display: flex; flex-direction: column; align-items: center; transform: translateX(-100%); transition: transform 0.4s cubic-bezier(0.23, 1, 0.32, 1); padding: 70px 1rem 2rem 1rem; overflow-y: auto; }
        .mobile-menu.active { transform: translateX(0); }
        .mobile-links { list-style: none; text-align: left; width: 100%; max-width: 320px; padding: 0; margin-top: 1rem; }
        .mobile-links li { margin-bottom: 0; }
        .mobile-links a { color: var(--dark-color); text-decoration: none; font-weight: 500; font-size: 1.05rem; display: block; padding: 0.9rem 1.2rem; transition: color var(--transition), background-color var(--transition); border-bottom: 1px solid var(--light-gray); border-radius: 0; }
        .mobile-links li:first-child a { border-top: 1px solid var(--light-gray); }
        .mobile-links a:hover { color: var(--primary-color); background-color: rgba(67, 97, 238, 0.07); }
        .mobile-menu .btn, .mobile-menu .btn-outline { margin-top: 1.5rem; width: 100%; max-width: 280px; padding: 0.8rem 1.5rem; font-size: 0.95rem; text-align: center; }
        .mobile-menu .btn .material-symbols-outlined { font-size: 1.2em; vertical-align: middle; margin-right: 4px; }
        .mobile-menu .btn-outline { margin-top: 1rem; }
        .close-btn { position: absolute; top: 25px; right: 25px; font-size: 1.6rem; color: var(--dark-color); cursor: pointer; transition: var(--transition); padding: 0.5rem; line-height: 1; }
        .close-btn:hover { color: var(--primary-color); transform: rotate(90deg); }

        /* Account Assistance Page Styles */
        .account-assistance-section {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 0 4rem;
            flex-grow: 1;
        }
        .back-link-container {
            position: absolute;
            top: calc(80px + 1.5rem);
            left: 1.5rem;
        }
        @media (min-width: 1200px) {
            .back-link-container {
                 left: calc((100vw - 1200px) / 2 + 20px);
            }
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .back-link .material-symbols-outlined {
            font-size: 1.3em;
        }
        .back-link:hover {
            text-decoration: underline;
        }

        .assistance-container {
            background-color: var(--white);
            padding: 2.5rem 3rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 550px; /* Slightly wider than login */
            text-align: center;
            margin-left: auto; /* <-- PŘIDÁNO PRO CENTROVÁNÍ */
            margin-right: auto; /* <-- PŘIDÁNO PRO CENTROVÁNÍ */
        }

        .assistance-container h1 {
            font-size: 1.8rem;
            margin-bottom: 0.8rem;
            color: var(--dark-color);
        }
        .assistance-container .form-subtitle {
            font-size: 0.95rem;
            color: var(--gray-color);
            margin-bottom: 2.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.9rem;
        }
        .form-group input[type="text"],
        .form-group input[type="email"] /* Added email field */
         {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--dark-color);
            transition: border-color var(--transition), box-shadow var(--transition);
            background-color: #fdfdff;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
            background-color: var(--white);
        }

        .assistance-container .btn {
            width: 100%;
            padding: 0.9rem 2rem;
            margin-top: 0.5rem;
        }
        .message-area { /* For displaying success/error messages */
            margin-top: 1.5rem;
            padding: 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            text-align: left;
            display: none; /* Hidden by default */
        }
        .message-area.success {
            background-color: rgba(var(--success-color-rgb), 0.1); /* Použití RGB proměnné */
            color: var(--success-color);
            border-left: 3px solid var(--success-color);
        }
        .message-area.error {
            background-color: rgba(var(--danger-color-rgb), 0.1); /* Použití RGB proměnné */
            color: var(--danger-color);
            border-left: 3px solid var(--danger-color);
        }

        .login-link-prompt {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        .login-link-prompt a {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
        }
        .login-link-prompt a:hover {
            text-decoration: underline;
        }

        /* Footer */
        footer { background-color: var(--dark-color); color: var(--white); padding: 5rem 0 2rem; }
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 3rem; margin-bottom: 3rem; }
        .footer-column h3 { font-size: 1.3rem; margin-bottom: 1.8rem; position: relative; padding-bottom: 0.8rem; }
        .footer-column h3::after { content: ''; position: absolute; left: 0; bottom: 0; width: 50px; height: 3px; background-color: var(--primary-color); border-radius: 3px; }
        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: 0.8rem; }
        .footer-links a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); font-size: 0.95rem; display: inline-block; padding: 0.2rem 0; }
        .footer-links a:hover { color: var(--white); transform: translateX(5px); }
        .footer-links a i { margin-right: 0.5rem; width: 20px; text-align: center; }
        .social-links { display: flex; gap: 1.2rem; margin-top: 1.5rem; }
        .social-links a { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background-color: rgba(255, 255, 255, 0.1); color: var(--white); border-radius: 50%; font-size: 1.1rem; transition: var(--transition); }
        .social-links a:hover { background-color: var(--primary-color); transform: translateY(-3px); }
        .footer-bottom { text-align: center; padding-top: 3rem; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.9rem; color: rgba(255, 255, 255, 0.6); }
        .footer-bottom a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); }
        .footer-bottom a:hover { color: var(--primary-color); }

        /* Scroll to Top Button */
        #scrollToTopBtn { display: none; position: fixed; bottom: 30px; right: 30px; z-index: 999; border: none; outline: none; background-color: var(--primary-color); color: var(--white); cursor: pointer; padding: 0; border-radius: 50%; width: 50px; height: 50px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s ease; opacity: 0; visibility: hidden; transform: translateY(20px); }
        #scrollToTopBtn .material-symbols-outlined { font-size: 24px; line-height: 50px; width: 100%; text-align: center; }
        #scrollToTopBtn:hover { background-color: var(--primary-dark); }
        #scrollToTopBtn.show { display: flex; align-items: center; justify-content: center; opacity: 1; visibility: visible; transform: translateY(0); }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .nav-links { display: none; }
            .hamburger { display: flex; }
            .assistance-container {
                padding: 2rem 1.5rem;
            }
            .back-link-container {
                left: 20px;
            }
        }

        @media (max-width: 768px) {
            .account-assistance-section {
                padding: 2rem 1rem 3rem;
            }
            .assistance-container {
                 max-width: 90%;
            }
            .assistance-container h1 { font-size: 1.6rem; }
            .back-link-container {
                position: static;
                margin-bottom: 1.5rem;
                text-align: left;
                padding-left: 1rem;
            }
        }
         @media (max-width: 576px) {
            .assistance-container {
                padding: 1.5rem 1rem;
            }
            .assistance-container .form-subtitle {
                 margin-bottom: 2rem;
            }
         }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
    <div class="container">
        <nav class="navbar">
            <a href="index.php" class="logo">
                <img src="imgs/logo.png" alt="WavePass Logo" class="logo-img">
                Wave<span>Pass</span>
            </a>
            
            <ul class="nav-links">
                <li><a href="#features">Features</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="#about-us">About Us</a></li>
                <li><a href="#contact">Contact</a></li> 
                <li><a href="#faq">FAQ</a></li> 
                <li><a href="login.php" class="btn"><span aria-hidden="true" translate="no" class="material-symbols-outlined">account_circle</span> Login</a></li>
            </ul>
            
            <div class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </nav>
    </div>
    
    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <span class="close-btn" id="closeMenu"><i class="fas fa-times"></i></span> 
        <ul class="mobile-links">
            <li><a href="#features">Features</a></li>
            <li><a href="#how-it-works">How It Works</a></li>
            <li><a href="#about-us">About Us</a></li>
            <li><a href="#contact">Contact</a></li>
            <li><a href="#faq">FAQ</a></li> 
        </ul>
        <a href="login.php" class="btn"><span aria-hidden="true" translate="no" class="material-symbols-outlined">person</span> Login</a>
    </div>
</header>

    <style>

.logo {
    font-size: 1.8rem; /* This primarily affects the "WavePass" text part now */
    font-weight: 800;
    color: var(--primary-color);
    text-decoration: none;
    display: flex;
    align-items: center;
}

/* .logo i { font-size: 1.5rem; } REMOVED THIS */

.logo-img { /* ADDED THIS */
    height: 50px; /* Adjust to match the desired visual size, approx 1.5rem * 16px/rem ~ 24px, add a bit for visual balance */
    width: auto;  /* Maintain aspect ratio */
    vertical-align: middle; /* Helps align with text if there are minor differences */
}

.logo span { /* This is for the "Pass" part of WavePass */
    color: var(--dark-color); 
    font-weight: 600; 
}
/* The rest of .logo span styling might need adjustment if your image + text "Wave" part differs significantly in height from the text "Pass" */
    </style>

    <!-- Main Content for Account Assistance Page -->
    <main>
    <!--
                <div class="back-link-container">
            <div class="container">
                <a href="login.php" class="back-link">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Back to Login
                </a>
            </div>
        </div>
    !-->

        <section class="account-assistance-section">
            <div class="container">
                <div class="assistance-container">
                    <h1>Account Assistance</h1>
                    <p class="form-subtitle">If your school is registered but you don't have an account, please enter your school's ID and your email. We'll notify the administrator.</p>

                    <form id="accountAssistanceForm" action="process_account_assistance.php" method="POST">
                        <div class="form-group">
                            <label for="school_id">School ID / ICO</label>
                            <input type="text" id="school_id" name="school_id" placeholder="Enter your school's official ID" required>
                        </div>
                        <div class="form-group">
                            <label for="user_email">Your Email Address</label>
                            <input type="email" id="user_email" name="user_email" placeholder="your.email@example.com" required>
                        </div>

                        <button type="submit" class="btn">
                            <span aria-hidden="true" translate="no" class="material-symbols-outlined">contact_support</span> Request Account Setup
                        </button>
                    </form>

                    <div class="message-area" id="assistance-message-area">
                        {/* Messages will be displayed here by JavaScript */}
                    </div>

                    <p class="login-link-prompt" style="margin-top: 1.5rem;">
                        If your school is not yet registered, please <a href="get-started.php">Get Started here</a>.
                    </p>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <?php  require_once "components/footer-main.php"; ?>

    <!-- Scroll to Top Button -->
    <button id="scrollToTopBtn" title="Go to top">
        <span aria-hidden="true" translate="no" class="material-symbols-outlined">arrow_upward</span>
    </button>

    <script>

        // Smooth scrolling for on-page anchors
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                if (this.getAttribute('href') === '#') return;

                const targetId = this.getAttribute('href');
                if (targetId.startsWith('#') && document.querySelector(targetId)) {
                    e.preventDefault();
                    const targetElement = document.querySelector(targetId);
                    const headerHeight = document.querySelector('header') ? document.querySelector('header').offsetHeight : 0;
                    const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - headerHeight;

                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Add shadow to header on scroll
        const header = document.querySelector('header');
        if (header) {
            window.addEventListener('scroll', () => {
                if (window.scrollY > 10) {
                    header.style.boxShadow = '0 4px 10px rgba(0, 0, 0, 0.05)';
                } else {
                    header.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.05)';
                }
            });
        }

        // Account Assistance Form
        const assistanceForm = document.getElementById('accountAssistanceForm');
        const messageArea = document.getElementById('assistance-message-area');

        if (assistanceForm && messageArea) {
            assistanceForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent actual submission for this demo

                const schoolId = document.getElementById('school_id').value;
                const userEmail = document.getElementById('user_email').value;

                // --- SIMULATE BACKEND LOGIC ---
                // In a real application, you would send 'schoolId' and 'userEmail' to your server.
                // The server would check if schoolId exists.
                console.log('Requesting assistance for School ID:', schoolId, 'and Email:', userEmail);

                // Example: Simulate server response
                let responseFromServer = { status: '', message: '' };

                // This is a very basic simulation.
                // A real backend would query a database.
                if (schoolId.trim() === "") { // Check if schoolId is empty or just whitespace
                     responseFromServer.status = 'error';
                    responseFromServer.message = 'Please enter a School ID.';
                } else if (userEmail.trim() === "" || !userEmail.includes('@')) { // Basic email validation
                    responseFromServer.status = 'error';
                    responseFromServer.message = 'Please enter a valid Email Address.';
                }
                else if (schoolId === "EXISTING_SCHOOL_ID_123") { // Replace with actual check
                    responseFromServer.status = 'success';
                    responseFromServer.message = 'Thank you! If your school is registered with this ID, the administrator has been notified to assist you with account setup.';
                } else { // Assume other school IDs are not found for this demo
                    responseFromServer.status = 'error';
                    responseFromServer.message = 'The School ID provided does not seem to be registered in our system. Please double-check the ID or contact your school administrator. If your school is new to WavePass, please use the "Get Started" page to register.';
                }
                // --- END SIMULATION ---

                messageArea.textContent = responseFromServer.message;
                messageArea.className = 'message-area'; // Reset classes
                if (responseFromServer.status === 'success') {
                    messageArea.classList.add('success');
                    assistanceForm.reset(); // Clear form on success
                } else {
                    messageArea.classList.add('error');
                }
                messageArea.style.display = 'block';
            });
        }


        // Scroll to Top Button Functionality
        const scrollToTopBtn = document.getElementById("scrollToTopBtn");
        if (scrollToTopBtn) {
            window.onscroll = function() { scrollFunction(); };
            function scrollFunction() {
                if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
                    scrollToTopBtn.classList.add("show");
                } else {
                    scrollToTopBtn.classList.remove("show");
                }
            }
            scrollToTopBtn.addEventListener("click", function() {
                window.scrollTo({ top: 0, behavior: "smooth" });
            });
        }
    </script>
</body>
</html>