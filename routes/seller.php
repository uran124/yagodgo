<?php

declare(strict_types=1);

return [
    static function (string $method, string $uri, array $c): bool {
        $map = [
            'GET /seller/dashboard' => ['App\\Controllers\\SellerController', 'dashboard'],
            'GET /seller/profile' => ['App\\Controllers\\UsersController', 'sellerProfile'],
            'GET /seller/orders' => ['App\\Controllers\\SellerController', 'orders'],
            'POST /seller/orders/status' => ['App\\Controllers\\SellerController', 'updateOrderStatus'],
            'GET /seller/products' => ['App\\Controllers\\ProductsController', 'index'],
            'GET /seller/products/edit' => ['App\\Controllers\\ProductsController', 'edit'],
            'POST /seller/products/save' => ['App\\Controllers\\ProductsController', 'save'],
            'POST /seller/products/toggle' => ['App\\Controllers\\ProductsController', 'toggle'],
            'POST /seller/products/update-date' => ['App\\Controllers\\ProductsController', 'updateDeliveryDate'],
            'POST /seller/products/delete' => ['App\\Controllers\\ProductsController', 'delete'],
            'GET /seller/product-types' => ['App\\Controllers\\ProductTypesController', 'index'],
            'GET /seller/product-types/edit' => ['App\\Controllers\\ProductTypesController', 'edit'],
            'POST /seller/product-types/save' => ['App\\Controllers\\ProductTypesController', 'save'],
        ];

        $key = $method . ' ' . $uri;
        if (!isset($map[$key])) return false;
        requireSeller();
        [$class, $action] = $map[$key];
        (new $class($c['pdo']))->{$action}();
        return true;
    },
];
