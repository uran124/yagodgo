<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__);

$dbConfig = require $baseDir . '/config/database.php';
$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
$pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $dbConfig['options']);

$vendor = $baseDir . '/vendor/autoload.php';
if (file_exists($vendor)) {
    require $vendor;
} else {
    spl_autoload_register(function(string $class) use ($baseDir) {
        $prefix = 'App\\';
        $base = $baseDir . '/src/';
        if (strpos($class, $prefix) !== 0) return;
        $relative = substr($class, strlen($prefix));
        $file = $base . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) require $file;
    });
}

(new App\Controllers\AppsController($pdo))->generateSitemap();

