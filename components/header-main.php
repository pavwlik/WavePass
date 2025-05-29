<?php
// --- SESSION MANAGEMENT ---
// THIS MUST BE THE VERY FIRST THING IN YOUR SCRIPT
if (session_status() == PHP_SESSION_NONE) {
}

// --- ERROR REPORTING (Good for development) ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo" translate="no">
                <img src="./imgs/logo.png" alt="WavePass Logo" class="logo-img">
                    Wave<span>Pass</span>
                </a>
                <ul class="nav-actions-group nav-links">
                    <li><a href="index.php#features">Features</a></li>
                    <li><a href="index.php#how-it-works">How It Works</a></li>
                    <li><a href="index.php#about-us">About Us</a></li>
                    <li><a href="index.php#contact">Contact</a></li>
                    <li><a href="index.php#faq">FAQ</a></li>
                    <?php if (isset($_SESSION['loggedin'])) : ?>
                        <li class="nav-item-login"><a href="dashboard.php" class="btn"><span aria-hidden="true" translate="no" class="material-symbols-outlined">dashboard</span> Dashboard</a></li>
                    <?php else : ?>
                        <li class="nav-item-login"><a href="login.php" class="btn <?php echo basename($_SERVER['PHP_SELF']) === 'login.php' ? 'active-link' : ''; ?>"><span aria-hidden="true" translate="no" class="material-symbols-outlined">account_circle</span> Login</a></li>
                    <?php endif; ?>
                </ul>
                <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
            </nav>
        </div>
        <div class="mobile-menu" id="mobileMenu">
            <span class="close-btn" id="closeMenu"></i></span>
            <ul class="mobile-links">
                <li><a href="index.php#features">Features</a></li>
                <li><a href="index.php#how-it-works">How It Works</a></li>
                <li><a href="index.php#about-us">About Us</a></li>
                <li><a href="index.php#contact">Contact</a></li>
                <li><a href="index.php#faq">FAQ</a></li>
            </ul>
            <?php if (isset($_SESSION['loggedin'])) : ?>
                <a href="dashboard.php" class="btn"><span aria-hidden="true" translate="no" class="material-symbols-outlined">dashboard</span> Dashboard</a>
            <?php else : ?>
                <a href="login.php" class="btn <?php echo basename($_SERVER['PHP_SELF']) === 'login.php' ? 'active-link' : ''; ?>"><span aria-hidden="true" translate="no" class="material-symbols-outlined">person</span> Login</a>
            <?php endif; ?>
        </div>
    </header>

    <main>
    
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: var(--light-color); display: flex; flex-direction: column; min-height: 100vh; overflow-x: hidden; }
        main { flex-grow: 1; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
        header { background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: fixed; width: 100%; top: 0; z-index: 1000; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; height: 80px; }
        .logo { font-size: 1.8rem; font-weight: 800; color: var(--primary-color); text-decoration: none; display: flex; align-items: center;  }
        .logo i { font-size: 1.5rem; }

        .nav-actions-group { /* Obal pre navigačné odkazy a hamburger na pravej strane */
        display: flex;
        align-items: center;
        gap: 1rem; /* Odsadenie medzi ul.nav-links a .hamburger */
    }
    .logo-img {
    height: 35px; 
    width: auto;
    margin-right: 0.5rem; /* Menšie odsadenie, ak je admin badge blízko */
}

    .nav-links {
        display: flex;
        list-style: none;
        align-items: center;
        gap: 0.5rem; 
        margin: 0;
        padding: 0;
}
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
        @media (max-width: 768px) { .nav-links { display: none; } .hamburger { display: flex; } }
        
    
    </style>

    <script>
        // Mobile menu functionality
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
        
        // Smooth scrolling for anchor links
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
                    window.location.href = href; 
                    return;
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
        
        // Header shadow on scroll
        const pageHeader = document.querySelector('header');
        if (pageHeader) {
            window.addEventListener('scroll', () => {
                pageHeader.style.boxShadow = (window.scrollY > 10) ? '0 4px 10px rgba(0,0,0,0.05)' : '0 2px 10px rgba(0,0,0,0.05)';
            });
        }
        
        // Set active nav link based on current page
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
        
        // Initialize
        setActiveNavLink();
    </script>