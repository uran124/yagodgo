<?php
namespace App\Services;

use PDO;
use RuntimeException;

class OrderReturnService
{
    private PDO $pdo;
    private StockService $stockService;

    public function __construct(PDO $pdo, ?StockService $stockService = null)
    {
        $this->pdo = $pdo;
        $this->stockService = $stockService ?? new StockService($pdo);
    }

    public function returnCompletedOrder(int $orderId, ?int $actorUserId = null): void
    {
        $order = $this->loadOrder($orderId);
        if ((string)($order['status'] ?? '') !== 'completed') {
            throw new RuntimeException('Возврат доступен только для выполненного заказа.');
        }

        $this->writeOffSoldItems($orderId, $actorUserId);
        $this->reverseBonusAccruals($orderId);
        $this->refundUsedPoints($orderId, (int)$order['user_id'], (int)($order['points_used'] ?? 0));
        $this->reverseSellerPayouts($orderId);
        $this->resetOrderAccruals($orderId);
        $this->markPaymentRefundPendingIfPaid($orderId);
        $this->resetReferralCouponIfNoSuccessfulOrders((int)$order['user_id'], $orderId);
    }

    /** @return array<string, mixed> */
    private function loadOrder(int $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, user_id, status, points_used FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new RuntimeException('Заказ не найден.');
        }
        return $order;
    }

    private function writeOffSoldItems(int $orderId, ?int $actorUserId): void
    {
        if ($this->hasReturnWriteOff($orderId)) {
            return;
        }

        $itemsStmt = $this->pdo->prepare(
            'SELECT product_id, purchase_batch_id, boxes FROM order_items WHERE order_id = ? AND purchase_batch_id IS NOT NULL'
        );
        $itemsStmt->execute([$orderId]);
        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $boxes = (float)($item['boxes'] ?? 0);
            if ($boxes <= 0) {
                continue;
            }
            $this->stockService->writeOffSoldSale(
                (int)$item['product_id'],
                (int)$item['purchase_batch_id'],
                $boxes,
                $orderId,
                $actorUserId,
                'Полный возврат заказа №' . $orderId
            );
        }
    }

    private function hasReturnWriteOff(int $orderId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE order_id = ? AND movement_type = 'writeoff'");
        $stmt->execute([$orderId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function reverseBonusAccruals(int $orderId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT user_id, COALESCE(SUM(amount), 0) AS amount
             FROM points_transactions
             WHERE order_id = ? AND transaction_type = 'accrual' AND amount > 0
             GROUP BY user_id"
        );
        $stmt->execute([$orderId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) {
            return;
        }

        $update = $this->pdo->prepare('UPDATE users SET points_balance = points_balance - ? WHERE id = ?');
        $insert = $this->pdo->prepare(
            "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at)
             VALUES (?, ?, ?, 'usage', ?, " . $this->nowSql() . ")"
        );
        foreach ($rows as $row) {
            $amount = (int)($row['amount'] ?? 0);
            $userId = (int)($row['user_id'] ?? 0);
            if ($amount <= 0 || $userId <= 0) {
                continue;
            }
            $update->execute([$amount, $userId]);
            $insert->execute([$userId, $orderId, -$amount, 'Сторно бонусов за возврат заказа №' . $orderId]);
        }
    }

    private function refundUsedPoints(int $orderId, int $userId, int $pointsUsed): void
    {
        if ($userId <= 0 || $pointsUsed <= 0) {
            return;
        }
        $this->pdo->prepare('UPDATE users SET points_balance = points_balance + ? WHERE id = ?')
            ->execute([$pointsUsed, $userId]);
        $this->pdo->prepare(
            "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at)
             VALUES (?, ?, ?, 'accrual', ?, " . $this->nowSql() . ")"
        )->execute([$userId, $orderId, $pointsUsed, 'Возврат списанных клубничек за возврат заказа №' . $orderId]);
    }

    private function reverseSellerPayouts(int $orderId): void
    {
        $stmt = $this->pdo->prepare("SELECT id, seller_id, payout_amount, status FROM seller_payouts WHERE order_id = ? AND status <> 'cancelled'");
        $stmt->execute([$orderId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) {
            return;
        }

        $balance = $this->pdo->prepare('UPDATE users SET rub_balance = rub_balance - ? WHERE id = ?');
        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            if (in_array($status, ['accrued', 'paid'], true)) {
                $balance->execute([(float)($row['payout_amount'] ?? 0), (int)$row['seller_id']]);
            }
        }
        $this->pdo->prepare("UPDATE seller_payouts SET status = 'cancelled' WHERE order_id = ? AND status <> 'cancelled'")
            ->execute([$orderId]);
    }

    private function resetOrderAccruals(int $orderId): void
    {
        $this->pdo->prepare('UPDATE orders SET points_accrued = 0, manager_points_accrued = 0 WHERE id = ?')
            ->execute([$orderId]);
    }

    private function markPaymentRefundPendingIfPaid(int $orderId): void
    {
        if (!$this->columnExists('orders', 'payment_status')) {
            return;
        }
        $this->pdo->prepare("UPDATE orders SET payment_status = 'refund_pending' WHERE id = ? AND payment_status = 'paid'")
            ->execute([$orderId]);
    }

    private function resetReferralCouponIfNoSuccessfulOrders(int $userId, int $orderId): void
    {
        if ($userId <= 0) {
            return;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND id <> ? AND status = 'completed'");
        $stmt->execute([$userId, $orderId]);
        if ((int)$stmt->fetchColumn() === 0) {
            $this->pdo->prepare('UPDATE users SET has_used_referral_coupon = 0 WHERE id = ?')->execute([$userId]);
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $stmt = $this->pdo->query('PRAGMA table_info(' . $table . ')');
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (($row['name'] ?? '') === $column) {
                        return true;
                    }
                }
                return false;
            }
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function nowSql(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()';
    }
}
