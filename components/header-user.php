<?php
// This file is assumed to be included by other PHP pages like dashboard.php, profile.php etc.
// Session should already be started. $sessionUserId, $sessionFirstName, $sessionRole, $_SESSION['profile_photo'] should be available.
// $currentPage should be defined by the including script.

if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF']); // Fallback if not set by parent
}

$profile_photo_url = null;
if (isset($_SESSION["profile_photo"]) && !empty($_SESSION["profile_photo"])) {
    // Construct the web-accessible path to the photo
    // Assuming PROFILE_UPLOAD_DIR is defined and accessible (e.g., 'profile_photos/')
    // If header-employee-panel.php is in 'components/' and profile_photos is in parent: '../profile_photos/'
    // For safety, ensure PROFILE_UPLOAD_DIR ends with a slash.
    $base_upload_path = defined('PROFILE_UPLOAD_DIR') ? PROFILE_UPLOAD_DIR : 'profile_photos/'; // Default if not defined
    
    // Ensure no leading slashes from DB if $base_upload_path is already relative root
    $photo_filename = ltrim(basename($_SESSION["profile_photo"]), '/'); 
    $potential_photo_path = $base_upload_path . $photo_filename;

    // Simple check if file likely exists (more robust check would be on server filesystem path)
    // This relies on the URL being correctly formed.
    // For actual file_exists, you'd need server path: $_SERVER['DOCUMENT_ROOT'] . '/' . $potential_photo_path
    $profile_photo_url = htmlspecialchars($potential_photo_path);
}
?>
<header>
    <div class="container">
        <nav class="navbar">
            <a href="index.php" class="logo">
                <img src="imgs/logo.png" alt="WavePass Logo" class="logo-img">
                Wave<span>Pass</span>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="<?php if ($currentPage === 'dashboard.php') echo 'active-nav-link'; ?>">My Dashboard</a></li>
                <li><a href="my_attendance_log.php" class="<?php if ($currentPage === 'my_attendance_log.php') echo 'active-nav-link'; ?>">Attendance Log</a></li>
                <li><a href="absences.php" class="<?php if ($currentPage === 'absences.php') echo 'active-nav-link'; ?>">Absence</a></li>
                <li><a href="messages.php" class="<?php if ($currentPage === 'messages.php') echo 'active-nav-link'; ?>">Messages</a></li>
                <li>
                    <a href="profile.php?section=profile" class="nav-profile-link <?php if ($currentPage === 'profile.php' || $currentPage === 'profile.php') echo 'active-nav-link'; ?>"> 
                        <?php if ($profile_photo_url): ?>
                            <img src="<?php echo $profile_photo_url; ?>" alt="Profile" class="nav-user-photo">
                        <?php else: ?>
                            <span class="material-symbols-outlined">account_circle</span>
                        <?php endif; ?>
                        <?php echo $sessionFirstName; ?>
                    </a>
                </li>
                <li><a href="logout.php" class="btn btn-outline">Logout</a></li> 
            </ul>
            <div class="hamburger" id="hamburger">
                <span></span><span></span><span></span>
            </div>
        </nav>
    </div>
</header>
<div class="mobile-menu" id="mobileMenu"> 
    <span class="close-btn" id="closeMenu"></i></span>
    <ul class="mobile-links">
         <li><a href="dashboard.php" class="<?php if ($currentPage === 'dashboard.php') echo 'active-nav-link'; ?>">My Dashboard</a></li>
         <li><a href="my_attendance_log.php" class="<?php if ($currentPage === 'my_attendance_log.php') echo 'active-nav-link'; ?>">Attendance Log</a></li>
         <li><a href="absences.php" class="<?php if ($currentPage === 'absences.php') echo 'active-nav-link'; ?>">Absence</a></li>
         <li><a href="messages.php" class="<?php if ($currentPage === 'messages.php') echo 'active-nav-link'; ?>">Messages</a></li>
         <li>
            <a href="profile.php?section=profile" class="<?php if ($currentPage === 'profile.php' || $currentPage === 'profile.php') echo 'active-nav-link'; ?>">
                <?php if ($profile_photo_url): ?>
                    <img src="<?php echo $profile_photo_url; ?>" alt="Profile" class="nav-user-photo mobile-nav-user-photo">
                    My Profile
                <?php else: ?>
                    My Profile
                <?php endif; ?>
            </a>
        </li>
    </ul>
    <a href="logout.php" class="btn btn-outline">Log out</a> 
</div>

