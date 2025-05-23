<?php
// components/main-header.php

// Session by měla být již spuštěna stránkou, která tento soubor includuje.
// Předpokládáme, že jsou dostupné:
// $_SESSION['loggedin'], $_SESSION['user_id'], $_SESSION['first_name'], 
// $_SESSION['role_name'] (nebo $_SESSION['roleID'] / $_SESSION['role'])
// $_SESSION['profile_photo']

// $currentPage by měla být definována includující stránkou.
if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF']); // Fallback
}

// Základní session proměnné s fallbacks
$sessionIsLoggedIn = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'User';

// Určení role - priorita $_SESSION['role_name'], pak $_SESSION['roleID'], pak $_SESSION['role']
$userRole = 'employee'; // Default role
if (isset($_SESSION["role_name"])) {
    $userRole = strtolower(htmlspecialchars($_SESSION["role_name"]));
} elseif (isset($_SESSION["roleID"])) { // Pokud máte roleID a je to 'admin' nebo 'employee'
    $userRole = strtolower(htmlspecialchars($_SESSION["roleID"]));
} elseif (isset($_SESSION["role"])) {
    $userRole = strtolower(htmlspecialchars($_SESSION["role"]));
}


// --- Dynamické nastavení cest podle umístění includujícího skriptu ---
$pathPrefix = ""; // Předpoklad: header je v components/, skript je v rootu projektu
// Zjistíme, zda je aktuální skript ve složce 'admin/'
if (strpos($_SERVER['SCRIPT_FILENAME'], DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR) !== false) {
    // Jsme v admin sekci (např. /var/www/html/projekt/admin/admin-panel.php)
    // components/ je o úroveň výš
    $pathPrefix = "../"; 
}
// Pokud byste měli složitější strukturu, tato logika by se musela přizpůsobit.

// Cesty pro odkazy a assety
$base_path_for_root_links = $pathPrefix; // Pro odkazy na soubory v rootu projektu
$base_path_for_admin_links = ($userRole === 'admin' && $pathPrefix === "") ? "admin/" : ""; // Pro odkazy na admin stránky z rootu
if ($userRole === 'admin' && $pathPrefix === "../") {
    $base_path_for_admin_links = ""; // Jsme již v admin, tak není třeba prefix 'admin/'
}

$base_path_for_assets = $pathPrefix; // Pro obrázky atd.


// --- Profile Photo URL Construction ---
$profile_photo_url = null;
// Adresář pro fotky relativně k rootu projektu
$profile_upload_dir_relative_to_project_root = defined('PROFILE_UPLOAD_DIR_FROM_PROJECT_ROOT') ? PROFILE_UPLOAD_DIR_FROM_PROJECT_ROOT : 'profile_photos/';
// Název výchozího avataru
$default_avatar_filename = defined('DEFAULT_AVATAR_FILENAME') ? DEFAULT_AVATAR_FILENAME : 'default_avatar.jpg';

// Sestavení plné webové cesty k adresáři profilových fotek z rootu webu
// Předpoklad: $base_path_for_assets již správně ukazuje na root projektu z aktuálního skriptu
$web_profile_photos_path = htmlspecialchars($base_path_for_assets . $profile_upload_dir_relative_to_project_root);

// Výchozí URL pro avatar
$profile_photo_url = $web_profile_photos_path . $default_avatar_filename;

