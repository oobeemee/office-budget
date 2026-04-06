<?php
// /api/categories.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

session_start();
if(!isset($_SESSION['user_id'])){
    echo json_encode(['success'=>false,'message'=>'Неавторизованный пользователь']);
    exit;
}

require_once __DIR__.'/../config/database.php';

$stmt = $pdo->prepare('SELECT role FROM users WHERE id=?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$role = $user['role'] ?? 'user';

// Проверка прав доступа: только operator или admin
if(!in_array($role, ['operator','admin'])){
    echo json_encode(['success'=>false,'message'=>'Нет прав для управления категориями']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    // POST — добавление/редактирование категории
    if($_SERVER['REQUEST_METHOD'] === 'POST' && $input){
        $id = intval($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $type = $input['type'] ?? 'expense';
        $description = trim($input['description'] ?? '');
        $is_active = intval($input['is_active'] ?? 1);

        if($name === ''){
            echo json_encode(['success'=>false,'message'=>'Название категории обязательно']);
            exit;
        }

        if($id > 0){
            $stmt = $pdo->prepare('UPDATE categories SET name=?, type=?, description=?, is_active=? WHERE id=?');
            $stmt->execute([$name, $type, $description, $is_active, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO categories (name, type, description, is_active, created_at) VALUES (?,?,?,?,NOW())');
            $stmt->execute([$name, $type, $description, $is_active]);
        }
        echo json_encode(['success'=>true]);
        exit;
    }

    // GET или POST без тела — возвращаем все категории
    $stmt = $pdo->query('SELECT id, name, type, description, is_active FROM categories ORDER BY name ASC');
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($categories);
    exit;

} catch(PDOException $e){
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    exit;
}

