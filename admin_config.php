<?php
$host = 'localhost';
$db = 'admin_telegram_bot_db';
$user = 'admin_telegram_bot_db';
$pass = 'Rkishor@36';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Telegram Bot Token for Admin
define('BOT_TOKEN', '7734857363:AAFqnNE8Z4zNo7DqlZqDgn0hchEeeXnphE0');

// Secret Admin Access Code (New & Secure)
define('ADMIN_ACCESS_CODE', 'K0nD0@Adm1n#927');
?>