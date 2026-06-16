<?php
namespace App\Services;

use PDO;

class ProductionJobService
{
    public const STATUS_NEW = 'new';
    public const STATUS_ASSIGNED = 'assigned';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO production_jobs (
                order_id, product_id, executor_type, executor_id, fulfillment_model,
                production_location, status, production_deadline, handover_deadline,
                bonus_type, bonus_value, bonus_amount_locked, materials_required,
                materials_delivery_required, materials_delivery_cost,
                result_delivery_required, result_delivery_cost, manager_comment,
                created_at, updated_at
            ) VALUES (
                :order_id, :product_id, :executor_type, :executor_id, :fulfillment_model,
                :production_location, :status, :production_deadline, :handover_deadline,
                :bonus_type, :bonus_value, :bonus_amount_locked, :materials_required,
                :materials_delivery_required, :materials_delivery_cost,
                :result_delivery_required, :result_delivery_cost, :manager_comment,
                ' . $this->currentTimestampExpression() . ', ' . $this->currentTimestampExpression() . '
            )'
        );

        $status = (string)($data['status'] ?? self::STATUS_NEW);
        $stmt->execute([
            'order_id' => (int)$data['order_id'],
            'product_id' => isset($data['product_id']) ? (int)$data['product_id'] : null,
            'executor_type' => $data['executor_type'] ?? null,
            'executor_id' => isset($data['executor_id']) ? (int)$data['executor_id'] : null,
            'fulfillment_model' => (string)($data['fulfillment_model'] ?? 'by_berrygo_on_site'),
            'production_location' => (string)($data['production_location'] ?? 'shop'),
            'status' => $status,
            'production_deadline' => $data['production_deadline'] ?? null,
            'handover_deadline' => $data['handover_deadline'] ?? null,
            'bonus_type' => (string)($data['bonus_type'] ?? 'internal_bonus'),
            'bonus_value' => isset($data['bonus_value']) ? (float)$data['bonus_value'] : 0,
            'bonus_amount_locked' => isset($data['bonus_amount_locked']) ? (float)$data['bonus_amount_locked'] : 0,
            'materials_required' => $data['materials_required'] ?? null,
            'materials_delivery_required' => !empty($data['materials_delivery_required']) ? 1 : 0,
            'materials_delivery_cost' => isset($data['materials_delivery_cost']) ? (float)$data['materials_delivery_cost'] : 0,
            'result_delivery_required' => !empty($data['result_delivery_required']) ? 1 : 0,
            'result_delivery_cost' => isset($data['result_delivery_cost']) ? (float)$data['result_delivery_cost'] : 0,
            'manager_comment' => $data['manager_comment'] ?? null,
        ]);

        $jobId = (int)$this->pdo->lastInsertId();
        $this->recordEvent($jobId, (int)$data['order_id'], null, $status, null, null, 'production_job_created');

        return $jobId;
    }


    public function createForOrderIfRequired(int $orderId): int
    {
        if ($orderId <= 0 || !$this->tableExists('production_jobs') || !$this->columnExists('products', 'requires_production')) {
            return 0;
        }

        $productNameExpression = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "GROUP_CONCAT(COALESCE(NULLIF(p.variety, ''), 'Товар #' || p.id), ', ')"
            : "GROUP_CONCAT(COALESCE(NULLIF(p.variety, ''), CONCAT('Товар #', p.id)) SEPARATOR ', ')";

        $select = "SELECT oi.product_id,
                          SUM(oi.quantity) AS total_quantity,
                          SUM(oi.quantity * oi.unit_price) AS line_total,
                          MAX(p.default_fulfillment_model) AS fulfillment_model,
                          MAX(p.default_production_minutes) AS production_minutes,
                          MAX(p.default_executor_bonus_percent) AS bonus_percent,
                          MAX(p.default_executor_bonus_amount) AS fixed_bonus,
                          MAX(p.production_spec_id) AS production_spec_id,
                          {$productNameExpression} AS product_names
                   FROM order_items oi
                   JOIN products p ON p.id = oi.product_id
                   WHERE oi.order_id = ?
                     AND p.requires_production = 1
                   GROUP BY oi.product_id
                   ORDER BY oi.product_id";
        $stmt = $this->pdo->prepare($select);
        $stmt->execute([$orderId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) {
            return 0;
        }

        $created = 0;
        foreach ($rows as $row) {
            $productId = (int)($row['product_id'] ?? 0);
            if ($productId <= 0 || $this->jobExistsForOrderProduct($orderId, $productId)) {
                continue;
            }

            $minutes = max(1, (int)($row['production_minutes'] ?? 120));
            $fixedBonus = (float)($row['fixed_bonus'] ?? 0);
            $bonusPercent = (float)($row['bonus_percent'] ?? 10);
            $lineTotal = (float)($row['line_total'] ?? 0);
            $lockedBonus = max($fixedBonus, round($lineTotal * $bonusPercent / 100, 2));
            $deadlineExpression = $this->deadlineExpression($minutes);

            $stmtInsert = $this->pdo->prepare(
                'INSERT INTO production_jobs (
                    order_id, product_id, fulfillment_model, production_location, status,
                    production_deadline, bonus_type, bonus_value, bonus_amount_locked,
                    manager_comment, created_at, updated_at
                ) VALUES (
                    :order_id, :product_id, :fulfillment_model, :production_location, :status,
                    ' . $deadlineExpression . ', :bonus_type, :bonus_value, :bonus_amount_locked,
                    :manager_comment, ' . $this->currentTimestampExpression() . ', ' . $this->currentTimestampExpression() . '
                )'
            );
            $fulfillmentModel = (string)($row['fulfillment_model'] ?: 'by_berrygo_on_site');
            $stmtInsert->execute([
                'order_id' => $orderId,
                'product_id' => $productId,
                'fulfillment_model' => $fulfillmentModel,
                'production_location' => $fulfillmentModel === 'by_berrygo_remote' ? 'remote' : 'shop',
                'status' => self::STATUS_NEW,
                'bonus_type' => 'internal_bonus',
                'bonus_value' => $bonusPercent,
                'bonus_amount_locked' => $lockedBonus,
                'manager_comment' => 'Автозадание по производственному товару: ' . (string)($row['product_names'] ?? ('#' . $productId)),
            ]);

            $jobId = (int)$this->pdo->lastInsertId();
            $this->recordEvent($jobId, $orderId, null, self::STATUS_NEW, null, null, 'production_job_auto_created');
            $created++;
        }

        return $created;
    }

    public function assignAtomically(int $jobId, int $executorId, string $executorType, ?int $changedByUserId = null, ?string $changedByRole = null): bool
    {
        if ($jobId <= 0 || $executorId <= 0 || $executorType === '') {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE production_jobs
                SET executor_id = :executor_id,
                    executor_type = :executor_type,
                    status = :assigned_status,
                    assigned_at = ' . $this->currentTimestampExpression() . ',
                    updated_at = ' . $this->currentTimestampExpression() . '
              WHERE id = :job_id
                AND executor_id IS NULL
                AND status = :new_status'
        );
        $stmt->execute([
            'executor_id' => $executorId,
            'executor_type' => $executorType,
            'assigned_status' => self::STATUS_ASSIGNED,
            'job_id' => $jobId,
            'new_status' => self::STATUS_NEW,
        ]);

        if ($stmt->rowCount() !== 1) {
            return false;
        }

        $orderId = $this->findOrderId($jobId);
        if ($orderId !== null) {
            $this->recordEvent($jobId, $orderId, self::STATUS_NEW, self::STATUS_ASSIGNED, $changedByUserId, $changedByRole, 'production_job_assigned');
        }

        return true;
    }

    public function recordEvent(int $jobId, int $orderId, ?string $fromStatus, string $toStatus, ?int $changedByUserId, ?string $changedByRole, ?string $comment = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO production_job_events (job_id, order_id, from_status, to_status, changed_by_user_id, changed_by_role, comment, created_at)
             VALUES (:job_id, :order_id, :from_status, :to_status, :changed_by_user_id, :changed_by_role, :comment, ' . $this->currentTimestampExpression() . ')'
        );
        $stmt->execute([
            'job_id' => $jobId,
            'order_id' => $orderId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by_user_id' => $changedByUserId,
            'changed_by_role' => $changedByRole,
            'comment' => $comment,
        ]);
    }


    private function jobExistsForOrderProduct(int $orderId, int $productId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM production_jobs WHERE order_id = ? AND product_id = ?');
        $stmt->execute([$orderId, $productId]);
        return (int)$stmt->fetchColumn() > 0;
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

    private function columnExists(string $table, string $column): bool
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->query("PRAGMA table_info({$table})");
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
    }

    private function deadlineExpression(int $minutes): string
    {
        if ((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return "datetime('now', '+" . $minutes . " minutes')";
        }

        return 'DATE_ADD(NOW(), INTERVAL ' . $minutes . ' MINUTE)';
    }

    private function findOrderId(int $jobId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT order_id FROM production_jobs WHERE id = ?');
        $stmt->execute([$jobId]);
        $orderId = $stmt->fetchColumn();

        return $orderId === false ? null : (int)$orderId;
    }

    private function currentTimestampExpression(): string
    {
        return (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? "datetime('now')" : 'NOW()';
    }
}