<style>
/* :root variables are essential if used in the styles below */
:root {
    --primary-color: #4361ee;
    --primary-dark: #3a56d4;
    /* --secondary-color: #3f37c9; /* Not directly used by header styles below, but included for completeness if your header uses it */
    --dark-color: #1a1a2e;
    /* --light-color: #f8f9fa; /* Not directly used by header styles below */
    /* --gray-color: #6c757d; /* Not directly used by header styles below */
    /* --light-gray: #e9ecef; /* Not directly used by header styles below */
    --white: #ffffff; /* Used for header background */
    /* --success-color: #4cc9f0; */
    /* --warning-color: #f8961e; */
    /* --danger-color: #f72585; */
    /* --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); /* General shadow, header might have its own */
    --transition: all 0.3s ease;
}

/* Styles affecting body layout relevant to a fixed header */
body {
    /* font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; */
    /* line-height: 1.6; */
    /* color: var(--dark-color); */
    /* background-color: var(--light-color); */
    /* overflow-x: hidden; */
    /* scroll-behavior: smooth; */
    display: flex; 
    flex-direction: column; 
    min-height: 100vh; 
}

main { /* Ensure main content starts below the fixed header */
    flex-grow: 1;
    padding-top: 80px; /* Adjust this if your header height changes from 80px */
}

.container { /* Used within the header for width control */
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Header & Navigation */
header {
    background-color: var(--white);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); /* Initial shadow */
    position: fixed; /* Makes the header stick to the top */
    width: 100%;
    top: 0;
    z-index: 1000; /* Ensures header is above most other content */
    transition: var(--transition); /* For smooth shadow changes on scroll */
}

/* Navbar is inside the header's .container */
header .container .navbar { /* More specific selector if .navbar is only in header */
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0; /* Vertical padding for navbar content */
    height: 80px;   /* Fixed height for the navbar */
}

.logo {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--primary-color);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem; /* Space between icon and text if applicable */
}

.logo i { font-size: 1.5rem; } /* If using FontAwesome icon in logo */
/* If you have an <img> tag for the logo, add styles for it:
.logo img {
    height: 50px; / Adjust as needed /
    width: auto;
}
*/
.logo span { /* For the "Pass" part of "WavePass" or similar */
    color: var(--dark-color); 
    font-weight: 600; 
}

/* Desktop Navigation Links Container */
.nav-links {
    display: flex; /* Makes links appear inline on desktop */
    list-style: none; /* Removes default bullet points */
    align-items: center; /* Vertically aligns items in the nav */
    gap: 0.5rem; 
    transition: var(--transition); /* For potential future animations */
    margin: 0; /* Remove default ul margin */
    padding: 0; /* Remove default ul padding */
}

/* Individual Desktop Navigation Links (not buttons) */
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
.nav-links a:not(.btn).active-link { /* Class for the currently active page link */
    color: var(--primary-color);
    background-color: rgba(67, 97, 238, 0.07); 
}
.nav-links a:not(.btn)::after { 
    display: none; /* Used to remove potential underlines from pseudo-elements if any were planned */
}
        
/* Desktop Navigation Buttons (.btn in .nav-links) */
.nav-links .btn {
    display: inline-flex;
    gap: 8px; /* Space between icon and text in button */
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
    background-color: var(--primary-color);
    color: var(--white);
    box-shadow: 0 4px 14px rgba(67, 97, 238, 0.2);
    border: none; /* Ensure button-like appearance if it's an <a> tag styled as button */
}
.nav-links .btn .material-symbols-outlined { 
    /* Icon styling for material symbols inside the nav button */
    /* font-size: 1.2em; /* Adjust as needed */
    /* vertical-align: middle; */
    /* margin-right: 4px; /* gap on .btn handles this */
}
.nav-links .btn:hover{
    background-color: var(--primary-dark);
    box-shadow: 0 6px 20px rgba(67, 97, 238, 0.3);
    transform: translateY(-2px); /* Slight lift effect */
}
/* If you also have .btn-outline in .nav-links */
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


