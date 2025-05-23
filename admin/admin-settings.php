<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Restrict Access: Ensure only admin can access
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true ||
    !isset($_SESSION["role"]) || strtolower($_SESSION["role"]) !== 'admin') {
    header("location: ../login.php");
    exit;
}

require_once '../db.php'; // Included for future use with settings storage

$sessionAdminFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$sessionAdminUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;

$dbErrorMessage = null;
$successMessage = null;

// Determine the current settings section
$currentSection = isset($_GET['section']) ? $_GET['section'] : 'general';

// --- HANDLE SETTINGS FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($pdo)) {
    // This is a placeholder for actual settings saving logic
    // You would check which form was submitted (e.g., by a hidden input 'form_section')
    // and then process and save the data.

    if (isset($_POST['save_general_settings'])) {
        // Example: Process general settings
        $site_name = htmlspecialchars(trim($_POST['site_name']));
        // ... other general settings
        
        // Placeholder: In a real scenario, save to DB
        // For now, just show a success message
        $successMessage = "General settings (conceptually) updated successfully! Site Name: " . $site_name;
        // To see changes reflect, you'd typically reload settings from DB or update session/config vars
    
    } elseif (isset($_POST['save_appearance_settings'])) {
        $theme_color = htmlspecialchars($_POST['theme_color']);
        $successMessage = "Appearance settings (conceptually) updated! Theme Color: " . $theme_color;

    }  elseif (isset($_POST['save_notification_settings'])) {
        $admin_email_notifications = isset($_POST['admin_email_notifications']) ? 1 : 0;
        $user_email_notifications = isset($_POST['user_email_notifications']) ? 1 : 0;
        $successMessage = "Notification settings (conceptually) updated! Admin Email: " .($admin_email_notifications ? 'Enabled' : 'Disabled');
    }

    // Redirect to the same section to avoid form resubmission on refresh
    // header("location: admin-settings.php?section=" . urlencode($currentSection) . "&status=success");
    // For this example, we'll just let the messages display directly.
}

// --- (PLACEHOLDER) LOAD SETTINGS ---
// In a real application, you would load settings from the database here
// For example:
// $settings = [];
// $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
// while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
//    $settings[$row['setting_key']] = $row['setting_value'];
// }
//
// $siteName = $settings['site_name'] ?? 'WavePass';
// $defaultTimezone = $settings['default_timezone'] ?? 'UTC';
// $maintenanceMode = !empty($settings['maintenance_mode']);

// For this example, we'll use some default values
$currentSettings = [
    'general' => [
        'site_name' => 'WavePass Portal',
        'admin_email' => 'admin@example.com',
        'maintenance_mode' => false,
    ],
    'appearance' => [
        'theme_color' => '#4361ee', // Default primary color
        'dark_mode_default' => false,
    ],
    'notifications' => [
        'admin_email_notifications' => true,
        'user_email_notifications' => true,
    ]
];


