<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Доступ запрещен.";
    exit;
}

require_once __DIR__.'/../config/database.php';

$file_name = $_GET['file'] ?? null;
if (!$file_name || !preg_match('/^check_[a-f0-9\.]+\.(jpg|jpeg|png|pdf)$/i', $file_name)) {
    http_response_code(400);
    echo "Некорректное имя файла.";
    exit;
}

$file_path = __DIR__ . '/../uploads/' . $file_name;

if (!file_exists($file_path)) {
    http_response_code(404);
    echo "Файл не найден.";
    exit;
}

header('Content-Type: ' . mime_content_type($file_path));
header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;
