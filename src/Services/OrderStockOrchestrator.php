<?php
namespace App\Services;

use PDO;
use RuntimeException;

class OrderStockOrchestrator
{
    private PDO $pdo;
    private StockService $stockService;

    public function __construct(PDO $pdo, ?StockService $stockService = null)
    {
        $this->pdo = $pdo;
        $this->stockService = $stockService ?? new StockService($pdo);
    }

    /**
     * @return array<int, array{batch_id:int, boxes:float}>
     */
    public function allocateFifoBatches(int $productId, float $requiredBoxes, string $mode): array
    {
        if ($requiredBoxes <= 0) {
            return [];
        }

        $column = $mode === 'discount_stock' ? 'boxes_discount' : 'boxes_free';
        $stmt = $this->pdo->prepare(
            "SELECT id, {$column} AS available_boxes\n" .
            "FROM purchase_batches\n" .
            "WHERE product_id = ? AND status IN ('active', 'arrived', 'purchased') AND {$column} > 0\n" .
            "ORDER BY purchased_at ASC, id ASC"
        );
        $stmt->execute([$productId]);

        $allocations = [];
        $left = $requiredBoxes;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($left <= 0) {
                break;
            }
            $available = (float)($row['available_boxes'] ?? 0);
            if ($available <= 0) {
                continue;
            }
            $take = min($left, $available);
            $allocations[] = ['batch_id' => (int)$row['id'], 'boxes' => $take];
            $left -= $take;
        }

        if ($left > 0.0001) {
            throw new RuntimeException('Недостаточно остатков партии для отгрузки по FIFO.');
        }

