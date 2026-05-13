<?php
namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

class StockService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAvailableBoxes(int $productId, string $mode): float
    {
        $column = $this->resolveModeColumn($mode);
        $stmt = $this->pdo->prepare("SELECT {$column} AS available FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException('Product not found.');
        }

        return (float)($row['available'] ?? 0);
    }

    public function reserve(int $productId, int $batchId, float $boxes, int $orderId, string $mode): void
    {
        if ($boxes <= 0) {
            throw new RuntimeException('Reserve boxes must be greater than zero.');
        }
        if (!in_array($mode, ['preorder', 'instant', 'discount_stock'], true)) {
            throw new RuntimeException('Unsupported stock mode for reserve.');
        }

        $this->changeStock($productId, $batchId, $orderId, $mode, -$boxes, 'reserve');
    }

    public function unreserve(int $productId, int $batchId, float $boxes, int $orderId): void
    {
        if ($boxes <= 0) {
            throw new RuntimeException('Unreserve boxes must be greater than zero.');
        }

        try {
            $this->pdo->beginTransaction();
            $this->appendMovement($batchId, $productId, $orderId, null, 'unreserve', 'internal', $boxes);
            $this->updateBatchCounters($batchId, [
                'boxes_reserved' => $boxes,
            ]);
            $this->assertBatchInvariants($batchId);
            $this->syncProductStock($productId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function sell(int $productId, int $batchId, float $boxes, int $orderId): void
    {
        if ($boxes <= 0) {
            throw new RuntimeException('Sell boxes must be greater than zero.');
        }

        try {
            $this->pdo->beginTransaction();
            $this->appendMovement($batchId, $productId, $orderId, null, 'sale', 'internal', -$boxes);
            $this->updateBatchCounters($batchId, [
                'boxes_reserved' => -$boxes,
                'boxes_sold' => $boxes,
            ]);
            $this->assertBatchInvariants($batchId);
            $this->syncProductStock($productId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function writeOff(int $batchId, float $boxes, int $userId, string $comment): void
    {
        if ($boxes <= 0) {
            throw new RuntimeException('Write off boxes must be greater than zero.');
        }

        $productId = $this->getBatchProductId($batchId);

        try {
            $this->pdo->beginTransaction();
            $this->appendMovement($batchId, $productId, null, $userId, 'writeoff', 'internal', -$boxes, $comment);
            $this->updateBatchCounters($batchId, [
                'boxes_written_off' => $boxes,
            ]);
            $this->assertBatchInvariants($batchId);
            $this->syncProductStock($productId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function recalculateBatchCounters(int $batchId): void
    {
        $stmt = $this->pdo->prepare('SELECT boxes_total, boxes_sold, boxes_written_off FROM purchase_batches WHERE id = ? LIMIT 1');
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$batch) {
            throw new RuntimeException('Batch not found for recalculate.');
        }

        $remaining = (float)$batch['boxes_total'] - (float)$batch['boxes_sold'] - (float)$batch['boxes_written_off'];
        $upd = $this->pdo->prepare('UPDATE purchase_batches SET boxes_remaining = ? WHERE id = ?');
        $upd->execute([$remaining, $batchId]);
    }

    public function syncProductStock(int $productId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                COALESCE(SUM(boxes_free), 0) AS free_boxes,
                COALESCE(SUM(boxes_reserved), 0) AS reserved_boxes,
                COALESCE(SUM(boxes_discount), 0) AS discount_boxes,
                COALESCE(SUM(boxes_sold), 0) AS sold_boxes,
                COALESCE(SUM(boxes_written_off), 0) AS written_off_boxes
             FROM purchase_batches
             WHERE product_id = ? AND status IN ("active", "arrived", "purchased")'
        );
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return;
        }

        $status = ((float)$row['free_boxes'] > 0) ? 'in_stock' : (((float)$row['reserved_boxes'] > 0) ? 'preorder' : 'sold_out');

        $upd = $this->pdo->prepare(
            'UPDATE products
             SET free_stock_boxes = :free_boxes,
                 reserved_stock_boxes = :reserved_boxes,
                 discount_stock_boxes = :discount_boxes,
                 sold_stock_boxes = :sold_boxes,
                 written_off_stock_boxes = :written_off_boxes,
                 stock_status = :stock_status
             WHERE id = :product_id'
        );
        $upd->execute([
            'free_boxes' => (float)$row['free_boxes'],
            'reserved_boxes' => (float)$row['reserved_boxes'],
            'discount_boxes' => (float)$row['discount_boxes'],
            'sold_boxes' => (float)$row['sold_boxes'],
            'written_off_boxes' => (float)$row['written_off_boxes'],
            'stock_status' => $status,
            'product_id' => $productId,
        ]);
    }

    private function changeStock(int $productId, int $batchId, int $orderId, string $mode, float $delta, string $movementType): void
    {
        $batch = $this->loadBatch($batchId);
        $column = $this->resolveModeColumn($mode, true);

        if (((float)$batch[$column] + $delta) < 0) {
            throw new RuntimeException('Not enough stock in selected mode.');
        }

        try {
            $this->pdo->beginTransaction();

            $this->appendMovement($batchId, $productId, $orderId, null, $movementType, $mode, $delta);

            $updates = [$column => $delta];
            if ($movementType === 'reserve') {
                $updates['boxes_reserved'] = abs($delta);
            }

            $this->updateBatchCounters($batchId, $updates);
            $this->assertBatchInvariants($batchId);
            $this->syncProductStock($productId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function resolveModeColumn(string $mode, bool $forBatch = false): string
    {
        if ($mode === 'preorder') {
            return $forBatch ? 'boxes_free' : 'reserved_stock_boxes';
        }
        if ($mode === 'instant') {
            return $forBatch ? 'boxes_free' : 'free_stock_boxes';
        }
        if ($mode === 'discount_stock') {
            return $forBatch ? 'boxes_discount' : 'discount_stock_boxes';
        }

        throw new RuntimeException('Unknown stock mode.');
    }

    private function getBatchProductId(int $batchId): int
    {
        $stmt = $this->pdo->prepare('SELECT product_id FROM purchase_batches WHERE id = ? LIMIT 1');
        $stmt->execute([$batchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Batch not found.');
        }

        return (int)$row['product_id'];
    }

    /** @return array<string, mixed> */
    private function loadBatch(int $batchId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM purchase_batches WHERE id = ? LIMIT 1');
        $stmt->execute([$batchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Batch not found.');
        }

        return $row;
    }

    /** @param array<string, float> $deltas */
    private function updateBatchCounters(int $batchId, array $deltas): void
    {
        $parts = [];
        $params = [];
        foreach ($deltas as $column => $delta) {
            $parts[] = "{$column} = {$column} + ?";
            $params[] = $delta;
        }
        $params[] = $batchId;

        $sql = 'UPDATE purchase_batches SET ' . implode(', ', $parts) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }



    private function assertBatchInvariants(int $batchId): void
    {
        $batch = $this->loadBatch($batchId);

        $nonNegativeColumns = [
            'boxes_total',
            'boxes_reserved',
            'boxes_free',
            'boxes_discount',
            'boxes_sold',
            'boxes_written_off',
            'boxes_remaining',
        ];

        foreach ($nonNegativeColumns as $column) {
            if ((float)$batch[$column] < 0) {
                throw new RuntimeException('Batch invariant failed: ' . $column . ' can not be negative.');
            }
        }

        $expectedRemaining = (float)$batch['boxes_total'] - (float)$batch['boxes_sold'] - (float)$batch['boxes_written_off'];
        if (abs((float)$batch['boxes_remaining'] - $expectedRemaining) > 0.0001) {
            throw new RuntimeException('Batch invariant failed: boxes_remaining mismatch.');
        }
    }

    private function appendMovement(
        int $batchId,
        int $productId,
        ?int $orderId,
        ?int $userId,
        string $movementType,
        string $mode,
        float $boxesDelta,
        ?string $comment = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO stock_movements
                (purchase_batch_id, product_id, order_id, user_id, movement_type, stock_mode, boxes_delta, comment)
             VALUES
                (:purchase_batch_id, :product_id, :order_id, :user_id, :movement_type, :stock_mode, :boxes_delta, :comment)'
        );

        $stmt->execute([
            'purchase_batch_id' => $batchId,
            'product_id' => $productId,
            'order_id' => $orderId,
            'user_id' => $userId,
            'movement_type' => $movementType,
            'stock_mode' => $mode,
            'boxes_delta' => $boxesDelta,
            'comment' => $comment,
        ]);
    }
}
