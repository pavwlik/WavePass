<?php
// This file is assumed to be included by admin PHP pages like admin-dashboard.php, admin-manage-employees.php etc.
// Session should already be started.
// $_SESSION['user_id'], $_SESSION['first_name'], $_SESSION['role'], $_SESSION['profile_photo'] should be available.

// $currentPage should be defined by the including script (e.g., basename($_SERVER['PHP_SELF']))
if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF']); // Fallback if not set by parent
}
if (!isset($sessionFirstName)) { // Fallback if not set by parent script (though it should be)
    $sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
}


// --- Path Adjustments ---
// Determine base path for assets and links if this header is in 'components/'
// and included from files in 'admin/' which is a sibling of 'components/'.
// If included from admin/some_file.php:
// - Links to other admin pages: admin-other-page.php (no path change needed)
// - Links to root files (like logout.php, index.php): ../logout.php
// - Asset paths (imgs, profile_photos): ../imgs/, ../profile_photos/

$base_path_for_root_links = "../"; // Go up one level from 'admin/' to reach root
$base_path_for_assets = "../";    // Go up one level from 'admin/' to reach root asset folders

// Profile photo URL construction
$profile_photo_url = null;
if (isset($_SESSION["profile_photo"]) && !empty($_SESSION["profile_photo"])) {
    $profile_upload_dir_relative_to_root = defined('PROFILE_UPLOAD_DIR_FROM_ROOT') ? PROFILE_UPLOAD_DIR_FROM_ROOT : 'profile_photos/';
    $photo_filename = ltrim(basename($_SESSION["profile_photo"]), '/');
    // Construct path relative to the root of the site for web access
    $profile_photo_url = htmlspecialchars($base_path_for_assets . $profile_upload_dir_relative_to_root . $photo_filename);
}
?>
<header>
    <div class="container"> 
        <nav class="navbar">
            <a href="<?php echo $base_path_for_root_links; ?>admin/admin-dashboard.php" class="logo">
                <img src="<?php echo $base_path_for_assets; ?>imgs/logo.png" alt="WavePass Logo" class="logo-img">
                Wave<span>Pass</span> <span class="admin-badge">[Admin]</span>
            </a>
            <ul class="nav-links">
                <li><a href="admin-dashboard.php" class="<?php if ($currentPage === 'admin-dashboard.php') echo 'active-nav-link'; ?>">Dashboard</a></li>
                <li><a href="admin-manage-employees.php" class="<?php if ($currentPage === 'admin-manage-employees.php') echo 'active-nav-link'; ?>">Employees</a></li>
                <li><a href="admin-manage-rfid.php" class="<?php if ($currentPage === 'admin-manage-rfid.php') echo 'active-nav-link'; ?>">RFID Cards</a></li>
                <li><a href="admin-manage-absences.php" class="<?php if ($currentPage === 'admin-manage-absences.php') echo 'active-nav-link'; ?>">Absences</a></li>
                <li><a href="<?php echo $base_path_for_root_links; ?>messages.php" class="<?php if ($currentPage === 'messages.php') echo 'active-nav-link'; ?>">Messages</a></li> 
                <li><a href="admin-system-logs.php" class="<?php if ($currentPage === 'admin-system-logs.php') echo 'active-nav-link'; ?>">System Logs</a></li>
                <li><a href="admin-settings.php" class="<?php if ($currentPage === 'admin-settings.php') echo 'active-nav-link'; ?>">Settings</a></li>
                <li>
                    <a href="<?php echo $base_path_for_root_links; ?>profile.php?section=profile" class="nav-profile-link <?php if ($currentPage === 'profile.php') echo 'active-nav-link'; ?>"> 
                        <?php if ($profile_photo_url): ?>
                            <img src="<?php echo $profile_photo_url; ?>" alt="Profile" class="nav-user-photo">
                        <?php else: ?>
                            <span class="material-symbols-outlined">account_circle</span>
                        <?php endif; ?>
                        <?php echo $sessionFirstName; ?>
                    </a>
                </li>
                <li><a href="<?php echo $base_path_for_root_links; ?>logout.php" class="btn btn-outline">Logout</a></li> 
            </ul>
            <div class="hamburger" id="hamburger">
                <span></span><span></span><span></span>
            </div>
        </nav>
    </div>