        return $allocations;
    }

    /**
     * @param array{quantity:float,box_size:float,unit_price:float,purchase_batch_id?:int|null} $data
     */
    public function persistOrderItemWithStock(\PDOStatement $stmtItem, int $orderId, int $productId, array $data, string $orderMode, bool $isReservedOrder): void
    {
        $kgQty = $data['quantity'] * $data['box_size'];
        $kgPrice = $data['box_size'] > 0 ? $data['unit_price'] / $data['box_size'] : $data['unit_price'];

        $allocations = [];
        $requestedBatchId = isset($data['purchase_batch_id']) ? (int)$data['purchase_batch_id'] : 0;
        if ($requestedBatchId > 0 && in_array($orderMode, ['preorder', 'instant', 'discount_stock'], true)) {
            $allocations = [['batch_id' => $requestedBatchId, 'boxes' => (float)$data['quantity']]];
        } elseif (in_array($orderMode, ['preorder', 'instant', 'discount_stock'], true)) {
            $allocations = $this->allocateFifoBatches($productId, (float)$data['quantity'], $orderMode);
        }

        if ($allocations === []) {
            $stmtItem->execute([$orderId, $productId, $kgQty, $data['quantity'], $kgPrice, $orderMode, null]);
            return;
        }

        foreach ($allocations as $allocation) {
            $allocatedBoxes = (float)$allocation['boxes'];
            $allocatedKgQty = $allocatedBoxes * (float)$data['box_size'];

            $stmtItem->execute([
                $orderId,
                $productId,
                $allocatedKgQty,
                $allocatedBoxes,
                $kgPrice,
                $orderMode,
                (int)$allocation['batch_id'],
            ]);

            if ($orderMode === 'preorder' || $isReservedOrder) {
                $this->stockService->reserve($productId, (int)$allocation['batch_id'], $allocatedBoxes, $orderId, $orderMode);
            } else {
                $this->stockService->sellAvailable($productId, (int)$allocation['batch_id'], $allocatedBoxes, $orderId, $orderMode);
            }
        }
    }


    /**
     * @param array{quantity:float,box_size:float,unit_price:float,purchase_batch_id?:int|null} $data
     */
    public function persistOrderItemOnly(\PDOStatement $stmtItem, int $orderId, int $productId, array $data, string $orderMode): void
    {
        $kgQty = $data['quantity'] * $data['box_size'];
        $kgPrice = $data['box_size'] > 0 ? $data['unit_price'] / $data['box_size'] : $data['unit_price'];
        $batchId = isset($data['purchase_batch_id']) && (int)$data['purchase_batch_id'] > 0 ? (int)$data['purchase_batch_id'] : null;
        $stmtItem->execute([$orderId, $productId, $kgQty, $data['quantity'], $kgPrice, $orderMode, $batchId]);
    }

    public function applyStockForOrderId(int $orderId): void
    {
        if ($this->hasStockMovements($orderId)) {
            return;
        }

        $itemsStmt = $this->pdo->prepare(
            'SELECT product_id, purchase_batch_id, boxes, stock_mode FROM order_items WHERE order_id = ?'
        );
        $itemsStmt->execute([$orderId]);
        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $productId = (int)$item['product_id'];
            $mode = (string)($item['stock_mode'] ?? 'instant');
            $boxes = (float)$item['boxes'];
            if ($boxes <= 0) {
                continue;
            }

            $requestedBatchId = (int)($item['purchase_batch_id'] ?? 0);
            $batchId = $requestedBatchId > 0
                ? $requestedBatchId
                : $this->resolveBatchForDeferredStock($productId, $mode);

            $this->stockService->reserve($productId, $batchId, $boxes, $orderId, $mode);
            $this->pdo->prepare(
                'UPDATE order_items SET purchase_batch_id = COALESCE(purchase_batch_id, ?) WHERE order_id = ? AND product_id = ? AND purchase_batch_id IS NULL'
            )->execute([$batchId, $orderId, $productId]);
        }
    }

    public function commitReservedStockByOrderId(int $orderId): void
    {
        if (!$this->hasReserveMovements($orderId)) {
            return;
        }
        if ($this->hasSaleMovements($orderId)) {
            return;
        }

        $itemsStmt = $this->pdo->prepare(
            'SELECT product_id, purchase_batch_id, boxes FROM order_items WHERE order_id = ? AND purchase_batch_id IS NOT NULL'
        );
        $itemsStmt->execute([$orderId]);
        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $this->stockService->sell(
                (int)$item['product_id'],
                (int)$item['purchase_batch_id'],
                (float)$item['boxes'],
                $orderId
            );
        }
    }

    private function resolveBatchForDeferredStock(int $productId, string $mode): int
    {
        $column = $mode === 'discount_stock' ? 'boxes_discount' : 'boxes_free';
        $stmt = $this->pdo->prepare(
            "SELECT id FROM purchase_batches WHERE product_id = ? AND status IN ('active', 'arrived', 'purchased') ORDER BY CASE WHEN {$column} > 0 THEN 0 ELSE 1 END, purchased_at ASC, id ASC LIMIT 1"
        );
        $stmt->execute([$productId]);
        $batchId = (int)($stmt->fetchColumn() ?: 0);
        if ($batchId <= 0) {
            throw new RuntimeException('Недостаточно остатков партии для отгрузки по FIFO.');
        }
        return $batchId;
    }

    private function hasStockMovements(int $orderId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM stock_movements WHERE order_id = ?');
        $stmt->execute([$orderId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function hasReserveMovements(int $orderId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE order_id = ? AND movement_type = 'reserve'");
        $stmt->execute([$orderId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function hasSaleMovements(int $orderId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE order_id = ? AND movement_type = 'sale'");
        $stmt->execute([$orderId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function rollbackReservationByOrderId(int $orderId): void
    {
        $itemsStmt = $this->pdo->prepare(
            'SELECT product_id, purchase_batch_id, boxes, stock_mode FROM order_items WHERE order_id = ? AND purchase_batch_id IS NOT NULL'
        );
        $itemsStmt->execute([$orderId]);
        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $mode = (string)($item['stock_mode'] ?? 'instant');
            if (!$this->hasSaleMovements($orderId) && ($mode === 'preorder' || $this->hasReserveMovements($orderId))) {
                $this->stockService->unreserve(
                    (int)$item['product_id'],
                    (int)$item['purchase_batch_id'],
                    (float)$item['boxes'],
                    $orderId,
                    $mode
                );
                continue;
            }

            $this->stockService->returnSale(
                (int)$item['product_id'],
                (int)$item['purchase_batch_id'],
                (float)$item['boxes'],
                $orderId,
                $mode
            );
        }
    }
}
