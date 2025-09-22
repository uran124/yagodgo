<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

mb_internal_encoding('UTF-8');

// Запуск сессии
session_start();
header('Content-Type: text/html; charset=UTF-8');

// Сохраняем пригласительный код из URL в сессию (если передан)
if (isset($_GET['invite'])) {
    $invite = trim((string)$_GET['invite']);
    if ($invite !== '') {
        $_SESSION['invite_code'] = $invite;
    }
}

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
    $stmtBalance = $pdo->prepare("SELECT points_balance, rub_balance FROM users WHERE id = ?");
    $stmtBalance->execute([ $_SESSION['user_id'] ]);
    $bal = $stmtBalance->fetch(PDO::FETCH_ASSOC);
    $_SESSION['points_balance'] = $bal !== false ? (int)$bal['points_balance'] : 0;
    $_SESSION['rub_balance'] = $bal !== false ? (int)$bal['rub_balance'] : 0;
}

// -------------------------------------------------------
// 2) Подключаем конфиг Telegram
$telegramConfig = require __DIR__ . '/config/telegram.php';
// Конфиг SMS.RU
$smsConfig = require __DIR__ . '/config/sms.php';
// Конфиг почты
$emailConfig = require __DIR__ . '/config/email.php';
// Общие константы
$constants = require __DIR__ . '/config/constants.php';
define('PLACEHOLDER_DATE', $constants['placeholder_date']);
define('BOX_MARKUP', $constants['box_markup']);
define('DISCOUNT_FACTOR', $constants['discount_factor']);
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

require_once __DIR__ . '/src/helpers.php';













// Функция для рендеринга админских шаблонов
function viewAdmin(string $template, array $data = []): void
{
    $pageTitle = $data['pageTitle'] ?? '';
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . "/src/Views/admin/{$template}.php";
    $content = ob_get_clean();
    $role = $_SESSION['role'] ?? '';
    if ($role === 'seller') {
        require __DIR__ . '/src/Views/layouts/seller_main.php';
    } elseif (in_array($role, ['manager','partner'], true)) {
        require __DIR__ . '/src/Views/layouts/manager_main.php';
    } else {
        require __DIR__ . '/src/Views/layouts/admin_main.php';
    }
}

// Render manager templates (reuse admin views with manager layout)
function viewManager(string $template, array $data = []): void
{
    $pageTitle = $data['pageTitle'] ?? '';
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . "/src/Views/admin/{$template}.php";
    $content = ob_get_clean();
    require __DIR__ . '/src/Views/layouts/manager_main.php';
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

// Simplified layout for auth pages
function viewAuth(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . "/src/Views/{$template}.php";
    $content = ob_get_clean();
    require __DIR__ . '/src/Views/layouts/auth.php';
}

// 4) Простая маршрутизация по URI и методу
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Вспомогательная функция для защиты клиентских маршрутов
function requireClient(): void
{
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, ['client','partner','admin','manager','seller'], true)) {
        header('Location: /login');
        exit;
    }
}

// Вспомогательная функция для защиты админских маршрутов
function requireAdmin(): void
{
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: /login');
        exit;
    }
}

// Manager access (manager or admin)
function requireManager(): void
{
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, ['manager', 'admin'], true)) {
        header('Location: /login');
        exit;
    }
}

// Seller access (seller or admin)
function requireSeller(): void
{
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, ['seller', 'admin'], true)) {
        header('Location: /login');
        exit;
    }
}