</header>
<div class="mobile-menu" id="mobileMenu"> 
    <span class="close-btn" id="closeMenu"><i class="fas fa-times"></i></span>
    <ul class="mobile-links">
         <li><a href="admin-dashboard.php" class="<?php if ($currentPage === 'admin-dashboard.php') echo 'active-nav-link'; ?>">Dashboard</a></li>
         <li><a href="admin-manage-employees.php" class="<?php if ($currentPage === 'admin-manage-employees.php') echo 'active-nav-link'; ?>">Employees</a></li>
         <li><a href="admin-manage-rfid.php" class="<?php if ($currentPage === 'admin-manage-rfid.php') echo 'active-nav-link'; ?>">RFID Cards</a></li>
         <li><a href="admin-manage-absences.php" class="<?php if ($currentPage === 'admin-manage-absences.php') echo 'active-nav-link'; ?>">Absences</a></li>
         <li><a href="<?php echo $base_path_for_root_links; ?>messages.php" class="<?php if ($currentPage === 'messages.php') echo 'active-nav-link'; ?>">Messages</a></li>
         <li><a href="admin-system-logs.php" class="<?php if ($currentPage === 'admin-system-logs.php') echo 'active-nav-link'; ?>">System Logs</a></li>
         <li><a href="admin-settings.php" class="<?php if ($currentPage === 'admin-settings.php') echo 'active-nav-link'; ?>">Settings</a></li>
         <li>
            <a href="<?php echo $base_path_for_root_links; ?>profile.php?section=profile" class="<?php if ($currentPage === 'profile.php') echo 'active-nav-link'; ?>">
                <?php if ($profile_photo_url): ?>
                    <img src="<?php echo $profile_photo_url; ?>" alt="Profile" class="nav-user-photo mobile-nav-user-photo">
                <?php endif; ?>
                My Profile (Admin)
            </a>
        </li>
    </ul>
    <a href="<?php echo $base_path_for_root_links; ?>logout.php" class="btn btn-outline">Log out</a> 
</div>

<style>
/* These styles should ideally be in a global CSS file linked by all pages. */
/* If not, they need to be present here or in the including admin page. */
:root {
    --primary-color: #4361ee;
    --primary-dark: #3a56d4;
    --primary-color-rgb: 67, 97, 238; /* For rgba */
    --dark-color: #1a1a2e;
    --white: #ffffff;
    --light-gray: #e9ecef;
    --gray-color: #6c757d;
    --danger-color: #F44336;
    --transition: all 0.3s ease;
}

/* Ensure .container within header is styled for max-width and centering if header is full-width */
header > .container { /* Targeting the .container directly under header */
    max-width: 1440px; /* Or your site's max width */
    margin-left: auto;
    margin-right: auto;
    padding-left: 20px; /* Consistent with page content padding */
    padding-right: 20px;
}


.logo {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--primary-color);
    text-decoration: none;
    display: flex;
    align-items: center;
}
.logo-img {
    height: 45px; /* Adjusted slightly */
    width: auto;  
    vertical-align: middle; 
    margin-right: 0.6rem; 
}
.logo span {
    color: var(--dark-color); 
    font-weight: 600; 
}
.admin-badge {
    font-size: 0.7rem;
    font-weight: 500;
    color: var(--danger-color);
    border: 1px solid var(--danger-color);
    padding: 2px 5px;
    border-radius: 4px;
    margin-left: 0.5rem;
    vertical-align: middle;
}

