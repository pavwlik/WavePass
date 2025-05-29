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
$userRole = 'guest'; // Default role if not logged in
if ($sessionIsLoggedIn) {
    $userRole = 'employee'; // Default for logged-in users
    if (isset($_SESSION["role_name"])) {
        $userRole = strtolower(htmlspecialchars($_SESSION["role_name"]));
    } elseif (isset($_SESSION["role"])) {
        $userRole = strtolower(htmlspecialchars($_SESSION["role"]));
    }
}

// Current page detection
if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF']); // Fallback
}

// --- Path adjustments ---
$asset_prefix = "";             // For assets (CSS, JS, images in root folders)
$base_path_for_page_links = ""; // For links between PHP pages
$logout_path = "";              // For the logout link

// Determine if the current script is inside the 'admin' directory
// This assumes your admin-specific PHP files are in a subfolder named 'admin'.
// If admin files are at the root (e.g., admin-panel.php), this will be false.
$current_script_path = $_SERVER['SCRIPT_FILENAME'];
$admin_folder_name = 'admin'; // Define your admin folder name here
$is_in_admin_folder = (strpos($current_script_path, DIRECTORY_SEPARATOR . $admin_folder_name . DIRECTORY_SEPARATOR) !== false);

if ($is_in_admin_folder) {
    $asset_prefix = "../"; // Assets are one level up
    // $base_path_for_page_links = ""; // Links to other pages in the same /admin/ folder are direct
    $logout_path = "../logout.php"; // logout.php is one level up
} else {
    $asset_prefix = "";    // Assets are in the same level or subfolders
    // $base_path_for_page_links = ""; // Links to other pages in the root are direct
    $logout_path = "logout.php";   // logout.php is in the same root level
}


// --- Profile Photo URL Construction ---
$profile_upload_dir_from_root = 'profile_photos/';
$default_avatar_filename = 'default_avatar.png';

$web_profile_photos_path_resolved = htmlspecialchars($asset_prefix . $profile_upload_dir_from_root);
$profile_photo_url = $web_profile_photos_path_resolved . $default_avatar_filename;

if ($sessionIsLoggedIn && isset($_SESSION["profile_photo"]) && !empty($_SESSION["profile_photo"])) {
    $current_photo_filename = basename($_SESSION["profile_photo"]);
    // Ensure we don't try to use default_avatar.png if it's already the one in session
    // though the logic below handles it, this is just a note.
    $profile_photo_url = $web_profile_photos_path_resolved . htmlspecialchars($current_photo_filename);
}

// --- Navigation Link Paths (Example for dashboard, adjust as needed) ---
// This part can be complex depending on your exact structure.
// The $base_path_for_page_links is currently always "" based on your original logic.
// This means links like dashboard.php or admin-panel.php are expected to be relative
// to the current page's directory.

// If admin pages (e.g. admin-panel.php) are in the root:
$admin_panel_link = $base_path_for_page_links . "admin-panel.php";
$admin_dashboard_link = $base_path_for_page_links . "admin-dashboard.php"; // Assuming this is also root
$user_dashboard_link = $base_path_for_page_links . "dashboard.php";

// If admin pages were in an 'admin' subfolder and you were in root, you'd need:
// $admin_panel_link = $base_path_for_page_links . "admin/admin-panel.php";

// Given your $is_in_admin_folder, if admin pages are indeed in /admin/,
// and you want to link from /admin/somepage.php to /admin/admin-panel.php,
// $base_path_for_page_links = "" is correct for that.
// If you wanted to link from /admin/somepage.php to /dashboard.php (in root), you'd need "../dashboard.php".
// Your $user_dashboard_link logic seems to try to handle this, but let's simplify based on $asset_prefix for now.


// For linking to root dashboard from an admin page
$user_dashboard_accessible_link = ($is_in_admin_folder ? $asset_prefix : $base_path_for_page_links) . "dashboard.php";
$index_link = htmlspecialchars($asset_prefix . "index.php");
$logo_path_for_img_tag = htmlspecialchars($asset_prefix . "imgs/logo.png");

