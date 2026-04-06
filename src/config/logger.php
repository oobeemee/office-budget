<?php

function writeLog($message, $level = 'ERROR') {
    $logDir = __DIR__ . '/../logs';
    $logFile = $logDir . '/server_errors.log';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $timestamp = date('Y-m-d H:i:s');

    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;

    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
?>
