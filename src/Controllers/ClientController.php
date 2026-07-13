<?php
namespace App\Controllers;

use PDO;
use App\Helpers\PhoneNormalizer;
use App\Services\StockService;
use App\Services\StockDeficitService;
use App\Services\OrderStockOrchestrator;
use App\Services\ClientCatalogService;
use App\Services\SellableBatchResolver;
use App\Services\DeliveryPricingService;
use App\Services\OrderStatusHistoryService;
use App\Services\ProductionJobService;
use App\Services\SellerEconomicsService;

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

    private function isBatchFirstEnabled(): bool
    {
        $raw = getenv('BATCH_FIRST_SALES_ENABLED');
        if ($raw === false) {
            return true;
        }
        return in_array(strtolower((string)$raw), ['1', 'true', 'yes', 'on'], true);
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
            ci.id AS cart_item_id,
            ci.product_id,
            ci.purchase_batch_id,
            ci.quantity,
            ci.unit_price,
            ci.stock_mode,
            p.variety,
            t.name AS product,
            p.box_size,
            p.box_unit,
            p.image_path,
            DATE(pb.purchased_at) AS delivery_date,
            p.sale_price,
            p.is_active
         FROM cart_items ci
         JOIN products p ON p.id = ci.product_id
         JOIN product_types t ON t.id = p.product_type_id
         LEFT JOIN purchase_batches pb ON pb.id = ci.purchase_batch_id
         WHERE ci.user_id = ?"
    );
    $stmt->execute([$userId]);
    $rawItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2) Подставляем в каждый элемент выбранную дату доставки из сессии (или "сегодня")
    $items = [];
    $today = date('Y-m-d');
    foreach ($rawItems as $row) {
        $pid = $row['product_id'];
        $cartItemId = (int)$row['cart_item_id'];
        $sessionDate = $_SESSION['delivery_date'][$cartItemId] ?? $_SESSION['delivery_date'][$pid] ?? null;
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
            'cart_item_id'  => (int)$row['cart_item_id'],
            'product_id'    => $pid,
            'product'       => $row['product'],
            'variety'       => $row['variety'],
            'image_path'    => $row['image_path'],
            'unit_price'    => $row['unit_price'],
            'quantity'      => $row['quantity'],
            'stock_mode'    => $row['stock_mode'] ?? 'instant',
            'purchase_batch_id' => $row['purchase_batch_id'],
            'delivery_date' => $deliveryDate,
            'sale_price'    => $row['sale_price'],
            'is_active'     => $row['is_active'],
            'box_size'      => $row['box_size'],
            'box_unit'      => $row['box_unit'],
        ];
    }

    // 3) Пересчитываем общую сумму корзины
    $this->refreshCartTotal();

    // 4) Рендерим шаблон
    view('client/cart', [
        'items'      => $items,
        'userName'   => $_SESSION['name'] ?? null,
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
            if (!$this->isBatchFirstEnabled()) {
                $_SESSION['cart_error'] = 'Режим legacy add-to-cart отключен. Включите batch-first продажи.';
                $referer = $_SERVER['HTTP_REFERER'] ?? '/';
                header('Location: ' . $referer);
                exit;
            }

            $purchaseBatchId = 0;
            $priceBox = 0.0;
            $available = 0.0;
            $resolver = new SellableBatchResolver($this->pdo);
            $batch = $resolver->resolveForProduct($productId, $stockMode);
            if ($batch === null) {
                $_SESSION['cart_error'] = 'Для этого товара сейчас нет доступного варианта продажи.';
                $referer = $_SERVER['HTTP_REFERER'] ?? '/';
                header('Location: ' . $referer);
                exit;
            }
            $purchaseBatchId = (int)($batch['id'] ?? 0);
            if ($purchaseBatchId <= 0) {
                $_SESSION['cart_error'] = 'Для этого товара сейчас нет доступного варианта продажи.';
                $referer = $_SERVER['HTTP_REFERER'] ?? '/';
                header('Location: ' . $referer);
                exit;
            }
            $priceBox = (float)($batch['price_per_box'] ?? 0);
            $available = (float)($batch['boxes_available'] ?? ($batch['boxes_free'] ?? 0));
            if ($priceBox <= 0) {
                $_SESSION['cart_error'] = 'Для выбранного варианта продажи не задана цена.';
                $referer = $_SERVER['HTTP_REFERER'] ?? '/';
                header('Location: ' . $referer);
                exit;
            }
            if ($stockMode !== 'preorder' && $quantity > $available) {
                $_SESSION['cart_error'] = 'Недостаточно товара в наличии.';
                $referer = $_SERVER['HTTP_REFERER'] ?? '/';
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
        $cartItemId = (int)($_POST['cart_item_id'] ?? 0);
        $action     = $_POST['action'] ?? '';

        $stmt = $this->pdo->prepare(
            "SELECT quantity FROM cart_items WHERE user_id = ? AND id = ?"
        );
        $stmt->execute([$userId, $cartItemId]);
        $current = (float)$stmt->fetchColumn();

        if ($current > 0) {
            $newQty = match($action) {
                'increase' => $current + 1,
                'decrease' => max(1, $current - 1),
                default    => $current,
            };
            $this->pdo->prepare(
                "UPDATE cart_items SET quantity = ?, boxes = ? WHERE user_id = ? AND id = ?"
            )->execute([$newQty, $newQty, $userId, $cartItemId]);
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
        $cartItemId = (int)($_POST['cart_item_id'] ?? 0);

        $this->pdo->prepare(
            "DELETE FROM cart_items WHERE user_id = ? AND id = ?"
        )->execute([$userId, $cartItemId]);

        unset($_SESSION['delivery_date'][$cartItemId]);
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
                ci.stock_mode,
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
            "SELECT id, street, recipient_name, recipient_phone, is_primary, last_checkout_comment FROM addresses WHERE user_id = ? ORDER BY is_primary DESC, created_at ASC"
        );
        $addrStmt->execute([$userId]);
        $addresses = $addrStmt->fetchAll(PDO::FETCH_ASSOC);
        $address = $addresses[0]['street'] ?? '';

        // 8.1) Время слотов доставки
        $slotsStmt = $this->pdo->query(
            "SELECT id, time_from, time_to FROM delivery_slots ORDER BY time_from"
        );
        $slots = $slotsStmt->fetchAll(PDO::FETCH_ASSOC);
    
        // 9) Рендерим шаблон, передаём всё в view
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
           "SELECT ci.id AS cart_item_id, ci.product_id, ci.purchase_batch_id, ci.quantity, ci.unit_price,
              p.box_size, p.box_unit, t.name AS product, t.alias AS type_alias, p.alias, p.variety, p.seller_id,
              ci.stock_mode
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
        $pid = (int)$it['product_id'];
        $cartItemId = (int)$it['cart_item_id'];
        $stockMode = (string)($it['stock_mode'] ?? 'instant');
        $date = $_SESSION['delivery_date'][$cartItemId] ?? $_SESSION['delivery_date'][$pid] ?? PLACEHOLDER_DATE;
        if ($stockMode !== 'preorder' && ($date === PLACEHOLDER_DATE || $date === 'on_demand')) {
            $date = date('Y-m-d');
        }
        $dateKey = $this->cartGroupKey($stockMode, (string)$date);
        if (!isset($itemsByDate[$dateKey])) {
            $itemsByDate[$dateKey] = [];
        }
        $itemsByDate[$dateKey][$cartItemId] = [
            'cart_item_id' => $cartItemId,
            'product_id'  => $pid,
            'quantity'   => $it['quantity'],     // boxes
            'unit_price' => $it['unit_price'],   // price per box
            'box_size'   => $it['box_size'],
            'seller_id'  => $it['seller_id'],
            'stock_mode' => $stockMode,
            'purchase_batch_id' => isset($it['purchase_batch_id']) ? (int)$it['purchase_batch_id'] : null,
        ];
    }

    if (isset($_POST['selected_orders_present'])) {
        $postedSelectedOrders = is_array($_POST['selected_orders'] ?? null) ? $_POST['selected_orders'] : [];
        $itemsByDate = array_filter(
            $itemsByDate,
            static fn($dateKey): bool => !empty($postedSelectedOrders[$dateKey]),
            ARRAY_FILTER_USE_KEY
        );
        if (!$itemsByDate) {
            header('Location: /checkout?coupon_error=' . urlencode('Выберите хотя бы один заказ для оформления'));
            exit;
        }

        $selectedProductIdsForCheckout = [];
        foreach ($itemsByDate as $block) {
            foreach ($block as $cartRow) {
                $selectedProductIdsForCheckout[(int)($cartRow['cart_item_id'] ?? 0)] = true;
            }
        }
        $rawItems = array_values(array_filter(
            $rawItems,
            static fn(array $item): bool => isset($selectedProductIdsForCheckout[(int)($item['cart_item_id'] ?? 0)])
        ));
    }

        $postedOrderModes = is_array($_POST['order_mode'] ?? null) ? $_POST['order_mode'] : [];
    $orderModeByDate = $this->resolveOrderModesFromCart($itemsByDate, $rawItems, $postedOrderModes);
    foreach ($itemsByDate as $dateKey => $block) {
        $dateMode = (string)($orderModeByDate[$dateKey] ?? 'instant');
        foreach ($block as $productId => $data) {
            $batchId = (int)($data['purchase_batch_id'] ?? 0);
            if (in_array($dateMode, ['instant', 'discount_stock'], true) && $batchId <= 0) {
                throw new \RuntimeException('В корзине есть товар без привязки к партии. Обновите корзину и повторите.');
            }
        }
    }

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
    $this->pdo->prepare(
        "INSERT INTO order_groups (user_id, created_by_user_id, comment) VALUES (?, NULL, ?)"
    )->execute([$userId, 'client checkout']);
    $orderGroupId = (int)$this->pdo->lastInsertId();

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

    // 8) Обрабатываем адреса, комментарии и предварительный расчёт доставки по каждой дате.
    $postedAddresses = is_array($_POST['address_id'] ?? null) ? $_POST['address_id'] : [];
    $defaultAddress  = $postedAddresses['default'] ?? '';
    $postedNewAddresses = $_POST['new_address'] ?? '';
    $postedComments = is_array($_POST['delivery_comment'] ?? null) ? $_POST['delivery_comment'] : [];
    $postedSelectedAddresses = is_array($_POST['new_address_normalized'] ?? null) ? $_POST['new_address_normalized'] : [];
    $postedSelectedLats = is_array($_POST['new_address_lat'] ?? null) ? $_POST['new_address_lat'] : [];
    $postedSelectedLngs = is_array($_POST['new_address_lng'] ?? null) ? $_POST['new_address_lng'] : [];
    $recipientName   = trim($_POST['recipient_name'] ?? ($_SESSION['name'] ?? ''));
    $recipientPhone  = PhoneNormalizer::normalize($_POST['recipient_phone'] ?? '');

    $deliveryPricing = new DeliveryPricingService($this->pdo);
    $addressIds = [];
    $streetByDate = [];
    $addrInputByDate = [];
    $deliveryCommentByDate = [];
    $deliveryByDate = [];

    foreach ($itemsByDate as $dateKey => $_) {
        $addrInput = (string)($postedAddresses[$dateKey] ?? $defaultAddress);
        $addrInputByDate[$dateKey] = $addrInput;
        $comment = trim((string)($postedComments[$dateKey] ?? ''));
        $deliveryCommentByDate[$dateKey] = $comment;
        $streetVal = '';

        if ($addrInput === 'pickup') {
            $streetVal = 'Самовывоз: 9 мая, 73';
            $addressIds[$dateKey] = $this->ensureAddress($userId, $streetVal, '', '');
        } elseif ($addrInput === 'new') {
            $newStreet = is_array($postedNewAddresses)
                ? trim((string)($postedNewAddresses[$dateKey] ?? ''))
                : trim((string)$postedNewAddresses);
            if ($newStreet === '') {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                header('Location: /checkout?coupon_error=' . urlencode('Введите новый адрес доставки'));
                exit;
            }
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
            $streetVal = trim($addrInput);
            $addressIds[$dateKey] = $this->ensureAddress($userId, $streetVal, $recipientName, $recipientPhone);
        }

        $streetByDate[$dateKey] = $streetVal;

        if ($addrInput === 'pickup') {
            $deliveryByDate[$dateKey] = [
                'delivery_fee' => 0,
                'distance_km' => null,
                'distance_m' => null,
                'delivery_tariff_zone_id' => null,
                'delivery_pricing_source' => 'pickup',
            ];
            continue;
        }

        try {
            $selected = [
                'selected_lat' => $postedSelectedLats[$dateKey] ?? '',
                'selected_lng' => $postedSelectedLngs[$dateKey] ?? '',
                'selected_address' => $postedSelectedAddresses[$dateKey] ?? '',
            ];
            $deliveryCalc = $deliveryPricing->calculateForAddress($streetVal, null, $selected);
            $deliveryByDate[$dateKey] = [
                'delivery_fee' => (int)($deliveryCalc['delivery_fee'] ?? $deliveryCalc['price_rub'] ?? 300),
                'distance_km' => isset($deliveryCalc['distance_km']) && $deliveryCalc['distance_km'] !== '' ? (float)$deliveryCalc['distance_km'] : null,
                'distance_m' => isset($deliveryCalc['distance_m']) && $deliveryCalc['distance_m'] !== '' ? (int)round((float)$deliveryCalc['distance_m']) : null,
                'delivery_tariff_zone_id' => $deliveryCalc['delivery_tariff_zone_id'] ?? null,
                'delivery_pricing_source' => (string)($deliveryCalc['delivery_pricing_source'] ?? $deliveryCalc['pricing_source'] ?? ''),
                'lat' => $deliveryCalc['lat'] ?? null,
                'lng' => $deliveryCalc['lng'] ?? null,
                'normalized_address' => $deliveryCalc['normalized_address'] ?? $deliveryCalc['address'] ?? $streetVal,
            ];
        } catch (\Throwable $e) {
            $deliveryByDate[$dateKey] = [
                'delivery_fee' => 300,
                'distance_km' => null,
                'distance_m' => null,
                'delivery_tariff_zone_id' => null,
                'delivery_pricing_source' => 'pending_review',
                'delivery_distance_error' => $e->getMessage(),
            ];
        }

        $deliveryRow = $deliveryByDate[$dateKey];
        $this->pdo->prepare(
            "UPDATE addresses
             SET last_checkout_comment = ?,
                 delivery_distance_km = ?,
                 delivery_distance_m = ?,
                 delivery_lat = ?,
                 delivery_lng = ?,
                 delivery_normalized_address = ?,
                 delivery_distance_provider = ?,
                 delivery_distance_calculated_at = NOW(),
                 delivery_distance_error = ?
             WHERE id = ? AND user_id = ?"
        )->execute([
            $comment,
            $deliveryRow['distance_km'] ?? null,
            $deliveryRow['distance_m'] ?? null,
            $deliveryRow['lat'] ?? null,
            $deliveryRow['lng'] ?? null,
            $deliveryRow['normalized_address'] ?? $streetVal,
            $deliveryRow['delivery_pricing_source'] ?? null,
            $deliveryRow['delivery_distance_error'] ?? null,
            $addressIds[$dateKey],
            $userId,
        ]);
    }

    // 9) СОЗДАЁМ ЗАКАЗЫ ПО КАЖДОЙ ДАТЕ, учитываем дату и слот
    $createdOrderIds = [];
        foreach ($itemsByDate as $dateKey => $block) {
        $orderMode = (string)($orderModeByDate[$dateKey] ?? 'instant');
        $isReservedOrder = ($orderMode === 'preorder');
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
        $addrInput = $addrInputByDate[$dateKey] ?? ($postedAddresses[$dateKey] ?? $defaultAddress);
        $deliveryRow = $deliveryByDate[$dateKey] ?? ['delivery_fee' => 300];
        $shippingFee = (int)($deliveryRow['delivery_fee'] ?? 300);
        $finalSum = $subAfterPickup - $pointsDiscount - $couponDiscount + $shippingFee;
        if ($isReservedOrder) {
            // В корзине показываем предварительную цену, но сумма reserved-заказа становится точной
            // только после подтверждения и пересчёта order_items.
            $finalSum = 0;
        }

        $slotId = $_POST['slot_id'][$dateKey] ?? null; // из формы
        $status = $isReservedOrder ? 'reserved' : 'new';

        // (7.2) Вставляем заказ. Поскольку у таблицы orders есть колонки discount_applied, points_used, points_accrued, нужно задать их:
        $stmtOrder = $this->pdo->prepare(
            "INSERT INTO orders
               (user_id, order_group_id, address_id, slot_id, status, total_amount,
                discount_applied, points_used, points_accrued, coupon_code,
                delivery_date, delivery_fee, delivery_distance_km, delivery_tariff_zone_id,
                delivery_pricing_source, delivery_comment,
                created_at, order_mode, bonuses_allowed, coupons_allowed, reserved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)"
        );
        $pointsAccrued = 0; // пока 0, начислим ниже, если надо
        $deliveryDateOnly = $this->dateFromCartGroupKey((string)$dateKey);
        $orderDeliveryDate = ($isReservedOrder && $deliveryDateOnly === PLACEHOLDER_DATE) ? date('Y-m-d') : $deliveryDateOnly;
        $reservedAt = $isReservedOrder ? date('Y-m-d H:i:s') : null;
        $bonusesAllowed = $orderMode === 'discount_stock' ? 0 : 1;
        $couponsAllowed = $orderMode === 'discount_stock' ? 0 : 1;

        $stmtOrder->execute([
            $userId,
            $orderGroupId,
            $addressIds[$dateKey],
            $slotId,
            $status,
            $finalSum,
            $couponDiscount, // discount_applied = скидка по купону
            $pointsDiscount,  // points_used = списанные баллы
            $pointsAccrued,   // points_accrued = пока 0
            $couponsAllowed ? $couponCode : '',
            $orderDeliveryDate,
            $shippingFee,
            $deliveryRow['distance_km'] ?? null,
            $deliveryRow['delivery_tariff_zone_id'] ?? null,
            $deliveryRow['delivery_pricing_source'] ?? null,
            $deliveryCommentByDate[$dateKey] ?? '',
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
        foreach ($block as $data) {
            $prodId = (int)$data['product_id'];
            $kgQty   = $data['quantity'] * $data['box_size'];
            $kgPrice = $data['box_size'] > 0
                ? $data['unit_price'] / $data['box_size']
                : $data['unit_price'];

            $itemPayload = [
                'quantity' => (float)$data['quantity'],
                'box_size' => (float)$data['box_size'],
                'unit_price' => (float)$data['unit_price'],
                'purchase_batch_id' => isset($data['purchase_batch_id']) ? (int)$data['purchase_batch_id'] : null,
            ];
            if ($isReservedOrder) {
                $orderStock->persistOrderItemWithStock(
                    $stmtItem,
                    $orderId,
                    (int)$prodId,
                    $itemPayload,
                    $orderMode,
                    true
                );
            } else {
                $orderStock->persistOrderItemOnly(
                    $stmtItem,
                    $orderId,
                    (int)$prodId,
                    $itemPayload,
                    $orderMode
                );
            }
        }

        // (7.4) Создаём производственные задания для товаров berryGo, которые требуют изготовления.
        (new ProductionJobService($this->pdo))->createForOrderIfRequired($orderId);

        // (7.5) Создаём записи выплат для селлеров
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
            $sellerEconomics = new SellerEconomicsService($this->pdo);
            foreach ($sellerTotals as $sid => $gross) {
                $payoutRecord = $sellerEconomics->payoutRecord((int)$sid, (float)$gross, (string)($modes[$sid] ?? 'berrygo_store'));
                $pStmt->execute([
                    $sid,
                    $orderId,
                    $gross,
                    (float)$payoutRecord['commission_rate'],
                    (float)$payoutRecord['commission'],
                    (float)$payoutRecord['payout'],
                ]);
            }
        }

    }

    // 10) Очищаем из корзины только выбранные для оформления блоки.
    $selectedCartItemIds = [];
    foreach ($itemsByDate as $block) {
        foreach ($block as $cartRow) {
            $selectedCartItemIds[] = (int)($cartRow['cart_item_id'] ?? 0);
        }
    }
    $selectedCartItemIds = array_values(array_unique(array_filter($selectedCartItemIds)));
    if ($selectedCartItemIds) {
        $placeholders = implode(',', array_fill(0, count($selectedCartItemIds), '?'));
        $deleteParams = array_merge([$userId], $selectedCartItemIds);
        $this->pdo->prepare("DELETE FROM cart_items WHERE user_id = ? AND id IN ($placeholders)")->execute($deleteParams);
        foreach ($selectedCartItemIds as $cartItemId) {
            unset($_SESSION['delivery_date'][$cartItemId]);
        }
    }
    if (empty($_SESSION['delivery_date'])) {
        $_SESSION['delivery_date'] = [];
    }
    $remainingStmt = $this->pdo->prepare('SELECT COUNT(*) FROM cart_items WHERE user_id = ?');
    $remainingStmt->execute([$userId]);
    $hasRemainingCartItems = ((int)$remainingStmt->fetchColumn() > 0);
    $this->refreshCartTotal();

        $preorderIntentId = (int)($_SESSION['preorder_checkout_intent_id'] ?? 0);
    if ($preorderIntentId > 0 && !$hasRemainingCartItems) {
        $this->pdo->prepare(
            "UPDATE preorder_intents SET status = 'completed', updated_at = NOW() WHERE id = ? AND user_id = ? AND status IN ('confirmed','moved_to_cart')"
        )->execute([$preorderIntentId, $userId]);
        $this->logPreorderEvent($preorderIntentId, 'checkout_completed', 'moved_to_cart', 'completed');
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
    (new StockDeficitService($this->pdo))->notifyAdminsIfChanged('оформлен заказ');
    if (empty($hasRemainingCartItems)) {
        unset($_SESSION['preorder_checkout_intent_id']);
    }

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
            "SELECT status, offered_price_per_box, purchase_batch_id, desired_delivery_date FROM preorder_intents WHERE id = ? AND user_id = ? AND product_id = ? LIMIT 1"
        );
        $intentStmt->execute([$intentId, $userId, $productId]);
        $intent = $intentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$intent || (string)$intent['status'] !== 'confirmed') {
            unset($_SESSION['preorder_continue']);
            return;
        }

        $purchaseBatchId = (int)($intent['purchase_batch_id'] ?? 0);
        $batchStmt = $this->pdo->prepare(
            "SELECT pb.id, COALESCE(NULLIF(pb.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0) AS preorder_price_per_box
             FROM purchase_batches pb
             JOIN products p ON p.id = pb.product_id
             WHERE pb.product_id = ?
               AND pb.id = ?
               AND pb.status IN ('purchased','arrived')
               AND COALESCE(NULLIF(pb.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0) > 0
             LIMIT 1"
        );
        $batchStmt->execute([$productId, $purchaseBatchId]);
        $batch = $batchStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $priceBox = (float)($intent['offered_price_per_box'] ?? 0);
        if ($priceBox <= 0) {
            $priceBox = (float)($batch['preorder_price_per_box'] ?? 0);
        }
        if ($purchaseBatchId <= 0 || $priceBox <= 0) {
            unset($_SESSION['preorder_continue']);
            $_SESSION['cart_error'] = 'Для предзаказа сейчас нет выкупленной партии с подтвержденной ценой.';
            return;
        }

        $desiredDeliveryDate = $this->normalizeDateString($intent['desired_delivery_date'] ?? null);
        $_SESSION['delivery_date'][$productId] = $desiredDeliveryDate ?: PLACEHOLDER_DATE;

        $this->pdo->prepare(
            "INSERT INTO cart_items (user_id, product_id, quantity, unit_price, stock_mode, purchase_batch_id, boxes, sale_price_per_box)
             VALUES (?, ?, ?, ?, 'preorder', ?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), unit_price = VALUES(unit_price), stock_mode = 'preorder', purchase_batch_id = VALUES(purchase_batch_id), boxes = VALUES(boxes), sale_price_per_box = VALUES(sale_price_per_box)"
        )->execute([$userId, $productId, $requestedBoxes, $priceBox, $purchaseBatchId, $requestedBoxes, $priceBox]);

        $this->pdo->prepare("UPDATE preorder_intents SET status = 'moved_to_cart', updated_at = NOW() WHERE id = ? AND user_id = ? AND status IN ('confirmed','moved_to_cart')")
            ->execute([$intentId, $userId]);
        $this->logPreorderEvent($intentId, 'moved_to_cart', 'confirmed', 'moved_to_cart');
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

    $this->pdo->prepare("UPDATE orders SET status = 'confirmed' WHERE id = ?")->execute([$orderId]);
    (new OrderStatusHistoryService($this->pdo))->record($orderId, 'reserved', 'confirmed', $userId, 'client', 'Клиент подтвердил бронь');
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
            "SELECT o.id, o.status, o.total_amount, o.created_at, o.delivery_date,\n       o.delivery_fee, o.delivery_distance_km, o.delivery_pricing_source, o.delivery_comment,\n       d.time_from AS slot_from, d.time_to AS slot_to, a.street AS address\nFROM orders o\nLEFT JOIN addresses a ON a.id = o.address_id\nLEFT JOIN delivery_slots d ON d.id = o.slot_id\nWHERE o.user_id = ?\nORDER BY o.id DESC"
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

        view('client/orders', [
            'ordersAwaiting' => $awaiting,
            'orders'         => $rest,
            'userName'       => $_SESSION['name'] ?? null,
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

        view('client/profile', [
            'user'      => $user,
            'address'   => $address,
            'userName'  => $_SESSION['name'] ?? null,
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

        $preordersStmt = $this->pdo->prepare(
            "SELECT pi.id, pi.status, pi.requested_boxes, pi.offer_expires_at, pi.updated_at,
                    pt.name AS product_name, p.variety
             FROM preorder_intents pi
             JOIN products p ON p.id = pi.product_id
             JOIN product_types pt ON pt.id = p.product_type_id
             WHERE pi.user_id = ?
             ORDER BY pi.updated_at DESC, pi.id DESC
             LIMIT 20"
        );
        $preordersStmt->execute([$userId]);
        $preorderIntents = $preordersStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($preorderIntents as &$intent) {
            $intent['status_label'] = $this->preorderIntentStatusLabel((string)($intent['status'] ?? ''));
        }
        unset($intent);

        view('client/favorites', [
            'favorites' => $favorites,
            'preorderIntents' => $preorderIntents,
            'userName'  => $_SESSION['name'] ?? null,
        ]);
    }
    /** Настройки уведомлений */
    public function notifications(): void
    {
        requireClient();
        $userId = (int)$_SESSION['user_id'];
        $stmt = $this->pdo->prepare("SELECT phone FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $phone = $stmt->fetchColumn();
        $tgStart = $phone ? $phone : null;
        $notificationRows = $this->pdo->query(
            "SELECT id, code, description FROM notifications ORDER BY id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $this->expireExpiredPreorderOffersForUser($userId);

        $offersStmt = $this->pdo->prepare(
            "SELECT pi.id, pi.status, pi.requested_boxes, pi.offered_price_per_box, pi.expected_price_per_box,
                    pi.discount_percent_snapshot, pi.offer_expires_at, pi.desired_delivery_date,
                    p.alias AS product_alias, p.variety, pt.alias AS type_alias, pt.name AS product_name
             FROM preorder_intents pi
             JOIN products p ON p.id = pi.product_id
             JOIN product_types pt ON pt.id = p.product_type_id
             WHERE pi.user_id = ?
               AND pi.status IN ('waiting_batch','linked_to_batch','awaiting_price_confirmation','offer_sent', 'confirmed', 'declined', 'expired','moved_to_cart')
             ORDER BY pi.updated_at DESC, pi.id DESC"
        );
        $offersStmt->execute([$userId]);
        $preorderOffers = $offersStmt->fetchAll(PDO::FETCH_ASSOC);
        $this->attachPendingDateChanges($preorderOffers);

        view('client/notifications', [
            'userName' => $_SESSION['name'] ?? null,
            'tgStart'  => $tgStart,
            'notifications' => $notificationRows,
            'preorderOffers' => $preorderOffers,
        ]);
    }



    /** Список SEO-материалов */
    public function materials(?string $categoryAlias = null): void
    {
        $category = null;
        $params = [];
        $where = 'm.is_active = 1';

        if ($categoryAlias !== null) {
            $catStmt = $this->pdo->prepare(
                'SELECT id, name, alias, meta_title, meta_description, meta_keywords
                   FROM content_categories
                  WHERE alias = ?
                  LIMIT 1'
            );
            $catStmt->execute([$categoryAlias]);
            $category = $catStmt->fetch(PDO::FETCH_ASSOC);
            if (!$category) {
                http_response_code(404);
                echo 'Раздел материалов не найден';
                return;
            }

            $where .= ' AND c.alias = ?';
            $params[] = $categoryAlias;
        }

        $stmt = $this->pdo->prepare(
            "SELECT m.id, m.alias AS mat_alias, m.title, m.short_desc, m.image_path, m.created_at,
                    c.name AS category_name, c.alias AS cat_alias
               FROM materials m
               JOIN content_categories c ON c.id = m.category_id
              WHERE {$where}
              ORDER BY c.name ASC, m.created_at DESC, m.id DESC"
        );
        $stmt->execute($params);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $categoriesStmt = $this->pdo->query(
            "SELECT c.id, c.name, c.alias, COUNT(m.id) AS materials_count
               FROM content_categories c
               JOIN materials m ON m.category_id = c.id AND m.is_active = 1
              GROUP BY c.id, c.name, c.alias
              ORDER BY c.name ASC"
        );

        $title = $category
            ? (string)($category['meta_title'] ?: $category['name'] . ' — материалы BerryGo')
            : 'Полезные материалы о ягодах и фруктах | BerryGo';
        $description = $category
            ? (string)($category['meta_description'] ?: 'SEO-материалы BerryGo: советы о выборе, хранении и заказе свежих ягод и фруктов с доставкой в Красноярске.')
            : 'Все полезные материалы BerryGo о свежих ягодах, фруктах, клубнике, черешне, хранении, сортах и доставке по Красноярску.';

        view('client/materials', [
            'materials' => $materials,
            'categories' => $categoriesStmt->fetchAll(PDO::FETCH_ASSOC),
            'currentCategory' => $category,
            'meta' => [
                'title' => $title,
                'description' => $description,
                'keywords' => $category ? (string)($category['meta_keywords'] ?? '') : 'ягоды Красноярск, клубника Красноярск, черешня Красноярск, доставка ягод, свежие фрукты',
            ],
            'breadcrumbs' => $category
                ? [
                    ['label' => 'Материалы', 'url' => '/content'],
                    ['label' => $category['name']],
                ]
                : [['label' => 'Материалы']],
        ]);
    }

    /** Показ одного материала */
    public function showMaterial(string $categoryAlias, string $alias): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.*, c.alias AS category_alias, c.name AS category_name
               FROM materials m
               JOIN content_categories c ON c.id = m.category_id
               WHERE m.alias = ? AND c.alias = ? AND m.is_active = 1"
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
                            p.box_size, p.box_unit,
                            CASE
                                WHEN COALESCE(pb.boxes_discount, 0) > 0 AND COALESCE(pb.discount_price_per_box, 0) > 0 THEN pb.discount_price_per_box
                                ELSE COALESCE(pb.instant_price_per_box, p.price, 0)
                            END AS price,
                            COALESCE(pb.instant_price_per_box, 0) AS current_price_per_box, p.sale_price, p.is_active,
                            COALESCE(NULLIF(batch_photo.image_path, ''), NULLIF(p.image_path, ''), '') AS image_path,
                            p.image_path AS product_image_path,
                            batch_photo.image_path AS batch_image_path,
                            DATE(pb.purchased_at) AS delivery_date,
                            COALESCE(u.company_name,u.name,'berryGo') AS seller_name
                       FROM products p
                       JOIN product_types t ON t.id = p.product_type_id
                       LEFT JOIN purchase_batches pb ON pb.id = (
                           SELECT pb2.id
                           FROM purchase_batches pb2
                           WHERE pb2.product_id = p.id
                             AND pb2.status IN ('purchased', 'arrived')
                             AND (pb2.boxes_free > 0 OR pb2.boxes_discount > 0)
                           ORDER BY pb2.purchased_at ASC, pb2.id ASC
                           LIMIT 1
                       )
                       LEFT JOIN purchase_batch_photos batch_photo ON batch_photo.id = (
                           SELECT pbp.id
                           FROM purchase_batch_photos pbp
                           WHERE pbp.purchase_batch_id = pb.id
                           ORDER BY pbp.id DESC
                           LIMIT 1
                       )
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
        $query = "SELECT p.*, t.name AS product, t.alias AS type_alias,
                         CASE
                             WHEN COALESCE(pb.boxes_discount, 0) > 0 AND COALESCE(pb.discount_price_per_box, 0) > 0 THEN pb.discount_price_per_box
                             WHEN pb.status = 'planned' THEN COALESCE(NULLIF(pb.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0)
                             ELSE COALESCE(pb.instant_price_per_box, p.price, 0)
                         END AS price,
                         COALESCE(pb.instant_price_per_box, 0) AS current_price_per_box,
                         CASE WHEN pb.status = 'planned' THEN COALESCE(NULLIF(pb.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0) ELSE COALESCE(pb.preorder_price_per_box, 0) END AS preorder_price_per_box,
                         COALESCE(NULLIF(batch_photo.image_path, ''), NULLIF(p.image_path, ''), '') AS display_image_path,
                         p.image_path AS product_image_path,
                         batch_photo.image_path AS batch_image_path,
                         DATE(pb.purchased_at) AS delivery_date,
                         pb.purchased_at AS latest_purchase_date,
                         instant_pb.id AS instant_purchase_batch_id,
                         COALESCE(instant_pb.boxes_free, 0) AS instant_available_boxes,
                         COALESCE(instant_pb.instant_price_per_box, 0) AS instant_price_per_box,
                         preorder_pb.id AS preorder_purchase_batch_id,
                         DATE(preorder_pb.purchased_at) AS preorder_availability_date,
                         (COALESCE(NULLIF(preorder_pb.boxes_total, 0), preorder_pb.boxes_free + preorder_pb.boxes_reserved) - preorder_pb.boxes_reserved) AS preorder_available_boxes,
                         COALESCE(NULLIF(preorder_pb.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0) AS confirmed_preorder_price_per_box
                  FROM products p
                  JOIN product_types t ON t.id = p.product_type_id
                  LEFT JOIN purchase_batches instant_pb ON instant_pb.id = (
                      SELECT pb_i.id
                      FROM purchase_batches pb_i
                      WHERE pb_i.product_id = p.id
                        AND pb_i.status IN ('purchased', 'arrived')
                        AND pb_i.boxes_free > 0
                        AND pb_i.instant_price_per_box > 0
                      ORDER BY pb_i.purchased_at ASC, pb_i.id ASC
                      LIMIT 1
                  )
                  LEFT JOIN purchase_batches preorder_pb ON preorder_pb.id = (
                      SELECT pb_p.id
                      FROM purchase_batches pb_p
                      WHERE pb_p.product_id = p.id
                        AND pb_p.status = 'planned'
                        AND pb_p.purchased_at IS NOT NULL
                        AND (COALESCE(NULLIF(pb_p.boxes_total, 0), pb_p.boxes_free + pb_p.boxes_reserved) - pb_p.boxes_reserved) > 0
                        AND COALESCE(NULLIF(pb_p.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0) > 0
                      ORDER BY pb_p.purchased_at ASC, pb_p.id ASC
                      LIMIT 1
                  )
                  LEFT JOIN purchase_batches pb ON pb.id = (
                      SELECT pb2.id
                      FROM purchase_batches pb2
                      WHERE pb2.product_id = p.id
                        AND ((pb2.status IN ('purchased', 'arrived') AND (pb2.boxes_free > 0 OR pb2.boxes_discount > 0))
                             OR (pb2.status = 'planned' AND COALESCE(NULLIF(pb2.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0) > 0))
                      ORDER BY CASE WHEN pb2.status IN ('purchased', 'arrived') AND pb2.boxes_free > 0 THEN 1 WHEN pb2.status IN ('purchased', 'arrived') AND pb2.boxes_discount > 0 THEN 2 WHEN pb2.status = 'planned' THEN 3 ELSE 9 END, pb2.purchased_at ASC, pb2.id ASC
                      LIMIT 1
                  )
                  LEFT JOIN purchase_batch_photos batch_photo ON batch_photo.id = (
                      SELECT pbp.id
                      FROM purchase_batch_photos pbp
                      WHERE pbp.purchase_batch_id = pb.id
                      ORDER BY pbp.id DESC
                      LIMIT 1
                  )
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
        if (!empty($product['display_image_path'])) {
            $product['image_path'] = $product['display_image_path'];
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
            "SELECT p.id, p.alias, t.name AS product, t.alias AS type_alias, p.variety, p.description, p.origin_country, p.box_size, p.box_unit,
                    CASE
                        WHEN COALESCE(pb_latest.boxes_discount, 0) > 0 AND COALESCE(pb_latest.discount_price_per_box, 0) > 0 THEN pb_latest.discount_price_per_box
                        WHEN pb_latest.status = 'planned' THEN COALESCE(NULLIF(pb_latest.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0)
                        ELSE COALESCE(pb_latest.instant_price_per_box, p.price, 0)
                    END AS price,
                    COALESCE(pb_latest.instant_price_per_box, 0) AS current_price_per_box,
                    CASE WHEN pb_latest.status = 'planned' THEN COALESCE(NULLIF(pb_latest.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0) ELSE COALESCE(pb_latest.preorder_price_per_box, 0) END AS preorder_price_per_box,
                    p.sale_price, p.is_active,
                    COALESCE(NULLIF(batch_photo.image_path, ''), NULLIF(p.image_path, ''), '') AS image_path,
                    p.image_path AS product_image_path,
                    batch_photo.image_path AS batch_image_path,
                    DATE(pb_latest.purchased_at) AS delivery_date,
                    COALESCE(u.company_name,u.name,'berryGo') AS seller_name,
                    pb_latest.purchased_at AS latest_purchase_date
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             LEFT JOIN users u ON u.id = p.seller_id
             LEFT JOIN purchase_batches pb_latest ON pb_latest.id = (
                 SELECT pb2.id
                 FROM purchase_batches pb2
                 WHERE pb2.product_id = p.id
                   AND ((pb2.status IN ('purchased', 'arrived') AND (pb2.boxes_free > 0 OR pb2.boxes_discount > 0))
                        OR (pb2.status = 'planned' AND COALESCE(NULLIF(pb2.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), p.price, 0) > 0))
                 ORDER BY CASE WHEN pb2.status IN ('purchased', 'arrived') AND pb2.boxes_free > 0 THEN 1 WHEN pb2.status IN ('purchased', 'arrived') AND pb2.boxes_discount > 0 THEN 2 WHEN pb2.status = 'planned' THEN 3 ELSE 9 END, pb2.purchased_at ASC, pb2.id ASC
                 LIMIT 1
             )
             LEFT JOIN purchase_batch_photos batch_photo ON batch_photo.id = (
                 SELECT pbp.id
                 FROM purchase_batch_photos pbp
                 WHERE pbp.purchase_batch_id = pb_latest.id
                 ORDER BY pbp.id DESC
                 LIMIT 1
             )
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
        $sourceSection = trim((string)($_POST['source_section'] ?? ''));
        $sourceDeliveryDate = trim((string)($_POST['source_delivery_date'] ?? ''));
        $desiredDeliveryDateRaw = trim((string)($_POST['desired_delivery_date'] ?? ''));
        $expectedPricePerBox = round((float)($_POST['expected_price_per_box'] ?? 0), 2);
        $discountPercentSnapshot = round((float)($_POST['discount_percent_snapshot'] ?? 0), 2);

        if ($userId <= 0 || $productId <= 0 || $requestedBoxes <= 0) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Некорректные параметры предзаказа'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $productStmt = $this->pdo->prepare("SELECT id, seller_id, price, preorder_price_per_box FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
        $productStmt->execute([$productId]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Товар не найден'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!empty($product['seller_id'])) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Предзаказ для этого товара недоступен'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $existingStmt = $this->pdo->prepare(
            "SELECT id FROM preorder_intents WHERE user_id = ? AND product_id = ? AND status IN ('waiting_batch','linked_to_batch','awaiting_price_confirmation','intent_created','offer_sent') ORDER BY id DESC LIMIT 1"
        );
        $existingStmt->execute([$userId, $productId]);
        $existingId = $existingStmt->fetchColumn();

        $plannedBatchStmt = $this->pdo->prepare(
            "SELECT pb.id, pb.purchased_at, COALESCE(NULLIF(pb.preorder_price_per_box, 0), NULLIF(p.preorder_price_per_box, 0), 0) AS preorder_price_per_box,
                    COALESCE(NULLIF(pb.instant_price_per_box, 0), NULLIF(p.price, 0), 0) AS regular_price_per_box,
                    (COALESCE(NULLIF(pb.boxes_total, 0), pb.boxes_free + pb.boxes_reserved) - pb.boxes_reserved) AS available_boxes
             FROM purchase_batches pb
             JOIN products p ON p.id = pb.product_id
             WHERE pb.product_id = ? AND pb.status = 'planned'
               AND (COALESCE(NULLIF(pb.boxes_total, 0), pb.boxes_free + pb.boxes_reserved) - pb.boxes_reserved) > 0
             ORDER BY pb.purchased_at ASC, pb.id ASC
             LIMIT 1"
        );
        $plannedBatchStmt->execute([$productId]);
        $plannedBatch = $plannedBatchStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $hasPlannedBatch = $plannedBatch !== null;
        $autoOfferPrice = $hasPlannedBatch ? round((float)($plannedBatch['preorder_price_per_box'] ?? 0), 2) : 0.0;
        if ($hasPlannedBatch && $autoOfferPrice <= 0) {
            $regularForPreorder = (float)($plannedBatch['regular_price_per_box'] ?? ($product['price'] ?? 0));
            $discountPercent = max(0.0, min(99.0, (float)(get_setting('ui_preorder_discount_percent', '10') ?? '10')));
            if ($regularForPreorder > 0) {
                $autoOfferPrice = round($regularForPreorder * ((100.0 - $discountPercent) / 100.0), 0);
            }
        }
        if (!$hasPlannedBatch || $autoOfferPrice <= 0) {
            error_log('Preorder price unavailable: ' . json_encode([
                'product_id' => $productId,
                'purchase_batch_id' => $plannedBatch['id'] ?? null,
                'regular_price' => $plannedBatch['regular_price_per_box'] ?? ($product['price'] ?? null),
                'expected_price' => $autoOfferPrice,
            ], JSON_UNESCAPED_UNICODE));
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Цена предзаказа уточняется'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $expectedPricePerBox = $autoOfferPrice;
        $targetStatus = 'linked_to_batch';
        $targetBatchId = (int)$plannedBatch['id'];

        $etaDateValue = null;
        if ($sourceSection === 'in_stock' && $sourceDeliveryDate !== '') {
            $ts = strtotime($sourceDeliveryDate);
            if ($ts !== false) {
                $etaDateValue = date('Y-m-d', strtotime('+2 day', $ts));
            }
        }

        $desiredDeliveryDate = null;
        if ($desiredDeliveryDateRaw !== '' && $desiredDeliveryDateRaw !== 'any') {
            $tsDesired = strtotime($desiredDeliveryDateRaw);
            if ($tsDesired === false) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Некорректная дата получения предзаказа'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $desiredDeliveryDate = date('Y-m-d', $tsDesired);
            $minPreorderDate = date('Y-m-d', strtotime('+2 day'));
            if ($desiredDeliveryDate < $minPreorderDate) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => false,
                    'error' => 'Предзаказ можно оформить не раньше ' . date('d.m.Y', strtotime($minPreorderDate)) . '. Если ягода нужна раньше, оформите обычную покупку по текущей цене.',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
        }
        $expectedPriceStored = $expectedPricePerBox > 0 ? $expectedPricePerBox : null;
        $discountSnapshotStored = $discountPercentSnapshot >= 0 ? $discountPercentSnapshot : null;

        if ($existingId) {
            $this->pdo->prepare(
                "UPDATE preorder_intents
                 SET requested_boxes = ?, desired_delivery_date = ?, expected_price_per_box = ?, discount_percent_snapshot = ?,
                     purchase_batch_id = ?, status = ?, offered_price_per_box = ?, offer_expires_at = NULL, checkout_token = NULL, updated_at = NOW()
                 WHERE id = ?"
            )->execute([
                $requestedBoxes,
                $desiredDeliveryDate,
                $expectedPriceStored,
                $discountSnapshotStored,
                $targetBatchId,
                $targetStatus,
                $autoOfferPrice > 0 ? $autoOfferPrice : null,
                (int)$existingId,
            ]);
            $intentId = (int)$existingId;
            $this->logPreorderEvent($intentId, 'intent_updated', null, 'intent_created', [
                'requested_boxes' => $requestedBoxes,
                'source_section' => $sourceSection,
                'source_delivery_date' => $sourceDeliveryDate,
                'eta_delivery_date' => $etaDateValue,
                'desired_delivery_date' => $desiredDeliveryDate ?? 'any',
                'expected_price_per_box' => $expectedPriceStored,
                'discount_percent_snapshot' => $discountSnapshotStored,
                'planned_batch_id' => $targetBatchId,
                'status' => $targetStatus,
            ]);
        } else {
            $this->pdo->prepare(
                "INSERT INTO preorder_intents (
                    user_id, product_id, purchase_batch_id, requested_boxes, desired_delivery_date, expected_price_per_box, discount_percent_snapshot, status, offered_price_per_box, created_at, updated_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
            )->execute([$userId, $productId, $targetBatchId, $requestedBoxes, $desiredDeliveryDate, $expectedPriceStored, $discountSnapshotStored, $targetStatus, $autoOfferPrice > 0 ? $autoOfferPrice : null]);
            $intentId = (int)$this->pdo->lastInsertId();
            $this->logPreorderEvent($intentId, 'intent_created', null, 'intent_created', [
                'requested_boxes' => $requestedBoxes,
                'source_section' => $sourceSection,
                'source_delivery_date' => $sourceDeliveryDate,
                'eta_delivery_date' => $etaDateValue,
                'desired_delivery_date' => $desiredDeliveryDate ?? 'any',
                'expected_price_per_box' => $expectedPriceStored,
                'discount_percent_snapshot' => $discountSnapshotStored,
                'planned_batch_id' => $targetBatchId,
                'status' => $targetStatus,
            ]);
        }

        $etaText = 'на ближайшую возможную дату';
        if ($sourceSection === 'in_stock' && $sourceDeliveryDate !== '') {
            $ts = strtotime($sourceDeliveryDate);
            if ($ts !== false) {
                $etaText = 'на ' . date('d.m.Y', strtotime('+2 day', $ts));
            }
        }

        if ($targetBatchId === null || $autoOfferPrice <= 0) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'intent_id' => $intentId,
                'status' => $targetStatus,
                'status_label' => 'Ждёт закупку',
                'eta_delivery_date' => $etaDateValue,
                'message' => 'Предзаказ сохранён' . ($desiredDeliveryDate ? ' на ' . date('d.m.Y', strtotime($desiredDeliveryDate)) : ' на ближайшую возможную дату') . '. Мы подтвердим его после назначения поставки и финальной цены.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $modeCheckStmt = $this->pdo->prepare(
            "SELECT stock_mode FROM cart_items WHERE user_id = ? AND product_id = ? LIMIT 1"
        );
        $modeCheckStmt->execute([$userId, $productId]);
        $existingCartMode = $modeCheckStmt->fetchColumn();
        if ($existingCartMode !== false && (string)$existingCartMode !== 'preorder') {
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => 'Этот товар уже есть в корзине в другом режиме. Сначала удалите его из корзины.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $_SESSION['delivery_date'][$productId] = $desiredDeliveryDate ?: PLACEHOLDER_DATE;
        $this->pdo->prepare(
            "INSERT INTO cart_items (user_id, product_id, quantity, unit_price, stock_mode, purchase_batch_id, boxes, sale_price_per_box)" .
            " VALUES (?, ?, ?, ?, 'preorder', ?, ?, ?)" .
            " ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)," .
            " unit_price = VALUES(unit_price)," .
            " stock_mode = 'preorder'," .
            " purchase_batch_id = VALUES(purchase_batch_id)," .
            " boxes = VALUES(boxes)," .
            " sale_price_per_box = VALUES(sale_price_per_box)"
        )->execute([$userId, $productId, $requestedBoxes, $autoOfferPrice, $targetBatchId, $requestedBoxes, $autoOfferPrice]);
        $this->refreshCartTotal();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'intent_id' => $intentId,
            'status' => 'in_cart',
            'status_label' => 'В корзине',
            'eta_delivery_date' => $etaDateValue,
            'cart_url' => '/cart',
            'message' => 'Предзаказ добавлен в корзину' . ($desiredDeliveryDate ? ' на ' . date('d.m.Y', strtotime($desiredDeliveryDate)) : ': ' . $etaText) . '. Цена предварительная. Точная цена будет после выкупа.',
        ], JSON_UNESCAPED_UNICODE);
    }

    public function confirmPreorderIntent(int $intentId): void
    {
        requireClient();
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $this->pdo->prepare(
            "SELECT id, status, offer_expires_at, offered_price_per_box, expected_price_per_box FROM preorder_intents WHERE id = ? AND user_id = ? LIMIT 1"
        );
        $stmt->execute([$intentId, $userId]);
        $intent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$intent) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Предзаказ не найден'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!in_array((string)$intent['status'], ['awaiting_price_confirmation','offer_sent'], true)) {
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Оффер недоступен для подтверждения'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $expiresAt = $intent['offer_expires_at'] ?? null;
        if ($expiresAt === null || strtotime((string)$expiresAt) < time()) {
            $this->pdo->prepare("UPDATE preorder_intents SET status = 'expired', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$intentId]);
            $this->logPreorderEvent($intentId, 'offer_expired_on_confirm', (string)$intent['status'], 'expired');
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Время подтверждения истекло'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $token = bin2hex(random_bytes(24));
        $this->pdo->prepare(
            "UPDATE preorder_intents SET status = 'confirmed', checkout_token = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
        )->execute([$token, $intentId]);
        $this->logPreorderEvent($intentId, 'offer_confirmed', (string)$intent['status'], 'confirmed');

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'status' => 'confirmed',
            'status_label' => $this->preorderIntentStatusLabel('confirmed'),
            'continue_url' => '/preorder/continue/' . $token,
            'message' => 'Финальная цена подтверждена. Продолжите оформление заказа.',
        ], JSON_UNESCAPED_UNICODE);
    }

    public function declinePreorderIntent(int $intentId): void
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
        if (!in_array((string)$intent['status'], ['awaiting_price_confirmation','offer_sent'], true)) {
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Оффер недоступен для отказа'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $expiresAt = $intent['offer_expires_at'] ?? null;
        if ($expiresAt !== null && strtotime((string)$expiresAt) < time()) {
            $this->pdo->prepare("UPDATE preorder_intents SET status = 'expired', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$intentId]);
            $this->logPreorderEvent($intentId, 'offer_expired_on_decline', (string)$intent['status'], 'expired');
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Время подтверждения истекло'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->pdo->prepare("UPDATE preorder_intents SET status = 'declined', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$intentId]);
        $this->logPreorderEvent($intentId, 'offer_declined', (string)$intent['status'], 'declined');

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'status' => 'declined',
            'status_label' => $this->preorderIntentStatusLabel('declined'),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function respondPreorderDateChange(int $intentId): void
    {
        requireClient();
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $decision = (string)($_POST['decision'] ?? '');
        if (!in_array($decision, ['accept_new','next_supply','wait_next','cancel'], true)) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Некорректное решение по новой дате'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, user_id, product_id, purchase_batch_id, status, desired_delivery_date
             FROM preorder_intents
             WHERE id = ? AND user_id = ?
             LIMIT 1"
        );
        $stmt->execute([$intentId, $userId]);
        $intent = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$intent) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Предзаказ не найден'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pending = $this->pendingDateChangeForIntent($intentId);
        if ($pending === null) {
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Нет активного запроса на подтверждение даты'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $meta = $pending['meta'];
        $fromStatus = (string)($intent['status'] ?? '');

        if ($decision === 'cancel') {
            $this->pdo->prepare("UPDATE preorder_intents SET status = 'declined', checkout_token = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$intentId]);
            $this->logPreorderEvent($intentId, 'date_change_cancelled', $fromStatus, 'declined', ['request_event_id' => $pending['id']]);
            $this->jsonDateChangeResponse('declined', 'Предзаказ отменён.');
            return;
        }

        if ($decision === 'wait_next') {
            $this->pdo->prepare("UPDATE preorder_intents SET purchase_batch_id = NULL, status = 'waiting_batch', offer_expires_at = NULL, checkout_token = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$intentId]);
            $this->logPreorderEvent($intentId, 'date_change_wait_next', $fromStatus, 'waiting_batch', ['request_event_id' => $pending['id']]);
            $this->jsonDateChangeResponse('waiting_batch', 'Ок, будем ждать следующую поставку.');
            return;
        }

        if ($decision === 'next_supply') {
            $nextBatchId = (int)($meta['next_batch_id'] ?? 0);
            $nextDate = $this->normalizeDateString($meta['next_supply_date'] ?? null);
            if ($nextBatchId <= 0 || $nextDate === null) {
                $next = $this->findNextSupplyForPreorder((int)$intent['product_id'], (int)($intent['purchase_batch_id'] ?? 0), $this->normalizeDateString($meta['new_supply_date'] ?? $meta['old_supply_date'] ?? null));
                $nextBatchId = (int)($next['id'] ?? 0);
                $nextDate = $next['date'] ?? null;
            }
            if ($nextBatchId <= 0 || $nextDate === null) {
                $this->pdo->prepare("UPDATE preorder_intents SET purchase_batch_id = NULL, status = 'waiting_batch', offer_expires_at = NULL, checkout_token = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$intentId]);
                $this->logPreorderEvent($intentId, 'date_change_wait_next', $fromStatus, 'waiting_batch', ['request_event_id' => $pending['id'], 'reason' => 'next_supply_missing']);
                $this->jsonDateChangeResponse('waiting_batch', 'Следующая дата пока неизвестна. Оставили предзаказ в ожидании поставки.');
                return;
            }
            $this->pdo->prepare("UPDATE preorder_intents SET purchase_batch_id = ?, desired_delivery_date = ?, status = 'linked_to_batch', offer_expires_at = NULL, checkout_token = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$nextBatchId, $nextDate, $intentId]);
            $this->logPreorderEvent($intentId, 'date_change_next_supply', $fromStatus, 'linked_to_batch', [
                'request_event_id' => $pending['id'],
                'next_batch_id' => $nextBatchId,
                'next_supply_date' => $nextDate,
            ]);
            $this->jsonDateChangeResponse('linked_to_batch', 'Предзаказ перенесён на следующую поставку ' . date('d.m.Y', strtotime($nextDate)) . '.');
            return;
        }

        $proposedDate = $this->normalizeDateString($meta['proposed_delivery_date'] ?? $meta['new_supply_date'] ?? $meta['next_supply_date'] ?? null);
        if ($proposedDate === null) {
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Новая дата пока неизвестна'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $acceptBatchId = (int)($meta['next_batch_id'] ?? 0);
        if ($acceptBatchId > 0 && $this->normalizeDateString($meta['next_supply_date'] ?? null) === $proposedDate) {
            $this->pdo->prepare("UPDATE preorder_intents SET purchase_batch_id = ?, desired_delivery_date = ?, status = 'linked_to_batch', offer_expires_at = NULL, checkout_token = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$acceptBatchId, $proposedDate, $intentId]);
            $toStatus = 'linked_to_batch';
        } else {
            $this->pdo->prepare("UPDATE preorder_intents SET desired_delivery_date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$proposedDate, $intentId]);
            $toStatus = $fromStatus;
        }
        $this->logPreorderEvent($intentId, 'date_change_accepted', $fromStatus, $toStatus, [
            'request_event_id' => $pending['id'],
            'accepted_delivery_date' => $proposedDate,
            'accepted_batch_id' => $acceptBatchId > 0 ? $acceptBatchId : null,
        ]);
        $this->jsonDateChangeResponse($fromStatus, 'Новая дата подтверждена: ' . date('d.m.Y', strtotime($proposedDate)) . '.');
    }

    private function jsonDateChangeResponse(string $status, string $message): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'status' => $status,
            'status_label' => $this->preorderIntentStatusLabel($status),
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
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
        $offer['status_label'] = $this->preorderIntentStatusLabel((string)($offer['status'] ?? ''));
        view('client/preorder_offer', ['offer' => $offer]);
    }

    private function expireExpiredPreorderOffersForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            "SELECT id, status
             FROM preorder_intents
             WHERE user_id = ?
               AND status IN ('awaiting_price_confirmation','offer_sent')
               AND offer_expires_at IS NOT NULL
               AND offer_expires_at < CURRENT_TIMESTAMP"
        );
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($items === []) {
            return 0;
        }
        $ids = array_map(static fn (array $item): int => (int)$item['id'], $items);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $update = $this->pdo->prepare(
            "UPDATE preorder_intents
             SET status = 'expired', updated_at = CURRENT_TIMESTAMP
             WHERE id IN ({$placeholders})
               AND status IN ('awaiting_price_confirmation','offer_sent')"
        );
        $update->execute($ids);
        foreach ($items as $item) {
            $this->logPreorderEvent((int)$item['id'], 'offer_auto_expired', (string)$item['status'], 'expired');
        }
        return count($items);
    }

    /** @param array<int,array<string,mixed>> $preorderOffers */
    private function attachPendingDateChanges(array &$preorderOffers): void
    {
        $ids = array_map(static fn (array $offer): int => (int)($offer['id'] ?? 0), $preorderOffers);
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return;
        }
        $pending = $this->pendingDateChangesForIntents($ids);
        foreach ($preorderOffers as &$offer) {
            $intentId = (int)($offer['id'] ?? 0);
            if (isset($pending[$intentId])) {
                $offer['date_change'] = $pending[$intentId];
            }
        }
        unset($offer);
    }

    /** @return array{id:int,meta:array<string,mixed>}|null */
    private function pendingDateChangeForIntent(int $intentId): ?array
    {
        $pending = $this->pendingDateChangesForIntents([$intentId]);
        return $pending[$intentId] ?? null;
    }

    /**
     * @param array<int,int> $intentIds
     * @return array<int,array{id:int,meta:array<string,mixed>}>
     */
    private function pendingDateChangesForIntents(array $intentIds): array
    {
        $intentIds = array_values(array_filter(array_map('intval', $intentIds), static fn (int $id): bool => $id > 0));
        if ($intentIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($intentIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, preorder_intent_id, event_type, meta_json
             FROM preorder_intent_events
             WHERE preorder_intent_id IN ({$placeholders})
               AND event_type IN ('date_change_requested','date_change_accepted','date_change_next_supply','date_change_wait_next','date_change_cancelled')
             ORDER BY preorder_intent_id ASC, id ASC"
        );
        $stmt->execute($intentIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $pending = [];
        foreach ($rows as $row) {
            $intentId = (int)($row['preorder_intent_id'] ?? 0);
            $eventType = (string)($row['event_type'] ?? '');
            if ($eventType === 'date_change_requested') {
                $meta = json_decode((string)($row['meta_json'] ?? ''), true);
                $pending[$intentId] = [
                    'id' => (int)($row['id'] ?? 0),
                    'meta' => is_array($meta) ? $meta : [],
                ];
                continue;
            }
            unset($pending[$intentId]);
        }
        return $pending;
    }

    private function normalizeDateString(mixed $value): ?string
    {
        $raw = trim((string)($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }

    /** @return array{id:int,date:string}|null */
    private function findNextSupplyForPreorder(int $productId, int $excludeBatchId, ?string $afterDate): ?array
    {
        if ($productId <= 0) {
            return null;
        }
        $afterDate = $this->normalizeDateString($afterDate) ?? date('Y-m-d');
        $stmt = $this->pdo->prepare(
            "SELECT id, DATE(purchased_at) AS supply_date
             FROM purchase_batches
             WHERE product_id = ?
               AND id <> ?
               AND status IN ('planned','purchased','arrived')
               AND purchased_at IS NOT NULL
               AND DATE(purchased_at) > ?
             ORDER BY purchased_at ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$productId, $excludeBatchId, $afterDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return ['id' => (int)$row['id'], 'date' => (string)$row['supply_date']];
    }

    private function preorderIntentStatusLabel(string $status): string
    {
        return match ($status) {
            'waiting_batch', 'intent_created' => 'Ожидает закупку',
            'linked_to_batch' => 'Привязана к закупке',
            'awaiting_price_confirmation', 'offer_sent' => 'Ожидает подтверждения цены',
            'confirmed' => 'Подтверждена',
            'moved_to_cart' => 'Переведена в корзину',
            'completed' => 'Выполнена',
            'checkout_completed' => 'Выполнена',
            'declined' => 'Отказ',
            'expired' => 'Истекла',
            default => $status,
        };
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


    private function cartGroupKey(string $stockMode, string $deliveryDate): string
    {
        return $stockMode . '|' . $deliveryDate;
    }

    private function dateFromCartGroupKey(string $groupKey): string
    {
        $parts = explode('|', $groupKey, 2);
        return $parts[1] ?? $groupKey;
    }

    public function normalizeOrderModes(array $itemsByDate, array $postedOrderModes): array
    {
        $allowedModes = ['preorder', 'instant', 'discount_stock'];
        $result = [];

        foreach ($itemsByDate as $dateKey => $_) {
            $rawMode = (string)($postedOrderModes[$dateKey] ?? '');
            if (!in_array($rawMode, $allowedModes, true)) {
                $rawMode = ($this->dateFromCartGroupKey((string)$dateKey) === PLACEHOLDER_DATE) ? 'preorder' : 'instant';
            }
            $result[(string)$dateKey] = $rawMode;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $itemsByDate
     * @param array<int, array<string, mixed>> $rawItems
     * @param array<string, mixed> $postedOrderModes
     * @return array<string, string>
     */
    private function resolveOrderModesFromCart(array $itemsByDate, array $rawItems, array $postedOrderModes): array
    {
        $allowedModes = ['preorder', 'instant', 'discount_stock'];
        $modeByDate = [];

        foreach ($rawItems as $it) {
            $pid = (int)$it['product_id'];
            $cartItemId = (int)($it['cart_item_id'] ?? 0);
            $mode = (string)($it['stock_mode'] ?? 'instant');
            $date = $_SESSION['delivery_date'][$cartItemId] ?? $_SESSION['delivery_date'][$pid] ?? PLACEHOLDER_DATE;
            if (!in_array($mode, $allowedModes, true)) {
                $mode = ($date === PLACEHOLDER_DATE) ? 'preorder' : 'instant';
            }
            $dateKey = $this->cartGroupKey($mode, (string)$date);
            if (!isset($modeByDate[$dateKey])) {
                $modeByDate[$dateKey] = $mode;
            }
        }

        // compatibility fallback: allow posted mode only when mode for date cannot be inferred
        foreach ($itemsByDate as $dateKey => $_) {
            if (!isset($modeByDate[$dateKey])) {
                $rawMode = (string)($postedOrderModes[$dateKey] ?? '');
                if (!in_array($rawMode, $allowedModes, true)) {
                    $rawMode = ($this->dateFromCartGroupKey((string)$dateKey) === PLACEHOLDER_DATE) ? 'preorder' : 'instant';
                }
                $modeByDate[$dateKey] = $rawMode;
            }
        }

        return $modeByDate;
    }

    /** @param array<string, string> $orderModeByDate */
    public function shouldDisableRewardsForModes(array $orderModeByDate): bool
    {
        return in_array('discount_stock', $orderModeByDate, true);
    }

}