?>
<header>
    <div class="container">
    <nav class="navbar">
            <a href="<?php echo $index_link; ?>" class="logo-block" translate="no">
                <img src="<?php echo $logo_path_for_img_tag; ?>" alt="WavePass Logo" class="logo-img">
                <div class="logo-text-content"> 
                    Wave<span>Pass</span>
                    <?php if ($userRole === 'admin' && $sessionIsLoggedIn): ?>
                        <span class="admin-badge-inline">ADMIN</span> 
                    <?php endif; ?>
                </div>
            </a>

            <ul class="nav-actions-group nav-links">
                <?php if ($sessionIsLoggedIn): ?>
                    <?php if ($userRole === 'admin'): ?>
                        <?php // Links for Admin - adjust paths if admin files are in a subfolder or root ?>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-dashboard.php" class="<?php if ($currentPage === 'admin-dashboard.php') echo 'active-link'; ?>">User Dashboard</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-panel.php" class="<?php if ($currentPage === 'admin-panel.php') echo 'active-link'; ?>">Admin Panel</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-manage-rfid.php" class="<?php if ($currentPage === 'admin-manage-rfid.php') echo 'active-link'; ?>">RFID Cards</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-manage-users.php" class="<?php if ($currentPage === 'admin-manage-users.php' || $currentPage === 'admin-manage-employees.php') echo 'active-link'; ?>">Users</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-manage-absence.php" class="<?php if ($currentPage === 'admin-manage-absence.php') echo 'active-link'; ?>">Absences</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-messages.php?context=admin" class="<?php if ($currentPage === 'admin-messages.php' && isset($_GET['context']) && $_GET['context'] === 'admin') echo 'active-link'; ?>">Messages</a></li>
                        <li>
                        <?php // Profile link: if admin-profile.php is at root, use $asset_prefix to get there from /admin/ folder ?>
                        <a href="<?php echo htmlspecialchars($asset_prefix); ?>admin/admin-profile.php?section=profile" class="<?php if ($currentPage === 'admin-profile.php') echo 'active-link'; ?>">
                            <img src="<?php echo $profile_photo_url; ?>?<?php echo time(); ?>" alt="Profile" class="nav-user-photo">
                            <?php echo $sessionFirstName; ?>
                        </a>
                    </li>

                    <?php else: // Employee Links ?>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>dashboard.php" class="<?php if ($currentPage === 'dashboard.php') echo 'active-link'; ?>">My Dashboard</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>my_attendance_log.php" class="<?php if ($currentPage === 'my_attendance_log.php') echo 'active-link'; ?>">Attendance Log</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>absences.php" class="<?php if ($currentPage === 'absences.php') echo 'active-link'; ?>">Absence</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>messages.php" class="<?php if ($currentPage === 'messages.php' && (!isset($_GET['context']) || $_GET['context'] !== 'admin') ) echo 'active-link'; ?>">Messages</a></li>
                        <li>
                            <?php // Profile link: if admin-profile.php is at root, use $asset_prefix to get there from /admin/ folder ?>
                            <a href="<?php echo htmlspecialchars($asset_prefix); ?>profile.php?section=profile" class="<?php if ($currentPage === 'admin-profile.php') echo 'active-link'; ?>">
                                <img src="<?php echo $profile_photo_url; ?>?<?php echo time(); ?>" alt="Profile" class="nav-user-photo">
                                <?php echo $sessionFirstName; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    

                    <li><a href="<?php echo htmlspecialchars($logout_path); ?>" class="btn"><span aria-hidden="true" translate="no" class="material-symbols-outlined">logout</span> Logout</a></li>
                <?php else: // Guest Links ?>
                    <li><a href="<?php echo htmlspecialchars($asset_prefix); ?>index.php#features" class="<?php if ($currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '#features') !== false) echo 'active-link'; ?>">Features</a></li>
                    <li><a href="<?php echo htmlspecialchars($asset_prefix); ?>pricing.php" class="<?php if ($currentPage === 'pricing.php') echo 'active-link'; ?>">Pricing</a></li>
                    <li><a href="<?php echo htmlspecialchars($asset_prefix); ?>index.php#faq" class="<?php if ($currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '#faq') !== false) echo 'active-link'; ?>">FAQ</a></li>
                    <li><a href="<?php echo htmlspecialchars($asset_prefix); ?>login.php" class="btn <?php if ($currentPage === 'login.php') echo 'active-link'; ?>"><span aria-hidden="true" translate="no" class="material-symbols-outlined">account_circle</span> Login</a></li>
                <?php endif; ?>
            </ul>
            <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
        </nav>
    </div>
    <div class="mobile-menu" id="mobileMenu">
        <span class="close-btn" id="closeMenu"></span> 
        <ul class="mobile-links">
            <?php if ($sessionIsLoggedIn): ?>
                <?php if ($userRole === 'admin'): ?>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-dashboard.php" class="<?php if ($currentPage === 'admin-dashboard.php') echo 'active-link'; ?>">User Dashboard</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-panel.php" class="<?php if ($currentPage === 'admin-panel.php') echo 'active-link'; ?>">Admin Panel</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-manage-rfid.php" class="<?php if ($currentPage === 'admin-manage-rfid.php') echo 'active-link'; ?>">RFID Cards</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-manage-users.php" class="<?php if ($currentPage === 'admin-manage-users.php' || $currentPage === 'admin-manage-employees.php') echo 'active-link'; ?>">Employees</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-manage-absence.php" class="<?php if ($currentPage === 'admin-manage-absence.php') echo 'active-link'; ?>">Absences</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>admin-messages.php?context=admin" class="<?php if ($currentPage === 'admin-messages.php' && isset($_GET['context']) && $_GET['context'] === 'admin') echo 'active-link'; ?>">Messages</a></li>
                    <li>
                    <?php // Profile link: if admin-profile.php is at root, use $asset_prefix to get there from /admin/ folder ?>
                    <a href="<?php echo htmlspecialchars($asset_prefix); ?>admin/admin-profile.php?section=profile" class="<?php if ($currentPage === 'admin-profile.php') echo 'active-link'; ?>">
                        <img src="<?php echo $profile_photo_url; ?>?<?php echo time(); ?>" alt="Profile" class="nav-user-photo">
                        <?php echo $sessionFirstName; ?>
                    </a>
                    </li>

                <?php else: // Employee Links ?>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>dashboard.php" class="<?php if ($currentPage === 'dashboard.php') echo 'active-link'; ?>">My Dashboard</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>my_attendance_log.php" class="<?php if ($currentPage === 'my_attendance_log.php') echo 'active-link'; ?>">Attendance Log</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>absences.php" class="<?php if ($currentPage === 'absences.php') echo 'active-link'; ?>">Absence</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_page_links); ?>messages.php" class="<?php if ($currentPage === 'messages.php' && (!isset($_GET['context']) || $_GET['context'] !== 'admin') ) echo 'active-link'; ?>">Messages</a></li>
                    <li>
                        <?php // Profile link: if admin-profile.php is at root, use $asset_prefix to get there from /admin/ folder ?>
                        <a href="<?php echo htmlspecialchars($asset_prefix); ?>profile.php?section=profile" class="<?php if ($currentPage === 'admin-profile.php') echo 'active-link'; ?>">
                            <img src="<?php echo $profile_photo_url; ?>?<?php echo time(); ?>" alt="Profile" class="nav-user-photo">
                            <?php echo $sessionFirstName; ?>
                        </a>
                    </li>
                <?php endif; ?>

                <li><a href="<?php echo htmlspecialchars($logout_path); ?>" class="btn"><span aria-hidden="true" translate="no" class="material-symbols-outlined">logout</span> Log out</a></li>
            <?php else: // Guest Links ?>
                <li><a href="<?php echo htmlspecialchars($asset_prefix); ?>index.php#features" class="<?php if ($currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '#features') !== false) echo 'active-link'; ?>">Features</a></li>
                <li><a href="<?php echo htmlspecialchars($asset_prefix); ?>pricing.php" class="<?php if ($currentPage === 'pricing.php') echo 'active-link'; ?>">Pricing</a></li>
                <li><a href="<?php echo htmlspecialchars($asset_prefix); ?>index.php#faq" class="<?php if ($currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '#faq') !== false) echo 'active-link'; ?>">FAQ</a></li>
                <li><a href="<?php echo htmlspecialchars($asset_prefix); ?>login.php" class="btn <?php if ($currentPage === 'login.php') echo 'active-link'; ?>"><span aria-hidden="true" translate="no" class="material-symbols-outlined">account_circle</span> Login</a></li>
            <?php endif; ?>
        </ul>
    </div>