// Partner access (partner, manager or admin)
function requirePartner(): void
{
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, ['partner', 'manager', 'admin'], true)) {
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
            'label' => 'В работе',
            'badge' => 'bg-green-100 text-green-800',
            'bg'    => 'bg-green-50',
        ],
        'delivered' => [
            'label' => 'Выполнен',
            'badge' => 'bg-blue-100 text-blue-800',
            'bg'    => 'bg-blue-50',
        ],
        'cancelled' => [
            'label' => 'Отменен',
            'badge' => 'bg-gray-100 text-gray-800',
            'bg'    => 'bg-gray-50',
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
        viewAuth('client/register', [
            'error' => $_GET['error'] ?? null
        ]);
        break;
    case 'POST /register':
        (new App\Controllers\AuthController($pdo))->register();
        break;

    // Логин
    case 'GET /login':
        viewAuth('client/login', [
            'error' => $_GET['error'] ?? null
        ]);
        break;
    case 'POST /login':
        (new App\Controllers\AuthController($pdo))->login();
        break;

    // СМС подтверждения при регистрации
    case 'POST /api/send-reg-code':
        (new App\Controllers\AuthController($pdo, $smsConfig, $telegramConfig, $emailConfig))->sendRegistrationCode();
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
        (new App\Controllers\ClientController($pdo))->catalog();
        break;

    // В блоке switch(..)
    case 'GET /orders':
        requireClient();
        (new App\Controllers\ClientController($pdo))->orders();
        break;

    case 'GET /notifications':
        requireClient();
        (new App\Controllers\ClientController($pdo))->notifications();
        break;
    
    // Добавляем прямо ниже, например:
    case (bool) preg_match('#^GET /orders/(\d+)$#', "$method $uri", $matches):
        requireClient();
        $orderId = (int)$matches[1];
        (new App\Controllers\ClientController($pdo))->showOrder($orderId);
        break;

    case (bool) preg_match('#^GET /content/([^/]+)/([^/]+)$#', "$method $uri", $m):
        (new App\Controllers\ClientController($pdo))->showMaterial($m[1], $m[2]);
        break;
    case (bool) preg_match('#^GET /catalog/([^/]+)/([^/]+)$#', "$method $uri", $m):
        (new App\Controllers\ClientController($pdo))->showProduct($m[2], $m[1]);
        break;
    case (bool) preg_match('#^GET /catalog/([^/]+)$#', "$method $uri", $m):
        (new App\Controllers\ClientController($pdo))->showProductType($m[1]);
        break;
    case (bool) preg_match('#^GET /product/([^/]+)$#', "$method $uri", $m):
        (new App\Controllers\ClientController($pdo))->showProduct($m[1]);
        break;
    case (bool) preg_match('#^GET /type/([^/]+)$#', "$method $uri", $m):
        (new App\Controllers\ClientController($pdo))->showProductType($m[1]);
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
    case 'POST /profile/set-primary':
        requireClient();
        (new App\Controllers\UsersController($pdo))->setPrimaryAddress();
        break;
    case 'POST /profile/delete-address':
        requireClient();
        (new App\Controllers\UsersController($pdo))->deleteAddress();
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
        requireAdmin();
        (new App\Controllers\AdminController($pdo))->dashboard();
        break;

    case 'GET /admin/products':
        requireAdmin();
        (new App\Controllers\ProductsController($pdo))->index();
        break;
    case 'GET /admin/products/edit':
        requireAdmin();
        (new App\Controllers\ProductsController($pdo))->edit();
        break;
    case 'POST /admin/products/save':
        requireAdmin();
        (new App\Controllers\ProductsController($pdo))->save();
        break;
    case 'POST /admin/products/toggle':
        requireAdmin();
        (new App\Controllers\ProductsController($pdo))->toggle();
        break;
    case 'POST /admin/products/update-price':
    case 'GET /admin/products/update-price':
        requireAdmin();
        (new App\Controllers\ProductsController($pdo))->updatePrice();
        break;
    case 'POST /admin/products/update-date':
        requireAdmin();
        (new App\Controllers\ProductsController($pdo))->updateDeliveryDate();
        break;
    case 'POST /admin/products/delete':
        requireAdmin();
        (new App\Controllers\ProductsController($pdo))->delete();
        break;

    case 'GET /admin/product-types':
        requireAdmin();
        (new App\Controllers\ProductTypesController($pdo))->index();
        break;
    case 'GET /admin/product-types/edit':
        requireAdmin();
        (new App\Controllers\ProductTypesController($pdo))->edit();
        break;
    case 'POST /admin/product-types/save':
        requireAdmin();
        (new App\Controllers\ProductTypesController($pdo))->save();
        break;

    case 'GET /admin/orders':
        requireAdmin();
        (new App\Controllers\OrdersController($pdo))->index();
        break;
    case 'GET /admin/orders/create':
        requireAdmin();
        (new App\Controllers\OrdersController($pdo))->create();
        break;
    case 'POST /admin/orders/create':
        requireAdmin();
        (new App\Controllers\OrdersController($pdo))->storeManual();
        break;
    case (bool)preg_match('#^GET /admin/orders/(\d+)$#', "$method $uri", $m):
        requireAdmin();
        (new App\Controllers\OrdersController($pdo))->show((int)$m[1]);
        break;
    case 'POST /admin/orders/assign':
        requireAdmin();
        (new App\Controllers\OrdersController($pdo))->assign();
        break;
    case 'POST /admin/orders/status':
        requireAdmin();
        (new App\Controllers\OrdersController($pdo))->updateStatus();
        break;
    case 'POST /admin/orders/update-item':
        requireAdmin();
        (new App\Controllers\OrdersController($pdo))->updateItem();
        break;
    case 'POST /admin/orders/add-item':
        requireAdmin();
        (new App\Controllers\OrdersController($pdo))->addItem();
        break;
    case 'POST /admin/orders/delete-item':
        requireAdmin();
        (new App\Controllers\OrdersController($pdo))->deleteItem();
        break;
    case 'POST /admin/orders/comment':
        requireAdmin();
        (new App\Controllers\OrdersController($pdo))->updateComment();
        break;
    case 'POST /admin/orders/referral':
        requireAdmin();
        (new App\Controllers\OrdersController($pdo))->updateReferral();
        break;
    case 'POST /admin/orders/update-delivery':
        requireAdmin();
        (new App\Controllers\OrdersController($pdo))->updateDelivery();
        break;
    case 'POST /admin/orders/delete':
        requireAdmin();
        (new App\Controllers\OrdersController($pdo))->delete();
        break;

    case 'GET /admin/slots':
        requireAdmin();
        (new App\Controllers\SlotsController($pdo))->index();
        break;
    case 'GET /admin/slots/edit':
        requireAdmin();
        (new App\Controllers\SlotsController($pdo))->edit();
        break;
    case 'POST /admin/slots/save':
        requireAdmin();
        (new App\Controllers\SlotsController($pdo))->save();
        break;
    case 'POST /admin/slots/delete':
        requireAdmin();
        (new App\Controllers\SlotsController($pdo))->delete();
        break;

    case 'GET /admin/coupons':
        requireAdmin();
        (new App\Controllers\CouponsController($pdo))->index();
        break;
    case 'GET /admin/coupons/edit':
        requireAdmin();
        (new App\Controllers\CouponsController($pdo))->edit();
        break;
    case 'POST /admin/coupons/save':
        requireAdmin();
        (new App\Controllers\CouponsController($pdo))->save();
        break;
    case 'POST /admin/coupons/generate':
        requireAdmin();
        (new App\Controllers\CouponsController($pdo))->generate();
        break;

    case 'GET /admin/content':
        requireAdmin();
        (new App\Controllers\ContentController($pdo))->categories();
        break;
    case 'GET /admin/content/category/edit':
        requireAdmin();
        (new App\Controllers\ContentController($pdo))->editCategory();
        break;
    case 'POST /admin/content/category/save':
        requireAdmin();
        (new App\Controllers\ContentController($pdo))->saveCategory();
        break;
    case 'GET /admin/content/materials':
        requireAdmin();
        (new App\Controllers\ContentController($pdo))->materials();
        break;
    case 'GET /admin/content/materials/edit':
        requireAdmin();
        (new App\Controllers\ContentController($pdo))->editMaterial();
        break;
    case 'POST /admin/content/materials/save':
        requireAdmin();
        (new App\Controllers\ContentController($pdo))->saveMaterial();
        break;

    case 'GET /admin/users':
        requireAdmin();
        (new App\Controllers\UsersController($pdo))->index();
        break;
    case 'GET /admin/users/search':
        requireAdmin();
        (new App\Controllers\UsersController($pdo))->searchPhone();
        break;
    case 'GET /admin/users/addresses':
        requireAdmin();
        (new App\Controllers\UsersController($pdo))->addresses();
        break;
    case (bool)preg_match('#^GET /admin/users/(\d+)$#', "$method $uri", $m):
        requireAdmin();
        (new App\Controllers\UsersController($pdo))->show((int)$m[1]);
        break;
    case 'GET /admin/users/edit':
        requireAdmin();
        (new App\Controllers\UsersController($pdo))->edit();
        break;
    case 'POST /admin/users/save':
        requireAdmin();
        (new App\Controllers\UsersController($pdo))->save();
        break;
    case 'POST /admin/users/delete':
        requireAdmin();
        (new App\Controllers\UsersController($pdo))->delete();
        break;
    case 'POST /admin/users/toggle-block':
        requireAdmin();
        (new App\Controllers\UsersController($pdo))->toggleBlock();
        break;
    case 'POST /admin/users/reset-balance':
        requireAdmin();
        (new App\Controllers\UsersController($pdo))->resetRubBalance();
        break;
    case 'POST /admin/users/add-address':
        requireAdmin();
        (new App\Controllers\UsersController($pdo))->addAddressAdmin();
        break;
    case 'POST /admin/users/delete-address':
        requireAdmin();
        (new App\Controllers\UsersController($pdo))->deleteAddressAdmin();
        break;

    case 'GET /admin/sellers':
        requireAdmin();
        (new App\Controllers\SellersController($pdo))->index();
        break;
    case 'GET /admin/sellers/edit':
        requireAdmin();
        (new App\Controllers\SellersController($pdo))->edit();
        break;
    case 'POST /admin/sellers/save':
        requireAdmin();
        (new App\Controllers\SellersController($pdo))->save();
        break;
    case (bool)preg_match('#^GET /admin/sellers/(\\d+)$#', "$method $uri", $m):
        requireAdmin();
        (new App\Controllers\SellersController($pdo))->show((int)$m[1]);
        break;

    case 'GET /admin/apps':
        requireAdmin();
        (new App\Controllers\AppsController($pdo))->index();
        break;
    case 'POST /admin/apps/sitemap/toggle':
        requireAdmin();
        (new App\Controllers\AppsController($pdo))->toggleSitemap();
        break;
    case 'GET /admin/apps/sitemap':
        requireAdmin();
        (new App\Controllers\AppsController($pdo))->sitemapSettings();
        break;
    case 'POST /admin/apps/sitemap/generate':
        requireAdmin();
        (new App\Controllers\AppsController($pdo))->generateSitemap();
        break;
    case 'POST /admin/apps/sitemap/toggle-item':
        requireAdmin();
        (new App\Controllers\AppsController($pdo))->toggleItem();
        break;

    case 'GET /admin/apps/seo':
        requireAdmin();
        (new App\Controllers\SeoController($pdo))->index();
        break;
    case 'GET /admin/apps/seo/edit':
        requireAdmin();
        (new App\Controllers\SeoController($pdo))->edit();
        break;
    case 'POST /admin/apps/seo/save':
        requireAdmin();
        (new App\Controllers\SeoController($pdo))->save();
        break;

    case 'GET /admin/settings':
        requireAdmin();
        (new App\Controllers\SettingsController($pdo))->index();
        break;
    case 'POST /admin/settings':
        requireAdmin();
        (new App\Controllers\SettingsController($pdo))->save();
        break;

    // === ROUTES FOR MANAGERS ===
    case 'GET /manager/dashboard':
        requireManager();
        (new App\Controllers\AdminController($pdo))->dashboard();
        break;
    case 'GET /manager/profile':
        requireManager();
        (new App\Controllers\UsersController($pdo))->managerProfile();
        break;
    case 'POST /manager/payout':
        requireManager();
        (new App\Controllers\UsersController($pdo))->requestPayout();
        break;
    case 'GET /manager/orders':
        requireManager();
        (new App\Controllers\OrdersController($pdo))->index();
        break;
    case 'GET /manager/orders/create':
        requireManager();
        (new App\Controllers\OrdersController($pdo))->create();
        break;
    case 'POST /manager/orders/create':
        requireManager();
        (new App\Controllers\OrdersController($pdo))->storeManual();
        break;
    case (bool)preg_match('#^GET /manager/orders/(\d+)$#', "$method $uri", $m):
        requireManager();
        (new App\Controllers\OrdersController($pdo))->show((int)$m[1]);
        break;
    case 'POST /manager/orders/assign':
        requireManager();
        (new App\Controllers\OrdersController($pdo))->assign();
        break;
    case 'POST /manager/orders/status':
        requireManager();
        (new App\Controllers\OrdersController($pdo))->updateStatus();
        break;
    case 'POST /manager/orders/update-item':
        requireManager();
        (new App\Controllers\OrdersController($pdo))->updateItem();
        break;
    case 'POST /manager/orders/add-item':
        requireManager();
        (new App\Controllers\OrdersController($pdo))->addItem();
        break;
    case 'POST /manager/orders/delete-item':
        requireManager();
        (new App\Controllers\OrdersController($pdo))->deleteItem();
        break;
    case 'POST /manager/orders/comment':
        requireManager();
        (new App\Controllers\OrdersController($pdo))->updateComment();
        break;
    case 'POST /manager/orders/referral':
        requireManager();
        (new App\Controllers\OrdersController($pdo))->updateReferral();
        break;
    case 'POST /manager/orders/update-delivery':
        requireManager();
        (new App\Controllers\OrdersController($pdo))->updateDelivery();
        break;
    case 'POST /manager/orders/delete':
        requireManager();
        (new App\Controllers\OrdersController($pdo))->delete();
        break;

    case 'GET /manager/products':
        requireManager();
        (new App\Controllers\ProductsController($pdo))->index();
        break;
    case 'GET /manager/products/edit':
        requireManager();
        (new App\Controllers\ProductsController($pdo))->edit();
        break;
    case 'POST /manager/products/save':
        requireManager();
        (new App\Controllers\ProductsController($pdo))->save();
        break;
    case 'POST /manager/products/toggle':
        requireManager();
        (new App\Controllers\ProductsController($pdo))->toggle();
        break;
    case 'POST /manager/products/update-price':
    case 'GET /manager/products/update-price':
        requireManager();
        (new App\Controllers\ProductsController($pdo))->updatePrice();
        break;
    case 'POST /manager/products/update-date':
        requireManager();
        (new App\Controllers\ProductsController($pdo))->updateDeliveryDate();
        break;
    case 'POST /manager/products/delete':
        requireManager();
        (new App\Controllers\ProductsController($pdo))->delete();
        break;

    case 'GET /manager/users':
        requireManager();
        (new App\Controllers\UsersController($pdo))->index();
        break;
    case 'GET /manager/users/search':
        requireManager();
        (new App\Controllers\UsersController($pdo))->searchPhone();
        break;
    case 'GET /manager/users/addresses':
        requireManager();
        (new App\Controllers\UsersController($pdo))->addresses();
        break;
    case (bool)preg_match('#^GET /manager/users/(\d+)$#', "$method $uri", $m):
        requireManager();
        (new App\Controllers\UsersController($pdo))->show((int)$m[1]);
        break;
    case 'GET /manager/users/edit':
        requireManager();
        (new App\Controllers\UsersController($pdo))->edit();
        break;
    case 'POST /manager/users/save':
        requireManager();
        (new App\Controllers\UsersController($pdo))->save();
        break;
    case 'POST /manager/users/toggle-block':
        requireManager();
        (new App\Controllers\UsersController($pdo))->toggleBlock();
        break;
    case 'POST /manager/users/add-address':
        requireManager();
        (new App\Controllers\UsersController($pdo))->addAddressAdmin();
        break;
    case 'POST /manager/users/delete-address':
        requireManager();
        (new App\Controllers\UsersController($pdo))->deleteAddressAdmin();
        break;

    // === ROUTES FOR PARTNERS ===
    case 'GET /partner/dashboard':
        requirePartner();
        (new App\Controllers\AdminController($pdo))->dashboard();
        break;
    case 'GET /partner/profile':
        requirePartner();
        (new App\Controllers\UsersController($pdo))->partnerProfile();
        break;
    case 'POST /partner/payout':
        requirePartner();
        (new App\Controllers\UsersController($pdo))->requestPayout();
        break;
    case 'GET /partner/orders':
        requirePartner();
        (new App\Controllers\OrdersController($pdo))->index();
        break;
    case 'GET /partner/orders/create':
        requirePartner();
        (new App\Controllers\OrdersController($pdo))->create();
        break;
    case 'POST /partner/orders/create':
        requirePartner();
        (new App\Controllers\OrdersController($pdo))->storeManual();
        break;
    case (bool)preg_match('#^GET /partner/orders/(\d+)$#', "$method $uri", $m):
        requirePartner();
        (new App\Controllers\OrdersController($pdo))->show((int)$m[1]);
        break;
    case 'POST /partner/orders/assign':
        requirePartner();
        (new App\Controllers\OrdersController($pdo))->assign();
        break;
    case 'POST /partner/orders/status':
        requirePartner();
        (new App\Controllers\OrdersController($pdo))->updateStatus();
        break;
    case 'POST /partner/orders/update-item':
        requirePartner();
        (new App\Controllers\OrdersController($pdo))->updateItem();
        break;
    case 'POST /partner/orders/add-item':
        requirePartner();
        (new App\Controllers\OrdersController($pdo))->addItem();
        break;
    case 'POST /partner/orders/delete-item':
        requirePartner();
        (new App\Controllers\OrdersController($pdo))->deleteItem();
        break;
    case 'POST /partner/orders/comment':
        requirePartner();
        (new App\Controllers\OrdersController($pdo))->updateComment();
        break;
    case 'POST /partner/orders/referral':
        requirePartner();
        (new App\Controllers\OrdersController($pdo))->updateReferral();
        break;
    case 'POST /partner/orders/update-delivery':
        requirePartner();
        (new App\Controllers\OrdersController($pdo))->updateDelivery();
        break;
    case 'POST /partner/orders/delete':
        requirePartner();
        (new App\Controllers\OrdersController($pdo))->delete();
        break;

    case 'GET /partner/products':
        requirePartner();
        (new App\Controllers\ProductsController($pdo))->index();
        break;
    case 'GET /partner/products/edit':
        requirePartner();
        (new App\Controllers\ProductsController($pdo))->edit();
        break;
    case 'POST /partner/products/save':
        requirePartner();
        (new App\Controllers\ProductsController($pdo))->save();
        break;
    case 'POST /partner/products/toggle':
        requirePartner();
        (new App\Controllers\ProductsController($pdo))->toggle();
        break;
    case 'POST /partner/products/update-date':
        requirePartner();
        (new App\Controllers\ProductsController($pdo))->updateDeliveryDate();
        break;
    case 'POST /partner/products/delete':
        requirePartner();
        (new App\Controllers\ProductsController($pdo))->delete();
        break;

    case 'GET /partner/users':
        requirePartner();
        (new App\Controllers\UsersController($pdo))->index();
        break;
    case 'GET /partner/users/search':
        requirePartner();
        (new App\Controllers\UsersController($pdo))->searchPhone();
        break;
    case 'GET /partner/users/addresses':
        requirePartner();
        (new App\Controllers\UsersController($pdo))->addresses();
        break;
    case (bool)preg_match('#^GET /partner/users/(\d+)$#', "$method $uri", $m):
        requirePartner();
        (new App\Controllers\UsersController($pdo))->show((int)$m[1]);
        break;
    case 'GET /partner/users/edit':
        requirePartner();
        (new App\Controllers\UsersController($pdo))->edit();
        break;
    case 'POST /partner/users/save':
        requirePartner();
        (new App\Controllers\UsersController($pdo))->save();
        break;
    case 'POST /partner/users/toggle-block':
        requirePartner();
        (new App\Controllers\UsersController($pdo))->toggleBlock();
        break;
    case 'POST /partner/users/add-address':
        requirePartner();
        (new App\Controllers\UsersController($pdo))->addAddressAdmin();
        break;
    case 'POST /partner/users/delete-address':
        requirePartner();
        (new App\Controllers\UsersController($pdo))->deleteAddressAdmin();
        break;

    // === ROUTES FOR SELLERS ===
    case 'GET /seller/dashboard':
        requireSeller();
        (new App\Controllers\SellerController($pdo))->dashboard();
        break;
    case 'GET /seller/profile':
        requireSeller();
        (new App\Controllers\UsersController($pdo))->sellerProfile();
        break;
    case 'GET /seller/orders':
        requireSeller();
        (new App\Controllers\SellerController($pdo))->orders();
        break;
    case 'GET /seller/products':
        requireSeller();
        (new App\Controllers\ProductsController($pdo))->index();
        break;
    case 'GET /seller/products/edit':
        requireSeller();
        (new App\Controllers\ProductsController($pdo))->edit();
        break;
    case 'POST /seller/products/save':
        requireSeller();
        (new App\Controllers\ProductsController($pdo))->save();
        break;
    case 'POST /seller/products/toggle':
        requireSeller();
        (new App\Controllers\ProductsController($pdo))->toggle();
        break;
    case 'POST /seller/products/update-date':
        requireSeller();
        (new App\Controllers\ProductsController($pdo))->updateDeliveryDate();
        break;
    case 'POST /seller/products/delete':
        requireSeller();
        (new App\Controllers\ProductsController($pdo))->delete();
        break;
    case 'GET /seller/product-types':
        requireSeller();
        (new App\Controllers\ProductTypesController($pdo))->index();
        break;
    case 'GET /seller/product-types/edit':
        requireSeller();
        (new App\Controllers\ProductTypesController($pdo))->edit();
        break;
    case 'POST /seller/product-types/save':
        requireSeller();
        (new App\Controllers\ProductTypesController($pdo))->save();
        break;

    // Любые другие запросы — 404
    default:
        http_response_code(404);
        echo "Страница не найдена";
        break;
}
