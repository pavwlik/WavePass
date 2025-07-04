<?php
// --- SESSION MANAGEMENT ---
// THIS MUST BE THE VERY FIRST THING IN YOUR SCRIPT
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Spustí session, pokud ještě neběží
}

require_once "db.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="imgs/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- ========= Primary Meta Tags for SEO & Search Result Appearance ========= -->
    <title>WavePass - Teacher Attendance System</title>
    <meta name="description" content="Streamline school employee attendance with WavePass. Our intuitive, cloud-based system saves time, improves accuracy, and offers real-time tracking & reports.">
    
    <!-- ========= Open Graph / Facebook Meta Tags ========= -->
    <meta property="og:title" content="WavePass - Teacher Attendance System">
    <meta property="og:description" content="Streamline school employee attendance with WavePass. Our intuitive, cloud-based system saves time, improves accuracy, and offers real-time tracking & reports.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="WavePass">

    <!-- ========= Twitter Card Meta Tags ========= -->
    <meta name="twitter:card" content="summary_large_image"> 
    <meta name="twitter:title" content="WavePass - Teacher Attendance System">
    <meta name="twitter:description" content="Streamline school employee attendance with WavePass. Our intuitive, cloud-based system saves time, improves accuracy, and offers real-time tracking & reports.">
    <meta name="twitter:image" content="https://www.your-wavepass-domain.com/assets/twitter-image.png"> <!-- !! REPLACE with URL to an image !! -->

    <!-- ========= Other Meta Tags ========= -->
    <meta name="author" content="Pavel Bures, Krystof Topinka, Filip Elznic"> 
    <meta name="keywords" content="school attendance, teacher attendance, employee tracking, attendance system, education technology, school management, cloud attendance, WavePass, RFID,">

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
            display: flex; /* For sticky footer, if main content is short */
            flex-direction: column;
            min-height: 100vh;
        }
        
        main {
            flex-grow: 1; /* Ensures footer stays at bottom */
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


        /* General Button Styles */
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
            background-color: transparent!important; 
            border: 2px solid var(--primary-color)!important;
            color: var(--primary-color)!important;
            box-shadow: none!important;
        }
        .btn-outline:hover { 
            background-color: var(--primary-color)!important; 
            color: var(--white)!important; 
            transform: translateY(-2px)!important;
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
            position: fixed; top: 0; left: 0; width: 100%; height: 100vh;
            background-color: var(--white); z-index: 1000;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            transform: translateX(-100%);
            transition: transform 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            padding: 2rem;
        }
        .mobile-menu.active { transform: translateX(0); }
        .mobile-links { list-style: none; text-align: center; width: 100%; max-width: 300px; }
        .mobile-links li { margin-bottom: 1.5rem; }
        .mobile-links a {
            color: var(--dark-color); text-decoration: none; font-weight: 600;
            font-size: 1.2rem; display: inline-block; padding: 0.5rem 1rem;
            transition: var(--transition); border-radius: 8px;
        }
        .mobile-links a:hover,
        .mobile-links a.active { /* Apply active state style same as hover */
            color: var(--primary-color); 
            background-color: rgba(67, 97, 238, 0.1); 
        }
        .mobile-menu .btn { margin-top: 2rem; width: 100%; max-width: 200px; }
        .close-btn {
            position: absolute; top: 30px; right: 30px; font-size: 1.8rem;
            color: var(--dark-color); cursor: pointer; transition: var(--transition);
        }
        .close-btn:hover { color: var(--primary-color); transform: rotate(90deg); }
        
        /* Hero Section */
        .hero {
            padding-top: 10rem; padding-bottom: 5rem; text-align: center;
            background: linear-gradient(135deg, #f0f4ff 0%, #e0e9ff 100%);
            position: relative; overflow: hidden;
        }
        .hero::before { content: ''; position: absolute; top: -50%; right: -20%; width: 70%; height: 200%; background: radial-gradient(circle, rgba(67, 97, 238, 0.1) 0%, rgba(67, 97, 238, 0) 70%); z-index: 0; }
        .hero .container { position: relative; z-index: 1; }
        .hero h1 { font-size: 2.8rem; margin-bottom: 1.5rem; color: var(--dark-color); line-height: 1.2; }
        .hero p { font-size: 1.2rem; color: var(--gray-color); max-width: 700px; margin: 0 auto 2.5rem; }
        .hero-buttons { display: flex; justify-content: center; gap: 1rem; margin-top: 2rem; }

        /* General Section Styles */
        .section { padding: 6rem 0; }
        .section-title { text-align: center; margin-bottom: 4rem; }
        .section-title h2 { font-size: 2.2rem; color: var(--dark-color); margin-bottom: 1.2rem; }
        .section-title p { color: var(--gray-color); max-width: 700px; margin: 0 auto; font-size: 1.1rem; }
        
        /* Features Section Specifics */
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
        .feature-card { 
            background-color: var(--white); padding: 2.5rem 2rem; border-radius: 12px; 
            box-shadow: var(--shadow); text-align: center; transition: var(--transition); 
            border: 1px solid rgba(0, 0, 0, 0.03); cursor: pointer; 
        }
        .feature-card:hover { transform: translateY(-10px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); }
        .feature-icon { 
            font-size: 2.8rem; color: var(--primary-color); margin-bottom: 1.8rem; 
            display: inline-flex; justify-content: center; align-items: center; 
            width: 80px; height: 80px; background-color: rgba(67, 97, 238, 0.1); border-radius: 50%; 
        }
        .feature-card h3 { margin-bottom: 1.2rem; color: var(--dark-color); font-size: 1.3rem; }
        .feature-card p { color: var(--gray-color); font-size: 0.95rem; line-height: 1.6; }

        /* How It Works Section Specifics */
        .steps-container { background-color: var(--white); padding: 4rem 0; border-radius: 16px; box-shadow: var(--shadow); }
        .steps-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; padding: 0 2rem; }
        .steps-grid:hover{ cursor: pointer; }
        .step-card { 
            background-color: var(--light-color); padding: 2rem; border-radius: 12px; 
            text-align: center; transition: var(--transition); border: 1px solid rgba(0, 0, 0, 0.05); 
            position: relative; overflow: hidden; 
        }
        .step-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 0; background-color: var(--primary-color); transition: var(--transition); }
        .step-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08); }
        .step-card:hover::before { height: 100%; }
        .step-number { 
            background-color: var(--primary-color); color: var(--white); width: 50px; height: 50px; 
            border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            font-weight: 700; margin: 0 auto 1.5rem; font-size: 1.2rem; 
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3); 
        }
        .step-card h3 { margin-bottom: 1rem; color: var(--dark-color); font-size: 1.3rem; }
        .step-card p { color: var(--gray-color); font-size: 0.95rem; line-height: 1.6; }

        /* About Us Section Specifics */
        .team-rows-container {
            margin-top: 2rem; 
        }
        .team-row {
            display: flex;
            flex-wrap: wrap; 
            justify-content: center; 
            gap: 2rem; 
            margin-bottom: 2rem; 
        }
        .team-row:last-child {
            margin-bottom: 0; 
        }
        .team-member-card {
            background-color: var(--white);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
            border: 1px solid rgba(0,0,0,0.03);
            flex-grow: 1;      
            flex-shrink: 1;    
            flex-basis: 360px; 
            max-width: 410px;  
        }
        .team-member-card:hover {
            transform: translateY(-8px);
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        .member-image-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: var(--light-gray); 
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-color); 
            font-size: 3rem; 
            border: 3px solid var(--primary-color);
            overflow: hidden; 
            position: relative; 
        }
        .member-image-placeholder img {
            width: 100%;
            height: 100%;
            object-fit: cover;   
            object-position: center; 
        }
        .member-image-placeholder i { 
            font-size: 3.5rem; 
            color: var(--primary-color);
            opacity: 0.5;
        }
        .member-name {
            font-size: 1.4rem;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        .member-role {
            font-size: 0.95rem;
            color: var(--primary-color);
            font-weight: 500;
            margin-bottom: 0.75rem; 
        }
        .member-social-links {
            margin-top: 1rem; 
            display: flex;
            justify-content: center;
            gap: 1rem; 
        }
        .member-social-links a {
            color: var(--gray-color); 
            font-size: 1.3rem; 
            transition: color var(--transition), transform var(--transition);
            display: inline-block; 
        }
        .member-social-links a:hover {
            color: var(--primary-color); 
            transform: scale(1.15); 
        }
        .member-description { 
            font-size: 0.9rem;
            color: var(--gray-color);
            line-height: 1.5;
        }
        
        /* Contact Section Specifics */
        .contact-section { background-color: #f0f4ff; }
        .contact-container { display: grid; grid-template-columns: 1fr 1.2fr; gap: 3rem; align-items: flex-start; }
        .contact-details { margin-top: 0; } 
        .contact-detail { display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem; }
        .contact-icon { 
            background-color: rgba(67, 97, 238, 0.1); color: var(--primary-color); 
            width: 45px; height: 45px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 1.1rem; flex-shrink: 0; 
        }
        .contact-text h4 { color: var(--dark-color); margin-bottom: 0.3rem; font-size: 1.1rem; }
        .contact-text p, .contact-text a { color: var(--gray-color); text-decoration: none; transition: var(--transition); font-size: 0.95rem; }
        .contact-text a:hover { color: var(--primary-color); }
        .contact-form { background-color: var(--white); padding: 2.5rem; border-radius: 12px; box-shadow: var(--shadow); }
        .form-group { margin-bottom: 1.5rem; text-align: left; }
        .form-group label { display: block; margin-bottom: 0.6rem; font-weight: 600; color: var(--dark-color); font-size: 0.9rem; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group textarea {
            width: 100%; padding: 0.9rem 1.2rem; border: 1px solid var(--light-gray); 
            border-radius: 8px; font-size: 0.95rem; font-family: inherit; color: var(--dark-color); 
            transition: border-color var(--transition), box-shadow var(--transition); background-color: var(--light-color);
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus,
        .form-group textarea:focus {
            outline: none; border-color: var(--primary-color); 
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15); background-color: var(--white);
        }
        .form-group textarea { min-height: 130px; resize: vertical; }
        .contact-form .btn { width: 100%; padding: 0.9rem 2rem; }

        /* FAQ Section Specifics */
        .faq-section { background-color: var(--white); }
        .faq-container { max-width: 800px; margin: 0 auto; }
        .faq-item {
            background-color: var(--white); margin-bottom: 1rem; border-radius: 8px;
            overflow: hidden; box-shadow: var(--shadow); transition: box-shadow var(--transition);
            border: 1px solid var(--light-gray);
        }
        .faq-item:hover { box-shadow: 0 6px 25px rgba(0,0,0,0.1); }
        .faq-question {
            padding: 1.5rem; display: flex; justify-content: space-between; align-items: center;
            cursor: pointer; font-weight: 600; color: var(--dark-color);
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .faq-question:hover { color: var(--primary-color); background-color: rgba(var(--primary-color-rgb), 0.03); }
        .faq-question i { transition: transform var(--transition); }
        .faq-answer {
            max-height: 0; overflow: hidden;
            transition: max-height 0.35s ease-out;
            background-color: rgba(var(--primary-color-rgb), 0.02);
        }
        .faq-answer-content {
            padding: 1.5rem; color: var(--gray-color);
            line-height: 1.6; font-size: 0.95rem;
        }
        .faq-item.active .faq-question i { transform: rotate(180deg); }
        .faq-item.active .faq-question { color: var(--primary-color); background-color: rgba(var(--primary-color-rgb), 0.05); }



        /* Responsive Styles */
        @media (max-width: 992px) {
            .hero h1 { font-size: 2.5rem; }
            .section-title h2 { font-size: 2rem; }
            .contact-container { grid-template-columns: 1fr; gap: 2.5rem; }
            .contact-info { text-align: center; }
            .contact-info .contact-details { justify-content: center; }
            .contact-details { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; }
            .contact-detail { flex-direction: column; align-items: center; text-align: center; max-width: 200px; }
            /* .team-grid was removed, .team-row handles responsiveness */
        }

        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hamburger { display: flex; flex-direction: column; justify-content: space-between; }
            .hero { padding-top: 8rem; padding-bottom: 4rem; }
            .hero h1 { font-size: 2.2rem; }
            .hero p { font-size: 1.1rem; }
            .hero-buttons { flex-direction: column; align-items: center; }
            .btn, .nav-links .btn, .mobile-menu .btn, .contact-form .btn  { width: 100%; max-width: 300px; }
            .btn-outline, .nav-links .btn-outline, .mobile-menu .btn-outline { width: 100%; max-width: 300px;}
            .nav-links .btn, .nav-links .btn-outline { max-width: 180px; width: auto; } 
            .section { padding: 4rem 0; }
            .section-title h2 { font-size: 1.8rem; }
            .section-title p { font-size: 1rem; }
            .steps-grid { grid-template-columns: 1fr; gap: 1.5rem; }
            /* .team-grid was removed */
            .footer-content { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 576px) {
            .hero h1 { font-size: 2rem; }
            .section-title h2 { font-size: 1.6rem; }
            .feature-card { padding: 2rem 1.5rem; }
            .team-member-card { padding: 1.5rem; }
            .member-image-placeholder { width: 100px; height: 100px; font-size: 2.5rem; }
            .member-image-placeholder i { font-size: 3rem; }
            .footer-content { grid-template-columns: 1fr; }
            .contact-form { padding: 2rem 1.5rem; }
            .btn, .btn-outline, 
            .nav-links .btn, .nav-links .btn-outline, 
            .mobile-menu .btn, .mobile-menu .btn-outline, 
            .hero-buttons .btn, .hero-buttons .btn-outline, 
            .contact-form .btn { max-width: 100%; } 

            .hero::before {
                width: 150%;
                height: 150%;
                top: -25%;   
                right: -75%; 
            }
        }

        /* Scroll to Top Button */
        #scrollToTopBtn {
            display: none; position: fixed; bottom: 30px; right: 30px;
            z-index: 999; border: none; outline: none;
            background-color: var(--primary-color); color: var(--white);
            cursor: pointer; padding: 0; border-radius: 50%;
            width: 50px; height: 50px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s ease;
            opacity: 0; visibility: hidden; transform: translateY(20px);
        }
        #scrollToTopBtn .material-symbols-outlined {
            font-size: 24px; line-height: 50px; width: 100%; text-align: center;
        }
        #scrollToTopBtn:hover { background-color: var(--primary-dark); }
        #scrollToTopBtn.show {
            display: flex; align-items: center; justify-content: center;
            opacity: 1; visibility: visible; transform: translateY(0);
        }
        .greySymbol{
            color: var(--gray-color);
        }
    </style>
</head>
<body>
    
    <!-- Header -->
    <?php require "components/header-main.php" ?>

    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <h1>Modern Attendance Tracking for Schools</h1>
                <p>Streamline your school's employee attendance management with our intuitive, cloud-based solution that saves time and increases safety.</p>
                <div class="hero-buttons">
                    <a href="get-started.php" class="btn"><i class="fas fa-rocket"></i> Get Started</a>
                    <a href="#features" class="btn btn-outline"><i class="fas fa-info-circle"></i> Learn More</a>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="section" id="features">
            <div class="container">
                <div class="section-title">
                    <h2>Powerful Features</h2>
                    <p>Everything you need to manage employee attendance efficiently</p>
                </div>
                <div class="features">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-clock"></i></div>
                        <h3>Real-time Tracking</h3>
                        <p>Monitor employee attendance in real-time with instant updates and notifications for administrators.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-chart-bar"></i></div>
                        <h3>Detailed Reports</h3>
                        <p>Generate comprehensive reports for individual teachers, departments, or the entire institution.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                        <h3>Responsive Design</h3>
                        <p>Teachers can check in/out from any device, including smartphones and tablets.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-user-shield"></i></div>
                        <h3>Secure Access</h3>
                        <p>Role-based permissions ensure data security and privacy compliance.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section class="section" id="how-it-works">
            <div class="container">
                <div class="section-title">
                    <h2>How It Works</h2>
                    <p>Simple steps to transform your employee attendance management</p>
                </div>
                <div class="steps-container">
                    <div class="steps-grid">
                        <div class="step-card">
                            <div class="step-number">1</div>
                            <h3>Set Up Your Account</h3>
                            <p>Register your institution and create administrator accounts in minutes with our simple onboarding process.</p>
                        </div>
                        <div class="step-card">
                            <div class="step-number">2</div>
                            <h3>Import Employee Data</h3>
                            <p>Easily upload your existing employee records via CSV or add them manually through our intuitive interface.</p>
                        </div>
                        <div class="step-card">
                            <div class="step-number">3</div>
                            <h3>Configure Your Settings</h3>
                            <p>Customize attendance rules, work schedules, and reporting formats to match your institution's policies.</p>
                        </div>
                        <div class="step-card">
                            <div class="step-number">4</div>
                            <h3>Start Tracking</h3>
                            <p>Employees can begin recording attendance via RFID cards through our simple web interface.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- About Us Section -->
        <section class="section" id="about-us">
            <div class="container">
                <div class="section-title">
                    <h2>Meet Our Team</h2>
                    <p>The passionate individuals behind WavePass</p>
                </div>
                <div class="team-rows-container">
                    <div class="team-row"> 
                    <div class="team-member-card">
                            <div class="member-image-placeholder">
                                <img src="imgs/IMG_0671.JPEG" alt="Pavel Bureš"> 
                            </div>
                            <h3 class="member-name">Pavel Bureš</h3>
                            <p class="member-role">Full-Stack Developer <span class="greySymbol">|</span> UI & UX Design <span class="greySymbol">|</span> Database</p>
                            <div class="member-social-links">
                                <a href="https://www.instagram.com/pavwlik/" target="_blank" title="Pavel Bureš Instagram" aria-label="Pavel Bureš Instagram"><i class="fab fa-instagram"></i></a>
                                <a href="https://www.linkedin.com/in/pavel-bure%C5%A1-71992b311/" target="_blank" title="Pavel Bureš LinkedIn" aria-label="Pavel Bureš LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                                <a href="mailto:pavelbures07@gmail.com" title="Email Pavel Bureš" aria-label="Email Pavel Bureš"><i class="fas fa-envelope"></i></a>
                            </div>
                        </div>
                        <div class="team-member-card">
                        <div class="member-image-placeholder">
                                <img src="imgs/krystof1.jpg" alt="Kryštof Topinka"> 
                            </div>
                            <h3 class="member-name">Kryštof Topinka</h3>
                            <p class="member-role">Backend Developer <span class="greySymbol">|</span> Documentation <span class="greySymbol">|</span> Database</p>
                            <div class="member-social-links">
                                <a href="https://www.instagram.com/kikous_topa/" target="_blank" title="Kryštof Topinka Instagram" aria-label="Kryštof Topinka Instagram"><i class="fab fa-instagram"></i></a>
                                <!--<a href="#" target="_blank" title="Kryštof Topinka LinkedIn" aria-label="Kryštof Topinka LinkedIn"><i class="fab fa-linkedin-in"></i></a> !-->
                                <a href="mailto:kikotopaxd@gmail.com" title="Email Kryštof Topinka" aria-label="Email Kryštof Topinka"><i class="fas fa-envelope"></i></a>
                            </div>
                        </div>
                        <div class="team-member-card">
                        <div class="member-image-placeholder">
                                <img src="imgs/IMG_2321.png" alt="Filip Elznic"> 
                            </div>
                            <h3 class="member-name">Filip Elznic</h3>
                            <p class="member-role">Operating Systems <span class="greySymbol">|</span> Project Manager</p>
                            <div class="member-social-links">
                                <a href="https://www.instagram.com/filip_elza_elznic/" target="_blank" title="Filip Elznic Instagram" aria-label="Filip Elznic Instagram"><i class="fab fa-instagram"></i></a>
                                <!-- <a href="#" target="_blank" title="Kryštof Topinka LinkedIn" aria-label="Kryštof Topinka LinkedIn"><i class="fab fa-linkedin-in"></i></a> !-->
                                <a href="mailtoelznic.f@gmail.com" title="Email Filip Elznic" aria-label="Email Filip Elznic"><i class="fas fa-envelope"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="team-row"> 
                        <div class="team-member-card">
                        <div class="member-image-placeholder">
                                <img src="imgs/tomas.jpg    " alt="Tomas Koci"> 
                            </div>
                            <h3 class="member-name">Tomáš Kočí</h3>
                            <p class="member-role">QA & Testing <span class="greySymbol">|</span> Support</p>
                            <div class="member-social-links">
                                <a href="koci.to.2022@skola.spsrakovnik.cz" target="_blank" title="Tomáš Kočí Instagram" aria-label="Tomáš Kočí Instagram"><i class="fab fa-instagram"></i></a>
                                <!-- <a href="#" target="_blank" title="Kryštof Topinka LinkedIn" aria-label="Kryštof Topinka LinkedIn"><i class="fab fa-linkedin-in"></i></a> !-->
                                <a href="mailto:https://www.instagram.com/kotom_friends/" title="Email Tomáš Kočí" aria-label="Email Tomáš Kočí"><i class="fas fa-envelope"></i></a>
                            </div>
                        </div>
                        <div class="team-member-card">
                        <div class="member-image-placeholder">
                                <img src="imgs/zdenek.jpg" alt="Zdenek Cepelak"> 
                            </div>
                            <h3 class="member-name">Zdeněk Čepelák</h3>
                            <p class="member-role">QA & Testing <span class="greySymbol">|</span> Support</p>
                            <div class="member-social-links">
                                <a href="https://www.instagram.com/cepelda/" target="_blank" title="Zdeněk Čepelák Instagram" aria-label="Zdeněk Čepelák Instagram"><i class="fab fa-instagram"></i></a>
                                <!-- <a href="#" target="_blank" title="Kryštof Topinka LinkedIn" aria-label="Kryštof Topinka LinkedIn"><i class="fab fa-linkedin-in"></i></a> !-->
                                <a href="mailto:cepelak.zd.2022@skola.spsrakovnik.cz" title="Email Zdeněk Čepelák" aria-label="Email Zdeněk Čepelák"><i class="fas fa-envelope"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section class="section contact-section" id="contact">
            <div class="container">
                <div class="section-title">
                    <h2>Get In Touch</h2>
                    <p>Have questions about WavePass? Our team is here to help you understand how our solution can meet your school's specific needs.</p>
                </div>
                <div class="contact-container">
                    <div class="contact-info">
                        <div class="contact-details">
                            <div class="contact-detail">
                                <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                                <div class="contact-text">
                                    <h4>Email Us</h4>
                                    <a href="mailto:info@wavepass.pl">info@wavepass.com</a>
                                </div>
                            </div>
                            <div class="contact-detail">
                                <div class="contact-icon"><i class="fas fa-phone-alt"></i></div>
                                <div class="contact-text">
                                    <h4>Call Us</h4>
                                    <a href="tel:+50123456789">+420 733 757 767</a>
                                </div>
                            </div>
                            <div class="contact-detail">
                                <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="contact-text">
                                    <h4>Visit Us</h4>
                                    <p><a href="https://maps.app.goo.gl/sRryn6QST8gEhF6t5" target="_blank" rel="noopener noreferrer" title="View on Google Maps">Sídliště Generála Josefa Kholla 2501, 269 01 Rakovník 1, Česko</a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <form class="contact-form">
                        <div class="form-group">
                            <label for="name">Your Name</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="school">School/Institution</label>
                            <input type="text" id="school" name="school" required>
                        </div>
                        <div class="form-group">
                            <label for="message">Your Message</label>
                            <textarea id="message" name="message" required></textarea>
                        </div>
                        <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Send Message</button>
                    </form>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="section faq-section" id="faq">
            <div class="container">
                <div class="section-title">
                    <h2>Frequently Asked Questions</h2>
                    <p>Find answers to common questions about WavePass</p>
                </div>
                <div class="faq-container">
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How secure is my school's data?</span>
                            <i class="fas fa-chevron-down" aria-hidden="true" translate="no"></i>
                        </div>
                        <div class="faq-answer">
                            <div class="faq-answer-content">
                                <p>We take data security very seriously. All data is encrypted both in transit and at rest using industry-standard protocols. Our servers are hosted in secure, SOC 2 compliant data centers with regular security audits.</p>
                            </div>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>Can I integrate with our existing school management system?</span>
                            <i class="fas fa-chevron-down" aria-hidden="true" translate="no"></i>
                        </div>
                        <div class="faq-answer">
                            <div class="faq-answer-content">
                                <p>Yes! WavePass offers API integration with most popular school management systems. We also support CSV imports/exports for easy data transfer. Our support team can assist with setting up integrations.</p>
                            </div>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>What happens if our internet goes down?</span>
                            <i class="fas fa-chevron-down" aria-hidden="true" translate="no"></i>
                        </div>
                        <div class="faq-answer">
                            <div class="faq-answer-content">
                                <p>WavePass primarily operates as a cloud-based system, requiring an internet connection for real-time check-ins and administrative access. For critical situations, we are exploring offline capabilities for future updates. Currently, attendance would need to be recorded manually and entered once connectivity is restored.</p>
                            </div>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How much training is required for staff?</span>
                            <i class="fas fa-chevron-down" aria-hidden="true" translate="no"></i>
                        </div>
                        <div class="faq-answer">
                            <div class="faq-answer-content">
                                <p>WavePass is designed to be intuitive and user-friendly. Most teachers can start using it with minimal instruction. We provide comprehensive onboarding materials and training videos for administrators, and our support team is always available to help.</p>
                            </div>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>What kind of support do you offer?</span>
                            <i class="fas fa-chevron-down" aria-hidden="true" translate="no"></i>
                        </div>
                        <div class="faq-answer">
                            <div class="faq-answer-content">
                                <p>We offer 24/7 email support with a guaranteed response time of under 4 hours during business days. Premium support packages with phone support and dedicated account managers are also available for larger institutions.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <?php  require_once "components/footer-main.php"; ?>

    <!-- Scroll to Top Button -->
    <button id="scrollToTopBtn" title="Go to top">
        <span class="material-symbols-outlined" aria-hidden="true" translate="no" >arrow_upward</span>
    </button>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mobile Menu Toggle (předpokládáme, že prvky jsou v header-main.php)
            const hamburger = document.getElementById('hamburger');
            const mobileMenu = document.getElementById('mobileMenu');
            const closeMenu = document.getElementById('closeMenu'); 
            const body = document.body;

            if (hamburger && mobileMenu && closeMenu) {
                if (closeMenu.innerHTML.includes('×') || closeMenu.querySelector('i')) { 
                    closeMenu.setAttribute('translate', 'no');
                }
                if (hamburger.querySelectorAll('span')) {
                    hamburger.querySelectorAll('span').forEach(span => {
                        span.setAttribute('translate', 'no');
                    });
                }

                hamburger.addEventListener('click', () => {
                    const isActive = hamburger.classList.toggle('active');
                    mobileMenu.classList.toggle('active');
                    body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
                    hamburger.setAttribute('aria-expanded', isActive ? 'true' : 'false');
                    if (mobileMenu) mobileMenu.setAttribute('aria-hidden', !isActive);
                });

                closeMenu.addEventListener('click', () => {
                    hamburger.classList.remove('active');
                    mobileMenu.classList.remove('active');
                    body.style.overflow = '';
                    hamburger.setAttribute('aria-expanded', 'false');
                    if (mobileMenu) mobileMenu.setAttribute('aria-hidden', 'true');
                    if (hamburger) hamburger.focus();
                });

                const mobileNavLinksList = document.querySelectorAll('.mobile-menu a');
                mobileNavLinksList.forEach(link => {
                    link.addEventListener('click', () => {
                        if (!link.target || link.target === '_self') {
                            hamburger.classList.remove('active');
                            mobileMenu.classList.remove('active');
                            body.style.overflow = '';
                            if (hamburger) hamburger.setAttribute('aria-expanded', 'false');
                            if (mobileMenu) mobileMenu.setAttribute('aria-hidden', 'true');
                        }
                    });
                });
                
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && mobileMenu && mobileMenu.classList.contains('active')) {
                        closeMenu.click();
                    }
                });
            }

            const pageHeader = document.getElementById('pageHeader');
            const headerHeight = pageHeader ? pageHeader.offsetHeight : 0;

            document.querySelectorAll('a[href^="#"], a[href^="index.php#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href === '#' || (href.startsWith('#') && href.length === 1) ) return;

                    let targetId;
                    let onIndexPage = window.location.pathname.endsWith('index.php') || window.location.pathname === '/' || window.location.pathname.endsWith('/');

                    if (href.startsWith('index.php#')) {
                        targetId = href.substring(href.indexOf('#'));
                        if (!onIndexPage) {
                            window.location.href = href;
                            return;
                        }
                    } else if (href.startsWith('#')) {
                        targetId = href;
                        if (!onIndexPage) {
                            window.location.href = 'index.php' + href;
                            return;
                        }
                    } else { return; }

                    try {
                        const targetElement = document.querySelector(targetId);
                        if (targetElement) {
                            e.preventDefault();
                            const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - headerHeight;
                            window.scrollTo({
                                top: targetPosition,
                                behavior: 'smooth'
                            });
                        }
                    } catch (error) {
                        console.warn("Smooth scroll target not found or invalid selector:", targetId, error);
                    }
                });
            });

            if (pageHeader) {
                window.addEventListener('scroll', () => {
                    if (window.scrollY > 10) {
                        pageHeader.style.boxShadow = '0 4px 10px rgba(0,0,0,0.05)';
                    } else {
                        pageHeader.style.boxShadow = '0 2px 10px rgba(0,0,0,0.05)';
                    }
                });
            }

            // FAQ Accordion Functionality
            const faqItems = document.querySelectorAll('.faq-item');
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question');
                const answer = item.querySelector('.faq-answer');

                if (question && answer) {
                    question.addEventListener('click', () => {
                        const isActive = item.classList.contains('active');
                        
                        if (!isActive) { 
                            faqItems.forEach(otherItem => {
                                if (otherItem !== item && otherItem.classList.contains('active')) {
                                    otherItem.classList.remove('active');
                                    otherItem.querySelector('.faq-answer').style.maxHeight = null;
                                }
                            });
                        }
                        
                        item.classList.toggle('active');
                        if (item.classList.contains('active')) {
                            answer.style.maxHeight = answer.scrollHeight + "px";
                        } else {
                            answer.style.maxHeight = null;
                        }
                    });
                }
            });

            const contactForm = document.querySelector('.contact-form');
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const name = contactForm.querySelector('#name').value;
                    const email = contactForm.querySelector('#email').value;
                    const message = contactForm.querySelector('#message').value;
                    if (!name || !email || !message) {
                        alert('Please fill in all required fields.');
                        return;
                    }
                    console.log('Form submitted (demo):', { name, email, message });
                    alert('Thank you for your message! (This is a demo, data not actually sent)');
                    contactForm.reset();
                });
            }

            // Scroll to Top Button Functionality
            const scrollToTopBtn = document.getElementById("scrollToTopBtn");
            if (scrollToTopBtn) {
                const scrollFunction = () => {
                    if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
                        scrollToTopBtn.classList.add("show");
                    } else {
                        scrollToTopBtn.classList.remove("show");
                    }
                };
                window.addEventListener('scroll', scrollFunction);
                scrollFunction(); 
                scrollToTopBtn.addEventListener("click", function() {
                    window.scrollTo({ top: 0, behavior: "smooth" });
                });
            }

            // Active Nav Link Highlighting on Scroll (and load/hashchange)
            function highlightActiveNavLinkOnScroll() {
                const pageHeaderForNav = document.getElementById('pageHeader');
                if (!pageHeaderForNav) return;
                const headerHeightForNav = pageHeaderForNav.offsetHeight;
                const scrollPosition = window.pageYOffset;
                let currentActiveSectionId = null;
                const sections = [];
                const navLinksToConsider = document.querySelectorAll(
                    'header#pageHeader .nav-links a:not(.btn)[href*="#"], header#pageHeader .mobile-links a:not(.btn)[href*="#"]'
                );

                navLinksToConsider.forEach(link => {
                    const href = link.getAttribute('href');
                    let sectionIdSelector = '';
                    if (href.includes('#')) {
                        sectionIdSelector = href.substring(href.indexOf('#'));
                    }
                    const isLinkForIndexPage = href.startsWith('index.php#') || (href.startsWith('#') && (window.location.pathname.endsWith('index.php') || window.location.pathname === '/' || window.location.pathname.endsWith('/')));
                    if (sectionIdSelector && sectionIdSelector.length > 1 && isLinkForIndexPage) {
                        try {
                            const sectionElement = document.querySelector(sectionIdSelector);
                            if (sectionElement && !sections.some(s => s.id === sectionElement.id)) {
                                sections.push(sectionElement);
                            }
                        } catch (e) { console.warn("Invalid selector for active link: " + sectionIdSelector, e); }
                    }
                });
                sections.sort((a, b) => a.offsetTop - b.offsetTop);

                for (let i = sections.length - 1; i >= 0; i--) {
                    const section = sections[i];
                    const sectionTop = section.offsetTop;
                    const triggerPointTop = sectionTop - headerHeightForNav - Math.min(80, window.innerHeight * 0.15);
                    if (scrollPosition >= triggerPointTop) {
                        currentActiveSectionId = section.getAttribute('id');
                        break;
                    }
                }
                if (sections.length > 0 && (window.innerHeight + scrollPosition >= document.body.offsetHeight - 30)) {
                     currentActiveSectionId = sections[sections.length - 1].getAttribute('id');
                }
                
                const currentHash = window.location.hash;
                if (!currentActiveSectionId && sections.length > 0 && (currentHash === "" || currentHash === "#") && scrollPosition < sections[0].offsetTop - headerHeightForNav ) {
                    // Pokud chcete defaultní sekci (např. první), odkomentujte:
                    // currentActiveSectionId = sections[0].id;
                }

                // Používáme třídu .active-link
                document.querySelectorAll('header#pageHeader .nav-links a, header#pageHeader .mobile-links a').forEach(link => {
                    link.classList.remove('active-link');
                });

                let isHashLinkNowActive = false;
                navLinksToConsider.forEach(link => {
                    const linkHref = link.getAttribute('href');
                    let linkSectionIdFromHref = '';
                    if (linkHref.includes('#')) {
                        linkSectionIdFromHref = linkHref.substring(linkHref.indexOf('#') + 1);
                    }
                    if (linkSectionIdFromHref && linkSectionIdFromHref === currentActiveSectionId) {
                        link.classList.add('active-link');
                        isHashLinkNowActive = true;
                    }
                });

                const currentPathBase = window.location.pathname.split('/').pop() || 'index.php';
                const loginBtnDesktop = document.querySelector('header#pageHeader .nav-links a.btn[href="login.php"]');
                const dashboardBtnDesktop = document.querySelector('header#pageHeader .nav-links a.btn[href="dashboard.php"]');
                const loginBtnMobile = document.querySelector('header#pageHeader .mobile-menu a.btn[href="login.php"]');
                const dashboardBtnMobile = document.querySelector('header#pageHeader .mobile-menu a.btn[href="dashboard.php"]');

                if (!isHashLinkNowActive) {
                    if (loginBtnDesktop && currentPathBase === 'login.php') loginBtnDesktop.classList.add('active-link');
                    if (dashboardBtnDesktop && currentPathBase === 'dashboard.php') dashboardBtnDesktop.classList.add('active-link');
                    if (loginBtnMobile && currentPathBase === 'login.php') loginBtnMobile.classList.add('active-link');
                    if (dashboardBtnMobile && currentPathBase === 'dashboard.php') dashboardBtnMobile.classList.add('active-link');
                }
            }

            const pageHeaderForNavHighlight = document.getElementById('pageHeader');
            if (pageHeaderForNavHighlight) {
                window.addEventListener('scroll', highlightActiveNavLinkOnScroll);
                window.addEventListener('load', highlightActiveNavLinkOnScroll);
                window.addEventListener('hashchange', highlightActiveNavLinkOnScroll);
            }
        });
    </script>
</body>
</html>