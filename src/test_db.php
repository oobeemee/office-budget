<?php

// Включаем самый строгий режим показа ошибок. Это наша последняя надежда.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Тест подключения к базе данных</h1>";
echo "<p>Скрипт запущен... Пытаюсь подключить файл конфигурации...</p>";
echo "<hr>";

// --- Шаг 1: Проверка файла конфигурации ---
$config_path = __DIR__ . '/config/database.php';
if (!file_exists($config_path)) {
    echo "<h2><font color='red'>КРИТИЧЕСКАЯ ОШИБКА!</font></h2>";
    echo "<p>Файл конфигурации не найден по пути: <code>" . $config_path . "</code></p>";
    echo "<p>Проверьте, что папка 'config' и файл 'database.php' существуют и названы правильно.</p>";
    exit; // Останавливаем выполнение
}

// Если файл найден, подключаем его
require_once $config_path;
echo "<p><font color='green'>УСПЕХ!</font> Файл конфигурации <code>/config/database.php</code> найден и подключен.</p>";


// --- Шаг 2: Проверка подключения к MySQL ---
echo "<p>Пытаюсь подключиться к MySQL с данными:</p>";
echo "<ul>";
echo "<li>Хост: " . htmlspecialchars($host) . "</li>";
echo "<li>Имя базы: " . htmlspecialchars($db_name) . "</li>";
echo "<li>Пользователь: " . htmlspecialchars($username) . "</li>";
// Пароль мы, конечно, не выводим
echo "</ul>";

try {
    // Переменная $pdo должна создаваться в подключенном файле database.php
    if (!isset($pdo)) {
        throw new Exception("Переменная \$pdo не была создана в файле конфигурации. Проверьте его содержимое.");
    }

    // Выполняем простой запрос, чтобы проверить, что соединение живое
    $pdo->query("SELECT 1");

    echo "<h2><font color='green'>УСПЕХ! Соединение с базой данных установлено!</font></h2>";

} catch (PDOException $e) {
    // Эта ошибка возникает, если данные для подключения (логин, пароль, имя БД) неверные
    echo "<h2><font color='red'>ОШИБКА PDO!</font></h2>";
    echo "<p>Не удалось подключиться к базе данных. Сервер вернул ошибку:</p>";
    echo "<pre style='background-color: #eee; padding: 10px; border: 1px solid #ccc;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p><b>Что делать:</b> Дважды проверьте логин, пароль и имя базы данных в файле <code>/config/database.php</code>. Скорее всего, ошибка именно там.</p>";
    exit;

} catch (Exception $e) {
    echo "<h2><font color='red'>ОШИБКА PHP!</font></h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}


// --- Шаг 3: Проверка наличия пользователя admin ---
echo "<hr>";
echo "<p>Пытаюсь найти пользователя 'admin' в таблице 'users'...</p>";

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch();

    if ($user) {
        echo "<h3><font color='green'>УСПЕХ! Пользователь 'admin' найден в базе данных!</font></h3>";
        echo "<p>Система готова к работе. Проблема входа может быть связана с кэшем или сессиями.</p>";
    } else {
        echo "<h3><font color='orange'>ВНИМАНИЕ! Пользователь 'admin' НЕ НАЙДЕН!</font></h3>";
        echo "<p><b>Что делать:</b> Зайдите в phpMyAdmin, выберите вашу базу, перейдите во вкладку SQL и выполните команду для создания администратора (я присылал ее ранее).</p>";
    }

} catch (Exception $e) {
    echo "<h2><font color='red'>ОШИБКА!</font></h2>";
    echo "<p>Не удалось выполнить запрос к таблице 'users'. Ошибка:</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p>Возможно, таблицы не были созданы. Проверьте их наличие в phpMyAdmin.</p>";
}

?>
