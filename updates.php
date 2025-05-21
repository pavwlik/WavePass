<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Updates & Changelog - WavePass</title>
    <meta name="description" content="Stay up-to-date with the latest features, improvements, and bug fixes for the WavePass Teacher Attendance System.">
    <link rel="canonical" href="https://www.your-wavepass-domain.com/updates.php"> <!-- !! REPLACE with your actual URL !! -->

    <!-- Favicon (same as index.php) -->
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link rel="icon" href="assets/favicon.png" type="image/png">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">

    <!-- Open Graph / Facebook Meta Tags (adjust for updates page) -->
    <meta property="og:title" content="Updates & Changelog - WavePass">
    <meta property="og:description" content="Latest updates for the WavePass Teacher Attendance System.">
    <meta property="og:image" content="https://www.your-wavepass-domain.com/assets/og-image-updates.png"> <!-- !! REPLACE with a relevant image for updates !! -->
    <meta property="og:url" content="https://www.your-wavepass-domain.com/updates.php"> <!-- !! REPLACE !! -->
    <meta property="og:type" content="article"> <!-- Or website -->
    <meta property="og:site_name" content="WavePass">

    <!-- Twitter Card Meta Tags (adjust for updates page) -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Updates & Changelog - WavePass">
    <meta name="twitter:description" content="Latest updates for the WavePass Teacher Attendance System.">
    <meta name="twitter:image" content="https://www.your-wavepass-domain.com/assets/twitter-image-updates.png"> <!-- !! REPLACE !! -->

    <meta name="author" content="WavePass Team">
    <meta name="keywords" content="wavepass updates, changelog, new features, attendance system updates">

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Copied all styles from your index.php for consistency */
        /* Add new styles for search/filter and timeline enhancements below */
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
            padding-top: 80px; /* Account for fixed header */
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

        /* Header & Navigation (Same as index.php) */
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
            transition: var(--transition);
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
            transition: var(--transition);
        }
        .nav-links a:not(.btn):hover, .nav-links a:not(.btn).active-nav-link {
            color: var(--primary-color);
            background-color: rgba(67, 97, 238, 0.07); 
        }
        .nav-links a:not(.btn)::after { 
            display: none;
        }
        
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

        /* General Button Styles (Same as index.php) */
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

        /* Hamburger Menu Icon (Same as index.php) */
        .hamburger { display: none; cursor: pointer; width: 30px; height: 24px; position: relative; z-index: 1001; transition: var(--transition); }
        .hamburger span { display: block; width: 100%; height: 3px; background-color: var(--dark-color); position: absolute; left: 0; transition: var(--transition); transform-origin: center; }
        .hamburger span:nth-child(1) { top: 0; }
        .hamburger span:nth-child(2) { top: 50%; transform: translateY(-50%); }
        .hamburger span:nth-child(3) { bottom: 0; }
        .hamburger.active span:nth-child(1) { top: 50%; transform: translateY(-50%) rotate(45deg); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { bottom: 50%; transform: translateY(50%) rotate(-45deg); }

        /* Mobile Menu (Same as index.php) */
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
        .mobile-links a:hover, .mobile-links a.active-nav-link { color: var(--primary-color); background-color: rgba(67, 97, 238, 0.1); }
        .mobile-menu .btn { margin-top: 2rem; width: 100%; max-width: 200px; }
        .close-btn {
            position: absolute; top: 30px; right: 30px; font-size: 1.8rem;
            color: var(--dark-color); cursor: pointer; transition: var(--transition);
        }
        .close-btn:hover { color: var(--primary-color); transform: rotate(90deg); }

        /* General Section Styles (Same as index.php) */
        .section { padding: 4rem 0; } /* Reduced top/bottom padding as main has padding-top */
        .section-title { text-align: center; margin-bottom: 3rem; } /* Reduced margin */
        .section-title h2 { font-size: 2.2rem; color: var(--dark-color); margin-bottom: 1.2rem; }
        .section-title p { color: var(--gray-color); max-width: 700px; margin: 0 auto; font-size: 1.1rem; }
        
        /* Search and Filter Bar */
        .search-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 3rem;
            padding: 1.5rem;
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }
        .search-filter-bar .form-group {
            flex: 1 1 200px; /* Grow, shrink, with a basis */
            margin-bottom: 0; /* Remove default margin from .form-group if used */
        }
        .search-filter-bar label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--dark-color);
        }
        .search-filter-bar input[type="search"],
        .search-filter-bar select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--dark-color);
            background-color: var(--light-color);
            transition: border-color var(--transition), box-shadow var(--transition);
        }
        .search-filter-bar input[type="search"]:focus,
        .search-filter-bar select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
            background-color: var(--white);
        }
        .search-filter-bar .btn-clear {
            padding: 0.8rem 1.5rem;
            background-color: var(--gray-color);
            align-self: flex-end; /* Align with bottom of other inputs */
        }
        .search-filter-bar .btn-clear:hover {
            background-color: var(--dark-color);
        }


        /* Timeline Styles (Same as index.php) */
        .timeline-container {
            position: relative;
            max-width: 1000px;
            margin: 2rem auto;
            padding: 2rem 0;
        }
        .timeline-container::before { 
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: var(--primary-color);
            transform: translateX(-50%);
            z-index: 0;
        }
        .timeline-item {
            padding: 10px 40px;
            position: relative;
            background-color: inherit;
            width: 50%;
            margin-bottom: 2rem;
            z-index: 1;
        }
        .timeline-item::after { 
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            right: -12px; 
            background-color: var(--white);
            border: 4px solid var(--primary-color);
            top: 20px; 
            border-radius: 50%;
            z-index: 2;
        }
        .timeline-item.left { left: 0; }
        .timeline-item.right { left: 50%; }
        .timeline-item.left::before { 
            content: " ";
            height: 0;
            position: absolute;
            top: 25px; 
            width: 0;
            z-index: 1;
            right: 30px;
            border: medium solid var(--white);
            border-width: 10px 0 10px 10px;
            border-color: transparent transparent transparent var(--white);
        }
        .timeline-item.right::before { 
            content: " ";
            height: 0;
            position: absolute;
            top: 25px; 
            width: 0;
            z-index: 1;
            left: 30px;
            border: medium solid var(--white);
            border-width: 10px 10px 10px 0;
            border-color: transparent var(--white) transparent transparent;
        }
        .timeline-item.right::after { left: -12px; } 

        .timeline-content {
            padding: 20px 30px;
            background-color: var(--white);
            position: relative;
            border-radius: 8px;
            box-shadow: var(--shadow);
            border: 1px solid var(--light-gray);
        }
        .timeline-content h3 {
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
            font-size: 1.4rem;
        }
        .update-date-text {
            font-size: 0.9rem;
            color: var(--gray-color);
            margin-bottom: 1rem;
            font-style: italic;
        }
        .update-category-badge {
            display: inline-block;
            background-color: var(--secondary-color);
            color: var(--white);
            padding: 0.25rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .update-category-badge.feature { background-color: var(--success-color); }
        .update-category-badge.improvement { background-color: var(--warning-color); }
        .update-category-badge.bugfix { background-color: var(--danger-color); }

        .update-description {
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--dark-color);
        }
        .update-description ul {
            list-style-type: disc;
            padding-left: 20px;
            margin-top: 0.5rem;
        }
        .update-description ul li {
            margin-bottom: 0.3rem;
        }
        .no-updates-message {
            text-align: center;
            color: var(--gray-color);
            font-size: 1.1rem;
            padding: 3rem 0;
        }
        
        /* Responsive Timeline (Same as index.php) */
        @media screen and (max-width: 768px) {
            .search-filter-bar .btn-clear {
                align-self: stretch; /* Make clear button full width on mobile if desired */
                margin-top: 1rem; /* Add space if it stacks */
            }
            .timeline-container::before {
                left: 15px; 
                transform: translateX(0);
            }
            .timeline-item {
                width: 100%;
                padding-left: 50px; 
                padding-right: 15px;
            }
            .timeline-item.left, .timeline-item.right {
                left: 0%; 
            }
            .timeline-item::after { 
                left: 3px; 
            }
            .timeline-item.left::before, .timeline-item.right::before { 
                left: 40px; 
                border: medium solid var(--white);
                border-width: 10px 10px 10px 0;
                border-color: transparent var(--white) transparent transparent;
            }
        }

        /* Footer (Same as index.php) */
        footer { background-color: var(--dark-color); color: var(--white); padding: 5rem 0 2rem; margin-top: auto; } /* Added margin-top: auto */
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 3rem; margin-bottom: 3rem; }
        .footer-column h3 { font-size: 1.3rem; margin-bottom: 1.8rem; position: relative; padding-bottom: 0.8rem; }
        .footer-column h3::after { content: ''; position: absolute; left: 0; bottom: 0; width: 50px; height: 3px; background-color: var(--primary-color); border-radius: 3px; }
        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: 0.8rem; }
        .footer-links a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); font-size: 0.95rem; display: inline-block; padding: 0.2rem 0; }
        .footer-links a:hover { color: var(--white); transform: translateX(5px); }
        .footer-links a i { margin-right: 0.5rem; width: 20px; text-align: center; }
        .social-links { display: flex; gap: 1.2rem; margin-top: 1.5rem; }
        .social-links a { 
            display: inline-flex; align-items: center; justify-content: center; 
            width: 40px; height: 40px; background-color: rgba(255, 255, 255, 0.1); 
            color: var(--white); border-radius: 50%; font-size: 1.1rem; transition: var(--transition); 
        }
        .social-links a:hover { background-color: var(--primary-color); transform: translateY(-3px); }
        .footer-bottom { text-align: center; padding-top: 3rem; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.9rem; color: rgba(255, 255, 255, 0.6); }
        .footer-bottom a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); }
        .footer-bottom a:hover { color: var(--primary-color); }

        /* Responsive Styles for General Layout (Same as index.php) */
        @media (max-width: 992px) {
            /* .contact-container from index is not on this page, so not needed */
        }
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hamburger { display: flex; flex-direction: column; justify-content: space-between; }
            main { padding-top: 80px; } /* ensure content below fixed header */
            .section { padding: 3rem 0; }
            .section-title h2 { font-size: 2rem; }
             .btn, .nav-links .btn, .mobile-menu .btn { width: 100%; max-width: 300px; }
            .btn-outline, .nav-links .btn-outline, .mobile-menu .btn-outline { width: 100%; max-width: 300px;}
             .nav-links .btn, .nav-links .btn-outline { max-width: 180px; width: auto; } /* Retain for non-mobile context */
            .footer-content { grid-template-columns: 1fr 1fr; }
        }
         @media (max-width: 576px) {
            .section-title h2 { font-size: 1.8rem; }
            .footer-content { grid-template-columns: 1fr; }
            .btn, .btn-outline, 
            .nav-links .btn, .nav-links .btn-outline, 
            .mobile-menu .btn, .mobile-menu .btn-outline, 
            .contact-form .btn { max-width: 100%; }
         }

        /* Scroll to Top Button (Same as index.php) */
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
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo"> <!-- Link back to homepage -->
                    <i class="fas fa-chalkboard-teacher"></i>
                    Wave<span>Pass</span>
                </a>
                
                <ul class="nav-links">
                    <li><a href="index.php#features">Features</a></li>
                    <li><a href="index.php#how-it-works">How It Works</a></li>
                    <li><a href="index.php#about-us">About Us</a></li>
                    <li><a href="updates.php" class="active-nav-link">Updates</a></li> <!-- Active link for this page -->
                    <li><a href="index.php#faq">FAQ</a></li> 
                    <li><a href="index.php#contact">Contact</a></li> 
                    <li><a href="login.php" class="btn"><span class="material-symbols-outlined">account_circle</span> Login</a></li>
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
                <li><a href="index.php#features">Features</a></li>
                <li><a href="index.php#how-it-works">How It Works</a></li>
                <li><a href="index.php#about-us">About Us</a></li>
                <li><a href="updates.php" class="active-nav-link">Updates</a></li>
                <li><a href="index.php#contact">Contact</a></li>
                <li><a href="index.php#faq">FAQ</a></li> 
            </ul>
            <a href="login.php" class="btn"><span class="material-symbols-outlined">person</span> Login</a>
        </div>
    </header>

    <main>
        <!-- Updates Section -->
        <section class="section" id="updates-page">
            <div class="container">
                <div class="section-title">
                    <h2>Updates & Changelog</h2>
                    <p>Stay informed about the latest improvements and features in WavePass.</p>
                </div>

                <!-- Search and Filter Bar -->
                <div class="search-filter-bar">
                    <div class="form-group">
                        <label for="search-updates">Search Updates</label>
                        <input type="search" id="search-updates" placeholder="E.g., Reporting, Mobile App..." aria-label="Search Updates">
                    </div>
                    <div class="form-group">
                        <label for="filter-category">Filter by Category</label>
                        <select id="filter-category" aria-label="Filter by Category">
                            <option value="all">All Categories</option>
                            <option value="feature">New Feature</option>
                            <option value="improvement">Improvement</option>
                            <option value="bugfix">Bug Fix</option>
                            <option value="announcement">Announcement</option>
                        </select>
                    </div>
                    <button class="btn btn-clear" id="clear-filters-btn" type="button">Clear</button>
                </div>

                <div class="timeline-container" id="timeline-updates-list">
                    <!-- Update items will be dynamically populated or hardcoded here -->
                    <div class="timeline-item left" data-category="feature">
                        <div class="timeline-content">
                            <h3>Version 1.2.0 Released</h3>
                            <p class="update-date-text">October 26, 2023</p>
                            <span class="update-category-badge feature">New Feature</span>
                            <div class="update-description">
                                <p>Major update focusing on performance and reporting enhancements.</p>
                                <ul>
                                    <li>New advanced reporting filters.</li>
                                    <li>Improved data export speeds.</li>
                                    <li>UI refresh for the admin dashboard.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="timeline-item right" data-category="improvement">
                        <div class="timeline-content">
                            <h3>Mobile App Update (v1.1.5)</h3>
                            <p class="update-date-text">September 15, 2023</p>
                            <span class="update-category-badge improvement">Improvement</span>
                            <div class="update-description">
                                <p>Offline mode improvements and push notifications for important alerts.</p>
                                <ul>
                                    <li>Enhanced offline data synchronization.</li>
                                    <li>Added customizable push notifications.</li>
                                    <li>Improved UI for smaller devices.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="timeline-item left" data-category="bugfix">
                        <div class="timeline-content">
                            <h3>Minor Bug Fixes (v1.1.1)</h3>
                            <p class="update-date-text">August 10, 2023</p>
                            <span class="update-category-badge bugfix">Bug Fix</span>
                            <div class="update-description">
                                <p>Addressed several minor bugs reported by users, including an issue with date formatting in reports.</p>
                            </div>
                        </div>
                    </div>
                    <div class="timeline-item right" data-category="announcement">
                        <div class="timeline-content">
                            <h3>WavePass Launch! (v1.0.0)</h3>
                            <p class="update-date-text">June 20, 2023</p>
                            <span class="update-category-badge announcement">Announcement</span>
                            <div class="update-description">
                                <p>We are thrilled to announce the official launch of WavePass! Our modern attendance tracking system is now live.</p>
                                <ul>
                                    <li>Real-time attendance tracking.</li>
                                    <li>Basic reporting features.</li>
                                    <li>Secure user authentication.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <!-- Add more timeline items here -->
                </div>
                <div id="no-updates-message" class="no-updates-message" style="display: none;">
                    No updates found matching your criteria.
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
                        <li><a href="index.php#about-us"><i class="fas fa-chevron-right"></i> About Us</a></li>
                        <li><a href="updates.php"><i class="fas fa-chevron-right"></i> Updates</a></li>
                        <li><a href="index.php#contact"><i class="fas fa-chevron-right"></i> Contact</a></li> 
                        <li><a href="index.php#faq"><i class="fas fa-chevron-right"></i> FAQ</a></li> 
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Resources</h3>
                    <ul class="footer-links">
                        <li><a href="blog.php"><i class="fas fa-chevron-right"></i> Blog</a></li>
                        <li><a href="help.php"><i class="fas fa-chevron-right"></i> Help Center</a></li>
                        <li><a href="webinars.php"><i class="fas fa-chevron-right"></i> Webinars</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul class="footer-links">
                        <li><a href="mailto:info@wavepass.pl"><i class="fas fa-envelope"></i> info@wavepass.pl</a></li> 
                        <li><a href="tel:+50123456789"><i class="fas fa-phone"></i> +50 123 456 789</a></li> 
                        <li>
                             <a href="https://maps.app.goo.gl/TRmU5TDWmBpGfQan9" target="_blank" rel="noopener noreferrer" title="View on Google Maps">
                                <i class="fas fa-map-marker-alt"></i> Księdza Piotra Ściegiennego 49, Katowice
                            </a> 
                        </li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© <?php echo date("Y"); ?> WavePass All rights reserved. | <a href="privacy.php">Privacy Policy</a> | <a href="terms.php">Terms of Service</a></p>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button id="scrollToTopBtn" title="Go to top">
        <span class="material-symbols-outlined">arrow_upward</span>
    </button>


    <script>
        // Mobile Menu Toggle (Same as index.php)
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
                    // For direct page links like updates.php, menu will close automatically.
                    // If it's an anchor link on the *same* page (not applicable here for updates.php), then close.
                    if (link.getAttribute('href').startsWith('#')) {
                         hamburger.classList.remove('active');
                        mobileMenu.classList.remove('active');
                        body.style.overflow = '';
                    }
                });
            });
        }
        
        // Smooth scrolling (Mainly for index.php, but harmless here)
        document.querySelectorAll('a[href^="#"]').forEach(anchor => { // Changed to 'a[href*="#"]' to also catch index.php#section links
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#' || href.startsWith('updates.php#')) { // Only prevent default for on-page hash links
                     if (href.startsWith('#')) e.preventDefault(); // Prevent default for simple hash links on current page
                    const targetId = href.substring(href.indexOf('#')); // Get the hash part
                    if (document.querySelector(targetId)) { // Check if element exists on current page
                        const targetElement = document.querySelector(targetId);
                        const headerHeight = document.querySelector('header') ? document.querySelector('header').offsetHeight : 0;
                        const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - headerHeight;
                        
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });
                    }
                }
                // Allow default behavior for links to other pages like index.php#features
            });
        });
        
        
        // Add shadow to header on scroll (Same as index.php)
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

        // Scroll to Top Button Functionality (Same as index.php)
        const scrollToTopBtn = document.getElementById("scrollToTopBtn");
        if (scrollToTopBtn) {
            window.onscroll = function() {
                if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
                    scrollToTopBtn.classList.add("show");
                } else {
                    scrollToTopBtn.classList.remove("show");
                }
            };
            scrollToTopBtn.addEventListener("click", function() {
                window.scrollTo({ top: 0, behavior: "smooth" });
            });
        }

        // Updates Page Specific JS: Search and Filter
        const searchInput = document.getElementById('search-updates');
        const categoryFilter = document.getElementById('filter-category');
        const timelineItems = document.querySelectorAll('#timeline-updates-list .timeline-item');
        const noUpdatesMessage = document.getElementById('no-updates-message');
        const clearFiltersBtn = document.getElementById('clear-filters-btn');

        function applyFiltersAndSearch() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedCategory = categoryFilter.value;
            let itemsVisible = 0;

            timelineItems.forEach(item => {
                const title = item.querySelector('h3').textContent.toLowerCase();
                const description = item.querySelector('.update-description').textContent.toLowerCase();
                const itemCategory = item.dataset.category;

                const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
                const matchesCategory = selectedCategory === 'all' || itemCategory === selectedCategory;

                if (matchesSearch && matchesCategory) {
                    item.style.display = ''; // Reset to default (flex or block)
                    itemsVisible++;
                } else {
                    item.style.display = 'none';
                }
            });

            if (noUpdatesMessage) {
                noUpdatesMessage.style.display = itemsVisible === 0 ? 'block' : 'none';
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyFiltersAndSearch);
        }
        if (categoryFilter) {
            categoryFilter.addEventListener('change', applyFiltersAndSearch);
        }
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => {
                if(searchInput) searchInput.value = '';
                if(categoryFilter) categoryFilter.value = 'all';
                applyFiltersAndSearch();
            });
        }


        // Timeline alternating classes (Same as index.php, but ensure it runs for the items on this page)
        const timelineItemsOnUpdatesPage = document.querySelectorAll('#timeline-updates-list .timeline-item');
        timelineItemsOnUpdatesPage.forEach((item, index) => {
            // Clear existing side classes first
            item.classList.remove('left', 'right');
            
            if ((index % 2) === 0) { 
                item.classList.add('left');
            } else { 
                item.classList.add('right');
            }
        });
         // Active Nav Link for current page
        document.addEventListener('DOMContentLoaded', () => {
            const currentPage = window.location.pathname.split('/').pop(); // e.g., 'updates.php'
            const navLinksDesktop = document.querySelectorAll('.nav-links a:not(.btn)');
            const navLinksMobile = document.querySelectorAll('.mobile-links a');

            navLinksDesktop.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active-nav-link');
                } else {
                    link.classList.remove('active-nav-link');
                }
            });
            navLinksMobile.forEach(link => {
                 if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active-nav-link');
                } else {
                    link.classList.remove('active-nav-link');
                }
            });
        });


    </script>
</body>
</html>