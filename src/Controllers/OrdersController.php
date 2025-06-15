<?php
namespace App\Controllers;

use PDO;
use App\Helpers\Auth;

class OrdersController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Список заказов (админ)
    public function index(): void
    {
        $stmt = $this->pdo->query(
            "SELECT o.id, u.name AS client_name, o.total_amount, o.status, o.created_at,\n" .
            "       c.name AS courier_name, o.discount_applied, o.points_used, o.points_accrued\n" .
            "FROM orders o\n" .
            "JOIN users u ON u.id = o.user_id\n" .
            "LEFT JOIN users c ON c.id = o.assigned_to\n" .
            "ORDER BY o.created_at DESC"
        );
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('orders/index', [
            'pageTitle' => 'Заказы',
            'orders'    => $orders,
        ]);
    }

    // Детали заказа (админ)
    public function show(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT o.*, u.name AS client_name, u.phone, a.street AS address\n" .
            "FROM orders o\n" .
            "JOIN users u ON u.id = o.user_id\n" .
            "JOIN addresses a ON a.id = o.address_id\n" .
            "WHERE o.id = ?"
        );
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare(
            "SELECT oi.quantity, oi.unit_price, t.name AS product_name, p.unit\n" .
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

        $couponInfo = null;
        $pointsFromBalance = (int)($order['points_used'] ?? 0);
        if (!empty($order['coupon_code'])) {
            $cStmt = $this->pdo->prepare(
                "SELECT code, type, discount, points FROM coupons WHERE code = ?"
            );
            $cStmt->execute([$order['coupon_code']]);
            $couponInfo = $cStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($couponInfo && $couponInfo['type'] === 'points') {
                $pointsFromBalance = max(0, $pointsFromBalance - (int)$couponInfo['points']);
            }
        }

        viewAdmin('orders/show', [
            'pageTitle'    => "Заказ #{$id}",
            'order'        => $order,
            'items'        => $items,
            'transactions' => $transactions,
            'coupon'       => $couponInfo,
            'pointsFromBalance' => $pointsFromBalance,
        ]);
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
        header("Location: /admin/orders/{$orderId}");
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
                "SELECT status, user_id, total_amount, points_accrued FROM orders WHERE id = ?"
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

                    // 3% пригласившему (если есть и ещё не начислено)
                    if ((int)$order['points_accrued'] === 0) {
                        $refStmt = $this->pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
                        $refStmt->execute([$userId]);
                        $refId = (int)($refStmt->fetchColumn() ?: 0);
                        if ($refId) {
                            $refBonus = (int) floor($sum * 0.03);
                            if ($refBonus > 0) {
                                $this->pdo->prepare(
                                    "UPDATE users SET points_balance = points_balance + ? WHERE id = ?"
                                )->execute([$refBonus, $refId]);

                                $refDesc = "Бонус за заказ №{$orderId}";
                                $this->pdo->prepare(
                                    "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES (?, ?, ?, 'accrual', ?, NOW())"
                                )->execute([$refId, $orderId, $refBonus, $refDesc]);

                                $this->pdo->prepare(
                                    "UPDATE orders SET points_accrued = ? WHERE id = ?"
                                )->execute([$refBonus, $orderId]);
                            }
                        }
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
                }
            }
        }
        header("Location: /admin/orders/{$orderId}");
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
        $maxPointsUse  = (int)floor($subtotal * 0.30);

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

        $stmt = $this->pdo->prepare("SELECT ci.product_id, ci.quantity, ci.unit_price FROM cart_items ci WHERE ci.user_id = ?");
        $stmt->execute([$user['id']]);
        $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($cartItems)) {
            header('Location: /cart?error=Корзина+пуста');
            exit;
        }

        $totalAmount = 0;
        foreach ($cartItems as $ci) {
            $totalAmount += $ci['quantity'] * $ci['unit_price'];
        }

        try {
            $this->pdo->beginTransaction();

            // Логика скидок и баллов
            $discount = 0;
            if ($user['referred_by'] !== null && $user['has_used_referral_coupon'] == 0) {
                $discount = (int)floor($totalAmount * 0.10);
                $this->pdo->prepare("UPDATE users SET has_used_referral_coupon = 1 WHERE id = ?")->execute([$user['id']]);
            }

            $pointsUsed = 0;
            $maxPointsUse = (int)floor(($totalAmount - $discount) * 0.30);
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
                (user_id, address_id, slot_id, status, total_amount, discount_applied, points_used, points_accrued, delivery_date, delivery_slot, created_at)
                 VALUES (?, ?, ?, 'new', ?, ?, ?, 0, ?, '', NOW())"
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
                "INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)"
            );
            foreach ($cartItems as $ci) {
                $stmtItem->execute([$orderId, $ci['product_id'], $ci['quantity'], $ci['unit_price']]);
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
            header('Location: /checkout?error=Ошибка+при+оформлении+заказа');
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
            "SELECT o.created_at, o.total_amount, o.delivery_date, o.delivery_slot, u.name, u.phone
             FROM orders o
             JOIN users u ON u.id = o.user_id
             WHERE o.id = ?"
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$order) {
            return;
        }

        // Получаем первую позицию заказа (если их несколько, берём первую)
        $stmtItems = $this->pdo->prepare(
            "SELECT t.name AS product, p.variety, p.unit, oi.quantity, oi.unit_price
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             JOIN product_types t ON t.id = p.product_type_id
             WHERE oi.order_id = ?
             LIMIT 1"
        );
        $stmtItems->execute([$orderId]);
        $item = $stmtItems->fetch(\PDO::FETCH_ASSOC);

        $createdAt = date('d.m.Y H:i', strtotime($order['created_at']));

        $deliveryDate = $order['delivery_date'] ?? null;
        $deliverySlot = $order['delivery_slot'] ?? '';
        $placeholder = defined('PLACEHOLDER_DATE') ? PLACEHOLDER_DATE : '2025-05-15';
        if ($deliveryDate && $deliveryDate !== $placeholder) {
            $deliveryText = date('d.m.Y', strtotime($deliveryDate));
            if ($deliverySlot !== '') {
                $deliveryText .= ' ' . $deliverySlot;
            }
        } else {
            $deliveryText = $createdAt;
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
        $payload = json_encode([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ], JSON_UNESCAPED_UNICODE);

        // Инициализируем cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
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
            $stmt = $this->pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $userId = (int)$stmt->fetchColumn();

            $this->pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
            $this->pdo->prepare("DELETE FROM points_transactions WHERE order_id = ?")->execute([$orderId]);
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
        header('Location: /admin/orders');
        exit;
    }
}
