<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../config/database.php';

if(!isset($_SESSION['user_id'])){
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Не авторизован']);
    exit;
}

$stmt = $pdo->prepare('SELECT role FROM users WHERE id=?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$role = $user ? $user['role'] : 'user';

$from = isset($_GET['from_date']) ? $_GET['from_date'].' 00:00:00' : null;
$to = isset($_GET['to_date']) ? $_GET['to_date'].' 23:59:59' : null;
$type = isset($_GET['type']) ? $_GET['type'] : null;
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
$export = isset($_GET['export']) ? $_GET['export'] : null;

$sql = 'SELECT t.date, t.type, t.amount, c.name AS category_name, u.login AS created_by, t.comment
        FROM transactions t
        JOIN categories c ON t.category_id=c.id
        JOIN users u ON t.created_by=u.id
        WHERE 1=1';
$params = [];

if($from && $to){ $sql .= ' AND t.date BETWEEN ? AND ?'; $params[]=$from; $params[]=$to; }
if($type && in_array($type,['expense','income'])){ $sql .= ' AND t.type=?'; $params[]=$type; }
if($category_id){ $sql .= ' AND t.category_id=?'; $params[]=$category_id; }

$sql .= ' ORDER BY t.date ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_expense = 0;
$total_income = 0;
$category_sums = [];

foreach($transactions as $tr){
    if($tr['type']=='expense') $total_expense += $tr['amount'];
    if($tr['type']=='income') $total_income += $tr['amount'];

    if(!isset($category_sums[$tr['category_name']])) $category_sums[$tr['category_name']]=0;
    $category_sums[$tr['category_name']] += $tr['amount'];
}

if($export==='csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=report.csv');
    $output = fopen('php://output','w');
    fputcsv($output,['Дата','Тип','Сумма','Категория','Пользователь','Комментарий']);
    foreach($transactions as $tr){
        fputcsv($output,[$tr['date'],$tr['type'],$tr['amount'],$tr['category_name'],$tr['created_by'],$tr['comment']]);
    }
    fclose($output);
    exit;
}

echo json_encode([
    'success'=>true,
    'transactions'=>$transactions,
    'total_expense'=>$total_expense,
    'total_income'=>$total_income,
    'balance'=>$total_income-$total_expense,
    'category_sums'=>$category_sums
]);
