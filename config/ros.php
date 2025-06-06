<?php
// ~/www/yagodgo.ru/config/routes.php

use App\Controllers\AuthController;
use App\Controllers\ClientController;
use App\Controllers\CourierController;
use App\Controllers\AdminController;
use App\Controllers\UsersController;

// Маршруты для гостя (неавторизованные пользователи)
$router->get('/register',  'AuthController@showRegistrationForm');
$router->post('/register', 'AuthController@register');
$router->get('/login',     'AuthController@showLoginForm');
$router->post('/login',    'AuthController@login');

// Выход (от всех ролей)
$router->post('/logout', 'AuthController@logout');

// Защищённые маршруты — клиенты (роль client)
$router->group(['middleware' => 'auth:client'], function() use ($router) {
    // Главная страница клиента
    $router->get('/',         'ClientController@home');
    // Каталог товаров
    $router->get('/catalog',  'ClientController@catalog');
    // ... здесь могут быть другие страницы клиента (корзина, заказы и т.д.)

    // Просмотр и редактирование профиля клиента
    $router->get('/profile',  'UsersController@showProfile');
    $router->post('/profile', 'UsersController@saveAddress');

    // Оформление заказа, история заказов и т.п.
    // $router->get('/checkout',        'OrdersController@showCheckoutForm');
    // $router->post('/checkout',       'OrdersController@placeOrder');
    // $router->get('/orders',          'OrdersController@listForClient');
    // ...
});

// Защищённые маршруты — курьеры (роль courier)
$router->group(['middleware' => 'auth:courier'], function() use ($router) {
    $router->get('/courier/orders',       'CourierController@listOrders');
    $router->post('/courier/orders/{$id}', 'CourierController@updateOrderStatus');
    // ... другие действия курьера
});

// Защищённые маршруты — админы (роль admin)
$router->group(['middleware' => 'auth:admin'], function() use ($router) {
    $router->get('/admin/dashboard',       'AdminController@dashboard');
    $router->get('/admin/users',           'UsersController@index');
    $router->get('/admin/users/edit',      'UsersController@edit');
    $router->post('/admin/users/save',     'UsersController@save');
    $router->get('/admin/orders',          'OrdersController@index');
    $router->get('/admin/orders/show',     'OrdersController@show');
    $router->post('/admin/orders/assign',  'OrdersController@assign');
    $router->post('/admin/orders/status',  'OrdersController@updateStatus');
    // ... и другие административные страницы
});
