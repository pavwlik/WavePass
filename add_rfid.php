<?php
header('Content-Type: application/json'); // Odpovídáme JSONem
require_once 'db.php'; // Načte $pdo proměnnou

// Přijímáme data metodou POST ve formátu JSON
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Pouze POST metoda je povolena.']);
    exit;
}

if (!isset($input['rfid_uid']) || empty(trim($input['rfid_uid']))) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Chybí parametr rfid_uid nebo je prázdný.']);
    exit;
}

$rfid_uid = trim($input['rfid_uid']);

// Zkontrolujeme, zda karta s tímto rfid_uid již neexistuje
// Pokud bys chtěl povolit duplicity, tento krok přeskoč.
// Pro jednoduchost přidáme kontrolu existence.
try {
    $stmt_check = $pdo->prepare("SELECT RFID FROM rfids WHERE rfid_uid = :rfid_uid");
    $stmt_check->execute(['rfid_uid' => $rfid_uid]);
    if ($stmt_check->fetch()) {
        http_response_code(200); // OK, ale karta již existuje
        echo json_encode([
            'status' => 'info', 
            'message' => 'RFID karta s tímto UID již existuje v databázi.',
            'rfid_uid' => $rfid_uid
        ]);
        exit;
    }
} catch (PDOException $e) {
    error_log("Database Check Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Chyba při kontrole databáze.']);
    exit;
}


// Vložení nové karty
// Dle tvé struktury:
// name: může být NULL, dáme nějaké výchozí nebo necháme NULL
// card_type: má výchozí 'Primary Access Card', můžeme nechat na DB nebo explicitně nastavit
// created_at: má výchozí current_timestamp()
// is_active: nastavíme na 0 (neaktivní)
// rfid_uid: UID karty
// userID: může být NULL pro novou, nepřirazenou kartu

$default_name = "Nová karta: " . substr($rfid_uid, 0, 8); // Příklad jména
$card_type = 'Primary Access Card'; // Nebo nech na DB default
$is_active = 0; // Dle požadavku neaktivní

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
            'message' => 'RFID karta úspěšně uložena jako neaktivní.',
            'rfid_uid_added' => $rfid_uid,
            'inserted_id' => $pdo->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Nepodařilo se uložit RFID kartu.']);
    }

} catch (PDOException $e) {
    error_log("Database Insert Error: " . $e->getMessage());
    http_response_code(500);
    // Zkontroluj, zda chyba není kvůli duplicitnímu rfid_uid, pokud máš UNIQUE constraint
    if ($e->getCode() == 23000) { // Integrity constraint violation
         echo json_encode([
            'status' => 'error', 
            'message' => 'Chyba: RFID karta s tímto UID již pravděpodobně existuje (pokud je rfid_uid unikátní).',
            'detail' => $e->getMessage()
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Chyba databáze při ukládání.', 'detail' => $e->getMessage()]);
    }
}
?>