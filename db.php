<?php
$host = 'localhost';
$db   = 'team01';
$user = 'uzivatel'; // Ujistěte se, že tento uživatel má práva INSERT do tabulky system_logs
$pass = 'team01';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    // Pro uživatele zobrazit generickou zprávu, aby neviděl detaily chyby
    die("Omlouváme se, nastala technická potíž s připojením k databázi. Zkuste to prosím později.");
}

