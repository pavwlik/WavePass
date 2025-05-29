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

$dbErrorMessage = null;
$attendanceLogs = [];

// --- Filtry ---
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // Default: První den aktuálního měsíce
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');     // Default: Poslední den aktuálního měsíce
$filter_log_type = isset($_GET['log_type']) ? $_GET['log_type'] : 'all';         // 'all', 'entry', 'exit'
$filter_log_result = isset($_GET['log_result']) ? $_GET['log_result'] : 'all';   // 'all', 'granted', 'denied'

// --- Export CSV ---
if (isset($_GET['export']) && $_GET['export'] == 'csv' && $sessionUserId) {
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $sqlParams = [':userID' => $sessionUserId];
            $whereClauses = ["al.userID = :userID"];

            if (!empty($filter_date_from)) {
                $whereClauses[] = "DATE(al.logTime) >= :date_from";
                $sqlParams[':date_from'] = $filter_date_from;
            }
            if (!empty($filter_date_to)) {
                $whereClauses[] = "DATE(al.logTime) <= :date_to";
                $sqlParams[':date_to'] = $filter_date_to;
            }
            if ($filter_log_type !== 'all') {
                $whereClauses[] = "al.logType = :log_type";
                $sqlParams[':log_type'] = $filter_log_type;
            }
            if ($filter_log_result !== 'all') {
                $whereClauses[] = "al.logResult = :log_result";
                $sqlParams[':log_result'] = $filter_log_result;
            }

            $sqlExport = "SELECT u.firstName, u.lastName, al.logTime, al.logType, al.logResult 
                          FROM attendance_logs al
                          JOIN users u ON al.userID = u.userID 
                          WHERE " . implode(" AND ", $whereClauses) . "
                          ORDER BY al.logTime DESC";
            
            $stmtExport = $pdo->prepare($sqlExport);
            $stmtExport->execute($sqlParams);
            $logsToExport = $stmtExport->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($logsToExport)) {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=attendance_log_' . date('Y-m-d') . '.csv');
                $output = fopen('php://output', 'w');
                fputcsv($output, ['First Name', 'Last Name', 'Date & Time', 'Log Type', 'Log Result']); // Hlavička CSV

                foreach ($logsToExport as $log) {
                    fputcsv($output, [
                        $log['firstName'],
                        $log['lastName'],
                        date("Y-m-d H:i:s", strtotime($log['logTime'])),
                        ucfirst($log['logType']),
                        ucfirst($log['logResult'])
                    ]);
                }
                fclose($output);
                exit;
            } else {
                // Pokud nejsou data k exportu, můžeme přesměrovat zpět s chybovou hláškou, nebo jen nechat stránku načíst normálně.
                // Pro jednoduchost, pokud nejsou data, CSV se nevygeneruje a uživatel zůstane na stránce.
                // $dbErrorMessage = "No data to export for the selected filters.";
            }

        } catch (PDOException $e) {
            // $dbErrorMessage = "Error generating CSV: " . $e->getMessage(); 
            // Tuto chybu by bylo lepší zalogovat, uživatel by měl vidět obecnější zprávu.
            error_log("CSV Export Error: " . $e->getMessage());
            // Necháme stránku načíst normálně, aby uživatel viděl případnou chybu na stránce.
        }
    }
}


