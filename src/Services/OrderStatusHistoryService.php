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

    public function record(
        int $orderId,
        ?string $fromStatus,
        string $toStatus,
        ?int $changedByUserId,
        ?string $changedByRole,
        ?string $comment = null,
        bool $suppressIntegrationSync = false
    ): void {
        if ($orderId <= 0 || $toStatus === '' || $fromStatus === $toStatus) {
            return;
        }

        try {
            if ($this->tableExists('order_status_history')) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by_user_id, changed_by_role, comment, created_at) VALUES (?, ?, ?, ?, ?, ?, ' . $this->currentTimestampExpression() . ')'
                );
                $stmt->execute([$orderId, $fromStatus, $toStatus, $changedByUserId, $changedByRole, $comment]);
            }
        } catch (Throwable $e) {
            error_log('order status history failed: ' . $e->getMessage());
        }

        if ($suppressIntegrationSync) {
            return;
        }

        try {
            $actorName = null;
            if ($changedByUserId !== null && $changedByUserId > 0 && $this->tableExists('users')) {
                $stmt = $this->pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$changedByUserId]);
                $name = $stmt->fetchColumn();
                $actorName = $name !== false ? (string)$name : null;
            }
            (new Florix24IntegrationService($this->pdo))->enqueueStatusChange(
                $orderId,
                $fromStatus,
                $toStatus,
                $changedByUserId,
                $actorName,
                $changedByRole
            );
        } catch (Throwable $e) {
            // Status changes in BerryGo must not fail because Florix24 is unavailable or not configured.
            error_log('florix24 status enqueue failed: ' . $e->getMessage());
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
