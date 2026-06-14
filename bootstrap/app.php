<?php
declare(strict_types=1);

$appEnv = getenv('APP_ENV') ?: 'production';
$appDebugRaw = getenv('APP_DEBUG');
$appDebug = $appDebugRaw !== false
    ? filter_var($appDebugRaw, FILTER_VALIDATE_BOOLEAN)
    : !in_array($appEnv, ['production', 'prod'], true);

ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

mb_internal_encoding('UTF-8');

function appLog(string $message, ?Throwable $throwable = null): void
{
    $logFile = getenv('APP_LOG_FILE') ?: dirname(__DIR__) . '/log/app.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $context = $throwable !== null
        ? sprintf(' in %s:%d\n%s', $throwable->getFile(), $throwable->getLine(), $throwable->getTraceAsString())
        : '';
    $safeMessage = class_exists('App\Helpers\SensitiveData')
        ? \App\Helpers\SensitiveData::sanitizeText($message . $context)
        : $message . $context;

    error_log(sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $safeMessage, PHP_EOL), 3, $logFile);
}

function renderBootstrapError(string $publicMessage, ?Throwable $throwable = null): void
{
    global $appDebug;

    http_response_code(500);
    if ($appDebug && $throwable !== null) {
        echo '<pre>' . htmlspecialchars($publicMessage . "\n" . $throwable->getMessage() . "\n" . $throwable->getTraceAsString(), ENT_QUOTES) . '</pre>';
        return;
    }

    echo htmlspecialchars($publicMessage, ENT_QUOTES);
}

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

set_exception_handler(static function (Throwable $e): void {
    appLog('Uncaught exception: ' . $e->getMessage(), $e);
    renderBootstrapError('Внутренняя ошибка приложения. Попробуйте позже.', $e);
});

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
        appLog('Missing required config keys: ' . implode(', ', $missing));
        renderBootstrapError('Ошибка конфигурации: отсутствуют обязательные ключи: ' . implode(', ', $missing));
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
    appLog('Invalid email.from runtime config value.');
    renderBootstrapError('Ошибка конфигурации: email.from должен быть валидным email-адресом.');
    exit;
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    appLog(sprintf('PHP error [%d]: %s in %s:%d', $severity, $message, $file, $line));
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
    appLog('Database connection failed: ' . $e->getMessage(), $e);
    renderBootstrapError('Ошибка подключения к базе данных. Проверьте корректность database.* в конфиге.', $e);
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
$csrfMiddleware = new App\Middleware\CsrfMiddleware();

return [
    'pdo' => $pdo,
    'telegramConfig' => $telegramConfig,
    'smsConfig' => $smsConfig,
    'emailConfig' => $emailConfig,
    'authMiddleware' => $authMiddleware,
    'csrfMiddleware' => $csrfMiddleware,
];
