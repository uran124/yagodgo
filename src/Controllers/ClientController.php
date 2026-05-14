<?php
namespace App\Controllers;

use PDO;
use App\Helpers\PhoneNormalizer;
use App\Services\StockService;
use App\Services\OrderStockOrchestrator;
use App\Services\ClientCatalogService;

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


    /** Главная страница */
    public function home(): void
    {
        $catalogService = new ClientCatalogService($this->pdo);
        $data = $catalogService->getHomePageData();

        view('client/home', array_merge($data, [
            'userName' => $_SESSION['name'] ?? null,
        ]));
    }

    /** Каталог: сортировка по наличию/дате */
    public function catalog(): void
    {
        $catalogService = new ClientCatalogService($this->pdo);
        $data = $catalogService->getCatalogData();
    
        view('client/catalog', array_merge($data, [
            'userName'  => $_SESSION['name'] ?? null,
            'breadcrumbs' => [ ['label' => 'Каталог'] ],
        ]));
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
    $today = date('Y-m-d');
    foreach ($rawItems as $row) {
        $pid = $row['product_id'];
        $sessionDate = $_SESSION['delivery_date'][$pid] ?? null;
        if ($sessionDate !== null) {
            $deliveryDate = $sessionDate;
        } else {
            $prodDate = $row['delivery_date'];
            if ($prodDate === null) {
                $deliveryDate = PLACEHOLDER_DATE;
            } elseif ($prodDate > $today) {
                $deliveryDate = $prodDate;
            } else {
                $deliveryDate = $today;
            }
        }

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
        $stockMode = (string)($_POST['stock_mode'] ?? 'instant');
        if (!in_array($stockMode, ['preorder', 'instant', 'discount_stock'], true)) {
            $stockMode = 'instant';
        }

        // читаем выбор даты
        $dateOpt = $_POST['delivery_date'][$productId] ?? null;
        if ($dateOpt) {
            $_SESSION['delivery_date'][$productId] = $dateOpt;
        }

        if ($productId && $quantity > 0) {
            $priceStmt = $this->pdo->prepare(
                "SELECT price, sale_price, box_size, preorder_unit_price, instant_unit_price, discount_unit_price, current_purchase_batch_id
                 FROM products WHERE id = ?"
            );
            $priceStmt->execute([$productId]);
            $row = $priceStmt->fetch(PDO::FETCH_ASSOC);
            $priceBox = 0.0;
            $purchaseBatchId = null;
            if ($row) {
                $boxSize = (float)($row['box_size'] ?? 0);
                $sale    = (float)($row['sale_price'] ?? 0); // per kg
                $regular = (float)($row['price'] ?? 0);      // per kg
                $fallbackKgPrice = $sale > 0 ? $sale : $regular;
                $kgPrice = match ($stockMode) {
                    'preorder' => (float)($row['preorder_unit_price'] ?? 0),
                    'discount_stock' => (float)($row['discount_unit_price'] ?? 0),
                    default => (float)($row['instant_unit_price'] ?? 0),
                };
                if ($kgPrice <= 0) {
                    $kgPrice = $fallbackKgPrice;
                }
                $priceBox = $kgPrice * $boxSize;
                if ($stockMode !== 'preorder') {
                    $purchaseBatchId = isset($row['current_purchase_batch_id']) ? (int)$row['current_purchase_batch_id'] : null;
                }
            }

            $modeCheckStmt = $this->pdo->prepare(
                "SELECT stock_mode FROM cart_items WHERE user_id = ? AND product_id = ? LIMIT 1"
            );
            $modeCheckStmt->execute([$userId, $productId]);
            $existingMode = $modeCheckStmt->fetchColumn();
            if ($existingMode !== false && (string)$existingMode !== $stockMode) {
                $_SESSION['cart_error'] = 'Этот товар уже есть в корзине в другом режиме. Оформите текущую корзину или замените режим заказа.';
                $referer = $_SERVER['HTTP_REFERER'] ?? '/cart';
                header('Location: ' . $referer);
                exit;
            }

            $this->pdo->prepare(
                "INSERT INTO cart_items (user_id, product_id, quantity, unit_price, stock_mode, purchase_batch_id, boxes, sale_price_per_box)" .
                " VALUES (?, ?, ?, ?, ?, ?, ?, ?)" .
                " ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)," .
                " unit_price = VALUES(unit_price)," .
                " stock_mode = VALUES(stock_mode)," .
                " purchase_batch_id = VALUES(purchase_batch_id)," .
                " boxes = VALUES(boxes)," .
                " sale_price_per_box = VALUES(sale_price_per_box)"
            )->execute([$userId, $productId, $quantity, $priceBox, $stockMode, $purchaseBatchId, $quantity, $priceBox]);
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
                "UPDATE cart_items SET quantity = ?, boxes = ? WHERE user_id = ? AND product_id = ?"
            )->execute([$newQty, $newQty, $userId, $productId]);
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
     * - рассчитывает, сколько баллов можно списать
     * - передаёт в шаблон: группы, subtotal, баланс баллов, сколько списать, сумма после списания, адрес
     */
    public function checkout(): void
    {
        requireClient();
        $userId = $_SESSION['user_id'];
        $this->syncPreorderContinueToCart($userId);
    
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
                p.alias,
                t.name AS product,
                t.alias AS type_alias,
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
                                  ?? PLACEHOLDER_DATE;
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
    
        // 5) Получаем информацию о пользователе: баланс баллов и данные реферала
        $stmtUser = $this->pdo->prepare(
            "SELECT points_balance, referred_by, has_used_referral_coupon FROM users WHERE id = ?"
        );
        $stmtUser->execute([$userId]);
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $pointsBalance      = (int)($userRow['points_balance'] ?? 0);
        $referredBy         = $userRow['referred_by'] ? (int)$userRow['referred_by'] : null;
        $usedReferralCoupon = (int)($userRow['has_used_referral_coupon'] ?? 0);

        $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $cntStmt->execute([$userId]);
        $orderCount = (int)$cntStmt->fetchColumn();

        // Код пригласившего для автоподстановки на первый заказ
        $prefilledReferral = '';
        if ($referredBy !== null && $usedReferralCoupon === 0 && $orderCount === 0) {
            $refStmt = $this->pdo->prepare("SELECT referral_code FROM users WHERE id = ?");
            $refStmt->execute([$referredBy]);
            $prefilledReferral = $refStmt->fetchColumn() ?: '';
        }

        // Полученный из GET код купона или автоподставленный реферальный
        $couponCode  = trim($_GET['coupon_code'] ?? $prefilledReferral);
        $applyCoupon = isset($_GET['apply_coupon']) || $prefilledReferral !== '';

        $discountPercent = 0.0;
        $couponPoints    = 0;
        $couponInfo      = null;
        $couponError     = null;

        if ($applyCoupon && $couponCode !== '') {
            $stmt = $this->pdo->prepare(
                "SELECT code, type, discount, points FROM coupons WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE())"
            );
            $stmt->execute([$couponCode]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$coupon) {
                // Может быть реферальным кодом
                $rStmt = $this->pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                $rStmt->execute([$couponCode]);
                $ref = $rStmt->fetch(PDO::FETCH_ASSOC);
                if ($ref) {
                    $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                    $cntStmt->execute([$userId]);
                    $orderCount = (int)$cntStmt->fetchColumn();
                    if (( $referredBy === null && $orderCount === 0 ) ||
                        ( $referredBy === (int)$ref['id'] && $usedReferralCoupon === 0 && $orderCount === 0 )) {
                        $discountPercent = 10.0;
                        $couponInfo = [
                            'code' => $couponCode,
                            'type' => 'discount',
                            'discount' => 10
                        ];
                    } else {
                        $couponError = 'Промокод действует на первый заказ';
                    }
                } else {
                    $couponError = 'Неверный купон';
                }
            } else {
                $couponInfo = $coupon;
                if ($coupon['type'] === 'discount') {
                    $discountPercent = (float)$coupon['discount'];
                } elseif ($coupon['type'] === 'points') {
                    $couponPoints = (int)$coupon['points'];
                }
            }
        }
        if ($couponError === null) {
            $incomingCouponError = trim((string)($_GET['coupon_error'] ?? ''));
            if ($incomingCouponError !== '') {
                $couponError = $incomingCouponError;
            }
        }
    
        // 6) Рассчитываем, сколько баллов можно списать (не более суммы заказа)
        $pointsToUse = min($pointsBalance, (int)$subtotal);

        // 7) Итог после применения купона и баллов
        $pointsDiscountTotal = min($pointsToUse + $couponPoints, $subtotal);
        $couponPercentAmount = 0;
        if ($discountPercent > 0) {
            $couponPercentAmount = (int)floor(($subtotal - $pointsDiscountTotal) * ($discountPercent / 100));
        }
        $shippingTotal = 300 * count($groups);
        $finalTotal = $subtotal - $pointsDiscountTotal - $couponPercentAmount + $shippingTotal;
    
        // 8) Берём текущий адрес пользователя
        $addrStmt = $this->pdo->prepare(
            "SELECT id, street, recipient_name, recipient_phone, is_primary FROM addresses WHERE user_id = ? ORDER BY is_primary DESC, created_at ASC"
        );
        $addrStmt->execute([$userId]);
        $addresses = $addrStmt->fetchAll(PDO::FETCH_ASSOC);
        $address = $addresses[0]['street'] ?? '';

        // 8.1) Время слотов доставки
        $slotsStmt = $this->pdo->query(
            "SELECT id, time_from, time_to FROM delivery_slots ORDER BY time_from"
        );
        $slots = $slotsStmt->fetchAll(PDO::FETCH_ASSOC);
    
        // 9) Собираем debug-данные
        $debugData = [
            'raw_items'        => $rawItems,
            'groups'           => $groups,
            'subtotal'         => $subtotal,
            'pointsBalance'    => $pointsBalance,
            'pointsToUse'      => $pointsToUse,
            'discountPercent'  => $discountPercent,
            'couponPoints'     => $couponPoints,
            'finalTotal'       => $finalTotal,
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
            'couponCode'       => $couponCode,
            'couponInfo'       => $couponInfo,
            'couponError'      => $couponError,
            'finalTotal'       => $finalTotal,
            'lockCoupon'       => $prefilledReferral !== '',
            'address'          => $address,
            'addresses'        => $addresses,
            'userName'         => $_SESSION['name'] ?? null,
            'today'            => date('Y-m-d'),
            'slots'            => $slots,
            'debugData'        => $debugData,
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
              p.box_size, p.box_unit, t.name AS product, t.alias AS type_alias, p.alias, p.variety, p.seller_id
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
        $dateKey = $_SESSION['delivery_date'][$pid] ?? PLACEHOLDER_DATE;
        if (!isset($itemsByDate[$dateKey])) {
            $itemsByDate[$dateKey] = [];
        }
        $itemsByDate[$dateKey][$pid] = [
            'quantity'   => $it['quantity'],     // boxes
            'unit_price' => $it['unit_price'],   // price per box
            'box_size'   => $it['box_size'],
            'seller_id'  => $it['seller_id'],
        ];
    }

    $postedOrderModes = is_array($_POST['order_mode'] ?? null) ? $_POST['order_mode'] : [];
    $orderModeByDate = $this->normalizeOrderModes($itemsByDate, $postedOrderModes);

    // 3) Считаем общий чек
    $allTotal = 0;
    foreach ($itemsByDate as $block) {
        foreach ($block as $data) {
            $allTotal += $data['quantity'] * $data['unit_price'];
        }
    }

    // 4) Узнаём баланс баллов, реферера и кол-во заказов
    $stmt = $this->pdo->prepare("SELECT points_balance, referred_by, has_used_referral_coupon FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userRow      = $stmt->fetch(PDO::FETCH_ASSOC);
    $pointsBalance = (int)$userRow['points_balance'];
    $referredBy    = $userRow['referred_by'] ? (int)$userRow['referred_by'] : null;
    $usedReferralCoupon = (int)($userRow['has_used_referral_coupon'] ?? 0);
    $stmtCnt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $stmtCnt->execute([$userId]);
    $orderCount = (int)$stmtCnt->fetchColumn();

    // 4.1) Проверяем промокод
    $couponCode      = trim($_POST['coupon_code'] ?? '');
    $discountPercent = 0.0;
    $couponPoints    = 0;
    $referralUsed = false;
    $referrerId   = null;
    if ($couponCode !== '') {
        $stmt = $this->pdo->prepare(
            "SELECT code, type, discount, points FROM coupons WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE())"
        );
        $stmt->execute([$couponCode]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$coupon) {
            // может быть реферальный код
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
            $stmt->execute([$couponCode]);
            $ref = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ref) {
                if (( $referredBy === null && $orderCount === 0 ) ||
                    ( $referredBy === (int)$ref['id'] && $usedReferralCoupon === 0 && $orderCount === 0 )) {
                    $discountPercent = 10.0;
                    $referrerId = (int)$ref['id'];
                    $referralUsed = true;
                } else {
                    header('Location: /checkout?coupon_error=' . urlencode('Промокод действует на первый заказ'));
                    exit;
                }
            } else {
                header('Location: /checkout?coupon_error=' . urlencode('Неверный купон'));
                exit;
            }
        } else {
            if ($coupon['type'] === 'discount') {
                $discountPercent = (float)$coupon['discount'];
            } elseif ($coupon['type'] === 'points') {
                $couponPoints = (int)$coupon['points'];
            }
        }
    }

    $hasDiscountStockOrder = $this->shouldDisableRewardsForModes($orderModeByDate);
    if ($hasDiscountStockOrder) {
        $discountPercent = 0.0;
        $couponPoints = 0;
        $couponCode = '';
        $referralUsed = false;
        $referrerId = null;
    }

    // 5) Считаем, сколько баллов списать (не более суммы заказа)
    $pointsToUse  = $hasDiscountStockOrder ? 0 : min($pointsBalance, $allTotal);

    $this->pdo->beginTransaction();
    $stockService = new StockService($this->pdo);
    $orderStock = new OrderStockOrchestrator($this->pdo, $stockService);

    try {

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
    $newStreet       = trim($_POST['new_address'] ?? '');
    $recipientName   = trim($_POST['recipient_name'] ?? ($_SESSION['name'] ?? ''));
    $recipientPhone  = PhoneNormalizer::normalize($_POST['recipient_phone'] ?? '');

    $addressIds   = [];
    foreach ($itemsByDate as $dateKey => $_) {
        $addrInput = $postedAddresses[$dateKey] ?? $defaultAddress;
        $streetVal = '';
        if ($addrInput === 'pickup') {
            $streetVal = 'Самовывоз: 9 мая, 73';
            $addressIds[$dateKey] = $this->ensureAddress(
                $userId,
                $streetVal,
                $recipientName,
                $recipientPhone
            );
        } elseif ($addrInput === 'new' && $newStreet !== '') {
            $addressIds[$dateKey] = $this->ensureAddress($userId, $newStreet, $recipientName, $recipientPhone);
            $streetVal = $newStreet;
        } elseif (is_numeric($addrInput)) {
            $addressIds[$dateKey] = (int)$addrInput;
            $stmtAddr = $this->pdo->prepare("SELECT street FROM addresses WHERE id = ? AND user_id = ?");
            $stmtAddr->execute([(int)$addrInput, $userId]);
            $street = $stmtAddr->fetchColumn();
            if ($street === false) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                header('Location: /checkout?coupon_error=' . urlencode('Выбран недоступный адрес доставки'));
                exit;
            }
            $streetVal = (string)$street;
        } else {
            $addressIds[$dateKey] = $this->ensureAddress($userId, $addrInput, $recipientName, $recipientPhone);
            $streetVal = $addrInput;
        }
        // сохраняем адрес как обычный, скидка за самовывоз не применяется
    }

    // 9) СОЗДАЁМ ЗАКАЗЫ ПО КАЖДОЙ ДАТЕ, учитываем дату и слот
    $createdOrderIds = [];
        foreach ($itemsByDate as $dateKey => $block) {
        $isReservedOrder = ($dateKey === PLACEHOLDER_DATE);
        // (7.1) Считаем сумму по блоку и применяем скидку
        $blockSum = 0;
        foreach ($block as $data) {
            $blockSum += $data['quantity'] * $data['unit_price'];
        }
        $subAfterPickup = $blockSum;
        $pointsDiscount = $discountsByDate[$dateKey] ?? 0;
        $couponDiscount = 0;
        if ($discountPercent > 0) {
            $couponDiscount = (int) floor(($subAfterPickup - $pointsDiscount) * ($discountPercent / 100));
        }
        $addrInput = $postedAddresses[$dateKey] ?? $defaultAddress;
        $shippingFee = ($addrInput === 'pickup') ? 0 : 300;
        $finalSum = $subAfterPickup - $pointsDiscount - $couponDiscount + $shippingFee;
        if ($isReservedOrder) {
            $finalSum = 0;
        }

        $slotId = $_POST['slot_id'][$dateKey] ?? null; // из формы
        $status = $isReservedOrder ? 'reserved' : 'new';

        // (7.2) Вставляем заказ. Поскольку у таблицы orders есть колонки discount_applied, points_used, points_accrued, нужно задать их:
        $stmtOrder = $this->pdo->prepare(
            "INSERT INTO orders
               (user_id, address_id, slot_id, status, total_amount,
                discount_applied, points_used, points_accrued, coupon_code,
                delivery_date, created_at, order_mode, bonuses_allowed, coupons_allowed, reserved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)"
        );
        $pointsAccrued = 0; // пока 0, начислим ниже, если надо
        $orderDeliveryDate = $isReservedOrder ? date('Y-m-d') : $dateKey;
        $orderMode = (string)($orderModeByDate[$dateKey] ?? ($isReservedOrder ? 'preorder' : 'instant'));
        $reservedAt = $isReservedOrder ? date('Y-m-d H:i:s') : null;
        $bonusesAllowed = $orderMode === 'discount_stock' ? 0 : 1;
        $couponsAllowed = $orderMode === 'discount_stock' ? 0 : 1;

        $stmtOrder->execute([
            $userId,
            $addressIds[$dateKey],
            $slotId,
            $status,
            $finalSum,
            $couponDiscount, // discount_applied = скидка по купону
            $pointsDiscount,  // points_used = списанные баллы
            $pointsAccrued,   // points_accrued = пока 0
            $couponsAllowed ? $couponCode : '',
            $orderDeliveryDate,
            $orderMode,
            $bonusesAllowed,
            $couponsAllowed,
            $reservedAt,
        ]);
        $orderId = (int)$this->pdo->lastInsertId();
        $createdOrderIds[] = $orderId;

        // (7.3) Вставляем позиции в order_items
        $stmtItem = $this->pdo->prepare(
            "INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price, stock_mode, purchase_batch_id)\n" .
            "VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($block as $prodId => $data) {
            $kgQty   = $data['quantity'] * $data['box_size'];
            $kgPrice = $data['box_size'] > 0
                ? $data['unit_price'] / $data['box_size']
                : $data['unit_price'];

            $orderStock->persistOrderItemWithStock(
                $stmtItem,
                $orderId,
                (int)$prodId,
                [
                    'quantity' => (float)$data['quantity'],
                    'box_size' => (float)$data['box_size'],
                    'unit_price' => (float)$data['unit_price'],
                ],
                $orderMode,
                $isReservedOrder
            );
        }

        // (7.4) Создаём записи выплат для селлеров
        // Для discount_stock выплаты не формируем (низкомаржинальный режим)
        if ($orderMode === 'discount_stock') {
            continue;
        }

        $sellerTotals = [];
        foreach ($block as $prodId => $data) {
            $sid = $data['seller_id'] ?? null;
            if ($sid) {
                $sellerTotals[$sid] = ($sellerTotals[$sid] ?? 0) + $data['quantity'] * $data['unit_price'];
            }
        }
        if ($sellerTotals) {
            // Получаем режим работы селлеров одним запросом
            $sellerIds = array_keys($sellerTotals);
            $placeholders = implode(',', array_fill(0, count($sellerIds), '?'));
            $mStmt = $this->pdo->prepare("SELECT id, work_mode FROM users WHERE id IN ($placeholders)");
            $mStmt->execute($sellerIds);
            $modes = [];
            foreach ($mStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $modes[(int)$row['id']] = $row['work_mode'];
            }

            $pStmt = $this->pdo->prepare(
                "INSERT INTO seller_payouts (seller_id, order_id, gross_amount, commission_rate, commission_amount, payout_amount) VALUES (?, ?, ?, ?, ?, ?)"
            );
            foreach ($sellerTotals as $sid => $gross) {
                $rate = 30.00;
                $commission = round($gross * $rate / 100, 2);
                $mode = $modes[$sid] ?? 'berrygo_store';
                // Для собственных магазинов и доставки удерживаем комиссию, иначе выплачиваем 70%
                if (in_array($mode, ['own_store', 'warehouse_delivery'], true)) {
                    $payout = -$commission;
                } else {
                    $payout = $gross - $commission;
                }
                $pStmt->execute([$sid, $orderId, $gross, $rate, $commission, $payout]);
            }
        }

    }

    // 10) Очищаем корзину
    $this->pdo->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$userId]);
    $_SESSION['delivery_date'] = [];
    $this->refreshCartTotal();

    $preorderIntentId = (int)($_SESSION['preorder_checkout_intent_id'] ?? 0);
    if ($preorderIntentId > 0) {
        $this->pdo->prepare(
            "UPDATE preorder_intents SET status = 'checkout_completed', updated_at = NOW() WHERE id = ? AND user_id = ? AND status = 'confirmed'"
        )->execute([$preorderIntentId, $userId]);
        $this->logPreorderEvent($preorderIntentId, 'checkout_completed', 'confirmed', 'checkout_completed');
    }

    if ($referralUsed && $referrerId !== null) {
        $this->pdo->prepare(
            "UPDATE users SET referred_by = ?, has_used_referral_coupon = 1 WHERE id = ?"
        )->execute([$referrerId, $userId]);
        $this->pdo->prepare(
            "INSERT IGNORE INTO referrals (referrer_id, referred_id, created_at) VALUES (?, ?, NOW())"
        )->execute([$referrerId, $userId]);
    } elseif ($referredBy !== null && $usedReferralCoupon === 0 && $couponCode !== '') {
        $this->pdo->prepare(
            "UPDATE users SET has_used_referral_coupon = 1 WHERE id = ?"
        )->execute([$userId]);
    }

    $this->pdo->commit();
    } catch (\Throwable $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        $message = 'Не удалось оформить заказ. Проверьте остатки и попробуйте снова.';
        if ($e instanceof \RuntimeException) {
            $message = $e->getMessage();
        }

        header('Location: /checkout?coupon_error=' . urlencode($message));
        exit;
    }

    // Оповещаем администраторов о новых заказах
    $ordersController = new OrdersController($this->pdo);
    foreach ($createdOrderIds as $oid) {
        $ordersController->notifyAdmins($oid);
    }
    unset($_SESSION['preorder_checkout_intent_id']);

    header('Location: /orders');
    exit;
    }

    private function syncPreorderContinueToCart(int $userId): void
    {
        $pre = $_SESSION['preorder_continue'] ?? null;
        if (!is_array($pre)) {
            return;
        }

        $intentId = (int)($pre['intent_id'] ?? 0);
        $productId = (int)($pre['product_id'] ?? 0);
        $requestedBoxes = (float)($pre['requested_boxes'] ?? 0);
        if ($intentId <= 0 || $productId <= 0 || $requestedBoxes <= 0) {
            unset($_SESSION['preorder_continue']);
            return;
        }

        $intentStmt = $this->pdo->prepare(
            "SELECT status, offered_price_per_box FROM preorder_intents WHERE id = ? AND user_id = ? AND product_id = ? LIMIT 1"
        );
        $intentStmt->execute([$intentId, $userId, $productId]);
        $intent = $intentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$intent || (string)$intent['status'] !== 'confirmed') {
            unset($_SESSION['preorder_continue']);
            return;
        }

        $productStmt = $this->pdo->prepare(
            "SELECT box_size, preorder_price_per_box, preorder_unit_price FROM products WHERE id = ? AND is_active = 1 LIMIT 1"
        );
        $productStmt->execute([$productId]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            unset($_SESSION['preorder_continue']);
            return;
        }

        $priceBox = (float)($intent['offered_price_per_box'] ?? 0);
        if ($priceBox <= 0) {
            $priceBox = (float)($product['preorder_price_per_box'] ?? 0);
        }
        if ($priceBox <= 0) {
            $boxSize = max(0.0, (float)($product['box_size'] ?? 0));
            $unitPreorder = (float)($product['preorder_unit_price'] ?? 0);
            $priceBox = $boxSize > 0 ? $unitPreorder * $boxSize : $unitPreorder;
        }

        $this->pdo->prepare(
            "INSERT INTO cart_items (user_id, product_id, quantity, unit_price, stock_mode, purchase_batch_id, boxes, sale_price_per_box)
             VALUES (?, ?, ?, ?, 'preorder', NULL, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), unit_price = VALUES(unit_price), stock_mode = 'preorder', purchase_batch_id = NULL, boxes = VALUES(boxes), sale_price_per_box = VALUES(sale_price_per_box)"
        )->execute([$userId, $productId, $requestedBoxes, $priceBox, $requestedBoxes, $priceBox]);

        $_SESSION['preorder_checkout_intent_id'] = $intentId;
        unset($_SESSION['preorder_continue']);
        $this->refreshCartTotal();
    }





