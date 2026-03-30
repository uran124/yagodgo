<?php

return [
    'host'      => '127.0.0.1',
    'dbname'    => 'berrygo',
    'user'      => 'berrygo_user',
    'password'  => 'change_me',
    'charset'   => 'utf8mb4',
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