/* Hamburger Menu Icon */
.hamburger { 
    display: none; /* Hidden on desktop, shown in @media query */
    cursor: pointer; 
    width: 30px; 
    height: 24px; 
    position: relative; 
    z-index: 1001; /* Ensure it's clickable and above other elements if needed */
    transition: var(--transition); 
    /* If using flex for inner spans directly on .hamburger */
    flex-direction: column;
    justify-content: space-between; /* This spaces out the spans if .hamburger is flex container */
}
.hamburger span { 
    display: block; 
    width: 100%; 
    height: 3px; 
    background-color: var(--dark-color); 
    position: absolute; /* If .hamburger is not flex, spans need absolute positioning */
    left: 0; 
    transition: var(--transition); 
    transform-origin: center; 
    border-radius: 3px; /* Optional: for rounded bars */
}
/* Positioning for spans if .hamburger is not a flex container for them */
.hamburger span:nth-child(1) { top: 0; }
.hamburger span:nth-child(2) { top: 50%; transform: translateY(-50%); }
.hamburger span:nth-child(3) { bottom: 0; }

/* Hamburger active state (transforms to 'X') */
.hamburger.active span:nth-child(1) { top: 50%; transform: translateY(-50%) rotate(45deg); }
.hamburger.active span:nth-child(2) { opacity: 0; /* Middle bar fades out */ }
.hamburger.active span:nth-child(3) { bottom: 50%; transform: translateY(50%) rotate(-45deg); }

/* Mobile Menu Overlay */
.mobile-menu {
    position: fixed;
    top: 0;
    left: 0; 
    width: 100%; 
    height: 100vh;
    background-color: var(--white);
    z-index: 1000; /* Below active hamburger, above page content */
    display: flex;
    flex-direction: column;
    justify-content: center; 
    align-items: center; 
    transform: translateX(-100%); /* Hidden off-screen to the left */
    transition: transform 0.4s cubic-bezier(0.23, 1, 0.32, 1); /* Smooth slide animation */
    padding: 2rem; /* Padding inside the mobile menu */
    overflow-y: auto; /* Allow scrolling if mobile menu content is long */
}

.mobile-menu.active {
    transform: translateX(0); /* Slide into view */
}

/* Links within the Mobile Menu */
.mobile-links {
    list-style: none;
    text-align: center;
    width: 100%;
    max-width: 300px; /* Max width for the list of links */
    padding: 0; /* Remove default ul padding */
    margin: 0;  /* Remove default ul margin */
}

.mobile-links li {
    margin-bottom: 1.5rem; /* Spacing between mobile links */
}

.mobile-links a { /* Styling for individual links */
    color: var(--dark-color);
    text-decoration: none;
    font-weight: 600;
    font-size: 1.2rem; /* Larger font for mobile */
    display: block; /* Make the whole area clickable */
    padding: 0.5rem 1rem;
    transition: var(--transition);
    border-radius: 8px;
}

.mobile-links a:hover, 
.mobile-links a.active-link { /* Style for active/hovered mobile link */
    color: var(--primary-color);
    background-color: rgba(67, 97, 238, 0.1);
}

/* Button styling within mobile menu (e.g., Login/Logout button) */
.mobile-menu .btn { /* Uses the general .btn styles */
    margin-top: 2rem; /* Space above the button */
    width: 100%;
    max-width: 200px; /* Max width for the button in mobile menu */
}
.mobile-menu .btn .material-symbols-outlined { 
    /* Icon styling for material symbols inside the mobile menu button */
    /* font-size: 1.2em; */
    /* vertical-align: middle; */
    /* margin-right: 4px; /* gap on .btn handles this */
}

/* Close Button ('X') for Mobile Menu */
.close-btn { 
    position: absolute;
    top: 30px; 
    right: 30px; 
    font-size: 1.8rem; 
    color: var(--dark-color);
    cursor: pointer;
    transition: var(--transition);
    line-height: 1; /* Important for icon vertical alignment if using text/FontAwesome */
    background: none; /* Remove default button styles */
    border: none; /* Remove default button styles */
    padding: 5px; /* Increase tappable area */
}
.close-btn:hover {
    color: var(--primary-color);
    transform: rotate(90deg); /* Optional: rotate 'X' on hover */
}

/* Responsive Media Queries for Header/Nav */
@media (max-width: 768px) { /* Tablet and smaller - adjust breakpoint as needed */
    .nav-links { 
        display: none; /* Hide desktop nav links */
    }
    .hamburger { 
        display: flex; /* Show hamburger icon. flex-direction and justify-content are on .hamburger now */
    }
    /* Adjustments for login button in navbar if it's also a .btn */
    header .container .navbar .nav-links .btn { /* Style for the .btn inside the hidden .nav-links if needed, though it's hidden */
        max-width: 180px; 
        width: auto; 
    }
}

