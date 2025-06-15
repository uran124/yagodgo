<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Запуск сессии
session_start();

// 1) Подключаем конфигурацию БД и создаём PDO
$dbConfig = require __DIR__ . '/config/database.php';
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
    echo "Ошибка подключения к базе данных: " . htmlspecialchars($e->getMessage());
    exit;
}

if (!empty($_SESSION['user_id'])) {
    $stmtBalance = $pdo->prepare("SELECT points_balance FROM users WHERE id = ?");
    $stmtBalance->execute([ $_SESSION['user_id'] ]);
    // Если пользователь найден, сохраняем баланс в сессию
    $bal = $stmtBalance->fetchColumn();
    $_SESSION['points_balance'] = $bal !== false ? (int)$bal : 0;
}

// -------------------------------------------------------
// 2) Подключаем конфиг Telegram
$telegramConfig = require __DIR__ . '/config/telegram.php';
// Конфиг SMS.RU
$smsConfig = require __DIR__ . '/config/sms.php';
// Общие константы
$constants = require __DIR__ . '/config/constants.php';
define('PLACEHOLDER_DATE', $constants['placeholder_date']);
// -----



// 2) Подключаем Composer autoloader, если он есть,
//    иначе fallback на простейший PSR-4 автозагрузчик
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require $vendorAutoload;
} else {
    spl_autoload_register(function (string $class) {
        $prefix  = 'App\\';
        $baseDir = __DIR__ . '/src/';
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













// Функция для рендеринга админских шаблонов
function viewAdmin(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . "/src/Views/admin/{$template}.php";
    $content = ob_get_clean();
    $pageTitle = $data['pageTitle'] ?? '';
    require __DIR__ . '/src/Views/layouts/admin_main.php';
}

// Функция для рендеринга клиентских шаблонов
function view(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . "/src/Views/{$template}.php";
    $content = ob_get_clean();
    require __DIR__ . '/src/Views/layouts/main.php';
}

// 4) Простая маршрутизация по URI и методу
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Вспомогательная функция для защиты клиентских маршрутов
function requireClient(): void
{
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, ['client','admin'], true)) {
        header('Location: /login');
        exit;
    }
}

// Информация о статусе заказа: название, классы бейджа и фона
function order_status_info(string $status): array
{
    return match($status) {
        'new' => [
            'label'  => 'Новый заказ',
            'badge'  => 'bg-red-100 text-red-800',
            'bg'     => 'bg-red-50',
        ],
        'processing' => [
            'label' => 'Принят',
            'badge' => 'bg-yellow-100 text-yellow-800',
            'bg'    => 'bg-yellow-50',
        ],
        'assigned' => [
            'label' => 'Обработан',
            'badge' => 'bg-green-100 text-green-800',
            'bg'    => 'bg-green-50',
        ],
        'delivered' => [
            'label' => 'Выполнен',
            'badge' => 'bg-gray-200 text-gray-800',
            'bg'    => 'bg-gray-100',
        ],
        'cancelled' => [
            'label' => 'Отменен',
            'badge' => 'text-gray-500',
            'bg'    => '',
        ],
        default => [
            'label' => $status,
            'badge' => 'bg-gray-100 text-gray-800',
            'bg'    => '',
        ],
    };
}

switch ("$method $uri") {




   // --- ДОБАВЛЯЕМ РАБОТУ С TELEGRAM BOT ---

    // Обработка обычных сообщений и команд (без нажатий Inline-кнопок)
    case 'POST /telegram/webhook':
        // Подключаем контроллер бота
        // Предполагается, что у вас есть файл src/Controllers/BotController.php
        $botController = new App\Controllers\BotController($pdo, $telegramConfig);
        $botController->webhook();
        // Telegram ожидает, что сервер вернёт HTTP 200 OK
        http_response_code(200);
        exit;

    // Если вы планируете обрабатывать callback_query отдельно
    case 'POST /telegram/callback':
        $botController = new App\Controllers\BotController($pdo, $telegramConfig);
        $botController->handleCallbackQuery();
        http_response_code(200);
        exit;







    // Публичная главная (например, редирект на каталог для гостей)
    case 'GET /':
        (new App\Controllers\ClientController($pdo))->home();
        break;

    // Регистрация
    case 'GET /register':
        view('client/register', [
            'error' => $_GET['error'] ?? null
        ]);
        break;
    case 'POST /register':
        (new App\Controllers\AuthController($pdo))->register();
        break;

    // Логин
    case 'GET /login':
        view('client/login', [
            'error' => $_GET['error'] ?? null
        ]);
        break;
    case 'POST /login':
        (new App\Controllers\AuthController($pdo))->login();
        break;

    // СМС подтверждения при регистрации
    case 'POST /api/send-reg-code':
        (new App\Controllers\AuthController($pdo, $smsConfig))->sendRegistrationCode();
        break;
    case 'POST /api/verify-reg-code':
        (new App\Controllers\AuthController($pdo, $smsConfig))->verifyRegistrationCode();
        break;
    case 'POST /api/verify-reset-code':
        (new App\Controllers\AuthController($pdo, $smsConfig))->verifyResetPinCode();
        break;

    // Восстановление PIN-кода
    case 'GET /reset-pin':
        (new App\Controllers\AuthController($pdo, $smsConfig))->showResetPinForm();
        break;
    case 'POST /reset-pin/send-code':
        (new App\Controllers\AuthController($pdo, $smsConfig))->sendResetPinCode();
        break;
    case 'POST /reset-pin':
        (new App\Controllers\AuthController($pdo, $smsConfig))->resetPin();
        break;

    // Выход
    case 'POST /logout':
        (new App\Controllers\AuthController($pdo))->logout();
        break;

    // Защищённые маршруты — клиенты
    case 'GET /catalog':
        requireClient();
        (new App\Controllers\ClientController($pdo))->catalog();
        break;

    // В блоке switch(..)
    case 'GET /orders':
        requireClient();
        (new App\Controllers\ClientController($pdo))->orders();
        break;
    
    // Добавляем прямо ниже, например:
    case (bool) preg_match('#^GET /orders/(\d+)$#', "$method $uri", $matches):
        requireClient();
        $orderId = (int)$matches[1];
        (new App\Controllers\ClientController($pdo))->showOrder($orderId);
        break;

    case 'GET /favorites':
        requireClient();
        (new App\Controllers\ClientController($pdo))->favorites();
        break;

    // Профиль (UsersController)
    case 'GET /profile':
        requireClient();
        (new App\Controllers\UsersController($pdo))->showProfile();
        break;
    case 'POST /profile':
        requireClient();
        (new App\Controllers\UsersController($pdo))->saveAddress();
        break;

    case 'GET /checkout':
        requireClient();
        (new App\Controllers\ClientController($pdo))->checkout();
        break;

    case 'GET /cart':
        requireClient();
        (new App\Controllers\ClientController($pdo))->cart();
        break;

    case 'POST /cart/add':
        requireClient();
        (new App\Controllers\ClientController($pdo))->addToCart();
        break;

    case 'POST /cart/update':
        requireClient();
        (new App\Controllers\ClientController($pdo))->updateCart();
        break;

    case 'POST /cart/remove':
        requireClient();
        (new App\Controllers\ClientController($pdo))->removeFromCart();
        break;

    case 'POST /cart/clear':
        requireClient();
        (new App\Controllers\ClientController($pdo))->clearCart();
        break;

    case 'POST /checkout':
        requireClient();
        (new App\Controllers\ClientController($pdo))->placeOrder();
        break;

    // Курьерские маршруты
    case 'GET /courier/orders':
        if (($_SESSION['role'] ?? '') !== 'courier') {
            header('Location: /login');
            exit;
        }
        (new App\Controllers\CourierController($pdo))->listOrders();
        break;
    case 'POST /courier/order/update':
        if (($_SESSION['role'] ?? '') !== 'courier') {
            header('Location: /login');
            exit;
        }
        (new App\Controllers\CourierController($pdo))->updateStatus();
        break;

    // === АДМИН-МАРШРУТЫ ===

    case 'GET /admin/dashboard':
        (new App\Controllers\AdminController($pdo))->dashboard();
        break;

    case 'GET /admin/products':
        (new App\Controllers\ProductsController($pdo))->index();
        break;
    case 'GET /admin/products/edit':
        (new App\Controllers\ProductsController($pdo))->edit();
        break;
    case 'POST /admin/products/save':
        (new App\Controllers\ProductsController($pdo))->save();
        break;
    case 'POST /admin/products/toggle':
        (new App\Controllers\ProductsController($pdo))->toggle();
        break;
    case 'POST /admin/products/delete':
        (new App\Controllers\ProductsController($pdo))->delete();
        break;

    case 'GET /admin/orders':
        (new App\Controllers\OrdersController($pdo))->index();
        break;
    case (bool)preg_match('#^GET /admin/orders/(\d+)$#', "$method $uri", $m):
        (new App\Controllers\OrdersController($pdo))->show((int)$m[1]);
        break;
    case 'POST /admin/orders/assign':
        (new App\Controllers\OrdersController($pdo))->assign();
        break;
    case 'POST /admin/orders/status':
        (new App\Controllers\OrdersController($pdo))->updateStatus();
        break;
    case 'POST /admin/orders/delete':
        (new App\Controllers\OrdersController($pdo))->delete();
        break;
        
    case 'GET /admin/slots':
        (new App\Controllers\SlotsController($pdo))->index();
        break;
    case 'GET /admin/slots/edit':
        (new App\Controllers\SlotsController($pdo))->edit();
        break;
    case 'POST /admin/slots/save':
        (new App\Controllers\SlotsController($pdo))->save();
        break;
    case 'POST /admin/slots/delete':
        (new App\Controllers\SlotsController($pdo))->delete();
        break;

    case 'GET /admin/coupons':
        (new App\Controllers\CouponsController($pdo))->index();
        break;
    case 'GET /admin/coupons/edit':
        (new App\Controllers\CouponsController($pdo))->edit();
        break;
    case 'POST /admin/coupons/save':
        (new App\Controllers\CouponsController($pdo))->save();
        break;
    case 'POST /admin/coupons/generate':
        (new App\Controllers\CouponsController($pdo))->generate();
        break;

    case 'GET /admin/users':
        (new App\Controllers\UsersController($pdo))->index();
        break;
    case 'GET /admin/users/edit':
        (new App\Controllers\UsersController($pdo))->edit();
        break;
    case 'POST /admin/users/save':
        (new App\Controllers\UsersController($pdo))->save();
        break;
    case 'POST /admin/users/toggle-block':
        (new App\Controllers\UsersController($pdo))->toggleBlock();
        break;

    case 'GET /admin/settings':
        (new App\Controllers\SettingsController($pdo))->index();
        break;
    case 'POST /admin/settings':
        (new App\Controllers\SettingsController($pdo))->save();
        break;

    // Любые другие запросы — 404
    default:
        http_response_code(404);
        echo "Страница не найдена";
        break;
}
