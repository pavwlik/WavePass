<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WavePass - Pricing Plans</title>
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
            --success-color: #4cc9f0; /* Can be used for checkmarks or highlights */
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
            max-width: 1200px; /* Standard container width */
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
        .nav-links a:not(.btn).active, /* Style for active link */
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
        .mobile-links a.active, /* Style for active link in mobile */
        .mobile-links a:hover { color: var(--primary-color); background-color: rgba(67, 97, 238, 0.07); }
        .mobile-menu .btn, .mobile-menu .btn-outline { margin-top: 1.5rem; width: 100%; max-width: 280px; padding: 0.8rem 1.5rem; font-size: 0.95rem; text-align: center; }
        .mobile-menu .btn .material-symbols-outlined { font-size: 1.2em; vertical-align: middle; margin-right: 4px; }
        .mobile-menu .btn-outline { margin-top: 1rem; }
        .close-btn { position: absolute; top: 25px; right: 25px; font-size: 1.6rem; color: var(--dark-color); cursor: pointer; transition: var(--transition); padding: 0.5rem; line-height: 1; }
        .close-btn:hover { color: var(--primary-color); transform: rotate(90deg); }

        /* Page Title Section */
        .page-title-section {
            padding: 3rem 0;
            background-color: #f0f4ff;
            color: var(--white);
            text-align: center;
        }
        .page-title-section h1 {
            color: #000;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }
        .page-title-section p {
            color: var(--gray-color);
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Pricing Section Styles */
        .pricing-section {
            padding: 4rem 0;
        }
        .pricing-toggle {
            text-align: center;
            margin-bottom: 3rem;
        }
        .toggle-switch {
            display: inline-flex;
            align-items: center;
            background-color: var(--light-gray);
            border-radius: 25px;
            padding: 0.3rem;
            cursor: pointer;
        }
        .toggle-switch span {
            padding: 0.5rem 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-color);
            border-radius: 20px;
            transition: var(--transition);
        }
        .toggle-switch span.active {
            background-color: var(--primary-color);
            color: var(--white);
            box-shadow: 0 2px 10px rgba(67, 97, 238, 0.3); /* var(--primary-color) with alpha */
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            align-items: stretch; /* Make cards same height if content differs */
        }
        .pricing-card {
            background-color: var(--white);
            padding: 2.5rem 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            text-align: center;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
            border: 1px solid var(--light-gray);
        }
        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .pricing-card.featured {
            border: 2px solid var(--primary-color);
            transform: scale(1.05); /* Make featured plan slightly larger */
            position: relative; /* For badge */
            z-index: 10;
        }
        .pricing-card.featured:hover {
             transform: scale(1.08) translateY(-5px);
        }
        .featured-badge {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--primary-color);
            color: var(--white);
            padding: 0.3rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .plan-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        .plan-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        .plan-description {
            font-size: 0.9rem;
            color: var(--gray-color);
            margin-bottom: 1.5rem;
            min-height: 40px; 
        }
        .plan-price {
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        .plan-price .term {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-color);
        }
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 2rem 0;
            text-align: left;
            flex-grow: 1; 
        }
        .plan-features li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.8rem;
            font-size: 0.95rem;
            color: var(--dark-color);
        }
        .plan-features li .material-symbols-outlined,
        .plan-features li .fas {
            color: var(--success-color); 
            font-size: 1.2em;
        }
        .plan-cta {
            margin-top: auto; 
        }
        .plan-cta .btn {
            width: 100%;
        }
        
        /* Call to Action Section */
        .cta-section {
            padding: 4rem 0;
            background-color: var(--light-gray);
            text-align: center;
        }
        .cta-section h2 {
            font-size: 2rem;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        .cta-section p {
            font-size: 1.1rem;
            color: var(--gray-color);
            max-width: 600px;
            margin: 0 auto 2rem auto;
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
            .page-title-section h1 { font-size: 2.2rem; }
        }

        @media (max-width: 768px) {
             .pricing-grid {
                grid-template-columns: 1fr; 
            }
            .pricing-card.featured {
                transform: scale(1); 
            }
             .pricing-card.featured:hover {
                transform: translateY(-10px); 
            }
            .page-title-section h1 { font-size: 2rem; }
            .page-title-section p { font-size: 1rem; }
            .cta-section h2 {font-size: 1.8rem;}
        }
         @media (max-width: 576px) {
            .toggle-switch span { padding: 0.5rem 1rem; font-size: 0.85rem;}
         }

    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="index.html" class="logo"> 
                    <i class="fas fa-chalkboard-teacher"></i>
                    Wave<span>Pass</span>
                </a>
                
                <ul class="nav-links">
                    <li><a href="index.html#features">Features</a></li>
                    <li><a href="index.html#how-it-works">How It Works</a></li>
                    <li><a href="pricing.html" class="active">Pricing</a></li> 
                    <li><a href="index.html#contact">Contact</a></li> 
                    <li><a href="index.html#faq">FAQ</a></li> 
                    <li><a href="help.php">Help Center</a></li>
                    <li><a href="login.php" class="btn btn-outline">Login</a></li> 
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
                 <li><a href="index.html#features">Features</a></li>
                 <li><a href="index.html#how-it-works">How It Works</a></li>
                 <li><a href="pricing.html" class="active">Pricing</a></li>
                 <li><a href="index.html#contact">Contact</a></li>
                 <li><a href="index.html#faq">FAQ</a></li> 
                 <li><a href="help.php">Help Center</a></li>
            </ul>
            <a href="login.php" class="btn" style="margin-top:1rem;">Login</a>
        </div>
    </header>

    <!-- Main Content for Pricing Page -->
    <main>
        <section class="page-title-section">
            <div class="container">
                <h1>Flexible Pricing for Every School</h1>
                <p>Choose a plan that fits your institution's size and needs. Simple, transparent, and powerful.</p>
            </div>
        </section>

        <section class="pricing-section">
            <div class="container">
                <div class="pricing-toggle">
                    <div class="toggle-switch">
                        <span class="monthly active" data-period="monthly">Monthly</span>
                        <span class="yearly" data-period="yearly">Yearly (Save 20%)</span>
                    </div>
                </div>

                <div class="pricing-grid">
                    <!-- Plan 1: Basic -->
                    <div class="pricing-card">
                        <span class="material-symbols-outlined plan-icon">rocket_launch</span>
                        <h3 class="plan-name">Starter</h3>
                        <p class="plan-description">Perfect for small schools or getting started.</p>
                        <div class="plan-price">
                            $<span class="price-value" data-monthly="19" data-yearly="180">19</span>
                            <span class="term">/month</span>
                        </div>
                        <ul class="plan-features">
                            <li><span class="material-symbols-outlined">check_circle</span> Up to 50 Teachers</li>
                            <li><span class="material-symbols-outlined">check_circle</span> Real-time Attendance Tracking</li>
                            <li><span class="material-symbols-outlined">check_circle</span> Basic Reporting</li>
                            <li><span class="material-symbols-outlined">check_circle</span> Mobile App Access</li>
                            <li><span class="material-symbols-outlined">check_circle</span> Email Support</li>
                            <li><span class="material-symbols-outlined">remove</span> Advanced Reporting</li>
                            <li><span class="material-symbols-outlined">remove</span> API Access</li>
                        </ul>
                        <div class="plan-cta">
                            <a href="get-started.html?plan=starter" class="btn btn-outline">Choose Starter</a>
                        </div>
                    </div>

                    <!-- Plan 2: Standard (Featured) -->
                    <div class="pricing-card featured">
                        <div class="featured-badge">Most Popular</div>
                        <span class="material-symbols-outlined plan-icon">star</span>
                        <h3 class="plan-name">Pro</h3>
                        <p class="plan-description">Ideal for medium-sized schools needing more features.</p>
                        <div class="plan-price">
                            $<span class="price-value" data-monthly="49" data-yearly="470">49</span>
                            <span class="term">/month</span>
                        </div>
                        <ul class="plan-features">
                            <li><span class="material-symbols-outlined">check_circle</span> Up to 200 Teachers</li>
                            <li><span class="material-symbols-outlined">check_circle</span> Real-time Attendance Tracking</li>
                            <li><span class="material-symbols-outlined">check_circle</span> Advanced Reporting & Analytics</li>
                            <li><span class="material-symbols-outlined">check_circle</span> Mobile App Access for All Staff</li>
                            <li><span class="material-symbols-outlined">check_circle</span> Automated Alerts</li>
                            <li><span class="material-symbols-outlined">check_circle</span> Priority Email Support</li>
                            <li><span class="material-symbols-outlined">remove</span> API Access</li>
                        </ul>
                        <div class="plan-cta">
                            <a href="get-started.html?plan=pro" class="btn">Choose Pro</a>
                        </div>
                    </div>

                    <!-- Plan 3: Premium -->
                    <div class="pricing-card">
                        <span class="material-symbols-outlined plan-icon">workspace_premium</span>
                        <h3 class="plan-name">Enterprise</h3>
                        <p class="plan-description">Comprehensive solution for large institutions.</p>
                        <div class="plan-price">
                            $<span class="price-value" data-monthly="99" data-yearly="950">99</span>
                            <span class="term">/month</span>
                        </div>
                        <ul class="plan-features">
                            <li><span class="material-symbols-outlined">check_circle</span> Unlimited Teachers</li>
                            <li><span class="material-symbols-outlined">check_circle</span> All Pro Features</li>
                            <li><span class="material-symbols-outlined">check_circle</span> API Access & Integrations</li>
                            <li><span class="material-symbols-outlined">check_circle</span> Dedicated Account Manager</li>
                            <li><span class="material-symbols-outlined">check_circle</span> Custom Onboarding & Training</li>
                            <li><span class="material-symbols-outlined">check_circle</span> 24/7 Phone Support</li>
                            <li><span class="material-symbols-outlined">check_circle</span> SLA Agreement</li>
                        </ul>
                        <div class="plan-cta">
                            <a href="get-started.html?plan=enterprise" class="btn btn-outline">Choose Enterprise</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta-section">
            <div class="container">
                <h2>Not Sure Which Plan is Right for You?</h2>
                <p>Our team is happy to help you find the perfect fit for your school's unique requirements. Contact us for a personalized consultation or a custom quote.</p>
                <a href="index.html#contact" class="btn"><span class="material-symbols-outlined">contact_support</span> Contact Sales</a>
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
                        <li><a href="help.php"><i class="fas fa-chevron-right"></i> Help Center</a></li>
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
                <p>© <?php echo date("Y"); ?> WavePass. All rights reserved. | <a href="privacy.php">Privacy Policy</a> | <a href="terms.php">Terms of Service</a></p>
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

        // Pricing Toggle Functionality
        const toggleSwitch = document.querySelector('.toggle-switch');
        const priceValues = document.querySelectorAll('.price-value');
        const priceTerms = document.querySelectorAll('.plan-price .term');

        if (toggleSwitch && priceValues.length > 0 && priceTerms.length > 0) {
            const monthlyToggle = toggleSwitch.querySelector('.monthly');
            const yearlyToggle = toggleSwitch.querySelector('.yearly');

            toggleSwitch.addEventListener('click', (e) => {
                const selectedPeriod = e.target.dataset.period;
                if (!selectedPeriod) return; 

                if (selectedPeriod === 'monthly') {
                    monthlyToggle.classList.add('active');
                    yearlyToggle.classList.remove('active');
                    priceValues.forEach(pv => pv.textContent = pv.dataset.monthly);
                    priceTerms.forEach(pt => pt.textContent = '/month');
                } else if (selectedPeriod === 'yearly') {
                    yearlyToggle.classList.add('active');
                    monthlyToggle.classList.remove('active');
                    priceValues.forEach(pv => pv.textContent = pv.dataset.yearly);
                    priceTerms.forEach(pt => pt.textContent = '/year');
                }
            });
        }

    </script>
</body>
</html>