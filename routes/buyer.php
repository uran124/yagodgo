<?php

declare(strict_types=1);

return [
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/buyer/purchases', $method, $uri)) return false;
        requireBuyer(); (new App\Controllers\PurchaseBatchesController($c['pdo']))->index(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/buyer/purchases/create', $method, $uri)) return false;
        requireBuyer(); (new App\Controllers\PurchaseBatchesController($c['pdo']))->create(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/buyer/purchases/store', $method, $uri)) return false;
        requireBuyer(); (new App\Controllers\PurchaseBatchesController($c['pdo']))->store(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/buyer/purchases/(\d+)$#', $method, $uri, $m)) return false;
        requireBuyer(); (new App\Controllers\PurchaseBatchesController($c['pdo']))->show((int)$m[1]); return true;
    },
];
