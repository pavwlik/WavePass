<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WavePass - Get Started</title>
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
            --success-color: #4cc9f0;
            --warning-color: #f8961e;
            --danger-color: #f72585;
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
            background-color: var(--light-color);
            overflow-x: hidden;
            scroll-behavior: smooth;
            display: flex; 
            flex-direction: column;
            min-height: 100vh;
        }
        
        main {
            flex-grow: 1; 
            padding-top: 40px; /* Height of the fixed header */
        }

        h1, h2, h3, h4 {
            font-weight: 700;
            line-height: 1.2;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Štýly pre header by mali byť v header.php alebo v linkovanom CSS */
        /* Tu ponechávam len štýly špecifické pre get-started.php */

        .page-header-bar {
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--light-gray);
            background-color: var(--white); 
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            opacity: 0.8;
            transition: Var(--transition);
        }
        .back-link .material-symbols-outlined {
            font-size: 1.3em;
            opacity: 1;
        }
        .back-link:hover {
            opacity: 1;
        }

        .intro-section {
            padding: 3rem 0;
            background-color: #f0f4ff; 
            text-align: center;
        }
        .intro-section h1 {
            font-size: 2.2rem;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        .intro-section p {
            font-size: 1.1rem;
            color: var(--gray-color);
            max-width: 750px;
            margin: 0 auto 1.5rem auto;
        }
        .intro-benefits {
            list-style: none;
            padding: 0;
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        .intro-benefits li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            color: var(--dark-color);
            background-color: var(--white);
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .intro-benefits .material-symbols-outlined {
            color: var(--primary-color);
        }

        .request-form-section {
            padding: 3rem 0 4rem;
        }
        .request-form-container {
            background-color: var(--white);
            padding: 2.5rem 3rem;
            border-radius: 12px;
            box-shadow: var(--shadow);
            max-width: 800px; 
            margin: 0 auto; 
        }
        .request-form-container h2 {
            font-size: 1.8rem;
            margin-bottom: 0.8rem;
            color: var(--dark-color);
            text-align: center;
        }
        .request-form-container .form-subtitle {
            font-size: 1rem;
            color: var(--gray-color);
            margin-bottom: 2.5rem;
            text-align: center;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr; 
            gap: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem; 
            text-align: left;
        }
        .form-group.full-width {
            grid-column: 1 / -1; 
        }
        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.9rem;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--dark-color);
            transition: border-color var(--transition), box-shadow var(--transition);
            background-color: var(--light-color); 
        }
        .form-group select {
            appearance: none; 
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%236c757d'%3E%3Cpath d='M7 10l5 5 5-5H7z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1.2rem center;
            background-size: 1em;
            padding-right: 3rem; 
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { 
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
            background-color: var(--white);
        }
        .form-group textarea { 
             min-height: 120px; resize: vertical;
        }
        
        .request-form-container .btn { 
            width: 100%;
            padding: 0.9rem 2rem;
            margin-top: 1.5rem; 
        }
        
        /* Footer Styles - mali by byť v footer.php alebo main-styles.css */
        footer { background-color: var(--dark-color); color: var(--white); padding: 5rem 0 2rem; }
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 3rem; margin-bottom: 3rem; }
        .footer-column h3 { font-size: 1.3rem; margin-bottom: 1.8rem; position: relative; padding-bottom: 0.8rem; }
        .footer-column h3::after { content: ''; position: absolute; left: 0; bottom: 0; width: 50px; height: 3px; background-color: var(--primary-color); border-radius: 3px; }
        .footer-links { list-style: none; padding:0; /*Reset*/ }
        .footer-links li { margin-bottom: 0.8rem; }
        .footer-links a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); font-size: 0.95rem; display: inline-block; padding: 0.2rem 0; }
        .footer-links a:hover { color: var(--white); transform: translateX(5px); }
        .footer-links a i { margin-right: 0.5rem; width: 20px; text-align: center; }
        .social-links { display: flex; gap: 1.2rem; margin-top: 1.5rem; padding:0; /*Reset*/ }
        .social-links a { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background-color: rgba(255, 255, 255, 0.1); color: var(--white); border-radius: 50%; font-size: 1.1rem; transition: var(--transition); }
        .social-links a:hover { background-color: var(--primary-color); transform: translateY(-3px); }
        .footer-bottom { text-align: center; padding-top: 3rem; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.9rem; color: rgba(255, 255, 255, 0.6); }
        .footer-bottom a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); }
        .footer-bottom a:hover { color: var(--primary-color); }

        @media (max-width: 992px) {
            /* Štýly pre header by mali byť v header.php alebo linkovanom CSS */
            .request-form-container {
                padding: 2rem 1.5rem;
            }
            .intro-section h1 { font-size: 2rem; }
        }

        @media (max-width: 768px) {
            .request-form-container h2 { font-size: 1.6rem; }
            .request-form-container .form-subtitle { font-size: 0.95rem; }
            .form-grid {
                grid-template-columns: 1fr; 
            }
            .form-group.full-width { 
                grid-column: auto; 
            }
            .intro-section h1 { font-size: 1.8rem; }
            .intro-section p { font-size: 1rem; }
            .intro-benefits { flex-direction: column; align-items: center;}
        }
    </style>
