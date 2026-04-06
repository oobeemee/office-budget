<?php
// Подключаем автозагрузчик Composer (поднимаемся на уровень вверх из папки api)
require_once __DIR__ . '/../vendor/autoload.php';

// Подключаем необходимые классы из библиотеки PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Подключаем конфигурацию базы данных
require_once __DIR__ . '/../config/database.php';

// Проверка подключения к БД
if (!$pdo) {
    http_response_code(500);
    die("Критическая ошибка: Не удалось установить соединение с базой данных.");
}

session_start();

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Access Denied');
}

// Получаем параметры фильтрации
$params = json_decode(file_get_contents('php://input'), true);
// Если параметры не пришли в body (например при GET запросе), берем их из $_GET, если нужно
// Но в твоем JS коде отправляется POST c JSON, так что $params будет заполнен.
$format = $_GET['format'] ?? 'csv';

// --- ПОДГОТОВКА ЗАПРОСА К БД ---
// Убрали user_id из выборки и фильтров, чтобы избежать ошибки "Column not found"
$query = "SELECT t.id, t.date, t.type, t.amount, c.name as category_name, t.comment, t.payment_method, t.counterparty 
          FROM transactions t 
          LEFT JOIN categories c ON t.category_id = c.id 
          WHERE 1=1";
$queryParams = [];

if (!empty($params['from_date'])) { 
    $query .= " AND t.date >= ?"; 
    $queryParams[] = $params['from_date']; 
}
if (!empty($params['to_date'])) { 
    $query .= " AND t.date <= ?"; 
    $queryParams[] = $params['to_date']; 
}
if (!empty($params['type'])) { 
    $query .= " AND t.type = ?"; 
    $queryParams[] = $params['type']; 
}
if (!empty($params['category_id'])) { 
    $query .= " AND t.category_id = ?"; 
    $queryParams[] = $params['category_id']; 
}

$query .= " ORDER BY t.date DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($queryParams);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    writeLog("Ошибка экспорта (SQL): " . $e->getMessage()); // <--- Добавили
    http_response_code(500);
    die("Ошибка базы данных: " . $e->getMessage());
}


// =================================================================
// ЭКСПОРТ В XLSX (Excel)
// =================================================================

