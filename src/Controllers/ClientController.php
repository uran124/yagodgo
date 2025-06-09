<?php
namespace App\Controllers;

use PDO;

class ClientController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Пересчитать сумму корзины и сохранить в сессию */
    private function refreshCartTotal(): void
    {
        $userId = $_SESSION['user_id'] ?? 0;
        $stmt   = $this->pdo->prepare(
            "SELECT COALESCE(SUM(quantity * unit_price),0) FROM cart_items WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        $_SESSION['cart_total'] = (float)$stmt->fetchColumn();
    }

    /** Главная: последние 4 товара */
    public function home(): void
    {
        $popular = $this->pdo->query(
            "SELECT p.id,
                    t.name AS product,
                    p.variety,
                    p.description,
                    p.origin_country,
                    p.box_size,
                    p.box_unit,
                    p.price,
                    p.sale_price,
                    p.is_active,
                    p.image_path,
                    p.delivery_date
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             WHERE p.is_active = 1
             ORDER BY p.id DESC
             LIMIT 4"
        )->fetchAll(PDO::FETCH_ASSOC);

        $debugData = [
            'popularCount' => count($popular),
            'today'        => date('Y-m-d'),
        ];

        view('client/home', [
            'popularProducts' => $popular,
            'userName'        => $_SESSION['name'] ?? null,
            'debugData'       => $debugData,
        ]);
    }

    /** Каталог: сортировка по наличию/дате */
    public function catalog(): void
    {
        $today = date('Y-m-d');
        $all = $this->pdo->query(
            "SELECT
                 p.id,
                 t.name        AS product,
                 p.variety,
                 p.description,
                 p.origin_country,
                 p.box_size,
                 p.box_unit,
                 p.price,
                 p.sale_price,
                 p.is_active,
                 p.image_path,
                 p.delivery_date
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             WHERE p.is_active = 1
             ORDER BY
               CASE WHEN p.sale_price > 0 THEN 0 ELSE 1 END,
               CASE
                 WHEN p.delivery_date IS NULL    THEN 3
                 WHEN p.delivery_date > '$today' THEN 2
                 ELSE 1
               END,
               COALESCE(p.delivery_date, '9999-12-31'),
               p.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    
        $debugData = [
            'productsCount' => count($all),
            'today'         => $today,
        ];
    
        view('client/catalog', [
            'products'  => $all,
            'userName'  => $_SESSION['name'] ?? null,
            'debugData' => $debugData,
        ]);
    }

    /** Список товаров в корзине */
public function cart(): void
{
    requireClient();
    $userId = $_SESSION['user_id'];

    // 1) Получаем все товары из корзины, включая sale_price и is_active
    $stmt = $this->pdo->prepare(
        "SELECT 
            ci.product_id,
            ci.quantity,
            ci.unit_price,
            p.variety,
            t.name AS product,
            p.box_size,
            p.box_unit,
            p.image_path,
            p.delivery_date,
            p.sale_price,
            p.is_active
         FROM cart_items ci
         JOIN products p ON p.id = ci.product_id
         JOIN product_types t ON t.id = p.product_type_id
         WHERE ci.user_id = ?"
    );
    $stmt->execute([$userId]);
    $rawItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2) Подставляем в каждый элемент выбранную дату доставки из сессии (или "сегодня")
    $items = [];
    foreach ($rawItems as $row) {
        $pid = $row['product_id'];
        $deliveryDate = $_SESSION['delivery_date'][$pid] ?? date('Y-m-d');

        $items[] = [
            'product_id'    => $pid,
            'product'       => $row['product'],
            'variety'       => $row['variety'],
            'image_path'    => $row['image_path'],
            'unit_price'    => $row['unit_price'],
            'quantity'      => $row['quantity'],
            'delivery_date' => $deliveryDate,
            'sale_price'    => $row['sale_price'],
            'is_active'     => $row['is_active'],
            'box_size'      => $row['box_size'],
            'box_unit'      => $row['box_unit'],
        ];
    }

    // 3) Пересчитываем общую сумму корзины
    $this->refreshCartTotal();

    // 4) Получаем баланс баллов пользователя
    $pointsStmt = $this->pdo->prepare("SELECT points_balance FROM users WHERE id = ?");
    $pointsStmt->execute([$userId]);
    $pointsBalance = (int)$pointsStmt->fetchColumn();

    // 5) Составляем debug-данные (при необходимости)
    $debugData = [
        'raw_items'     => $items,
        'cart_total'    => $_SESSION['cart_total'] ?? 0,
        'pointsBalance' => $pointsBalance,
        'today'         => date('Y-m-d'),
    ];

    // 6) Рендерим шаблон
    view('client/cart', [
        'items'      => $items,
        'userName'   => $_SESSION['name'] ?? null,
        'debugData'  => $debugData,
    ]);
}


    /** Добавить товар в корзину + сохранить выбранную дату */
    public function addToCart(): void
    {
        requireClient();
        $userId    = $_SESSION['user_id'];
        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity  = (float)($_POST['quantity'] ?? 1.0);

        // читаем выбор даты
        $dateOpt = $_POST['delivery_date'][$productId] ?? null;
        if ($dateOpt) {
            $_SESSION['delivery_date'][$productId] = $dateOpt;
        }

        if ($productId && $quantity > 0) {
            $priceStmt = $this->pdo->prepare("SELECT price FROM products WHERE id = ?");
            $priceStmt->execute([$productId]);
            $price = (float)$priceStmt->fetchColumn();

            $this->pdo->prepare(
                "INSERT INTO cart_items (user_id, product_id, quantity, unit_price)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)"
            )->execute([$userId, $productId, $quantity, $price]);
        }

        $this->refreshCartTotal();

        // После добавления не переходим в корзину, а возвращаемся на предыдущую страницу
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        header('Location: ' . $referer);
        exit;
    }

    /** Обновить количество в корзине */
    public function updateCart(): void
    {
        requireClient();
        $userId    = $_SESSION['user_id'];
        $productId = (int)($_POST['product_id'] ?? 0);
        $action    = $_POST['action'] ?? '';

        $stmt = $this->pdo->prepare(
            "SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?"
        );
        $stmt->execute([$userId, $productId]);
        $current = (float)$stmt->fetchColumn();

        if ($current > 0) {
            $newQty = match($action) {
                'increase' => $current + 1,
                'decrease' => max(1, $current - 1),
                default    => $current,
            };
            $this->pdo->prepare(
                "UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?"
            )->execute([$newQty, $userId, $productId]);
        }

        $this->refreshCartTotal();
        header('Location: /cart');
        exit;
    }

    /** Удалить один товар из корзины */
    public function removeFromCart(): void
    {
        requireClient();
        $userId    = $_SESSION['user_id'];
        $productId = (int)($_POST['product_id'] ?? 0);

        $this->pdo->prepare(
            "DELETE FROM cart_items WHERE user_id = ? AND product_id = ?"
        )->execute([$userId, $productId]);

        unset($_SESSION['delivery_date'][$productId]);
        $this->refreshCartTotal();
        header('Location: /cart');
        exit;
    }

    /** Очистить всю корзину */
    public function clearCart(): void
    {
        requireClient();
        $userId = $_SESSION['user_id'];

        $this->pdo->prepare("DELETE FROM cart_items WHERE user_id = ?")
                  ->execute([$userId]);

        $_SESSION['delivery_date'] = [];
        $this->refreshCartTotal();
        header('Location: /cart');
        exit;
    }

    /**
     * Форма подтверждения (checkout):
     * - группирует товары по дате
     * - рассчитывает, сколько баллов можно списать (до 30 % от общей суммы)
     * - передаёт в шаблон: группы, subtotal, баланс баллов, сколько списать, сумма после списания, адрес
     */
    public function checkout(): void
    {
        requireClient();
        $userId = $_SESSION['user_id'];
    
        // 0) Если в GET переданы новые даты для продуктов, сохраняем их в сессии:
        if (!empty($_GET['delivery_date']) && is_array($_GET['delivery_date'])) {
            foreach ($_GET['delivery_date'] as $pid => $date) {
                // Проверим формат даты, например, YYYY-MM-DD (можно добавить более строгую валидацию)
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $_SESSION['delivery_date'][(int)$pid] = $date;
                }
            }
        }
    
        // 1) Получаем все товары из корзины вместе с основной информацией
        $stmt = $this->pdo->prepare(
            "SELECT 
                ci.product_id, 
                ci.quantity, 
                ci.unit_price,
                p.variety, 
                t.name AS product, 
                p.box_size, 
                p.box_unit, 
                p.image_path 
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             JOIN product_types t ON t.id = p.product_type_id
             WHERE ci.user_id = ?"
        );
        $stmt->execute([$userId]);
        $rawItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        // 2) Прикрепляем к каждому товару дату из сессии (или сегодняшнюю, если до этого не указывалось)
        foreach ($rawItems as &$it) {
            $pid = $it['product_id'];
            $it['delivery_date'] = $_SESSION['delivery_date'][$pid] 
                                  ?? date('Y-m-d');
        }
        unset($it);
    
        // 3) Группируем товары по дате доставки
        $groups = [];
        foreach ($rawItems as $it) {
            $dateKey = $it['delivery_date'];
            if (!isset($groups[$dateKey])) {
                $groups[$dateKey] = [];
            }
            $groups[$dateKey][] = $it;
        }
    
        // 4) Считаем исходную сумму корзины (subtotal)
        $subtotal = 0;
        foreach ($rawItems as $it) {
            $subtotal += $it['quantity'] * $it['unit_price'];
        }
    
        // 5) Получаем баланс баллов пользователя (points_balance)
        $pointsStmt = $this->pdo->prepare("SELECT points_balance FROM users WHERE id = ?");
        $pointsStmt->execute([$userId]);
        $pointsBalance = (int)$pointsStmt->fetchColumn();
    
        // 6) Рассчитываем, сколько баллов можно списать (до 30% от subtotal)
        $maxAllowedByPercent = (int)floor($subtotal * 0.30);
        $pointsToUse = min($pointsBalance, $maxAllowedByPercent);
    
        // 7) Общая сумма после списания баллов
        $totalAfterPoints = $subtotal - $pointsToUse;
    
        // 8) Берём текущий адрес пользователя
        $addrStmt = $this->pdo->prepare("SELECT street FROM addresses WHERE user_id = ?");
        $addrStmt->execute([$userId]);
        $address = $addrStmt->fetchColumn() ?: '';
    
        // 9) Собираем debug-данные
        $debugData = [
            'raw_items'        => $rawItems,
            'groups'           => $groups,
            'subtotal'         => $subtotal,
            'pointsBalance'    => $pointsBalance,
            'pointsToUse'      => $pointsToUse,
            'totalAfterPoints' => $totalAfterPoints,
            'today'            => date('Y-m-d'),
            'address'          => $address,
            'session_delivery' => $_SESSION['delivery_date'],
        ];
    
        // 10) Рендерим шаблон, передаём всё в view
        view('client/checkout', [
            'groups'           => $groups,
            'subtotal'         => $subtotal,
            'pointsBalance'    => $pointsBalance,
            'pointsToUse'      => $pointsToUse,
            'totalAfterPoints' => $totalAfterPoints,
            'address'          => $address,
            'userName'         => $_SESSION['name'] ?? null,
            'today'            => date('Y-m-d'),
            'debugData'        => $debugData,
            'couponError'      => $_GET['coupon_error'] ?? null,
        ]);
    }

    /**
     * Обработка создания заказа(й):
     * - группирует товары, применяет списание баллов (usage)
     * - создаёт заказы по каждой дате с учётом распределённой скидки
     * - начисляет реферальный бонус (accrual) пригласившему, если есть
     */
     
    public function placeOrder(): void
{
    requireClient();
    $userId = $_SESSION['user_id'];

    // 1) Получаем товары из корзины
    $stmt = $this->pdo->prepare(
      "SELECT ci.product_id, ci.quantity, ci.unit_price,
              p.box_size, p.box_unit, t.name AS product, p.variety
       FROM cart_items ci
       JOIN products p ON p.id = ci.product_id
       JOIN product_types t ON t.id = p.product_type_id
       WHERE ci.user_id = ?"
    );
    $stmt->execute([$userId]);
    $rawItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2) Группируем товары по дате доставки, храня выбранную дату из сессии
    $itemsByDate = [];
    foreach ($rawItems as $it) {
        $pid = $it['product_id'];
        $dateKey = $_SESSION['delivery_date'][$pid] ?? date('Y-m-d');
        if (!isset($itemsByDate[$dateKey])) {
            $itemsByDate[$dateKey] = [];
        }
        $itemsByDate[$dateKey][$pid] = [
            'quantity'   => $it['quantity'],
            'unit_price' => $it['unit_price'],
        ];
    }

    // 3) Считаем общий чек
    $allTotal = 0;
    foreach ($itemsByDate as $block) {
        foreach ($block as $data) {
            $allTotal += $data['quantity'] * $data['unit_price'];
        }
    }

    // 4) Узнаём баланс баллов и реферера
    $stmt = $this->pdo->prepare("SELECT points_balance, referred_by FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userRow      = $stmt->fetch(PDO::FETCH_ASSOC);
    $pointsBalance = (int)$userRow['points_balance'];
    $referredBy    = $userRow['referred_by'] ? (int)$userRow['referred_by'] : null;

    // 4.1) Проверяем промокод
    $couponCode      = trim($_POST['coupon_code'] ?? '');
    $discountPercent = 0.0;
    $couponPoints    = 0;
    if ($couponCode !== '') {
        $stmt = $this->pdo->prepare(
            "SELECT code, type, discount, points FROM coupons WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE())"
        );
        $stmt->execute([$couponCode]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$coupon) {
            header('Location: /checkout?coupon_error=Неверный+купон');
            exit;
        }
        if ($coupon['type'] === 'discount') {
            $discountPercent = (float)$coupon['discount'];
        } elseif ($coupon['type'] === 'points') {
            $couponPoints = (int)$coupon['points'];
        }
    }

    // 5) Считаем, сколько баллов списать (до 30% от суммы)
    $maxPossible  = floor($allTotal * 0.3);
    $pointsToUse  = min($pointsBalance, $maxPossible);

    $this->pdo->beginTransaction();

    // 6) Если списываем баллы — обновляем баланс и фиксируем транзакцию
    if ($pointsToUse > 0) {
        $this->pdo->prepare(
          "UPDATE users SET points_balance = points_balance - ? WHERE id = ?"
        )->execute([$pointsToUse, $userId]);
    
        // Здесь transaction_type заменён на 'usage', 
        // чтобы совпадало с тем, что хранится в ENUM
        $stmtTx = $this->pdo->prepare(
            "INSERT INTO points_transactions
              (user_id, amount, transaction_type, description, order_id, created_at)
             VALUES (?, ?, 'usage', 'Скидка за заказ', NULL, NOW())"
        );
        $stmtTx->execute([$userId, -$pointsToUse]);
    }

    // 7) Распределяем списанные баллы и купон с баллами только на первый заказ
    $discountsByDate = [];
    $pointsTotal = $pointsToUse + $couponPoints;
    $firstKey = array_key_first($itemsByDate);
    foreach ($itemsByDate as $dateKey => $block) {
        $discountsByDate[$dateKey] = 0;
    }
    if ($pointsTotal > 0 && $firstKey !== null) {
        $discountsByDate[$firstKey] = min($pointsTotal, $allTotal);
    }

    // 8) Обрабатываем адреса: для каждой даты либо свой, либо default
    $postedAddresses = $_POST['address_id'] ?? [];
    $defaultAddress  = $postedAddresses['default'] ?? '';
    $addressIds = [];
    foreach ($itemsByDate as $dateKey => $_) {
        $addrInput = $postedAddresses[$dateKey] ?? $defaultAddress;
        $addressIds[$dateKey] = $this->ensureAddress($userId, $addrInput);
    }

    // 9) СОЗДАЁМ ЗАКАЗЫ ПО КАЖДОЙ ДАТЕ, учитываем дату и слот
        foreach ($itemsByDate as $dateKey => $block) {
        // (7.1) Считаем сумму по блоку и применяем скидку
        $blockSum = 0;
        foreach ($block as $data) {
            $blockSum += $data['quantity'] * $data['unit_price'];
        }
        $pointsDiscount = $discountsByDate[$dateKey] ?? 0;
        $couponDiscount = 0;
        if ($discountPercent > 0) {
            $couponDiscount = (int) floor(($blockSum - $pointsDiscount) * ($discountPercent / 100));
        }
        $finalSum = $blockSum - $pointsDiscount - $couponDiscount;

        $deliverySlot = $_POST['slot_id'][$dateKey] ?? ''; // из формы

        // (7.2) Вставляем заказ. Поскольку у таблицы orders есть колонки discount_applied, points_used, points_accrued, нужно задать их:
        $stmtOrder = $this->pdo->prepare(
            "INSERT INTO orders
               (user_id, address_id, status, total_amount,
                discount_applied, points_used, points_accrued, coupon_code,
                delivery_date, delivery_slot, created_at)
             VALUES (?, ?, 'new', ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $pointsAccrued = 0; // пока 0, начислим ниже, если надо
        $stmtOrder->execute([
            $userId,
            $addressIds[$dateKey],
            $finalSum,
            $couponDiscount,  // discount_applied = скидка по купону %
            $pointsDiscount,  // points_used = списанные баллы
            $pointsAccrued,   // points_accrued = пока 0
            $couponCode,
            $dateKey,
            $deliverySlot
        ]);
        $orderId = (int)$this->pdo->lastInsertId();

        // (7.3) Вставляем позиции в order_items — без изменений

        // (7.4) Начисляем реферальный бонус пригласившему, если он есть
        if ($referredBy) {
            $refBonus = (int) floor($finalSum * 0.03);
            if ($refBonus > 0) {
                // Обновляем баланс пригласившего
                $this->pdo->prepare(
                  "UPDATE users SET points_balance = points_balance + ? WHERE id = ?"
                )->execute([$refBonus, $referredBy]);

                // Вставляем транзакцию с transaction_type = 'accrual'
                $stmtRefTx = $this->pdo->prepare(
                  "INSERT INTO points_transactions
                     (user_id, amount, transaction_type, description, order_id, created_at)
                   VALUES (?, ?, 'accrual', ?, ?, NOW())"
                );
                $desc = "Бонус за заказ №{$orderId}";
                $stmtRefTx->execute([$referredBy, $refBonus, $desc, $orderId]);

                // Обновляем поле points_accrued в самом заказе
                $this->pdo->prepare(
                    "UPDATE orders SET points_accrued = ? WHERE id = ?"
                )->execute([$refBonus, $orderId]);
            }
        }
    }

    // 10) Очищаем корзину
    $this->pdo->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$userId]);
    $_SESSION['delivery_date'] = [];
    $this->refreshCartTotal();

    $this->pdo->commit();

    header('Location: /orders');
    exit;
}