/* Styles from your max-width: 576px specifically for buttons that might be in header */
@media (max-width: 576px) {
    /* If you have .btn or .btn-outline directly in .nav-links (which are hidden here) or .mobile-menu */
    .mobile-menu .btn, .mobile-menu .btn-outline { 
        max-width: 100%; /* Make mobile menu buttons full width if desired at this breakpoint */
    }
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {

// --- Mobile Menu Toggle ---
const hamburger = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobileMenu');
const closeMenuButton = document.getElementById('closeMenu'); // Assuming 'closeMenu' is the ID of your 'X' button
const body = document.body;

if (hamburger && mobileMenu) { // Check if hamburger and mobileMenu elements exist
    hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        mobileMenu.classList.toggle('active');
        // Optional: Prevent body scrolling when mobile menu is open
        if (mobileMenu.classList.contains('active')) {
            body.style.overflow = 'hidden';
        } else {
            body.style.overflow = '';
        }
    });

    // If there's an explicit close button inside the mobile menu
    if (closeMenuButton) {
        closeMenuButton.addEventListener('click', () => {
            hamburger.classList.remove('active');
            mobileMenu.classList.remove('active');
            body.style.overflow = '';
        });
    }

    // Close mobile menu when a link inside it is clicked
    // Useful for single-page navigation or when navigating to another page
    const mobileNavLinks = mobileMenu.querySelectorAll('ul.mobile-links a'); // Target links within the ul
    mobileNavLinks.forEach(link => {
        link.addEventListener('click', () => {
            // Check if the link is an anchor link for the current page or a navigation link
            const href = link.getAttribute('href');
            let shouldCloseMenu = false;

            if (href) {
                if (href.startsWith('#')) { // Anchor link on the same page
                    shouldCloseMenu = true;
                } else if (href.includes('.php') || href === 'login.php' || href === 'logout.php') { // Link to another page
                    shouldCloseMenu = true;
                }
                // If it's a button styled as a link, like a login button, it should also close
                if (link.classList.contains('btn')) {
                    shouldCloseMenu = true;
                }
            }

            if (shouldCloseMenu && mobileMenu.classList.contains('active')) {
                hamburger.classList.remove('active');
                mobileMenu.classList.remove('active');
                body.style.overflow = '';
            }
        });
    });

    // Optional: Close mobile menu if clicked outside of it
    document.addEventListener('click', function(event) {
        if (mobileMenu.classList.contains('active')) {
            const isClickInsideMenu = mobileMenu.contains(event.target);
            const isClickOnHamburger = hamburger.contains(event.target);
            const isClickOnHamburgerSpan = event.target.parentElement === hamburger;


            if (!isClickInsideMenu && !isClickOnHamburger && !isClickOnHamburgerSpan) {
                hamburger.classList.remove('active');
                mobileMenu.classList.remove('active');
                body.style.overflow = '';
            }
        }
    });
}

// --- Smooth Scrolling for Anchor Links ---
// Selects all <a> tags whose href starts with '#' OR 'index.php#'
document.querySelectorAll('a[href^="#"], a[href^="index.php#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');

        // Ignore if it's just a placeholder '#' or similar
        if (href === '#' || href.length < 2 || (href.startsWith('index.php#') && href.length < "index.php#".length + 1) ) {
            return;
        }
        
        let targetId;
        let targetPage = window.location.pathname.split('/').pop() || 'index.php'; // Get current page filename

        if (href.startsWith('index.php#')) {
            targetId = href.substring(href.indexOf('#') + 1);
            // If we are not on index.php, then navigate to index.php with the hash
            if (targetPage !== 'index.php') {
                window.location.href = href; // Full navigation
                return;
            }
        } else if (href.startsWith('#')) {
            targetId = href.substring(1);
        } else {
            return; // Not a hash link we are handling here
        }

        const targetElement = document.getElementById(targetId);

        if (targetElement) {
            e.preventDefault(); // Prevent default anchor jump

            const headerElement = document.querySelector('header'); // Get the fixed header
            const headerHeight = headerElement ? headerElement.offsetHeight : 0; // Get its height or 0 if not found
            
            // Calculate position of target element relative to the document, then subtract header height
            const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - headerHeight;

            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });

            // Optional: Close mobile menu if open after smooth scroll
            if (mobileMenu && mobileMenu.classList.contains('active')) {
                hamburger.classList.remove('active');
                mobileMenu.classList.remove('active');
                body.style.overflow = '';
            }
        }
    });
});

