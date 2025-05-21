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
    <span class="close-btn" id="closeMenu"><i class="fas fa-times"></i></span>
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
    <a href="logout.php" class="btn btn-outline">Logout</a> 
</div>

<style>
/* Styles from your previous index.php/assistent.html for the general navbar */
:root {
    --primary-color: #4361ee;
    --primary-dark: #3a56d4;
    /* ... other root vars from your main style ... */
    --dark-color: #1a1a2e;
    --white: #ffffff;
    --light-gray: #e9ecef;
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
    height: 50px; 
    width: auto;  
    vertical-align: middle; 
    margin-right: 0.5rem; /* Adjusted from your snippet */
}
.logo span {
    color: var(--dark-color); 
    font-weight: 600; 
}

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
    padding: 0.7rem 1rem; 
    font-size: 0.95rem; /* From your public index styling */
    border-radius: 8px; 
    position: relative; 
    transition: var(--transition, all 0.3s ease); /* Added fallback for var */
    display: inline-flex; /* To align icon/photo with text */
    align-items: center; /* To align icon/photo with text */
}
.nav-links a:not(.btn):hover, .nav-links a.active-nav-link {
    color: var(--primary-color);
    background-color: rgba(67, 97, 238, 0.07); 
}
.nav-links .btn-outline {
    display: inline-flex;
    gap: 8px; /* From your public index styling */
    align-items: center;
    justify-content: center;
    padding: 0.7rem 1.5rem; /* From your public index styling */
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: var(--transition, all 0.3s ease);
    cursor: pointer;
    text-align: center;
    font-size: 0.9rem; /* From your public index styling */
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

/* Profile photo in Navbar */
.nav-user-photo {
    width: 30px;  /* Adjust size to be icon-like */
    height: 30px;
    border-radius: 50%; /* Circular */
    object-fit: cover; /* Ensure image covers the area without distortion */
    margin-right: 8px; /* Space between photo and text */
    vertical-align: middle; /* Align with text */
    border: 1.5px solid var(--light-gray); /* Optional subtle border */
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.nav-links a .material-symbols-outlined { /* For the fallback icon */
    font-size: 1.4em; /* Adjust to match visual size of photo */
    vertical-align: middle;
    margin-right: 6px;
    line-height: 1; 
}

/* Hamburger and Mobile Menu - Styles from your index.php/assistent.html should be here */
.hamburger { /* ... copy from index.php ... */ }
.hamburger span { /* ... */ }
.hamburger.active span { /* ... */ }
@media (max-width: 992px) { .nav-links { display: none; } .hamburger { display: flex; /* ensure it has display:flex */ flex-direction:column; justify-content:space-between; /* ... and other styles from index.php ... */ } }

.mobile-menu { /* ... copy from index.php ... */ }
.mobile-menu.active { /* ... */ }
.mobile-links { /* ... */ }
.mobile-links li { /* ... */ }
.mobile-links a { /* ... */ }
.mobile-links a:hover, .mobile-links a.active-nav-link { /* ... */ }
.mobile-menu .btn-outline { /* ... for logout button ... */ }
.close-btn { /* ... */ }

/* Specific styling for profile photo in mobile menu if needed */
.mobile-links a .nav-user-photo.mobile-nav-user-photo {
    width: 28px; 
    height: 28px;
    margin-right: 10px; /* Space for text */
    /* Other styles can be inherited or specific */
}

</style>