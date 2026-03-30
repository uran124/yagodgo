<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$groups = [
    require __DIR__ . '/public.php',
    require __DIR__ . '/client.php',
    require __DIR__ . '/courier.php',
    require __DIR__ . '/admin.php',
    require __DIR__ . '/manager.php',
    require __DIR__ . '/partner.php',
    require __DIR__ . '/seller.php',
];

$routes = [];
foreach ($groups as $group) {
    foreach ($group as $route) {
        $routes[] = $route;
    }
}

return $routes;
