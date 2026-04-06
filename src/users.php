<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__.'/config/database.php';

// --- Проверка прав доступа ---
// Эта страница доступна ТОЛЬКО администратору
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$current_user_role = ($stmt->fetch(PDO::FETCH_ASSOC))['role'] ?? 'user';

if ($current_user_role !== 'admin') {
    // Если не админ, отправляем на главную
    header('Location: /dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление пользователями — Учет офисного бюджета</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Офисный бюджет</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="/dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="/users.php">Пользователи</a>
                </li>
            </ul>
        </div>
        <a class="btn btn-outline-light btn-sm" href="logout.php">Выйти</a>
    </div>
</nav>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Управление пользователями</h1>
        <button class="btn btn-success" id="add-user-btn">Добавить пользователя</button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>Роль</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                        <!-- Пользователи будут загружены сюда -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для создания/редактирования пользователя -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalLabel">Новый пользователь</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="user-form">
                    <input type="hidden" id="user_id_input">
                    <div class="mb-3">
                        <label for="user_login" class="form-label">Логин</label>
                        <input type="text" class="form-control" id="user_login" required>
                    </div>
                    <div class="mb-3">
                        <label for="user_password" class="form-label">Пароль</label>
                        <input type="password" class="form-control" id="user_password">
                        <div class="form-text">Оставьте пустым, если не хотите менять пароль.</div>
                    </div>
                    <div class="mb-3">
                        <label for="user_role" class="form-label">Роль</label>
                        <select id="user_role" class="form-select">
                            <option value="user">Пользователь (user)</option>
                            <option value="operator">Оператор (operator)</option>
                            <option value="admin">Администратор (admin)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/users.js"></script>
</body>
</html>
