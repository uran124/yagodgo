<?php

declare(strict_types=1);

return [
    static function (string $method, string $uri, array $c): bool {
        $map = [
            'GET /partner/dashboard' => ['App\\Controllers\\AdminController', 'dashboard'],
            'GET /partner/profile' => ['App\\Controllers\\UsersController', 'partnerProfile'],
            'POST /partner/payout' => ['App\\Controllers\\UsersController', 'requestPayout'],
            'GET /partner/orders' => ['App\\Controllers\\OrdersController', 'index'],
            'GET /partner/orders/create' => ['App\\Controllers\\OrdersController', 'create'],
            'POST /partner/orders/create' => ['App\\Controllers\\OrdersController', 'storeManual'],
            'POST /partner/orders/assign' => ['App\\Controllers\\OrdersController', 'assign'],
            'POST /partner/orders/status' => ['App\\Controllers\\OrdersController', 'updateStatus'],
            'POST /partner/orders/update-item' => ['App\\Controllers\\OrdersController', 'updateItem'],
            'POST /partner/orders/add-item' => ['App\\Controllers\\OrdersController', 'addItem'],
            'POST /partner/orders/delete-item' => ['App\\Controllers\\OrdersController', 'deleteItem'],
            'POST /partner/orders/comment' => ['App\\Controllers\\OrdersController', 'updateComment'],
            'POST /partner/orders/referral' => ['App\\Controllers\\OrdersController', 'updateReferral'],
            'POST /partner/orders/update-delivery' => ['App\\Controllers\\OrdersController', 'updateDelivery'],
            'POST /partner/orders/delete' => ['App\\Controllers\\OrdersController', 'delete'],
            'GET /partner/products' => ['App\\Controllers\\ProductsController', 'index'],
            'GET /partner/products/edit' => ['App\\Controllers\\ProductsController', 'edit'],
            'POST /partner/products/save' => ['App\\Controllers\\ProductsController', 'save'],
            'POST /partner/products/toggle' => ['App\\Controllers\\ProductsController', 'toggle'],
            'POST /partner/products/update-date' => ['App\\Controllers\\ProductsController', 'updateDeliveryDate'],
            'POST /partner/products/delete' => ['App\\Controllers\\ProductsController', 'delete'],
            'GET /partner/users' => ['App\\Controllers\\UsersController', 'index'],
            'GET /partner/users/search' => ['App\\Controllers\\UsersController', 'searchPhone'],
            'GET /partner/users/addresses' => ['App\\Controllers\\UsersController', 'addresses'],
            'GET /partner/users/edit' => ['App\\Controllers\\UsersController', 'edit'],
            'POST /partner/users/save' => ['App\\Controllers\\UsersController', 'save'],
            'POST /partner/users/toggle-block' => ['App\\Controllers\\UsersController', 'toggleBlock'],
            'POST /partner/users/add-address' => ['App\\Controllers\\UsersController', 'addAddressAdmin'],
            'POST /partner/users/delete-address' => ['App\\Controllers\\UsersController', 'deleteAddressAdmin'],
        ];

        $key = $method . ' ' . $uri;
        if (!isset($map[$key])) return false;
        requirePartner();
        [$class, $action] = $map[$key];
        (new $class($c['pdo']))->{$action}();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/partner/orders/(\d+)$#', $method, $uri, $m)) return false;
        requirePartner(); (new App\Controllers\OrdersController($c['pdo']))->show((int)$m[1]); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/partner/users/(\d+)$#', $method, $uri, $m)) return false;
        requirePartner(); (new App\Controllers\UsersController($c['pdo']))->show((int)$m[1]); return true;
    },
];
