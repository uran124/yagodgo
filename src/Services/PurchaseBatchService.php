<?php
namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

class PurchaseBatchService
{
    private PDO $pdo;
    private PricingService $pricingService;

    public function __construct(PDO $pdo, ?PricingService $pricingService = null)
    {
        $this->pdo = $pdo;
        $this->pricingService = $pricingService ?? new PricingService($pdo);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createBatch(array $data): int
    {
        $productId = (int)($data['product_id'] ?? 0);
        $purchasePricePerBox = (float)($data['purchase_price_per_box'] ?? 0);
        $boxesTotal = (float)($data['boxes_total'] ?? 0);
        $boxesFree = (float)($data['boxes_free'] ?? 0);
        $boxesReserved = (float)($data['boxes_reserved'] ?? 0);
        $extraCostPerBox = (float)($data['extra_cost_per_box'] ?? 0);
        $buyerUserId = isset($data['buyer_user_id']) ? (int)$data['buyer_user_id'] : null;
        $comment = isset($data['comment']) ? (string)$data['comment'] : null;

        if ($productId <= 0) {
            throw new RuntimeException('Invalid product_id for purchase batch.');
        }
        if ($boxesTotal <= 0) {
            throw new RuntimeException('boxes_total must be greater than zero.');
        }
        if ($boxesFree < 0 || $boxesReserved < 0 || $purchasePricePerBox < 0 || $extraCostPerBox < 0) {
            throw new RuntimeException('Batch values can not be negative.');
        }
        if (($boxesFree + $boxesReserved) > $boxesTotal) {
            throw new RuntimeException('Allocated boxes exceed boxes_total.');
        }

        $product = $this->loadProduct($productId);
        $boxSize = (float)$product['box_size'];
        $boxUnit = (string)$product['box_unit'];

        $prices = $this->pricingService->calculateFromPurchase($purchasePricePerBox, $boxSize);
        $boxesRemaining = $boxesTotal;
        $costPricePerBox = $purchasePricePerBox + $extraCostPerBox;

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'INSERT INTO purchase_batches (
                    product_id,
                    buyer_user_id,
                    box_size_snapshot,
                    box_unit_snapshot,
                    boxes_total,
                    boxes_reserved,
                    boxes_free,
                    boxes_remaining,
                    purchase_price_per_box,
                    extra_cost_per_box,
                    cost_price_per_box,
                    preorder_margin_percent,
                    instant_margin_percent,
                    discount_markup_fixed,
                    preorder_price_per_box,
                    instant_price_per_box,
                    discount_price_per_box,
                    preorder_unit_price,
                    instant_unit_price,
                    discount_unit_price,
                    status,
                    comment
                ) VALUES (
                    :product_id,
                    :buyer_user_id,
                    :box_size_snapshot,
                    :box_unit_snapshot,
                    :boxes_total,
                    :boxes_reserved,
                    :boxes_free,
                    :boxes_remaining,
                    :purchase_price_per_box,
                    :extra_cost_per_box,
                    :cost_price_per_box,
                    :preorder_margin_percent,
                    :instant_margin_percent,
                    :discount_markup_fixed,
                    :preorder_price_per_box,
                    :instant_price_per_box,
                    :discount_price_per_box,
                    :preorder_unit_price,
                    :instant_unit_price,
                    :discount_unit_price,
                    :status,
                    :comment
                )'
            );

            $settings = $this->pricingService->getSettings();
            $stmt->execute([
                'product_id' => $productId,
                'buyer_user_id' => $buyerUserId,
                'box_size_snapshot' => $boxSize,
                'box_unit_snapshot' => $boxUnit,
                'boxes_total' => $boxesTotal,
                'boxes_reserved' => $boxesReserved,
                'boxes_free' => $boxesFree,
                'boxes_remaining' => $boxesRemaining,
                'purchase_price_per_box' => $purchasePricePerBox,
                'extra_cost_per_box' => $extraCostPerBox,
                'cost_price_per_box' => $costPricePerBox,
                'preorder_margin_percent' => $settings['pricing_preorder_margin_percent'],
                'instant_margin_percent' => $settings['pricing_instant_margin_percent'],
                'discount_markup_fixed' => $settings['pricing_discount_stock_markup_fixed'],
                'preorder_price_per_box' => $prices['preorder_price_per_box'],
                'instant_price_per_box' => $prices['instant_price_per_box'],
                'discount_price_per_box' => $prices['discount_price_per_box'],
                'preorder_unit_price' => $prices['preorder_unit_price'],
                'instant_unit_price' => $prices['instant_unit_price'],
                'discount_unit_price' => $prices['discount_unit_price'],
                'status' => (string)($data['status'] ?? 'purchased'),
                'comment' => $comment,
            ]);

