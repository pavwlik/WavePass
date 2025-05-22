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
                <li><a href="#features">Features</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="#about-us">About Us</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="#faq">FAQ</a></li> 
            </ul>
            <a href="login.php" class="btn"><span class="material-symbols-outlined">person</span> Login</a>
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
    
        </style>