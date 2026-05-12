<?php

declare(strict_types=1);

return [
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/admin/dashboard', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\AdminController($c['pdo']))->dashboard(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/admin/products', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\ProductsController($c['pdo']))->index(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/admin/products/edit', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\ProductsController($c['pdo']))->edit(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/admin/products/save', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\ProductsController($c['pdo']))->save(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/admin/products/toggle', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\ProductsController($c['pdo']))->toggle(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!(routeExact('POST', '/admin/products/update-price', $method, $uri) || routeExact('GET', '/admin/products/update-price', $method, $uri))) return false;
        requireAdmin(); (new App\Controllers\ProductsController($c['pdo']))->updatePrice(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/admin/products/update-date', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\ProductsController($c['pdo']))->updateDeliveryDate(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/admin/products/delete', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\ProductsController($c['pdo']))->delete(); return true;
    },

    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/admin/product-types', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\ProductTypesController($c['pdo']))->index(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/admin/product-types/edit', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\ProductTypesController($c['pdo']))->edit(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/admin/product-types/save', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\ProductTypesController($c['pdo']))->save(); return true;
    },

    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/admin/orders', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\OrdersController($c['pdo']))->index(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/admin/purchases', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\PurchaseBatchesController($c['pdo']))->index(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/admin/purchases/create', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\PurchaseBatchesController($c['pdo']))->create(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/admin/purchases/(\d+)$#', $method, $uri, $m)) return false;
        requireAdmin(); (new App\Controllers\PurchaseBatchesController($c['pdo']))->show((int)$m[1]); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/admin/purchases/(\d+)/pnl\.csv$#', $method, $uri, $m)) return false;
        requireAdmin(); (new App\Controllers\PurchaseBatchesController($c['pdo']))->exportPnlCsv((int)$m[1]); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/admin/purchases/store', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\PurchaseBatchesController($c['pdo']))->store(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/admin/purchases/arrived', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\PurchaseBatchesController($c['pdo']))->markArrived(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/admin/purchases/move-to-discount', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\PurchaseBatchesController($c['pdo']))->moveToDiscount(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/admin/purchases/write-off', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\PurchaseBatchesController($c['pdo']))->writeOff(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/admin/purchases/close', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\PurchaseBatchesController($c['pdo']))->close(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/admin/purchases/photos/delete', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\PurchaseBatchesController($c['pdo']))->deletePhoto(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('GET', '/admin/orders/create', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\OrdersController($c['pdo']))->create(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeExact('POST', '/admin/orders/create', $method, $uri)) return false;
        requireAdmin(); (new App\Controllers\OrdersController($c['pdo']))->storeManual(); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/admin/orders/(\d+)$#', $method, $uri, $m)) return false;
        requireAdmin(); (new App\Controllers\OrdersController($c['pdo']))->show((int)$m[1]); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        $map = [
            '/admin/orders/assign' => 'assign',
            '/admin/orders/status' => 'updateStatus',
            '/admin/orders/update-item' => 'updateItem',
            '/admin/orders/add-item' => 'addItem',
            '/admin/orders/delete-item' => 'deleteItem',
            '/admin/orders/comment' => 'updateComment',
            '/admin/orders/referral' => 'updateReferral',
            '/admin/orders/update-delivery' => 'updateDelivery',
            '/admin/orders/delete' => 'delete',
        ];
        if ($method !== 'POST' || !isset($map[$uri])) return false;
        requireAdmin(); (new App\Controllers\OrdersController($c['pdo']))->{$map[$uri]}(); return true;
    },

    static function (string $method, string $uri, array $c): bool {
        $map = [
            'GET /admin/slots' => ['App\\Controllers\\SlotsController', 'index'],
            'GET /admin/slots/edit' => ['App\\Controllers\\SlotsController', 'edit'],
            'POST /admin/slots/save' => ['App\\Controllers\\SlotsController', 'save'],
            'POST /admin/slots/delete' => ['App\\Controllers\\SlotsController', 'delete'],
            'GET /admin/coupons' => ['App\\Controllers\\CouponsController', 'index'],
            'GET /admin/coupons/edit' => ['App\\Controllers\\CouponsController', 'edit'],
            'POST /admin/coupons/save' => ['App\\Controllers\\CouponsController', 'save'],
            'POST /admin/coupons/generate' => ['App\\Controllers\\CouponsController', 'generate'],
            'GET /admin/content' => ['App\\Controllers\\ContentController', 'categories'],
            'GET /admin/content/category/edit' => ['App\\Controllers\\ContentController', 'editCategory'],
            'POST /admin/content/category/save' => ['App\\Controllers\\ContentController', 'saveCategory'],
            'GET /admin/content/materials' => ['App\\Controllers\\ContentController', 'materials'],
            'GET /admin/content/materials/edit' => ['App\\Controllers\\ContentController', 'editMaterial'],
            'POST /admin/content/materials/save' => ['App\\Controllers\\ContentController', 'saveMaterial'],
            'POST /admin/content/materials/toggle-active' => ['App\\Controllers\\ContentController', 'toggleMaterialActive'],
            'POST /admin/content/materials/toggle-home' => ['App\\Controllers\\ContentController', 'toggleMaterialHome'],
            'GET /admin/users' => ['App\\Controllers\\UsersController', 'index'],
            'GET /admin/users/search' => ['App\\Controllers\\UsersController', 'searchPhone'],
            'GET /admin/users/addresses' => ['App\\Controllers\\UsersController', 'addresses'],
            'GET /admin/users/edit' => ['App\\Controllers\\UsersController', 'edit'],
            'POST /admin/users/save' => ['App\\Controllers\\UsersController', 'save'],
            'POST /admin/users/delete' => ['App\\Controllers\\UsersController', 'delete'],
            'POST /admin/users/toggle-block' => ['App\\Controllers\\UsersController', 'toggleBlock'],
            'POST /admin/users/reset-balance' => ['App\\Controllers\\UsersController', 'resetRubBalance'],
            'POST /admin/users/add-address' => ['App\\Controllers\\UsersController', 'addAddressAdmin'],
            'POST /admin/users/delete-address' => ['App\\Controllers\\UsersController', 'deleteAddressAdmin'],
            'GET /admin/sellers' => ['App\\Controllers\\SellersController', 'index'],
            'GET /admin/sellers/edit' => ['App\\Controllers\\SellersController', 'edit'],
            'POST /admin/sellers/save' => ['App\\Controllers\\SellersController', 'save'],
            'GET /admin/apps' => ['App\\Controllers\\AppsController', 'index'],
            'POST /admin/apps/sitemap/toggle' => ['App\\Controllers\\AppsController', 'toggleSitemap'],
            'GET /admin/apps/sitemap' => ['App\\Controllers\\AppsController', 'sitemapSettings'],
            'POST /admin/apps/sitemap/generate' => ['App\\Controllers\\AppsController', 'generateSitemap'],
            'POST /admin/apps/sitemap/toggle-item' => ['App\\Controllers\\AppsController', 'toggleItem'],
            'GET /admin/apps/mailing' => ['App\\Controllers\\MailingController', 'index'],
            'POST /admin/apps/mailing/toggle' => ['App\\Controllers\\MailingController', 'toggle'],
            'POST /admin/apps/mailing/comment' => ['App\\Controllers\\MailingController', 'updateComment'],
            'GET /admin/apps/seo' => ['App\\Controllers\\SeoController', 'index'],
            'GET /admin/apps/seo/edit' => ['App\\Controllers\\SeoController', 'edit'],
            'POST /admin/apps/seo/save' => ['App\\Controllers\\SeoController', 'save'],
            'GET /admin/settings' => ['App\\Controllers\\SettingsController', 'index'],
            'POST /admin/settings' => ['App\\Controllers\\SettingsController', 'save'],
        ];

        $key = $method . ' ' . $uri;
        if (!isset($map[$key])) return false;
        requireAdmin();
        [$class, $action] = $map[$key];
        (new $class($c['pdo']))->{$action}();
        return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/admin/users/(\d+)$#', $method, $uri, $m)) return false;
        requireAdmin(); (new App\Controllers\UsersController($c['pdo']))->show((int)$m[1]); return true;
    },
    static function (string $method, string $uri, array $c): bool {
        if (!routeRegex('GET', '#^/admin/sellers/(\d+)$#', $method, $uri, $m)) return false;
        requireAdmin(); (new App\Controllers\SellersController($c['pdo']))->show((int)$m[1]); return true;
    },
];
