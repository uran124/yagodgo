<?php
namespace App\Services;

use PDO;

class SellableBatchResolver
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * FIFO-выбор продаваемой партии.
     *
     * @return array<string,mixed>|null
     */
    public function resolveForProduct(int $productId, string $stockMode): ?array
    {
        if ($stockMode === 'preorder') {
            $plannedAvailableExpr = "(COALESCE(NULLIF(pb.boxes_total, 0), pb.boxes_free + pb.boxes_reserved) - pb.boxes_reserved)";
            $stmt = $this->pdo->prepare(
                "SELECT pb.id, pb.preorder_price_per_box AS price_per_box, {$plannedAvailableExpr} AS boxes_available
                 FROM purchase_batches pb
                 JOIN products p ON p.id = pb.product_id
                 WHERE pb.product_id = ?
                   AND pb.status = 'planned'
                   AND pb.purchased_at IS NOT NULL
                   AND {$plannedAvailableExpr} > 0
                   AND pb.preorder_price_per_box > 0
                 ORDER BY pb.purchased_at ASC, pb.id ASC
                 LIMIT 1"
            );
            $stmt->execute([$productId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        $priceColumn = $stockMode === 'discount_stock'
            ? 'discount_price_per_box'
            : 'instant_price_per_box';
        $stockColumn = $stockMode === 'discount_stock'
            ? 'boxes_discount'
            : 'boxes_free';

        $stmt = $this->pdo->prepare(
            "SELECT id, {$priceColumn} AS price_per_box, {$stockColumn} AS boxes_available
             FROM purchase_batches
             WHERE product_id = ?
               AND status IN ('active', 'purchased', 'arrived')
               AND {$stockColumn} > 0
             ORDER BY purchased_at ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