public function showOrder(int $orderId): void
{
    requireClient();
    $userId = $_SESSION['user_id'] ?? 0;
    $role = $_SESSION['role'] ?? '';

    // 1) Получаем данные о заказе
    $stmt = $this->pdo->prepare(
      "SELECT 
         o.id,
         o.user_id,
         o.address_id,
         o.status,
         o.total_amount,
         o.delivery_date,
         o.delivery_slot,
         o.created_at,
         u.name AS client_name,
         a.street AS address
       FROM orders o
       JOIN users u ON u.id = o.user_id
       LEFT JOIN addresses a ON a.id = o.address_id
       WHERE o.id = ?"
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo "Заказ не найден";
        exit;
    }

    // 2) Проверяем, что текущий пользователь — владелец заказа или админ
    if ($order['user_id'] !== $userId && $role !== 'admin') {
        http_response_code(403);
        echo "Доступ запрещён";
        exit;
    }

    // 3) Достаём позиции этого заказа
    $stmtItems = $this->pdo->prepare(
      "SELECT 
         oi.product_id,
         oi.quantity,
         oi.unit_price,
         p.variety,
         t.name AS product_name
       FROM order_items oi
       JOIN products p ON p.id = oi.product_id
       JOIN product_types t ON t.id = p.product_type_id
       WHERE oi.order_id = ?"
    );
    $stmtItems->execute([$orderId]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 4) Отправляем всё в view. Файл-шаблон: src/Views/client/order_show.php
    view('client/order_show', [
        'order'    => $order,
        'items'    => $items,
        'userName' => $_SESSION['name'] ?? null,
    ]);
}







    /** Обновить/вставить адрес */
    private function ensureAddress(int $userId, string $street): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM addresses WHERE user_id = ?");
        $stmt->execute([$userId]);
        if ($id = $stmt->fetchColumn()) {
            $this->pdo->prepare("UPDATE addresses SET street = ? WHERE id = ?")
                      ->execute([$street, $id]);
            return $id;
        }
        $this->pdo->prepare("INSERT INTO addresses (user_id,street,created_at) VALUES (?, ?, NOW())")
                  ->execute([$userId, $street]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Список моих заказов */
    public function orders(): void
    {
        requireClient();
        $userId = $_SESSION['user_id'];
        $stmt = $this->pdo->prepare(
            "SELECT o.id, o.status, o.total_amount, o.created_at, o.delivery_date, o.delivery_slot, a.street AS address
             FROM orders o
             LEFT JOIN addresses a ON a.id = o.address_id
             WHERE o.user_id = ?
             ORDER BY o.created_at DESC"
        );
        $stmt->execute([$userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Подтягиваем позиции для каждого заказа
        $itemsStmt = $this->pdo->prepare(
            "SELECT t.name AS product_name, p.variety, oi.quantity
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             JOIN product_types t ON t.id = p.product_type_id
             WHERE oi.order_id = ?"
        );
        foreach ($orders as &$o) {
            $itemsStmt->execute([$o['id']]);
            $o['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $debugData = [
            'ordersCount' => count($orders),
            'today'       => date('Y-m-d'),
        ];

        view('client/v2/orders', [
            'orders'    => $orders,
            'userName'  => $_SESSION['name'] ?? null,
            'debugData' => $debugData,
        ]);
    }

    /** Профиль */
    public function profile(): void
    {
        requireClient();
        $userId = $_SESSION['user_id'];
        $stmt = $this->pdo->prepare("SELECT name, phone FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $addr = $this->pdo->prepare("SELECT street FROM addresses WHERE user_id = ?");
        $addr->execute([$userId]);
        $address = $addr->fetchColumn() ?: '';

        $debugData = [
            'userInfo' => $user,
            'address'  => $address,
            'today'    => date('Y-m-d'),
        ];

        view('client/profile', [
            'user'      => $user,
            'address'   => $address,
            'userName'  => $_SESSION['name'] ?? null,
            'debugData' => $debugData,
        ]);
    }

    /** Обновление профиля */
    public function updateProfile(): void
    {
        requireClient();
        $userId  = $_SESSION['user_id'];
        $address = trim($_POST['address'] ?? '');
        $this->ensureAddress($userId, $address);
        header('Location: /profile');
        exit;
    }

    /** Избранное */
    public function favorites(): void
    {
        requireClient();
        $userId = $_SESSION['user_id'];
        $stmt = $this->pdo->prepare(
            "SELECT p.id, t.name AS product, p.variety, p.origin_country,
                    p.box_size, p.box_unit, p.price, p.image_path
             FROM favorites f
             JOIN products p ON p.id = f.product_id
             JOIN product_types t ON t.id = p.product_type_id
             WHERE f.user_id = ? AND p.is_active = 1
             ORDER BY f.created_at DESC"
        );
        $stmt->execute([$userId]);
        $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $debugData = [
            'favoritesCount' => count($favorites),
            'today'          => date('Y-m-d'),
        ];

        view('client/favorites', [
            'favorites' => $favorites,
            'userName'  => $_SESSION['name'] ?? null,
            'debugData' => $debugData,
        ]);
    }
}