if ($sessionIsLoggedIn && isset($_SESSION["profile_photo"]) && !empty($_SESSION["profile_photo"])) {
    $current_photo_filename = basename($_SESSION["profile_photo"]);
    if ($current_photo_filename !== $default_avatar_filename) {
        $profile_photo_url = $web_profile_photos_path . htmlspecialchars($current_photo_filename);
    }
}
?>
<header>
    <div class="container"> 
        <nav class="navbar">
            <a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>index.php" class="logo">
                <img src="<?php echo htmlspecialchars($base_path_for_assets); ?>imgs/logo.png" alt="WavePass Logo" class="logo-img">
                Wave<span>Pass</span> 
                <?php if ($userRole === 'admin'): ?>
                    <span class="admin-badge">Admin</span>
                <?php endif; ?>
            </a>
            <ul class="nav-links">
                <?php if ($sessionIsLoggedIn): ?>
                    <?php if ($userRole === 'admin'): ?>
                        <!-- Admin Links -->
                        <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>../dashboard.php" class="<?php if ($currentPage === 'dashboard.php') echo 'active-nav-link'; ?>">User Dashboard</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_admin_links); ?>admin-panel.php" class="<?php if ($currentPage === 'admin-panel.php') echo 'active-nav-link'; ?>">Admin Panel</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_admin_links); ?>admin-manage-rfid.php" class="<?php if ($currentPage === 'admin-manage-rfid.php') echo 'active-nav-link'; ?>">RFID Cards</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_admin_links); ?>admin-manage-employees.php" class="<?php if ($currentPage === 'admin-manage-employees.php') echo 'active-nav-link'; ?>">Employees</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_admin_links); ?>admin-manage-absences.php" class="<?php if ($currentPage === 'admin-manage-absences.php') echo 'active-nav-link'; ?>">Absences</a></li>
                    <?php else: ?>
                        <!-- Employee Links -->
                        <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>dashboard.php" class="<?php if ($currentPage === 'dashboard.php') echo 'active-nav-link'; ?>">My Dashboard</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>my_attendance_log.php" class="<?php if ($currentPage === 'my_attendance_log.php') echo 'active-nav-link'; ?>">Attendance Log</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>absences.php" class="<?php if ($currentPage === 'absences.php') echo 'active-nav-link'; ?>">Absence</a></li>
                        <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>messages.php" class="<?php if ($currentPage === 'messages.php') echo 'active-nav-link'; ?>">Messages</a></li>
                    <?php endif; ?>
                    
                    <!-- Common Logged In Links -->
                    <li>
                        <a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>profile.php?section=profile" class="nav-profile-link <?php if ($currentPage === 'profile.php') echo 'active-nav-link'; ?>"> 
                            <img src="<?php echo $profile_photo_url; ?>?<?php echo time(); // Cache buster ?>" alt="Profile" class="nav-user-photo">
                            <?php echo $sessionFirstName; ?>
                        </a>
                    </li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>logout.php" class="btn btn-outline">Logout</a></li> 
                <?php else: ?>
                    <!-- Logged Out Links -->
                    <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>index.php#features" class="<?php if ($currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '#features') !== false) echo 'active-nav-link'; ?>">Features</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>pricing.php" class="<?php if ($currentPage === 'pricing.php') echo 'active-nav-link'; ?>">Pricing</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>index.php#faq" class="<?php if ($currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '#faq') !== false) echo 'active-nav-link'; ?>">FAQ</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>login.php" class="btn btn-outline">Login</a></li> 
                <?php endif; ?>
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
        <?php if ($sessionIsLoggedIn): ?>
            <?php if ($userRole === 'admin'): ?>
                <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>dashboard.php" class="<?php if ($currentPage === 'dashboard.php') echo 'active-nav-link'; ?>">User Dashboard</a></li>
                <li><a href="<?php echo htmlspecialchars($base_path_for_admin_links); ?>admin-panel.php" class="<?php if ($currentPage === 'admin-panel.php') echo 'active-nav-link'; ?>">Admin Panel</a></li>
                <li><a href="<?php echo htmlspecialchars($base_path_for_admin_links); ?>admin-manage-rfid.php" class="<?php if ($currentPage === 'admin-manage-rfid.php') echo 'active-nav-link'; ?>">RFID Cards</a></li>
                <li><a href="<?php echo htmlspecialchars($base_path_for_admin_links); ?>admin-manage-employees.php" class="<?php if ($currentPage === 'admin-manage-employees.php') echo 'active-nav-link'; ?>">Employees</a></li>
                <li><a href="<?php echo htmlspecialchars($base_path_for_admin_links); ?>admin-manage-absences.php" class="<?php if ($currentPage === 'admin-manage-absences.php') echo 'active-nav-link'; ?>">Absences</a></li>
            <?php else: ?>
                <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>dashboard.php" class="<?php if ($currentPage === 'dashboard.php') echo 'active-nav-link'; ?>">My Dashboard</a></li>
                <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>my_attendance_log.php" class="<?php if ($currentPage === 'my_attendance_log.php') echo 'active-nav-link'; ?>">Attendance Log</a></li>
                <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>absences.php" class="<?php if ($currentPage === 'absences.php') echo 'active-nav-link'; ?>">Absence</a></li>
                <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>messages.php" class="<?php if ($currentPage === 'messages.php') echo 'active-nav-link'; ?>">Messages</a></li>
            <?php endif; ?>
            <li>
                <a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>profile.php?section=profile" class="<?php if ($currentPage === 'profile.php') echo 'active-nav-link'; ?>">
                    <img src="<?php echo $profile_photo_url; ?>?<?php echo time(); ?>" alt="Profile" class="nav-user-photo mobile-nav-user-photo">
                    My Profile
                </a>
            </li>
            <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>logout.php" class="btn btn-outline" style="width:100%; margin-top:1rem;">Log out</a> </li>
        <?php else: ?>
            <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>index.php#features" class="<?php if ($currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '#features') !== false) echo 'active-nav-link'; ?>">Features</a></li>
            <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>pricing.php" class="<?php if ($currentPage === 'pricing.php') echo 'active-nav-link'; ?>">Pricing</a></li>
            <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>index.php#faq" class="<?php if ($currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], '#faq') !== false) echo 'active-nav-link'; ?>">FAQ</a></li>
            <li><a href="<?php echo htmlspecialchars($base_path_for_root_links); ?>login.php" class="btn btn-primary" style="width:100%; margin-top:1rem;">Login</a></li>
        <?php endif; ?>
    </ul>