</header>

<style>
/* Váš CSS kód zde - beze změny */
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
    position: fixed;
    width: 100%;
    top: 0;
    left: 0;
    z-index: 1000;
    height: 80px;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
    height: 100%;
}

.navbar {
    display: flex;
    justify-content: space-between; /* KĽÚČOVÁ ZMENA */
    align-items: center;
    height: 100%;
}

.logo-block { /* Obal pre obrázok loga, text a admin badge */
    display: flex;
    align-items: center;
    text-decoration: none; /* Odstráni podčiarknutie z odkazu */
}

.logo-img {
    height: 35px; 
    width: auto;
    margin-right: 0.5rem; /* Menšie odsadenie, ak je admin badge blízko */
}

.logo-text-content { /* Div pre text "WavePass" a "ADMIN" badge */
    display: flex;
    align-items: baseline; /* Zarovná text a badge na účaŕu */
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--primary-color);
}

.logo-text-content span { /* Pre "Pass" časť */
    color: var(--dark-color);
    font-weight: 600;
}

.admin-badge-inline { /* Badge umiestnený priamo pri logu */
    font-size: 0.65rem; /* Ešte menší pre lepšie zapadnutie */
    font-weight: 700;
    color: var(--white);
    background-color: var(--primary-color);
    padding: 3px 6px;
    border-radius: 4px;
    margin-left: 0.7rem; 
    text-transform: uppercase;
    letter-spacing: 0.5px;
    line-height: 1; 
    align-self: center; /* Pre lepšie vertikálne zarovnanie s väčším textom loga */
}

