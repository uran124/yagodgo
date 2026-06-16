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
                result_delivery_required, result_delivery_cost, estimated_materials_cost,
                estimated_acquiring_cost, estimated_margin_amount, minimum_margin_amount,
                margin_status, manager_comment, created_at, updated_at
            ) VALUES (
                :order_id, :product_id, :executor_type, :executor_id, :fulfillment_model,
                :production_location, :status, :production_deadline, :handover_deadline,
                :bonus_type, :bonus_value, :bonus_amount_locked, :materials_required,
                :materials_delivery_required, :materials_delivery_cost,
                :result_delivery_required, :result_delivery_cost, :estimated_materials_cost,
                :estimated_acquiring_cost, :estimated_margin_amount, :minimum_margin_amount,
                :margin_status, :manager_comment,
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
            'estimated_materials_cost' => isset($data['estimated_materials_cost']) ? (float)$data['estimated_materials_cost'] : 0,
            'estimated_acquiring_cost' => isset($data['estimated_acquiring_cost']) ? (float)$data['estimated_acquiring_cost'] : 0,
            'estimated_margin_amount' => isset($data['estimated_margin_amount']) ? (float)$data['estimated_margin_amount'] : null,
            'minimum_margin_amount' => isset($data['minimum_margin_amount']) ? (float)$data['minimum_margin_amount'] : 0,
            'margin_status' => (string)($data['margin_status'] ?? 'unknown'),
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
                          MAX(p.default_materials_cost) AS materials_cost,
                          MAX(p.minimum_production_margin) AS minimum_margin,
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
            $materialsCost = max(0, (float)($row['materials_cost'] ?? 0) * max(1, (float)($row['total_quantity'] ?? 1)));
            $acquiringCost = round($lineTotal * 0.035, 2);
            $minimumMargin = max(0, (float)($row['minimum_margin'] ?? 0));
            $estimatedMargin = round($lineTotal - $materialsCost - $lockedBonus - $acquiringCost, 2);
            $marginStatus = $estimatedMargin >= $minimumMargin ? 'ok' : 'low_margin';

            $stmtInsert = $this->pdo->prepare(
                'INSERT INTO production_jobs (
                    order_id, product_id, fulfillment_model, production_location, status,
                    production_deadline, bonus_type, bonus_value, bonus_amount_locked,
                    estimated_materials_cost, estimated_acquiring_cost, estimated_margin_amount,
                    minimum_margin_amount, margin_status, manager_comment, created_at, updated_at
                ) VALUES (
                    :order_id, :product_id, :fulfillment_model, :production_location, :status,
                    ' . $deadlineExpression . ', :bonus_type, :bonus_value, :bonus_amount_locked,
                    :estimated_materials_cost, :estimated_acquiring_cost, :estimated_margin_amount,
                    :minimum_margin_amount, :margin_status, :manager_comment, ' . $this->currentTimestampExpression() . ', ' . $this->currentTimestampExpression() . '
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
                'estimated_materials_cost' => $materialsCost,
                'estimated_acquiring_cost' => $acquiringCost,
                'estimated_margin_amount' => $estimatedMargin,
                'minimum_margin_amount' => $minimumMargin,
                'margin_status' => $marginStatus,
                'manager_comment' => 'Автозадание по производственному товару: ' . (string)($row['product_names'] ?? ('#' . $productId)),
            ]);

            $jobId = (int)$this->pdo->lastInsertId();
            $this->recordEvent($jobId, $orderId, null, self::STATUS_NEW, null, null, 'production_job_auto_created');
            $created++;
        }

        return $created;
    }


    public function addPhoto(int $jobId, string $imagePath, string $photoType = 'ready', ?int $changedByUserId = null, ?string $changedByRole = null): bool
    {
        if ($jobId <= 0 || $imagePath === '' || !$this->tableExists('production_job_photos')) {
            return false;
        }

        $orderId = $this->findOrderId($jobId);
        if ($orderId === null) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO production_job_photos (job_id, order_id, image_path, photo_type, review_status, created_at)
             VALUES (:job_id, :order_id, :image_path, :photo_type, :review_status, ' . $this->currentTimestampExpression() . ')'
        );
        $stmt->execute([
            'job_id' => $jobId,
            'order_id' => $orderId,
            'image_path' => $imagePath,
            'photo_type' => in_array($photoType, ['ready', 'packaging', 'handover'], true) ? $photoType : 'ready',
            'review_status' => 'pending',
        ]);

        $this->transitionStatus($jobId, 'photo_uploaded', $changedByUserId, $changedByRole, 'production_photo_uploaded');

        return true;
    }

    public function reviewPhoto(int $photoId, string $reviewStatus, ?int $reviewedByUserId, ?string $comment = null): bool
    {
        if ($photoId <= 0 || !$this->tableExists('production_job_photos')) {
            return false;
        }

        $reviewStatus = in_array($reviewStatus, ['approved', 'rejected'], true) ? $reviewStatus : 'rejected';
        $stmt = $this->pdo->prepare(
            'UPDATE production_job_photos
                SET review_status = :review_status,
                    reviewed_by_user_id = :reviewed_by_user_id,
                    reviewed_at = ' . $this->currentTimestampExpression() . ',
                    review_comment = :review_comment
              WHERE id = :photo_id'
        );
        $stmt->execute([
            'review_status' => $reviewStatus,
            'reviewed_by_user_id' => $reviewedByUserId,
            'review_comment' => $comment,
            'photo_id' => $photoId,
        ]);

        if ($stmt->rowCount() < 1) {
            return false;
        }

        $photo = $this->findPhoto($photoId);
        if (!$photo) {
            return true;
        }

        $this->transitionStatus(
            (int)$photo['job_id'],
            $reviewStatus === 'approved' ? 'approved' : 'problem',
            $reviewedByUserId,
            null,
            $reviewStatus === 'approved' ? 'production_photo_approved' : 'production_photo_rejected'
        );

        return true;
    }

    public function transitionStatus(int $jobId, string $toStatus, ?int $changedByUserId = null, ?string $changedByRole = null, ?string $comment = null): bool
    {
        if ($jobId <= 0 || $toStatus === '') {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT order_id, status FROM production_jobs WHERE id = ?');
        $stmt->execute([$jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            return false;
        }

        $fromStatus = (string)$job['status'];
        if ($fromStatus === $toStatus) {
            return true;
        }

        $timestamp = $this->currentTimestampExpression();
        $photoUploadedAt = $toStatus === 'photo_uploaded' ? $timestamp : 'photo_uploaded_at';
        $approvedAt = $toStatus === 'approved' ? $timestamp : 'approved_at';
        $update = $this->pdo->prepare(
            'UPDATE production_jobs
                SET status = :status,
                    photo_uploaded_at = ' . $photoUploadedAt . ',
                    approved_at = ' . $approvedAt . ',
                    updated_at = ' . $timestamp . '
              WHERE id = :job_id'
        );
        $update->execute([
            'status' => $toStatus,
            'job_id' => $jobId,
        ]);

        $this->recordEvent($jobId, (int)$job['order_id'], $fromStatus, $toStatus, $changedByUserId, $changedByRole, $comment);

        return true;
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



    /** @return array<string,mixed>|null */
    private function findPhoto(int $photoId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM production_job_photos WHERE id = ?');
        $stmt->execute([$photoId]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        return $photo ?: null;
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
