<?php

function sendJsonResponse($data, $success = true, $http_code = 200) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true, $http_code);
    }
    echo json_encode(['success' => $success, 'data' => $data]);
    exit;
}

set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    session_start();

    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['message' => 'Неавторизованный пользователь.'], false, 401);
    }
    
    require_once __DIR__.'/../config/database.php';

    $stmt = $pdo->prepare('SELECT role FROM users WHERE id=?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $role = $user['role'] ?? 'user';

    if (!in_array($role, ['operator', 'admin'])) {
        sendJsonResponse(['message' => 'Недостаточно прав для этого действия.'], false, 403);
    }

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $stmt = $pdo->query('SELECT id, name, type, description, is_active FROM categories ORDER BY name ASC');
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonResponse($categories);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                 sendJsonResponse(['message' => 'Некорректный формат данных.'], false, 400);
            }
            
            $id = intval($input['id'] ?? 0);
            $name = trim($input['name'] ?? '');
            $type = $input['type'] ?? 'expense';
            $description = trim($input['description'] ?? '');
            $is_active = isset($input['is_active']) ? intval($input['is_active']) : 1;

            if (empty($name)) {
                sendJsonResponse(['message' => 'Название категории не может быть пустым.'], false, 400);
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE categories SET name=?, type=?, description=?, is_active=? WHERE id=?');
                $stmt->execute([$name, $type, $description, $is_active, $id]);
                sendJsonResponse(['message' => 'Категория успешно обновлена']);
            } else {
                $stmt = $pdo->prepare('INSERT INTO categories (name, type, description, is_active, created_at) VALUES (?,?,?,?,NOW())');
                $stmt->execute([$name, $type, $description, $is_active]);
                $newId = $pdo->lastInsertId();
                sendJsonResponse(['message' => 'Категория успешно создана', 'id' => $newId]);
            }
            break;

        default:
            sendJsonResponse(['message' => 'Метод не поддерживается.'], false, 405);
            break;
    }

} catch (Throwable $e) {
    error_log($e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    sendJsonResponse(['message' => 'Произошла критическая ошибка на сервере. Пожалуйста, попробуйте позже.'], false, 500);
}