.nav-actions-group { /* Obal pre navigačné odkazy a hamburger na pravej strane */
    display: flex;
    align-items: center;
    gap: 1rem; /* Odsadenie medzi ul.nav-links a .hamburger */
}

.nav-links {
    display: flex;
    list-style: none;
    align-items: center;
    gap: 0.5rem; 
    margin: 0;
    padding: 0;
}

.nav-links li {
    display: flex;
    align-items: center;
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
    z-index: 1001; /* Mělo by být nad .mobile-menu, pokud .mobile-menu nemá vyšší a není skryto */
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
    z-index: 1000; /* Nižší než hamburger, když je aktivní, ale musí být dostatečně vysoké */
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    transform: translateX(-100%);
    transition: transform 0.4s cubic-bezier(0.23,1,0.32,1);
    padding: 2rem;
    box-shadow: 5px 0 15px rgba(0,0,0,0.1); /* Přidáno pro vizuální oddělení */
}

.mobile-menu.active {
    transform: translateX(0);
}

.mobile-links {
    list-style: none;
    text-align: center;
    width: 100%;
    max-width: 300px; /* Omezení šířky obsahu menu */
    padding: 0; /* Reset default padding */
}

.mobile-links li {
    margin-bottom: 1.5rem;
}

.mobile-links a {
    color: var(--dark-color);
    text-decoration: none;
    font-weight: 600;
    font-size: 1.2rem;
    display: block; /* Aby zabraly celou šířku a padding fungoval lépe */
    padding: 0.75rem 1rem; /* Zvětšený padding pro lepší klikatelnost */
    transition: var(--transition);
    border-radius: 8px;
}
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0.8rem 2rem; background-color: var(--primary-color); color: var(--white)!important; border: none; border-radius: 8px; text-decoration: none; font-weight: 600; transition: var(--transition); cursor: pointer; box-shadow: 0 4px 14px rgba(67,97,238,0.3); font-size: 0.95rem; }
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
        .mobile-links a { display: flex; align-items: center; color: var(--dark-color); text-decoration: none; font-weight: 600; font-size: 1.2rem; display: inline-block; padding: 0.5rem 1rem; transition: var(--transition); border-radius: 8px; }
        .mobile-links a:hover, .mobile-links a.active-link { color: var(--primary-color); background-color: rgba(67,97,238,0.1); }
        .mobile-menu .btn .material-symbols-outlined { font-size: 1.2em; vertical-align: middle; margin-right: 4px; }
        .mobile-menu .btn { margin-top: 2rem; width: 100%; max-width: 200px; }
        .close-btn { position: absolute; top: 30px; right: 30px; font-size: 1.8rem; color: var(--dark-color); cursor: pointer; transition: var(--transition); line-height: 1; }
        .close-btn:hover { color: var(--primary-color); transform: rotate(90deg); }