</head>
<body>
    <?php
        // Definovanie $asset_prefix pre túto stránku (get-started.php je v roote)
        $asset_prefix = ""; // Keďže sme v roote
        // Nasledujúci riadok je dôležitý, aby header.php vedel, ako tvoriť cesty
        // Ak header.php už má svoju logiku pre $asset_prefix, toto nemusí byť nutné,
        // ale je dobré zabezpečiť, aby header mal prístup k správnemu prefixu.
        // V tomto prípade, keďže get-started.php je v roote, header.php bude tiež generovať root cesty.
        require_once "components/header-main.php";
    ?>

    <main>
        <section class="intro-section">
            <div class="container">
                <h1>Ready to Modernize Your School's Attendance?</h1>
                <p>WavePass offers a simple, efficient, and reliable way to manage teacher attendance. By providing us with a few details about your institution, we can tailor a solution that perfectly fits your needs. Let's get started on transforming your attendance tracking process!</p>
                <ul class="intro-benefits">
                    <li><span aria-hidden="true" translate="no" class="material-symbols-outlined">timer</span> Save Administrative Time</li>
                    <li><span aria-hidden="true" translate="no" class="material-symbols-outlined">paid</span> Reduce Costs</li>
                    <li><span aria-hidden="true" translate="no" class="material-symbols-outlined">analytics</span> Improve Accuracy & Reporting</li>
                    <li><span aria-hidden="true" translate="no" class="material-symbols-outlined">cloud_upload</span> Cloud-Based & Accessible</li>
                </ul>
            </div>
        </section>

        <section class="request-form-section" id="request-form">
            <div class="container">
                <div class="request-form-container">
                    <h2>Request Our Service</h2>
                    <p class="form-subtitle">Please fill out the form below, and our team will contact you shortly.</p>
                    
                    <form action="process_service_request.php" method="POST">
                        <h3 style="margin-top: 0; margin-bottom: 1.5rem; font-size: 1.3rem; color: var(--dark-color); border-bottom: 1px solid var(--light-gray); padding-bottom: 0.5rem;">
                            <span aria-hidden="true" translate="no" class="material-symbols-outlined" style="vertical-align: bottom; margin-right: 0.3em; font-size: 1.2em;">school</span>School Information
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="school_name">School Name <span style="color:var(--danger-color)">*</span></label>
                                <input type="text" id="school_name" name="school_name" required>
                            </div>
                            <div class="form-group">
                                <label for="school_ico">ICO / Registration ID <span style="color:var(--danger-color)">*</span></label>
                                <input type="text" id="school_ico" name="school_ico" required>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="school_type">Type of School <span style="color:var(--danger-color)">*</span></label>
                                <select id="school_type" name="school_type" required>
                                    <option value="" disabled selected>Select type...</option>
                                    <option value="primary">Primary School</option>
                                    <option value="secondary">Secondary School / High School</option>
                                    <option value="vocational">Vocational School</option>
                                    <option value="college">College</option>
                                    <option value="university">University</option>
                                    <option value="language_school">Language School</option>
                                    <option value="other">Other Educational Institution</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="teacher_count">Approx. Number of Employees <span style="color:var(--danger-color)">*</span></label>
                                <input type="number" id="teacher_count" name="teacher_count" min="1" placeholder="e.g., 50" required>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label for="school_address_street">
                                <span aria-hidden="true" translate="no" class="material-symbols-outlined" style="font-size: 1.1em; vertical-align: text-bottom; margin-right: 0.2em;">location_on</span>Street Address <span style="color:var(--danger-color)">*</span>
                            </label>
                            <input type="text" id="school_address_street" name="school_address_street" required>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="school_address_city">City <span style="color:var(--danger-color)">*</span></label>
                                <input type="text" id="school_address_city" name="school_address_city" required>
                            </div>
                            <div class="form-group">
                                <label for="school_address_postal">Postal Code <span style="color:var(--danger-color)">*</span></label>
                                <input type="text" id="school_address_postal" name="school_address_postal" required>
                            </div>
                        </div>
                         <div class="form-group full-width">
                            <label for="school_address_country">Country <span style="color:var(--danger-color)">*</span></label>
                            <input type="text" id="school_address_country" name="school_address_country" required>
                        </div>

                        <h3 style="margin-top: 2.5rem; margin-bottom: 1.5rem; font-size: 1.3rem; color: var(--dark-color); border-bottom: 1px solid var(--light-gray); padding-bottom: 0.5rem;">
                             <span aria-hidden="true" translate="no" class="material-symbols-outlined" style="vertical-align: bottom; margin-right: 0.3em; font-size: 1.2em;">badge</span>Contact Person
                        </h3>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="contact_person_name">Full Name <span style="color:var(--danger-color)">*</span></label>
                                <input type="text" id="contact_person_name" name="contact_person_name" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_person_email">
                                     <span aria-hidden="true" translate="no" class="material-symbols-outlined" style="font-size: 1.1em; vertical-align: text-bottom; margin-right: 0.2em;">contact_mail</span>Email Address <span style="color:var(--danger-color)">*</span>
                                </label>
                                <input type="email" id="contact_person_email" name="contact_person_email" required>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label for="contact_person_phone">
                                <span aria-hidden="true" translate="no" class="material-symbols-outlined" style="font-size: 1.1em; vertical-align: text-bottom; margin-right: 0.2em;">contact_phone</span>Phone Number (Optional)
                            </label>
                            <input type="tel" id="contact_person_phone" name="contact_person_phone">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="message">Any Specific Requirements or Message (Optional)</label>
                            <textarea id="message" name="message" rows="4"></textarea>
                        </div>
                        
                        <button type="submit" class="btn">
                            <span aria-hidden="true" translate="no" class="material-symbols-outlined">send</span> Send Request
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <?php  require_once "components/footer-user.php"; ?>

    <script>
        // Smooth scrolling for on-page anchors
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const hrefAttribute = this.getAttribute('href');
                // Zabezpečenie, že sa jedná o platnú kotvu a nie len "#"
                if (hrefAttribute.length > 1 && hrefAttribute.startsWith('#')) {
                    const targetId = hrefAttribute.substring(1);
                    const targetElement = document.getElementById(targetId);
                    
                    if (targetElement) {
                        e.preventDefault();
                        const headerHeight = document.querySelector('header') ? document.querySelector('header').offsetHeight : 80; // Fallback
                        const elementPosition = targetElement.getBoundingClientRect().top;
                        const offsetPosition = elementPosition + window.pageYOffset - headerHeight;
                        
                        window.scrollTo({
                            top: offsetPosition,
                            behavior: 'smooth'
                        });
                    }
                }
            });
        });
        
        // Add shadow to header on scroll (prevzaté z components/header.php, ak tam nie je)
        const header = document.querySelector('header');
        if (header) {
            const initialHeaderShadow = getComputedStyle(header).boxShadow;
            window.addEventListener('scroll', () => {
                if (window.scrollY > 10) {
                    header.style.boxShadow = '0 4px 10px rgba(0, 0, 0, 0.07)'; 
                } else {
                    header.style.boxShadow = initialHeaderShadow; 
                }
            });
        }

        // Hamburger menu functionality (prevzaté z components/header.php, ak tam nie je)
        const hamburger = document.getElementById('hamburger');
        const mobileMenu = document.getElementById('mobileMenu');
        const closeMenuBtn = document.getElementById('closeMenu');
        const body = document.body;

        if (hamburger && mobileMenu && closeMenuBtn) {
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                mobileMenu.classList.toggle('active');
                body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
            });

            closeMenuBtn.addEventListener('click', () => {
                hamburger.classList.remove('active');
                mobileMenu.classList.remove('active');
                body.style.overflow = '';
            });

            const mobileLinks = mobileMenu.querySelectorAll('.mobile-links a');
            mobileLinks.forEach(link => {
                link.addEventListener('click', () => {
                    // Ak odkaz smeruje na kotvu na tej istej stránke alebo na inú stránku
                    if (mobileMenu.classList.contains('active')) {
                        hamburger.classList.remove('active');
                        mobileMenu.classList.remove('active');
                        body.style.overflow = '';
                    }
                });
            });
        }

    </script>
</body>
</html>