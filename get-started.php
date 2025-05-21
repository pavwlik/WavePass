<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WavePass - Get Started</title>
    <!-- CORRECTED Google Material Symbols Link -->
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
            padding-top: 80px; /* Height of the fixed header */
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

        /* Get Started Page Styles */
        .page-header-bar {
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--light-gray);
            background-color: var(--white); 
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            opacity: 0.8;
            transition: Var(--transition);
        }
        .back-link .material-symbols-outlined {
            font-size: 1.3em;
            opacity: 1;
        }
        .back-link:hover {
            opacity: 1;
        }

        .intro-section {
            padding: 3rem 0;
            background-color: #f0f4ff; 
            text-align: center;
        }
        .intro-section h1 {
            font-size: 2.2rem;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        .intro-section p {
            font-size: 1.1rem;
            color: var(--gray-color);
            max-width: 750px;
            margin: 0 auto 1.5rem auto;
        }
        .intro-benefits {
            list-style: none;
            padding: 0;
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        .intro-benefits li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            color: var(--dark-color);
            background-color: var(--white);
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .intro-benefits .material-symbols-outlined {
            color: var(--primary-color);
        }

        .request-form-section {
            padding: 3rem 0 4rem;
        }
        .request-form-container {
            background-color: var(--white);
            padding: 2.5rem 3rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            max-width: 800px; 
            margin: 0 auto; 
        }
        .request-form-container h2 {
            font-size: 1.8rem;
            margin-bottom: 0.8rem;
            color: var(--dark-color);
            text-align: center;
        }
        .request-form-container .form-subtitle {
            font-size: 1rem;
            color: var(--gray-color);
            margin-bottom: 2.5rem;
            text-align: center;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; 
            gap: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem; 
            text-align: left;
        }
        .form-group.full-width {
            grid-column: 1 / -1; 
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
        .form-group input[type="tel"],
        .form-group input[type="number"],
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
            background-color: var(--light-color); 
        }
        .form-group select {
            appearance: none; 
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%236c757d'%3E%3Cpath d='M7 10l5 5 5-5H7z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1.2rem center;
            background-size: 1em;
            padding-right: 3rem; 
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { 
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
            background-color: var(--white);
        }
        .form-group textarea { 
             min-height: 120px; resize: vertical;
        }
        
        .request-form-container .btn { 
            width: 100%;
            padding: 0.9rem 2rem;
            margin-top: 1.5rem; 
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

        /* Responsive Styles */
        @media (max-width: 992px) {
            .nav-links { display: none; }
            .hamburger { display: flex; }
            .request-form-container {
                padding: 2rem 1.5rem;
            }
            .intro-section h1 { font-size: 2rem; }
        }

        @media (max-width: 768px) {
            .request-form-container h2 { font-size: 1.6rem; }
            .request-form-container .form-subtitle { font-size: 0.95rem; }
            .form-grid {
                grid-template-columns: 1fr; 
            }
            .form-group.full-width { 
                grid-column: auto; 
            }
            .intro-section h1 { font-size: 1.8rem; }
            .intro-section p { font-size: 1rem; }
            .intro-benefits { flex-direction: column; align-items: center;}
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php require_once "components/header.php" ?>

    <!-- Main Content for Get Started Page -->
    <main>
    <!--
                <div class="page-header-bar">
            <div class="container">
                <a href="index.php" class="back-link">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Back to Home
                </a>
            </div>
        </div>
    !-->

        <section class="intro-section">
            <div class="container">
                <h1>Ready to Modernize Your School's Attendance?</h1>
                <p>WavePass offers a simple, efficient, and reliable way to manage teacher attendance. By providing us with a few details about your institution, we can tailor a solution that perfectly fits your needs. Let's get started on transforming your attendance tracking process!</p>
                <ul class="intro-benefits">
                    <li><span class="material-symbols-outlined">timer</span> Save Administrative Time</li>
                    <li><span class="material-symbols-outlined">paid</span> Reduce Costs</li>
                    <li><span class="material-symbols-outlined">analytics</span> Improve Accuracy & Reporting</li>
                    <li><span class="material-symbols-outlined">cloud_upload</span> Cloud-Based & Accessible</li>
                </ul>
            </div>
        </section>

        <section class="request-form-section" id="request-form">
            <div class="container">
                <div class="request-form-container">
                    <h2>Request Our Service</h2>
                    <p class="form-subtitle">Please fill out the form below, and our team will contact you shortly.</p>
                    
                    <form action="process_service_request.php" method="POST">
                        <h3 style="margin-top: 0; margin-bottom: 1.5rem; font-size: 1.3rem; color: var(--dark-color); border-bottom: 1px solid var(--light-gray); padding-bottom: 0.5rem;">
                            <span class="material-symbols-outlined" style="vertical-align: bottom; margin-right: 0.3em; font-size: 1.2em;">school</span>School Information
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="school_name">School Name <span style="color:var(--danger-color)">*</span></label>
                                <input type="text" id="school_name" name="school_name" required>
                            </div>
                            <div class="form-group">
                                <label for="school_ico">ICO / Registration ID <span style="color:var(--danger-color)">*</span></label>
                                <input type="text" id="school_ico" name="school_ico" required>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="school_type">Type of School <span style="color:var(--danger-color)">*</span></label>
                                <select id="school_type" name="school_type" required>
                                    <option value="" disabled selected>Select type...</option>
                                    <option value="primary">Primary School</option>
                                    <option value="secondary">Secondary School / High School</option>
                                    <option value="vocational">Vocational School</option>
                                    <option value="college">College</option>
                                    <option value="university">University</option>
                                    <option value="language_school">Language School</option>
                                    <option value="other">Other Educational Institution</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="teacher_count">Approx. Number of Employees <span style="color:var(--danger-color)">*</span></label>
                                <input type="number" id="teacher_count" name="teacher_count" min="1" placeholder="e.g., 50" required>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label for="school_address_street">
                                <span class="material-symbols-outlined" style="font-size: 1.1em; vertical-align: text-bottom; margin-right: 0.2em;">location_on</span>Street Address <span style="color:var(--danger-color)">*</span>
                            </label>
                            <input type="text" id="school_address_street" name="school_address_street" required>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="school_address_city">City <span style="color:var(--danger-color)">*</span></label>
                                <input type="text" id="school_address_city" name="school_address_city" required>
                            </div>
                            <div class="form-group">
                                <label for="school_address_postal">Postal Code <span style="color:var(--danger-color)">*</span></label>
                                <input type="text" id="school_address_postal" name="school_address_postal" required>
                            </div>
                        </div>
                         <div class="form-group full-width">
                            <label for="school_address_country">Country <span style="color:var(--danger-color)">*</span></label>
                            <input type="text" id="school_address_country" name="school_address_country" required>
                        </div>

                        <h3 style="margin-top: 2.5rem; margin-bottom: 1.5rem; font-size: 1.3rem; color: var(--dark-color); border-bottom: 1px solid var(--light-gray); padding-bottom: 0.5rem;">
                             <span class="material-symbols-outlined" style="vertical-align: bottom; margin-right: 0.3em; font-size: 1.2em;">badge</span>Contact Person
                        </h3>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="contact_person_name">Full Name <span style="color:var(--danger-color)">*</span></label>
                                <input type="text" id="contact_person_name" name="contact_person_name" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_person_email">
                                     <span class="material-symbols-outlined" style="font-size: 1.1em; vertical-align: text-bottom; margin-right: 0.2em;">contact_mail</span>Email Address <span style="color:var(--danger-color)">*</span>
                                </label>
                                <input type="email" id="contact_person_email" name="contact_person_email" required>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label for="contact_person_phone">
                                <span class="material-symbols-outlined" style="font-size: 1.1em; vertical-align: text-bottom; margin-right: 0.2em;">contact_phone</span>Phone Number (Optional)
                            </label>
                            <input type="tel" id="contact_person_phone" name="contact_person_phone">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="message">Any Specific Requirements or Message (Optional)</label>
                            <textarea id="message" name="message" rows="4"></textarea>
                        </div>
                        
                        <button type="submit" class="btn">
                            <span class="material-symbols-outlined">send</span> Send Request
                        </button>
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
                        <li><a href="index.php#features"><i class="fas fa-chevron-right"></i> Features</a></li>
                        <li><a href="index.php#how-it-works"><i class="fas fa-chevron-right"></i> How It Works</a></li>
                        <li><a href="index.php#contact"><i class="fas fa-chevron-right"></i> Contact</a></li> 
                        <li><a href="index.php#faq"><i class="fas fa-chevron-right"></i> FAQ</a></li> 
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
            
            const mobileNavLinks = document.querySelectorAll('.mobile-menu a');
            mobileNavLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (link.getAttribute('href').startsWith('#') || link.getAttribute('href').startsWith('index.php#') || link.classList.contains('btn')) {
                        hamburger.classList.remove('active');
                        mobileMenu.classList.remove('active');
                        body.style.overflow = '';
                    }
                });
            });
        }
        
        // Smooth scrolling (if any on-page anchors are used, e.g., from footer)
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

        // Service Request Form Submission (Basic example)
        const serviceRequestForm = document.querySelector('.request-form-container form');
        if (serviceRequestForm) {
            serviceRequestForm.addEventListener('submit', function(e) {
                if (!this.checkValidity()) {
                    e.preventDefault(); 
                } else {
                    // For demo purposes, prevent actual submission and show an alert
                    // In a real application, you would let the form submit to process_service_request.php
                    // e.preventDefault(); 
                    // alert('Service request submitted! (This is a demo and the data was not actually sent).');
                    // this.reset(); // Optionally reset the form after demo alert
                }
            });
        }

    </script>
</body>
</html>