<?php
// components/main-header.php

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Basic session variables with fallbacks
$sessionIsLoggedIn = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'User';

// Improved role detection with priority
$userRole = 'employee'; // Default role
if ($sessionIsLoggedIn) {
    if (isset($_SESSION["role_name"])) {
        $userRole = strtolower(htmlspecialchars($_SESSION["role_name"]));
    } elseif (isset($_SESSION["role"])) {
        // Fallback if role_name isn't set but role is (e.g. 'admin', 'employee')
        $userRole = strtolower(htmlspecialchars($_SESSION["role"]));
    }
} else {
    $userRole = 'guest';
}

// Current page detection
if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF']); // Fallback
}

// Path adjustments
// $base_path_for_page_links determines if links need ../ or not.
// Generally for pages within the same directory level.
$base_path_for_page_links = "";

// $asset_prefix is used for assets like images, CSS, JS that are in root folders
// when the current page is in a subdirectory (like /admin/).
$asset_prefix = "";
$is_admin_page_context = (strpos($currentPage, 'admin-') === 0);

// Determine if the current script is inside the 'admin' directory based on its path
$current_script_path = $_SERVER['SCRIPT_FILENAME'];
$is_in_admin_folder = (strpos($current_script_path, DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR) !== false);


if ($is_in_admin_folder) { // If the script itself is in /admin/
    $asset_prefix = "../";
    $base_path_for_page_links = ""; // Links to other admin pages are direct
} else { // Script is in root
    $asset_prefix = "";
    $base_path_for_page_links = ""; // Links to other root pages are direct
}


// --- Profile Photo URL Construction ---
$profile_upload_dir_from_root = 'profile_photos/'; // Always relative to project root
$default_avatar_filename = 'default_avatar.jpg';   // Always relative to project root

// The $asset_prefix will correctly make it ../profile_photos/ if we are in /admin/
// or profile_photos/ if we are in root.
$web_profile_photos_path_resolved = htmlspecialchars($asset_prefix . $profile_upload_dir_from_root);

$profile_photo_url = $web_profile_photos_path_resolved . $default_avatar_filename;

if ($sessionIsLoggedIn && isset($_SESSION["profile_photo"]) && !empty($_SESSION["profile_photo"])) {
    $current_photo_filename = basename($_SESSION["profile_photo"]);
    if ($current_photo_filename !== $default_avatar_filename) {
        $profile_photo_url = $web_profile_photos_path_resolved . htmlspecialchars($current_photo_filename);
    }
}


// Define paths for key pages
$user_dashboard_path_target = "dashboard.php"; // Target filename
$admin_dashboard_path_target = "admin-panel.php"; // Target filename

// Links FROM admin folder TO root folder need "../"
// Links FROM root folder TO admin folder need "admin/" (if admin files are in /admin/)
// Links within the same folder are direct.

// For the "User Dashboard" link when admin is in admin context
if ($is_in_admin_folder) {
    $user_dashboard_link = "../" . $user_dashboard_path_target;
} else {
    $user_dashboard_link = $user_dashboard_path_target;
}

// For links to admin pages (assuming admin pages are in /admin/ folder)
if (!$is_in_admin_folder && $userRole === 'admin') {
    // If user is admin and on a root page, link to admin pages needs 'admin/' prefix
    // BUT your admin files (admin-panel.php) are at the root according to the image.
    // So, no prefix needed here for your structure.
    $admin_page_link_prefix = "";
} else {
    // If inside admin folder, or not admin, links to admin pages are direct (or not shown)
    $admin_page_link_prefix = "";
}

