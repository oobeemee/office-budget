<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Метод не поддерживается');
    }

    if (!isset($input['login']) || !isset($input['password'])) {
        throw new Exception('Логин и пароль обязательны');
    }

    $login = trim($input['login']);
    $password = $input['password'];

    $stmt = $pdo->prepare('SELECT id, login, password_hash, role, is_active FROM users WHERE login=?');
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success'=>false,'message'=>'Неверный логин или пароль']);
        exit;
    }

    if ($user['is_active'] != 1) {
        echo json_encode(['success'=>false,'message'=>'Пользователь заблокирован']);
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        echo json_encode(['success'=>false,'message'=>'Неверный логин или пароль']);
        exit;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];

    echo json_encode(['success'=>true,'message'=>'Успешный вход']);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>