</div>

<style>
/* Základní styly pro header, pokud nejsou globálně definovány */
:root {
    --primary-color: #4361ee;
    --primary-dark: #3a56d4;
    --primary-color-rgb: 67, 97, 238;
    --dark-color: #1a1a2e;
    --white: #ffffff;
    --light-gray: #e9ecef;
    --gray-color: #6c757d;
    --danger-color: #F44336;
    --transition: all 0.3s ease;
}

header {
    background-color: var(--white);
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    position: fixed;
    width: 100%;
    top: 0;
    z-index: 1000;
}
header > .container {
    max-width: 1440px; 
    margin-left: auto;
    margin-right: auto;
    padding-left: 20px; 
    padding-right: 20px;
    height: 80px; /* Výška headeru */
    display: flex; /* Pro zarovnání .navbar */
    align-items: center; /* Pro zarovnání .navbar */
}
.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.logo {
    font-size: 1.7rem; /* Mírně menší */
    font-weight: 800;
    color: var(--primary-color);
    text-decoration: none;
    display: flex;
    align-items: center;
}
.logo-img {
    height: 40px; /* Mírně menší */
    width: auto;  
    margin-right: 0.5rem; 
}
.logo span {
    color: var(--dark-color); 
    font-weight: 600; 
}
.admin-badge {
    font-size: 0.75rem; /* Zvětšeno pro čitelnost */
    font-weight: 600; /* Zvýraznění */
    color: var(--white);
    background-color: var(--primary-color); /* Lepší kontrast */
    padding: 3px 7px; /* Více paddingu */
    border-radius: 4px;
    margin-left: 0.6rem;
    vertical-align: middle; /* Lepší zarovnání */
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.nav-links {
    display: flex;
    list-style: none;
    align-items: center;
    gap: 0.5rem; /* Mírně větší mezery */
    margin: 0; /* Odstranění výchozího marginu */
    padding: 0; /* Odstranění výchozího paddingu */
}
.nav-links a:not(.btn-outline) {
    color: var(--dark-color);
    text-decoration: none;
    font-weight: 500; 
    padding: 0.7rem 1rem; /* Upravený padding */
    font-size: 0.9rem; 
    border-radius: 6px; 
    transition: var(--transition);
    display: inline-flex; 
    align-items: center; 
}
.nav-links a:not(.btn-outline):hover, 
.nav-links a:not(.btn-outline).active-nav-link {
    color: var(--primary-color);
    background-color: rgba(var(--primary-color-rgb), 0.08); /* Mírně výraznější pozadí */
}

.nav-links .btn-outline {
    padding: 0.7rem 1.3rem; /* Upravený padding */
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.9rem; /* Sjednocení velikosti písma */
    border: 2px solid var(--primary-color);
    color: var(--primary-color);
    background-color: transparent;
    text-decoration: none;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.nav-links .btn-outline:hover {
    background-color: var(--primary-color);
    color: var(--white);
    transform: translateY(-1px); /* Jemný hover efekt */
}

.nav-user-photo {
    width: 32px; 
    height: 32px;
    border-radius: 50%; 
    object-fit: cover; 
    margin-right: 10px; /* Větší mezera */
    border: 2px solid var(--light-gray); /* Výraznější okraj */
}
.nav-links a.nav-profile-link { /* Specifické styly pro profilový odkaz */
    padding-right: 0.5rem; /* Menší padding vpravo, pokud je fotka */
}

/* Hamburger and Mobile Menu */
.hamburger {
    display: none; 
    flex-direction: column;
    justify-content: space-around; 
    width: 28px; 
    height: 22px; 
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0;
    z-index: 1002; 
}
.hamburger span {
    display: block; width: 100%; height: 3px;
    background-color: var(--dark-color);
    border-radius: 3px; /* Mírně menší radius */
    transition: all 0.3s linear;
}
.hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(2px, -2px); } /* Upravená transformace */
.hamburger.active span:nth-child(2) { opacity: 0; transform: translateX(15px); }
.hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(2px, 1px); } /* Upravená transformace */