?>
<header>
    <div class="container">
        <nav class="navbar">
            <a href="/imgs/logo.png" class="logo">
                <i class="fas fa-chalkboard-teacher"></i>
                Wave<span>Pass</span>
                <?php if ($userRole === 'admin'): ?>
                    <span class="admin-badge">Admin</span>
                <?php endif; ?>
            </a>
            <ul class="nav-links">
                <?php if ($sessionIsLoggedIn): ?>
                    <?php if ($userRole === 'admin'): ?>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-dashboard.php" class="<?php if ($currentPage === 'admin-dashboard.php') echo 'active-link'; ?>">User Dashboard</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-panel.php" class="<?php if ($currentPage === 'admin-panel.php') echo 'active-link'; ?>">Admin Panel</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-manage-rfid.php" class="<?php if ($currentPage === 'admin-manage-rfid.php') echo 'active-link'; ?>">RFID Cards</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-manage-users.php" class="<?php if ($currentPage === 'admin-manage-employees.php') echo 'active-link'; ?>">Employees</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-manage-absence.php" class="<?php if ($currentPage === 'admin-manage-absence.php') echo 'active-link'; ?>">Absences</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-messages.php?context=admin" class="<?php if ($currentPage === 'messages.php' && isset($_GET['context']) && $_GET['context'] === 'admin') echo 'active-link'; ?>">Messages</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>dashboard.php" class="<?php if ($currentPage === 'dashboard.php') echo 'active-link'; ?>">My Dashboard</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>attendance-logs.php" class="<?php if ($currentPage === 'attendance-logs.php') echo 'active-link'; ?>">Attendance Log</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>absences.php" class="<?php if ($currentPage === 'absences.php') echo 'active-link'; ?>">Absence</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>messages.php" class="<?php if ($currentPage === 'messages.php' && (!isset($_GET['context']) || $_GET['context'] !== 'admin') ) echo 'active-link'; ?>">Messages</a></li>
                    <?php endif; ?>
                    
                    <li>
                        <a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-profile.php?section=profile" class="<?php if ($currentPage === 'admin-profile.php') echo 'active-link'; ?>">
                            <img src="<?php echo $profile_photo_url; ?>?<?php echo time(); ?>" alt="Profile" class="nav-user-photo">
                            <?php echo $sessionFirstName; ?>
                        </a>
                    </li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>../logout.php" class="btn"><span class="material-symbols-outlined">logout</span> Logout</a></li>
                <?php else: ?>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>index.php#features" class="<?php if ($currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '#features') !== false) echo 'active-link'; ?>">Features</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>pricing.php" class="<?php if ($currentPage === 'pricing.php') echo 'active-link'; ?>">Pricing</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>index.php#faq" class="<?php if ($currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '#faq') !== false) echo 'active-link'; ?>">FAQ</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>login.php" class="btn <?php echo $currentPage === 'login.php' ? 'active-link' : ''; ?>"><span class="material-symbols-outlined">account_circle</span> Login</a></li>
                <?php endif; ?>
            </ul>
            <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
        </nav>
    </div>
    <div class="mobile-menu" id="mobileMenu">
        <span class="close-btn" id="closeMenu"></i></span>
        <ul class="mobile-links">
            <?php if ($sessionIsLoggedIn): ?>
                <?php if ($userRole === 'admin'): ?>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-dashboard.php" class="<?php if ($currentPage === 'admin-dashboard.php') echo 'active-link'; ?>">User Dashboard</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-panel.php" class="<?php if ($currentPage === 'admin-panel.php') echo 'active-link'; ?>">Admin Panel</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-manage-rfid.php" class="<?php if ($currentPage === 'admin-manage-rfid.php') echo 'active-link'; ?>">RFID Cards</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-manage-employees.php" class="<?php if ($currentPage === 'admin-manage-employees.php') echo 'active-link'; ?>">Employees</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-manage-absence.php" class="<?php if ($currentPage === 'admin-manage-absence.php') echo 'active-link'; ?>">Absences</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-messages.php?context=admin" class="<?php if ($currentPage === 'messages.php' && isset($_GET['context']) && $_GET['context'] === 'admin') echo 'active-link'; ?>">Messages</a></li>
                <?php else: ?>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>dashboard.php" class="<?php if ($currentPage === 'dashboard.php') echo 'active-link'; ?>">My Dashboard</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>attendance-logs.php" class="<?php if ($currentPage === 'attendance-logs.php') echo 'active-link'; ?>">Attendance Log</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>absences.php" class="<?php if ($currentPage === 'absences.php') echo 'active-link'; ?>">Absence</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>messages.php" class="<?php if ($currentPage === 'messages.php' && (!isset($_GET['context']) || $_GET['context'] !== 'admin') ) echo 'active-link'; ?>">Messages</a></li>
                <?php endif; ?>
                <li class="nav-item-profile">
                        <a href="<?php echo htmlspecialchars($asset_prefix . 'admin-profile.php?section=profile'); ?>" class="profile-link <?php if ($currentPage === 'profile.php') echo 'active-link'; ?>">
                            <img src="<?php echo $profile_photo_url; ?>?<?php echo time(); /* Cache buster */ ?>" alt="Profile" class="nav-user-photo">
                            <span class="nav-user-name"><?php echo $sessionFirstName; ?></span>
                        </a>
                    </li>
                <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>../logout.php" class="btn"><span class="material-symbols-outlined">logout</span> Log out</a></li>
            <?php else: ?>
                <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>index.php#features" class="<?php if ($currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '#features') !== false) echo 'active-link'; ?>">Features</a></li>
                <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>pricing.php" class="<?php if ($currentPage === 'pricing.php') echo 'active-link'; ?>">Pricing</a></li>
                <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>index.php#faq" class="<?php if ($currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '#faq') !== false) echo 'active-link'; ?>">FAQ</a></li>
                <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>login.php" class="btn <?php echo $currentPage === 'login.php' ? 'active-link' : ''; ?>"><span class="material-symbols-outlined">account_circle</span> Login</a></li>
            <?php endif; ?>
        </ul>
    </div>
</header>

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

header {
    background-color: var(--white);
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    position: fixed; /* Důležité pro header, který zůstává nahoře */
    width: 100%;
    top: 0;
    left: 0; /* Přidáno pro plnou šířku */
    z-index: 1000;
    height: 80px; /* Explicitní výška headeru */
}


