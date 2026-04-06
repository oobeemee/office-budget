<?php

require_once __DIR__ . '/logger.php';

// --- 1. Читаем .env вручную (для поддержки XAMPP) ---
if (!getenv('MYSQL_DATABASE') && file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            putenv(trim($name) . "=" . trim($value));
        }
    }
}

// --- 2. Настройка подключения ---
// Если хост 'db' доступен — мы в Docker. Иначе localhost.
$host = (gethostbyname('db') !== 'db') ? 'db' : 'localhost';

// Берем переменные окружения
$db   = getenv('MYSQL_DATABASE');
$user = getenv('MYSQL_USER');
$pass = getenv('MYSQL_PASSWORD');

// Проверка на случай, если .env не прочитался
if (!$db) {
    die('Ошибка конфига: Не задано имя БД в .env');
}

$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
);

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Логируем ошибку
    if (function_exists('writeLog')) {
        writeLog("Ошибка БД: " . $e->getMessage(), 'CRITICAL');
    } else {
        error_log("Ошибка БД: " . $e->getMessage());
    }
    http_response_code(500);
    exit('Ошибка подключения к базе данных.');
}
?>