.mobile-menu {
    position: fixed; top: 0; right: -100%;
    width: 280px; height: 100vh;
    background-color: var(--white);
    box-shadow: -3px 0 15px rgba(0, 0, 0, 0.1); /* Mírnější stín */
    padding: 20px; /* Sjednocený padding */
    padding-top: 70px; /* Místo pro close button */
    transition: right 0.35s cubic-bezier(0.68, -0.55, 0.27, 1.55); /* Upravená animace */
    z-index: 1001; 
    display: flex; flex-direction: column;
    overflow-y: auto;
}
.mobile-menu.active { right: 0; }

.mobile-links {
    list-style: none; padding: 0; margin: 0;
    display: flex; flex-direction: column;
    gap: 0.8rem; /* Větší mezery mezi odkazy */
    flex-grow: 1; 
}
.mobile-links li { width: 100%; }
.mobile-links a {
    display: flex; align-items: center;
    padding: 0.9rem 1.2rem; /* Větší padding */
    text-decoration: none; color: var(--dark-color);
    font-size: 1rem; border-radius: 8px; /* Větší radius */
    transition: var(--transition);
    font-weight: 500;
}
.mobile-links a:hover, .mobile-links a.active-nav-link {
    color: var(--primary-color);
    background-color: rgba(var(--primary-color-rgb), 0.08);
}
.mobile-menu .btn-outline {
    width: 100%; 
    margin-top: 1.5rem; /* Větší horní margin */
    padding: 0.9rem 1.2rem; /* Sjednocený padding */
    font-size: 0.95rem; /* Mírně větší písmo */
    margin-bottom: 1rem;
}
.close-btn {
    position: absolute;
    top: 20px; 
    right: 20px;
    font-size: 1.6rem; /* Mírně menší pro lepší vzhled s ikonou */
    color: var(--dark-color);
    cursor: pointer;
    background: none; border: none;
    padding: 8px; /* Větší klikací plocha */
    line-height: 1;
}
.close-btn:hover {
    color: var(--danger-color);
}

.mobile-links a .nav-user-photo.mobile-nav-user-photo {
    width: 30px; 
    height: 30px;
    margin-right: 12px; 
}

@media (max-width: 1024px) { /* Změna breakpointu pro zobrazení hamburgeru */
    .nav-links { display: none; } 
    .hamburger { display: flex; } 
}
</style>

<script>
// Tento skript by měl být ideálně v samostatném souboru a linkován,
// nebo alespoň na konci body, aby se prvky načetly.
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.getElementById('hamburger');
    const mobileMenu = document.getElementById('mobileMenu');
    const closeMenuButton = document.getElementById('closeMenu'); // Ujistěte se, že ID 'closeMenu' existuje u zavíracího tlačítka

    if (hamburger && mobileMenu) {
        hamburger.addEventListener('click', function() {
            this.classList.toggle('active');
            mobileMenu.classList.toggle('active');
            document.body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
        });

        if (closeMenuButton) {
            closeMenuButton.addEventListener('click', function() {
                hamburger.classList.remove('active');
                mobileMenu.classList.remove('active');
                document.body.style.overflow = '';
            });
        }

        // Zavření menu po kliknutí na odkaz v mobilním menu
        const mobileLinks = mobileMenu.querySelectorAll('.mobile-links a');
        mobileLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (mobileMenu.classList.contains('active')) {
                    hamburger.classList.remove('active');
                    mobileMenu.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
    }
});
</script>