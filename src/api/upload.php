<?php

function sendUploadResponse($data, $success = true, $http_code = 200) {
    header('Content-Type: application/json; charset=utf-8', true, $http_code);
    echo json_encode(['success' => $success, 'data' => $data]);
    exit;
}

session_start();
if (!isset($_SESSION['user_id'])) {
    sendUploadResponse(['message' => 'Только авторизованные пользователи могут загружать файлы.'], false, 403);
}

if (isset($_FILES['attachment'])) {
    $file = $_FILES['attachment'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        sendUploadResponse(['message' => 'Ошибка при загрузке файла. Код: ' . $file['error']], false, 400);
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        sendUploadResponse(['message' => 'Файл слишком большой. Максимальный размер - 5 МБ.'], false, 400);
    }

    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        sendUploadResponse(['message' => 'Недопустимый тип файла. Разрешены только JPG, PNG, PDF.'], false, 400);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe_filename = uniqid('check_', true) . '.' . strtolower($extension);

    $upload_dir = __DIR__ . '/../uploads/';
    $upload_path = $upload_dir . $safe_filename;

    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        sendUploadResponse(['message' => 'Файл успешно загружен.', 'path' => $safe_filename]);
    } else {
        sendUploadResponse(['message' => 'Не удалось переместить загруженный файл.'], false, 500);
    }
} else {
    sendUploadResponse(['message' => 'Файл не был отправлен.'], false, 400);
}
