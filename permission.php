<?php
// permission.php

// --- ZAPNUTIE ZOBRAZOVANIA CHÝB PRE LADENIE ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- KONIEC BLOKU PRE ZOBRAZOVANIE CHÝB ---

header('Content-Type: application/json');

// --- DEFINÍCIA CESTY K STAVOVÉMU SÚBORU ---
define('RFID_ADDING_API_STATUS_FILE', __DIR__ . '/rfid_adding_api_status.txt');

// --- Database Configuration ---
$db_host = 'localhost';
$db_name = 'team01';
$db_user = 'uzivatel';
$db_pass = 'team01';
// --- End Database Configuration ---

$response = [
    'status' => 'error',
    'message' => 'Initial error.',
    'action_taken' => 'none',
    'trigger_buzzer' => false // Predvolene je buzzer vypnutý
];

// --- FUNKCIA NA KONTROLU STAVU RFID API (pre pridávanie) ---
function isRfidAddingApiEnabled() {
    if (!defined('RFID_ADDING_API_STATUS_FILE')) {
         error_log("Constant RFID_ADDING_API_STATUS_FILE not defined in permission.php. RFID adding via API denied.");
         return false;
    }
    if (!file_exists(RFID_ADDING_API_STATUS_FILE)) {
        error_log("RFID Adding API status file not found (" . RFID_ADDING_API_STATUS_FILE . "). RFID adding via API denied.");
        return false;
    }
    $status = trim(@file_get_contents(RFID_ADDING_API_STATUS_FILE));
    if ($status === false) {
        error_log("Could not read RFID Adding API status file (" . RFID_ADDING_API_STATUS_FILE . "). RFID adding via API denied.");
        return false;
    }
    return $status === 'enabled';
}
// --- KONIEC FUNKCIE NA KONTROLU STAVU RFID API ---

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $response['message'] = 'Database connection error: ' . $e->getMessage();
    $response['details'] = 'Please check database credentials and if the MySQL server is running.';
    error_log("permission.php - Database Connection Error: " . $e->getMessage()); // Logovanie chyby pripojenia
    echo json_encode($response);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Invalid JSON input: ' . json_last_error_msg();
    echo json_encode($response);
    exit;
}

if (!$input || !isset($input['rfid_uid']) || empty(trim($input['rfid_uid']))) {
    $response['message'] = 'RFID UID not provided or empty in JSON payload.';
    echo json_encode($response);
    exit;
}

$rfid_uid_received = trim($input['rfid_uid']);

