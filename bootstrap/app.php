<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

mb_internal_encoding('UTF-8');

session_start();
header('Content-Type: text/html; charset=UTF-8');

if (isset($_GET['invite'])) {
    $invite = trim((string)$_GET['invite']);
    if ($invite !== '') {
        $_SESSION['invite_code'] = $invite;
    }
}

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require $vendorAutoload;
} else {
    spl_autoload_register(function (string $class) {
        $prefix  = 'App\\';
        $baseDir = __DIR__ . '/../src/';
        if (strpos($class, $prefix) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

/**
 * @param array<string, mixed> $config
 * @param array<int, string> $requiredKeys
 */
function ensureRequiredConfig(string $configName, array $config, array $requiredKeys): void
{
    $missing = [];
    foreach ($requiredKeys as $key) {
        $value = $config[$key] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $missing[] = $configName . '.' . $key;
        }
    }

    if ($missing !== []) {
        http_response_code(500);
        echo 'Ошибка конфигурации: отсутствуют обязательные ключи: ' . htmlspecialchars(implode(', ', $missing));
        exit;
    }
}

$dbConfig = require __DIR__ . '/../config/database.php';
$telegramConfig = require __DIR__ . '/../config/telegram.php';
$smsConfig = require __DIR__ . '/../config/sms.php';
$emailConfig = require __DIR__ . '/../config/email.php';
$constants = require __DIR__ . '/../config/constants.php';

ensureRequiredConfig('database', $dbConfig, ['host', 'dbname', 'user', 'password', 'charset']);
ensureRequiredConfig('telegram', $telegramConfig, ['bot_token', 'admin_chat_id']);
ensureRequiredConfig('sms', $smsConfig, ['api_id']);
ensureRequiredConfig('email', $emailConfig, ['from']);

if (!filter_var($emailConfig['from'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(500);
    echo 'Ошибка конфигурации: email.from должен быть валидным email-адресом.';
    exit;
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    $safeMessage = \App\Helpers\SensitiveData::sanitizeText($message);
    error_log(sprintf('[%s] PHP error: %s in %s:%d', date('Y-m-d H:i:s'), $safeMessage, $file, $line));
    return false;
});

$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
try {
    $pdo = new PDO(
        $dsn,
        $dbConfig['user'],
        $dbConfig['password'],
        $dbConfig['options']
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Ошибка подключения к базе данных. Проверьте корректность database.* в конфиге.';
    error_log(\App\Helpers\SensitiveData::sanitizeText($e->getMessage()));
    exit;
}

if (!empty($_SESSION['user_id'])) {
    $stmtBalance = $pdo->prepare("SELECT points_balance, rub_balance FROM users WHERE id = ?");
    $stmtBalance->execute([$_SESSION['user_id']]);
    $bal = $stmtBalance->fetch(PDO::FETCH_ASSOC);
    $_SESSION['points_balance'] = $bal !== false ? (int)$bal['points_balance'] : 0;
    $_SESSION['rub_balance'] = $bal !== false ? (int)$bal['rub_balance'] : 0;
}

define('PLACEHOLDER_DATE', $constants['placeholder_date']);
define('BOX_MARKUP', $constants['box_markup']);
define('DISCOUNT_FACTOR', $constants['discount_factor']);

require_once __DIR__ . '/../src/helpers.php';

$authMiddleware = new App\Middleware\AuthMiddleware();

return [
    'pdo' => $pdo,
    'telegramConfig' => $telegramConfig,
    'smsConfig' => $smsConfig,
    'emailConfig' => $emailConfig,
    'authMiddleware' => $authMiddleware,
];
