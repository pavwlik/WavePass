<?php
require_once "db.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ========= Primary Meta Tags for SEO & Search Result Appearance ========= -->
    <title>The Story of WavePass: From Idea to Reality | WavePass Blog</title>
    <meta name="description" content="Discover the journey behind WavePass. Learn how our student team conceptualized, developed, and overcame challenges to create an innovative teacher attendance system.">

    <!-- Specify the canonical (preferred) URL for this page -->
    <link rel="canonical" href="https://www.your-wavepass-domain.com/blog.php"> <!-- !! REPLACE with your actual URL !! -->

    <!-- Favicon (ensure these paths are correct) -->
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link rel="icon" href="assets/favicon.png" type="image/png">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">

    <!-- ========= Open Graph / Facebook Meta Tags ========= -->
    <meta property="og:title" content="The Story of WavePass: From Idea to Reality | WavePass Blog">
    <meta property="og:description" content="Discover the journey behind WavePass. Learn how our student team conceptualized, developed, and overcame challenges to create an innovative teacher attendance system.">
    <meta property="og:image" content="https://www.your-wavepass-domain.com/assets/og-blog-story-image.png"> <!-- !! REPLACE with URL to a relevant blog image !! -->
    <meta property="og:url" content="https://www.your-wavepass-domain.com/blog.php"> <!-- !! REPLACE with your actual URL !! -->
    <meta property="og:type" content="article">
    <meta property="article:published_time" content="<?php echo date('Y-m-d\TH:i:sP'); ?>">
    <meta property="article:author" content="The WavePass Team">
    <meta property="og:site_name" content="WavePass">

    <!-- ========= Twitter Card Meta Tags ========= -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="The Story of WavePass: From Idea to Reality | WavePass Blog">
    <meta name="twitter:description" content="Discover the journey behind WavePass. Learn how our student team conceptualized, developed, and overcame challenges to create an innovative teacher attendance system.">
    <meta name="twitter:image" content="https://www.your-wavepass-domain.com/assets/twitter-blog-story-image.png"> <!-- !! REPLACE with URL to an image !! -->

    <!-- ========= Other Meta Tags ========= -->
    <meta name="author" content="The WavePass Team">
    <meta name="keywords" content="WavePass story, project journey, student project, edtech development, teacher attendance system, SPŠE Ječná, VOŠ project, RFID attendance">

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
            padding-top: 100px; /* Account for fixed header */
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

        /* Header & Navigation */
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
        }
        .nav-links a:not(.btn):hover,
        .nav-links a:not(.btn).active-link {
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
        .nav-links .btn .material-symbols-outlined {
            font-size: 1.2em; 
            vertical-align: middle; 
            margin-right: 4px; 
        }
        .nav-links .btn:hover{
            background-color: var(--primary-dark);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.3);
            transform: translateY(-2px);
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
        .mobile-links a.active-link {
             color: var(--primary-color); background-color: rgba(67, 97, 238, 0.1);
        }
        .mobile-menu .btn { margin-top: 2rem; width: 100%; max-width: 200px; }
        .close-btn {
            position: absolute; top: 30px; right: 30px; font-size: 1.8rem;
            color: var(--dark-color); cursor: pointer; transition: var(--transition);
        }
        .close-btn:hover { color: var(--primary-color); transform: rotate(90deg); }

        /* General Section Styles for Blog Page */
        .section { padding: 4rem 0; }
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
        
        /* Blog Post Specific Styles */
        .blog-post-container {
            background-color: var(--white);
            padding: 3rem; /* Internal padding for the content box */
            border-radius: 12px;
            box-shadow: var(--shadow);
            /* Max-width is handled by the parent .container */
        }
        .blog-header {
            text-align: center;
            margin-bottom: 3rem; /* Increased margin */
            border-bottom: 1px solid var(--light-gray);
            padding-bottom: 2.5rem; /* Increased padding */
        }
        .blog-header h1 {
            font-size: 2.8rem; /* Slightly larger */
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        .blog-meta {
            font-size: 0.95rem; /* Slightly larger */
            color: var(--gray-color);
            margin-bottom: 1.5rem;
        }
        .blog-meta span { margin: 0 0.5rem; }
        .blog-banner-image {
            width: 100%;
            max-height: 450px; /* Increased max height */
            object-fit: cover;
            border-radius: 8px;
            margin-top: 1.5rem; /* Increased margin */
            box-shadow: 0 6px 20px rgba(0,0,0,0.12); /* Enhanced shadow */
        }

        .blog-content-section {
            margin-bottom: 3.5rem; /* Increased space between sections */
        }

        /* Alternating Layout Styles */
        .alternating-layout {
            display: flex;
            align-items: flex-start; /* Align items to the top */
            gap: 3rem; /* Increased gap */
        }
        .alternating-layout .text-content {
            flex: 1; /* Takes remaining space */
            min-width: 0; /* Prevents overflow */
        }
        .alternating-layout .image-content {
            flex: 0 0 40%; /* Image takes up 40% of the flex container's width */
            max-width: 40%;
        }
        .alternating-layout .image-content img {
            width: 100%;
            height: auto;
            border-radius: 12px; /* More rounded corners */
            box-shadow: 0 5px 18px rgba(0,0,0,0.1); /* Softer shadow */
        }
        .alternating-layout .image-content figcaption {
            font-size: 0.9rem;
            color: var(--gray-color);
            margin-top: 0.75rem; /* Increased margin */
            text-align: center;
            font-style: italic;
        }
        
        /* Logic for alternating order */
        .blog-content-section:nth-child(even) .alternating-layout .image-content {
            order: -1; /* Moves image to the left */
        }

        /* Styles for content within text-content or full-width sections */
        .blog-content-section h2 {
            font-size: 2rem; /* Slightly larger */
            color: var(--primary-color);
            margin-bottom: 1.5rem; /* Increased margin */
            padding-bottom: 0.75rem; /* Increased padding */
            border-bottom: 2px solid var(--primary-color);
            display: inline-block;
        }
        .blog-content-section p,
        .blog-content-section ul li {
            font-size: 1.1rem; /* Slightly larger for readability */
            line-height: 1.75; /* Increased line height */
            color: #333; 
            margin-bottom: 1.25rem; /* Increased margin */
        }
        .blog-content-section ul {
            list-style-position: inside;
            padding-left: 1rem; /* Or outside with margin if preferred */
        }
        .blog-content-section ul li {
            margin-bottom: 0.75rem; /* Increased margin */
        }
        .blog-content-section strong {
            color: var(--primary-dark);
        }
        .blog-content-section a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .blog-content-section a:hover {
            text-decoration: underline;
        }
        blockquote {
            border-left: 5px solid var(--primary-color); /* Thicker border */
            margin: 2rem 0; /* Increased margin */
            font-style: italic;
            color: var(--gray-color);
            background-color: rgba(67, 97, 238, 0.04); /* Subtler background */
            padding: 1.5rem 2rem; /* Increased padding */
            border-radius: 0 8px 8px 0;
        }
        blockquote p {
            margin-bottom: 0;
            font-size: 1.1rem; /* Match paragraph font size */
        }

        /* Full width sections (for Milestones, Acknowledgements) */
        .full-width-content .text-content {
            max-width: 900px; /* Limit width for readability if desired */
            margin-left: auto;
            margin-right: auto;
        }


        /* Footer */
        footer { background-color: var(--dark-color); color: var(--white); padding: 5rem 0 2rem; margin-top: 4rem; }
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

        /* Responsive Styles */
        @media (max-width: 992px) { /* Tablet breakpoint */
            .blog-post-container { padding: 2rem; }
            .blog-header h1 { font-size: 2.4rem; }
            .alternating-layout {
                flex-direction: column;
                gap: 2rem;
            }
            .alternating-layout .text-content,
            .alternating-layout .image-content {
                flex-basis: auto; /* Reset flex-basis */
                max-width: 100%; /* Allow full width */
                width: 100%;
            }
            .alternating-layout .image-content {
                order: -1 !important; /* Image always first when stacked */
                margin-bottom: 1.5rem;
            }
             .blog-content-section:nth-child(even) .alternating-layout .image-content {
                order: -1 !important; /* Ensure image is still first for even items when stacked */
            }
        }

        @media (max-width: 768px) { /* Mobile breakpoint */
            main { padding-top: 80px; }
            .nav-links { display: none; }
            .hamburger { display: flex; flex-direction: column; justify-content: space-between; }
            
            .blog-post-container { padding: 1.5rem; }
            .blog-header h1 { font-size: 2rem; }
            .blog-banner-image { max-height: 300px; }
            .blog-content-section h2 { font-size: 1.7rem; }
            .blog-content-section p, .blog-content-section ul li { font-size: 1rem; line-height: 1.7; }
            blockquote { padding: 1rem 1.5rem; }

            .footer-content { grid-template-columns: 1fr 1fr; }
            .btn, .nav-links .btn, .mobile-menu .btn { width: auto; max-width: 300px; }
        }

         @media (max-width: 576px) {
            .blog-header h1 { font-size: 1.8rem; }
            .blog-content-section h2 { font-size: 1.5rem; }
            .blog-banner-image { max-height: 250px; }
            .footer-content { grid-template-columns: 1fr; }
            .blog-post-container { padding: 1.5rem 1rem; }
             .btn, .nav-links .btn, .mobile-menu .btn  { width: 100%; max-width: 100%; }
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
    </style>
</head>
<body>
    
    <!-- Header -->
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
            <span class="close-btn" id="closeMenu"></span>
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
    <section class="page-title-section section">
            <div class="container">
                <div class="section-title">
                    <h1>Our Journey</h1>
                    <p>Thank you EU!</p>
                </div>
            </div>
        </section>
        <section class="section">
            <div class="container">
                <article class="blog-post-container">
                    <!-- Section 1: The Spark (Text Left, Image Right) -->
                    <section class="blog-content-section">
                        <div class="alternating-layout">
                            <div class="text-content">
                                <h2>The Start: Sponsored by Erasmus</h2>
                                <p>Every meaningful project starts with identifying a genuine problem. For us, the students behind WavePass—Pavel Bureš, Kryštof Topinka, Filip Elznic, Tomáš Kočí, and Zdeněk Čepelák—the journey began within the familiar halls of our own educational institution, SPŠE Ječná and VOŠ. We observed firsthand the daily administrative load on teachers and staff, particularly the often cumbersome and manual process of attendance tracking. It was clear that a more efficient, modern solution was needed – one that could save time, reduce errors, and enhance overall school management.</p>
                                <p>The idea was to create a system that was not just a technological exercise but a practical tool. We envisioned "WavePass": an intuitive, RFID-based attendance system coupled with a user-friendly web platform, designed to simplify the lives of educators. The name itself reflects the ease we aimed for – a quick 'wave' of a card to seamlessly 'pass' through the attendance process.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Section 2: The Blueprint (Image Left, Text Right) -->
                    <section class="blog-content-section">
                        <div class="alternating-layout">
                             <div class="image-content">
                                <figure>
                                    <img src="imgs/IMG_5638.JPEG" alt="image">
                                    <figcaption>From wireframes to working prototypes: iterating on the WavePass design.</figcaption>
                                </figure>
                            </div>
                            <div class="text-content">
                                <h2>The Blueprint: Design and Development</h2>
                                <p>With a clear vision, we embarked on the design and development phase. Our diverse team brought a range of skills: Pavel spearheaded the full-stack development and focused on creating an intuitive UI/UX. Kryštof and Filip architected the robust backend and managed the database, the core engine of WavePass. Tomáš and Zdeněk took on the critical roles of Quality Assurance, meticulously testing every component to ensure a reliable and smooth experience for users.</p>
                                <p>We adopted an agile approach, breaking the project into sprints, allowing for iterative development and regular feedback. Our technology stack was carefully selected: PHP for server-side logic, MySQL for data storage, and a combination of HTML, CSS, and JavaScript to build a responsive and engaging front-end. We prioritized clean, maintainable code, envisioning a system that could adapt and grow.</p>
                                <blockquote>
                                    <p>"WavePass was more than just a final project; it was a passion project. We wanted to build something that could genuinely make a difference in the daily operations of a school." - Pavel Bureš, Lead Developer.</p>
                                </blockquote>
                            </div>
                        </div>
                    </section>

                    <!-- Section 3: Navigating Challenges (Text Left, Image Right) -->
                    <section class="blog-content-section">
                        <div class="alternating-layout">
                            <div class="text-content">
                                <h2>Navigating Challenges: Lessons Learned</h2>
                                <p>The path from concept to a functional product is rarely smooth. We faced our share of technical hurdles, from ensuring seamless integration between the RFID hardware and our web application to implementing robust security measures to protect sensitive school data. Time management was also a constant challenge, balancing the rigorous demands of the project with our academic responsibilities.</p>
                                <p>One particular challenge was designing a user interface that was both comprehensive and accessible to users with varying levels of technical comfort. Through multiple iterations and (simulated) user feedback sessions, we refined the UI to be as intuitive as possible. Each obstacle overcome, however, was a valuable learning opportunity, strengthening our problem-solving abilities and teamwork.</p>
                            </div>
                            <div class="image-content">
                                <figure>
                                    <img src="imgs/blog_challenges_collaboration.jpg" alt="Team collaborating to overcome development challenges">
                                    <figcaption>Problem-solving and collaboration were key to overcoming hurdles.</figcaption>
                                </figure>
                            </div>
                        </div>
                    </section>

                    <!-- Section 4: Milestones (Full Width) -->
                    <section class="blog-content-section full-width-content">
                        <div class="text-content">
                            <h2>Milestones on the WavePass Journey</h2>
                            <ul>
                                <li><strong>Proof of Concept:</strong> Successfully demonstrating the core RFID check-in/out functionality.</li>
                                <li><strong>First UI Drafts & Prototyping:</strong> Visualizing and iterating on the user experience.</li>
                                <li><strong>Database Architecture Finalized:</strong> Building a scalable and secure data foundation.</li>
                                <li><strong>Real-time Tracking Implemented:</strong> Enabling live updates for administrators.</li>
                                <li><strong>Comprehensive Reporting Module Developed:</strong> Providing valuable insights from attendance data.</li>
                                <li><strong>Beta Testing Phase:</strong> Gathering initial feedback and identifying areas for improvement.</li>
                                <li><strong>Final Project Presentation:</strong> Showcasing our collective effort and the functional WavePass system.</li>
                            </ul>
                            <p>Witnessing WavePass evolve from a simple idea to a fully functional system has been an incredibly rewarding experience for the entire team.</p>
                        </div>
                    </section>
                    
                    <!-- Section 5: Looking Ahead (Image Left, Text Right) -->
                     <section class="blog-content-section">
                        <div class="alternating-layout">
                            <div class="image-content">
                                <figure>
                                    <img src="imgs/blog_future_vision.jpg" alt="Conceptual image of WavePass's future potential">
                                    <figcaption>Envisioning the future growth and impact of WavePass.</figcaption>
                                </figure>
                            </div>
                            <div class="text-content">
                                <h2>Looking Ahead: The Future of WavePass</h2>
                                <p>While WavePass originated as a capstone project for our studies, we believe it has the potential to grow beyond the classroom. We envision future enhancements such as dedicated mobile applications for even greater convenience, advanced analytics for deeper insights into attendance patterns, and potential integrations with other widely-used school management platforms.</p>
                                <p>Our core belief is that technology should simplify, not complicate. WavePass is our contribution towards a more efficient and streamlined administrative environment in schools, allowing educators to dedicate more of their valuable time to teaching and student engagement.</p>
                            </div>
                        </div>
                    </section>

                    <!-- Section 6: Acknowledgements (Full Width) -->
                    <section class="blog-content-section full-width-content">
                         <div class="text-content">
                            <h2>Acknowledgements</h2>
                            <p>We owe a significant debt of gratitude to our teachers and mentors at SPŠE Ječná and VOŠ. Their guidance, support, and constructive feedback were instrumental throughout this project's lifecycle. We also thank our peers and anyone who took the time to test our early versions and provide valuable insights.</p>
                            <p>This journey has been a profound learning experience, not just in terms of technical skills but also in collaboration, project management, and perseverance. We are proud of what we have built together and hope WavePass can serve as both a useful tool and an inspiration.</p>
                        </div>
                    </section>

                </article>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <?php  require_once "footer.php"; ?>

     <!-- Scroll to Top Button -->
    <button id="scrollToTopBtn" title="Go to top" aria-label="Scroll to top">
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
                    if (link.getAttribute('href').startsWith('#') || 
                        link.getAttribute('href').includes('.php') || 
                        link.classList.contains('btn')) {
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
                const href = this.getAttribute('href');
                if (href === '#' || href.startsWith('#') && !document.querySelector(href)) {
                    return;
                }
                const targetElement = document.querySelector(href);
                if (targetElement && (window.location.pathname === this.pathname || this.pathname.endsWith('/') && window.location.pathname + targetElement.id === this.pathname + href.substring(1) ) ) {
                    e.preventDefault();
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

        // Scroll to Top Button Functionality
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
    </script>
</body>
</html>