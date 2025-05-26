<?php
$host = 'localhost';
$db   = 'team01';
$user = 'uzivatel';
$pass = 'team01';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // This is excellent!
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // In a real application, you would log this error and show a more user-friendly generic message.
    // For development, die() is okay to see the error immediately.
    // Consider more graceful error handling for production.
    error_log("Database Connection Error: " . $e->getMessage()); // Log the error
    die("Omlouváme se, nastala technická potíž. Zkuste to prosím později."); // User-friendly message
    // Original: die("Nelze se připojit k databázi. Zkuste to prosím později. Chyba: " . $e->getMessage());
}

// No HTML output below this line in db.php
?>




