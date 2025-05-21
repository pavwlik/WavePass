<?php
$host = 'localhost';
$db   = 'team01';
$user = 'team01';
$pass = '2Mingo@01';
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
    // In a real application, you might log this error and show a generic message to the user.
    die("Nelze se připojit k databázi. Zkuste to prosím později. Chyba: " . $e->getMessage());
}
?>
<head>
    <link rel="icon" href="imgs/logo.png" type="image/x-icon"> 
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <meta name="author" content="Pavel Bureš">
</head>
<body>
    <!-- Scroll to Top Button -->
    <button id="scrollToTopBtn" title="Go to top">
        <span class="material-symbols-outlined">arrow_upward</span>
    </button>
</body>
<script src="script.js"></script>

