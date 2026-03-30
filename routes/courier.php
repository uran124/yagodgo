<?php

declare(strict_types=1);

return [
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/courier/orders', $method, $uri)) {
            return false;
        }

        if (($_SESSION['role'] ?? '') !== 'courier') {
            header('Location: /login');
            exit;
        }

        (new App\Controllers\CourierController($c['pdo']))->listOrders();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/courier/order/update', $method, $uri)) {
            return false;
        }

        if (($_SESSION['role'] ?? '') !== 'courier') {
            header('Location: /login');
            exit;
        }

        (new App\Controllers\CourierController($c['pdo']))->updateStatus();
        return true;
    },
];
