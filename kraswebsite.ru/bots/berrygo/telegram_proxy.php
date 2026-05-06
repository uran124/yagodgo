<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'description' => 'invalid_json']);
    exit;
}

$method = (string)($input['method'] ?? '');
$params = $input['params'] ?? [];
$botToken = (string)($input['bot_token'] ?? '');
$secret = (string)($input['secret'] ?? '');

if ($method === '' || !is_array($params) || $botToken === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'description' => 'missing_required_fields']);
    exit;
}

$allowedMethods = ['sendMessage', 'setWebhook', 'deleteWebhook', 'getWebhookInfo'];
if (!in_array($method, $allowedMethods, true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'description' => 'method_not_allowed']);
    exit;
}

$expectedSecret = getenv('BERRYGO_RELAY_SECRET') ?: '';
if ($expectedSecret !== '' && !hash_equals($expectedSecret, $secret)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'description' => 'forbidden']);
    exit;
}

$telegramUrl = "https://api.telegram.org/bot{$botToken}/{$method}";
$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json; charset=UTF-8\r\n",
        'content' => json_encode($params, JSON_UNESCAPED_UNICODE),
        'timeout' => 10,
    ],
];

$response = @file_get_contents($telegramUrl, false, stream_context_create($options));
if ($response === false) {
    http_response_code(502);
    $err = error_get_last();
    echo json_encode(['ok' => false, 'description' => 'telegram_unreachable', 'error' => $err['message'] ?? 'unknown']);
    exit;
}

echo $response;
