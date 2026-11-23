<?php
declare(strict_types=1);

// Default XAMPP MySQL settings. For local/team overrides, copy
// config/database.example.php to config/database.local.php (gitignored).
$host = '127.0.0.1';
$db   = 'voting_system_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$localConfig = __DIR__ . '/database.local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Check config/database.php or config/database.local.php and ensure MySQL is running.');
}
