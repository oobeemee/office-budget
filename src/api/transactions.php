<?php

require_once __DIR__.'/../config/logger.php';

function sendJsonResponse($data, $success = true, $http_code = 200) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true, $http_code);
    }
    echo json_encode(['success' => $success, 'data' => $data]);
    exit;
}

set_error_handler(function($severity, $message, $file, $line) {
    // Логируем ошибки PHP (варнинги и т.д.)
    if (!(error_reporting() & $severity)) { return; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['message' => 'Неавторизованный пользователь.'], false, 401);
    }
    
    require_once __DIR__.'/../config/database.php';

    $current_user_id = $_SESSION['user_id'];
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);

    // Получаем роль
    $stmt_role = $pdo->prepare('SELECT role FROM users WHERE id=?');
    $stmt_role->execute([$current_user_id]);
    $role = ($stmt_role->fetch(PDO::FETCH_ASSOC))['role'] ?? 'user';
    $is_writer = in_array($role, ['operator', 'admin']);

    // --- CSV ЭКСПОРТ ---
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        // ... (Тут код экспорта CSV, если он используется через GET, оставляем для совместимости) ...
        // Но основной экспорт у нас теперь через export.php
        exit; 
    }
    
    // --- POST ЗАПРОСЫ ---
    if ($method === 'POST') {
        
        // 1. СОХРАНЕНИЕ ТРАНЗАКЦИИ (Create/Update)
        if (isset($input['amount']) && isset($input['date'])) {
            if (!$is_writer) { sendJsonResponse(['message' => 'Нет прав.'], false, 403); }

            $id = intval($input['id'] ?? 0);
            $date = $input['date'];
            $type = $input['type'] ?? 'expense';
            $amount = floatval($input['amount']);
            $category_id = intval($input['category_id'] ?? 0);
            $comment = trim($input['comment'] ?? '');
            $payment_method = trim($input['payment_method'] ?? '');
            $counterparty = trim($input['counterparty'] ?? '');
            $attachment_path = trim($input['attachment_path'] ?? '') ?: null;

            if ($amount <= 0) { sendJsonResponse(['message' => 'Сумма должна быть больше 0.'], false, 400); }
            
            $category_id_db = ($category_id > 0) ? $category_id : null;

            if ($id > 0) {
                $sql = "UPDATE transactions SET date=?, type=?, amount=?, category_id=?, comment=?, payment_method=?, counterparty=?, attachment_path=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$date, $type, $amount, $category_id_db, $comment, $payment_method, $counterparty, $attachment_path, $id]);
                sendJsonResponse(['message' => 'Обновлено', 'id' => $id]);
            } else {
                $creator_id = !empty($input['requester_id']) ? intval($input['requester_id']) : $current_user_id;
                $sql = "INSERT INTO transactions (date, type, amount, category_id, comment, payment_method, counterparty, attachment_path, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$date, $type, $amount, $category_id_db, $comment, $payment_method, $counterparty, $attachment_path, $creator_id]);
                sendJsonResponse(['message' => 'Создано', 'id' => $pdo->lastInsertId()]);
            }
        } 
        
        // 2. ЗАГРУЗКА СПИСКА (Read)
        else {
            $params = [];
            $conditions = [];

            // Если просят одну транзакцию по ID
            if (!empty($input['id'])) {
                 $conditions[] = "t.id = ?"; $params[] = $input['id'];
            } 
            else {
                // Фильтры
                if (!empty($input['from_date'])) { $conditions[] = "t.date >= ?"; $params[] = $input['from_date']; }
                if (!empty($input['to_date'])) { $conditions[] = "t.date <= ?"; $params[] = $input['to_date']; }
                if (!empty($input['type'])) { $conditions[] = "t.type = ?"; $params[] = $input['type']; }
                if (!empty($input['category_id'])) { $conditions[] = "t.category_id = ?"; $params[] = $input['category_id']; }
                
                // ИСПРАВЛЕНО: используем created_by вместо user_id
                if (!empty($input['user_id'])) { $conditions[] = "t.created_by = ?"; $params[] = $input['user_id']; }
            }

            $where_clause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

            // А) Считаем общее количество (для пагинации)
            $total_query = "SELECT COUNT(t.id) FROM transactions t" . $where_clause;
            $total_stmt = $pdo->prepare($total_query);
            $total_stmt->execute($params);
            $total_records = $total_stmt->fetchColumn();

            // Б) Получаем ВСЕ данные для графиков (без LIMIT)
            // ИСПРАВЛЕНО: JOIN по t.created_by
            $all_query = "SELECT t.type, t.amount, t.date, c.name as category_name 
                          FROM transactions t 
                          LEFT JOIN categories c ON t.category_id = c.id 
                          LEFT JOIN users u ON t.created_by = u.id" . $where_clause . " ORDER BY t.date DESC";
            
            $stmtAll = $pdo->prepare($all_query);
            $stmtAll->execute($params);
            $allTransactions = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

            // В) Получаем страницу для таблицы (с LIMIT)
            $sortable_columns = ['id', 'date', 'type', 'amount', 'category_name', 'user_login'];
            $sort_by = in_array($input['sortBy'] ?? '', $sortable_columns) ? $input['sortBy'] : 'date';
            if ($sort_by == 'category_name') $sort_by = 'c.name';
            if ($sort_by == 'user_login') $sort_by = 'u.login';
            
            $sort_order = strtoupper($input['sortOrder'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            $page = intval($input['page'] ?? 1);
            $limit = intval($input['limit'] ?? 15);
            $offset = ($page - 1) * $limit;

            // ИСПРАВЛЕНО: JOIN по t.created_by
            $query = "SELECT t.id, t.date, t.type, t.amount, t.comment, t.payment_method, t.counterparty, t.attachment_path, c.name as category_name, u.login as user_login, t.category_id 
                      FROM transactions t 
                      LEFT JOIN categories c ON t.category_id = c.id 
                      LEFT JOIN users u ON t.created_by = u.id" . $where_clause .
                      " ORDER BY " . $sort_by . " " . $sort_order . " LIMIT ? OFFSET ?";

            $stmt = $pdo->prepare($query);
            
            $param_index = 1;
            foreach($params as $value) { $stmt->bindValue($param_index++, $value); }
            $stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
            $stmt->bindValue($param_index, $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $paginatedTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'transactions' => $paginatedTransactions, 
                'all_transactions' => $allTransactions,
                'total' => $total_records
            ]);
        }
    } 
    // --- DELETE ЗАПРОСЫ ---
    elseif ($method === 'DELETE') {
        if (!$is_writer) { sendJsonResponse(['message' => 'Нет прав.'], false, 403); }
        $id = intval($input['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
            $stmt->execute([$id]);
            sendJsonResponse(['message' => 'Удалено.']);
        }
    }

} catch (PDOException $e) {
    writeLog("Ошибка в transactions.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'data' => ['message' => 'Ошибка базы данных.']]);
} catch (Exception $e) {
    writeLog("Системная ошибка: " . $e->getMessage());
    echo json_encode(['success' => false, 'data' => ['message' => 'Ошибка сервера.']]);
}
?>











