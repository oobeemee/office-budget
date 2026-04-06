<?php

function sendUserResponse($data, $success = true, $http_code = 200) {
    if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8', true, $http_code); }
    echo json_encode(['success' => $success, 'data' => $data]);
    exit;
}

set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    session_start();
    if (!isset($_SESSION['user_id'])) { sendUserResponse(['message' => 'Неавторизованный пользователь.'], false, 401); }

    require_once __DIR__.'/../config/database.php';

    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $role = ($stmt->fetch(PDO::FETCH_ASSOC))['role'] ?? 'user';
    if ($role !== 'admin') {
        sendUserResponse(['message' => 'Доступ запрещен.'], false, 403);
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($method) {
        case 'GET':
            $stmt = $pdo->query("SELECT id, login, role FROM users ORDER BY id ASC");
            sendUserResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'POST':
            $id = intval($input['id'] ?? 0);
            $login = trim($input['login'] ?? '');
            $password = $input['password'] ?? '';
            $user_role = $input['role'] ?? 'user';

            if (empty($login)) { sendUserResponse(['message' => 'Логин не может быть пустым.'], false, 400); }
            if (!in_array($user_role, ['user', 'operator', 'admin'])) { sendUserResponse(['message' => 'Недопустимая роль.'], false, 400); }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ? AND id != ?");
            $stmt->execute([$login, $id]);
            if ($stmt->fetch()) { sendUserResponse(['message' => 'Пользователь с таким логином уже существует.'], false, 400); }

            if ($id > 0) {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET login = ?, role = ?, password_hash = ? WHERE id = ?");
                    $stmt->execute([$login, $user_role, $hashed_password, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET login = ?, role = ? WHERE id = ?");
                    $stmt->execute([$login, $user_role, $id]);
                }
                sendUserResponse(['message' => 'Пользователь обновлен.']);
            } else {
                if (empty($password)) { sendUserResponse(['message' => 'Пароль обязателен для нового пользователя.'], false, 400); }
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (login, password_hash, role) VALUES (?, ?, ?)");
                $stmt->execute([$login, $hashed_password, $user_role]);
                sendUserResponse(['message' => 'Пользователь создан.']);
            }
            break;

        case 'DELETE':
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) { sendUserResponse(['message' => 'Некорректный ID.'], false, 400); }
            if ($id === $_SESSION['user_id']) { sendUserResponse(['message' => 'Вы не можете удалить самого себя.'], false, 403); }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            sendUserResponse(['message' => 'Пользователь удален.']);
            break;
    }
} catch (Throwable $e) {
    error_log($e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    sendUserResponse(['message' => 'Произошла ошибка на сервере: ' . $e->getMessage()], false, 500);
}

