<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

require_once 'db.php'; 

$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'User';
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
$sessionRole = isset($_SESSION["role"]) ? $_SESSION["role"] : 'employee';

$currentPage = basename($_SERVER['PHP_SELF']);

// --- DATA FETCHING for calendar events (absences) ---
$calendarEvents = [];
$dbErrorMessage = null;

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $sqlFetchEvents = "SELECT 
                                a.absenceID, 
                                a.absence_start_datetime, 
                                a.absence_end_datetime, 
                                a.absence_type, 
                                a.status,
                                u.firstName,
                                u.lastName
                           FROM absence a
                           JOIN users u ON a.userID = u.userID
                           WHERE a.status IN ('approved', 'pending_approval') 
                           ORDER BY a.absence_start_datetime ASC";
        
        $stmtFetchEvents = $pdo->prepare($sqlFetchEvents);
        $stmtFetchEvents->execute(); 
        $fetchedAbsences = $stmtFetchEvents->fetchAll(PDO::FETCH_ASSOC);

        foreach ($fetchedAbsences as $absence) {
            // Titulek pro zobrazení v buňce kalendáře
            $cellDisplayTitle = htmlspecialchars(ucwords(str_replace('_', ' ', $absence['absence_type']))) . 
                                " (" . htmlspecialchars($absence['firstName']) . ")"; // Zkráceno pro buňku

            // Plný titul pro modal
            $fullTitleForModal = htmlspecialchars(ucwords(str_replace('_', ' ', $absence['absence_type'])));


            if ($absence['status'] === 'pending_approval') {
                $cellDisplayTitle .= " (P)"; // Krátký indikátor pro buňku
            }

            $calendarEvents[] = [
                'id' => $absence['absenceID'],
                'cellDisplayTitle' => $cellDisplayTitle, // Pro buňku kalendáře
                'modalTitle' => $fullTitleForModal, // Pro modal
                'firstName' => htmlspecialchars($absence['firstName']),
                'lastName' => htmlspecialchars($absence['lastName']),
                'absence_type' => htmlspecialchars($absence['absence_type']), // Surový typ pro logiku
                'start' => $absence['absence_start_datetime'],
                'end' => $absence['absence_end_datetime'],
                'status' => $absence['status'],
                'color' => ($absence['status'] === 'approved') ? 'var(--approved-color)' : 'var(--pending-color)'
            ];
        }

    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error fetching calendar events: " . $e->getMessage();
    }
} else {
    $dbErrorMessage = "Database connection not available.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="imgs/logo.png" type="image/x-icon"> 
    <title>Absence Calendar - WavePass</title>
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
            --pending-color: var(--warning-color); 
            --approved-color: var(--success-color);
            --rejected-color: var(--danger-color);
            --cancelled-color: var(--gray-color);
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh;}
        main { flex-grow: 1; padding-top: 80px;}
        .container, .page-header .container { 
            max-width: 1440px; margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px; 
        }
        
        .page-header { padding: 1.8rem 0; margin-bottom: 1.5rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; margin: 0; } .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        
        .db-error-message { padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem; background-color: rgba(244,67,54,0.1); color: var(--danger-color); border-left-color: var(--danger-color); }

        .calendar-container {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        .calendar-header h2 {
            font-size: 1.5rem;
            color: var(--dark-color);
            margin: 0;
        }
        .calendar-nav button { /* Tento styl zůstává pro navigační tlačítka kalendáře */
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 0.6rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }
        .calendar-nav button:hover {
            background-color: var(--primary-dark);
        }

        /* Styly pro obecné tlačítko "btn-outline" (pokud by bylo použito jinde) */
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            box-shadow: none;
            padding: 0.6rem 1rem; /* Příklad paddingu, upravte dle potřeby */
            border-radius: 5px;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex; /* Pro zarovnání ikony a textu, pokud by tam byly */
            align-items: center;
            justify-content: center;
        }
        .btn-outline:hover {
            background-color: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
            transform: translateY(-2px); /* Volitelný efekt */
            box-shadow: none; /* Nebo specifický stín pro hover */
        }


        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px; 
            border: 1px solid var(--light-gray);
        }
        .calendar-day-header, .calendar-day {
            padding: 0.8rem 0.5rem; 
            text-align: center;
            font-size: 0.85rem;
            min-height: 100px; 
            position: relative; 
            overflow-y: auto; /* Ponecháno pro případ mnoha událostí v buňce */
            max-height: 150px; 
        }
        .calendar-day-header {
            background-color: var(--light-color);
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 1px solid var(--light-gray);
            min-height: auto;
            padding: 0.6rem 0.5rem;
        }
        .calendar-day {
            border: 1px solid var(--light-gray);
            background-color: var(--white);
            transition: background-color 0.2s;
        }
        .calendar-day.other-month {
            background-color: #f9f9f9;
            color: #aaa;
        }
        .calendar-day.today {
            background-color: rgba(var(--primary-color-rgb), 0.1);
            border: 1px solid var(--primary-color);
            font-weight: bold;
        }
        .calendar-day .day-number {
            font-weight: 500;
            margin-bottom: 0.3rem;
            display: block;
            font-size: 0.9rem;
        }
        .calendar-event {
            background-color: var(--pending-color); 
            color: var(--white);
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-bottom: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            display: block; 
        }
        .calendar-event.status-approved {
            background-color: var(--approved-color);
        }
        
        /* ODSTRANĚNO: .event-tooltip a .calendar-event:hover .event-tooltip */

        /* === STYLY PRO MODÁLNÍ OKNO === */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1050; 
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto; 
            background-color: rgba(0,0,0,0.5); 
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: var(--white);
            margin: auto; 
            padding: 25px 30px;
            border: 1px solid var(--light-gray);
            width: 90%;
            max-width: 500px; 
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
            animation: fadeInModal 0.3s ease-out;
        }

        @keyframes fadeInModal {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-close-btn {
            color: var(--gray-color);
            float: right;
            font-size: 28px;
            font-weight: bold;
            line-height: 1;
            position: absolute;
            top: 15px;
            right: 20px;
        }

        .modal-close-btn:hover,
        .modal-close-btn:focus {
            color: var(--dark-color);
            text-decoration: none;
            cursor: pointer;
        }
        
        .modal-content h3 {
            margin-top: 0;
            margin-bottom: 1rem;
            color: var(--primary-color);
            font-size: 1.5rem;
        }
        .modal-content p {
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            color: var(--dark-color);
        }
        .modal-content p strong {
            color: var(--gray-color);
            min-width: 70px;
            display: inline-block;
        }
        .modal-status-label {
            padding: 3px 8px;
            border-radius: 4px;
            color: white;
            font-size: 0.9em;
            font-weight: 500;
        }


        @media (max-width: 768px) {
            .calendar-day-header, .calendar-day {
                font-size: 0.75rem;
                min-height: 80px;
                 padding: 0.5rem 0.2rem;
            }
            .calendar-event {
                font-size: 0.7rem;
                padding: 2px 4px;
            }
            .calendar-header h2 { font-size: 1.2rem; }
            .calendar-nav button { padding: 0.5rem 0.8rem; font-size: 0.8rem; }
        }
        @media (max-width: 480px) {
            .calendar-day-header { display: none; } 
            .calendar-grid { grid-template-columns: 1fr; } 
            .calendar-day { 
                text-align: left; 
                padding-left: 0.5rem;
                border-bottom: 1px solid var(--light-gray);
            }
            .calendar-day .day-number::before { 
                content: attr(data-weekday) " "; 
                font-weight: normal;
                color: var(--gray-color);
            }
            .modal-content { width: 95%; padding: 20px; }
            .modal-content h3 { font-size: 1.3rem; }
        }

        footer { background-color: var(--dark-color); color: var(--white); padding: 3rem 0 2rem; margin-top:auto; }
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 2rem; margin-bottom: 2rem; }
        .footer-column h3 { font-size: 1.2rem; margin-bottom: 1.2rem; position: relative; padding-bottom: 0.6rem; }
        .footer-column h3::after { content: ''; position: absolute; left: 0; bottom: 0; width: 40px; height: 2px; background-color: var(--primary-color); border-radius: 3px; }
        .footer-links { list-style: none; padding:0; } .footer-links li { margin-bottom: 0.6rem; }
        .footer-links a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); font-size: 0.9rem; display: inline-block; padding: 0.1rem 0; }
        .footer-links a:hover { color: var(--white); transform: translateX(4px); }
        .footer-links a i { margin-right: 0.4rem; width: 18px; text-align: center; } 
        .social-links { display: flex; gap: 1rem; margin-top: 1rem; padding:0; }
        .social-links a { display: inline-flex; align-items: center; justify-content: center; width: 35px; height: 35px; background-color: rgba(255, 255, 255, 0.1); color: var(--white); border-radius: 50%; font-size: 1rem; transition: var(--transition); }
        .social-links a:hover { background-color: var(--primary-color); transform: translateY(-2px); }
        .footer-bottom { text-align: center; padding-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.85rem; color: rgba(255, 255, 255, 0.6); }
        .footer-bottom a { color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); }
        .footer-bottom a:hover { color: var(--primary-color); }

    </style>
