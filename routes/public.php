<?php

declare(strict_types=1);

return [
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/telegram/webhook', $method, $uri)) {
            return false;
        }

        $botController = new App\Controllers\BotController($c['pdo'], $c['telegramConfig']);
        $botController->webhook();
        http_response_code(200);
        exit;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/telegram/callback', $method, $uri)) {
            return false;
        }

        $botController = new App\Controllers\BotController($c['pdo'], $c['telegramConfig']);
        $botController->handleCallbackQuery();
        http_response_code(200);
        exit;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/', $method, $uri)) {
            return false;
        }

        (new App\Controllers\ClientController($c['pdo']))->home();

        return true;
    },
    static function (string $method, string $uri): bool {
        if (!routeExact('GET', '/register', $method, $uri)) {
            return false;
        }

        viewAuth('client/register', [
            'error' => $_GET['error'] ?? null,
        ]);

        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/register', $method, $uri)) {
            return false;
        }

        (new App\Controllers\AuthController($c['pdo']))->register();

        return true;
    },
    static function (string $method, string $uri): bool {
        if (!routeExact('GET', '/login', $method, $uri)) {
            return false;
        }

        viewAuth('client/login', [
            'error' => $_GET['error'] ?? null,
        ]);

        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/login', $method, $uri)) {
            return false;
        }

        (new App\Controllers\AuthController($c['pdo']))->login();

        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/api/send-reg-code', $method, $uri)) {
            return false;
        }

        (new App\Controllers\AuthController($c['pdo'], $c['smsConfig'], $c['telegramConfig'], $c['emailConfig']))->sendRegistrationCode();

        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/api/verify-reg-code', $method, $uri)) {
            return false;
        }

        (new App\Controllers\AuthController($c['pdo'], $c['smsConfig']))->verifyRegistrationCode();

        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/api/verify-reset-code', $method, $uri)) {
            return false;
        }

        (new App\Controllers\AuthController($c['pdo'], $c['smsConfig']))->verifyResetPinCode();

        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/reset-pin', $method, $uri)) {
            return false;
        }

        (new App\Controllers\AuthController($c['pdo'], $c['smsConfig']))->showResetPinForm();

        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/reset-pin/send-code', $method, $uri)) {
            return false;
        }

        (new App\Controllers\AuthController($c['pdo'], $c['smsConfig']))->sendResetPinCode();

        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/reset-pin', $method, $uri)) {
            return false;
        }

        (new App\Controllers\AuthController($c['pdo'], $c['smsConfig']))->resetPin();

        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/logout', $method, $uri)) {
            return false;
        }

        (new App\Controllers\AuthController($c['pdo']))->logout();

        return true;
    },
];
