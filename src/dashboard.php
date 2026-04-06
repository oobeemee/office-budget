<?php

session_start();

if(!isset($_SESSION['user_id'])){
    header('Location: /login.php');
    exit;
}

require_once __DIR__.'/config/database.php';

$stmt = $pdo->prepare('SELECT id, login, role FROM users WHERE id=?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$role = $user['role'] ?? 'user';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — Учет офисного бюджета</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .btn-control-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <!-- <a class="navbar-brand" href="#">Офисный бюджет</a> -->
        <div class="d-flex align-items-center">
            <span class="navbar-text me-3">Привет, <?=htmlspecialchars($user['login'])?> (<?=htmlspecialchars($role)?>)</span>
            <a class="btn btn-outline-light btn-sm" href="logout.php">Выйти</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <!-- 1. КНОПКИ УПРАВЛЕНИЯ (Теперь в сетке) -->
    <div class="row g-4 mb-4">
        
        <?php if($role !== 'admin'): ?>
            <div class="col-md-6 col-lg-3">
                <button class="btn btn-primary w-100 h-100 btn-control-panel" onclick="openNewRequestModal()">
                    <span class="me-2">➕</span> Подать заявку
                </button>
            </div>
        <?php endif; ?>

        <?php if($role=='operator'||$role=='admin'): ?>
            <div class="col-md-6 col-lg-3">
                <button class="btn btn-primary border w-100 h-100 btn-control-panel" onclick="directAddTransaction()">
                    <span class="me-2">➕</span> Добавить операцию
                </button>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <button class="btn btn-primary border w-100 h-100 btn-control-panel" onclick="editCategory()">
                    <span class="me-2">🏷️</span> Добавить категорию
                </button>
            </div>

            <div class="col-md-6 col-lg-3">
                <button class="btn btn-primary border w-100 h-100 btn-control-panel" onclick="openCategoryManager()">
                    <span class="me-2">⚙️</span> Категории
                </button>
            </div>
            <?php if($role=='admin'): ?>
                <div class="col-md-6 col-lg-3">
                    <button class="btn btn-primary w-100 h-100 btn-control-panel" onclick="openUsersManager()">
                        <span class="me-2">👥</span> Пользователи
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- 2. ВИДЖЕТ ЗАЯВОК (Только для админов/операторов) -->
    <?php if($role=='operator'||$role=='admin'): ?>
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-danger h-100" id="requests-widget" style="cursor: pointer;">
                <div class="card-body">
                    <h5 class="card-title">Заявки на утверждение</h5>
                    <p class="card-text fs-4" id="pending-requests-count">0</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 3. ФИЛЬТРЫ -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filters-form" class="row g-3">
                <div class="col-md-3"><label>Дата от</label><input type="date" class="form-control" id="from_date"></div>
                <div class="col-md-3"><label>Дата до</label><input type="date" class="form-control" id="to_date"></div>
                <div class="col-md-3"><label>Тип операции</label><select class="form-select" id="type"><option value="">Все</option><option value="expense">Расход</option><option value="income">Пополнение</option></select></div>
                <div class="col-md-3"><label>Категория</label><select class="form-select" id="category_id"><option value="">Все</option></select></div>
                <div class="col-md-3"><label>Пользователь</label><select class="form-select" id="user_filter"><option value="">Все</option></select></div>
                <div class="col-12 text-end mt-2">
                    <button type="submit" class="btn btn-primary">Применить фильтры</button>
                    <button type="button" id="open-export-modal-btn" class="btn btn-success">Экспорт</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. СВОДНЫЕ КАРТОЧКИ (Суммы) -->
    <div class="row mb-4 g-4">
        <div class="col-md-4">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <h5 class="card-title">Сумма пополнений</h5>
                    <p class="card-text fs-4" id="total_income">0</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-danger h-100">
                <div class="card-body">
                    <h5 class="card-title">Сумма расходов</h5>
                    <p class="card-text fs-4" id="total_expense">0</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-primary h-100">
                <div class="card-body">
                    <h5 class="card-title">Текущий баланс</h5>
                    <p class="card-text fs-4" id="balance">0</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. ГРАФИКИ -->
    <div class="row mb-4 g-4">
        <div class="col-md-6">
            <div class="card p-3 h-100">
                <h5>Статистика по категориям</h5>
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3 h-100">
                <h5>Динамика по месяцам</h5>
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- 6. ТАБЛИЦА -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="transactions-table">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="id">ID <span></span></th>
                            <th class="sortable" data-sort="date">Дата <span></span></th>
                            <th>Чек</th>
                            <th class="sortable" data-sort="type">Тип <span></span></th>
                            <th class="sortable" data-sort="amount">Сумма <span></span></th>
                            <th class="sortable" data-sort="category_name">Категория <span></span></th>
                            <th>Комментарий</th>
                            <th>Способ оплаты</th>
                            <th>Контрагент</th>
                            <th class="sortable" data-sort="user_login">Пользователь <span></span></th>
                            <?php if($role=='operator'||$role=='admin'): ?><th>Действия</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <nav id="pagination-container" aria-label="Page navigation"></nav>
        </div>
    </div>
</div>

<!-- ================================================================= -->
<!-- МОДАЛЬНЫЕ ОКНА (БЕЗ ИЗМЕНЕНИЙ) -->
<!-- ================================================================= -->

<!-- 1. Новая заявка на расход -->
<div class="custom-modal-overlay" id="newRequestModal">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h5 class="modal-title">Новая заявка на расход</h5>
            <button type="button" class="custom-modal-close">&times;</button>
        </div>
        <div class="custom-modal-body">
            <form id="new-request-form">
                <div class="mb-3"><label>Сумма</label><input type="number" step="0.01" min="0.01" class="form-control" id="request_amount" required></div>
                <div class="mb-3"><label>На что были потрачены средства?</label><textarea class="form-control" id="request_description" required></textarea></div>
                <button type="submit" class="btn btn-primary">Отправить</button>
            </form>
        </div>
    </div>
</div>

<!-- 2. Заявки на утверждение -->
<div class="custom-modal-overlay" id="approveRequestsModal">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h5 class="modal-title">Заявки на утверждение</h5>
            <button type="button" class="custom-modal-close">&times;</button>
        </div>
        <div class="custom-modal-body">
            <div id="pending-requests-list"></div>
        </div>
    </div>
</div>

<?php if($role=='operator'||$role=='admin'): ?>
<!-- 3. Управление категориями -->
<div class="custom-modal-overlay" id="managementModal">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h5 class="modal-title">Управление категориями</h5>
            <button type="button" class="custom-modal-close">&times;</button>
        </div>
        <div class="custom-modal-body">
            <div id="category-list-container" class="list-group"></div>
        </div>
    </div>
</div>

<!-- 4. Редактирование категории -->
<div class="custom-modal-overlay" id="categoryModal">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h5 class="modal-title" id="categoryModalLabel">Добавить/Редактировать категорию</h5>
            <button type="button" class="custom-modal-close">&times;</button>
        </div>
        <div class="custom-modal-body">
            <form id="category-form">
                <input type="hidden" id="category_id_input">
                <div class="mb-3"><label>Название</label><input type="text" id="category_name" class="form-control" required></div>
                <div class="mb-3"><label>Тип</label><select id="category_type" class="form-select"><option value="expense">Расход</option><option value="income">Пополнение</option></select></div>
                <div class="mb-3"><label>Описание</label><textarea id="category_description" class="form-control"></textarea></div>
                <div class="mb-3"><label>Активна</label><select id="category_active" class="form-select"><option value="1">Да</option><option value="0">Нет</option></select></div>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </form>
        </div>
    </div>
</div>

<!-- 5. Редактирование транзакции -->
<div class="custom-modal-overlay" id="transactionModal">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h5 class="modal-title" id="transactionModalLabel">Транзакция</h5>
            <button type="button" class="custom-modal-close">&times;</button>
        </div>
        <div class="custom-modal-body">
            <form id="transaction-form" enctype="multipart/form-data">
                <input type="hidden" id="transaction_id">
                <input type="hidden" id="request_id_input">
                <input type="hidden" id="existing_attachment_path">
                <input type="hidden" id="transaction_requester_id"> 
                
                <!-- === ИЗМЕНЕНО ЗДЕСЬ === -->
                <div class="mb-3"><label>Дата и время</label><input type="datetime-local" id="transaction_date" class="form-control" required></div>
                
                <div class="mb-3"><label>Тип</label><select id="transaction_type" class="form-select" required><option value="expense">Расход</option><option value="income">Пополнение</option></select></div>
                <div class="mb-3"><label>Сумма</label><input type="number" step="0.01" min="0.01" id="transaction_amount" class="form-control" required></div>
                <div class="mb-3"><label>Категория</label><select id="transaction_category" class="form-select" required></select></div>
                <div class="mb-3"><label>Комментарий</label><textarea id="transaction_comment" class="form-control"></textarea></div>
                <div class="mb-3"><label>Способ оплаты (опционально)</label><input type="text" id="transaction_payment_method" class="form-control"></div>
                <div class="mb-3"><label>Контрагент (опционально)</label><input type="text" id="transaction_counterparty" class="form-control"></div>
                <div class="mb-3"><label for="transaction_attachment" class="form-label">Прикрепить чек (JPG, PNG, PDF)</label><input class="form-control" type="file" id="transaction_attachment"><div id="attachment-info" class="mt-2"></div></div>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if($role=='admin'): ?>
<!-- 6. Управление пользователями -->
<div class="custom-modal-overlay" id="usersManagementModal">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h5 class="modal-title">Управление пользователями</h5>
            <button type="button" class="custom-modal-close">&times;</button>
        </div>
        <div class="custom-modal-body">
            <div class="d-flex justify-content-end align-items-center mb-3">
                <button class="btn btn-success" id="add-user-btn">Добавить пользователя</button>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead><tr><th>ID</th><th>Логин</th><th>Роль</th><th>Действия</th></tr></thead>
                    <tbody id="users-table-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 7. Редактирование пользователя -->
<div class="custom-modal-overlay" id="userEditModal">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h5 class="modal-title" id="userEditModalLabel">Новый пользователь</h5>
            <button type="button" class="custom-modal-close">&times;</button>
        </div>
        <div class="custom-modal-body">
            <form id="user-edit-form">
                <input type="hidden" id="edit_user_id_input">
                <div class="mb-3"><label for="edit_user_login" class="form-label">Логин</label><input type="text" class="form-control" id="edit_user_login" required></div>
                <div class="mb-3"><label for="edit_user_password" class="form-label">Пароль</label><input type="password" class="form-control" id="edit_user_password"><div class="form-text" id="edit_password_help_text">Оставьте пустым, если не хотите менять пароль.</div></div>
                <div class="mb-3"><label for="edit_user_role" class="form-label">Роль</label><select id="edit_user_role" class="form-select"><option value="user">Пользователь (user)</option><option value="operator">Оператор (operator)</option><option value="admin">Администратор (admin)</option></select></div>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 8. Модальное окно экспорта -->
<div class="custom-modal-overlay" id="exportModal">
    <div class="custom-modal" style="max-width: 500px;">
        <div class="custom-modal-header">
            <h5 class="modal-title">Экспорт транзакций</h5>
            <button type="button" class="custom-modal-close">&times;</button>
        </div>
        <div class="custom-modal-body text-center">
            <p>Выберите формат для экспорта данных с учетом примененных фильтров.</p>
            <div class="d-grid gap-2 col-8 mx-auto mt-4">
                <button class="btn btn-lg btn-success export-btn" data-format="xlsx">
                    <span>📊</span> Экспорт в XLSX (Excel)
                </button>
                <button class="btn btn-lg btn-secondary export-btn" data-format="csv">
                    <span>📄</span> Экспорт в CSV
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.userRole = '<?=htmlspecialchars($role)?>';</script>
<script src="assets/js/main.js"></script>

</body>
</html>

