// --- Načítání dat pro zobrazení na stránce ---
if (isset($pdo) && $pdo instanceof PDO && $sessionUserId) {
    try {
        $sqlParams = [':userID' => $sessionUserId];
        $whereClauses = ["al.userID = :userID"];

        if (!empty($filter_date_from)) {
            $whereClauses[] = "DATE(al.logTime) >= :date_from";
            $sqlParams[':date_from'] = $filter_date_from;
        }
        if (!empty($filter_date_to)) {
            $whereClauses[] = "DATE(al.logTime) <= :date_to";
            $sqlParams[':date_to'] = $filter_date_to;
        }
        if ($filter_log_type !== 'all') {
            $whereClauses[] = "al.logType = :log_type";
            $sqlParams[':log_type'] = $filter_log_type;
        }
        if ($filter_log_result !== 'all') {
            $whereClauses[] = "al.logResult = :log_result";
            $sqlParams[':log_result'] = $filter_log_result;
        }

        $sql = "SELECT al.logTime, al.logType, al.logResult 
                FROM attendance_logs al
                WHERE " . implode(" AND ", $whereClauses) . "
                ORDER BY al.logTime DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($sqlParams);
        $attendanceLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $dbErrorMessage = "Database Query Error: " . $e->getMessage();
    }
} else {
    if (!$sessionUserId) $dbErrorMessage = "User session is invalid.";
    elseif (!isset($pdo) || !($pdo instanceof PDO)) $dbErrorMessage = "Database connection is not available.";
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="imgs/logo.png" type="image/x-icon"> 
    <title>My Attendance Log - <?php echo $sessionFirstName; ?> - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --primary-color-rgb: 67, 97, 238; 
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            
            /* Barvy pro statusy a logy */
            --success-color: #4CAF50; 
            --success-color-rgb: 76, 175, 80;
            --danger-color: #F44336;  
            --danger-color-rgb: 244, 67, 54;
            --warning-color: #FF9800; 
            --info-color: #2196F3;    
            
            /* === NOVÉ BARVY PRO ENTRY A EXIT PODLE OBRÁZKU === */
            --entry-bg-color: #e0f2f7;   /* Světlá tyrkysová/modrozelená pro pozadí */
            --entry-text-color: #00796b; /* Tmavší tyrkysová/modrozelená pro text */
            
            --exit-bg-color: #ffebee;     /* Světlá červená/růžová pro pozadí */
            --exit-text-color: #c62828;    /* Tmavší červená pro text */
            /* === KONEC NOVÝCH BAREV === */

            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
        }
        /* ... (zbytek vašich :root proměnných a globálních stylů jako *, body, main, .container atd.) ... */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; padding-top:80px; }
        main { flex-grow: 1; }
        .container, .page-header .container { 
            max-width: 1400px; margin-left: auto; margin-right: auto; padding-left: 20px; padding-right: 20px; 
        }
         .container-attendance-log { 
            display: flex; 
            gap: 1.8rem; 
            margin-top: 1.5rem; 
            align-items: flex-start; 
        }
        
        header { 
            background-color: var(--white); box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: fixed; width: 100%; top: 0; z-index: 1000; height: 80px;
        }
        
        .page-header { padding: 1.8rem 0; margin-bottom: 0; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; margin: 0; } .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        
        .db-error-message, .success-message { 
            padding: 1rem; border-left-width: 4px; border-left-style: solid; margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;
        }
        .db-error-message { background-color: rgba(var(--danger-color-rgb),0.1); color: var(--danger-color); border-left-color: var(--danger-color); }
        .success-message { background-color: rgba(var(--success-color-rgb),0.1); color: var(--success-color); border-left-color: var(--success-color); }

        .attendance-sidebar { 
            flex: 0 0 300px; background-color: var(--white); 
            padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow); 
            height: fit-content; 
        }
        .attendance-sidebar h3 { font-size: 1.25rem; margin-bottom: 1.2rem; color: var(--dark-color); padding-bottom: 0.6rem; border-bottom: 1px solid var(--light-gray); }
        .filter-group { margin-bottom: 1.5rem; }
        .filter-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.9rem; }
        .filter-group input[type="date"], .filter-group select {
            width: 100%; padding: 0.7rem; border: 1px solid var(--light-gray);
            border-radius: 6px; font-size: 0.9rem;
        }
        .filter-group input[type="date"]:focus, .filter-group select:focus {
            outline: none; border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb),0.2);
        }
        .btn-apply-filters, .btn-export {
            display: flex; align-items:center; justify-content:center; gap:0.5rem; 
            width: 100%; text-align: center; 
            padding: 0.8rem 1rem; border-radius: 6px; 
            font-weight: 500; transition: var(--transition); 
            border:none; cursor:pointer; font-size:0.95rem;
            margin-top: 0.8rem;
        }
        .btn-apply-filters { background-color: var(--primary-color); color: var(--white); }
        .btn-apply-filters:hover { background-color: var(--primary-dark); }
        .btn-export { background-color: var(--success-color); color: var(--white); }
        .btn-export:hover { opacity:0.9; }
        .btn-export .material-symbols-outlined { font-size:1.3em; }

        .attendance-content { flex-grow: 1; }
        .attendance-log-panel { 
            background-color: var(--white); padding: 1.8rem 2rem; 
            border-radius: 8px; box-shadow: var(--shadow); 
        }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); flex-wrap: wrap; gap:1rem;}
        .panel-title { font-size: 1.4rem; color: var(--dark-color); margin:0; }
        .export-buttons { display: flex; gap: 0.8rem; }

        .attendance-table-wrapper { overflow-x: auto; }
        .attendance-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .attendance-table th, .attendance-table td { 
            padding: 0.9rem 1rem; text-align: left; 
            border-bottom: 1px solid var(--light-gray); 
        }
        .attendance-table th { 
            background-color: #f9fafb; font-weight: 600; 
            color: var(--dark-color); white-space: nowrap;
        }
        .attendance-table tbody tr:hover { background-color: #f0f4ff; }
        
        /* === UPRAVENÉ STYLY PRO LOG-TYPE-BADGE === */
        .log-type-badge { 
            padding: 0.35rem 0.8rem; 
            border-radius: 16px;    
            font-size: 0.85rem;     
            font-weight: 500; 
            /* color: var(--white); Barva textu bude specifická pro typ */
            text-transform: capitalize; 
            display: inline-flex; 
            align-items: center;
            gap: 0.5rem; 
            line-height: 1.3;
        }
        .log-type-badge .material-symbols-outlined {
            font-size: 1.1em; 
            /* Barva ikony bude stejná jako barva textu badge */
        }

        .log-type-entry { 
            background-color: var(--entry-bg-color); 
            color: var(--entry-text-color);
        }
        .log-type-entry .material-symbols-outlined {
            color: var(--entry-text-color);
        }

        .log-type-exit { 
            background-color: var(--exit-bg-color); 
            color: var(--exit-text-color);
        }
        .log-type-exit .material-symbols-outlined {
            color: var(--exit-text-color);
        }
        /* === KONEC UPRAVENÝCH STYLŮ PRO LOG-TYPE-BADGE === */

        .log-result-granted { color: var(--success-color); font-weight: 500; }
        .log-result-denied { color: var(--danger-color); font-weight: 500; }

        .no-logs-message { text-align: center; padding: 3rem 1rem; color: var(--gray-color); font-size: 1.1rem; }
        .no-logs-message .material-symbols-outlined { font-size: 3rem; display:block; margin-bottom:0.8rem; color:var(--primary-color); opacity:0.7;}

        @media (max-width: 992px) { 
            .container-attendance-log { flex-direction: column; } 
            .attendance-sidebar { flex: 0 0 auto; width: 100%; margin-bottom: 1.5rem; }
        }
    </style>
</head>
<body>
    <?php require "components/header-admin.php"; // Nebo header-user.php, podle kontextu stránky ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>My Attendance Log</h1>
                <p class="sub-heading">Review your attendance history and export records.</p>
            </div>
        </div>

        <div class="container container-attendance-log"> 
            <aside class="attendance-sidebar">
                <!-- ... (obsah sidebar s filtry a exportem) ... -->
                <h3>Filter Log</h3>
                <form action="my_attendance_log.php" method="GET" id="filterForm">
                    <div class="filter-group">
                        <label for="date_from">From Date:</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="date_to">To Date:</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="log_type">Log Type:</label>
                        <select id="log_type" name="log_type">
                            <option value="all" <?php if ($filter_log_type == 'all') echo 'selected'; ?>>All Types</option>
                            <option value="entry" <?php if ($filter_log_type == 'entry') echo 'selected'; ?>>Entry</option>
                            <option value="exit" <?php if ($filter_log_type == 'exit') echo 'selected'; ?>>Exit</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="log_result">Log Result:</label>
                        <select id="log_result" name="log_result">
                            <option value="all" <?php if ($filter_log_result == 'all') echo 'selected'; ?>>All Results</option>
                            <option value="granted" <?php if ($filter_log_result == 'granted') echo 'selected'; ?>>Granted</option>
                            <option value="denied" <?php if ($filter_log_result == 'denied') echo 'selected'; ?>>Denied</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-apply-filters">
                        <span aria-hidden="true" translate="no" class="material-symbols-outlined">filter_alt</span> Apply Filters
                    </button>
                </form>
                <hr style="margin: 1.5rem 0;">
                <h3>Export Log</h3>
                <a href="my_attendance_log.php?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn-export">
                    <span aria-hidden="true" translate="no" class="material-symbols-outlined">download</span> Export as CSV
                </a>
                <a href="my_attendance_log.php?<?php echo http_build_query(array_merge($_GET, ['export' => 'pdf'])); ?>" class="btn-export" style="background-color: var(--danger-color); margin-top: 0.5rem;" onclick="alert('PDF export is not yet implemented.'); return false;">
                    <span aria-hidden="true" translate="no" class="material-symbols-outlined">picture_as_pdf</span> Export as PDF
                </a>
            </aside>

            <div class="attendance-content">
                <?php if ($dbErrorMessage): ?>
                    <div class="db-error-message" role="alert"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($dbErrorMessage); ?></div>
                <?php endif; ?>

                <section class="attendance-log-panel">
                    <div class="panel-header">
                        <h2 class="panel-title">Attendance Records</h2>
                    </div>
                    <div class="attendance-table-wrapper">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Log Type</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($attendanceLogs)): ?>
                                    <?php foreach ($attendanceLogs as $log): ?>
                                        <tr>
                                            <td><?php echo date("M d, Y - H:i:s", strtotime($log['logTime'])); ?></td>
                                            <td>
                                                <span class="log-type-badge log-type-<?php echo htmlspecialchars(strtolower($log['logType'])); ?>">
                                                    <?php if (strtolower($log['logType']) == 'entry'): ?>
                                                        <span aria-hidden="true" translate="no" class="material-symbols-outlined">login</span>
                                                    <?php elseif (strtolower($log['logType']) == 'exit'): ?>
                                                        <span aria-hidden="true" translate="no" class="material-symbols-outlined">logout</span>
                                                    <?php else: ?>
                                                        <span aria-hidden="true" translate="no" class="material-symbols-outlined">help_outline</span>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars(ucfirst($log['logType'])); ?>
                                                </span>
                                            </td>
                                            <td class="log-result-<?php echo htmlspecialchars(strtolower($log['logResult'])); ?>">
                                                <?php echo htmlspecialchars(ucfirst($log['logResult'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php if (!$dbErrorMessage): ?>
                                    <tr>
                                        <td colspan="3" class="no-logs-message">
                                            <span aria-hidden="true" translate="no" class="material-symbols-outlined">manage_search</span>
                                            No attendance records found matching your criteria.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <?php require "components/footer-admin.php"; // Nebo footer-user.php ?>

    <script>
        // ... (váš JavaScript pro date pickery a případně hamburger menu) ...
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const dateFromInput = document.getElementById('date_from');
            const dateToInput = document.getElementById('date_to');

            if (dateToInput) {
                dateToInput.max = today; 
            }

            if (dateFromInput && dateToInput) {
                dateFromInput.addEventListener('change', function() {
                    if (dateToInput.value && dateToInput.value < this.value) {
                        dateToInput.value = this.value;
                    }
                    dateToInput.min = this.value; 
                });
                 dateToInput.addEventListener('change', function() {
                    if (dateFromInput.value && this.value < dateFromInput.value) {
                        // Optionally set dateFromInput.value = this.value if "to" can't be before "from"
                        // For now, just ensure "from" doesn't restrict "to" incorrectly if "to" is changed first.
                    }
                });
                if (dateFromInput.value) {
                     dateToInput.min = dateFromInput.value;
                }
            }
        });
    </script>
</body>
</html>