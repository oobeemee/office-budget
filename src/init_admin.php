<?php
// Этот скрипт будет автоматически создавать админа при запуске контейнера

$host = 'db';
$dbname = getenv('MYSQL_DATABASE');
$user = getenv('MYSQL_USER');
$pass = getenv('MYSQL_PASSWORD');

// Получаем данные админа из переменных окружения
$admin_login = getenv('admin');
$admin_password = getenv('admin_password');

if (!$admin_login || !$admin_password) {
    echo "Admin credentials not set in .env\n";
    exit(0);
}

try {
    // Ждем, пока БД запустится
    $connected = false;
    for ($i = 0; $i < 30; $i++) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $connected = true;
            break;
        } catch(PDOException $e) {
            sleep(1);
        }
    }
    
    if (!$connected) {
        throw new Exception("Could not connect to database");
    }
    
    // Создаем хеш пароля
    $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
    
    // Добавляем админа (если еще нет)
    $sql = "INSERT INTO users (login, password_hash, role, is_active) 
            VALUES (?, ?, 'admin', 1) 
            ON DUPLICATE KEY UPDATE 
            password_hash = VALUES(password_hash), role = 'admin'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$admin_login, $password_hash]);
    
    echo "Admin user '$admin_login' created/updated successfully!\n";
    
} catch(Exception $e) {
    echo "Error creating admin: " . $e->getMessage() . "\n";
}