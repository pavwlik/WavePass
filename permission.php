<?php
// permission.php

// --- ZAPNUTIE ZOBRAZOVANIA CHÝB PRE LADENIE ---
// TIETO RIADKY ODSTRÁŇTE ALEBO ZAKOMENTUJTE NA PRODUKČNOM SERVERI!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- KONIEC BLOKU PRE ZOBRAZOVANIE CHÝB ---

header('Content-Type: application/json');

// --- Database Configuration ---
$db_host = 'localhost';       // Váš server, zvyčajne localhost
$db_name = 'team01';          // Názov vašej databázy
$db_user = 'uzivatel';        // Vaše meno databázového používateľa
$db_pass = 'team01';          // Vaše heslo k databáze
// --- End Database Configuration ---

$response = ['status' => 'error', 'message' => 'Initial error.']; // Zmenená počiatočná správa pre lepšiu identifikáciu

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Táto chyba by sa mala zobraziť, ak je problém s pripojením k DB
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

$rfid_uid = trim($input['rfid_uid']);

// 1. Overenie RFID karty a získanie detailov spolu s info o používateľovi
$stmt_card = $pdo->prepare("
    SELECT 
        r.rfid_uid, 
        r.is_active, 
        r.userID,
        u.username,
        u.firstName,
        u.lastName
    FROM rfids r
    LEFT JOIN users u ON r.userID = u.userID
    WHERE r.rfid_uid = :rfid_uid
");
$stmt_card->bindParam(':rfid_uid', $rfid_uid);
$stmt_card->execute();
$card_data = $stmt_card->fetch();

if (!$card_data) {
    $response['message'] = "RFID card ({$rfid_uid}) not found in the system.";
    echo json_encode($response);
    exit;
}

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
    $response['message'] = "RFID card ({$rfid_uid}) is not assigned to any user.";
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
    $response['status'] = 'denied'; // Konzistentné so zamietnutím
    $final_message = "Access Denied for {$username_display}. RFID card ({$rfid_uid}) is inactive.";
} else {
    $log_result_value = 'granted';
    $response['status'] = 'granted'; // Konzistentné s povolením (namiesto 'success')
    if ($intended_log_type === 'entry') {
        $final_message = "Entry Granted for {$username_display}. Welcome!";
    } else {
        $final_message = "Exit Granted for {$username_display}. Goodbye!";
    }
}

// 5. Vloženie záznamu do tabuľky attendance_logs
try {
    $stmt_insert_log = $pdo->prepare("
        INSERT INTO attendance_logs (userID, logTime, logResult, logType) 
        VALUES (:userID, NOW(), :logResult, :logType)
    ");
    $stmt_insert_log->bindParam(':userID', $user_id, PDO::PARAM_INT);
    $stmt_insert_log->bindParam(':logResult', $log_result_value);
    $stmt_insert_log->bindParam(':logType', $intended_log_type);
    $stmt_insert_log->execute();
    
    // Ak bolo vloženie úspešné, nastavíme finálnu odpoveď
    $response['message'] = $final_message;
    $response['user'] = $username_display;
    $response['log_type'] = $intended_log_type;
    $response['log_result'] = $log_result_value;

} catch (PDOException $e) {
    // Ak zlyhá vloženie logu, upravíme status a message
    // $response['status'] už môže byť 'denied' alebo 'granted' z kroku 4,
    // ale ak vloženie zlyhá, celkový výsledok je 'error'.
    $response['status'] = 'error'; 
    $response['message'] = "Operation status: {$log_result_value} for user {$username_display}. However, failed to record attendance log: " . $e->getMessage();
    // Odstránime potenciálne zavádzajúce polia, ak logovanie zlyhalo
    unset($response['user']);
    unset($response['log_type']);
    unset($response['log_result']);
}

echo json_encode($response);

?>