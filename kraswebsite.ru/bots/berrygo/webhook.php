<?php

declare(strict_types=1);

use App\Controllers\BotController;

['pdo' => $pdo, 'telegramConfig' => $telegramConfig] = require __DIR__ . '/bootstrap.php';

$secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
$expectedSecret = (string)($telegramConfig['secret_token'] ?? '');

if ($expectedSecret !== '' && !hash_equals($expectedSecret, $secretHeader)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$botController = new BotController($pdo, $telegramConfig);
$botController->webhook();
http_response_code(200);
echo 'OK';
