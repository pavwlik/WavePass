<?php
header('Content-Type: application/json'); // Odpovídáme JSONem
require_once 'db.php'; // Načte $pdo proměnnou

define('RFID_ADDING_API_STATUS_FILE', __DIR__ . '/rfid_adding_api_status.txt'); // Súbor je v aktuálnom (koreňovom) adresári

// --- FUNKCIA NA KONTROLU STAVU RFID API ---
function isRfidAddingApiEnabledGlobalCheck() {
    if (!defined('RFID_ADDING_API_STATUS_FILE')) {
         error_log("Constant RFID_ADDING_API_STATUS_FILE not defined in add_rfid.php. API access denied.");
         return false; // Bezpečnostný fallback
    }
    if (!file_exists(RFID_ADDING_API_STATUS_FILE)) {
        // Ak súbor neexistuje, považujeme API za vypnuté, kým ho admin explicitne nezapne cez panel.
        // Toto je bezpečnejšie ako defaultne povoliť.
        error_log("RFID Adding API status file not found (" . RFID_ADDING_API_STATUS_FILE . "). API access denied.");
        return false;
    }
    $status = trim(@file_get_contents(RFID_ADDING_API_STATUS_FILE));
    if ($status === false) {
        error_log("Could not read RFID Adding API status file (" . RFID_ADDING_API_STATUS_FILE . "). API access denied.");
        return false; // Chyba pri čítaní súboru
    }
    return $status === 'enabled';
}
// --- KONIEC FUNKCIE NA KONTROLU STAVU RFID API ---


// --- KONTROLA, ČI JE API POVOLENÉ ---
if (!isRfidAddingApiEnabledGlobalCheck()) {
    http_response_code(503); // Service Unavailable (alebo 403 Forbidden)
    echo json_encode(['status' => 'error', 'message' => 'Adding new RFID cards via API is currently disabled by the administrator.']);
    exit;
}
// --- KONIEC KONTROLY POVOLENIA API ---


// Přijímáme data metodou POST ve formátu JSON
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Pouze POST metoda je povolena.']);
    exit;
}

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Neplatný JSON formát v tele požiadavky.', 'json_error' => json_last_error_msg()]);
    exit;
}


if (!isset($input['rfid_uid']) || empty(trim($input['rfid_uid']))) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Chybí parametr rfid_uid nebo je prázdný.']);
    exit;
}

$rfid_uid = trim($input['rfid_uid']);

// Zkontrolujeme, zda karta s tímto rfid_uid již neexistuje
try {
    $stmt_check = $pdo->prepare("SELECT RFID FROM rfids WHERE rfid_uid = :rfid_uid");
    $stmt_check->execute(['rfid_uid' => $rfid_uid]);
    if ($stmt_check->fetch()) {
        // Karta už existuje. Pre Python skript to môže byť stále "úspech", že karta je známa.
        // Ak chcete, aby to bola chyba, zmeňte http_response_code a správu.
        http_response_code(200); // OK
        echo json_encode([
            'status' => 'info',
            'message' => 'RFID karta s týmto UID (' . htmlspecialchars($rfid_uid) . ') už existuje v databáze.',
            'rfid_uid' => $rfid_uid,
            'action' => 'already_exists'
        ]);
        exit;
    }
} catch (PDOException $e) {
    error_log("Database Check Error (add_rfid.php): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Chyba pri kontrole databáze.']);
    exit;
}


// Vložení nové karty
// name: môže byť NULL, dáme nejaké výchozí alebo necháme NULL
// card_type: defaultne 'Primary Access Card' (ak DB má default), inak nastavíme
// is_active: nastavíme na 0 (neaktivní) ako bolo požadované
// userID: NULL pre novú, nepriradenú kartu

$default_name = "Nová karta (API): " . substr($rfid_uid, 0, 12) . "...";
$card_type = 'Primary Access Card'; // Môžete zmeniť alebo nechať na DB default
$is_active = 0; // Podľa požiadavky - pridaná ako neaktívna

try {
    $sql = "INSERT INTO rfids (name, card_type, rfid_uid, is_active, userID)
            VALUES (:name, :card_type, :rfid_uid, :is_active, NULL)";
    $stmt = $pdo->prepare($sql);

    $params = [
        ':name' => $default_name,
        ':card_type' => $card_type,
        ':rfid_uid' => $rfid_uid,
        ':is_active' => $is_active
    ];

    if ($stmt->execute($params)) {
        http_response_code(201); // Created
        echo json_encode([
            'status' => 'success',
            'message' => 'RFID karta úspešne uložená ako neaktívna.',
            'rfid_uid_added' => $rfid_uid,
            'inserted_id' => $pdo->lastInsertId(),
            'action' => 'added_new'
        ]);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Database Insert Failed (add_rfid.php): " . ($errorInfo[2] ?? "Unknown PDO error"));
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Nepodařilo se uložit RFID kartu.', 'db_error' => $errorInfo[2] ?? "Unknown"]);
    }

} catch (PDOException $e) {
    error_log("Database Insert Error (add_rfid.php): " . $e->getMessage());
    http_response_code(500);
    // Zkontroluj, zda chyba není kvůli duplicitnímu rfid_uid, pokud máš UNIQUE constraint
    if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) ) { // Integrity constraint violation (MySQL error 1062 for duplicate entry)
         http_response_code(409); // Conflict
         echo json_encode([
            'status' => 'error',
            'message' => 'Chyba: RFID karta s týmto UID (' . htmlspecialchars($rfid_uid) . ') už pravdepodobne existuje (porušenie unikátnosti).',
            'detail' => $e->getMessage(),
            'action' => 'duplicate_entry_error'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Chyba databáze při ukládání.', 'detail' => $e->getMessage()]);
    }
}
?>