<?php
namespace App\Services;

use PDO;
use RuntimeException;

class ManualOrderAvailabilityService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array{inStockOffers:array<int,array<string,mixed>>,preorderOffers:array<int,array<string,mixed>>} */
    public function buildOffers(): array
    {
        return [
            'inStockOffers' => $this->fetchInStockOffers(),
            'preorderOffers' => $this->fetchPreorderOffers(),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function fetchInStockOffers(): array
    {
        $stmt = $this->pdo->query(
            "SELECT pb.id AS purchase_batch_id, pb.product_id, pb.purchased_at AS availability_date,\n" .
            "       pb.boxes_free AS available_boxes, pb.instant_price_per_box AS price_per_box,\n" .
            "       COALESCE(NULLIF(pb.box_size_snapshot, 0), NULLIF(p.box_size, 0), 1) AS box_size,\n" .
            "       COALESCE(NULLIF(pb.box_unit_snapshot, ''), p.box_unit, p.unit, '') AS box_unit,\n" .
            "       t.name AS product_name, p.variety, p.image_path, 'instant' AS stock_mode\n" .
            "FROM purchase_batches pb\n" .
            "JOIN products p ON p.id = pb.product_id\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "WHERE pb.status IN ('purchased', 'arrived')\n" .
            "  AND pb.boxes_free > 0\n" .
            "  AND pb.instant_price_per_box > 0\n" .
            "  AND p.is_active = 1\n" .
            "ORDER BY pb.purchased_at ASC, pb.id ASC"
        );
        return $this->normalizeOffers($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<int,array<string,mixed>> */
    public function fetchPreorderOffers(): array
    {
        $availableExpr = "(COALESCE(NULLIF(pb.boxes_total, 0), pb.boxes_free + pb.boxes_reserved) - pb.boxes_reserved)";
        $stmt = $this->pdo->query(
            "SELECT pb.id AS purchase_batch_id, pb.product_id, pb.purchased_at AS availability_date,\n" .
            "       {$availableExpr} AS available_boxes, pb.preorder_price_per_box AS price_per_box,\n" .
            "       COALESCE(NULLIF(pb.box_size_snapshot, 0), NULLIF(p.box_size, 0), 1) AS box_size,\n" .
            "       COALESCE(NULLIF(pb.box_unit_snapshot, ''), p.box_unit, p.unit, '') AS box_unit,\n" .
            "       t.name AS product_name, p.variety, p.image_path, 'preorder' AS stock_mode\n" .
            "FROM purchase_batches pb\n" .
            "JOIN products p ON p.id = pb.product_id\n" .
            "JOIN product_types t ON t.id = p.product_type_id\n" .
            "WHERE pb.status = 'planned'\n" .
            "  AND pb.purchased_at IS NOT NULL\n" .
            "  AND {$availableExpr} > 0\n" .
            "  AND pb.preorder_price_per_box > 0\n" .
            "  AND p.is_active = 1\n" .
            "ORDER BY pb.purchased_at ASC, pb.id ASC"
        );
        return $this->normalizeOffers($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<int,array<string,mixed>> */
    public function allocateInstantFifo(int $productId, float $boxes): array
    {
        if ($boxes <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            "SELECT pb.id AS purchase_batch_id, pb.product_id, pb.boxes_free AS available_boxes,\n" .
            "       pb.instant_price_per_box AS price_per_box,\n" .
            "       COALESCE(NULLIF(pb.box_size_snapshot, 0), NULLIF(p.box_size, 0), 1) AS box_size\n" .
            "FROM purchase_batches pb\n" .
            "JOIN products p ON p.id = pb.product_id\n" .
            "WHERE pb.product_id = ? AND pb.status IN ('purchased', 'arrived')\n" .
            "  AND pb.boxes_free > 0 AND pb.instant_price_per_box > 0 AND p.is_active = 1\n" .
            "ORDER BY pb.purchased_at ASC, pb.id ASC"
        );
        $stmt->execute([$productId]);
        $left = $boxes;
        $allocations = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if ($left <= 0.0001) {
                break;
            }
            $take = min($left, (float)$row['available_boxes']);
            if ($take <= 0) {
                continue;
            }
            $allocations[] = [
                'product_id' => (int)$row['product_id'],
                'purchase_batch_id' => (int)$row['purchase_batch_id'],
                'boxes' => $take,
                'box_size' => (float)$row['box_size'],
                'price_per_box' => (float)$row['price_per_box'],
                'stock_mode' => 'instant',
            ];
            $left -= $take;
        }
        if ($left > 0.0001) {
            throw new RuntimeException('Товар закончился, обновите количество');
        }
        return $allocations;
    }

    /** @return array<string,mixed> */
    public function loadPreorderAllocation(int $batchId, float $boxes): array
    {
        $availableExpr = "(COALESCE(NULLIF(pb.boxes_total, 0), pb.boxes_free + pb.boxes_reserved) - pb.boxes_reserved)";
        $stmt = $this->pdo->prepare(
            "SELECT pb.id AS purchase_batch_id, pb.product_id, {$availableExpr} AS available_boxes,\n" .
            "       pb.preorder_price_per_box AS price_per_box, pb.purchased_at,\n" .
            "       COALESCE(NULLIF(pb.box_size_snapshot, 0), NULLIF(p.box_size, 0), 1) AS box_size\n" .
            "FROM purchase_batches pb JOIN products p ON p.id = pb.product_id\n" .
            "WHERE pb.id = ? AND pb.status = 'planned' AND pb.purchased_at IS NOT NULL\n" .
            "  AND pb.preorder_price_per_box > 0 AND p.is_active = 1"
        );
        $stmt->execute([$batchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Дата будущей поставки изменилась');
        }
        if ($boxes > (float)$row['available_boxes']) {
            throw new RuntimeException('На выбранную дату осталось только ' . (int)$row['available_boxes'] . ' ящика');
        }
        return [
            'product_id' => (int)$row['product_id'],
            'purchase_batch_id' => (int)$row['purchase_batch_id'],
            'boxes' => $boxes,
            'box_size' => (float)$row['box_size'],
            'price_per_box' => (float)$row['price_per_box'],
            'stock_mode' => 'preorder',
            'availability_date' => substr((string)$row['purchased_at'], 0, 10),
        ];
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private function normalizeOffers(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['product_id'] = (int)$row['product_id'];
            $row['purchase_batch_id'] = (int)$row['purchase_batch_id'];
            $row['available_boxes'] = (float)$row['available_boxes'];
            $row['price_per_box'] = (float)$row['price_per_box'];
            $row['box_size'] = (float)$row['box_size'];
            $row['availability_date'] = substr((string)($row['availability_date'] ?? ''), 0, 10);
        }
        unset($row);
        return $rows;
    }
}