.mobile-nav-user-photo { 
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 12px;
    border: 1.5px solid var(--light-gray);
}
.nav-item-profile { /* Pro lepší zarovnání v mobilním menu */
    display: flex;
    align-items: center;
    justify-content: center; /* Centrování obsahu, pokud je odkaz blokový */
}
.nav-item-profile .profile-link {
    display: inline-flex; /* Aby se obrázek a text zarovnaly vedle sebe */
    align-items: center;
}


@media (max-width: 1264px) { /* Změna breakpointu, pokud chcete menu dříve */
    .nav-links { display: none; }
    .hamburger { display: flex; /* Zobrazení hamburgeru */ }
}

</style>

<script>
    // Mobile menu functionality
    const hamburger = document.getElementById('hamburger');
    const mobileMenu = document.getElementById('mobileMenu');
    const closeMenuBtn = document.getElementById('closeMenu'); // Přejmenováno pro jasnost
    const body = document.body;
    
    if (hamburger && mobileMenu && closeMenuBtn) {
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            mobileMenu.classList.toggle('active');
            body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
        });
        
        closeMenuBtn.addEventListener('click', () => { // Použití přejmenované proměnné
            hamburger.classList.remove('active');
            mobileMenu.classList.remove('active');
            body.style.overflow = '';
        });
        
        // Zavření menu po kliknutí na odkaz v mobilním menu
        const mobileLinks = document.querySelectorAll('.mobile-menu .mobile-links a');
        mobileLinks.forEach(link => {
            link.addEventListener('click', () => {
                // Vždy zavřít menu po kliknutí na odkaz v mobilním zobrazení
                if (mobileMenu.classList.contains('active')) {
                    hamburger.classList.remove('active');
                    mobileMenu.classList.remove('active');
                    body.style.overflow = '';
                }
            });
        });
    }
    
    // Smooth scrolling for anchor links (ponecháno, ale zkontrolujte relevantnost)
    document.querySelectorAll('a[href^="#"], a[href^="index.php#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            let targetId = '';
            
            if (href.startsWith('index.php#')) {
                targetId = href.substring(href.indexOf('#') + 1);
                // If not on index.php, redirect first
                if (!window.location.pathname.endsWith('/') && !window.location.pathname.endsWith('index.php')) {
                    window.location.href = '<?php echo htmlspecialchars($asset_prefix); ?>index.php#' + targetId;
                    return;
                }
            } else if (href.startsWith('#') && href.length > 1) {
                targetId = href.substring(1);
                 // Ensure we are on a page that could contain this ID, or it's a generic anchor
                if (!document.getElementById(targetId) && !window.location.pathname.endsWith('/') && !window.location.pathname.endsWith('index.php')) {
                    // Potentially navigate to index.php if it's a common anchor like #faq from another page
                    // This part might need more specific logic based on your site structure
                     window.location.href = '<?php echo htmlspecialchars($asset_prefix); ?>index.php#' + targetId;
                     return;
                }

            } else {
                return; // Not a valid local anchor
            }

            const targetElement = document.getElementById(targetId);
            if (targetElement) {
                e.preventDefault();
                const headerOffset = document.querySelector('header') ? document.querySelector('header').offsetHeight : 80; // fallback height
                const elementPosition = targetElement.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
            
                window.scrollTo({
                    top: offsetPosition,
                    behavior: "smooth"
                });
            }
        });
    });
    
    // Header shadow on scroll
    const pageHeader = document.querySelector('header');
    if (pageHeader) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 10) {
                pageHeader.style.boxShadow = '0 4px 10px rgba(0,0,0,0.07)';
            } else {
                pageHeader.style.boxShadow = '0 2px 10px rgba(0,0,0,0.05)';
            }
        });
    }
    
    // Set active nav link based on current page (zjednodušená verze)
    function setActiveNavLink() {
        const navLinks = document.querySelectorAll('.nav-links a, .mobile-links a'); // Zahrnuje i .btn pro případ aktivního login odkazu
        let currentFullUrl = window.location.href; // Celá URL
        let currentPathname = window.location.pathname; // Jen cesta, např. /admin/admin-panel.php
        let currentPageFile = currentPathname.substring(currentPathname.lastIndexOf('/') + 1); // Jen název souboru

        navLinks.forEach(link => {
            const linkHref = link.getAttribute('href');
            if (!linkHref) return;

            let linkPageFile = linkHref.substring(linkHref.lastIndexOf('/') + 1).split('?')[0].split('#')[0];
            
            // Normalizace pro index.php
            if (currentPageFile === "" || currentPageFile === "index.php") {
                 currentPageFile = "index.php";
            }
            if (linkPageFile === "" || linkPageFile === "index.php") {
                linkPageFile = "index.php";
            }
            
            // Přímé porovnání názvu souboru
            if (linkPageFile === currentPageFile) {
                // Speciální případ pro messages.php s ?context=admin
                if (linkPageFile === 'admin-messages.php' || linkPageFile === 'messages.php') {
                    const urlParams = new URLSearchParams(window.location.search);
                    const linkParams = new URLSearchParams(link.search);
                    if (urlParams.get('context') === linkParams.get('context')) {
                        link.classList.add('active-link');
                    } else if (!urlParams.get('context') && !linkParams.get('context') && linkPageFile === 'messages.php') {
                         link.classList.add('active-link'); // Pro messages.php bez kontextu
                    }
                     else {
                        link.classList.remove('active-link');
                    }
                } else if (linkPageFile === 'index.php' && window.location.hash && linkHref.includes(window.location.hash)) {
                    // Pro odkazy s hashem na index.php
                     link.classList.add('active-link');
                } else if (linkPageFile === 'index.php' && window.location.hash && !linkHref.includes('#')){
                    // Pokud jsme na index.php s hashem, ale odkaz je jen na index.php bez hashe
                    link.classList.remove('active-link');
                }
                else if (linkPageFile !== 'index.php' || (linkPageFile === 'index.php' && !window.location.hash)){
                    link.classList.add('active-link');
                }
                 else {
                     link.classList.remove('active-link');
                 }

            } else {
                link.classList.remove('active-link');
            }
        });
    }
    
    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setActiveNavLink);
    } else {
        setActiveNavLink(); // Již načteno
    }

</script>