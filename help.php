<?php
require_once "db.php"; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WavePass - Help Center</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=person" />
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Improved Header & Navigation */
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
            gap: 0.5rem; /* Reduced gap to account for padding on links */
            transition: var(--transition);
        }

        /* Desktop Navigation Links (non-buttons) */
        .nav-links a:not(.btn) {
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 500; /* Standard weight */
            transition: color var(--transition), background-color var(--transition);
            padding: 0.7rem 1rem; /* Padding for clickable area and background */
            font-size: 0.95rem;
            border-radius: 8px; /* Rounded corners for the hover background */
            position: relative; /* Keep for potential future pseudo-elements if needed */
            transition: var(--transition);
        }
        .nav-links a:not(.btn):hover {
            color: var(--primary-color);
            background-color: rgba(67, 97, 238, 0.07); /* Light primary background on hover */
        }
        .nav-links a:not(.btn)::after { /* Ensure no underline by default or on hover */
            display: none;
        }
        
        /* Desktop Navigation Buttons (.btn, .btn-outline) */
        .nav-links .btn,
        .nav-links .btn-outline {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
            padding: 0.7rem 1.5rem; 
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            text-align: center;
            font-size: 0.9rem; 
        }
        .nav-links .btn {
            background-color: var(--primary-color);
            color: var(--white);
            box-shadow: 0 4px 14px rgba(67, 97, 238, 0.2);
        }
        .nav-links .btn .material-symbols-outlined { /* Icon styling within nav button */
            font-size: 1.2em; /* Adjust icon size */
            vertical-align: middle; /* Align icon nicely with text */
            margin-right: 4px; /* Space between icon and text */
        }
        .nav-links .btn:hover{
            background-color: var(--primary-dark);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.3);
            transform: translateY(-2px);
        }
        .nav-links .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            box-shadow: none;
        }
        .nav-links .btn-outline:hover {
            background-color: var(--primary-color);
            color: var(--white);
            transform: translateY(-2px);
        }


        /* General Button Styles (used elsewhere on the page) */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.8rem 2rem;
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            text-align: center;
            box-shadow: 0 4px 14px rgba(67, 97, 238, 0.3);
            font-size: 0.95rem;
        }
        .btn:hover {
            background-color: var(--primary-dark);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
            transform: translateY(-2px);
        }
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            box-shadow: none;
        }
        .btn-outline:hover { 
            background-color: var(--primary-color); 
            color: var(--white); 
            transform: translateY(-2px);
        }

        /* Hamburger Menu Icon */
        .hamburger { display: none; cursor: pointer; width: 30px; height: 24px; position: relative; z-index: 1001; transition: var(--transition); }
        .hamburger span { display: block; width: 100%; height: 3px; background-color: var(--dark-color); position: absolute; left: 0; transition: var(--transition); transform-origin: center; }
        .hamburger span:nth-child(1) { top: 0; }
        .hamburger span:nth-child(2) { top: 50%; transform: translateY(-50%); }
        .hamburger span:nth-child(3) { bottom: 0; }
        .hamburger.active span:nth-child(1) { top: 50%; transform: translateY(-50%) rotate(45deg); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { bottom: 50%; transform: translateY(50%) rotate(-45deg); }

        /* Mobile Menu */
        .mobile-menu {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background-color: var(--white);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transform: translateX(-100%);
            transition: transform 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            padding: 2rem;
        }

        .mobile-menu.active {
            transform: translateX(0);
        }

        .mobile-links {
            list-style: none;
            text-align: center;
            width: 100%;
            max-width: 300px;
        }

        .mobile-links li {
            margin-bottom: 1.5rem;
        }

        .mobile-links a {
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.2rem;
            display: inline-block;
            padding: 0.5rem 1rem;
            transition: var(--transition);
            border-radius: 8px;
        }

        .mobile-links a:hover {
            color: var(--primary-color);
            background-color: rgba(67, 97, 238, 0.1);
        }

        .mobile-menu .btn {
            margin-top: 2rem;
            width: 100%;
            max-width: 200px;
        }

        .close-btn {
            position: absolute;
            top: 30px;
            right: 30px;
            font-size: 1.8rem;
            color: var(--dark-color);
            cursor: pointer;
            transition: var(--transition);
        }

        .close-btn:hover {
            color: var(--primary-color);
            transform: rotate(90deg);
        }

        /* Section General Styles */
        .section {
            padding: 4rem 0; /* Adjusted default padding for content pages */
        }
        .section-title {
            text-align: center;
            margin-bottom: 3rem; /* Adjusted margin */
        }
        .section-title h1 { /* For main page title */
            font-size: 2.5rem;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        .section-title h2 { /* For sub-section titles */
            font-size: 2rem;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        .section-title p {
            color: var(--gray-color);
            max-width: 700px;
            margin: 0 auto;
            font-size: 1.1rem;
        }

        /* FAQ Section Styles (Reused from index) */
        .faq-section { /* This class can be used for the common problems section */
            background-color: var(--light-color); /* Or var(--white) depending on preference */
        }
        .faq-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .faq-item {
            background-color: var(--white);
            margin-bottom: 1rem;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--light-gray); 
        }
        .faq-item:hover { box-shadow: 0 6px 25px rgba(0, 0, 0, 0.1); }
        .faq-question {
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-weight: 600;
            color: var(--dark-color);
            transition: var(--transition);
        }
        .faq-question:hover { color: var(--primary-color); background-color: rgba(67, 97, 238, 0.03); }
        .faq-question i { transition: var(--transition); }
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.35s ease-out; 
            padding: 0 1.5rem;
            background-color: rgba(67, 97, 238, 0.03); 
        }
        .faq-answer-content { padding: 1.5rem 0; color: var(--gray-color); line-height: 1.6; font-size: 0.95rem; }
        .faq-item.active .faq-question i { transform: rotate(180deg); }
        .faq-item.active .faq-question { color: var(--primary-color); }


        /* Support Form Section Styles */
        .support-form-section {
            background-color: var(--white); /* Or --light-color for contrast */
        }
        .support-form-container {
            background-color: var(--white);
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            max-width: 700px; /* Max width of the support form box */
            margin: 0 auto; /* Center the form container */
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
        .form-group input[type="email"],
        .form-group input[type="file"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--dark-color);
            transition: border-color var(--transition), box-shadow var(--transition);
            background-color: var(--light-color); /* Changed from #fdfdff */
        }
        .form-group select {
            appearance: none; /* For custom select arrow if desired, or use browser default */
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%236c757d'%3E%3Cpath d='M7 10l5 5 5-5H7z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1.2rem center;
            background-size: 1em;
            padding-right: 3rem; /* Make space for arrow */
        }


        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
            background-color: var(--white);
        }
        
        .form-group textarea {
            min-height: 130px;
            resize: vertical;
        }
        .support-form-container .btn { /* Button specific to support form */
            width: 100%;
            padding: 0.9rem 2rem;
            margin-top: 0.5rem; 
        }
        .form-group input[type="file"] {
            padding: 0.6rem 1.2rem; /* Adjust padding for file input */
            background-color: var(--light-color);
        }
        .form-group input[type="file"]::file-selector-button {
            margin-right: 1rem;
            border: none;
            background: var(--primary-color);
            padding: 0.6rem 1rem;
            border-radius: 6px;
            color: var(--white);
            cursor: pointer;
            transition: background-color var(--transition);
        }
        .form-group input[type="file"]::file-selector-button:hover {
            background: var(--primary-dark);
        }


        /* Footer (Copied from existing styles) */
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

        /* Responsive Styles */
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hamburger { display: flex; flex-direction: column; justify-content: space-between; }
            
            .section-title h1 { font-size: 2rem; }
            .section-title h2 { font-size: 1.8rem; }

            .support-form-container {
                padding: 2rem 1.5rem;
            }
        }
         @media (max-width: 576px) {
            .section-title h1 { font-size: 1.8rem; }
            .section-title h2 { font-size: 1.6rem; }
            .section-title p { font-size: 1rem; }

            .support-form-container {
                padding: 1.5rem 1rem;
            }
         }

    </style>
