<?php
namespace App\Services;

use PDO;
use Throwable;

class OrderStatusHistoryService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function record(int $orderId, ?string $fromStatus, string $toStatus, ?int $changedByUserId, ?string $changedByRole, ?string $comment = null): void
    {
        if ($orderId <= 0 || $toStatus === '' || $fromStatus === $toStatus) {
            return;
        }

        try {
            if (!$this->tableExists('order_status_history')) {
                return;
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by_user_id, changed_by_role, comment, created_at) VALUES (?, ?, ?, ?, ?, ?, ' . $this->currentTimestampExpression() . ')'
            );
            $stmt->execute([$orderId, $fromStatus, $toStatus, $changedByUserId, $changedByRole, $comment]);
        } catch (Throwable $e) {
            error_log('order status history failed: ' . $e->getMessage());
        }
    }

    private function tableExists(string $table): bool
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function currentTimestampExpression(): string
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        return $driver === 'sqlite' ? "datetime('now')" : 'NOW()';
    }
}