// --- Add Shadow to Header on Scroll ---
const headerElement = document.querySelector('header'); // Re-select for this specific functionality
if (headerElement) {
    // Store the initial box-shadow to revert to
    const initialHeaderShadow = getComputedStyle(headerElement).boxShadow;
    // Define a more prominent shadow for when scrolled
    const scrolledHeaderShadow = getComputedStyle(document.documentElement).getPropertyValue('--shadow').trim() || '0 4px 12px rgba(0,0,0,0.08)';


    window.addEventListener('scroll', () => {
        if (window.scrollY > 10) { // Add shadow after scrolling a bit (e.g., 10px)
            headerElement.style.boxShadow = scrolledHeaderShadow;
        } else {
            headerElement.style.boxShadow = initialHeaderShadow; // Revert to initial or a lighter shadow
        }
    });
}

// --- FAQ Accordion Functionality ---
const faqItems = document.querySelectorAll('.faq-item');
faqItems.forEach(item => {
    const question = item.querySelector('.faq-question');
    const answer = item.querySelector('.faq-answer');
    const icon = question ? question.querySelector('i.fas') : null; // For FontAwesome icon

    if (question && answer) { 
        question.addEventListener('click', () => {
            const isActive = item.classList.contains('active');
            
            // Optional: Close all other FAQ items first
            // faqItems.forEach(otherItem => {
            //     if (otherItem !== item && otherItem.classList.contains('active')) {
            //         otherItem.classList.remove('active');
            //         otherItem.querySelector('.faq-answer').style.maxHeight = null;
            //         const otherIcon = otherItem.querySelector('.faq-question i.fas');
            //         if (otherIcon) otherIcon.classList.replace('fa-chevron-up', 'fa-chevron-down');
            //     }
            // });

            if (isActive) {
                item.classList.remove('active');
                answer.style.maxHeight = null;
                if (icon) icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
            } else {
                item.classList.add('active');
                answer.style.maxHeight = answer.scrollHeight + "px";
                if (icon) icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
            }
        });
    }
});

// --- Scroll to Top Button Functionality ---
const scrollToTopBtn = document.getElementById("scrollToTopBtn");
if (scrollToTopBtn) {
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 200 || document.documentElement.scrollTop > 200) { // Show button after scrolling 200px
            scrollToTopBtn.classList.add("show");
        } else {
            scrollToTopBtn.classList.remove("show");
        }
    });

    scrollToTopBtn.addEventListener("click", function() {
        window.scrollTo({ top: 0, behavior: "smooth" });
    });
}

// --- Active Navigation Link Highlighting ---
// (This function was in your login.php script, good to have it globally if needed)
function setActiveNavLink() {
    const navLinksDesktop = document.querySelectorAll('header .nav-links a:not(.btn)');
    const navLinksMobile = document.querySelectorAll('.mobile-menu .mobile-links a:not(.btn)');
    
    let currentPath = window.location.pathname.split('/').pop();
    if (currentPath === "" || currentPath === "index.php") { // Treat root and index.php the same for highlighting
        currentPath = "index.php"; 
    }
    
    // Handle active link for non-button links
    function highlightLinks(links) {
        links.forEach(link => {
            let linkHref = link.getAttribute('href');
            let linkPath = linkHref.split('/').pop().split('#')[0]; // Get filename without hash
            if (linkPath === "") linkPath = "index.php";

            if (linkPath === currentPath) {
                link.classList.add('active-link'); // Ensure your CSS has .active-link
            } else {
                link.classList.remove('active-link');
            }
        });
    }

    highlightLinks(navLinksDesktop);
    highlightLinks(navLinksMobile);

    // Special handling for login button if you want it active on login.php
    const loginPageIdentifier = 'login.php';
    const navLoginBtnDesktop = document.querySelector('header .nav-links a.btn[href*="login.php"]');
    const navLoginBtnMobile = document.querySelector('.mobile-menu a.btn[href*="login.php"]');

    if (currentPath === loginPageIdentifier) {
        if (navLoginBtnDesktop) navLoginBtnDesktop.classList.add('active-link');
        if (navLoginBtnMobile) navLoginBtnMobile.classList.add('active-link');
    } else {
        if (navLoginBtnDesktop) navLoginBtnDesktop.classList.remove('active-link');
        if (navLoginBtnMobile) navLoginBtnMobile.classList.remove('active-link');
    }
}
setActiveNavLink(); // Call it on page load
// You might also want to call setActiveNavLink after AJAX page loads if you implement SPA behavior.

}); // End of DOMContentLoaded
</script>