</head>
<body>
    <!-- Header -->
    <?php require_once "components/header.php" ?> 

    <!-- Main Content for Help Center -->
    <main>
        <section class="page-title-section section">
            <div class="container">
                <div class="section-title">
                    <h1>Help Center</h1>
                    <p>Your go-to resource for assistance and troubleshooting.</p>
                </div>
            </div>
        </section>

        <section class="common-problems-section section faq-section" id="common-problems">
            <div class="container">
                <div class="section-title">
                    <h2>Most Common Problems</h2>
                    <p>Quick solutions to frequently encountered issues.</p>
                </div>
                <div class="faq-container">
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>I forgot my password. How can I reset it?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <div class="faq-answer-content">
                                <p>If you've forgotten your password, you can easily reset it. Go to the <a href="login.php">login page</a> and click on the "Forgot Password?" link. Follow the instructions sent to your registered email address to create a new password.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How do I update my profile information?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <div class="faq-answer-content">
                                <p>Once logged into your dashboard, navigate to the 'Profile' or 'Account Settings' section. There you will find options to edit your personal details, contact information, and other relevant settings. Make sure to save any changes you make.</p>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <span>The attendance report is not generating correctly. What should I do?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <div class="faq-answer-content">
                                <p>First, please ensure that all attendance data for the specified period has been accurately entered and saved. Check if any filters applied to the report (like date range or department) are set correctly. If the issue persists, please use the support form below to describe the problem, including the report type and any error messages you see.</p>
                            </div>
                        </div>
                    </div>
                     <div class="faq-item">
                        <div class="faq-question">
                            <span>Can multiple administrators access the system?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <div class="faq-answer-content">
                                <p>Yes, WavePass supports multiple administrator accounts. The primary administrator can create and manage other admin accounts with varying levels of permissions based on your institution's needs. This can be managed under the 'User Management' or 'Admin Settings' section.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="support-form-section section" id="submit-request">
            <div class="container">
                <div class="section-title">
                    <h2>Still Need Help?</h2>
                    <p>Fill out the form below, and our support team will get back to you as soon as possible.</p>
                </div>
                <div class="support-form-container">
                    <form action="process_support_request.php" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="support-name">Your Name</label>
                            <input type="text" id="support-name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="support-email">Email Address</label>
                            <input type="email" id="support-email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="support-category">Problem Category</label>
                            <select id="support-category" name="category" required>
                                <option value="" disabled selected>Select a category...</option>
                                <option value="login_issues">Login & Account Access</option>
                                <option value="attendance_tracking">Attendance Tracking</option>
                                <option value="reporting">Reporting Issues</option>
                                <option value="profile_management">Profile Management</option>
                                <option value="integration">System Integration</option>
                                <option value="technical_error">Technical Error/Bug</option>
                                <option value="general_inquiry">General Inquiry</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="support-subject">Subject</label>
                            <input type="text" id="support-subject" name="subject" required>
                        </div>
                        <div class="form-group">
                            <label for="support-description">Describe Your Issue in Detail</label>
                            <textarea id="support-description" name="description" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="support-attachment">Attach File (Optional, e.g., screenshot)</label>
                            <input type="file" id="support-attachment" name="attachment">
                            <small style="display: block; margin-top: 0.3rem; color: var(--gray-color);">Max file size: 5MB. Allowed types: JPG, PNG, PDF.</small>
                        </div>
                        <button type="submit" class="btn"><i class="fas fa-headset"></i> Submit Support Request</button>
                    </form>
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
                        <li><a href="index.html#contact"><i class="fas fa-chevron-right"></i> Contact</a></li> 
                        <li><a href="index.html#faq"><i class="fas fa-chevron-right"></i> FAQ</a></li> 
                        <li><a href="help.php"><i class="fas fa-chevron-right"></i> Help Center</a></li>
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
                        <li><a href="mailto:support@WavePass.com"><i class="fas fa-envelope"></i> support@WavePass.com</a></li> {/* Changed to support email */}
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
                <p> <p>&copy; <?php echo date("Y"); ?> WavePass All rights reserved. | <a href="privacy.php">Privacy Policy</a> | <a href="terms.php">Terms of Service</a></p>
            </div>
        </div>
    </footer>

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
            
            const mobileNavLinks = document.querySelectorAll('.mobile-menu a'); // Renamed to avoid conflict
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
        
        // Smooth scrolling for anchor links
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

        // FAQ Accordion Functionality
        const faqItems = document.querySelectorAll('.faq-item');
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            const answer = item.querySelector('.faq-answer');

            question.addEventListener('click', () => {
                const isActive = item.classList.contains('active');
                // Optional: Close other open items
                // faqItems.forEach(otherItem => {
                //     if (otherItem !== item && otherItem.classList.contains('active')) {
                //         otherItem.classList.remove('active');
                //         otherItem.querySelector('.faq-answer').style.maxHeight = null;
                //     }
                // });
                if (isActive) {
                    item.classList.remove('active');
                    answer.style.maxHeight = null;
                } else {
                    item.classList.add('active');
                    answer.style.maxHeight = answer.scrollHeight + 'px';
                }
            });
        });

        // Support Form Submission (Basic example)
        const supportForm = document.querySelector('.support-form-container form');
        if (supportForm) {
            supportForm.addEventListener('submit', function(e) {
                e.preventDefault(); 
                const formData = new FormData(supportForm);
                console.log('Support request submitted:');
                for (let [name, value] of formData.entries()) {
                    // For file input, value will be a File object
                    if (value instanceof File) {
                        console.log(`${name}: ${value.name} (type: ${value.type}, size: ${value.size} bytes)`);
                    } else {
                        console.log(`${name}: ${value}`);
                    }
                }
                alert('Your support request has been submitted! (This is a demo, data not actually sent)');
                supportForm.reset(); 
            });
        }
    </script>
</body>
</html>