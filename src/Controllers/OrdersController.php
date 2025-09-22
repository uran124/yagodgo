<?php
namespace App\Controllers;

use PDO;
use App\Helpers\Auth;
use App\Helpers\ReferralHelper;

class OrdersController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Returns base path depending on user role
     */
    private function basePath(): string
    {
        $role = $_SESSION['role'] ?? '';
        return match ($role) {
            'manager' => '/manager/orders',
            'partner' => '/partner/orders',
            default   => '/admin/orders',
        };
    }

    /**
     * Normalize phone number to 7XXXXXXXXXX format
     */
    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (strlen($digits) === 10) {
            return '7' . $digits;
        }
        if (strlen($digits) === 11) {
            $first = $digits[0];
            $rest  = substr($digits, 1);
            return ($first === '8' ? '7' : $first) . $rest;
        }
        return $digits;
    }

    /**
     * Ensure pickup address exists for the user and return its ID.
     */
    private function ensurePickupAddress(int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT name, phone FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $name = $user['name'] ?? '';
        $phone = $user['phone'] ?? '';

        $street = 'Самовывоз: 9 мая, 73';
        $stmt = $this->pdo->prepare("SELECT id FROM addresses WHERE user_id = ? AND street = ? AND recipient_name = ? AND recipient_phone = ?");
        $stmt->execute([$userId, $street, $name, $phone]);
        if ($id = $stmt->fetchColumn()) {
            return (int)$id;
        }

        $this->pdo->prepare("INSERT INTO addresses (user_id, street, recipient_name, recipient_phone, is_primary, created_at) VALUES (?, ?, ?, ?, 0, NOW())")
            ->execute([$userId, $street, $name, $phone]);

        return (int)$this->pdo->lastInsertId();
    }

    // Список заказов (админ/менеджер)
    public function index(): void
    {
        $managerId = isset($_GET['manager']) ? (int)$_GET['manager'] : 0;

        $sql = "SELECT o.id, o.status, o.total_amount, o.delivery_date,\n" .
               "       o.points_used, o.coupon_code, o.discount_applied,\n" .
               "       o.slot_id, d.time_from AS slot_from, d.time_to AS slot_to,\n" .
               "       u.name AS client_name, u.phone, a.street AS address,\n" .
               "       o.created_at, o.comment\n" .
               "FROM orders o\n" .
               "JOIN users u ON u.id = o.user_id\n" .
               "LEFT JOIN addresses a ON a.id = o.address_id\n" .
               "LEFT JOIN delivery_slots d ON d.id = o.slot_id";
        $params = [];
        if ($managerId > 0) {
            $sql .= " WHERE u.referred_by = ?";
            $params[] = $managerId;
        }
        $sql .= " ORDER BY o.delivery_date DESC, d.time_from DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$orders) {
            $orders = [];
        }

        // Load items for all orders (for manager view)
        $orderIds = array_column($orders, 'id');
        $itemsByOrder = [];
        if ($orderIds) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $iStmt = $this->pdo->prepare(
                "SELECT oi.order_id, oi.quantity, oi.boxes, oi.unit_price,\n" .
                "       t.name AS product_name, p.variety, p.box_size, p.box_unit\n" .
                "FROM order_items oi\n" .
                "JOIN products p ON p.id = oi.product_id\n" .
                "JOIN product_types t ON t.id = p.product_type_id\n" .
                "WHERE oi.order_id IN ($placeholders)\n" .
                "ORDER BY oi.order_id"
            );
            $iStmt->execute($orderIds);
            while ($row = $iStmt->fetch(PDO::FETCH_ASSOC)) {
                $itemsByOrder[$row['order_id']][] = $row;
            }
        }

        // Prefetch coupon info
        $codes = array_values(array_filter(
            array_column($orders, 'coupon_code'),
            fn($c) => $c !== null && $c !== ''
        ));
        $couponInfo = [];
        if ($codes) {
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $cStmt = $this->pdo->prepare(
                "SELECT code, type, discount, points FROM coupons WHERE code IN ($placeholders)"
            );
            $cStmt->execute($codes);
            foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $couponInfo[$c['code']] = $c;
            }
        }

        $refStmt = $this->pdo->prepare("SELECT id FROM users WHERE referral_code = ?");

        foreach ($orders as &$o) {
            $o['items'] = $itemsByOrder[$o['id']] ?? [];

            $rawTotal = 0;
            foreach ($o['items'] as $it) {
                $rawTotal += $it['quantity'] * $it['unit_price'];
            }



            $coupon = null;
            if (!empty($o['coupon_code'])) {
                $coupon = $couponInfo[$o['coupon_code']] ?? null;
                if (!$coupon) {
                    $refStmt->execute([$o['coupon_code']]);
                    if ($refStmt->fetch()) {
                        $coupon = [
                            'code' => $o['coupon_code'],
                            'type' => 'discount',
                            'discount' => 10,
                        ];
                    }
                }
            }
            $o['coupon'] = $coupon;

            $pointsFromBalance = (int)($o['points_used'] ?? 0);
            if ($coupon && $coupon['type'] === 'points') {
                $pointsFromBalance = max(0, $pointsFromBalance - (int)$coupon['points']);
            }
            $o['points_from_balance'] = $pointsFromBalance;

            $o['coupon_discount'] = (int)$o['discount_applied'];
        }
        unset($o);

        $managersStmt = $this->pdo->query("SELECT id, name FROM users WHERE role = 'manager' ORDER BY name");
        $managers = $managersStmt->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('orders/index', [
            'pageTitle'       => 'Заказы',
            'orders'          => $orders,
            'managers'       => $managers,
            'selectedManager' => $managerId,
        ]);
    }

    // Детали заказа (админ)
    public function show(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT o.*, d.time_from AS slot_from, d.time_to AS slot_to,\n" .
            "       u.name AS client_name, u.phone, u.has_used_referral_coupon, a.street AS address\n" .
            "FROM orders o\n" .
            "JOIN users u ON u.id = o.user_id\n" .
            "JOIN addresses a ON a.id = o.address_id\n" .
            "LEFT JOIN delivery_slots d ON d.id = o.slot_id\n" .
            "WHERE o.id = ?"
        );
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $msg = urlencode("Заказ {$id} удалён");
            header('Location: ' . $this->basePath() . "?msg={$msg}");
            exit;
        }

        $stmt = $this->pdo->prepare(
            "SELECT oi.product_id, oi.quantity, oi.boxes, oi.unit_price, t.name AS product_name, p.unit, p.variety, p.box_size, p.box_unit\n" .
            "FROM order_items oi\n" .
            "JOIN products p ON p.id = oi.product_id\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "WHERE oi.order_id = ?"
        );
        $stmt->execute([$id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare(
            "SELECT pt.*\n" .
            "FROM points_transactions pt\n" .
            "WHERE pt.order_id = ?\n" .
            "ORDER BY pt.created_at DESC"
        );
        $stmt->execute([$id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $addrStmt = $this->pdo->prepare(
            "SELECT id, street FROM addresses WHERE user_id = ? ORDER BY is_primary DESC, created_at ASC"
        );
        $addrStmt->execute([$order['user_id']]);
        $addresses = $addrStmt->fetchAll(PDO::FETCH_ASSOC);

        $slotsStmt = $this->pdo->query("SELECT id, time_from, time_to FROM delivery_slots ORDER BY time_from");
        $slots = $slotsStmt->fetchAll(PDO::FETCH_ASSOC);

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
        $prodStmt = $this->pdo->query(
            "SELECT p.id, t.name AS product, p.variety\n" .
            "FROM products p\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "WHERE p.is_active = 1\n" .
            "ORDER BY t.name"
        );
        $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('orders/show', [
            'pageTitle'    => "Заказ #{$id}",
            'order'        => $order,
            'items'        => $items,
            'transactions' => $transactions,
            'coupon'       => $couponInfo,
            'pointsFromBalance' => $pointsFromBalance,
            'addresses'    => $addresses,
            'slots'        => $slots,
            'products'     => $products,
        ]);
    }

    // Форма создания заказа вручную (админ)
    public function create(): void
    {
        $stmt = $this->pdo->query(
            "SELECT p.id, t.name AS product, p.variety, p.price, p.image_path, p.box_size, p.box_unit
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             WHERE p.is_active = 1
             ORDER BY t.name"
        );
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $slotsStmt = $this->pdo->query("SELECT id, time_from, time_to FROM delivery_slots ORDER BY time_from");
        $slots = $slotsStmt->fetchAll(PDO::FETCH_ASSOC);

        $debugData = $_SESSION['debug_order_data'] ?? [];
        unset($_SESSION['debug_order_data']);

        $today = date('Y-m-d');

        viewAdmin('orders/create', [
            'pageTitle' => 'Создать заказ',
            'products'  => $products,
            'slots'     => $slots,
            'debugData' => $debugData,
            'today'     => $today,
        ]);
    }

    // Сохранить заказ (POST, админ)
    public function storeManual(): void
    {
        $userId = (int)($_POST['user_id'] ?? 0);
        $isNew  = $userId === 0;
        $hasUsedReferral = 0;
        $isPickup = false;

        if ($isNew) {
            $name  = trim($_POST['new_name'] ?? '');
            if ($name === '') {
                $name = 'Клиент';
            }
            $phone = $this->normalizePhone($_POST['new_phone'] ?? '');
            $address = trim($_POST['new_address'] ?? '');
            $isPickup = $address === '';
            if ($isPickup) {
                $address = 'Самовывоз: 9 мая, 73';
            }
            $pin   = trim($_POST['new_pin'] ?? '');
            if (!preg_match('/^7\d{10}$/', $phone) || !preg_match('/^\d{4}$/', $pin)) {
                $_SESSION['debug_order_data'] = [
                    'name'    => $name,
                    'phone'   => $phone,
                    'address' => $address,
                    'pin'     => $pin,
                    'raw'     => $_POST,
                ];
                header('Location: ' . $this->basePath() . '/create?error=' . urlencode('invalid user'));
                exit;
            }

            // Check for duplicate phone number to avoid unique constraint errors
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE phone = ?');
            $stmt->execute([$phone]);
            if ($stmt->fetchColumn()) {
                $_SESSION['debug_order_data'] = [
                    'name'    => $name,
                    'phone'   => $phone,
                    'address' => $address,
                    'pin'     => $pin,
                    'raw'     => $_POST,
                ];
                header('Location: ' . $this->basePath() . '/create?error=' . urlencode('phone_exists'));
                exit;
            }

            $refCode = ReferralHelper::generateUniqueCode($this->pdo, 8);
            $managerId = $_SESSION['user_id'] ?? null;
            $pinHash = password_hash($pin, PASSWORD_DEFAULT);
            $hasUsedReferral = isset($_POST['has_used_referral_coupon']) && $_POST['has_used_referral_coupon'] === '1' ? 1 : 0;

            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                "INSERT INTO users (role, name, phone, password_hash, referral_code, referred_by, has_used_referral_coupon, points_balance, created_at) VALUES ('client', ?, ?, ?, ?, ?, ?, 0, NOW())"
            );
            $stmt->execute([$name, $phone, $pinHash, $refCode, $managerId, $hasUsedReferral]);
            $userId = (int)$this->pdo->lastInsertId();

            if ($address !== '') {
                $stmtA = $this->pdo->prepare(
                    "INSERT INTO addresses (user_id, street, recipient_name, recipient_phone, is_primary, created_at) VALUES (?, ?, ?, ?, 1, NOW())"
                );
                $stmtA->execute([$userId, $address, $name, $phone]);
                $addressId = (int)$this->pdo->lastInsertId();
            } else {
                $addressId = null;
            }

            if ($managerId) {
                $this->pdo->prepare(
                    "INSERT IGNORE INTO referrals (referrer_id, referred_id, created_at) VALUES (?, ?, NOW())"
                )->execute([$managerId, $userId]);
            }
            $this->pdo->commit();
            $referralDiscount = $hasUsedReferral === 1;
        } else {
            $addrInput = $_POST['address_id'] ?? null;
            $isPickup = ($addrInput === 'pickup');
            if ($isPickup) {
                $addressId = $this->ensurePickupAddress($userId);
            } elseif ($addrInput === 'new') {
                $newStreet = trim($_POST['address_new'] ?? '');
                if ($newStreet === '') {
                    header('Location: ' . $this->basePath() . '/create?error=' . urlencode('address'));
                    exit;
                }
                $stmtUser = $this->pdo->prepare('SELECT name, phone FROM users WHERE id = ?');
                $stmtUser->execute([$userId]);
                $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: ['name' => '', 'phone' => ''];
                $stmtA = $this->pdo->prepare(
                    "INSERT INTO addresses (user_id, street, recipient_name, recipient_phone, is_primary, created_at) VALUES (?, ?, ?, ?, 0, NOW())"
                );
                $stmtA->execute([$userId, $newStreet, $userRow['name'] ?? '', $userRow['phone'] ?? '']);
                $addressId = (int)$this->pdo->lastInsertId();
            } elseif ($addrInput !== null && $addrInput !== '') {
                $addressId = is_numeric($addrInput) ? (int)$addrInput : null;
            } else {
                $addressId = null;
            }
            $referralDiscount = false;
        }

        if ($userId <= 0) {
            header('Location: ' . $this->basePath() . '/create?error=' . urlencode('user'));
            exit;
        }

        $slotId = $_POST['slot_id'] ?? null;
        $deliveryDate = $_POST['delivery_date'] ?? null;
        $couponCode = trim($_POST['coupon_code'] ?? '');

        $items = $_POST['items'] ?? [];
        if (!$items) {
            header('Location: ' . $this->basePath() . '/create?error=' . urlencode('empty'));
            exit;
        }

        $total = 0;
        $stmtProd = $this->pdo->prepare("SELECT price, box_size FROM products WHERE id = ?");
        $itemsPrepared = [];
        foreach ($items as $pid => $boxes) {
            $boxes = (float)$boxes;
            if ($boxes <= 0) continue;
            $stmtProd->execute([$pid]);
            $row = $stmtProd->fetch(PDO::FETCH_ASSOC);
            if (!$row) continue;
            $pricePerKg = (float)$row['price'];
            $boxSize = (float)($row['box_size'] ?: 1);
            $qtyKg = $boxes * $boxSize;
            $total += $qtyKg * $pricePerKg;
            $itemsPrepared[$pid] = [
                'qtyKg'  => $qtyKg,
                'boxes'  => $boxes,
                'price'  => $pricePerKg,
            ];
        }

        if ($referralDiscount) {
            $total = (int)floor($total * 0.9);
            if ($hasUsedReferral === 0) {
                $this->pdo->prepare("UPDATE users SET has_used_referral_coupon = 1 WHERE id = ?")->execute([$userId]);
            }
        }

        $pointsUsed = 0;
        if (!$isNew) {
            $stmtPoints = $this->pdo->prepare("SELECT points_balance FROM users WHERE id = ?");
            $stmtPoints->execute([$userId]);
            $balance = (int)$stmtPoints->fetchColumn();
            $pointsUsed = min($balance, (int)($_POST['points'] ?? 0), (int)$total);
            $total -= $pointsUsed;
        }

          $shippingFee = $isPickup ? 0 : 300;
          $total += $shippingFee;

        $stmt = $this->pdo->prepare(
            "INSERT INTO orders (user_id, address_id, slot_id, status, total_amount, discount_applied, points_used, points_accrued, coupon_code, delivery_date, created_at) VALUES (?, ?, ?, 'new', ?, 0, ?, 0, ?, ?, NOW())"
        );
        $stmt->execute([$userId, $addressId, $slotId, $total, $pointsUsed, $couponCode, $deliveryDate]);
        $orderId = (int)$this->pdo->lastInsertId();

        $stmtItem = $this->pdo->prepare(
            "INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price) VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($itemsPrepared as $pid => $data) {
            $stmtItem->execute([
                $orderId,
                $pid,
                $data['qtyKg'],
                $data['boxes'],
                $data['price'],
            ]);
        }

        if ($pointsUsed > 0) {
            $this->pdo->prepare("UPDATE users SET points_balance = points_balance - ? WHERE id = ?")->execute([$pointsUsed, $userId]);
            $desc = "Списание {$pointsUsed} клубничек за заказ #{$orderId}";
            $this->pdo->prepare(
                "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES (?, ?, ?, 'usage', ?, NOW())"
            )->execute([$userId, $orderId, -$pointsUsed, $desc]);
        }

        header('Location: ' . $this->basePath());
        exit;
    }

    // Назначить курьера (POST, админ)
    public function assign(): void
    {
        $orderId   = (int)($_POST['order_id'] ?? 0);
        $courierId = (int)($_POST['courier_id'] ?? 0);
        if ($orderId && $courierId) {
            $stmt = $this->pdo->prepare(
                "UPDATE orders SET assigned_to = ?, status = 'assigned' WHERE id = ?"
            );
            $stmt->execute([$courierId, $orderId]);
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    // Обновление адреса и времени доставки
    public function updateDelivery(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId) {
            $addressRaw = $_POST['address_id'] ?? null;
            if ($addressRaw === 'pickup') {
                $addressId = null;
            } elseif ($addressRaw !== null && $addressRaw !== '') {
                $addressId = is_numeric($addressRaw) ? (int)$addressRaw : null;
            } else {
                $addressId = null;
            }

            $deliveryDate = $_POST['delivery_date'] ?? null;
            $slotId = $_POST['slot_id'] ?? null;
            if ($slotId === '') {
                $slotId = null;
            }

            $stmt = $this->pdo->prepare(
                "UPDATE orders SET address_id = ?, delivery_date = ?, slot_id = ? WHERE id = ?"
            );
            $stmt->execute([$addressId, $deliveryDate, $slotId, $orderId]);
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    // Обновить статус (POST, админ)
    public function updateStatus(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $status  = $_POST['status'] ?? '';
        if ($orderId && in_array($status, ['new','processing','assigned','delivered','cancelled'], true)) {
            // Получаем текущий статус и данные заказа
            $stmt = $this->pdo->prepare(
                "SELECT status, user_id, total_amount, points_accrued, manager_points_accrued, points_used FROM orders WHERE id = ?"
            );
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($order) {
                // Если переводим в delivered впервые — начисляем бонусы
                if ($status === 'delivered' && $order['status'] !== 'delivered') {
                    $userId = (int)$order['user_id'];
                    $sum    = (int)$order['total_amount'];

                    // 5% личный бонус
                    $personal = (int) floor($sum * 0.05);
                    if ($personal > 0) {
                        $this->pdo->prepare(
                            "UPDATE users SET points_balance = points_balance + ? WHERE id = ?"
                        )->execute([$personal, $userId]);

                        $desc = "Начисление {$personal} за заказ №{$orderId}";
                        $this->pdo->prepare(
                            "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES (?, ?, ?, 'accrual', ?, NOW())"
                        )->execute([$userId, $orderId, $personal, $desc]);
                    }

                    // Бонусы пригласившему и менеджеру (если есть и ещё не начислено)
                    if ((int)$order['points_accrued'] === 0) {
                        $refStmt = $this->pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
                        $refStmt->execute([$userId]);
                        $refId = (int)($refStmt->fetchColumn() ?: 0);
                        if ($refId) {
                            // Получаем роль пригласившего и его пригласившего (менеджера)
                            $infoStmt = $this->pdo->prepare("SELECT role, referred_by FROM users WHERE id = ?");
                            $infoStmt->execute([$refId]);
                            $refInfo = $infoStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                            $refRole = $refInfo['role'] ?? '';
                            $mgrId   = (int)($refInfo['referred_by'] ?? 0);

                            $refPercent   = ($refRole === 'partner') ? 0.10 : 0.03;
                            $refBonus     = (int) floor($sum * $refPercent);
                            $managerBonus = 0;

                            if ($refBonus > 0) {
                                $this->pdo->prepare(
                                    "UPDATE users SET points_balance = points_balance + ? WHERE id = ?"
                                )->execute([$refBonus, $refId]);

                                $refDesc = "Бонус за заказ №{$orderId}";
                                $this->pdo->prepare(
                                    "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES (?, ?, ?, 'accrual', ?, NOW())"
                                )->execute([$refId, $orderId, $refBonus, $refDesc]);

                                // Менеджеру второго уровня (если есть)
                                if ($mgrId) {
                                    $managerBonus = (int) floor($sum * 0.03);
                                    if ($managerBonus > 0) {
                                        $this->pdo->prepare(
                                            "UPDATE users SET points_balance = points_balance + ? WHERE id = ?"
                                        )->execute([$managerBonus, $mgrId]);

                                        $mgrDesc = "Менеджерский бонус за заказ №{$orderId}";
                                        $this->pdo->prepare(
                                            "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES (?, ?, ?, 'accrual', ?, NOW())"
                                        )->execute([$mgrId, $orderId, $managerBonus, $mgrDesc]);
                                    }
                                }

                                $this->pdo->prepare(
                                    "UPDATE orders SET points_accrued = ?, manager_points_accrued = ? WHERE id = ?"
                                )->execute([$refBonus, $managerBonus, $orderId]);
                            }
                        }
                    }

                    // Начисляем селлерам выплаты по заказу
                    $payoutsStmt = $this->pdo->prepare("SELECT seller_id, payout_amount FROM seller_payouts WHERE order_id = ?");
                    $payoutsStmt->execute([$orderId]);
                    $payoutRows = $payoutsStmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($payoutRows) {
                        $uStmt = $this->pdo->prepare("UPDATE users SET rub_balance = rub_balance + ? WHERE id = ?");
                        foreach ($payoutRows as $pr) {
                            $uStmt->execute([(float)$pr['payout_amount'], (int)$pr['seller_id']]);
                        }
                        $this->pdo->prepare("UPDATE seller_payouts SET status = 'accrued' WHERE order_id = ?")
                             ->execute([$orderId]);
                    }
                }

                $stmt = $this->pdo->prepare(
                    "UPDATE orders SET status = ? WHERE id = ?"
                );
                $stmt->execute([$status, $orderId]);

                if ($status === 'cancelled') {
                    $cnt = $this->pdo->prepare(
                        "SELECT COUNT(*) FROM orders WHERE user_id = ? AND id <> ? AND status <> 'cancelled'"
                    );
                    $cnt->execute([(int)$order['user_id'], $orderId]);
                    if ((int)$cnt->fetchColumn() === 0) {
                        $this->pdo->prepare("UPDATE users SET has_used_referral_coupon = 0 WHERE id = ?")
                                 ->execute([(int)$order['user_id']]);
                    }

                    if ($order['status'] !== 'cancelled') {
                        $pointsBack = (int)$order['points_used'];
                        if ($pointsBack > 0) {
                            $this->pdo->prepare(
                                "UPDATE users SET points_balance = points_balance + ? WHERE id = ?"
                            )->execute([$pointsBack, (int)$order['user_id']]);

                            $desc = "Возврат {$pointsBack} за отмену заказа №{$orderId}";
                            $this->pdo->prepare(
                                "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES (?, ?, ?, 'accrual', ?, NOW())"
                            )->execute([(int)$order['user_id'], $orderId, $pointsBack, $desc]);
                        }
                    }
                }
            }
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    // Форма подтверждения заказа (клиент)
    public function checkoutForm(): void
    {
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            exit;
        }

        $stmt = $this->pdo->prepare(
            "SELECT ci.product_id, t.name AS product, p.variety, ci.quantity, ci.unit_price, p.delivery_date\n" .
            "FROM cart_items ci\n" .
            "JOIN products p ON p.id = ci.product_id\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "WHERE ci.user_id = ?"
        );
        $stmt->execute([$user['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $groups = [];
        $subtotal = 0.0;
        foreach ($items as $it) {
            $key = $it['delivery_date'] ?: 'on_demand';
            $groups[$key][] = $it;
            $subtotal += $it['quantity'] * $it['unit_price'];
        }

        $currentPoints = $user['points_balance'] ?? 0;
        $maxPointsUse  = (int)$subtotal;

        include __DIR__ . '/../../src/Views/client/checkout.php';
    }

    // Обработка и сохранение заказа (POST /checkout)
    public function store(): void
    {
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            exit;
        }

        $stmt = $this->pdo->prepare(
            "SELECT ci.product_id, ci.quantity, ci.unit_price, p.box_size\n" .
            "FROM cart_items ci\n" .
            "JOIN products p ON p.id = ci.product_id\n" .
            "WHERE ci.user_id = ?"
        );
        $stmt->execute([$user['id']]);
        $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($cartItems)) {
            header('Location: /cart?error=' . urlencode('Корзина пуста'));
            exit;
        }

        $totalAmount = 0;
        foreach ($cartItems as &$ci) {
            $ci['kg_qty'] = $ci['quantity'] * (float)$ci['box_size'];
            $ci['kg_price'] = ((float)$ci['box_size'] > 0)
                ? $ci['unit_price'] / (float)$ci['box_size']
                : $ci['unit_price'];
            $totalAmount += $ci['kg_qty'] * $ci['kg_price'];
        }
        unset($ci);

        try {
            $this->pdo->beginTransaction();

            // Логика скидок и баллов
            $discount = 0;
            if ($user['referred_by'] !== null && $user['has_used_referral_coupon'] == 0) {
                $discount = (int)floor($totalAmount * 0.10);
                $this->pdo->prepare("UPDATE users SET has_used_referral_coupon = 1 WHERE id = ?")->execute([$user['id']]);
            }

            $pointsUsed = 0;
            $maxPointsUse = (int)($totalAmount - $discount);
            if (!empty($_POST['use_points']) && $user['points_balance'] > 0) {
                $requested = intval($_POST['points_to_use'] ?? 0);
                $pointsUsed = min($requested, $maxPointsUse, $user['points_balance']);
                if ($pointsUsed > 0) {
                    $this->pdo->prepare("UPDATE users SET points_balance = points_balance - ? WHERE id = ?")->execute([$pointsUsed, $user['id']]);
                    $this->pdo->prepare(
                        "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at)
                         VALUES (?, NULL, ?, 'usage', ?, NOW())"
                    )->execute([$user['id'], -$pointsUsed, "Списание {$pointsUsed} за заказ"]);
                }
            }

            // Сохраняем заказ
            $stmtOrder = $this->pdo->prepare(
                "INSERT INTO orders
                (user_id, address_id, slot_id, status, total_amount, discount_applied, points_used, points_accrued, delivery_date, created_at)
                 VALUES (?, ?, ?, 'new', ?, ?, ?, 0, ?, NOW())"
            );
            $stmtOrder->execute([
                $user['id'],
                $_POST['address_id'],
                $_POST['slot_id'] ?? null,
                $totalAmount,
                $discount,
                $pointsUsed,
                PLACEHOLDER_DATE
            ]);
            $orderId = (int)$this->pdo->lastInsertId();

            $stmtItem = $this->pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price) VALUES (?, ?, ?, ?, ?)"
            );
            foreach ($cartItems as $ci) {
                $stmtItem->execute([
                    $orderId,
                    $ci['product_id'],
                    $ci['kg_qty'],
                    $ci['quantity'],
                    $ci['kg_price']
                ]);
            }

            // Начисление бонусов по заказу
            $personalBonus = (int)floor(($totalAmount - $discount - $pointsUsed) * 0.05);
            if ($personalBonus > 0) {
                $this->pdo->prepare("UPDATE orders SET points_accrued = ? WHERE id = ?")->execute([$personalBonus, $orderId]);
                $this->pdo->prepare("UPDATE users SET points_balance = points_balance + ? WHERE id = ?")->execute([$personalBonus, $user['id']]);
                $this->pdo->prepare(
                    "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at)
                     VALUES (?, ?, ?, 'accrual', ?, NOW())"
                )->execute([$user['id'], $orderId, $personalBonus, "Начислено {$personalBonus} за заказ"]);
            }

            $this->pdo->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$user['id']]);
            $this->pdo->commit();

            // Уведомляем админов в Telegram
            $this->notifyAdmins($orderId);

            header('Location: /orders/thankyou');
            exit;

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            header('Location: /checkout?error=' . urlencode('Ошибка при оформлении заказа'));
            exit;
        }
    }

    // Уведомление администраторам
    public function notifyAdmins(int $orderId): void
    {
        $cfg    = require __DIR__ . '/../../config/telegram.php';
        $token  = $cfg['bot_token'];
        $chatId = $cfg['admin_chat_id'];

        // Получаем основные данные заказа и пользователя
        $stmt = $this->pdo->prepare(
            "SELECT o.created_at, o.total_amount, o.delivery_date,
" .
            "       d.time_from AS slot_from, d.time_to AS slot_to, u.name, u.phone
" .
            "FROM orders o
" .
            "JOIN users u ON u.id = o.user_id
" .
            "LEFT JOIN delivery_slots d ON d.id = o.slot_id
" .
            "WHERE o.id = ?"
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$order) {
            return;
        }

        // Получаем первую позицию заказа (если их несколько, берём первую)
        $stmtItems = $this->pdo->prepare(
            "SELECT t.name AS product, p.variety, p.unit, oi.quantity, oi.boxes, oi.unit_price
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             JOIN product_types t ON t.id = p.product_type_id
             WHERE oi.order_id = ?
             LIMIT 1"
        );
        $stmtItems->execute([$orderId]);
        $item = $stmtItems->fetch(\PDO::FETCH_ASSOC);

        $deliveryDate = $order['delivery_date'] ?? null;
        $deliverySlot = format_time_range($order['slot_from'] ?? null, $order['slot_to'] ?? null);
        $placeholder = defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15';
        if ($deliveryDate && $deliveryDate !== $placeholder) {
            $deliveryText = date('d.m.Y', strtotime($deliveryDate));
        } else {
            $deliveryText = 'Ближайшая возможная дата';
        }
        if ($deliverySlot !== '') {
            $deliveryText .= ' ' . $deliverySlot;
        }

        $line1 = $order['phone'] . ', ' . $order['name'];

        if ($item) {
            $productInfo = trim($item['product'] . ' ' . $item['variety']);
            if ($item['unit']) {
                $productInfo .= ' ' . $item['unit'];
            }
            $line2 = sprintf(
                '%s, %s, %s, %.0f',
                $deliveryText,
                $productInfo,
                $item['quantity'],
                $order['total_amount']
            );
        } else {
            $line2 = sprintf('%s, сумма %.0f', $deliveryText, $order['total_amount']);
        }

        $line3 = 'https://berrygo.ru/admin/orders/' . $orderId;

        $text = $line1 . "\n" . $line2 . "\n" . $line3;

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $payloadData = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ];
        if (!empty($cfg['admin_topic_id'])) {
            $payloadData['message_thread_id'] = (int)$cfg['admin_topic_id'];
        }

        $payload = json_encode($payloadData, JSON_UNESCAPED_UNICODE);

        // Инициализируем cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=UTF-8'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Логируем результат (в файл или через ваш логгер)
        $logEntry = date('Y-m-d H:i:s')
            . " | notifyAdmins | order={$orderId} | http_code={$httpCode}";
        if ($errno) {
            $logEntry .= " | curl_error={$error}";
        }
        $logEntry .= " | response=" . ($response === false ? 'false' : $response) . "\n";
        file_put_contents(__DIR__ . '/../../log/telegram_notify.log', $logEntry, FILE_APPEND);
        // если используете PSR-3 логгер:
        // $this->logger?->error('notifyAdmins', ['orderId'=>$orderId,'http'=>$httpCode,'curlErr'=>$error,'resp'=>$response]);
    }

    // Удаление заказа (POST, админ)
    public function delete(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId) {
            $stmt = $this->pdo->prepare("SELECT user_id, status, points_used FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $userId = (int)($order['user_id'] ?? 0);

            $this->pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
            $this->pdo->prepare("DELETE FROM points_transactions WHERE order_id = ?")->execute([$orderId]);


            if ($userId && ($order['status'] ?? '') !== 'cancelled') {
                $pointsBack = (int)($order['points_used'] ?? 0);
                if ($pointsBack > 0) {
                    $this->pdo->prepare(
                        "UPDATE users SET points_balance = points_balance + ? WHERE id = ?"
                    )->execute([$pointsBack, $userId]);

                    $desc = "Возврат {$pointsBack} за удаление заказа №{$orderId}";
                    $this->pdo->prepare(
                        "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES (?, ?, ?, 'accrual', ?, NOW())"
                    )->execute([$userId, $orderId, $pointsBack, $desc]);
                }
            }

            $this->pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$orderId]);

            if ($userId) {
                $cnt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status <> 'cancelled'");
                $cnt->execute([$userId]);
                if ((int)$cnt->fetchColumn() === 0) {
                    $this->pdo->prepare("UPDATE users SET has_used_referral_coupon = 0 WHERE id = ?")
                             ->execute([$userId]);
                }
            }
        }
        header('Location: ' . $this->basePath());
        exit;
    }

    // Обновление количества товара в заказе (POST, админ)
    public function updateItem(): void
    {
        $orderId   = (int)($_POST['order_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty       = (float)($_POST['quantity'] ?? 0);
        $price     = (float)($_POST['unit_price'] ?? 0);
        if ($orderId && $productId && $qty > 0) {
            $stmtBox = $this->pdo->prepare("SELECT box_size, price FROM products WHERE id = ?");
            $stmtBox->execute([$productId]);
            $prod = $stmtBox->fetch(PDO::FETCH_ASSOC) ?: [];
            $boxSize = (float)($prod['box_size'] ?? 1);
            if ($price <= 0) {
                $price = (float)($prod['price'] ?? 0);
            }
            $boxes = $boxSize > 0 ? $qty / $boxSize : $qty;
            $this->pdo->prepare(
                "UPDATE order_items SET quantity = ?, boxes = ?, unit_price = ? WHERE order_id = ? AND product_id = ?"
            )->execute([$qty, $boxes, $price, $orderId, $productId]);

            $this->recalculateTotals($orderId);
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    public function addItem(): void
    {
        $orderId   = (int)($_POST['order_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty       = (float)($_POST['quantity'] ?? 0);
        $price     = (float)($_POST['unit_price'] ?? 0);
        if ($orderId && $productId && $qty > 0) {
            $stmt = $this->pdo->prepare("SELECT price, box_size FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $boxSize = (float)($prod['box_size'] ?? 1);
            if ($price <= 0) {
                $price = (float)($prod['price'] ?? 0);
            }
            $boxes = $boxSize > 0 ? $qty / $boxSize : $qty;

            $check = $this->pdo->prepare("SELECT 1 FROM order_items WHERE order_id = ? AND product_id = ?");
            $check->execute([$orderId, $productId]);
            if ($check->fetch()) {
                $this->pdo->prepare(
                    "UPDATE order_items SET quantity = ?, boxes = ?, unit_price = ? WHERE order_id = ? AND product_id = ?"
                )->execute([$qty, $boxes, $price, $orderId, $productId]);
            } else {
                $this->pdo->prepare(
                    "INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price) VALUES (?, ?, ?, ?, ?)"
                )->execute([$orderId, $productId, $qty, $boxes, $price]);
            }

            $this->recalculateTotals($orderId);
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    public function deleteItem(): void
    {
        $orderId   = (int)($_POST['order_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        if ($orderId && $productId) {
            $this->pdo->prepare(
                "DELETE FROM order_items WHERE order_id = ? AND product_id = ?"
            )->execute([$orderId, $productId]);

            $this->recalculateTotals($orderId);
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    // Обновление скидки первого заказа
    public function updateReferral(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $userId  = (int)($_POST['user_id'] ?? 0);
        $value   = isset($_POST['has_used_referral_coupon']) && $_POST['has_used_referral_coupon'] === '1' ? 1 : 0;
        if ($orderId && $userId) {
            $this->pdo->prepare(
                "UPDATE users SET has_used_referral_coupon = ? WHERE id = ?"
            )->execute([$value, $userId]);
            $this->recalculateTotals($orderId);
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    public function updateComment(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($orderId) {
            $this->pdo->prepare("UPDATE orders SET comment = ? WHERE id = ?")
                      ->execute([$comment, $orderId]);
        }
        header('Location: ' . $this->basePath() . '/' . $orderId);
        exit;
    }

    private function recalculateTotals(int $orderId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT SUM(quantity * unit_price) FROM order_items WHERE order_id = ?"
        );
        $stmt->execute([$orderId]);
        $rawTotal = (float)$stmt->fetchColumn();

        $oStmt = $this->pdo->prepare(
            "SELECT user_id, points_used, coupon_code, address_id FROM orders WHERE id = ?"
        );
        $oStmt->execute([$orderId]);
        $oRow = $oStmt->fetch(PDO::FETCH_ASSOC);
        $pointsUsed = (int)($oRow['points_used'] ?? 0);
        $userId    = (int)($oRow['user_id'] ?? 0);
        $hasUsedReferral = 0;
        if ($userId) {
            $uStmt = $this->pdo->prepare("SELECT has_used_referral_coupon FROM users WHERE id = ?");
            $uStmt->execute([$userId]);
            $hasUsedReferral = (int)$uStmt->fetchColumn();
        }

        $subAfterPickup = $rawTotal;

        $discountApplied = 0;
        $couponCode = $oRow['coupon_code'] ?? '';
        if ($couponCode !== '') {
            $cStmt = $this->pdo->prepare(
                "SELECT type, discount, points FROM coupons WHERE code = ?"
            );
            $cStmt->execute([$couponCode]);
            $coupon = $cStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($coupon) {
                if ($coupon['type'] === 'discount') {
                    $percent = (float)$coupon['discount'];
                    $discountApplied = (int) floor(($subAfterPickup - $pointsUsed) * ($percent / 100));
                }
            } else {
                // реферальный код даёт скидку 10% только если отметка использована
                if ($hasUsedReferral === 1) {
                    $discountApplied = (int) floor(($subAfterPickup - $pointsUsed) * 0.10);
                }
            }
        }

        $finalTotal = $subAfterPickup - $pointsUsed - $discountApplied;

        $this->pdo->prepare(
            "UPDATE orders SET total_amount = ?, discount_applied = ? WHERE id = ?"
        )->execute([$finalTotal, $discountApplied, $orderId]);
    }
}
