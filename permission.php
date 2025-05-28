<?php
// permission.php

// --- ZAPNUTIE ZOBRAZOVANIA CHÝB PRE LADENIE ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- KONIEC BLOKU PRE ZOBRAZOVANIE CHÝB ---

header('Content-Type: application/json');

// --- DEFINÍCIA CESTY K STAVOVÉMU SÚBORU ---
define('RFID_ADDING_API_STATUS_FILE', __DIR__ . '/rfid_adding_api_status.txt'); // Súbor je v aktuálnom (koreňovom) adresári

// --- Database Configuration ---
$db_host = 'localhost';
$db_name = 'team01';
$db_user = 'uzivatel'; // Uistite sa, že tieto údaje sú správne
$db_pass = 'team01';   // Uistite sa, že tieto údaje sú správne
// --- End Database Configuration ---

$response = ['status' => 'error', 'message' => 'Initial error.', 'action_taken' => 'none'];

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
        r.RFID, -- Pridané pre prípadné referencie
        r.rfid_uid,
        r.is_active,
        r.userID,
        u.username,
        u.firstName,
        u.lastName
    FROM rfids r
    LEFT JOIN users u ON r.userID = u.userID
    WHERE r.rfid_uid = :rfid_uid_param
");
$stmt_card->bindParam(':rfid_uid_param', $rfid_uid_received);
$stmt_card->execute();
$card_data = $stmt_card->fetch();

// --- ZAČIATOK NOVEJ LOGIKY PRE PRIDÁVANIE KARIET ---
if (!$card_data) { // Karta nebola nájdená
    $is_api_for_adding_enabled = isRfidAddingApiEnabled();

    if ($is_api_for_adding_enabled) {
        // API je povolené, pokúsime sa pridať kartu
        try {
            $default_name = "Nová karta (auto-added): " . substr($rfid_uid_received, 0, 10) . "...";
            $card_type = 'Primary Access Card'; // Alebo iný default
            $is_active_new_card = 0; // Nové karty pridávame ako neaktívne

            $sql_insert_new_card = "INSERT INTO rfids (rfid_uid, name, card_type, is_active, userID)
                                    VALUES (:rfid_uid, :name, :card_type, :is_active, NULL)";
            $stmt_insert_new = $pdo->prepare($sql_insert_new_card);
            $stmt_insert_new->bindParam(':rfid_uid', $rfid_uid_received);
            $stmt_insert_new->bindParam(':name', $default_name);
            $stmt_insert_new->bindParam(':card_type', $card_type);
            $stmt_insert_new->bindParam(':is_active', $is_active_new_card, PDO::PARAM_INT);

            if ($stmt_insert_new->execute()) {
                $response['status'] = 'info'; // Info, nie granted/denied, lebo sa len pridala
                $response['message'] = "New RFID card ({$rfid_uid_received}) registered as inactive. Please assign and activate it in the admin panel.";
                $response['rfid_uid_processed'] = $rfid_uid_received;
                $response['action_taken'] = 'card_added_inactive';
                $response['inserted_id'] = $pdo->lastInsertId();
            } else {
                $response['message'] = "RFID card ({$rfid_uid_received}) not found, and failed to auto-register new card.";
                $response['action_taken'] = 'card_add_failed';
            }
        } catch (PDOException $e) {
            // Kontrola, či chyba nie je kvôli duplicitnému rfid_uid (ak by bol race condition alebo constraint)
            if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
                $response['status'] = 'info'; // Alebo error, podľa preferencie
                $response['message'] = "RFID card ({$rfid_uid_received}) was likely just added by another process or already exists (unique constraint).";
                $response['action_taken'] = 'card_add_conflict_or_exists';
            } else {
                $response['message'] = "RFID card ({$rfid_uid_received}) not found. DB error during auto-registration: " . $e->getMessage();
                $response['action_taken'] = 'card_add_db_error';
            }
        }
    } else {
        // API pre pridávanie je zakázané
        $response['message'] = "RFID card ({$rfid_uid_received}) not found in the system. Automatic registration is currently disabled.";
        $response['action_taken'] = 'card_not_found_adding_disabled';
    }
    echo json_encode($response);
    exit;
}
// --- KONIEC NOVEJ LOGIKY PRE PRIDÁVANIE KARIET ---

// Ak sme sa dostali sem, $card_data obsahuje údaje o existujúcej karte.
// Pokračujeme s pôvodnou logikou overovania prístupu.

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
    $response['status'] = 'denied'; // Karta existuje, ale nie je priradená = zamietnuté
    $response['message'] = "Access Denied. RFID card ({$rfid_uid_received}) is not assigned to any user.";
    $response['action_taken'] = 'access_check_unassigned_card';
    // Logujeme zamietnutý pokus pre existujúcu, ale nepriradenú kartu
    try {
        $stmt_log_unassigned = $pdo->prepare("
            INSERT INTO attendance_logs (userID, logTime, logResult, logType, rfid_uid_used)
            VALUES (NULL, NOW(), 'denied', 'unassigned_card', :rfid_uid_used_param) 
        "); // userID je NULL, logType je špecifický
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
    WHERE userID = :userID AND DATE(logTime) = CURDATE() AND logResult = 'granted'
");
$stmt_log_count->bindParam(':userID', $user_id, PDO::PARAM_INT);
$stmt_log_count->execute();
$scan_count_today = $stmt_log_count->fetchColumn();

$intended_log_type = ($scan_count_today % 2 == 0) ? 'entry' : 'exit';
$log_result_value = '';
$final_message = '';

// 4. Overenie, či je karta aktívna
if (!$is_card_active) {
    $log_result_value = 'denied';
    $response['status'] = 'denied';
    $final_message = "Access Denied for {$username_display}. RFID card ({$rfid_uid_received}) is inactive.";
    $response['action_taken'] = 'access_check_inactive_card';
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

// 5. Vloženie záznamu do tabuľky attendance_logs
try {
    $stmt_insert_log = $pdo->prepare("
        INSERT INTO attendance_logs (userID, logTime, logResult, logType, rfid_uid_used)
        VALUES (:userID, NOW(), :logResult, :logType, :rfid_uid_used_param)
    ");
    $stmt_insert_log->bindParam(':userID', $user_id, PDO::PARAM_INT);
    $stmt_insert_log->bindParam(':logResult', $log_result_value);
    $stmt_insert_log->bindParam(':logType', $intended_log_type);
    $stmt_insert_log->bindParam(':rfid_uid_used_param', $rfid_uid_received);
    $stmt_insert_log->execute();

    $response['message'] = $final_message;
    $response['user'] = $username_display;
    $response['log_type'] = $intended_log_type;
    $response['log_result'] = $log_result_value;
    $response['rfid_uid_processed'] = $rfid_uid_received;

} catch (PDOException $e) {
    // Ak zlyhá logovanie, stále vrátime pôvodný status (granted/denied), ale s chybou o logovaní
    $response['message_original_status'] = $final_message; // Uchováme pôvodnú správu
    $response['message'] = "Operation status: '{$log_result_value}' for user {$username_display}. However, failed to record attendance log: " . $e->getMessage();
    // Ponecháme status a iné relevantné informácie, aby klient vedel, či bol prístup povolený/zamietnutý
    // $response['status'] zostáva nastavený zhora
    $response['action_taken'] = ($response['action_taken'] ?? '') . '_log_failed';
}

echo json_encode($response);
?>