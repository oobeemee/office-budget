<?php
function sendRequestResponse($data, $success = true, $http_code = 200) {
    if (!headers_sent()) { header('Content-Type: application/json; charset=utf-8', true, $http_code); }
    echo json_encode(['success' => $success, 'data' => $data]);
    exit;
}

set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        sendRequestResponse(['message' => 'Неавторизованный пользователь.'], false, 401);
    }
    
    require_once __DIR__.'/../config/database.php';
    $current_user_id = $_SESSION['user_id'];
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    $stmt_role = $pdo->prepare('SELECT role FROM users WHERE id=?');
    $stmt_role->execute([$current_user_id]);
    $role = ($stmt_role->fetch(PDO::FETCH_ASSOC))['role'] ?? 'user';
    $is_manager = in_array($role, ['operator', 'admin']);
    
    switch ($method) {
        case 'GET':
            if ($is_manager) {

                $sql = "SELECT r.id, r.amount, r.description, r.created_at, u.login as requester_login, r.requester_id 
                        FROM transaction_requests r
                        JOIN users u ON r.requester_id = u.id
                        WHERE r.status = 'pending' ORDER BY r.created_at ASC";
                // =============================================
                $stmt = $pdo->query($sql);
                sendRequestResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
            } else {
                sendRequestResponse([]);
            }
            break;
        case 'POST':
            $action = $input['action'] ?? null;
            if ($action === 'create') {
                $amount = floatval($input['amount'] ?? 0);
                $description = trim($input['description'] ?? '');
                if ($amount <= 0 || empty($description)) {
                    sendRequestResponse(['message' => 'Сумма и описание обязательны.'], false, 400);
                }
                $stmt = $pdo->prepare("INSERT INTO transaction_requests (requester_id, amount, description) VALUES (?, ?, ?)");
                $stmt->execute([$current_user_id, $amount, $description]);
                sendRequestResponse(['message' => 'Заявка успешно отправлена на рассмотрение.']);

            } elseif ($is_manager && $action === 'approve') {
                $request_id = intval($input['request_id'] ?? 0);
                $transaction_id = intval($input['transaction_id'] ?? 0);
                if ($request_id <= 0 || $transaction_id <= 0) {
                    sendRequestResponse(['message' => 'Неверные данные для одобрения заявки.'], false, 400);
                }
                $update_stmt = $pdo->prepare("UPDATE transaction_requests SET status = 'approved', approver_id = ?, processed_at = NOW(), transaction_id = ? WHERE id = ? AND status = 'pending'");
                $update_stmt->execute([$current_user_id, $transaction_id, $request_id]);
                
                if ($update_stmt->rowCount() > 0) {
                    sendRequestResponse(['message' => 'Заявка одобрена.']);
                } else {
                    sendRequestResponse(['message' => 'Заявка не найдена или уже была обработана.'], false, 404);
                }

            } elseif ($is_manager && $action === 'reject') {
                $request_id = intval($input['request_id'] ?? 0);
                $reason = trim($input['reason'] ?? 'Причина не указана.');
                
                $stmt = $pdo->prepare("UPDATE transaction_requests SET status = 'rejected', approver_id = ?, rejection_reason = ?, processed_at = NOW() WHERE id = ? AND status = 'pending'");
                $stmt->execute([$current_user_id, $reason, $request_id]);
                
                if ($stmt->rowCount() > 0) {
                    sendRequestResponse(['message' => 'Заявка отклонена.']);
                } else {
                    sendRequestResponse(['message' => 'Заявка не найдена или уже была обработана.'], false, 404);
                }
            } else {
                sendRequestResponse(['message' => 'Недопустимое действие.'], false, 403);
            }
            break;
    }
} catch (Throwable $e) {
    error_log($e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    sendRequestResponse(['message' => 'Произошла ошибка на сервере: ' . $e->getMessage()], false, 500);
}