.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
    height: 100%; /* Aby .navbar mohl být vertikálně centrován */
}

.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    /* padding: 1rem 0;  Odstraněno, výška se řeší na headeru a containeru */
    height: 100%; /* Navbar zabere celou výšku headeru */
}

.logo {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--primary-color);
    text-decoration: none;
    display: flex;
    align-items: center; /* Vertikální centrování obsahu loga */
    gap: 0.5rem;
}

.logo i { /* Pokud používáte FontAwesome pro logo */
    font-size: 1.5rem; /* Můžete upravit velikost ikony */
    /* vertical-align: middle; Není potřeba, pokud je rodič flex a align-items: center */
}
.logo img.logo-img { /* Pokud používáte obrázek v logu */
    height: 35px; /* Upravte dle potřeby */
    width: auto;
    margin-right: 0.6rem;
}


.logo span {
    color: var(--dark-color);
    font-weight: 600;
}

.admin-badge {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--white);
    background-color: var(--primary-color);
    padding: 3px 7px;
    border-radius: 4px;
    margin-left: 0.6rem;
    vertical-align: middle; /* Pro lepší zarovnání s textem, pokud není flex */
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.nav-links {
    display: flex;
    list-style: none;
    align-items: center; /* Vertikální centrování všech položek v nav-links */
    gap: 0.5rem; /* Odsazení mezi položkami */
    margin: 0; /* Reset marginu */
    padding: 0; /* Reset paddingu */
}

.nav-links li { /* Přidáno pro lepší kontrolu nad položkami seznamu */
    display: flex; /* Umožní align-items na jednotlivých <a> */
    align-items: center; /* Vertikálně centruje obsah každé <li> */
}

.nav-links a:not(.btn) {
    color: var(--dark-color);
    text-decoration: none;
    font-weight: 500;
    padding: 0.7rem 1rem;
    font-size: 0.95rem;
    border-radius: 8px;
    position: relative;
    transition: color .3s ease, background-color .3s ease;
    display: inline-flex; /* Pro lepší zarovnání s ikonami a textem */
    align-items: center;  /* Vertikální centrování obsahu v <a> */
}

.nav-links a:not(.btn):hover, 
.nav-links a:not(.btn).active-link {
    color: var(--primary-color);
    background-color: rgba(67,97,238,0.07);
}

.nav-links .btn {
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
    font-size: 0.9rem;
    background-color: var(--primary-color);
    color: var(--white);
    box-shadow: 0 4px 14px rgba(67,97,238,0.2);
}

.nav-links .btn .material-symbols-outlined {
    font-size: 1.2em;
    /* vertical-align: middle; Není potřeba s flex */
    margin-right: 4px; /* Může být i jen gap na rodiči .btn */
}

.nav-links .btn:hover {
    background-color: var(--primary-dark);
    box-shadow: 0 6px 20px rgba(67,97,238,0.3);
    transform: translateY(-2px);
}

.nav-user-photo {
    width: 32px; /* Velikost obrázku */
    height: 32px;
    border-radius: 50%;
    object-fit: cover; /* Zajistí, že obrázek vyplní kruh bez deformace */
    margin-right: 10px;
    border: 2px solid var(--light-gray);
    /* vertical-align: middle; Odstraněno, řeší se flexboxem na rodiči <a> nebo <li> */
}

/* Hamburger and Mobile Menu */
.hamburger {
    display: none;
    cursor: pointer;
    width: 30px;
    height: 24px;
    position: relative;
    z-index: 1001;
}

.hamburger span {
    display: block;
    width: 100%;
    height: 3px;
    background-color: var(--dark-color);
    position: absolute;
    left: 0;
    transition: var(--transition);
    transform-origin: center;
}

.hamburger span:nth-child(1) { top: 0; }
.hamburger span:nth-child(2) { top: 50%; transform: translateY(-50%); }
.hamburger span:nth-child(3) { bottom: 0; }

.hamburger.active span:nth-child(1) { top: 50%; transform: translateY(-50%) rotate(45deg); }
.hamburger.active span:nth-child(2) { opacity: 0; }
.hamburger.active span:nth-child(3) { bottom: 50%; transform: translateY(50%) rotate(-45deg); }

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
    transition: transform 0.4s cubic-bezier(0.23,1,0.32,1);
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

.mobile-links a:hover, 
.mobile-links a.active-link {
    color: var(--primary-color);
    background-color: rgba(67,97,238,0.1);
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
    line-height: 1;
}

.close-btn:hover {
    color: var(--primary-color);
    transform: rotate(90deg);
}

.mobile-nav-user-photo { /* Již definováno jako .nav-user-photo, ale pro jistotu */
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 12px;
    border: 1.5px solid var(--light-gray);
}

@media (max-width: 768px) {
    .nav-links { display: none; }
    .hamburger { display: flex; }
}
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