public function showOrder(int $orderId): void
{
    requireClient();
    $userId = $_SESSION['user_id'] ?? 0;
    $role = $_SESSION['role'] ?? '';

    // 1) Получаем данные о заказе
    $stmt = $this->pdo->prepare(
      "SELECT
         o.*, d.time_from AS slot_from, d.time_to AS slot_to,
         u.name AS client_name,
         a.street AS address,
         a.recipient_name,
         a.recipient_phone
       FROM orders o
       JOIN users u ON u.id = o.user_id
       LEFT JOIN addresses a ON a.id = o.address_id
       LEFT JOIN delivery_slots d ON d.id = o.slot_id
       WHERE o.id = ?"
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo "Заказ не найден";
        exit;
    }

    // Получаем информацию о купоне, если применялся
    $couponInfo = null;
    $pointsFromBalance = (int)($order['points_used'] ?? 0);
    if (!empty($order['coupon_code'])) {
        $cStmt = $this->pdo->prepare(
            "SELECT code, type, discount, points FROM coupons WHERE code = ?"
        );
        $cStmt->execute([$order['coupon_code']]);
        $couponInfo = $cStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$couponInfo) {
            $rStmt = $this->pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
            $rStmt->execute([$order['coupon_code']]);
            if ($rStmt->fetch()) {
                $couponInfo = [
                    'code' => $order['coupon_code'],
                    'type' => 'discount',
                    'discount' => 10,
                ];
            }
        }
        if ($couponInfo && $couponInfo['type'] === 'points') {
            $pointsFromBalance = max(0, $pointsFromBalance - (int)$couponInfo['points']);
        }
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
         oi.boxes,
         oi.unit_price,
         p.variety,
         p.alias,
         t.name AS product_name,
         t.alias AS type_alias
       FROM order_items oi
       JOIN products p ON p.id = oi.product_id
       JOIN product_types t ON t.id = p.product_type_id
       WHERE oi.order_id = ?"
    );
    $stmtItems->execute([$orderId]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 4) Отправляем всё в view. Файл-шаблон: src/Views/client/order_show.php
    view('client/order_show', [
        'order'           => $order,
        'items'           => $items,
        'userName'        => $_SESSION['name'] ?? null,
        'coupon'          => $couponInfo,
        'pointsFromBalance' => $pointsFromBalance,
    ]);
}

