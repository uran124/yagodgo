<?php
namespace App\Controllers;

use PDO;
use App\Models\User;
use App\Models\Order;
use App\Models\PointsTransaction;
use App\Helpers\Auth; // Вспомогательный класс для получения текущего пользователя

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
          "SELECT o.id, u.name AS client_name, o.total_amount, o.status, o.created_at,
                  c.name AS courier_name, o.discount_applied, o.points_used, o.points_accrued
           FROM orders o
           JOIN users u ON u.id = o.user_id
           LEFT JOIN users c ON c.id = o.assigned_to
           ORDER BY o.created_at DESC"
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
        // Основная информация
        $stmt = $this->pdo->prepare(
          "SELECT o.*, u.name AS client_name, u.phone, a.street
           FROM orders o
           JOIN users u ON u.id = o.user_id
           JOIN addresses a ON a.id = o.address_id
           WHERE o.id = ?"
        );
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        // Товары в заказе
        $stmt = $this->pdo->prepare(
          "SELECT oi.quantity, oi.unit_price, p.name, p.unit
           FROM order_items oi
           JOIN products p ON p.id = oi.product_id
           WHERE oi.order_id = ?"
        );
        $stmt->execute([$id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // История баллов для клиента и реферера (если нужно)
        $stmt = $this->pdo->prepare(
            "SELECT pt.*
             FROM points_transactions pt
             WHERE pt.order_id = ?
             ORDER BY pt.created_at DESC"
        );
        $stmt->execute([$id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('orders/show', [
          'pageTitle'    => "Заказ #{$id}",
          'order'        => $order,
          'items'        => $items,
          'transactions' => $transactions,
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
            $stmt = $this->pdo->prepare(
              "UPDATE orders SET status = ? WHERE id = ?"
            );
            $stmt->execute([$status, $orderId]);
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

        // Получаем товары из корзины
        $stmt = $this->pdo->prepare(
            "SELECT ci.product_id, p.name AS product, p.variety, ci.quantity, ci.unit_price, p.delivery_date
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             WHERE ci.user_id = ?"
        );
        $stmt->execute([$user->id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Группировка по дате доставки
        $groups = [];
        $subtotal = 0.0;
        foreach ($items as $it) {
            $key = $it['delivery_date'] ?: 'on_demand';
            $groups[$key][] = $it;
            $subtotal += $it['quantity'] * $it['unit_price'];
        }

        // Текущий баланс баллов и максимум списания (30% от суммы)
        $currentPoints = $user['points_balance'] ?? 0;
        $maxPointsUse  = (int)floor($subtotal * 0.30);

        include 'src/Views/client/checkout.php';
    }

    // Обработка и сохранение заказа (POST /checkout)
    public function store(): void
    {
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            exit;
        }

        // Сначала вытягиваем товары из корзины, рассчитываем сумму
        $stmt = $this->pdo->prepare(
            "SELECT ci.product_id, ci.quantity, ci.unit_price
             FROM cart_items ci
             WHERE ci.user_id = ?"
        );
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

            // 1) Реферальная скидка 10% (первый заказ реферала)
            $discount = 0;
            if ($user['referred_by'] !== null && $user['has_used_referral_coupon'] == 0) {
                $discount = (int)floor($totalAmount * 0.10);
                $totalAfterReferral = $totalAmount - $discount;
                // Блокируем повторную скидку
                $stmt = $this->pdo->prepare(
                    "UPDATE users SET has_used_referral_coupon = 1 WHERE id = ?"
                );
                $stmt->execute([$user['id']]);
            } else {
                $totalAfterReferral = $totalAmount;
            }

            // 2) Списание баллов (если отмечено use_points)
            $pointsUsed = 0;
            $maxPointsUse = (int)floor($totalAfterReferral * 0.30);
            if (!empty($_POST['use_points']) && $user['points_balance'] > 0) {
                $requested = intval($_POST['points_to_use'] ?? 0);
                $pointsUsed = min($requested, $maxPointsUse, $user['points_balance']);
                if ($pointsUsed > 0) {
                    // Списываем с баланса
                    $stmt = $this->pdo->prepare(
                        "UPDATE users SET points_balance = points_balance - ? WHERE id = ?"
                    );
                    $stmt->execute([$pointsUsed, $user['id']]);

                    // Создаём запись о списании
                    $stmt = $this->pdo->prepare(
                        "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at)
                         VALUES (?, NULL, ?, 'usage', ?, NOW())"
                    );
                    $stmt->execute([
                        $user['id'],
                        -$pointsUsed,
                        "Списание {$pointsUsed} клубничек за заказ"
                    ]);
                }
                $totalAfterPoints = $totalAfterReferral - $pointsUsed;
            } else {
                $totalAfterPoints = $totalAfterReferral;
            }

            // 3) Сохраняем заказ
            $stmt = $this->pdo->prepare(
                "INSERT INTO orders (user_id, address_id, slot_id, status, total_amount, discount_applied, points_used, points_accrued, created_at)
                 VALUES (?, ?, ?, 'new', ?, ?, ?, 0, NOW())"
            );
            $stmt->execute([
                $user['id'],
                $_POST['address_id'],
                $_POST['slot_id'] ?? null,
                $totalAmount,
                $discount,
                $pointsUsed
            ]);
            $orderId = (int)$this->pdo->lastInsertId();

            // 4) Сохраняем товары в order_items
            $stmtItem = $this->pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, quantity, unit_price)
                 VALUES (?, ?, ?, ?)"
            );
            foreach ($cartItems as $ci) {
                $stmtItem->execute([
                    $orderId,
                    $ci['product_id'],
                    $ci['quantity'],
                    $ci['unit_price']
                ]);
            }

            // 5) Начисление "личных" 5%
            $personalBonus = (int)floor($totalAfterPoints * 0.05);
            if ($personalBonus > 0) {
                // Обновляем points_accrued в orders
                $stmt = $this->pdo->prepare(
                    "UPDATE orders SET points_accrued = ? WHERE id = ?"
                );
                $stmt->execute([$personalBonus, $orderId]);

                // Добавляем к балансу пользователя
                $stmt = $this->pdo->prepare(
                    "UPDATE users SET points_balance = points_balance + ? WHERE id = ?"
                );
                $stmt->execute([$personalBonus, $user['id']]);

                // Запись транзакции
                $stmt = $this->pdo->prepare(
                    "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at)
                     VALUES (?, ?, ?, 'accrual', ?, NOW())"
                );
                $stmt->execute([
                    $user['id'],
                    $orderId,
                    $personalBonus,
                    "Начисление 5% ({$personalBonus}) за заказ #{$orderId}"
                ]);
            }

            // 6) Начисление "реферальных" 3%
            if ($user['referred_by'] !== null) {
                $referrerId = $user['referred_by'];
                $referralBonus = (int)floor($totalAmount * 0.03);
                if ($referralBonus > 0) {
                    // Добавляем к балансу пригласившего
                    $stmt = $this->pdo->prepare(
                        "UPDATE users SET points_balance = points_balance + ? WHERE id = ?"
                    );
                    $stmt->execute([$referralBonus, $referrerId]);

                    // Запись транзакции
                    $stmt = $this->pdo->prepare(
                        "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at)
                         VALUES (?, ?, ?, 'accrual', ?, NOW())"
                    );
                    $stmt->execute([
                        $referrerId,
                        $orderId,
                        $referralBonus,
                        "Реферальное начисление 3% ({$referralBonus}) за заказ #{$orderId}"
                    ]);
                }
            }

            // 7) Очищаем корзину пользователя
            $stmt = $this->pdo->prepare(
                "DELETE FROM cart_items WHERE user_id = ?"
            );
            $stmt->execute([$user['id']]);

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            header("Location: /checkout?error=Ошибка+при+оформлении+заказа");
            exit;
        }

        header("Location: /orders/thankyou");
        exit;
    }

    // Удаление заказа (POST, админ)
    public function delete(): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId) {
            // Удаляем связанные записи в order_items
            $stmt = $this->pdo->prepare(
                "DELETE FROM order_items WHERE order_id = ?"
            );
            $stmt->execute([$orderId]);

            // Удаляем связанные транзакции баллов
            $stmt = $this->pdo->prepare(
                "DELETE FROM points_transactions WHERE order_id = ?"
            );
            $stmt->execute([$orderId]);

            // Удаляем сам заказ
            $stmt = $this->pdo->prepare(
                "DELETE FROM orders WHERE id = ?"
            );
            $stmt->execute([$orderId]);
        }
        header('Location: /admin/orders');
        exit;
    }
}