if ($format === 'xlsx') {
    $spreadsheet = new Spreadsheet();
    
    // ---------------------------------------------------------
    // ЛИСТ 1: Движение средств (Транзакции)
    // ---------------------------------------------------------
    $sheetTx = $spreadsheet->getActiveSheet();
    $sheetTx->setTitle('Движение средств');

    // Заголовки таблицы
    $headers = ['ID', 'Дата', 'Тип', 'Сумма', 'Категория', 'Комментарий', 'Способ оплаты', 'Контрагент'];
    $sheetTx->fromArray($headers, NULL, 'A1');
    
    // Стили заголовка (Синий фон, белый жирный текст)
    $headerStyle = [
        'font' => [
            'bold' => true, 
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID, 
            'startColor' => ['rgb' => '4F81BD']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ],
    ];
    $sheetTx->getStyle('A1:H1')->applyFromArray($headerStyle);

    $data = [];
    $totalIncome = 0;
    $totalExpense = 0;
    
    // Массив для сбора статистики по категориям (для второго листа)
    $statsCategories = []; 

    foreach ($transactions as $tx) {
        $amount = (float)$tx['amount'];
        
        $data[] = [
            $tx['id'],
            $tx['date'],
            $tx['type'] === 'income' ? 'Пополнение' : 'Расход',
            $amount,
            $tx['category_name'],
            $tx['comment'],
            $tx['payment_method'],
            $tx['counterparty']
        ];

        // Подсчет итогов и сбор статистики
        if ($tx['type'] === 'income') {
            $totalIncome += $amount;
        } else {
            $totalExpense += $amount;
            
            // Собираем данные по категориям (ТОЛЬКО РАСХОДЫ) для аналитики
            $catName = $tx['category_name'] ?: 'Без категории';
            if (!isset($statsCategories[$catName])) {
                $statsCategories[$catName] = 0;
            }
            $statsCategories[$catName] += $amount;
        }
    }

    // Вывод данных транзакций
    if (!empty($data)) {
        $sheetTx->fromArray($data, NULL, 'A2');
    }
    
    $lastRow = count($transactions) + 2; // +1 заголовок, +1 следующая строка

    // Блок итогов
    $sheetTx->setCellValue("G" . ($lastRow + 1), 'Итого приход:');
    $sheetTx->setCellValue("H" . ($lastRow + 1), $totalIncome);
    
    $sheetTx->setCellValue("G" . ($lastRow + 2), 'Итого расход:');
    $sheetTx->setCellValue("H" . ($lastRow + 2), $totalExpense);
    
    $sheetTx->setCellValue("G" . ($lastRow + 3), 'БАЛАНС:');
    $sheetTx->setCellValue("H" . ($lastRow + 3), $totalIncome - $totalExpense);
    
    // Форматирование итогов
    $sheetTx->getStyle("G" . ($lastRow + 1) . ":H" . ($lastRow + 3))->getFont()->setBold(true);
    
    // Формат валюты (Рубли)
    $currencyFormat = '#,##0.00" ₽"';
    // Колонка D (Сумма) в таблице
    $sheetTx->getStyle('D2:D' . ($lastRow - 1))->getNumberFormat()->setFormatCode($currencyFormat);
    // Ячейки итогов
    $sheetTx->getStyle('H' . ($lastRow + 1) . ':H' . ($lastRow + 3))->getNumberFormat()->setFormatCode($currencyFormat);
    
    // Автоширина колонок
    foreach (range('A', 'H') as $columnID) {
        $sheetTx->getColumnDimension($columnID)->setAutoSize(true);
    }

    // ---------------------------------------------------------
    // ЛИСТ 2: Расходы по категориям (Таблица + Доли)
    // ---------------------------------------------------------
    if (!empty($statsCategories)) {
        $sheetCat = $spreadsheet->createSheet();
        $sheetCat->setTitle('Расходы по категориям');

        // Заголовки отчета аналитики
        $sheetCat->setCellValue('A1', 'Категория');
        $sheetCat->setCellValue('B1', 'Сумма расходов');
        $sheetCat->setCellValue('C1', 'Доля (%)');
        
        // Применяем тот же стиль заголовков
        $sheetCat->getStyle('A1:C1')->applyFromArray($headerStyle);
        
        // Устанавливаем ширину колонок вручную для красоты
        $sheetCat->getColumnDimension('A')->setWidth(35);
        $sheetCat->getColumnDimension('B')->setWidth(20);
        $sheetCat->getColumnDimension('C')->setWidth(15);

        $row = 2;
        // Сортируем категории от большей суммы к меньшей
        arsort($statsCategories);

        foreach ($statsCategories as $catName => $amount) {
            // Вычисляем долю (процент)
            $percent = ($totalExpense > 0) ? ($amount / $totalExpense) : 0;

            $sheetCat->setCellValue('A' . $row, $catName);
            $sheetCat->setCellValue('B' . $row, $amount);
            $sheetCat->setCellValue('C' . $row, $percent); 
            $row++;
        }

        // Форматирование таблицы аналитики
        $lastCatRow = $row - 1;
        // Колонка Сумм - валюта
        $sheetCat->getStyle('B2:B' . $lastCatRow)->getNumberFormat()->setFormatCode($currencyFormat);
        // Колонка Долей - проценты (0.00%)
        $sheetCat->getStyle('C2:C' . $lastCatRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        
        // Добавляем строку "ВСЕГО" внизу таблицы
        $sheetCat->setCellValue('A' . $row, 'ВСЕГО:');
        $sheetCat->setCellValue('B' . $row, $totalExpense);
        $sheetCat->setCellValue('C' . $row, 1); // 1 = 100%
        
        // Стили для строки ВСЕГО
        $sheetCat->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true);
        $sheetCat->getStyle('B'.$row)->getNumberFormat()->setFormatCode($currencyFormat);
        $sheetCat->getStyle('C'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

        // --- ДОБАВЛЯЕМ КРУГОВУЮ ДИАГРАММУ ---
        $catCount = count($statsCategories);
        
        // Метки (Названия категорий)
        $xAxisTickValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'Расходы по категориям\'!$A$2:$A$' . ($catCount + 1), null, $catCount)
        ];
        // Данные (Суммы)
        $dataSeriesValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\'Расходы по категориям\'!$B$2:$B$' . ($catCount + 1), null, $catCount)
        ];

        // Создаем серию данных
        $series = new DataSeries(
            DataSeries::TYPE_PIECHART, 
            null, 
            range(0, count($dataSeriesValues) - 1), 
            [], 
            $xAxisTickValues, 
            $dataSeriesValues
        );
        
        $layout = new \PhpOffice\PhpSpreadsheet\Chart\Layout();
        $layout->setShowVal(true);     // Показывать значения
        $layout->setShowPercent(true); // Показывать проценты

        $plotArea = new PlotArea($layout, [$series]);
        $title = new Title('Структура расходов');
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        
        $chart = new Chart(
            'chart_cat', 
            $title, 
            $legend, 
            $plotArea, 
            true, 
            0, 
            null, 
            null
        );
        
        // Размещаем график справа от таблицы (ячейки E2 - M20)
        $chart->setTopLeftPosition('E2');
        $chart->setBottomRightPosition('M20');
        
        $sheetCat->addChart($chart);
    }
    
    // Возвращаем фокус на первый лист перед сохранением
    $spreadsheet->setActiveSheetIndex(0);

    // Создаем Writer и включаем поддержку графиков
    $writer = new Xlsx($spreadsheet);
    $writer->setIncludeCharts(true);
    
    $filename = 'report_' . date('Y-m-d_H-i') . '.xlsx';
    
    // Заголовки для скачивания файла
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit;

} else { 
    // =================================================================
    // ЭКСПОРТ В CSV (Резервный вариант)
    // =================================================================
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d_H-i') . '.csv"');
    $output = fopen('php://output', 'w');
    
    // BOM для корректного отображения кириллицы в Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['ID', 'Дата', 'Тип', 'Сумма', 'Категория', 'Комментарий', 'Способ оплаты', 'Контрагент']);
    
    foreach ($transactions as $tx) {
        $tx['type'] = $tx['type'] === 'income' ? 'Пополнение' : 'Расход';
        fputcsv($output, [
            $tx['id'], 
            $tx['date'], 
            $tx['type'], 
            $tx['amount'], 
            $tx['category_name'], 
            $tx['comment'], 
            $tx['payment_method'], 
            $tx['counterparty']
        ]);
    }
    fclose($output);
    exit;
}
?>






