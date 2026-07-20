<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $file = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

$lockFile = $root . '/log/florix24_queue.lock';
$lockHandle = fopen($lockFile, 'c+');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "Florix24 queue is already running.\n");
    exit(0);
}

try {
    $dbConfig = require $root . '/config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['dbname'],
        $dbConfig['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $dbConfig['options'] ?? []);
    $result = (new App\Services\Florix24IntegrationService($pdo))->processQueue(100);
    fwrite(STDOUT, sprintf(
        "Florix24 queue: processed=%d sent=%d error=%d conflict=%d\n",
        $result['processed'],
        $result['sent'],
        $result['error'],
        $result['conflict']
    ));
    exit($result['error'] > 0 ? 2 : 0);
} catch (Throwable $e) {
    $message = sprintf('[%s] Florix24 queue failed: %s%s', date('Y-m-d H:i:s'), $e->getMessage(), PHP_EOL);
    @file_put_contents($root . '/log/florix24_queue.log', $message, FILE_APPEND | LOCK_EX);
    fwrite(STDERR, $message);
    exit(1);
} finally {
    if (is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}
