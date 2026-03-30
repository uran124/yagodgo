<?php

declare(strict_types=1);

return [
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/catalog', $method, $uri)) {
            return false;
        }

        (new App\Controllers\ClientController($c['pdo']))->catalog();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/orders', $method, $uri)) {
            return false;
        }

        requireClient();
        (new App\Controllers\ClientController($c['pdo']))->orders();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/notifications', $method, $uri)) {
            return false;
        }

        requireClient();
        (new App\Controllers\ClientController($c['pdo']))->notifications();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/orders/(\d+)$#', $method, $uri, $matches)) {
            return false;
        }

        requireClient();
        (new App\Controllers\ClientController($c['pdo']))->showOrder((int) $matches[1]);
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('POST', '#^/orders/(\d+)/confirm$#', $method, $uri, $matches)) {
            return false;
        }

        requireClient();
        (new App\Controllers\ClientController($c['pdo']))->confirmReservedOrder((int) $matches[1]);
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('POST', '#^/orders/(\d+)/cancel$#', $method, $uri, $matches)) {
            return false;
        }

        requireClient();
        (new App\Controllers\ClientController($c['pdo']))->cancelReservedOrder((int) $matches[1]);
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/content/([^/]+)/([^/]+)$#', $method, $uri, $m)) {
            return false;
        }

        (new App\Controllers\ClientController($c['pdo']))->showMaterial($m[1], $m[2]);
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/catalog/([^/]+)/([^/]+)$#', $method, $uri, $m)) {
            return false;
        }

        (new App\Controllers\ClientController($c['pdo']))->showProduct($m[2], $m[1]);
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/catalog/([^/]+)$#', $method, $uri, $m)) {
            return false;
        }

        (new App\Controllers\ClientController($c['pdo']))->showProductType($m[1]);
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/product/([^/]+)$#', $method, $uri, $m)) {
            return false;
        }

        (new App\Controllers\ClientController($c['pdo']))->showProduct($m[1]);
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/type/([^/]+)$#', $method, $uri, $m)) {
            return false;
        }

        (new App\Controllers\ClientController($c['pdo']))->showProductType($m[1]);
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/favorites', $method, $uri)) {
            return false;
        }

        requireClient();
        (new App\Controllers\ClientController($c['pdo']))->favorites();
        return true;
    },

    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/profile', $method, $uri)) {
            return false;
        }

        requireClient();
        (new App\Controllers\UsersController($c['pdo']))->showProfile();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/profile', $method, $uri)) {
            return false;
        }

        requireClient();
        (new App\Controllers\UsersController($c['pdo']))->saveAddress();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/profile/set-primary', $method, $uri)) {
            return false;
        }

        requireClient();
        (new App\Controllers\UsersController($c['pdo']))->setPrimaryAddress();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/profile/delete-address', $method, $uri)) {
            return false;
        }

        requireClient();
        (new App\Controllers\UsersController($c['pdo']))->deleteAddress();
        return true;
    },

    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/checkout', $method, $uri)) {
            return false;
        }

        requireClient();
        (new App\Controllers\ClientController($c['pdo']))->checkout();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/cart', $method, $uri)) {
            return false;
        }

        requireClient();
        (new App\Controllers\ClientController($c['pdo']))->cart();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/cart/add', $method, $uri)) {
            return false;
        }

        requireClient();
        (new App\Controllers\ClientController($c['pdo']))->addToCart();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/cart/update', $method, $uri)) {
            return false;
        }

        requireClient();
        (new App\Controllers\ClientController($c['pdo']))->updateCart();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/cart/remove', $method, $uri)) {
            return false;
        }

        requireClient();
        (new App\Controllers\ClientController($c['pdo']))->removeFromCart();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/cart/clear', $method, $uri)) {
            return false;
        }

        requireClient();
        (new App\Controllers\ClientController($c['pdo']))->clearCart();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/checkout', $method, $uri)) {
            return false;
        }

        requireClient();
        (new App\Controllers\ClientController($c['pdo']))->placeOrder();
        return true;
    },
];