.nav-links {
    display: flex;
    list-style: none;
    align-items: center;
    gap: 0.3rem; /* Slightly reduced gap for more links */
}
.nav-links a:not(.btn-outline) { /* Target non-button links specifically */
    color: var(--dark-color);
    text-decoration: none;
    font-weight: 500; 
    padding: 0.6rem 0.9rem; /* Adjusted padding */
    font-size: 0.9rem; /* Adjusted font size */
    border-radius: 6px; 
    position: relative; 
    transition: var(--transition);
    display: inline-flex; 
    align-items: center; 
}
.nav-links a:not(.btn-outline):hover, 
.nav-links a:not(.btn-outline).active-nav-link {
    color: var(--primary-color);
    background-color: rgba(var(--primary-color-rgb), 0.07); 
}

.nav-links .btn-outline {
    display: inline-flex;
    gap: 8px; 
    align-items: center;
    justify-content: center;
    padding: 0.6rem 1.2rem; /* Adjusted padding */
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    transition: var(--transition);
    cursor: pointer;
    text-align: center;
    font-size: 0.85rem; /* Adjusted font size */
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

.nav-user-photo {
    width: 30px; 
    height: 30px;
    border-radius: 50%; 
    object-fit: cover; 
    margin-right: 8px; 
    vertical-align: middle; 
    border: 1.5px solid var(--light-gray); 
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.nav-links a .material-symbols-outlined { 
    font-size: 1.4em; 
    vertical-align: middle;
    margin-right: 6px;
    line-height: 1; 
}

/* Hamburger and Mobile Menu - Ensure these are complete and correct */
.hamburger {
    display: none; /* Hidden on desktop */
    flex-direction: column;
    justify-content: space-around; /* Or space-between */
    width: 28px; /* Slightly smaller */
    height: 22px; /* Slightly smaller */
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0;
    z-index: 1002; /* Above mobile menu overlay */
}
.hamburger span {
    display: block;
    width: 100%;
    height: 3px;
    background-color: var(--dark-color);
    border-radius: 10px;
    transition: all 0.3s linear;
    position: relative;
    transform-origin: 1px;
}
.hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(1px, -1px); }
.hamburger.active span:nth-child(2) { opacity: 0; transform: translateX(20px); }
.hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(2px, 0px); }


.mobile-menu {
    position: fixed;
    top: 0;
    right: -100%; /* Start off-screen to the right */
    width: 280px; /* Or your preferred width */
    height: 100vh;
    background-color: var(--white);
    box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
    padding: 60px 20px 20px; /* Top padding for close button */
    transition: right 0.4s cubic-bezier(0.23, 1, 0.32, 1);
    z-index: 1001; /* Below hamburger when active, above page content */
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}
.mobile-menu.active {
    right: 0; /* Slide in from the right */
}

.mobile-links {
    list-style: none;
    padding: 0;
    margin: 20px 0 0 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex-grow: 1; /* Pushes logout button down */
}
.mobile-links li { width: 100%; }
.mobile-links a {
    display: flex; 
    align-items: center;
    padding: 0.8rem 1rem;
    text-decoration: none;
    color: var(--dark-color);
    font-size: 1rem;
    border-radius: 6px;
    transition: var(--transition);
    font-weight: 500;
}
.mobile-links a:hover, .mobile-links a.active-nav-link {
    color: var(--primary-color);
    background-color: rgba(var(--primary-color-rgb), 0.07);
}
.mobile-menu .btn-outline {
    width: 100%; 
    margin-top: auto; /* Pushes to bottom */
    padding-top: 0.8rem;
    padding-bottom: 0.8rem;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}
.close-btn {
    position: absolute;
    top: 18px; /* Adjusted for visual balance */
    right: 20px;
    font-size: 1.8rem;
    color: var(--dark-color);
    cursor: pointer;
    background: none;
    border: none;
    padding: 5px;
}
.close-btn .fas.fa-times { line-height: 1; }

.mobile-links a .nav-user-photo.mobile-nav-user-photo {
    width: 28px; 
    height: 28px;
    margin-right: 10px; 
}

@media (max-width: 992px) { /* Adjust breakpoint if necessary */
    .nav-links { display: none; } 
    .hamburger { display: flex; } 
}
</style>