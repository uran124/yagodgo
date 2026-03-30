<?php

declare(strict_types=1);

// Конфигурация подключения к базе данных.
// Для локальной разработки задайте переменные окружения DB_*.
return [
    'host'      => getenv('DB_HOST') ?: '',
    'dbname'    => getenv('DB_NAME') ?: '',
    'user'      => getenv('DB_USER') ?: '',
    'password'  => getenv('DB_PASSWORD') ?: '',
    'charset'   => getenv('DB_CHARSET') ?: 'utf8mb4',

    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