// 1. Overenie RFID karty a získanie detailov
$stmt_card = $pdo->prepare("
    SELECT
        r.RFID,
        r.rfid_uid,
        r.name AS rfid_name, -- Pridané pre lepšiu identifikáciu karty
        r.is_active,
        r.userID,
        u.username,
        u.firstName,
        u.lastName,
        u.roleID -- Pridané pre kontrolu, či neodchádza posledný admin napr.
    FROM rfids r
    LEFT JOIN users u ON r.userID = u.userID
    WHERE r.rfid_uid = :rfid_uid_param
");
$stmt_card->bindParam(':rfid_uid_param', $rfid_uid_received);
$stmt_card->execute();
$card_data = $stmt_card->fetch();

// --- LOGIKA PRE PRIDÁVANIE KARIET A LOGOVANIE ---
if (!$card_data) { // Karta nebola nájdená
    $is_api_for_adding_enabled = isRfidAddingApiEnabled();
    $log_type_event = 'unknown_card_scan'; // Predvolený typ pre neznámu kartu

    if ($is_api_for_adding_enabled) {
        $pdo->beginTransaction();
        try {
            $default_name = "Nová karta (auto): " . substr($rfid_uid_received, 0, 8) . "...";
            $card_type = 'Primary Access Card';
            $is_active_new_card = 0;

            $sql_insert_new_card = "INSERT INTO rfids (rfid_uid, name, card_type, is_active, userID)
                                    VALUES (:rfid_uid, :name, :card_type, :is_active, NULL)";
            $stmt_insert_new = $pdo->prepare($sql_insert_new_card);
            $stmt_insert_new->bindParam(':rfid_uid', $rfid_uid_received);
            $stmt_insert_new->bindParam(':name', $default_name);
            $stmt_insert_new->bindParam(':card_type', $card_type);
            $stmt_insert_new->bindParam(':is_active', $is_active_new_card, PDO::PARAM_INT);

            if ($stmt_insert_new->execute()) {
                $new_rfid_db_id = $pdo->lastInsertId();
                $log_type_event = 'auto_registered'; // Aktualizujeme typ logu

                // Záznam do attendance_logs pre novo pridanú kartu
                // Použijeme logResult 'info', ak ho ENUM podporuje, inak 'denied'
                $log_result_for_new_card = 'info'; // Alebo 'denied'
                // Uistite sa, že ENUM pre logType podporuje 'auto_registered'
                // a pre logResult podporuje 'info'
                try {
                    $stmt_log_new_card = $pdo->prepare("
                        INSERT INTO attendance_logs (userID, logTime, logResult, logType, rfid_uid_used)
                        VALUES (NULL, NOW(), :logResult, :logType, :rfid_uid_used_param)
                    ");
                    $stmt_log_new_card->bindParam(':logResult', $log_result_for_new_card);
                    $stmt_log_new_card->bindParam(':logType', $log_type_event);
                    $stmt_log_new_card->bindParam(':rfid_uid_used_param', $rfid_uid_received);
                    $stmt_log_new_card->execute();
                } catch (PDOException $e_log) {
                     error_log("Failed to log auto-registered card for UID {$rfid_uid_received}: " . $e_log->getMessage());
                     // Pokračujeme, aj keď log zlyhá, hlavné je, že karta bola pridaná
                }

                $pdo->commit();
                $response['status'] = 'info';
                $response['message'] = "New RFID card ({$rfid_uid_received}) registered as inactive and event logged. Please assign and activate it in the admin panel.";
                $response['rfid_uid_processed'] = $rfid_uid_received;
                $response['action_taken'] = 'card_added_inactive_and_logged';
                $response['inserted_rfid_id'] = $new_rfid_db_id;
            } else {
                $pdo->rollBack();
                $response['message'] = "RFID card ({$rfid_uid_received}) not found, and failed to auto-register new card.";
                $response['action_taken'] = 'card_add_failed';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
                $response['status'] = 'info';
                $response['message'] = "RFID card ({$rfid_uid_received}) likely already exists (unique constraint).";
                $response['action_taken'] = 'card_add_conflict_or_exists';
                 // Aj keď už existuje, skúsime zalogovať pokus o sken
                try {
                    $stmt_log_exists = $pdo->prepare("
                        INSERT INTO attendance_logs (userID, logTime, logResult, logType, rfid_uid_used)
                        VALUES (NULL, NOW(), 'denied', 'unknown_card_scan', :rfid_uid_used_param)
                    "); // Používame 'unknown_card_scan', lebo sme ju nenašli pri prvom selecte
                    $stmt_log_exists->bindParam(':rfid_uid_used_param', $rfid_uid_received);
                    $stmt_log_exists->execute();
                } catch (PDOException $e_log_exists) {
                     error_log("Failed to log existing unknown card scan for UID {$rfid_uid_received}: " . $e_log_exists->getMessage());
                }

            } else {
                $response['message'] = "RFID card ({$rfid_uid_received}) not found. DB error during auto-registration: " . $e->getMessage();
                $response['action_taken'] = 'card_add_db_error';
            }
        }
    } else {
        // API pre pridávanie je zakázané, zalogujeme pokus o použitie neznámej karty
        try {
            // Uistite sa, že ENUM pre logType podporuje 'unknown_card_scan'
            $stmt_log_unknown = $pdo->prepare("
                INSERT INTO attendance_logs (userID, logTime, logResult, logType, rfid_uid_used)
                VALUES (NULL, NOW(), 'denied', 'unknown_card_scan', :rfid_uid_used_param)
            ");
            $stmt_log_unknown->bindParam(':rfid_uid_used_param', $rfid_uid_received);
            $stmt_log_unknown->execute();
            $response['message'] = "RFID card ({$rfid_uid_received}) not found. Automatic registration disabled. Scan attempt logged.";
            $response['action_taken'] = 'card_not_found_adding_disabled_scan_logged';
        } catch (PDOException $e_log_unknown) {
            error_log("Failed to log unknown card scan (API disabled) for UID {$rfid_uid_received}: " . $e_log_unknown->getMessage());
            $response['message'] = "RFID card ({$rfid_uid_received}) not found. Automatic registration disabled. Failed to log scan attempt.";
            $response['action_taken'] = 'card_not_found_adding_disabled_log_failed';
        }
    }
    echo json_encode($response);
    exit;
}
// --- KONIEC LOGIKY PRE PRIDÁVANIE KARIET ---

// Ak sme sa dostali sem, $card_data obsahuje údaje o existujúcej karte.
$user_id = $card_data['userID'];
$is_card_active = (bool)$card_data['is_active'];
$username_display = 'Unknown User';
if (!empty($card_data['firstName']) || !empty($card_data['lastName'])) {
    $username_display = trim(($card_data['firstName'] ?? '') . ' ' . ($card_data['lastName'] ?? ''));
} elseif (!empty($card_data['username'])) {
    $username_display = $card_data['username'];
}

// 2. Overenie, či je karta priradená používateľovi
if (empty($user_id)) {
    $response['status'] = 'denied';
    $response['message'] = "Access Denied. RFID card ({$rfid_uid_received} - " . htmlspecialchars($card_data['rfid_name'] ?? 'N/A') . ") is not assigned to any user.";
    $response['action_taken'] = 'access_check_unassigned_card';
    try {
        // Uistite sa, že ENUM pre logType podporuje 'unassigned_card_attempt'
        $stmt_log_unassigned = $pdo->prepare("
            INSERT INTO attendance_logs (userID, logTime, logResult, logType, rfid_uid_used)
            VALUES (NULL, NOW(), 'denied', 'unassigned_card_attempt', :rfid_uid_used_param)
        ");
        $stmt_log_unassigned->bindParam(':rfid_uid_used_param', $rfid_uid_received);
        $stmt_log_unassigned->execute();
    } catch (PDOException $e_log) {
        error_log("Failed to log unassigned card attempt for UID {$rfid_uid_received}: " . $e_log->getMessage());
    }
    echo json_encode($response);
    exit;
}

// 3. Určenie, či ide o príchod alebo odchod pre používateľa dnes
$stmt_log_count = $pdo->prepare("
    SELECT COUNT(*) as scan_count
    FROM attendance_logs
    WHERE userID = :userID_param AND DATE(logTime) = CURDATE() AND logResult = 'granted'
");
$stmt_log_count->bindParam(':userID_param', $user_id, PDO::PARAM_INT);
$stmt_log_count->execute();
$scan_count_today = $stmt_log_count->fetchColumn();
$stmt_log_count->closeCursor();

$intended_log_type = ($scan_count_today % 2 == 0) ? 'entry' : 'exit';
$log_result_value = '';
$final_message = '';

// 4. Overenie, či je karta aktívna
if (!$is_card_active) {
    $log_result_value = 'denied';
    $response['status'] = 'denied';
    $final_message = "Access Denied for {$username_display}. RFID card ({$rfid_uid_received}) is inactive.";
    $response['action_taken'] = 'access_check_inactive_card';
    // $intended_log_type zostáva 'entry' alebo 'exit' pre logovanie pokusu
} else {
    $log_result_value = 'granted';
    $response['status'] = 'granted';
    if ($intended_log_type === 'entry') {
        $final_message = "Entry Granted for {$username_display}. Welcome!";
    } else {
        $final_message = "Exit Granted for {$username_display}. Goodbye!";
    }
    $response['action_taken'] = 'access_check_success';
}

// 5. Vloženie záznamu do tabuľky attendance_logs pre existujúce, priradené karty
try {
    $stmt_insert_log = $pdo->prepare("
        INSERT INTO attendance_logs (userID, logTime, logResult, logType, rfid_uid_used)
        VALUES (:userID_param, NOW(), :logResult_param, :logType_param, :rfid_uid_used_param)
    ");
    $stmt_insert_log->bindParam(':userID_param', $user_id, PDO::PARAM_INT);
    $stmt_insert_log->bindParam(':logResult_param', $log_result_value);
    $stmt_insert_log->bindParam(':logType_param', $intended_log_type); // Použijeme 'entry' alebo 'exit'
    $stmt_insert_log->bindParam(':rfid_uid_used_param', $rfid_uid_received);
    $stmt_insert_log->execute();

    // --- LOGIKA PRE ZISTENIE POSLEDNÉHO ODCHÁDZAJÚCEHO ---
    if ($log_result_value == 'granted' && $intended_log_type == 'exit') {
        // Po úspešnom zaznamenaní odchodu skontrolujeme, či ešte niekto zostal
        // Tento dotaz by mal byť efektívnejší
        $stmt_still_present = $pdo->prepare("
            SELECT EXISTS (
                SELECT 1
                FROM attendance_logs al1
                WHERE al1.userID != :current_user_id 
                  AND DATE(al1.logTime) = CURDATE()
                  AND al1.logResult = 'granted'
                  AND NOT EXISTS ( -- Skontrolujeme, či neexistuje novší odchod pre daného používateľa
                      SELECT 1
                      FROM attendance_logs al2
                      WHERE al2.userID = al1.userID
                        AND DATE(al2.logTime) = CURDATE()
                        AND al2.logType = 'exit'
                        AND al2.logResult = 'granted'
                        AND al2.logTime > al1.logTime -- Hľadáme odchod po poslednom príchode
                  )
                  AND al1.logType = 'entry' -- Zaujímajú nás len tí, čo prišli
                GROUP BY al1.userID
                HAVING MAX(al1.logTime) > IFNULL((SELECT MAX(al_exit.logTime) FROM attendance_logs al_exit WHERE al_exit.userID = al1.userID AND al_exit.logType = 'exit' AND al_exit.logResult = 'granted' AND DATE(al_exit.logTime) = CURDATE()), '0000-00-00')
                 -- Vyššie uvedené HAVING overí, že posledný príchod je novší ako posledný odchod, alebo že odchod neexistuje
            ) as anyone_still_present;
        ");
        $stmt_still_present->bindParam(':current_user_id', $user_id, PDO::PARAM_INT);
        $stmt_still_present->execute();
        $still_present_data = $stmt_still_present->fetch(PDO::FETCH_ASSOC);
        $stmt_still_present->closeCursor();

        if ($still_present_data && (int)$still_present_data['anyone_still_present'] === 0) {
            // Ak je `anyone_still_present` 0, tento používateľ bol posledný
            $response['trigger_buzzer'] = true;
            $final_message .= " You were the last one, please lock up!";
        }
    }
    // --- KONIEC LOGIKY PRE ZISTENIE POSLEDNÉHO ODCHÁDZAJÚCEHO ---

    $response['message'] = $final_message;
    $response['user'] = $username_display;
    $response['log_type'] = $intended_log_type;
    $response['log_result'] = $log_result_value;
    $response['rfid_uid_processed'] = $rfid_uid_received;

} catch (PDOException $e) {
    $response['message_original_status'] = $final_message ?? 'N/A';
    $response['message'] = "Operation status: '{$log_result_value}' for user {$username_display}. However, failed to record attendance log: " . $e->getMessage();
    $response['action_taken'] = ($response['action_taken'] ?? 'access_log_issue') . '_log_failed';
    error_log("permission.php - Error logging attendance for user {$user_id} (UID: {$rfid_uid_received}): " . $e->getMessage());
}

echo json_encode($response);
?>