</head>
<body>
  <!-- Header -->
  <?php require "components/header-admin.php"; ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>Absence Calendar</h1>
                <p class="sub-heading">View scheduled absences and pending requests.</p>
            </div>
        </div>

        <div class="container">
            <?php if (isset($dbErrorMessage) && $dbErrorMessage): ?>
                <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo $dbErrorMessage; ?></div>
            <?php endif; ?>

            <div class="calendar-container">
                <div class="calendar-header">
                    <h2 id="currentMonthYear">Month Year</h2>
                    <div class="calendar-nav">
                        <button id="prevMonthBtn"><span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.2em;">arrow_back_ios</span> Prev</button>
                        <button id="nextMonthBtn">Next <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.2em;">arrow_forward_ios</span></button>
                    </div>
                </div>
                <div class="calendar-grid" id="calendarGrid">
                    <!-- Calendar days will be generated by JavaScript -->
                </div>
            </div>
        </div>
    </main>

    <!-- Modální okno pro detaily události -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <span class="modal-close-btn">×</span>
            <h3 id="modalTitle">Event Title</h3>
            <p><strong>User:</strong> <span id="modalUserName"></span></p>
            <p><strong>Start:</strong> <span id="modalStartTime"></span></p>
            <p><strong>End:</strong> <span id="modalEndTime"></span></p>
            <p><strong>Status:</strong> <span id="modalStatus" class="modal-status-label"></span></p>
        </div>
    </div>

    <?php 
    if (file_exists("components/footer-user.php")) { 
        require_once "components/footer-user.php";
    } else {
        echo "<!-- Footer component not found -->";
    }
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarGrid = document.getElementById('calendarGrid');
            const currentMonthYearEl = document.getElementById('currentMonthYear');
            const prevMonthBtn = document.getElementById('prevMonthBtn');
            const nextMonthBtn = document.getElementById('nextMonthBtn');

            // Modal elementy
            const eventModal = document.getElementById('eventModal');
            const modalCloseBtn = eventModal.querySelector('.modal-close-btn');
            const modalTitleEl = document.getElementById('modalTitle');
            const modalUserNameEl = document.getElementById('modalUserName');
            const modalStartTimeEl = document.getElementById('modalStartTime');
            const modalEndTimeEl = document.getElementById('modalEndTime');
            const modalStatusEl = document.getElementById('modalStatus');

            let currentDate = new Date();
            const calendarEvents = <?php echo json_encode($calendarEvents); ?>;

            function renderCalendar(date) {
                calendarGrid.innerHTML = ''; 
                const year = date.getFullYear();
                const month = date.getMonth(); 

                currentMonthYearEl.textContent = date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

                const firstDayOfMonth = new Date(year, month, 1);
                const lastDayOfMonth = new Date(year, month + 1, 0);
                const daysInMonth = lastDayOfMonth.getDate();
                const firstDayOfWeek = firstDayOfMonth.getDay(); 

                const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                weekdays.forEach(day => {
                    const dayHeaderEl = document.createElement('div');
                    dayHeaderEl.classList.add('calendar-day-header');
                    dayHeaderEl.textContent = day;
                    calendarGrid.appendChild(dayHeaderEl);
                });

                for (let i = 0; i < firstDayOfWeek; i++) {
                    const emptyCell = document.createElement('div');
                    emptyCell.classList.add('calendar-day', 'other-month');
                    calendarGrid.appendChild(emptyCell);
                }

                for (let day = 1; day <= daysInMonth; day++) {
                    const dayCell = document.createElement('div');
                    dayCell.classList.add('calendar-day');
                    dayCell.dataset.weekday = weekdays[new Date(year, month, day).getDay()]; 

                    const dayNumberEl = document.createElement('span');
                    dayNumberEl.classList.add('day-number');
                    dayNumberEl.textContent = day;
                    dayCell.appendChild(dayNumberEl);

                    const today = new Date();
                    if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
                        dayCell.classList.add('today');
                    }
                    
                    const currentDateStringStart = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')} 00:00:00`;
                    const currentDateStringEnd = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')} 23:59:59`;

                    calendarEvents.forEach(event => {
                        const eventStart = new Date(event.start);
                        const eventEnd = new Date(event.end);
                        const currentDayStart = new Date(currentDateStringStart);
                        const currentDayEnd = new Date(currentDateStringEnd);

                        if (eventStart <= currentDayEnd && eventEnd >= currentDayStart) {
                            const eventEl = document.createElement('div');
                            eventEl.classList.add('calendar-event');
                            eventEl.classList.add(`status-${event.status.toLowerCase()}`);
                            eventEl.style.backgroundColor = event.color; 
                            
                            let displayTitleForCell = event.cellDisplayTitle;
                            if (eventStart.toDateString() === currentDayStart.toDateString()) { 
                                displayTitleForCell = `${eventStart.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} ${event.cellDisplayTitle.split(' (')[0]}`; // Jen typ a čas
                            } else {
                                displayTitleForCell = event.cellDisplayTitle.split(' (')[0]; // Jen typ pro vícedenní
                            }
                            // Oříznutí, pokud je stále příliš dlouhé pro buňku
                            eventEl.textContent = displayTitleForCell.length > 15 ? displayTitleForCell.substring(0, 12) + '...' : displayTitleForCell;


                            // Odebráno generování starého tooltipu
                            // eventEl.appendChild(tooltip);
                            
                            eventEl.addEventListener('click', () => {
                                openEventModal(event);
                            });
                            
                            dayCell.appendChild(eventEl);
                        }
                    });
                    calendarGrid.appendChild(dayCell);
                }
            }

            function openEventModal(eventData) {
                modalTitleEl.textContent = eventData.modalTitle;
                modalUserNameEl.textContent = `${eventData.firstName} ${eventData.lastName}`;
                modalStartTimeEl.textContent = new Date(eventData.start).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
                modalEndTimeEl.textContent = new Date(eventData.end).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
                
                const statusText = eventData.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                modalStatusEl.textContent = statusText;
                modalStatusEl.style.backgroundColor = eventData.color; // Použijeme barvu události pro label statusu

                eventModal.style.display = 'flex';
            }

            // Zavírání modalu
            modalCloseBtn.addEventListener('click', () => {
                eventModal.style.display = 'none';
            });

            window.addEventListener('click', (e) => {
                if (e.target == eventModal) { 
                    eventModal.style.display = 'none';
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && eventModal.style.display === 'flex') {
                    eventModal.style.display = 'none';
                }
            });


            prevMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() - 1);
                renderCalendar(currentDate);
            });

            nextMonthBtn.addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() + 1);
                renderCalendar(currentDate);
            });

            renderCalendar(currentDate); 

            // Hamburger menu functionality (stejné jako dříve)
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
                    if (mobileMenu) mobileMenu.setAttribute('aria-hidden', !mobileMenu.classList.contains('active'));
                });
                if (closeMenuBtn) {
                    closeMenuBtn.addEventListener('click', () => {
                        if (mobileMenu) mobileMenu.classList.remove('active');
                        if (hamburger) hamburger.classList.remove('active'); 
                        body.style.overflow = '';
                        if (hamburger) hamburger.setAttribute('aria-expanded', 'false');
                        if (mobileMenu) mobileMenu.setAttribute('aria-hidden', 'true');
                        if (hamburger) hamburger.focus(); 
                    });
                }
                if (mobileMenu) {
                    mobileMenu.querySelectorAll('a').forEach(link => {
                        link.addEventListener('click', () => {
                            if (mobileMenu.classList.contains('active')) {
                                mobileMenu.classList.remove('active');
                                if (hamburger) hamburger.classList.remove('active');
                                body.style.overflow = '';
                                if (hamburger) hamburger.setAttribute('aria-expanded', 'false');
                                mobileMenu.setAttribute('aria-hidden', 'true');
                            }
                        });
                    });
                }
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && mobileMenu && mobileMenu.classList.contains('active')) {
                        if(closeMenuBtn) closeMenuBtn.click(); else if (hamburger) hamburger.click();
                    }
                });
            }
        });
    </script>
</body>
</html>