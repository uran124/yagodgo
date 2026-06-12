<?php

['pdo' => $pdo, 'telegramConfig' => $telegramConfig, 'smsConfig' => $smsConfig, 'emailConfig' => $emailConfig, 'authMiddleware' => $authMiddleware] = require __DIR__ . '/bootstrap/app.php';
require __DIR__ . '/bootstrap/views.php';
require __DIR__ . '/bootstrap/auth.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

enforce_csrf_for_request($method, $uri);

if ($uri === '/favicon.ico') {
    $faviconPath = __DIR__ . '/assets/images/favicon.svg';
    if (is_file($faviconPath)) {
        header('Content-Type: image/svg+xml');
        readfile($faviconPath);
        return;
    }
}

$context = [
    'pdo' => $pdo,
    'telegramConfig' => $telegramConfig,
    'smsConfig' => $smsConfig,
    'emailConfig' => $emailConfig,
    'authMiddleware' => $authMiddleware,
];

$routes = require __DIR__ . '/routes/index.php';

foreach ($routes as $route) {
    if ($route($method, $uri, $context) === true) {
        return;
    }
}

http_response_code(404);
echo 'Страница не найдена';
