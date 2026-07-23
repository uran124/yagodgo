<?php
namespace App\Services;

use App\Models\Order;
use PDO;
use RuntimeException;
use Throwable;

class OrderStatusApplicationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Applies one of the four integration statuses while preserving BerryGo stock and bonus rules.
     *
     * @return array{changed:bool,previous_status:string,current_status:string,current_main_status:string}
     */
    public function applyFromIntegration(int $orderId, string $targetStatus, ?string $actorName = null, ?string $actorId = null): array
    {
        if (!in_array($targetStatus, ['new', 'confirmed', 'completed', 'cancelled'], true)) {
            throw new RuntimeException('Неподдерживаемый статус Florix24.');
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, user_id, status, total_amount, delivery_fee, points_accrued,
                        manager_points_accrued, points_used, created_by_user_id
                 FROM orders WHERE id = ?" . ($this->driver() === 'mysql' ? ' FOR UPDATE' : '')
            );
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new RuntimeException('Заказ BerryGo не найден.');
            }

            $current = (string)$order['status'];
            $currentMain = $this->mainStatus($current);
            if ($currentMain === $targetStatus) {
                if ($ownsTransaction) {
                    $this->pdo->commit();
                }
                return ['changed' => false, 'previous_status' => $current, 'current_status' => $current, 'current_main_status' => $currentMain];
            }

            if (in_array($currentMain, ['completed', 'cancelled'], true)) {
                throw new OrderStatusConflictException("Терминальный статус BerryGo «{$currentMain}» конфликтует со статусом Florix24 «{$targetStatus}».");
            }
            if ($targetStatus === 'new' && $currentMain !== 'new') {
                throw new OrderStatusConflictException('Автоматический возврат подтверждённого заказа в статус «Новый» запрещён.');
            }

            $stock = new OrderStockOrchestrator($this->pdo, new StockService($this->pdo));
            if ($targetStatus === 'confirmed') {
                if ($current === 'new') {
                    $stock->applyStockForOrderId($orderId);
                }
                $this->updateStatus($orderId, 'confirmed');
            } elseif ($targetStatus === 'completed') {
                if ($current === 'new') {
                    $stock->applyStockForOrderId($orderId);
                }
                $stock->commitReservedStockByOrderId($orderId);
                $this->accrueCompletionBenefits($orderId, $order);
                $this->updateStatus($orderId, 'completed');
            } elseif ($targetStatus === 'cancelled') {
                $stock->rollbackReservationByOrderId($orderId);
                $this->restoreCancelledOrderBenefits($orderId, $order);
                $this->updateStatus($orderId, 'cancelled');
            } else {
                // reserved and new both represent the external system's main status "new".
                $this->updateStatus($orderId, $current === 'reserved' ? 'reserved' : 'new');
            }

            $storedStatus = $targetStatus === 'new' && $current === 'reserved' ? 'reserved' : $targetStatus;
            $comment = 'Статус получен из Florix24';
            if (trim((string)$actorName) !== '') {
                $comment .= ': ' . trim((string)$actorName);
            }
            if (trim((string)$actorId) !== '') {
                $comment .= ' [' . trim((string)$actorId) . ']';
            }
            (new OrderStatusHistoryService($this->pdo))->record(
                $orderId,
                $current,
                $storedStatus,
                null,
                'florix24',
                $comment,
                true
            );

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return [
                'changed' => $storedStatus !== $current,
                'previous_status' => $current,
                'current_status' => $storedStatus,
                'current_main_status' => $this->mainStatus($storedStatus),
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function updateStatus(int $orderId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $orderId]);
    }

    /** @param array<string,mixed> $order */
    private function accrueCompletionBenefits(int $orderId, array $order): void
    {
        $userId = (int)$order['user_id'];
        $sum = max(0, (int)$order['total_amount'] - max(0, (int)($order['delivery_fee'] ?? 0)));

        if ($this->tableExists('points_transactions')) {
            $alreadyPersonal = $this->pdo->prepare(
                "SELECT COUNT(*) FROM points_transactions
                 WHERE order_id = ? AND transaction_type = 'accrual' AND description LIKE 'Начисление % за заказ %'"
            );
            $alreadyPersonal->execute([$orderId]);
            if ((int)$alreadyPersonal->fetchColumn() === 0) {
                $personal = (int)floor($sum * 0.05);
                if ($personal > 0) {
                    $this->pdo->prepare('UPDATE users SET points_balance = points_balance + ? WHERE id = ?')->execute([$personal, $userId]);
                    $this->insertPointsTransaction($userId, $orderId, $personal, "Начисление {$personal} за заказ №{$orderId}");
                }
            }
        }

        if ((int)($order['points_accrued'] ?? 0) === 0 && (int)($order['manager_points_accrued'] ?? 0) === 0) {
            $refBonus = 0;
            $refStmt = $this->pdo->prepare('SELECT referred_by FROM users WHERE id = ?');
            $refStmt->execute([$userId]);
            $refId = (int)($refStmt->fetchColumn() ?: 0);
            // Florix24 stores the sale partner on the order.  It deliberately
            // takes precedence over the customer's permanent referral link.
            $explicitPartner = 0;
            try {
                $partnerStmt = $this->pdo->prepare('SELECT partner_user_id FROM orders WHERE id = ?');
                $partnerStmt->execute([$orderId]);
                $explicitPartner = (int)($partnerStmt->fetchColumn() ?: 0);
            } catch (Throwable) {
                // Older schemas used by maintenance tools do not have the column.
            }
            if ($explicitPartner > 0) {
                $refId = $explicitPartner;
            }
            if ($refId > 0) {
                $infoStmt = $this->pdo->prepare('SELECT role FROM users WHERE id = ?');
                $infoStmt->execute([$refId]);
                $refRole = (string)($infoStmt->fetchColumn() ?: '');
                $isPartner = $refRole === 'partner' || $explicitPartner > 0;
                $isFirstClientOrder = false;
                if ($isPartner) {
                    $count = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'completed' AND id <> ?");
                    $count->execute([$userId, $orderId]);
                    $isFirstClientOrder = (int)$count->fetchColumn() === 0;
                }
                $isSelfPlaced = empty($order['created_by_user_id']) || $explicitPartner > 0;
                if ($explicitPartner > 0) {
                    // The order-level Florix24 partner always follows the
                    // 10% first-order / 3% repeat-order partner rule.
                    $refBonus = Order::calculateReferralBonus($sum, true, $isFirstClientOrder);
                } elseif ($refRole === 'manager') {
                    $refBonus = $isSelfPlaced ? Order::calculateManagerReferralBonus($sum) : 0;
                } else {
                    $refBonus = Order::calculateReferralBonus($sum, $isPartner, $isFirstClientOrder);
                }
                if ($refBonus > 0) {
                    $this->pdo->prepare('UPDATE users SET points_balance = points_balance + ? WHERE id = ?')->execute([$refBonus, $refId]);
                    $description = $explicitPartner > 0
                        ? "Партнерское начисление Florix24 за заказ №{$orderId}"
                        : ($refRole === 'manager'
                        ? "Бонус менеджера за самостоятельный заказ по ссылке №{$orderId}"
                        : "Бонус за заказ №{$orderId}");
                    if ($explicitPartner > 0) {
                        $this->insertPartnerReward($refId, $orderId, $refBonus, $description);
                    } else {
                        $this->insertPointsTransaction($refId, $orderId, $refBonus, $description);
                    }
                }
            }

            $managerBonus = 0;
            $projectManagerId = $this->findProjectManagerId();
            if ($projectManagerId > 0) {
                $managerBonus = Order::calculateProjectManagerBonus($sum);
                if ($managerBonus > 0) {
                    $this->pdo->prepare('UPDATE users SET points_balance = points_balance + ? WHERE id = ?')->execute([$managerBonus, $projectManagerId]);
                    $this->insertPointsTransaction($projectManagerId, $orderId, $managerBonus, "Базовые 3% менеджера за заказ №{$orderId}");
                }
            }
            $this->pdo->prepare('UPDATE orders SET points_accrued = ?, manager_points_accrued = ? WHERE id = ?')
                ->execute([$refBonus, $managerBonus, $orderId]);
        }

        if ($this->tableExists('seller_payouts')) {
            $payouts = $this->pdo->prepare("SELECT seller_id, payout_amount FROM seller_payouts WHERE order_id = ? AND status <> 'accrued'");
            $payouts->execute([$orderId]);
            $rows = $payouts->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($rows) {
                $credit = $this->pdo->prepare('UPDATE users SET rub_balance = rub_balance + ? WHERE id = ?');
                foreach ($rows as $row) {
                    $credit->execute([(float)$row['payout_amount'], (int)$row['seller_id']]);
                }
                $this->pdo->prepare("UPDATE seller_payouts SET status = 'accrued' WHERE order_id = ?")->execute([$orderId]);
            }
        }
    }

    /** @param array<string,mixed> $order */
    private function restoreCancelledOrderBenefits(int $orderId, array $order): void
    {
        $userId = (int)$order['user_id'];
        $pointsBack = (int)($order['points_used'] ?? 0);
        if ($pointsBack > 0 && $this->tableExists('points_transactions')) {
            $check = $this->pdo->prepare(
                "SELECT COUNT(*) FROM points_transactions
                 WHERE order_id = ? AND transaction_type = 'accrual' AND description LIKE 'Возврат % за отмену заказа %'"
            );
            $check->execute([$orderId]);
            if ((int)$check->fetchColumn() === 0) {
                $this->pdo->prepare('UPDATE users SET points_balance = points_balance + ? WHERE id = ?')->execute([$pointsBack, $userId]);
                $this->insertPointsTransaction($userId, $orderId, $pointsBack, "Возврат {$pointsBack} за отмену заказа №{$orderId}");
            }
        }

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND id <> ? AND status NOT IN ('cancelled','returned')");
        $count->execute([$userId, $orderId]);
        if ((int)$count->fetchColumn() === 0) {
            $this->pdo->prepare('UPDATE users SET has_used_referral_coupon = 0 WHERE id = ?')->execute([$userId]);
        }
    }

    private function insertPointsTransaction(int $userId, int $orderId, int $amount, string $description): void
    {
        if (!$this->tableExists('points_transactions') || $amount <= 0) {
            return;
        }
        $timestamp = $this->driver() === 'sqlite' ? "datetime('now')" : 'NOW()';
        $stmt = $this->pdo->prepare(
            "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at)
             VALUES (?, ?, ?, 'accrual', ?, {$timestamp})"
        );
        $stmt->execute([$userId, $orderId, $amount, $description]);
    }

    private function insertPartnerReward(int $userId, int $orderId, int $amount, string $description): void
    {
        if ($amount <= 0) return;
        try {
            $timestamp = $this->driver() === 'sqlite' ? "datetime('now')" : 'NOW()';
            $stmt = $this->pdo->prepare(
                "INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, source, created_at)
                 VALUES (?, ?, ?, 'partner_reward', ?, 'florix24', {$timestamp})"
            );
            $stmt->execute([$userId, $orderId, $amount, $description]);
        } catch (Throwable $e) {
            // Compatibility with older local schemas; production migration adds source.
            $this->insertPointsTransaction($userId, $orderId, $amount, $description);
        }
    }

    private function findProjectManagerId(): int
    {
        $stmt = $this->pdo->query("SELECT id FROM users WHERE role = 'manager' AND referred_by IS NULL ORDER BY id LIMIT 1");
        $managerId = (int)($stmt ? ($stmt->fetchColumn() ?: 0) : 0);
        if ($managerId > 0) {
            return $managerId;
        }
        $stmt = $this->pdo->query("SELECT id FROM users WHERE role = 'manager' ORDER BY id LIMIT 1");
        return (int)($stmt ? ($stmt->fetchColumn() ?: 0) : 0);
    }

    private function mainStatus(string $status): string
    {
        return match ($status) {
            'reserved', 'new' => 'new',
            'confirmed', 'shipped' => 'confirmed',
            'completed' => 'completed',
            'cancelled', 'returned' => 'cancelled',
            default => $status,
        };
    }

    private function tableExists(string $table): bool
    {
        try {
            if ($this->driver() === 'sqlite') {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
                $stmt->execute([$table]);
                return (bool)$stmt->fetchColumn();
            }
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function driver(): string
    {
        return (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
