<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WavePass - Reset Password</title>
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
            --success-color: #4cc9f0;
            --warning-color: #f8961e;
            --danger-color: #f72585;
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

        /* Header & Navigation (Consistent) */
        header {
            background-color: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: var(--transition);
        }
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            height: 80px;
        }
        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .logo i { font-size: 1.5rem; }
        .logo span { color: var(--dark-color); font-weight: 600; }
        .nav-links {
            display: flex;
            list-style: none;
            align-items: center;
            gap: 0.5rem;
        }
        .nav-links a:not(.btn) {
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 500;
            transition: color var(--transition), background-color var(--transition);
            padding: 0.7rem 1rem;
            font-size: 0.95rem;
            border-radius: 8px;
            position: relative;
        }
        .nav-links a:not(.btn):hover {
            color: var(--primary-color);
            background-color: rgba(67, 97, 238, 0.07);
        }
        .nav-links a:not(.btn)::after { display: none; }
        .nav-links .btn,
        .nav-links .btn-outline {
            display: inline-flex; gap: 8px; align-items: center; justify-content: center;
            padding: 0.7rem 1.5rem; border-radius: 8px; text-decoration: none;
            font-weight: 600; transition: var(--transition); cursor: pointer;
            text-align: center; font-size: 0.9rem;
        }
        .nav-links .btn {
            background-color: var(--primary-color); color: var(--white);
            box-shadow: 0 4px 14px rgba(67, 97, 238, 0.2);
        }
        .nav-links .btn .material-symbols-outlined {
            font-size: 1.2em; vertical-align: middle; margin-right: 4px;
        }
        .nav-links .btn:hover{
            background-color: var(--primary-dark);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.3);
            transform: translateY(-2px);
        }
        .nav-links .btn-outline {
            background-color: transparent; border: 2px solid var(--primary-color);
            color: var(--primary-color); box-shadow: none;
        }
        .nav-links .btn-outline:hover {
            background-color: var(--primary-color); color: var(--white);
            transform: translateY(-2px);
        }

        /* General Button Styles */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 0.8rem 2rem; background-color: var(--primary-color); color: var(--white);
            border: none; border-radius: 8px; text-decoration: none; font-weight: 600;
            transition: var(--transition); cursor: pointer; text-align: center;
            box-shadow: 0 4px 14px rgba(67, 97, 238, 0.3); font-size: 0.95rem;
        }
        .btn:hover {
            background-color: var(--primary-dark);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
            transform: translateY(-2px);
        }
        .btn-outline {
            background-color: transparent; border: 2px solid var(--primary-color);
            color: var(--primary-color); box-shadow: none;
        }
        .btn-outline:hover {
            background-color: var(--primary-color); color: var(--white);
            transform: translateY(-2px);
        }
        .btn .material-symbols-outlined, .btn .fas {
            margin-right: 6px;
            font-size: 1.1em;
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

        /* Password Reset Page Specific Styles */
        .password-reset-section {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 0 4rem; /* Adjusted padding */
            flex-grow: 1;
        }
        .back-link-container { /* Re-using if needed, or place directly */
            position: absolute; /* Position it relative to the viewport or a parent */
            top: calc(80px + 1.5rem); /* Below header + some margin */
            left: 1.5rem; /* From the left edge */
        }
        @media (min-width: 1200px) { /* For larger screens, align with container */
            .back-link-container {
                 left: calc((100vw - 1200px) / 2 + 20px); /* Center relative to container */
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


        .password-reset-container {
            background-color: var(--white);
            padding: 2.5rem 3rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 480px; /* Max width of the reset box */
            text-align: center;
            margin-left: auto; /* <-- PŘIDÁNO PRO CENTROVÁNÍ */
            margin-right: auto; /* <-- PŘIDÁNO PRO CENTROVÁNÍ */
        }

        .password-reset-container h1 {
            font-size: 1.8rem;
            margin-bottom: 0.8rem;
            color: var(--dark-color);
        }
        .password-reset-container .form-subtitle {
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
        .form-group input[type="email"] {
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
        .form-group input[type="email"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
            background-color: var(--white);
        }

        .password-reset-container .btn {
            width: 100%;
            padding: 0.9rem 2rem;
            margin-top: 0.5rem;
        }
        .info-message {
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--gray-color);
            background-color: rgba(var(--primary-color), 0.05); /* Chybný zápis proměnné, opraveno níže pokud by se mělo použít */
            /* background-color: rgba(67, 97, 238, 0.05); /* Správně pokud chcete použít --primary-color */
            padding: 0.8rem 1rem;
            border-radius: 6px;
            border-left: 3px solid var(--primary-color);
            text-align: left;
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
            .password-reset-container {
                padding: 2rem 1.5rem;
            }
            .back-link-container {
                left: 20px; /* Adjust for general container padding */
            }
        }

        @media (max-width: 768px) {
            .password-reset-section {
                padding: 2rem 1rem 3rem;
            }
            .password-reset-container {
                 max-width: 90%;
            }
            .password-reset-container h1 { font-size: 1.6rem; }
            .back-link-container {
                position: static; /* Let it flow naturally */
                margin-bottom: 1.5rem;
                text-align: left; /* Align to left */
                padding-left: 1rem; /* Match container padding if needed */
            }
        }
         @media (max-width: 576px) {
            .password-reset-container {
                padding: 1.5rem 1rem;
            }
            .password-reset-container .form-subtitle {
                 margin-bottom: 2rem;
            }
         }
    </style>
</head>
<body>
    <!-- Header -->
    <?php require_once "components/header.php" ?>

    <!-- Main Content for Password Reset Page -->
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

        <section class="password-reset-section">
            <div class="container">
                <div class="password-reset-container">
                    <h1>Reset Your Password</h1>
                    <p class="form-subtitle">Enter your email address below, and we'll send you a link to reset your password.</p>

                    <form action="process_password_reset_request.php" method="POST">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" placeholder="you@example.com" required>
                        </div>

                        <button type="submit" class="btn"><span class="material-symbols-outlined">mail</span> Send Reset Link</button>
                    </form>

                    <div class="info-message" id="reset-info-message" style="display: none; background-color: rgba(67, 97, 238, 0.05);">
                        {/* This message can be shown via JS after form submission attempt */}
                        If an account with that email exists, a password reset link has been sent. Please check your inbox (and spam folder).
                    </div>

                    <p class="login-link-prompt" style="margin-top: 1.5rem;">
                        Remembered your password? <a href="login.php">Login here</a>.
                    </p>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
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
                        <li><a href="index.html#features"><i class="fas fa-chevron-right"></i> Features</a></li>
                        <li><a href="index.html#how-it-works"><i class="fas fa-chevron-right"></i> How It Works</a></li>
                        <li><a href="pricing.html"><i class="fas fa-chevron-right"></i> Pricing</a></li>
                        <li><a href="index.html#contact"><i class="fas fa-chevron-right"></i> Contact</a></li>
                        <li><a href="index.html#faq"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                        <li><a href="help.html"><i class="fas fa-chevron-right"></i> Help Center</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h3>Resources</h3>
                    <ul class="footer-links">
                        <li><a href="blog.php"><i class="fas fa-chevron-right"></i> Blog</a></li>
                        <li><a href="help.html"><i class="fas fa-chevron-right"></i> Help Center</a></li>
                        <li><a href="webinars.php"><i class="fas fa-chevron-right"></i> Webinars</a></li>
                        <li><a href="api.php"><i class="fas fa-chevron-right"></i> API Documentation</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul class="footer-links">
                        <li><a href="mailto:info@WavePass.com"><i class="fas fa-envelope"></i> info@WavePass.com</a></li>
                        <li><a href="tel:+15551234567"><i class="fas fa-phone"></i> +1 (555) 123-4567</a></li>
                        <li>
                             <a href="https://www.google.com/maps/search/?api=1&query=123%20Education%20St%2C%20Boston%2C%20MA%2002115" target="_blank" rel="noopener noreferrer" title="View on Google Maps">
                                <i class="fas fa-map-marker-alt"></i> 123 Education St, Boston, MA
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>© <?php echo date("Y"); ?> WavePass. All rights reserved. | <a href="privacy.php">Privacy Policy</a> | <a href="terms.php">Terms of Service</a></p>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button id="scrollToTopBtn" title="Go to top">
        <span class="material-symbols-outlined">arrow_upward</span>
    </button>

    <script>
        // Mobile Menu Toggle
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

            const mobileNavLinks = document.querySelectorAll('.mobile-menu a');
            mobileNavLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (link.getAttribute('href').startsWith('#') || link.getAttribute('href').startsWith('index.html#') || link.classList.contains('btn')) {
                        hamburger.classList.remove('active');
                        mobileMenu.classList.remove('active');
                        body.style.overflow = '';
                    }
                });
            });
        }

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

        // Password Reset Form
        const passwordResetForm = document.querySelector('.password-reset-container form');
        const infoMessageDiv = document.getElementById('reset-info-message');

        if (passwordResetForm && infoMessageDiv) {
            passwordResetForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent actual form submission for demo

                const emailInput = document.getElementById('email');
                if (emailInput.checkValidity()) { // Basic HTML5 email validation
                    // Simulate sending request
                    console.log('Password reset requested for:', emailInput.value);

                    // Show info message
                    infoMessageDiv.style.display = 'block';
                    // Optionally, disable the button or change its text
                    // this.querySelector('button[type="submit"]').disabled = true;
                    // this.querySelector('button[type="submit"]').textContent = 'Sending...';

                    // Clear form after a delay or on success (for demo, just show message)
                    // setTimeout(() => {
                    //     passwordResetForm.reset();
                    //     infoMessageDiv.style.display = 'none';
                    //     this.querySelector('button[type="submit"]').disabled = false;
                    //     this.querySelector('button[type="submit"]').innerHTML = '<span class="material-symbols-outlined">mail</span> Send Reset Link';
                    // }, 5000); // Hide message after 5 seconds for demo
                } else {
                    // Let browser handle showing validation error for email
                    emailInput.reportValidity();
                }
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