$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../imgs/logo.png" type="image/x-icon">
    <title>Admin Settings - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --primary-color-rgb: 67, 97, 238;
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --success-color: #4CAF50; --warning-color: #FF9800; --danger-color: #F44336;
            --info-color: #2196F3;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex-grow: 1; padding-top: 80px;; } /* Adjusted for fixed header */

        .page-container {
            max-width: 1440px; margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px;
            display: flex; gap: 1.8rem; margin-top: 1.5rem; align-items: flex-start;
        }
        .container, .page-header .container {
             max-width: 1440px; margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px;
        }

        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }

        .message-output { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .db-error-message { background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        .success-message { background-color: rgba(76,175,80,0.1); color: var(--success-color); border-left-color: var(--success-color); }

        .sidebar {
            flex: 0 0 280px; background-color: var(--white); padding: 1.5rem;
            border-radius: 8px; box-shadow: var(--shadow); height: fit-content;
        }
        .sidebar h3 {
            font-size: 1.25rem; margin-bottom: 1.2rem; color: var(--dark-color);
            padding-bottom: 0.6rem; border-bottom: 1px solid var(--light-gray);
        }
        .filter-list { list-style: none; padding: 0; margin: 0; } /* Reusing 'filter-list' class for sidebar navigation */
        .filter-list li a {
            display: flex; align-items: center; gap: 0.8rem;
            padding: 0.85rem 1.1rem; text-decoration: none;
            color: var(--dark-color); border-radius: 6px;
            transition: var(--transition); font-weight: 500; font-size: 0.95rem;
        }
        .filter-list li a:hover, .filter-list li a.active-filter {
            background-color: rgba(var(--primary-color-rgb), 0.1);
            color: var(--primary-color); font-weight: 600;
        }
        .filter-list li a .material-symbols-outlined { font-size: 1.4em; }

        .main-content {
            flex-grow: 1; background-color: var(--white); padding: 1.5rem 1.8rem;
            border-radius: 8px; box-shadow: var(--shadow); border: 1px solid var(--light-gray);
        }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); }
        .panel-title { font-size: 1.3rem; color: var(--dark-color); margin:0; }

        .settings-form .form-group { margin-bottom: 1.5rem; }
        .settings-form label { display: block; font-weight: 600; margin-bottom: 0.5rem; font-size: 0.9rem; color: var(--dark-color); }
        .settings-form input[type="text"],
        .settings-form input[type="email"],
        .settings-form input[type="password"],
        .settings-form input[type="number"],
        .settings-form input[type="color"],
        .settings-form select,
        .settings-form textarea {
            width: 100%; padding: 0.75rem 1rem;
            border: 1px solid #ced4da; border-radius: 6px;
            font-size: 0.9rem; transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .settings-form input[type="text"]:focus,
        .settings-form input[type="email"]:focus,
        .settings-form input[type="password"]:focus,
        .settings-form input[type="number"]:focus,
        .settings-form input[type="color"]:focus,
        .settings-form select:focus,
        .settings-form textarea:focus {
            border-color: var(--primary-color); outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(var(--primary-color-rgb), 0.25);
        }
        .settings-form textarea { min-height: 100px; resize: vertical; }
        .settings-form .checkbox-group { display: flex; align-items: center; }
        .settings-form .checkbox-group input[type="checkbox"] { margin-right: 0.5rem; width: auto; }
        .settings-form .checkbox-group label { margin-bottom: 0; font-weight: normal; }
        .settings-form .form-text { font-size: 0.8rem; color: var(--gray-color); margin-top: 0.3rem; }

        .btn-save-settings {
            background-color: var(--primary-color); color: white; padding: 0.75rem 1.5rem;
            border: none; border-radius: 6px; cursor: pointer; font-size: 0.95rem;
            font-weight: 500; transition: var(--transition);
            display: inline-flex; align-items: center; gap: 0.5rem;
        }
        .btn-save-settings:hover { background-color: var(--primary-dark); }
        .btn-save-settings .material-symbols-outlined { font-size: 1.2em; }
        
        hr.settings-divider { border: 0; height: 1px; background-color: var(--light-gray); margin: 2rem 0; }

        @media (max-width: 992px) {
            .page-container { flex-direction: column; }
            .sidebar { width: 100%; margin-bottom: 1.5rem; }
        }
        
        footer { background-color: var(--dark-color); color:var(--light-gray); padding: 2rem 0; text-align: center; font-size: 0.9rem; margin-top: auto;}

    </style>
</head>
<body>
    <?php
        $headerPath = "../components/header-admin.php";
        if (file_exists($headerPath)) {
            require_once $headerPath;
        } else {
            echo "<!-- Admin header file not found -->";
        }
    ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>Admin Settings</h1>
                <p class="sub-heading">Configure system-wide settings and preferences.</p>
            </div>
        </div>

        <div class="page-container">
            <aside class="sidebar">
                <h3>Settings Sections</h3>
                <ul class="filter-list">
                    <li>
                        <a href="admin-settings.php?section=general" class="<?php if ($currentSection == 'general') echo 'active-filter'; ?>">
                            <span class="material-symbols-outlined">settings</span> General Settings
                        </a>
                    </li>
                    <li>
                        <a href="admin-settings.php?section=appearance" class="<?php if ($currentSection == 'appearance') echo 'active-filter'; ?>">
                            <span class="material-symbols-outlined">palette</span> Appearance
                        </a>
                    </li>
                    <li>
                        <a href="admin-settings.php?section=notifications" class="<?php if ($currentSection == 'notifications') echo 'active-filter'; ?>">
                            <span class="material-symbols-outlined">notifications_active</span> Notifications
                        </a>
                    </li>
                    <li>
                        <a href="admin-settings.php?section=security" class="<?php if ($currentSection == 'security') echo 'active-filter'; ?>">
                            <span class="material-symbols-outlined">security</span> Security
                        </a>
                    </li>
                    <!-- Add more sections as needed -->
                </ul>
            </aside>

            <div class="main-content">
                <?php if (isset($dbErrorMessage) && $dbErrorMessage): ?>
                    <div class="message-output db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbErrorMessage); ?></div>
                <?php endif; ?>
                <?php if (isset($successMessage) && $successMessage): ?>
                    <div class="message-output success-message" role="alert"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>

                <?php if ($currentSection == 'general'): ?>
                    <div class="panel-header">
                        <h2 class="panel-title">General Settings</h2>
                    </div>
                    <form action="admin-settings.php?section=general" method="POST" class="settings-form">
                        <input type="hidden" name="form_section" value="general">
                        <div class="form-group">
                            <label for="site_name">Site Name</label>
                            <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($currentSettings['general']['site_name']); ?>" required>
                            <p class="form-text">The name displayed in the browser tab and site header.</p>
                        </div>
                        <div class="form-group">
                            <label for="admin_email">Administrator Email</label>
                            <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($currentSettings['general']['admin_email']); ?>" required>
                            <p class="form-text">Email address for administrative notifications.</p>
                        </div>
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?php echo $currentSettings['general']['maintenance_mode'] ? 'checked' : ''; ?>>
                                <label for="maintenance_mode">Enable Maintenance Mode</label>
                            </div>
                            <p class="form-text">If checked, non-admin users will see a maintenance page.</p>
                        </div>
                        <button type="submit" name="save_general_settings" class="btn-save-settings">
                            <span class="material-symbols-outlined">save</span> Save General Settings
                        </button>
                    </form>

                <?php elseif ($currentSection == 'appearance'): ?>
                    <div class="panel-header">
                        <h2 class="panel-title">Appearance Settings</h2>
                    </div>
                    <form action="admin-settings.php?section=appearance" method="POST" class="settings-form">
                        <input type="hidden" name="form_section" value="appearance">
                        <div class="form-group">
                            <label for="theme_color">Primary Theme Color</label>
                            <input type="color" id="theme_color" name="theme_color" value="<?php echo htmlspecialchars($currentSettings['appearance']['theme_color']); ?>">
                            <p class="form-text">Choose the main color for the UI elements.</p>
                        </div>
                         <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="dark_mode_default" name="dark_mode_default" value="1" <?php echo $currentSettings['appearance']['dark_mode_default'] ? 'checked' : ''; ?>>
                                <label for="dark_mode_default">Enable Dark Mode by Default</label>
                            </div>
                            <p class="form-text">Users can still override this in their profile (if feature exists).</p>
                        </div>
                        <button type="submit" name="save_appearance_settings" class="btn-save-settings">
                            <span class="material-symbols-outlined">save</span> Save Appearance Settings
                        </button>
                    </form>

                <?php elseif ($currentSection == 'notifications'): ?>
                    <div class="panel-header">
                        <h2 class="panel-title">Notification Settings</h2>
                    </div>
                    <form action="admin-settings.php?section=notifications" method="POST" class="settings-form">
                        <input type="hidden" name="form_section" value="notifications">
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="admin_email_notifications" name="admin_email_notifications" value="1" <?php echo $currentSettings['notifications']['admin_email_notifications'] ? 'checked' : ''; ?>>
                                <label for="admin_email_notifications">Enable Email Notifications for Admins</label>
                            </div>
                            <p class="form-text">Receive emails for important system events (e.g., new user registration, critical errors).</p>
                        </div>
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="user_email_notifications" name="user_email_notifications" value="1" <?php echo $currentSettings['notifications']['user_email_notifications'] ? 'checked' : ''; ?>>
                                <label for="user_email_notifications">Enable Email Notifications for Users</label>
                            </div>
                            <p class="form-text">Allow users to receive emails (e.g., password reset, absence request updates).</p>
                        </div>
                        <button type="submit" name="save_notification_settings" class="btn-save-settings">
                            <span class="material-symbols-outlined">save</span> Save Notification Settings
                        </button>
                    </form>
                
                <?php elseif ($currentSection == 'security'): ?>
                    <div class="panel-header">
                        <h2 class="panel-title">Security Settings</h2>
                    </div>
                    <p>Security settings options (e.g., password policies, 2FA configuration, IP whitelisting) would go here.</p>
                    <p class="form-text">This section is a placeholder for future development.</p>
                    <!-- Example:
                    <form action="admin-settings.php?section=security" method="POST" class="settings-form">
                        <input type="hidden" name="form_section" value="security">
                        <div class="form-group">
                            <label for="min_password_length">Minimum Password Length</label>
                            <input type="number" id="min_password_length" name="min_password_length" value="8" min="6" max="32">
                        </div>
                        <button type="submit" name="save_security_settings" class="btn-save-settings">
                            <span class="material-symbols-outlined">save</span> Save Security Settings
                        </button>
                    </form>
                    -->

                <?php else: ?>
                    <div class="panel-header">
                        <h2 class="panel-title">Settings</h2>
                    </div>
                    <p>Select a settings section from the sidebar to configure the application.</p>
                <?php endif; ?>
            </div> <!-- end .main-content -->
        </div> <!-- end .page-container -->
    </main>

    <?php
        $footerPath = "../components/footer-admin.php";
        if (file_exists($footerPath)) {
            require_once $footerPath;
        } else {
            echo "<!-- Admin footer file not found -->";
        }
    ?>
    <script>
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');
        const closeMenuBtn = document.getElementById('closeMenu');
        const body = document.body;

        if (hamburger && mobileMenu) {
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                mobileMenu.classList.toggle('active');
                body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
                hamburger.setAttribute('aria-expanded', mobileMenu.classList.contains('active'));
                mobileMenu.setAttribute('aria-hidden', !mobileMenu.classList.contains('active'));
            });
            if (closeMenuBtn) {
                closeMenuBtn.addEventListener('click', () => {
                    mobileMenu.classList.remove('active');
                    hamburger.classList.remove('active');
                    body.style.overflow = '';
                    hamburger.setAttribute('aria-expanded', 'false');
                    mobileMenu.setAttribute('aria-hidden', 'true');
                    if (hamburger) hamburger.focus();
                });
            }
            mobileMenu.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    if (mobileMenu.classList.contains('active')) {
                        mobileMenu.classList.remove('active');
                        hamburger.classList.remove('active');
                        body.style.overflow = '';
                        hamburger.setAttribute('aria-expanded', 'false');
                        mobileMenu.setAttribute('aria-hidden', 'true');
                    }
                });
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && mobileMenu.classList.contains('active')) {
                    if(closeMenuBtn) closeMenuBtn.click(); else hamburger.click();
                }
            });
        }
    </script>
</body>
</html>