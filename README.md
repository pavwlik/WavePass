# WavePass - Modern Attendance Tracking for Schools <img src="https://www.your-wavepass-domain.com/assets/favicon.png" alt="WavePass Logo" width="40" height="40" align="right"> <!-- REPLACE with actual logo URL -->

**Streamline your school's employee attendance management with WavePass: an intuitive, cloud-based solution designed to save time, increase accuracy, and enhance safety.**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT) <!-- Optional: Add a license badge -->
<!-- Optional: Add build status, version, etc. badges if applicable -->

---

## 👋 Welcome to WavePass!

WavePass is a comprehensive attendance system tailored for educational institutions. It leverages modern web technologies and RFID integration (conceptual) to provide a seamless experience for both administrators and employees (teachers). Our goal is to make attendance tracking effortless, accurate, and insightful.

<p align="center">
  <!-- Optional: Add a nice banner or screenshot of your application here -->
  <!-- <img src="https://www.your-wavepass-domain.com/assets/wavepass-banner.png" alt="WavePass Application Screenshot" width="700"> -->
</p>

---

## ✨ Core Features

WavePass offers a suite of powerful features designed to meet the demands of modern schools:

*   <span style="color:var(--primary-color); font-size:1.2em;"></span> **Real-time Tracking:** Monitor employee presence instantly. *(Requires RFID hardware & backend integration)*
*   <span style="color:var(--primary-color); font-size:1.2em;"></span> **Detailed Reports:** Generate comprehensive attendance reports for individuals, departments, or the entire institution.
*   <span style="color:var(--primary-color); font-size:1.2em;"></span> **Responsive Design:** Access WavePass and manage attendance from any device – desktop, tablet, or smartphone.
*   <span style="color:var(--primary-color); font-size:1.2em;"></span> **Secure Access:** Role-based permissions ensure data integrity and privacy compliance (Admin & Employee roles).
*   <span style="color:var(--primary-color); font-size:1.2em;"></span> **User Account Management:** Employees can manage their profile information, including profile photos and password.
*   <span style="color:var(--primary-color); font-size:1.2em;"></span> **Personalized Dashboards:** Dedicated dashboards for administrators and employees, providing relevant at-a-glance information.
*   <span style="color:var(--primary-color); font-size:1.2em;"></span> **RFID Card Integration (Conceptual):** Designed to work with RFID card systems for quick and easy check-in/out. *(Currently simulated based on user 'absence' flag in the provided DB schema)*
*   <span style="color:var(--primary-color); font-size:1.2em;"></span> **Calendar View:** Employees can view a monthly calendar (future development for event/leave display).
*   <span style="color:var(--primary-color); font-size:1.2em;"></span> **Messaging System (Conceptual):** Future scope for communication between administrators and employees (e.g., warning messages).
*   <span style="color:var(--primary-color); font-size:1.2em;">✏️</span> **Absence/Leave Request System (Conceptual):** Future scope for employees to request leave and for it to be managed.

---

## 🚀 Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes.

### Prerequisites

*   A web server with PHP support (e.g., Apache, Nginx with PHP-FPM)
*   MySQL or MariaDB database server
*   PHP (Version 7.4+ recommended, with PDO extension enabled)
*   Composer (for managing PHP dependencies, if any are added in the future)
*   Web browser

### Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/your-username/wavepass.git # Replace with your repo URL
    cd wavepass
    ```

2.  **Database Setup:**
    *   Create a MySQL database (e.g., `team01`).
    *   Import the database schema. You can use the provided screenshot as a guide to create the `users` table.
        ```sql
        -- Example users table structure (based on screenshot)
        CREATE TABLE `users` (
          `userID` int(11) NOT NULL AUTO_INCREMENT,
          `username` varchar(255) COLLATE latin1_swedish_ci NOT NULL,
          `password` varchar(255) COLLATE latin1_swedish_ci NOT NULL, -- Store hashed passwords!
          `firstName` varchar(64) COLLATE latin1_swedish_ci NOT NULL,
          `lastName` varchar(64) COLLATE latin1_swedish_ci NOT NULL,
          `roleID` enum('employee','admin') COLLATE latin1_swedish_ci NOT NULL DEFAULT 'employee',
          `phone` varchar(64) COLLATE latin1_swedish_ci DEFAULT NULL,
          `absence` tinyint(1) NOT NULL DEFAULT 0, -- 0 for present, 1 for absent (current simulation)
          `dateOfCreation` timestamp NOT NULL DEFAULT current_timestamp(),
          `email` varchar(64) COLLATE latin1_swedish_ci NOT NULL,
          `profile_photo` varchar(255) COLLATE latin1_swedish_ci DEFAULT NULL,
          `RFID` int(11) DEFAULT NULL, -- Consider VARCHAR if RFID IDs can be alphanumeric
          PRIMARY KEY (`userID`),
          UNIQUE KEY `username` (`username`),
          UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
        ```
    *   **Important:** For a production-ready system, create additional tables like `attendance_logs`, `absences` (leave requests), `user_messages` as discussed in previous interactions.

3.  **Configure Database Connection:**
    *   Copy or rename `db.example.php` to `db.php`.
    *   Edit `db.php` with your actual database credentials:
        ```php
        // db.php
        $host = 'localhost';
        $db   = 'team01'; // Your database name
        $user = 'your_db_user'; // Your database username
        $pass = 'your_db_password'; // Your database password
        $charset = 'utf8mb4';

        // ... (rest of PDO connection setup) ...
        ```

4.  **Directory Permissions:**
    *   Ensure the `profile_photos/` directory (in the same location as `my-account.php`) exists and is writable by your web server.
        ```bash
        mkdir profile_photos
        sudo chown www-data:www-data profile_photos # Replace www-data with your server's user/group
        sudo chmod 775 profile_photos
        ```

5.  **Web Server Configuration:**
    *   Point your web server's document root to the project directory (or a subdirectory if needed).
    *   Ensure `mod_rewrite` (for Apache) or equivalent is enabled if you plan to use clean URLs later.

6.  **Access the application:**
    *   Open your web browser and navigate to the project URL (e.g., `http://localhost/wavepass/` or `http://your-virtual-host-name/`).

---

## 🛠️ How It Works (Simplified Steps)

1.  <span style="color:var(--primary-color); font-size:1.1em;"></span> **User Login:** Users log in with their credentials. The system differentiates between 'employee' and 'admin' roles.
2.  <span style="color:var(--primary-color); font-size:1.1em;"></span> **Dashboard Access:**
    *   **Employees:** See a personalized dashboard with their current status, activity snapshot, and links to manage their profile, view attendance logs, and request absences.
    *   **Administrators (Conceptual):** Would have a dashboard to oversee all users, manage attendance records, generate reports, and configure system settings.
3.  <span style="color:var(--primary-color); font-size:1.1em;"></span> **Attendance Marking (Conceptual for RFID):**
    *   Employees would use their RFID cards at designated readers to check in and out.
    *   This data would populate an `attendance_logs` table.
    *   *(Current simulation uses a manual `absence` flag in the `users` table.)*
4.  <span style="color:var(--primary-color); font-size:1.1em;"></span> **Profile Management:** Users can update their personal details and profile picture via the "My Account" page.
5.  <span style="color:var(--primary-color); font-size:1.1em;"></span> **Reporting (Conceptual):** Admins would be able to generate various attendance reports.

---

## 🎨 Design & Styling

The user interface is designed to be clean, modern, and intuitive, drawing inspiration from contemporary web design trends. Key styling aspects include:

*   **Color Palette:** A primary color (`#4361ee`) for branding and calls to action, with complementary accent colors.
*   **Typography:** Utilizes the 'Inter' font family for readability and a modern feel.
*   **Layout:** Employs responsive design principles using CSS Flexbox and Grid for adaptability across devices.
*   **Components:** Consistent styling for buttons, cards, forms, and navigation elements.
*   **Visual Feedback:** Hover effects and active states provide users with clear interaction cues.

The styling is primarily managed within `<style>` tags in each PHP file for this version but can be easily modularized into external CSS files for larger projects.

---

## 💻 Technologies Used

*   **Frontend:** HTML5, CSS3 (with CSS Custom Properties), JavaScript (for dynamic interactions like mobile menu and client-side previews).
*   **Backend:** PHP (with PDO for database interaction).
*   **Database:** MySQL/MariaDB.
*   **Fonts:** Google Fonts ('Inter'), Font Awesome (for icons).
*   **Web Server:** Apache/Nginx (or any server supporting PHP).

---

## 🚧 Future Enhancements (Roadmap Ideas)

*   <span style="color:var(--secondary-color); font-size:1em;">✅</span> **Full `attendance_logs` Implementation:** Track detailed check-in/out times, event types (late, on-time, early-out), and integrate with actual RFID reader data.
*   <span style="color:var(--secondary-color); font-size:1em;">✅</span> **Advanced Reporting Module:** For administrators to generate customizable reports (daily, weekly, monthly, by department, individual).
*   <span style="color:var(--secondary-color); font-size:1em;">✅</span> **Leave Management System:** Allow employees to request various types of leave, and administrators to approve/reject them.
*   <span style="color:var(--secondary-color); font-size:1em;">✅</span> **Notifications/Alerts:** Implement a system for administrators to send messages/warnings and for employees to receive alerts (e.g., for upcoming leave).
*   <span style="color:var(--secondary-color); font-size:1em;">✅</span> **Administrator Panel:** A dedicated interface for managing users, roles, departments, school settings, and attendance policies.
*   <span style="color:var(--secondary-color); font-size:1em;">✅</span> **API Endpoints:** For potential integration with other school management systems or mobile applications.
*   <span style="color:var(--secondary-color); font-size:1em;">✅</span> **Localization/Internationalization (i18n):** Support for multiple languages.

---

## 🤝 Contributing

Contributions are welcome! If you'd like to contribute to WavePass, please follow these steps:

1.  Fork the Project.
2.  Create your Feature Branch (`git checkout -b feature/AmazingFeature`).
3.  Commit your Changes (`git commit -m 'Add some AmazingFeature'`).
4.  Push to the Branch (`git push origin feature/AmazingFeature`).
5.  Open a Pull Request.

Please make sure to update tests as appropriate and follow the existing coding style.

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details (if you add one).

---

## 🙏 Acknowledgements (Optional)

*   Any third-party libraries or assets used.
*   Inspiration or guidance received.

---

**We hope WavePass helps simplify your school's attendance management!**
For questions or support, please open an issue on this repository.
