<?php
namespace App\Controllers;

use PDO;
use App\Helpers\PhoneNormalizer;

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
        $sale = $this->pdo->query(
            "SELECT p.id,
                    p.alias,
                    t.name AS product,
                    t.alias AS type_alias,
                    p.variety,
                    p.description,
                    p.origin_country,
                    p.box_size,
                    p.box_unit,
                    p.price,
                    p.sale_price,
                    p.is_active,
                    p.image_path,
                    p.delivery_date,
                    COALESCE(u.company_name,u.name,'berryGo') AS seller_name
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             LEFT JOIN users u ON u.id = p.seller_id
             WHERE p.is_active = 1 AND p.sale_price > 0
             ORDER BY p.id DESC
             LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);

        $regular = $this->pdo->query(
            "SELECT p.id,
                    p.alias,
                    t.name AS product,
                    t.alias AS type_alias,
                    p.variety,
                    p.description,
                    p.origin_country,
                    p.box_size,
                    p.box_unit,
                    p.price,
                    p.sale_price,
                    p.is_active,
                    p.image_path,
                    p.delivery_date,
                    COALESCE(u.company_name,u.name,'berryGo') AS seller_name
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             LEFT JOIN users u ON u.id = p.seller_id
             WHERE p.is_active = 1
               AND p.delivery_date IS NOT NULL
               AND p.seller_id IS NULL
             ORDER BY p.id DESC
             LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);

        $sellerProducts = $this->pdo->query(
            "SELECT p.id,
                    p.alias,
                    t.name AS product,
                    t.alias AS type_alias,
                    p.variety,
                    p.description,
                    p.origin_country,
                    p.box_size,
                    p.box_unit,
                    p.price,
                    p.sale_price,
                    p.is_active,
                    p.image_path,
                    p.delivery_date,
                    COALESCE(u.company_name,u.name,'berryGo') AS seller_name
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             LEFT JOIN users u ON u.id = p.seller_id
             WHERE p.is_active = 1
               AND p.seller_id IS NOT NULL
             ORDER BY p.id DESC
             LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);

        $preorder = $this->pdo->query(
            "SELECT p.id,
                    p.alias,
                    t.name AS product,
                    t.alias AS type_alias,
                    p.variety,
                    p.description,
                    p.origin_country,
                    p.box_size,
                    p.box_unit,
                    p.price,
                    p.sale_price,
                    p.is_active,
                    p.image_path,
                    p.delivery_date,
                    COALESCE(u.company_name,u.name,'berryGo') AS seller_name
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             LEFT JOIN users u ON u.id = p.seller_id
             WHERE p.is_active = 1
               AND p.delivery_date IS NULL
               AND p.seller_id IS NULL
             ORDER BY p.id DESC
             LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);

        $materials = $this->pdo->query(
            "SELECT m.id, m.alias AS mat_alias, m.title, m.short_desc, m.image_path,
                    c.alias AS cat_alias
               FROM materials m
               JOIN content_categories c ON c.id = m.category_id
               ORDER BY m.created_at DESC
               LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);

        view('client/home', [
            'saleProducts'     => $sale,
            'regularProducts'  => $regular,
            'sellerProducts'   => $sellerProducts,
            'preorderProducts' => $preorder,
            'materials'       => $materials,
            'userName'        => $_SESSION['name'] ?? null,
        ]);
    }

    /** Каталог: сортировка по наличию/дате */
    public function catalog(): void
    {
        $today = date('Y-m-d');
        $all = $this->pdo->query(
            "SELECT
                 p.id,
                 p.alias,
                 t.name        AS product,
                 t.alias       AS type_alias,
                 p.variety,
                 p.description,
                 p.origin_country,
                 p.box_size,
                 p.box_unit,
                 p.price,
                 p.sale_price,
                 p.is_active,
                 p.image_path,
                 p.delivery_date,
                 COALESCE(u.company_name,u.name,'berryGo') AS seller_name
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             LEFT JOIN users u ON u.id = p.seller_id
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

        // Получаем только те категории, у которых есть активные товары
        $types = $this->pdo->query(
            "SELECT DISTINCT t.id, t.name, t.alias
               FROM product_types t
               JOIN products p ON p.product_type_id = t.id
              WHERE p.is_active = 1
              ORDER BY t.name"
        )->fetchAll(PDO::FETCH_ASSOC);
    
        $debugData = [
            'productsCount' => count($all),
            'today'         => $today,
        ];
    
        view('client/catalog', [
            'products'  => $all,
            'types'     => $types,
            'userName'  => $_SESSION['name'] ?? null,
            'debugData' => $debugData,
            'breadcrumbs' => [ ['label' => 'Каталог'] ],
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

        // читаем выбор даты
        $dateOpt = $_POST['delivery_date'][$productId] ?? null;
        if ($dateOpt) {
            $_SESSION['delivery_date'][$productId] = $dateOpt;
        }

        if ($productId && $quantity > 0) {
            $priceStmt = $this->pdo->prepare(
                "SELECT price, sale_price, box_size FROM products WHERE id = ?"
            );
            $priceStmt->execute([$productId]);
            $row = $priceStmt->fetch(PDO::FETCH_ASSOC);
            $priceBox = 0.0;
            if ($row) {
                $sale    = (float)($row['sale_price'] ?? 0); // per kg
                $regular = (float)($row['price'] ?? 0);      // per kg
                $boxSize = (float)($row['box_size'] ?? 0);
                $kgPrice = $sale > 0 ? $sale : $regular;
                $priceBox = $kgPrice * $boxSize;
            }

            $this->pdo->prepare(
                "INSERT INTO cart_items (user_id, product_id, quantity, unit_price)" .
                " VALUES (?, ?, ?, ?)" .
                " ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)," .
                " unit_price = VALUES(unit_price)"
            )->execute([$userId, $productId, $quantity, $priceBox]);
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
     * - рассчитывает, сколько баллов можно списать
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

    // 5) Считаем, сколько баллов списать (не более суммы заказа)
    $pointsToUse  = min($pointsBalance, $allTotal);

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
            $stmtAddr = $this->pdo->prepare("SELECT street FROM addresses WHERE id = ?");
            $stmtAddr->execute([(int)$addrInput]);
            $streetVal = (string)$stmtAddr->fetchColumn();
        } else {
            $addressIds[$dateKey] = $this->ensureAddress($userId, $addrInput, $recipientName, $recipientPhone);
            $streetVal = $addrInput;
        }
        // сохраняем адрес как обычный, скидка за самовывоз не применяется
    }

    // 9) СОЗДАЁМ ЗАКАЗЫ ПО КАЖДОЙ ДАТЕ, учитываем дату и слот
    $createdOrderIds = [];
        foreach ($itemsByDate as $dateKey => $block) {
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

        $slotId = $_POST['slot_id'][$dateKey] ?? null; // из формы

        // (7.2) Вставляем заказ. Поскольку у таблицы orders есть колонки discount_applied, points_used, points_accrued, нужно задать их:
        $stmtOrder = $this->pdo->prepare(
            "INSERT INTO orders
               (user_id, address_id, slot_id, status, total_amount,
                discount_applied, points_used, points_accrued, coupon_code,
                delivery_date, created_at)
             VALUES (?, ?, ?, 'new', ?, ?, ?, ?, ?, ?, NOW())"
        );
        $pointsAccrued = 0; // пока 0, начислим ниже, если надо
        $stmtOrder->execute([
            $userId,
            $addressIds[$dateKey],
            $slotId,
            $finalSum,
            $couponDiscount, // discount_applied = скидка по купону
            $pointsDiscount,  // points_used = списанные баллы
            $pointsAccrued,   // points_accrued = пока 0
            $couponCode,
            $dateKey
        ]);
        $orderId = (int)$this->pdo->lastInsertId();
        $createdOrderIds[] = $orderId;

        // (7.3) Вставляем позиции в order_items
        $stmtItem = $this->pdo->prepare(
            "INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price)\n" .
            "VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($block as $prodId => $data) {
            $kgQty   = $data['quantity'] * $data['box_size'];
            $kgPrice = $data['box_size'] > 0
                ? $data['unit_price'] / $data['box_size']
                : $data['unit_price'];
            $stmtItem->execute([
                $orderId,
                $prodId,
                $kgQty,
                $data['quantity'],
                $kgPrice,
            ]);
        }

        // (7.4) Создаём записи выплат для селлеров
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

    // Оповещаем администраторов о новых заказах
    $ordersController = new OrdersController($this->pdo);
    foreach ($createdOrderIds as $oid) {
        $ordersController->notifyAdmins($oid);
    }

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
        $placeholder = defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15';
        foreach ($orders as &$o) {
            $itemsStmt->execute([$o['id']]);
            $o['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($o['delivery_date']) || $o['delivery_date'] === $placeholder) {
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
}
