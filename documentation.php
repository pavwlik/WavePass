<?php
// Základní PHP setup (session, případně kontrola přihlášení, pokud je dokumentace interní)
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Není nutné kontrolovat přihlášení, pokud je dokumentace veřejná
// Pokud má být jen pro přihlášené uživatele, odkomentujte a upravte:
/*
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php"); // Upravte cestu podle potřeby
    exit;
}
*/

// Includování headeru (pokud máte společný pro veřejné stránky nebo specifický)
// Předpokládáme, že máte nějaký obecný header v components/header.php nebo podobně
$headerPath = 'components/header.php'; // Nebo header-public.php apod.
// Pokud je api-documentation.php ve složce (např. /docs/), pak cesta bude '../components/header.php'

// $sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Guest';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="imgs/logo.png" type="image/x-icon"> <!-- Upravte cestu k favikoně -->
    <title>WavePass - Project Documentation</title>
    <meta name="description" content="Technical documentation, API details, design resources, and project background for the WavePass Attendance System.">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Základní styly - můžete je převzít z vašeho index.php nebo mít globální CSS */
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
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6; color: var(--dark-color); background-color: var(--light-color);
            display: flex; flex-direction: column; min-height: 100vh;
        }
        main { flex-grow: 1; padding-top:40px; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }

        /* Header styly - předpokládáme, že je máte v components/header.php */
        header {
            background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: fixed; width: 100%; top: 0; z-index: 1000;
        }
        .navbar {
            max-width: 1400px; margin: 0 auto; padding: 0 20px;
            display: flex; justify-content: space-between; align-items: center; height: 80px;
        }
        .logo { font-size: 1.8rem; font-weight: 800; color: var(--primary-color); text-decoration: none; display:flex; align-items:center; }
        .logo img { height:30px; margin-right:0.5rem; }
        .logo span { color: var(--dark-color); font-weight: 600; }
        .nav-links { display: none; list-style: none; align-items: center; gap: 0.5rem; }
        @media (min-width: 993px) { .nav-links { display: flex; } .hamburger { display: none; } }
        /* ... další styly pro navigaci, hamburger, mobilní menu ... */
         .hamburger { 
            display: flex; 
            flex-direction: column; justify-content: space-around;
            cursor: pointer; width: 30px; height: 24px; position: relative; z-index: 1001; 
            background: none; border: none; padding: 0;
        }
        .hamburger span { display: block; width: 100%; height: 3px; background-color: var(--dark-color); position: absolute; left: 0; transition: var(--transition); transform-origin: center; }
        .hamburger span:nth-child(1) { top: 0; }
        .hamburger span:nth-child(2) { top: 50%; transform: translateY(-50%); }
        .hamburger span:nth-child(3) { bottom: 0; }
        .hamburger.active span:nth-child(1) { top: 50%; transform: translateY(-50%) rotate(45deg); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { bottom: 50%; transform: translateY(50%) rotate(-45deg); }

        .mobile-menu {
            position: fixed; top: 0; left: 0; width: 100%; height: 100vh;
            background-color: var(--white); z-index: 1000; 
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            transform: translateX(-100%);
            transition: transform 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            padding: 2rem;
        }
        .mobile-menu.active { transform: translateX(0); }
        .mobile-links { list-style: none; text-align: center; width: 100%; max-width: 300px; padding:0; }
        .mobile-links li { margin-bottom: 1.5rem; }
        .mobile-links a {
            color: var(--dark-color); text-decoration: none; font-weight: 600;
            font-size: 1.2rem; display: block; padding: 0.5rem 1rem;
            transition: var(--transition); border-radius: 8px;
        }
        .mobile-links a:hover, .mobile-links a.active-nav-link { color: var(--primary-color); background-color: rgba(67, 97, 238, 0.1); }
        .close-btn { 
            position: absolute; top: 20px; right: 20px; font-size: 2rem;
            color: var(--dark-color); cursor: pointer; transition: var(--transition);
            background: none; border: none; padding: 0.5rem; line-height: 1; 
        }
        .close-btn:hover { color: var(--primary-color); transform: rotate(90deg); }


        .page-header-docs {
            background-color: #fff;
            color: var(--dark-color);
            padding: 3rem 0;
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .page-header-docs h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .page-header-docs p { font-size: 1.1rem; opacity: 0.9; max-width: 700px; margin:0 auto; }

        .doc-section {
            background-color: var(--white);
            padding: 2rem 2.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 2.5rem;
            border-left: 5px solid var(--secondary-color);
        }
        .doc-section h2 {
            font-size: 1.8rem;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid var(--light-gray);
        }
        .doc-section p, .doc-section ul {
            margin-bottom: 1rem;
            color: var(--gray-color);
            font-size: 1.05rem;
        }
        .doc-section ul { list-style: none; padding-left: 0; }
        .doc-section ul li {
            background-color: var(--light-color);
            border: 1px solid var(--light-gray);
            padding: 1rem 1.5rem;
            border-radius: 6px;
            margin-bottom: 0.8rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }
        .doc-section ul li:hover {
            border-left: 5px solid var(--primary-color);
            transform: translateX(5px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        .doc-section ul li a.doc-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .doc-section ul li a.doc-link .material-symbols-outlined {
            font-size: 1.3em;
        }
        .doc-section ul li span.file-type {
            font-size: 0.8rem;
            color: var(--white);
            background-color: var(--gray-color);
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .doc-section .btn-link {
            display: inline-block;
            background-color: var(--primary-color);
            color: var(--white);
            padding: 0.8rem 1.8rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            margin-top: 0.5rem;
        }
        .doc-section .btn-link:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        .erasmus-logo {
            max-width: 150px; /* Nebo podle potřeby */
            height: auto;
            margin-top: 1rem;
            display: block; /* Pro centrování, pokud je potřeba */
            /* margin-left: auto; margin-right: auto; */
        }

        /* Footer */
        footer { background-color: var(--dark-color); color: var(--white); padding: 5rem 0 2rem; }
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 3rem; margin-bottom: 3rem; }
        .footer-column h3 { font-size: 1.3rem; margin-bottom: 1.8rem; position: relative; padding-bottom: 0.8rem; }
        .footer-column h3::after { content: ''; position: absolute; left: 0; bottom: 0; width: 50px; height: 3px; background-color: var(--primary-color); border-radius: 3px; }
        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: 0.8rem; }
        .footer-links a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); font-size: 0.95rem; display: inline-block; padding: 0.2rem 0; }
        .footer-links a:hover { color: var(--white); transform: translateX(5px); }
        .footer-links a i { margin-right: 0.5rem; width: 20px; text-align: center; }
        .social-links { display: flex; gap: 1.2rem; margin-top: 1.5rem; }
        .social-links a { 
            display: inline-flex; align-items: center; justify-content: center; 
            width: 40px; height: 40px; background-color: rgba(255, 255, 255, 0.1); 
            color: var(--white); border-radius: 50%; font-size: 1.1rem; transition: var(--transition); 
        }
        .social-links a:hover { background-color: var(--primary-color); transform: translateY(-3px); }
        .footer-bottom { text-align: center; padding-top: 3rem; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.9rem; color: rgba(255, 255, 255, 0.6); }
        .footer-bottom a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); }
        .footer-bottom a:hover { color: var(--primary-color); }
    </style>
