<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Include database connection if you need to fetch more data for the dashboard
// require_once 'db.php'; // You might need this if dashboard fetches more data

// Retrieve user data from session to display on the page
$firstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Guest';
$lastName = isset($_SESSION["last_name"]) ? htmlspecialchars($_SESSION["last_name"]) : '';
$role = isset($_SESSION["role"]) ? htmlspecialchars($_SESSION["role"]) : 'guest';
$email = isset($_SESSION["email"]) ? htmlspecialchars($_SESSION["email"]) : '';
$user_id = isset($_SESSION["user_id"]) ? htmlspecialchars($_SESSION["user_id"]) : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WavePass - Dashboard</title>
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
            --success-color: #4cc9f0; /* Greenish-blue for Present */
            --warning-color: #f8961e; /* Orange for Late */
            --danger-color: #f72585;  /* Pink/Red for Absent */
            --info-color: #54a0ff;   /* General Info Blue */
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
            background-color: var(--light-color); /* Slightly off-white background for dashboard area */
            overflow-x: hidden;
            scroll-behavior: smooth;
            display: flex; 
            flex-direction: column;
            min-height: 100vh;
        }
        
        main {
            flex-grow: 1; 
            padding-top: 80px; /* Height of the fixed header */
            background-color: #f4f7fc; /* Different background for the main content area */
        }

        h1, h2, h3, h4 {
            font-weight: 700;
            line-height: 1.2;
        }

        .container {
            max-width: 1400px; /* Wider container for dashboard */
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

/* Hamburger Menu Icon (This was already correct and consistent) */
.hamburger { display: none; cursor: pointer; width: 30px; height: 24px; position: relative; z-index: 1001; transition: var(--transition); }
        .hamburger span { display: block; width: 100%; height: 3px; background-color: var(--dark-color); position: absolute; left: 0; transition: var(--transition); transform-origin: center; }
        .hamburger span:nth-child(1) { top: 0; }
        .hamburger span:nth-child(2) { top: 50%; transform: translateY(-50%); }
        .hamburger span:nth-child(3) { bottom: 0; }
        .hamburger.active span:nth-child(1) { top: 50%; transform: translateY(-50%) rotate(45deg); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { bottom: 50%; transform: translateY(50%) rotate(-45deg); }

        /* Mobile Menu Panel (Updated to match target style) */
        .mobile-menu {
            position: fixed; top: 0; left: 0; width: 100%; height: 100vh;
            background-color: var(--white); z-index: 1000;
            display: flex; flex-direction: column; justify-content: center; align-items: center; /* Centered content */
            transform: translateX(-100%);
            transition: transform 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            padding: 2rem; /* Updated padding */
            overflow-y: auto; /* Keep for scrollability if many links */
        }
        .mobile-menu.active { transform: translateX(0); }

        .mobile-links {
            list-style: none;
            text-align: center; /* Updated text-align */
            width: 100%;
            max-width: 300px; /* Updated max-width */
            padding: 0;
            margin-top: 1rem; /* Or adjust as needed, could be 0 if relying on flex centering */
        }
        .mobile-links li {
            margin-bottom: 1.5rem; /* Updated margin */
        }
        .mobile-links a {
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 600; /* Updated font-weight */
            font-size: 1.2rem; /* Updated font-size */
            display: inline-block; /* Updated display */
            padding: 0.5rem 1rem; /* Updated padding */
            transition: color var(--transition), background-color var(--transition);
            border-radius: 8px; /* Updated border-radius */
            /* Removed border-bottom */
        }
        /* Removed .mobile-links li:first-child a rule */
        .mobile-links a:hover {
            color: var(--primary-color);
            background-color: rgba(67, 97, 238, 0.1); /* Updated hover background */
        }

        /* Button styles within mobile menu */
        .mobile-menu .btn { /* For primary button */
            margin-top: 2rem; /* Updated margin */
            width: 100%;
            max-width: 200px; /* Updated max-width */
            padding: 0.8rem 1.5rem; /* Kept from dashboard for consistency */
            font-size: 0.95rem; /* Kept from dashboard */
            text-align: center; /* Kept from dashboard */
        }
        .mobile-menu .btn .material-symbols-outlined { /* Kept from dashboard */
            font-size: 1.2em;
            vertical-align: middle;
            margin-right: 4px;
        }
        .mobile-menu .btn-outline { /* For outline button, if you use one */
            margin-top: 1rem; /* Kept from dashboard, adjust if only one button */
            width: 100%;
            max-width: 200px; /* Matched to .btn for consistency */
            padding: 0.8rem 1.5rem; /* Kept from dashboard */
            font-size: 0.95rem; /* Kept from dashboard */
            text-align: center; /* Kept from dashboard */
        }

        .close-btn {
            position: absolute;
            top: 30px; /* Updated position */
            right: 30px; /* Updated position */
            font-size: 1.8rem; /* Updated font-size */
            color: var(--dark-color);
            cursor: pointer;
            transition: var(--transition);
            /* Removed padding and line-height for a cleaner look */
        }
        .close-btn:hover {
            color: var(--primary-color);
            transform: rotate(90deg);
        }

        /* Dashboard Page Styles */
        .page-title-bar { /* Renamed from page-header-bar for clarity */
            padding: 1.5rem 0;
            background-color: var(--white);
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 2rem; /* Space below title bar */
        }
        .page-title-bar h1 {
            font-size: 1.8rem;
            color: var(--dark-color);
            margin: 0;
        }
        .page-title-bar .breadcrumb {
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        .page-title-bar .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }
        .page-title-bar .breadcrumb a:hover {
            text-decoration: underline;
        }

        .dashboard-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        .summary-card {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: var(--transition);
        }
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .summary-card .icon-container {
            font-size: 2rem;
            padding: 0.8rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .summary-card .icon-container.present { background-color: rgba(var(--success-color), 0.15); color: var(--success-color); }
        .summary-card .icon-container.absent { background-color: rgba(var(--danger-color), 0.15); color: var(--danger-color); }
        .summary-card .icon-container.late { background-color: rgba(var(--warning-color), 0.15); color: var(--warning-color); }
        .summary-card .icon-container.total { background-color: rgba(var(--info-color), 0.15); color: var(--info-color); }

        .summary-card .info h3 {
            font-size: 1.8rem;
            color: var(--dark-color);
            margin-bottom: 0.2rem;
        }
        .summary-card .info p {
            font-size: 0.9rem;
            color: var(--gray-color);
            margin: 0;
        }

        .dashboard-main-content {
            background-color: var(--white);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: var(--shadow);
        }
        .table-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
        }
        .table-controls input[type="date"],
        .table-controls select,
        .table-controls input[type="search"] {
            padding: 0.6rem 1rem;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 0.9rem;
            background-color: var(--white);
        }
         .table-controls input[type="search"] {
            min-width: 250px;
         }
        .table-controls .btn-sm { /* Smaller buttons for controls */
            padding: 0.6rem 1rem;
            font-size: 0.85rem;
        }


        .attendance-table-container {
            overflow-x: auto; /* Allows table to scroll horizontally on small screens */
        }
        table.attendance-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        table.attendance-table th, table.attendance-table td {
            padding: 0.9rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }
        table.attendance-table th {
            background-color: var(--light-color);
            font-weight: 600;
            color: var(--dark-color);
            white-space: nowrap;
        }
        table.attendance-table tbody tr:hover {
            background-color: rgba(var(--primary-color), 0.03);
        }
        .status-indicator {
            display: inline-block;
            padding: 0.3rem 0.7rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
        }
        .status-present { background-color: rgba(var(--success-color), 0.2); color: var(--success-color); border: 1px solid rgba(var(--success-color),0.3); }
        .status-absent { background-color: rgba(var(--danger-color), 0.2); color: var(--danger-color); border: 1px solid rgba(var(--danger-color),0.3); }
        .status-late { background-color: rgba(var(--warning-color), 0.2); color: var(--warning-color); border: 1px solid rgba(var(--warning-color),0.3); }
        
        .dashboard-actions {
            margin-top: 2rem;
            text-align: right;
        }


        /* Footer */
        footer { background-color: var(--dark-color); color: var(--white); padding: 5rem 0 2rem; }
        /* ... (rest of your footer styles are fine) ... */
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


        /* Responsive Styles for Dashboard */
        @media (max-width: 992px) {
            .nav-links { display: none; }
            .hamburger { display: flex; }
            .container { padding: 0 15px; } /* Slightly less padding for tablet */
            .dashboard-summary { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
            .page-title-bar h1 {font-size: 1.6rem;}
        }

        @media (max-width: 768px) {
            .table-controls { flex-direction: column; align-items: stretch; }
            .table-controls input, .table-controls select, .table-controls .btn-sm { width: 100%; }
            .dashboard-main-content { padding: 1.5rem; }
            .page-title-bar h1 {font-size: 1.5rem;}
            .summary-card { flex-direction: column; align-items: flex-start; text-align: left;}
            .summary-card .icon-container { margin-bottom: 0.5rem;}
        }
        @media (max-width: 576px) {
            .dashboard-main-content { padding: 1rem; }
             table.attendance-table th, table.attendance-table td {
                padding: 0.7rem 0.5rem;
                font-size: 0.85rem;
            }
            .status-indicator {
                font-size: 0.75rem;
                padding: 0.2rem 0.5rem;
            }
        }

    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo"> 
                    <i class="fas fa-chalkboard-teacher"></i>
                    Wave<span>Pass</span>
                </a>
                
                <ul class="nav-links">
                    <!-- Dashboard specific nav or user profile -->
                    <li><a href="dashboard.html" class="active">Dashboard</a></li> 
                    <li><a href="reports.html">Reports</a></li>
                    <li><a href="teachers.html">Employees</a></li>
                    <li><a href="settings.html">Settings</a></li>
                    <li><a href="profile.html"><span class="material-symbols-outlined">account_circle</span> Profile</a></li>
                    <li><a href="logout.php" class="btn btn-outline">Logout</a></li> 
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
            <span class="close-btn" id="closeMenu"></span>
            <ul class="mobile-links">
                 <li><a href="dashboard.html">Dashboard</a></li> 
                 <li><a href="reports.html">Reports</a></li>
                 <li><a href="teachers.html">Employees</a></li>
                 <li><a href="settings.html">Settings</a></li>
                 <li><a href="profile.html">Profile</a></li>
            </ul>
            <a href="index.html" class="btn btn-outline" style="margin-top:1rem;">Logout</a>
        </div>
    </header>

    <!-- Main Content for Dashboard -->
    <main>
        <div class="page-title-bar">
            <div class="container">
                <h1>Teacher Attendance Dashboard</h1>
                <div class="breadcrumb">
                    <a href="index.html">Home</a> / Dashboard
                </div>
            </div>
        </div>

        <div class="container" style="padding-top: 2rem; padding-bottom: 2rem;">
            <!-- Summary Cards -->
            <section class="dashboard-summary">
                <div class="summary-card">
                    <div class="icon-container present">
                        <span class="material-symbols-outlined">groups</span>
                    </div>
                    <div class="info">
                        <h3>28</h3>
                        <p>Teachers Present Today</p>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="icon-container absent">
                        <span class="material-symbols-outlined">person_off</span>
                    </div>
                    <div class="info">
                        <h3>2</h3>
                        <p>Teachers Absent Today</p>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="icon-container late">
                        <span class="material-symbols-outlined">history_toggle_off</span>
                    </div>
                    <div class="info">
                        <h3>3</h3>
                        <p>Late Arrivals Today</p>
                    </div>
                </div>
                <div class="summary-card">
                     <div class="icon-container total">
                        <span class="material-symbols-outlined">group</span>
                    </div>
                    <div class="info">
                        <h3>30</h3>
                        <p>Total Active Teachers</p>
                    </div>
                </div>
            </section>

            <!-- Main Content Area: Filters and Table -->
            <section class="dashboard-main-content">
                <div class="table-controls">
                    <input type="date" id="date-filter" name="date-filter" value="2023-10-27">
                    <select id="status-filter" name="status-filter">
                        <option value="all">All Statuses</option>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                    </select>
                    <input type="search" id="search-teachers" placeholder="Search teachers...">
                    <button class="btn btn-sm btn-outline"><span class="material-symbols-outlined" style="font-size: 1.2em;">filter_alt</span> Apply Filters</button>
                </div>

                <div class="attendance-table-container">
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Teacher Name</th>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Date</th>
                                <th>Department</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Dr. Evelyn Reed</td>
                                <td>TCH001</td>
                                <td><span class="status-indicator status-present">Present</span></td>
                                <td>08:00 AM</td>
                                <td>04:05 PM</td>
                                <td>2023-10-27</td>
                                <td>Mathematics</td>
                                <td>-</td>
                            </tr>
                            <tr>
                                <td>Mr. Samuel Green</td>
                                <td>TCH002</td>
                                <td><span class="status-indicator status-present">Present</span></td>
                                <td>07:55 AM</td>
                                <td>03:50 PM</td>
                                <td>2023-10-27</td>
                                <td>Science</td>
                                <td>-</td>
                            </tr>
                            <tr>
                                <td>Ms. Clara Oswald</td>
                                <td>TCH003</td>
                                <td><span class="status-indicator status-absent">Absent</span></td>
                                <td>-</td>
                                <td>-</td>
                                <td>2023-10-27</td>
                                <td>English</td>
                                <td>Sick leave</td>
                            </tr>
                            <tr>
                                <td>Mr. Arthur Pendelton</td>
                                <td>TCH004</td>
                                <td><span class="status-indicator status-late">Late</span></td>
                                <td>08:15 AM</td>
                                <td>04:20 PM</td>
                                <td>2023-10-27</td>
                                <td>History</td>
                                <td>Traffic delay</td>
                            </tr>
                             <tr>
                                <td>Dr. Irene Adler</td>
                                <td>TCH005</td>
                                <td><span class="status-indicator status-present">Present</span></td>
                                <td>07:58 AM</td>
                                <td>04:00 PM</td>
                                <td>2023-10-27</td>
                                <td>Music</td>
                                <td>-</td>
                            </tr>
                            <tr>
                                <td>Prof. James Moriarty</td>
                                <td>TCH006</td>
                                <td><span class="status-indicator status-present">Present</span></td>
                                <td>08:02 AM</td>
                                <td>-</td> <!-- Still present -->
                                <td>2023-10-27</td>
                                <td>Physics</td>
                                <td>Meeting after school</td>
                            </tr>
                            <!-- Add more sample rows as needed -->
                        </tbody>
                    </table>
                </div>
                <div class="dashboard-actions">
                    <button class="btn btn-outline"><span class="material-symbols-outlined" style="font-size:1.2em;">download</span> Export Report</button>
                </div>
            </section>
        </div>
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
                        <li><a href="index.html#contact"><i class="fas fa-chevron-right"></i> Contact</a></li> 
                        <li><a href="index.html#faq"><i class="fas fa-chevron-right"></i> FAQ</a></li> 
                        <li><a href="help.php"><i class="fas fa-chevron-right"></i> Help Center</a></li>
                        <li><a href="pricing.php"><i class="fas fa-chevron-right"></i> Pricing</a></li>
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
                <p> <p>&copy; <?php echo date("Y"); ?> WavePass All rights reserved. | <a href="privacy.php">Privacy Policy</a> | <a href="terms.php">Terms of Service</a></p>
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
                    // For direct page links like dashboard.html, help.php, let browser navigate
                    // Only close for on-page # anchors if that behavior is desired
                    if (link.getAttribute('href').startsWith('#')) {
                         if (mobileMenu.classList.contains('active')) {
                            hamburger.classList.remove('active');
                            mobileMenu.classList.remove('active');
                            body.style.overflow = '';
                        }
                    } else if (link.classList.contains('btn') || link.classList.contains('btn-outline')) {
                         // If it's a button leading to another page, also close
                         if (mobileMenu.classList.contains('active')) {
                            hamburger.classList.remove('active');
                            mobileMenu.classList.remove('active');
                            body.style.overflow = '';
                        }
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

        // Placeholder for dashboard interactions (filtering, etc.)
        // For example, log when a filter changes:
        const dateFilter = document.getElementById('date-filter');
        if(dateFilter) {
            dateFilter.addEventListener('change', function() {
                console.log('Date filter changed to:', this.value);
                // Here you would typically re-fetch or filter data
            });
        }
        const statusFilter = document.getElementById('status-filter');
        if(statusFilter) {
            statusFilter.addEventListener('change', function() {
                console.log('Status filter changed to:', this.value);
            });
        }

    </script>
</body>
</html>