<?php

declare(strict_types=1);

use App\Controllers\BotController;

require __DIR__ . '/vendor/autoload.php';

$databaseConfigFile = __DIR__ . '/config/database.php';
$telegramConfigFile = __DIR__ . '/config/telegram.php';

if (!file_exists($databaseConfigFile) || !file_exists($telegramConfigFile)) {
    http_response_code(500);
    echo 'Missing config files. Create config/database.php and config/telegram.php from *.example.php';
    exit;
}

$databaseConfig = require $databaseConfigFile;
$telegramConfig = require $telegramConfigFile;

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=%s', $databaseConfig['host'], $databaseConfig['dbname'], $databaseConfig['charset']),
    $databaseConfig['user'],
    $databaseConfig['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

return [
    'pdo' => $pdo,
    'telegramConfig' => $telegramConfig,
    'botControllerClass' => BotController::class,
];