</head>
<body>
    <!-- Header -->
    <?php require_once "components/header-main.php" ?>

    <main>
        <div class="page-header-docs">
            <div class="container">
                <h1>Project Documentation</h1>
                <p>Welcome to the WavePass project documentation hub. Here you'll find resources related to the system's design, development, and API (if applicable).</p>
            </div>
        </div>

        <div class="container">
            <!-- Sekce API Dokumentace (pokud relevantní) -->
            <!-- 
            <section class="doc-section">
                <h2>API Documentation</h2>
                <p>Our system provides a RESTful API for integration with other services. Below you can find the detailed specification:</p>
                <ul>
                    <li>
                        <a href="path/to/your/api_spec.yaml" download class="doc-link">
                            <span class="material-symbols-outlined">api</span> OpenAPI Specification (YAML)
                        </a>
                        <span class="file-type">YAML</span>
                    </li>
                    <li>
                        <a href="path/to/your/postman_collection.json" download class="doc-link">
                            <span class="material-symbols-outlined">integration_instructions</span> Postman Collection
                        </a>
                        <span class="file-type">JSON</span>
                    </li>
                </ul>
                <p>For authentication details and rate limits, please refer to the full API guide included in the downloadable technical documentation.</p>
            </section>
            -->

            <section class="doc-section">
                <h2>Downloadable Documents</h2>
                <p>Access key documents related to the WavePass project, including technical specifications, user manuals, and presentations.</p>
                <ul>
                <!--
                    <li>
                        <a href="documentation/WavePass_Project_Presentation.pdf" download class="doc-link">
                            <span aria-hidden="true" translate="no" class="material-symbols-outlined">slideshow</span> Project Presentation
                        </a>
                        <span class="file-type">PDF</span>
                    </li>
                !-->
                    <li>
                        <a href="https://spsrakovnik.sharepoint.com/:w:/r/sites/Team01/_layouts/15/Doc2.aspx?action=edit&sourcedoc=%7Bfd44f074-8e33-44e7-81fd-0729efe739e1%7D&wdOrigin=TEAMS-WEB.teams_ns.rwc&wdExp=TEAMS-TREATMENT&wdhostclicktime=1748504544846&web=1" download class="doc-link">
                            <span aria-hidden="true" translate="no" class="material-symbols-outlined">article</span> Technical Documentation 
                        </a>
                        <span class="file-type">DOCX</span>
                    </li>
                <!--
                    <li>
                        <a href="documentation/WavePass_User_Manual_Admin.pdf" download class="doc-link">
                            <span aria-hidden="true" translate="no" class="material-symbols-outlined">person</span> User Manual - Administrator
                        </a>
                        <span class="file-type">PDF</span>
                    </li>
                    <li>
                        <a href="documentation/WavePass_User_Manual_Employee.pdf" download class="doc-link">
                            <span aria-hidden="true" translate="no" class="material-symbols-outlined">badge</span> User Manual - Employee
                        </a>
                        <span class="file-type">PDF</span>
                    </li>      
                !-->
                <!-- Přidejte další soubory podle potřeby -->
                </ul>
            </section>

            <section class="doc-section">
                <h2>Design & Prototyping (Figma)</h2>
                <p>The user interface (UI) and user experience (UX) design for WavePass, including wireframes, mockups, and interactive prototypes, were developed using Figma. You can explore the design process and final visuals via the link below.</p>
                <p>
                    <a href="https://www.figma.com/design/ojZH8Cr8afGZXZjKxWdivb/Untitled?node-id=0-1&t=YxfhIqPVooqNi3ZH-1" target="_blank" rel="noopener noreferrer" class="btn-link">
                        <i class="fab fa-figma" style="margin-right: 0.5rem;"></i> View on Figma
                    </a>
                </p>
                <p><small>Note: You might need a Figma account to view all details or interact with prototypes. The project link should be set to public view access.</small></p>
            </section>

            <section class="doc-section">
                <h2>Project Background & Erasmus+</h2>
                <p>The WavePass Attendance System was conceived and developed as a practical project with the aim of modernizing attendance tracking in educational institutions. A significant part of its development and conceptualization was made possible through the invaluable experiences and collaborative opportunities offered by the <strong>Erasmus+ programme</strong>.</p>
                <p>This international exchange provided a platform for learning, sharing ideas, and gaining diverse perspectives, which directly contributed to the features and user-centric design of WavePass. We are grateful for the support of the Erasmus+ programme in fostering innovation and cross-cultural collaboration.</p>
                <!-- Můžete zde přidat logo Erasmus+, pokud máte a smíte ho použít -->
                <!-- <img src="imgs/erasmus.png" alt="Erasmus+ Programme Logo" class="erasmus-logo">  !-->
                <!-- Ujistěte se, že máte soubor imgs/erasmus_logo.png nebo upravte cestu a alt text -->
            </section>

        </div>
    </main>

    <!-- Footer !-->
    <?php require_once "components/footer-main.php"; ?>

    <script>
        // Skript pro mobilní menu - stejný jako na index.php nebo jiných stránkách
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');
        const closeMenu = document.getElementById('closeMenu'); // Předpokládá ID 'closeMenu' na zavíracím tlačítku
        const body = document.body;
        
        if (hamburger && mobileMenu) { // Přidána kontrola existence closeMenu
            if (closeMenu) {
                closeMenu.addEventListener('click', () => {
                    hamburger.classList.remove('active');
                    mobileMenu.classList.remove('active');
                    body.style.overflow = '';
                });
            }
            
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                mobileMenu.classList.toggle('active');
                body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
            });
            
            const mobileNavLinks = document.querySelectorAll('.mobile-menu a');
            mobileNavLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    // Pokud je to jen anchor #, nechej otevřené pro případ, že by to byl toggle pro sub-menu apod.
                    // if (link.getAttribute('href') === '#' && e) { 
                    //     // e.preventDefault(); // Zabraň skoku, pokud to není potřeba
                    //     return;
                    // }
                    // Pro všechny ostatní odkazy zavři menu
                    if (mobileMenu.classList.contains('active')) {
                        hamburger.classList.remove('active');
                        mobileMenu.classList.remove('active');
                        body.style.overflow = '';
                    }
                });
            });
        }

        // Stín headeru při scrollu
        const header = document.querySelector('header');
        if (header) {
            const initialHeaderShadow = getComputedStyle(header).boxShadow;
            window.addEventListener('scroll', () => {
                if (window.scrollY > 10) {
                    header.style.boxShadow = '0 4px 10px rgba(0, 0, 0, 0.05)'; 
                } else {
                    header.style.boxShadow = initialHeaderShadow; 
                }
            });
        }
    </script>
</body>
</html>