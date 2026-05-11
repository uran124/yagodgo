<?php

declare(strict_types=1);

return [
    static function (string $method, string $uri, array $c): bool {
        $map = [
            'GET /manager/dashboard' => ['App\\Controllers\\AdminController', 'dashboard'],
            'GET /manager/profile' => ['App\\Controllers\\UsersController', 'managerProfile'],
            'POST /manager/payout' => ['App\\Controllers\\UsersController', 'requestPayout'],
            'GET /manager/orders' => ['App\\Controllers\\OrdersController', 'index'],
            'GET /manager/orders/create' => ['App\\Controllers\\OrdersController', 'create'],
            'POST /manager/orders/create' => ['App\\Controllers\\OrdersController', 'storeManual'],
            'POST /manager/orders/assign' => ['App\\Controllers\\OrdersController', 'assign'],
            'POST /manager/orders/status' => ['App\\Controllers\\OrdersController', 'updateStatus'],
            'POST /manager/orders/update-item' => ['App\\Controllers\\OrdersController', 'updateItem'],
            'POST /manager/orders/add-item' => ['App\\Controllers\\OrdersController', 'addItem'],
            'POST /manager/orders/delete-item' => ['App\\Controllers\\OrdersController', 'deleteItem'],
            'POST /manager/orders/comment' => ['App\\Controllers\\OrdersController', 'updateComment'],
            'POST /manager/orders/referral' => ['App\\Controllers\\OrdersController', 'updateReferral'],
            'POST /manager/orders/update-delivery' => ['App\\Controllers\\OrdersController', 'updateDelivery'],
            'POST /manager/orders/delete' => ['App\\Controllers\\OrdersController', 'delete'],
            'GET /manager/products' => ['App\\Controllers\\ProductsController', 'index'],
            'GET /manager/products/edit' => ['App\\Controllers\\ProductsController', 'edit'],
            'POST /manager/products/save' => ['App\\Controllers\\ProductsController', 'save'],
            'POST /manager/products/toggle' => ['App\\Controllers\\ProductsController', 'toggle'],
            'POST /manager/products/update-price' => ['App\\Controllers\\ProductsController', 'updatePrice'],
            'GET /manager/products/update-price' => ['App\\Controllers\\ProductsController', 'updatePrice'],
            'POST /manager/products/update-date' => ['App\\Controllers\\ProductsController', 'updateDeliveryDate'],
            'POST /manager/products/delete' => ['App\\Controllers\\ProductsController', 'delete'],
            'GET /manager/users' => ['App\\Controllers\\UsersController', 'index'],
            'GET /manager/users/search' => ['App\\Controllers\\UsersController', 'searchPhone'],
            'GET /manager/users/addresses' => ['App\\Controllers\\UsersController', 'addresses'],
            'GET /manager/users/edit' => ['App\\Controllers\\UsersController', 'edit'],
            'POST /manager/users/save' => ['App\\Controllers\\UsersController', 'save'],
            'POST /manager/users/toggle-block' => ['App\\Controllers\\UsersController', 'toggleBlock'],
            'POST /manager/users/add-address' => ['App\\Controllers\\UsersController', 'addAddressAdmin'],
            'POST /manager/users/delete-address' => ['App\\Controllers\\UsersController', 'deleteAddressAdmin'],
            'GET /manager/purchases' => ['App\\Controllers\\PurchaseBatchesController', 'index'],
            'GET /manager/purchases/create' => ['App\\Controllers\\PurchaseBatchesController', 'create'],
            'POST /manager/purchases/store' => ['App\\Controllers\\PurchaseBatchesController', 'store'],
        ];

        $key = $method . ' ' . $uri;
        if (!isset($map[$key])) return false;
        requireManager();
        [$class, $action] = $map[$key];
        (new $class($c['pdo']))->{$action}();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/manager/orders/(\d+)$#', $method, $uri, $m)) return false;
        requireManager(); (new App\Controllers\OrdersController($c['pdo']))->show((int)$m[1]); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/manager/users/(\d+)$#', $method, $uri, $m)) return false;
        requireManager(); (new App\Controllers\UsersController($c['pdo']))->show((int)$m[1]); return true;
    },
];