            $batchId = (int)$this->pdo->lastInsertId();

            $this->upsertProductSnapshot($productId, $batchId, $boxesFree, $boxesReserved, $prices);

            $this->pdo->commit();

            return $batchId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function calculatePrices(int $productId, float $purchasePricePerBox, ?array $settings = null): array
    {
        $product = $this->loadProduct($productId);
        $boxSize = (float)$product['box_size'];

        if ($settings !== null) {
            return $this->calculatePricesFromSettings($purchasePricePerBox, $boxSize, $settings);
        }

        return $this->pricingService->calculateFromPurchase($purchasePricePerBox, $boxSize);
    }

    public function markArrived(int $batchId): void
    {
        $this->updateBatchStatus($batchId, 'arrived');
    }

    public function moveToDiscountStock(int $batchId, float $boxes): void
    {
        if ($boxes <= 0) {
            throw new RuntimeException('Discount boxes must be greater than zero.');
        }

        $batch = $this->loadBatch($batchId);
        if ((float)$batch['boxes_free'] < $boxes) {
            throw new RuntimeException('Not enough free boxes to move into discount stock.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE purchase_batches
             SET boxes_free = boxes_free - :boxes,
                 boxes_discount = boxes_discount + :boxes
             WHERE id = :id'
        );
        $stmt->execute(['boxes' => $boxes, 'id' => $batchId]);
    }

    public function writeOff(int $batchId, float $boxes, string $comment): void
    {
        if ($boxes <= 0) {
            throw new RuntimeException('Write-off boxes must be greater than zero.');
        }

        $batch = $this->loadBatch($batchId);
        if ((float)$batch['boxes_remaining'] < $boxes) {
            throw new RuntimeException('Not enough boxes remaining for write-off.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE purchase_batches
             SET boxes_written_off = boxes_written_off + :boxes,
                 boxes_remaining = boxes_remaining - :boxes,
                 comment = :comment
             WHERE id = :id'
        );
        $stmt->execute(['boxes' => $boxes, 'comment' => $comment, 'id' => $batchId]);
    }

    public function closeBatch(int $batchId): void
    {
        $this->updateBatchStatus($batchId, 'closed');
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, float>
     */
    private function calculatePricesFromSettings(float $purchasePricePerBox, float $boxSize, array $settings): array
    {
        $roundingStep = max(1, (int)($settings['pricing_rounding_step'] ?? 10));
        $preorderMargin = (float)($settings['pricing_preorder_margin_percent'] ?? 30);
        $instantMargin = (float)($settings['pricing_instant_margin_percent'] ?? 50);
        $discountMarkup = (float)($settings['pricing_discount_stock_markup_fixed'] ?? 100);

        $preorderPricePerBox = floor(($purchasePricePerBox * (1 + $preorderMargin / 100)) / $roundingStep) * $roundingStep;
        $instantPricePerBox = floor(($purchasePricePerBox * (1 + $instantMargin / 100)) / $roundingStep) * $roundingStep;
        $discountPricePerBox = $purchasePricePerBox + $discountMarkup;

        $safeBoxSize = $boxSize > 0 ? $boxSize : 1.0;

        return [
            'preorder_price_per_box' => $preorderPricePerBox,
            'instant_price_per_box' => $instantPricePerBox,
            'discount_price_per_box' => $discountPricePerBox,
            'preorder_unit_price' => round($preorderPricePerBox / $safeBoxSize, 2),
            'instant_unit_price' => round($instantPricePerBox / $safeBoxSize, 2),
            'discount_unit_price' => round($discountPricePerBox / $safeBoxSize, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadProduct(int $productId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, box_size, box_unit FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new RuntimeException('Product not found for purchase batch.');
        }

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadBatch(int $batchId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM purchase_batches WHERE id = ? LIMIT 1');
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$batch) {
            throw new RuntimeException('Purchase batch not found.');
        }

        return $batch;
    }

    private function updateBatchStatus(int $batchId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE purchase_batches SET status = ? WHERE id = ?');
        $stmt->execute([$status, $batchId]);
    }

    /**
     * @param array<string, float> $prices
     */
    private function upsertProductSnapshot(int $productId, int $batchId, float $freeBoxes, float $reservedBoxes, array $prices): void
    {
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
                 price = :instant_unit_price,
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
            'stock_status' => $stockStatus,
            'product_id' => $productId,
        ]);
    }
}