public function confirmReservedOrder(int $orderId): void
{
    requireClient();
    $userId = (int)($_SESSION['user_id'] ?? 0);

    $stmt = $this->pdo->prepare("SELECT id, user_id, status, total_amount, delivery_date FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order || (int)$order['user_id'] !== $userId) {
        http_response_code(404);
        echo 'Заказ не найден';
        exit;
    }
    $isReservation = (($order['status'] ?? '') === 'reserved');
    if (!$isReservation) {
        header('Location: /orders/' . $orderId . '?error=' . urlencode('Заказ уже подтвержден или отменен'));
        exit;
    }
    if ((int)($order['total_amount'] ?? 0) <= 0) {
        header('Location: /orders/' . $orderId . '?error=' . urlencode('Цена еще не определена'));
        exit;
    }

    $this->pdo->prepare("UPDATE orders SET status = 'new' WHERE id = ?")->execute([$orderId]);
    header('Location: /orders/' . $orderId . '?msg=' . urlencode('Заказ подтвержден'));
    exit;
}

public function cancelReservedOrder(int $orderId): void
{
    requireClient();
    $userId = (int)($_SESSION['user_id'] ?? 0);

    $this->pdo->beginTransaction();
    $stockService = new StockService($this->pdo);
    $orderStock = new OrderStockOrchestrator($this->pdo, $stockService);
    try {
        $stmt = $this->pdo->prepare("SELECT id, user_id, status, points_used, delivery_date FROM orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order || (int)$order['user_id'] !== $userId) {
            throw new \RuntimeException('order_not_found');
        }
        $isReservation = (($order['status'] ?? '') === 'reserved');
        if (!$isReservation) {
            throw new \RuntimeException('invalid_status');
        }

        $orderStock->rollbackReservationByOrderId($orderId);

        $pointsUsed = (int)($order['points_used'] ?? 0);
        if ($pointsUsed > 0) {
            $this->pdo->prepare("UPDATE users SET points_balance = points_balance + ? WHERE id = ?")
                ->execute([$pointsUsed, $userId]);
            $desc = "Возврат {$pointsUsed} клубничек за отмену брони #{$orderId}";
            $this->pdo->prepare(
                "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at)
                 VALUES (?, ?, ?, 'accrual', ?, NOW())"
            )->execute([$userId, $orderId, $pointsUsed, $desc]);
        }

        $this->pdo->prepare("DELETE FROM seller_payouts WHERE order_id = ?")->execute([$orderId]);
        $this->pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
        $this->pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);
        $this->pdo->commit();
        header('Location: /orders?msg=' . urlencode('Бронь удалена'));
        exit;
    } catch (\Throwable $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        header('Location: /orders/' . $orderId . '?error=' . urlencode('Не удалось отменить бронь'));
        exit;
    }
}







    /** Обновить/вставить адрес */
    private function ensureAddress(int $userId, string $street, string $name, string $phone): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM addresses WHERE user_id = ? AND street = ? AND recipient_name = ? AND recipient_phone = ?"
        );
        $stmt->execute([$userId, $street, $name, $phone]);
        if ($id = $stmt->fetchColumn()) {
            return (int)$id;
        }

        $this->pdo->prepare(
            "INSERT INTO addresses (user_id, street, recipient_name, recipient_phone, is_primary, created_at) VALUES (?, ?, ?, ?, 0, NOW())"
        )->execute([$userId, $street, $name, $phone]);

        return (int)$this->pdo->lastInsertId();
    }

    /** Список моих заказов */
    public function orders(): void
    {
        requireClient();
        $userId = $_SESSION['user_id'];
        $stmt = $this->pdo->prepare(
            "SELECT o.id, o.status, o.total_amount, o.created_at, o.delivery_date,\n       d.time_from AS slot_from, d.time_to AS slot_to, a.street AS address\nFROM orders o\nLEFT JOIN addresses a ON a.id = o.address_id\nLEFT JOIN delivery_slots d ON d.id = o.slot_id\nWHERE o.user_id = ?\nORDER BY o.id DESC"
        );
        $stmt->execute([$userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Подтягиваем позиции для каждого заказа
        $itemsStmt = $this->pdo->prepare(
            "SELECT t.name AS product_name, t.alias AS type_alias, p.variety, p.alias, p.box_size, p.box_unit, oi.quantity, oi.boxes, oi.unit_price
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             JOIN product_types t ON t.id = p.product_type_id
             WHERE oi.order_id = ?"
        );
        $awaiting = [];
        $rest = [];
        foreach ($orders as &$o) {
            $itemsStmt->execute([$o['id']]);
            $o['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            if (($o['status'] ?? '') === 'reserved') {
                $awaiting[] = $o;
            } else {
                $rest[] = $o;
            }
        }

        $debugData = [
            'ordersCount' => count($orders),
            'today'       => date('Y-m-d'),
        ];

        view('client/orders', [
            'ordersAwaiting' => $awaiting,
            'orders'         => $rest,
            'userName'       => $_SESSION['name'] ?? null,
            'debugData'      => $debugData,
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
        $addr = $this->pdo->prepare(
            "SELECT street FROM addresses WHERE user_id = ? AND is_primary = 1 LIMIT 1"
        );
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
        $stmt = $this->pdo->prepare("SELECT phone FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $phone = $stmt->fetchColumn() ?: '';
        $this->ensureAddress($userId, $address, $_SESSION['name'] ?? '', $phone);
        header('Location: /profile');
        exit;
    }

    /** Избранное */
    public function favorites(): void
    {
        requireClient();
        $userId = $_SESSION['user_id'];
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.alias, t.name AS product, t.alias AS type_alias, p.variety, p.origin_country,
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
    /** Настройки уведомлений */
    public function notifications(): void
    {
        requireClient();
        $userId = $_SESSION['user_id'];
        $stmt = $this->pdo->prepare("SELECT phone FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $phone = $stmt->fetchColumn();
        $tgStart = $phone ? $phone : null;
        view('client/notifications', [
            'userName' => $_SESSION['name'] ?? null,
            'tgStart'  => $tgStart,
        ]);
    }


    /** Показ одного материала */
    public function showMaterial(string $categoryAlias, string $alias): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.*, c.alias AS category_alias, c.name AS category_name
               FROM materials m
               JOIN content_categories c ON c.id = m.category_id
               WHERE m.alias = ? AND c.alias = ?"
        );
        $stmt->execute([$alias, $categoryAlias]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$material) {
            http_response_code(404);
            echo 'Материал не найден';
            return;
        }

        $products = [];
        foreach (['product1_id','product2_id','product3_id'] as $f) {
            $pid = $material[$f] ?? null;
            if ($pid) {
                $pStmt = $this->pdo->prepare(
                    "SELECT p.id, p.alias, t.name AS product, t.alias AS type_alias, p.variety, p.description, p.origin_country,
                            p.box_size, p.box_unit, p.price, p.sale_price, p.is_active,
                            p.image_path, p.delivery_date,
                            COALESCE(u.company_name,u.name,'berryGo') AS seller_name
                       FROM products p
                       JOIN product_types t ON t.id = p.product_type_id
                       LEFT JOIN users u ON u.id = p.seller_id
                       WHERE p.id = ?"
                );
                $pStmt->execute([$pid]);
                $prod = $pStmt->fetch(PDO::FETCH_ASSOC);
                if ($prod) { $products[] = $prod; }
            }
        }

        view('client/material', [
            'material'    => $material,
            'products'    => $products,
            'meta'        => [
                'title'       => $material['meta_title']       ?? '',
                'description' => $material['meta_description'] ?? '',
                'keywords'    => $material['meta_keywords']    ?? '',
            ],
            'breadcrumbs' => [
                [
                    'label' => $material['category_name'],
                    'url'   => '/content/' . $material['category_alias']
                ],
                ['label' => $material['title']]
            ],
        ]);
    }

    public function showProduct(string $alias, ?string $typeAlias = null): void
    {
        $query = "SELECT p.*, t.name AS product, t.alias AS type_alias
                  FROM products p
                  JOIN product_types t ON t.id = p.product_type_id
                  WHERE (p.alias = ? OR p.id = ?)";
        $params = [$alias, $alias];
        if ($typeAlias !== null) {
            $query .= " AND t.alias = ?";
            $params[] = $typeAlias;
        }
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            http_response_code(404);
            echo 'Товар не найден';
            return;
        }

        view('client/product', [
            'product' => $product,
            'breadcrumbs' => [
                ['label' => 'Каталог', 'url' => '/catalog'],
                [
                    'label' => $product['product'],
                    'url'   => '/catalog/' . $product['type_alias']
                ],
                ['label' => $product['variety']]
            ],
            'meta' => [
                'title'       => $product['meta_title'] ?? '',
                'description' => $product['meta_description'] ?? '',
                'keywords'    => $product['meta_keywords'] ?? ''
            ]
        ]);
    }

    public function showProductType(string $alias): void
    {
        $stmt = $this->pdo->prepare("SELECT * FROM product_types WHERE alias = ? OR id = ?");
        $stmt->execute([$alias, $alias]);
        $type = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$type) {
            http_response_code(404);
            echo 'Категория не найдена';
            return;
        }

        $pStmt = $this->pdo->prepare(
            "SELECT p.id, p.alias, t.name AS product, t.alias AS type_alias, p.variety, p.description, p.origin_country, p.box_size, p.box_unit, p.price, p.sale_price, p.is_active, p.image_path, p.delivery_date,
                    COALESCE(u.company_name,u.name,'berryGo') AS seller_name
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             LEFT JOIN users u ON u.id = p.seller_id
             WHERE p.product_type_id = ? AND p.is_active = 1"
        );
        $pStmt->execute([$type['id']]);
        $products = $pStmt->fetchAll(PDO::FETCH_ASSOC);

        view('client/catalog', [
            'products'          => $products,
            'meta'             => [
                'title'       => $type['meta_title']       ?? '',
                'description' => $type['meta_description'] ?? '',
                'keywords'    => $type['meta_keywords']    ?? '',
                'h1'          => $type['h1']              ?? $type['name'],
                'text'        => $type['text']            ?? '',
            ],
            'short_description' => $type['short_description'] ?? '',
            'breadcrumbs' => [
                ['label' => 'Каталог', 'url' => '/catalog'],
                ['label' => $type['name']]
            ],
            'hideFilters'  => true,
            'showMetaText' => false,
        ]);
    }

    public function createPreorderIntent(): void
    {
        requireClient();
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $requestedBoxes = round((float)($_POST['requested_boxes'] ?? 0), 2);

        if ($userId <= 0 || $productId <= 0 || $requestedBoxes <= 0) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Некорректные параметры предзаказа'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $productStmt = $this->pdo->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
        $productStmt->execute([$productId]);
        if (!$productStmt->fetchColumn()) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Товар не найден'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $existingStmt = $this->pdo->prepare(
            "SELECT id FROM preorder_intents WHERE user_id = ? AND product_id = ? AND status IN ('intent_created','offer_sent') ORDER BY id DESC LIMIT 1"
        );
        $existingStmt->execute([$userId, $productId]);
        $existingId = $existingStmt->fetchColumn();

        if ($existingId) {
            $this->pdo->prepare(
                "UPDATE preorder_intents SET requested_boxes = ?, status = 'intent_created', offered_price_per_box = NULL, offer_expires_at = NULL, checkout_token = NULL WHERE id = ?"
            )->execute([$requestedBoxes, (int)$existingId]);
            $intentId = (int)$existingId;
            $this->logPreorderEvent($intentId, 'intent_updated', null, 'intent_created', ['requested_boxes' => $requestedBoxes]);
        } else {
            $this->pdo->prepare(
                "INSERT INTO preorder_intents (user_id, product_id, requested_boxes, status, created_at, updated_at) VALUES (?, ?, ?, 'intent_created', NOW(), NOW())"
            )->execute([$userId, $productId, $requestedBoxes]);
            $intentId = (int)$this->pdo->lastInsertId();
            $this->logPreorderEvent($intentId, 'intent_created', null, 'intent_created', ['requested_boxes' => $requestedBoxes]);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'intent_id' => $intentId,
            'status' => 'intent_created',
            'message' => 'Предзаказ сохранён. Мы уведомим вас после поступления партии.',
        ], JSON_UNESCAPED_UNICODE);
    }

    public function confirmPreorderIntent(int $intentId): void
    {
        requireClient();
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $this->pdo->prepare(
            "SELECT id, status, offer_expires_at FROM preorder_intents WHERE id = ? AND user_id = ? LIMIT 1"
        );
        $stmt->execute([$intentId, $userId]);
        $intent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$intent) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Предзаказ не найден'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ((string)$intent['status'] !== 'offer_sent') {
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Оффер недоступен для подтверждения'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $expiresAt = $intent['offer_expires_at'] ?? null;
        if ($expiresAt === null || strtotime((string)$expiresAt) < time()) {
            $this->pdo->prepare("UPDATE preorder_intents SET status = 'expired', updated_at = NOW() WHERE id = ?")
                ->execute([$intentId]);
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Время подтверждения истекло'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $token = bin2hex(random_bytes(24));
        $this->pdo->prepare(
            "UPDATE preorder_intents SET status = 'confirmed', checkout_token = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$token, $intentId]);
        $this->logPreorderEvent($intentId, 'offer_confirmed', 'offer_sent', 'confirmed');

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'status' => 'confirmed',
            'continue_url' => '/preorder/continue/' . $token,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function declinePreorderIntent(int $intentId): void
    {
        requireClient();
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $this->pdo->prepare(
            "SELECT id, status FROM preorder_intents WHERE id = ? AND user_id = ? LIMIT 1"
        );
        $stmt->execute([$intentId, $userId]);
        $intent = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$intent) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Предзаказ не найден'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if ((string)$intent['status'] !== 'offer_sent') {
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Оффер недоступен для отказа'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->pdo->prepare("UPDATE preorder_intents SET status = 'declined', updated_at = NOW() WHERE id = ?")
            ->execute([$intentId]);
        $this->logPreorderEvent($intentId, 'offer_declined', 'offer_sent', 'declined');

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'status' => 'declined'], JSON_UNESCAPED_UNICODE);
    }

    public function continuePreorderCheckout(string $token): void
    {
        requireClient();
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $this->pdo->prepare(
            "SELECT id, product_id, requested_boxes, status FROM preorder_intents WHERE checkout_token = ? AND user_id = ? LIMIT 1"
        );
        $stmt->execute([$token, $userId]);
        $intent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$intent || (string)$intent['status'] !== 'confirmed') {
            http_response_code(404);
            echo 'Ссылка продолжения недействительна';
            return;
        }

        $_SESSION['preorder_continue'] = [
            'intent_id' => (int)$intent['id'],
            'product_id' => (int)$intent['product_id'],
            'requested_boxes' => (float)$intent['requested_boxes'],
        ];
        header('Location: /checkout');
        exit;
    }

    public function showPreorderIntentOffer(int $intentId): void
    {
        requireClient();
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $this->pdo->prepare(
            "SELECT pi.id, pi.status, pi.requested_boxes, pi.offered_price_per_box, pi.offer_expires_at,
                    p.alias AS product_alias, p.variety, pt.alias AS type_alias, pt.name AS product_name
             FROM preorder_intents pi
             JOIN products p ON p.id = pi.product_id
             JOIN product_types pt ON pt.id = p.product_type_id
             WHERE pi.id = ? AND pi.user_id = ? LIMIT 1"
        );
        $stmt->execute([$intentId, $userId]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$offer) {
            http_response_code(404);
            echo 'Оффер не найден';
            return;
        }
        view('client/preorder_offer', ['offer' => $offer]);
    }

    private function logPreorderEvent(int $intentId, string $eventType, ?string $fromStatus, ?string $toStatus, ?array $meta = null): void
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO preorder_intent_events (preorder_intent_id, event_type, from_status, to_status, meta_json, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            )->execute([
                $intentId,
                $eventType,
                $fromStatus,
                $toStatus,
                $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable) {
            // non-blocking audit write
        }
    }

    /**
     * @param array<string, mixed> $itemsByDate
     * @param array<string, mixed> $postedOrderModes
     * @return array<string, string>
     */

    /**
     * @return array<int, array{batch_id:int, boxes:float}>
     */
    private function allocateFifoBatches(int $productId, float $requiredBoxes, string $mode): array
    {
        $orchestrator = new OrderStockOrchestrator($this->pdo);
        return $orchestrator->allocateFifoBatches($productId, $requiredBoxes, $mode);
    }

    public function normalizeOrderModes(array $itemsByDate, array $postedOrderModes): array
    {
        $allowedModes = ['preorder', 'instant', 'discount_stock'];
        $result = [];

        foreach ($itemsByDate as $dateKey => $_) {
            $rawMode = (string)($postedOrderModes[$dateKey] ?? '');
            if (!in_array($rawMode, $allowedModes, true)) {
                $rawMode = ($dateKey === PLACEHOLDER_DATE) ? 'preorder' : 'instant';
            }
            $result[(string)$dateKey] = $rawMode;
        }

        return $result;
    }

    /** @param array<string, string> $orderModeByDate */
    public function shouldDisableRewardsForModes(array $orderModeByDate): bool
    {
        return in_array('discount_stock', $orderModeByDate, true);
    }

}
