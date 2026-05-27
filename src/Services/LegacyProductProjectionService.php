<?php
namespace App\Services;

use PDO;

class LegacyProductProjectionService
{
    private PDO $pdo;
    private bool $enabled;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $raw = getenv('LEGACY_PRODUCT_PROJECTION_ENABLED');
        $this->enabled = $raw === false ? true : in_array(strtolower((string)$raw), ['1', 'true', 'yes', 'on'], true);
    }

    public function syncAggregatesFromBatches(int $productId): void
    {
        if (!$this->enabled) {
            return;
        }

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
        if (!$row) return;

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

    /** @param array<string,float> $prices */
    public function updateBatchSnapshot(int $productId, int $batchId, float $freeBoxes, float $reservedBoxes, array $prices): void
    {
        if (!$this->enabled) {
            return;
        }
        $stockStatus = $freeBoxes > 0 ? 'in_stock' : 'preorder';
        $stmt = $this->pdo->prepare(
            'UPDATE products
             SET current_purchase_batch_id = :batch_id,
                 free_stock_boxes = :free_stock_boxes,
                 reserved_stock_boxes = :reserved_stock_boxes,
                 preorder_price_per_box = :preorder_price_per_box,
                 instant_price_per_box = :instant_price_per_box,
                 discount_price_per_box = :discount_price_per_box,
                 preorder_unit_price = :preorder_unit_price,
                 instant_unit_price = :instant_unit_price,
                 discount_unit_price = :discount_unit_price,
                 price = :price,
                 stock_status = :stock_status
             WHERE id = :product_id'
        );
        $stmt->execute([
            'batch_id' => $batchId,
            'free_stock_boxes' => $freeBoxes,
            'reserved_stock_boxes' => $reservedBoxes,
            'preorder_price_per_box' => $prices['preorder_price_per_box'],
            'instant_price_per_box' => $prices['instant_price_per_box'],
            'discount_price_per_box' => $prices['discount_price_per_box'],
            'preorder_unit_price' => $prices['preorder_unit_price'],
            'instant_unit_price' => $prices['instant_unit_price'],
            'discount_unit_price' => $prices['discount_unit_price'],
            'price' => $prices['instant_unit_price'],
            'stock_status' => $stockStatus,
            'product_id' => $productId,
        ]);
    }
}

