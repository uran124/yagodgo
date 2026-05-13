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
     * @param array{quantity:float,box_size:float,unit_price:float} $data
     */
    public function persistOrderItemWithStock(\PDOStatement $stmtItem, int $orderId, int $productId, array $data, string $orderMode, bool $isReservedOrder): void
    {
        $kgQty = $data['quantity'] * $data['box_size'];
        $kgPrice = $data['box_size'] > 0 ? $data['unit_price'] / $data['box_size'] : $data['unit_price'];

        $allocations = [];
        if (in_array($orderMode, ['preorder', 'instant', 'discount_stock'], true)) {
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

            $this->stockService->reserve($productId, (int)$allocation['batch_id'], $allocatedBoxes, $orderId, $orderMode);
            if (!$isReservedOrder) {
                $this->stockService->sell($productId, (int)$allocation['batch_id'], $allocatedBoxes, $orderId);
            }
        }
    }

    public function rollbackReservationByOrderId(int $orderId): void
    {
        $itemsStmt = $this->pdo->prepare(
            'SELECT product_id, purchase_batch_id, boxes, stock_mode FROM order_items WHERE order_id = ? AND purchase_batch_id IS NOT NULL'
        );
        $itemsStmt->execute([$orderId]);
        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $this->stockService->unreserve(
                (int)$item['product_id'],
                (int)$item['purchase_batch_id'],
                (float)$item['boxes'],
                $orderId,
                (string)($item['stock_mode'] ?? 'instant')
            );
        }
    }
}
