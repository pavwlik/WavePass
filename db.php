<?php
// db.php
$db_host = 'localhost';
$db_name = 'team01';
$db_user = 'uzivatel';
$db_pass = 'team01';

// DSN (Data Source Name)
// KĽÚČOVÁ ZMENA: pridanie charset=utf8mb4
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Zapnutie výnimiek pre chyby
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Predvolený spôsob načítania dát ako asociatívne pole
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Vypnutie emulácie prepared statements pre lepšiu bezpečnosť a výkon
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // V produkcii by ste nemali vypisovať $e->getMessage() priamo používateľovi
    // Logujte chybu a zobrazte všeobecnú chybovú správu
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please try again later or contact support.");
}
?>