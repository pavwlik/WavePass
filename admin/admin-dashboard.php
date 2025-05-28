<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- SESSION CHECK & ADMIN ROLE CHECK ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["role"]) || $_SESSION["role"] !== 'admin') {
    header("location: ../login.php");
    exit;
}

require_once '../db.php';

// --- SESSION VARIABLES ---
$sessionFirstName = isset($_SESSION["first_name"]) ? htmlspecialchars($_SESSION["first_name"]) : 'Admin';
$sessionUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;

// --- DATE HANDLING ---
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || strtotime($selectedDate) === false) {
    $selectedDate = date('Y-m-d');
}
$todayDate = date('Y-m-d');
$todayDateTime = date('Y-m-d H:i:s');

// --- DEFAULT VALUES FOR ADMIN'S OWN DISPLAY ---
$adminRfidStatus = "Status Unknown";
$adminRfidStatusClass = "neutral";
$adminUnreadMessagesCount = 0;
$adminActivityForSelectedDate = [];
$dbErrorMessage = null;
$adminCurrentUserData = null;

// Variables for Late Departures
$lateDeparturesTodayDetails = [];
$lateDepartureCount = 0;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $dbErrorMessage = "Database connection is not available. Please check server configuration or db.php.";
} elseif (!$sessionUserId) {
    $dbErrorMessage = "Admin session is invalid or user ID not found. Please log in again.";
} else {
    try {
        // 1. Fetch admin's own dateOfCreation
        $stmtAdminMeta = $pdo->prepare("SELECT dateOfCreation FROM users WHERE userID = :userid_param_meta");
        $stmtAdminMeta->bindParam(':userid_param_meta', $sessionUserId, PDO::PARAM_INT);
        $stmtAdminMeta->execute();
        $adminCurrentUserData = $stmtAdminMeta->fetch(PDO::FETCH_ASSOC);
        $stmtAdminMeta->closeCursor();

        // 2. DETERMINE ADMIN'S OWN CURRENT PRESENCE STATUS
        $stmtAdminLatestEvent = $pdo->prepare(
            "SELECT logType, logResult FROM attendance_logs
             WHERE userID = :userid_param_event AND DATE(logTime) = :today_date_param_event
             ORDER BY logTime DESC LIMIT 1"
        );
        $stmtAdminLatestEvent->bindParam(':userid_param_event', $sessionUserId, PDO::PARAM_INT);
        $stmtAdminLatestEvent->bindParam(':today_date_param_event', $todayDate);
        $stmtAdminLatestEvent->execute();
        $adminLatestEvent = $stmtAdminLatestEvent->fetch(PDO::FETCH_ASSOC);
        $stmtAdminLatestEvent->closeCursor();

        if ($adminLatestEvent) {
            if ($adminLatestEvent['logType'] == 'entry' && $adminLatestEvent['logResult'] == 'granted') { $adminRfidStatus = "Present"; $adminRfidStatusClass = "present"; }
            elseif ($adminLatestEvent['logType'] == 'exit' && $adminLatestEvent['logResult'] == 'granted') { $adminRfidStatus = "Checked Out"; $adminRfidStatusClass = "checked-out"; }
            elseif ($adminLatestEvent['logResult'] == 'denied') { $adminRfidStatus = ($adminLatestEvent['logType'] == 'entry' ? "Entry Denied" : "Exit Denied"); $adminRfidStatusClass = "danger"; }
            else { $adminRfidStatus = "Status Unknown"; $adminRfidStatusClass = "neutral"; }
        } else {
            $stmtAdminScheduledAbsenceToday = $pdo->prepare(
                "SELECT absence_type FROM absence WHERE userID = :userid_param_absence
                 AND :today_date_time_param_absence BETWEEN absence_start_datetime AND absence_end_datetime
                 AND status = 'approved' LIMIT 1"
            );
            $stmtAdminScheduledAbsenceToday->bindParam(':userid_param_absence', $sessionUserId, PDO::PARAM_INT);
            $stmtAdminScheduledAbsenceToday->bindParam(':today_date_time_param_absence', $todayDateTime);
            $stmtAdminScheduledAbsenceToday->execute();
            $adminScheduledAbsence = $stmtAdminScheduledAbsenceToday->fetch(PDO::FETCH_ASSOC);
            $stmtAdminScheduledAbsenceToday->closeCursor();
            if ($adminScheduledAbsence) {
                $absenceTypeDisplay = ucfirst(str_replace('_', ' ', $adminScheduledAbsence['absence_type']));
                $adminRfidStatus = "Scheduled " . htmlspecialchars($absenceTypeDisplay); $adminRfidStatusClass = "absent";
            } else {
                $adminRfidStatus = "Not Checked In"; $adminRfidStatusClass = "neutral";
            }
        }

        // 4. UNREAD MESSAGES COUNT for Admin
        $stmtAdminUnread = $pdo->prepare("
            SELECT COUNT(DISTINCT m.messageID) FROM messages m
            LEFT JOIN user_message_read_status umrs ON m.messageID = umrs.messageID AND umrs.userID = :current_user_id_unread
            WHERE m.is_active = TRUE AND (m.expires_at IS NULL OR m.expires_at > NOW())
            AND (m.recipientID = :current_user_id_unread_rec OR m.recipientRole = 'admin' OR m.recipientRole = 'everyone')
            AND (umrs.is_read IS NULL OR umrs.is_read = 0)"
        );
        $stmtAdminUnread->execute([
            ':current_user_id_unread' => $sessionUserId,
            ':current_user_id_unread_rec' => $sessionUserId
        ]);
        $adminUnreadMessagesCount = (int)$stmtAdminUnread->fetchColumn();
        $stmtAdminUnread->closeCursor();

        // Fetch today's late departure notifications
        $stmtLate = $pdo->prepare(
            "SELECT ldn.userID, ldn.planned_departure_time, ldn.notes, u.firstName, u.lastName
             FROM late_departure_notifications ldn
             JOIN users u ON ldn.userID = u.userID
             WHERE ldn.notification_date = :today_date_late
             ORDER BY ldn.planned_departure_time ASC, u.lastName ASC, u.firstName ASC"
        );
        $stmtLate->bindParam(':today_date_late', $todayDate);
        $stmtLate->execute();
        $lateDeparturesTodayDetails = $stmtLate->fetchAll(PDO::FETCH_ASSOC);
        $lateDepartureCount = count($lateDeparturesTodayDetails);
        $stmtLate->closeCursor();

        // 6. ADMIN'S OWN ACTIVITY SNAPSHOT FOR SELECTED DATE
        if ($adminCurrentUserData && isset($adminCurrentUserData['dateOfCreation']) && $selectedDate == date("Y-m-d", strtotime($adminCurrentUserData['dateOfCreation']))) {
            $adminActivityForSelectedDate[] = [
                'time' => date("H:i", strtotime($adminCurrentUserData['dateOfCreation'])),
                'original_db_log_type' => 'system_account_created',
                'log_type' => 'System',
                'log_result' => 'Info',
                'details' => 'Your Admin account was created.',
                'rfid_card_uid' => 'N/A',
                'status_class' => 'info'
            ];
        }

        // Načítanie záznamov z attendance_logs pre admina, zoradené od najnovších
        $sqlAdminActivity = "SELECT al.logTime, al.logType, al.logResult, al.rfid_uid_used
                             FROM attendance_logs al
                             WHERE al.userID = :admin_userid_activity AND DATE(al.logTime) = :selected_date_activity
                             ORDER BY al.logTime DESC"; // <--- ZMENA NA DESC PRE ZORADENIE
        $stmtAdminActivityLog = $pdo->prepare($sqlAdminActivity);
        $stmtAdminActivityLog->bindParam(':admin_userid_activity', $sessionUserId, PDO::PARAM_INT);
        $stmtAdminActivityLog->bindParam(':selected_date_activity', $selectedDate);
        $stmtAdminActivityLog->execute();

        while ($log = $stmtAdminActivityLog->fetch(PDO::FETCH_ASSOC)) {
            $logTimeFromDB = $log['logTime'] ?? date('Y-m-d H:i:s');
            $logTypeFromDB = $log['logType'] ?? 'undefined';
            $logResultFromDB = $log['logResult'] ?? 'undefined';
            $rfidUidFromDB = $log['rfid_uid_used'] ?? null;

            $logTypeDisplay = 'Event';
            $statusClass = 'neutral';
            $detailsDisplay = 'Activity recorded.';

            switch ($logTypeFromDB) {
                case 'entry':
                    $logTypeDisplay = 'Entry';
                    $detailsDisplay = ($logResultFromDB == 'granted') ? 'Access granted.' : 'Access attempt denied.';
                    $statusClass = ($logResultFromDB == 'granted') ? 'present' : 'danger';
                    break;
                case 'exit':
                    $logTypeDisplay = 'Exit';
                    $detailsDisplay = ($logResultFromDB == 'granted') ? 'Exit recorded.' : 'Exit attempt denied.';
                    $statusClass = ($logResultFromDB == 'granted') ? 'checked-out' : 'danger';
                    break;
                case 'auto_registered':
                    $logTypeDisplay = 'Card Auto-Registered';
                    $detailsDisplay = 'Card auto-registration event.';
                    // Predpokladajme, že logResult pre 'auto_registered' je 'denied' alebo 'info' (ak ste ho pridali)
                    $statusClass = ($logResultFromDB == 'info') ? 'info' : (($logResultFromDB == 'denied') ? 'warning' : 'neutral');
                    break;
                case 'unknown_card_scan':
                    $logTypeDisplay = 'Unknown Card Scan';
                    $detailsDisplay = 'Unrecognized card scan attempt.';
                    $statusClass = 'warning';
                    break;
                case 'unassigned_card_attempt':
                    $logTypeDisplay = 'Unassigned Card Use';
                    $detailsDisplay = 'Attempt to use an unassigned card.';
                    $statusClass = 'danger';
                    break;
                default:
                    $logTypeDisplay = ucfirst(str_replace('_', ' ', $logTypeFromDB));
                    $detailsDisplay = 'Unspecified admin activity event.';
                    if ($logResultFromDB == 'denied') $statusClass = 'danger';
                    elseif ($logResultFromDB == 'info') $statusClass = 'info';
                    else $statusClass = 'neutral';
                    break;
            }

            $adminActivityForSelectedDate[] = [
                'time' => date("H:i", strtotime($logTimeFromDB)),
                'original_db_log_type' => $logTypeFromDB,
                'log_type' => htmlspecialchars($logTypeDisplay),
                'log_result' => htmlspecialchars(ucfirst($logResultFromDB)),
                'details' => htmlspecialchars($detailsDisplay),
                'rfid_card_uid' => !empty($rfidUidFromDB) ? htmlspecialchars($rfidUidFromDB) : 'N/A',
                'status_class' => $statusClass
            ];
        }
        $stmtAdminActivityLog->closeCursor();

        // Fallback logika (už by nemala byť potrebná `usort`, ak SQL triedi správne)
        $adminHasCheckInOutActivity = false;
        foreach($adminActivityForSelectedDate as $act) {
            if (isset($act['original_db_log_type']) && ($act['original_db_log_type'] === 'entry' || $act['original_db_log_type'] === 'exit')) {
                $adminHasCheckInOutActivity = true; break;
            }
        }

        if ($selectedDate == $todayDate && !$adminHasCheckInOutActivity && $adminRfidStatus !== "Status Unknown" && $adminRfidStatus !== "Not Checked In") {
            if ($adminRfidStatusClass !== 'neutral' || strpos(strtolower($adminRfidStatus), 'scheduled') !== false) {
                // Tento blok pridá "Current Status" na koniec, ak SQL už triedi DESC.
                // Ak chceme, aby bol Current Status vždy hore, museli by sme ho pridať na začiatok poľa
                // alebo použiť usort, ktorý to zohľadní.
                $adminActivityForSelectedDate[] = [
                    'time' => date("H:i"),
                    'original_db_log_type' => 'system_current_status',
                    'log_type' => 'Current Status',
                    'log_result' => htmlspecialchars($adminRfidStatus),
                    'details' => 'Based on latest system information (admin).',
                    'rfid_card_uid' => 'N/A',
                    'status_class' => $adminRfidStatusClass
                ];
            }
        }
        if (empty($adminActivityForSelectedDate) && $selectedDate <= $todayDate){
            $stmtAdminSelectedDayAbsence = $pdo->prepare(
                 "SELECT absence_type, reason FROM absence WHERE userID = :userid_param
                   AND :selected_date_for_absence_param BETWEEN DATE(absence_start_datetime) AND DATE(absence_end_datetime)
                   AND status = 'approved' LIMIT 1"
            );
            $stmtAdminSelectedDayAbsence->bindParam(':userid_param', $sessionUserId, PDO::PARAM_INT);
            $stmtAdminSelectedDayAbsence->bindParam(':selected_date_for_absence_param', $selectedDate);
            $stmtAdminSelectedDayAbsence->execute();
            $adminSelectedDayAbsenceInfo = $stmtAdminSelectedDayAbsence->fetch(PDO::FETCH_ASSOC);
            $stmtAdminSelectedDayAbsence->closeCursor();

            if($adminSelectedDayAbsenceInfo){
                $absenceTypeDetailForMsg = htmlspecialchars(ucfirst(str_replace('_',' ',$adminSelectedDayAbsenceInfo['absence_type'])));
                $adminActivityForSelectedDate[] = [
                     'time' => '--:--', 'original_db_log_type' => 'system_scheduled_absence',
                     'log_type' => 'Scheduled Absence', 'log_result' => htmlspecialchars($absenceTypeDetailForMsg),
                     'details' => (!empty($adminSelectedDayAbsenceInfo['reason']) ? 'Reason: '.htmlspecialchars($adminSelectedDayAbsenceInfo['reason']) : 'Approved absence'),
                     'rfid_card_uid' => 'N/A', 'status_class' => 'absent'
                    ];
            } else {
                 if(empty($adminActivityForSelectedDate)) {
                    $adminActivityForSelectedDate[] = [
                         'time' => '--:--', 'original_db_log_type' => 'system_no_record',
                         'log_type' => 'No Record', 'log_result' => 'N/A',
                         'details' => 'No personal attendance events logged for this day.',
                         'rfid_card_uid' => 'N/A', 'status_class' => 'neutral'
                        ];
                }
            }
        }
        // Ak SQL dotaz pre $stmtAdminActivityLog už triedi DESC, tento usort už nie je potrebný pre dáta z DB.
        // Môže byť potrebný, ak chceme špeciálne fallback záznamy umiestniť inak.
        // Pre jednoduchosť ho teraz ponechám, ale s vedomím, že hlavné triedenie je už v SQL.
        if (!empty($adminActivityForSelectedDate)) {
            usort($adminActivityForSelectedDate, function($a, $b) {
                $timeAIsSpecial = ($a['time'] === '--:--');
                $timeBIsSpecial = ($b['time'] === '--:--');
                if ($timeAIsSpecial && $timeBIsSpecial) return 0;
                if ($timeAIsSpecial) return 1;
                if ($timeBIsSpecial) return -1;
                return strtotime($b['time']) - strtotime($a['time']);
            });
        }

    } catch (PDOException $e) {
        error_log("Admin Dashboard DB Error (Admin UserID: {$sessionUserId}, Selected Date: {$selectedDate}): " . $e->getMessage() . " --- SQL Query that failed (if available in trace): " . ($e->getTrace()[0]['args'][0] ?? 'N/A'));
        $dbErrorMessage = "A database error occurred (PDO). Please check the server logs (PHP error log) for the exact SQL error and query. The error message was: " . htmlspecialchars($e->getMessage());
    } catch (Exception $e) {
        error_log("Admin Dashboard App Error (Admin UserID: {$sessionUserId}, Selected Date: {$selectedDate}): " . $e->getMessage());
        $dbErrorMessage = "An application error occurred. Please try again later.";
    }
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../imgs/logo.png" type="image/x-icon">
    <title>Admin Dashboard - <?php echo $sessionFirstName; ?> - WavePass</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Vložte sem celé CSS z vášho admin-dashboard.php alebo dashboard.php */
        /* ... (Vynechané pre stručnosť) ... */
        :root {
            --primary-color: #4361ee; --primary-dark: #3a56d4; --secondary-color: #3f37c9;
            --dark-color: #1a1a2e; --light-color: #f8f9fa; --gray-color: #6c757d;
            --light-gray: #e9ecef; --white: #ffffff;
            --true-success-color: #28a745; --warning-color: #f8961e;
            --danger-color: #f72585; --info-color-custom: #007bff;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08); --transition: all 0.3s ease;
            --present-color-val: 67, 170, 139; --absent-color-val: 214, 40, 40;
            --checked-out-color-val: 255, 193, 7; --info-color-val: 84, 160, 255;
            --neutral-color-val: 173, 181, 189; --warning-color-val: 248, 150, 30;
            --late-departure-icon-color-val: 23, 162, 184; --danger-color-val: 247, 37, 133;
            --true-danger-color-val: 220, 53, 69;

            --present-color: rgb(var(--present-color-val)); --absent-color: rgb(var(--absent-color-val));
            --checked-out-color: rgb(var(--checked-out-color-val)); --info-color: rgb(var(--info-color-val));
            --neutral-color: rgb(var(--neutral-color-val));
            --late-departure-icon-color: rgb(var(--late-departure-icon-color-val));
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: var(--dark-color); background-color: #f4f6f9; display: flex; flex-direction: column; min-height: 100vh; padding-top: 80px; }
        main { flex-grow: 1; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
        h1, h2, h3, h4 { font-weight: 700; line-height: 1.2; }
        .page-header { padding: 1.8rem 0; margin-bottom: 1.8rem; background-color:var(--white); box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
        .page-header h1 { font-size: 1.7rem; color: var(--dark-color); margin: 0; }
        .page-header .sub-heading { font-size: 0.9rem; color: var(--gray-color); }
        .db-error-message {background-color: rgba(var(--true-danger-color-val),0.1); color: rgb(var(--true-danger-color-val)); padding: 1rem; border-left: 4px solid rgb(var(--true-danger-color-val)); margin-bottom: 1.5rem; border-radius: 4px; font-size:0.9rem;}
        .admin-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        .stat-card { background-color: var(--white); padding: 1.2rem 1.5rem; border-radius: 8px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1.2rem; transition: var(--transition); border: 1px solid var(--light-gray); position:relative; overflow:hidden;}
        .stat-card::before { content: ''; position:absolute; top:0; left:0; width:5px; height:100%; background-color:transparent; transition: var(--transition); }
        .stat-card:hover{transform: translateY(-4px); box-shadow: 0 6px 28px rgba(0,0,0,0.09);}
        .stat-card .icon { font-size: 2.2rem; padding: 0.8rem; border-radius: 50%; display:flex; align-items:center; justify-content:center; width:55px; height:55px; flex-shrink:0;}
        .stat-card .info { flex-grow: 1; }
        .stat-card .info .value { font-size: 1.8rem; font-weight: 500; color: var(--dark-color); display:block; line-height:1.1; margin-bottom:0.3rem;}
        .stat-card .info .label { font-size: 0.8rem; color: var(--gray-color); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .stat-card-button { background-color: var(--primary-color); color: var(--white); border: none; padding: 0.6rem 1rem; border-radius: 5px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: background-color 0.2s; margin-left: auto; white-space: nowrap; align-self: center; }
        .stat-card .stat-card-button:hover { background-color: var(--primary-dark); }
        .stat-card.rfid-status.present::before { background-color: var(--present-color); }
        .stat-card.rfid-status.present .icon { background-color: rgba(var(--present-color-val),0.1); color: var(--present-color); }
        .stat-card.rfid-status.absent::before { background-color: var(--absent-color); }
        .stat-card.rfid-status.absent .icon { background-color: rgba(var(--absent-color-val),0.1); color: var(--absent-color); }
        .stat-card.rfid-status.checked-out::before { background-color: var(--checked-out-color); }
        .stat-card.rfid-status.checked-out .icon { background-color: rgba(var(--checked-out-color-val),0.15); color: var(--checked-out-color); }
        .stat-card.rfid-status.neutral::before { background-color: var(--neutral-color); }
        .stat-card.rfid-status.neutral .icon { background-color: rgba(var(--neutral-color-val),0.1); color: var(--neutral-color); }
        .stat-card.rfid-status.danger::before { background-color: rgb(var(--danger-color-val)); }
        .stat-card.rfid-status.danger .icon { background-color: rgba(var(--danger-color-val),0.1); color: rgb(var(--danger-color-val)); }
        .stat-card.unread-messages::before { background-color: var(--warning-color); }
        .stat-card.unread-messages .icon { background-color: rgba(var(--warning-color-val),0.1); color: var(--warning-color); }
        .stat-card.admin-late-departures::before { background-color: var(--late-departure-icon-color); }
        .stat-card.admin-late-departures .icon { background-color: rgba(var(--late-departure-icon-color-val),0.1); color: var(--late-departure-icon-color); }
        .content-panel { background-color: var(--white); padding: 1.5rem 1.8rem; border-radius: 8px; box-shadow: var(--shadow); border: 1px solid var(--light-gray); margin-bottom: 2rem; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); }
        .panel-title { font-size: 1.3rem; color: var(--dark-color); margin:0; }
        .date-navigation { display: flex; align-items: center; gap: 0.5rem; }
        .date-navigation .btn-nav { background-color:var(--light-color); border:1px solid var(--light-gray); color:var(--dark-color); padding:0.4rem 0.6rem; border-radius:4px; cursor:pointer; transition:var(--transition); }
        .date-navigation .btn-nav:hover:not(:disabled) { background-color:var(--primary-color); color:var(--white); border-color:var(--primary-color); }
        .date-navigation .btn-nav:disabled { background-color: var(--light-gray); color: var(--gray-color); cursor: not-allowed; border-color: var(--light-gray); }
        .date-navigation .btn-nav .material-symbols-outlined {font-size:1.2em; vertical-align:middle;}
        .date-navigation input[type="date"] { padding: 0.4rem 0.7rem; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.9rem; background-color: var(--white); height:36px; text-align:center;}
        .activity-table-wrapper { overflow-x: auto; min-height: 150px; }
        .activity-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .activity-table th, .activity-table td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid var(--light-gray); }
        .activity-table th { background-color: #f9fafb; font-weight: 500; color: var(--gray-color); white-space: nowrap; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px; }
        .activity-table tbody tr:last-child td { border-bottom:none; }
        .activity-table tbody tr:hover { background-color: #f0f4ff; }
        .activity-status { display: inline-flex; align-items:center; gap:0.4rem; padding: 0.25rem 0.7rem; border-radius: 15px; font-size: 0.78rem; font-weight: 500; white-space: nowrap; }
        .activity-status .material-symbols-outlined { font-size: 1.1em; }
        .activity-status.present { background-color: rgba(var(--present-color-val), 0.15); color: var(--present-color); }
        .activity-status.absent { background-color: rgba(var(--absent-color-val), 0.15); color: var(--absent-color); }
        .activity-status.checked-out { background-color: rgba(var(--checked-out-color-val), 0.15); color: var(--checked-out-color); }
        .activity-status.info { background-color: rgba(var(--info-color-val), 0.15); color: var(--info-color); }
        .activity-status.neutral { background-color: rgba(var(--neutral-color-val),0.15); color: var(--neutral-color);}
        .activity-status.danger { background-color: rgba(var(--danger-color-val),0.15); color: rgb(var(--danger-color-val));}
        .activity-status.warning { background-color: rgba(var(--warning-color-val),0.15); color: var(--warning-color);}
        .activity-table td.rfid-cell { font-family: monospace; font-size: 0.8rem; color: var(--gray-color); }
        .no-activity-msg {text-align:center; padding: 2.5rem 1rem; color:var(--gray-color); font-size:0.95rem; background-color: #fdfdfd; border-radius: 4px; border: 1px dashed var(--light-gray);}
        /* Styly pre admin modal pre neskoré odchody */
        .admin-view-modal { display: none; position: fixed; z-index: 1070; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7); align-items: center; justify-content: center; padding: 20px;}
        .admin-view-modal.show { display: flex; }
        .admin-view-modal-content { background-color: var(--white); margin: auto; padding: 25px 30px; border-radius: 10px; width: 90%; max-width: 800px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); position: relative; animation: fadeInModal 0.3s ease-out; }
        @keyframes fadeInModal { from {opacity: 0; transform: translateY(-30px) scale(0.95);} to {opacity: 1; transform: translateY(0) scale(1);} }
        .admin-view-modal-content h2 { margin-top: 0; margin-bottom: 1.5rem; font-size: 1.6rem; color: var(--primary-color); text-align: center; padding-bottom: 0.8rem; border-bottom: 1px solid var(--light-gray); }
        .admin-view-close-modal-btn { color: var(--gray-color); background: transparent; border: none; position: absolute; top: 15px; right: 18px; font-size: 1.9rem; font-weight: bold; line-height: 1; padding: 0.2rem 0.5rem; cursor: pointer; transition: color 0.2s ease; }
        .admin-view-close-modal-btn:hover { color: var(--dark-color); }
        table.admin-late-departure-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: 0.9rem; }
        table.admin-late-departure-table th, table.admin-late-departure-table td { padding: 0.6rem 0.8rem; text-align: left; border-bottom: 1px solid var(--light-gray); }
        table.admin-late-departure-table th { background-color: #f9fafb; font-weight: 600; color: var(--dark-color); }
        table.admin-late-departure-table tbody tr:hover { background-color: #f5f5f5; }
        .admin-view-modal-actions { margin-top: 1.8rem; text-align: right; }
        .admin-view-btn-secondary { background-color: var(--gray-color); color: var(--white); border: none; padding: 0.7rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; transition: background-color 0.2s; font-size: 0.9rem; }
        .admin-view-btn-secondary:hover { background-color: var(--dark-color); }

        footer { background-color: var(--dark-color); color: var(--white); padding: 2rem 0; margin-top: auto; text-align: center; }
        footer p { margin: 0; font-size: 0.9rem;}
        footer a { color: rgba(255,255,255,0.8); text-decoration:none;}
        footer a:hover { color:var(--white); }
    </style>
</head>
<body>
    <?php require_once "../components/header-admin.php"; ?>

    <main>
        <div class="page-header">
            <div class="container">
                <h1>Admin Dashboard</h1>
                <p class="sub-heading">Overview for <?php echo $sessionFirstName; ?>.</p>
            </div>
        </div>

        <div class="container" style="padding-bottom: 2.5rem;">
            <?php if (isset($dbErrorMessage) && $dbErrorMessage): ?>
                <div class="db-error-message" role="alert">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i> <?php echo $dbErrorMessage; // Zobrazí detailnejšiu chybu ?>
                </div>
            <?php endif; ?>

            <section class="admin-stats-grid">
                <!-- Admin's personal stats cards (HTML zostáva rovnaký) -->
                 <div class="stat-card rfid-status <?php echo htmlspecialchars($adminRfidStatusClass); ?>">
                    <div class="icon"><span class="material-symbols-outlined">
                        <?php
                            if ($adminRfidStatusClass === "present") echo "admin_panel_settings";
                            elseif ($adminRfidStatusClass === "absent" && strpos($adminRfidStatus, "Scheduled") !== false) echo "event_busy";
                            elseif ($adminRfidStatusClass === "checked-out") echo "logout";
                            elseif ($adminRfidStatusClass === "danger") echo "gpp_maybe";
                            else echo "contactless";
                        ?>
                    </span></div>
                    <div class="info">
                        <span class="value"><?php echo htmlspecialchars($adminRfidStatus); ?></span>
                        <span class="label">Your Current Status (Admin)</span>
                    </div>
                </div>
                <div class="stat-card unread-messages">
                    <div class="icon"><span class="material-symbols-outlined">mark_chat_unread</span></div>
                    <div class="info">
                        <span class="value"><?php echo $adminUnreadMessagesCount; ?></span>
                        <span class="label">Your Unread Messages</span>
                    </div>
                </div>
                <div class="stat-card admin-late-departures">
                    <div class="icon"><span class="material-symbols-outlined">history_edu</span></div>
                    <div class="info">
                        <span class="value"><?php echo $lateDepartureCount; ?></span>
                        <span class="label">Users Staying Late Today</span>
                    </div>
                    <?php if ($lateDepartureCount > 0): ?>
                        <button type="button" class="stat-card-button" id="viewLateDeparturesDetailsBtn">View Details</button>
                    <?php endif; ?>
                </div>
            </section>

            <section class="content-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Your Activity for <span id="selectedDateDisplay"><?php echo date("F d, Y", strtotime($selectedDate)); ?></span> (Admin)</h2>
                    <div class="date-navigation">
                        <button class="btn-nav" id="prevDayBtn" title="Previous Day"><span class="material-symbols-outlined">chevron_left</span></button>
                        <input type="date" id="activity-date-selector" value="<?php echo htmlspecialchars($selectedDate); ?>" max="<?php echo $todayDate; ?>">
                        <button class="btn-nav" id="nextDayBtn" title="Next Day" <?php if ($selectedDate >= $todayDate) echo 'disabled'; ?> ><span class="material-symbols-outlined">chevron_right</span></button>
                    </div>
                </div>

                <div class="activity-table-wrapper">
                    <table class="activity-table" id="adminPersonalActivityTable">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Result</th>
                                <th>Details</th>
                                <th>Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($adminActivityForSelectedDate)): ?>
                                <?php foreach ($adminActivityForSelectedDate as $activity): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($activity['time']); ?></td>
                                        <td>
                                            <span class="activity-status <?php echo htmlspecialchars($activity['status_class'] ?? 'neutral'); ?>">
                                                <?php
                                                $logTypeForIcon = $activity['original_db_log_type'] ?? $activity['log_type'] ?? 'unknown';
                                                $logTypeForIconClean = strtolower(str_replace([' ', '_'], '', $logTypeForIcon));

                                                if ($logTypeForIconClean === 'entry') { echo '<span class="material-symbols-outlined">login</span>'; }
                                                elseif ($logTypeForIconClean === 'exit') { echo '<span class="material-symbols-outlined">logout</span>'; }
                                                elseif ($logTypeForIconClean === 'systemaccountcreated') { echo '<span class="material-symbols-outlined">engineering</span>'; }
                                                elseif ($logTypeForIconClean === 'systemcurrentstatus') { echo '<span class="material-symbols-outlined">update</span>'; }
                                                elseif ($logTypeForIconClean === 'systemscheduledabsence') { echo '<span class="material-symbols-outlined">event_busy</span>'; }
                                                elseif ($logTypeForIconClean === 'systemnorecord') { echo '<span class="material-symbols-outlined">search_off</span>'; }
                                                elseif ($logTypeForIconClean === 'autoregistered') { echo '<span class="material-symbols-outlined">person_add</span>'; }
                                                elseif ($logTypeForIconClean === 'unknowncardscan') { echo '<span class="material-symbols-outlined">contactless_off</span>'; }
                                                elseif ($logTypeForIconClean === 'unassignedcardattempt') { echo '<span class="material-symbols-outlined">no_accounts</span>'; }
                                                // Pridajte ďalšie ikony podľa potreby pre nové logType
                                                else { echo '<span class="material-symbols-outlined">help_outline</span>'; }
                                                ?>
                                                <?php echo htmlspecialchars($activity['log_type'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['log_result'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($activity['details'] ?? 'N/A'); ?></td>
                                        <td class="rfid-cell"><?php echo htmlspecialchars($activity['rfid_card_uid'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-activity-msg">No personal activity recorded for you on <?php echo date("M d, Y", strtotime($selectedDate)); ?>.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <!-- MODAL FOR LATE DEPARTURE DETAILS (HTML zostáva rovnaký) -->
    <div id="adminLateDeparturesModal" class="admin-view-modal">
        <div class="admin-view-modal-content">
            <button type="button" class="admin-view-close-modal-btn" aria-label="Close modal">×</button>
            <h2>Users Staying Late Today</h2>
            <?php if ($lateDepartureCount > 0 && !empty($lateDeparturesTodayDetails)): ?>
                <div class="activity-table-wrapper">
                    <table class="admin-late-departure-table">
                        <thead><tr><th>User</th><th>Planned Departure</th><th>Notes</th></tr></thead>
                        <tbody>
                            <?php foreach ($lateDeparturesTodayDetails as $departure): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($departure['firstName'] . ' ' . $departure['lastName']); ?></td>
                                    <td><?php echo date("H:i", strtotime($departure['planned_departure_time'])); ?></td>
                                    <td>
                                        <?php if (!empty($departure['notes'])): echo htmlspecialchars($departure['notes']);
                                              else: echo '<span style="color: var(--gray-color); font-style:italic;">No notes</span>';
                                        endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: var(--gray-color); margin-top: 1rem;">No users have notified a late departure for today.</p>
            <?php endif; ?>
            <div class="admin-view-modal-actions">
                <button type="button" class="admin-view-btn-secondary admin-view-close-modal-btn">Close</button>
            </div>
        </div>
    </div>

    <?php require_once "../components/footer-admin.php"; ?>

<script>
    // JavaScript kód pre admin-dashboard.php (navigácia dátumom, modal pre neskoré odchody)
    // Tento kód je rovnaký ako bol vo vašom admin-dashboard.php
    document.addEventListener('DOMContentLoaded', function() {
        const dateSelector = document.getElementById('activity-date-selector');
        const prevDayBtn = document.getElementById('prevDayBtn');
        const nextDayBtn = document.getElementById('nextDayBtn');
        const todayISO = new Date().toISOString().split('T')[0];

        function navigateToDate(dateString) {
            window.location.href = `admin-dashboard.php?date=${dateString}`;
        }

        function updateNextDayButtonState() {
            if (nextDayBtn && dateSelector) {
                nextDayBtn.disabled = dateSelector.value >= todayISO;
            }
        }

        if (dateSelector) {
            dateSelector.addEventListener('change', function() { navigateToDate(this.value); });
            if(dateSelector.max === "") dateSelector.max = todayISO;
            updateNextDayButtonState();
        }
        if (prevDayBtn) {
            prevDayBtn.addEventListener('click', function() {
                if (!dateSelector) return;
                const currentDate = new Date(dateSelector.value);
                currentDate.setDate(currentDate.getDate() - 1);
                navigateToDate(currentDate.toISOString().split('T')[0]);
            });
        }
        if (nextDayBtn) {
            nextDayBtn.addEventListener('click', function() {
                if (!dateSelector || dateSelector.value >= todayISO) return;
                const currentDate = new Date(dateSelector.value);
                currentDate.setDate(currentDate.getDate() + 1);
                navigateToDate(currentDate.toISOString().split('T')[0]);
            });
        }

        const viewLateDeparturesBtn = document.getElementById('viewLateDeparturesDetailsBtn');
        const lateDeparturesModal = document.getElementById('adminLateDeparturesModal');
        const lateModalCloseButtons = lateDeparturesModal ? lateDeparturesModal.querySelectorAll('.admin-view-close-modal-btn') : [];

        if (viewLateDeparturesBtn && lateDeparturesModal) {
            viewLateDeparturesBtn.addEventListener('click', () => {
                lateDeparturesModal.classList.add('show');
                document.body.style.overflow = 'hidden';
            });
        }

        if (lateModalCloseButtons.length > 0) {
            lateModalCloseButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    if (lateDeparturesModal) {
                        lateDeparturesModal.classList.remove('show');
                        document.body.style.overflow = '';
                    }
                });
            });
        }

        if (lateDeparturesModal) {
            window.addEventListener('click', (event) => {
                if (event.target === lateDeparturesModal) {
                    lateDeparturesModal.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && lateDeparturesModal.classList.contains('show')) {
                    lateDeparturesModal.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });
        }
    });
</script>
</body>
</html>