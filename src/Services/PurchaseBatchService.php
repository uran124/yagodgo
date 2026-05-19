<?php
namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

class PurchaseBatchService
{
    private const ALLOWED_BATCH_STATUSES = ['planned', 'purchased', 'arrived'];
    private const ALLOWED_STATUS_TRANSITIONS = [
        'planned' => ['purchased'],
        'purchased' => ['arrived'],
        'arrived' => [],
    ];

    private PDO $pdo;
    private PricingService $pricingService;
    private StockService $stockService;
    private PreorderIntentService $preorderIntentService;

    public function __construct(PDO $pdo, ?PricingService $pricingService = null)
    {
        $this->pdo = $pdo;
        $this->pricingService = $pricingService ?? new PricingService($pdo);
        $this->stockService = new StockService($pdo);
        $this->preorderIntentService = new PreorderIntentService($pdo);
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
        $purchasedAt = trim((string)($data['purchased_at'] ?? ''));
        $status = (string)($data['status'] ?? 'planned');

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
        if (!in_array($status, self::ALLOWED_BATCH_STATUSES, true)) {
            throw new RuntimeException('Unsupported purchase batch status.');
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
                    purchased_at,
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
                    :purchased_at,
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
                'status' => $status,
                'purchased_at' => $purchasedAt !== '' ? $purchasedAt : date('Y-m-d'),
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


    /** @return array<string, float> */
    public function calculateBatchPnl(int $batchId): array
    {
        $batch = $this->loadBatch($batchId);

        $costPerBox = (float)$batch['cost_price_per_box'];
        $instantPricePerBox = (float)$batch['instant_price_per_box'];
        $discountPricePerBox = (float)$batch['discount_price_per_box'];

        $soldBoxes = (float)$batch['boxes_sold'];
        $discountBoxes = (float)$batch['boxes_discount'];
        $writtenOffBoxes = (float)$batch['boxes_written_off'];
        $remainingBoxes = (float)$batch['boxes_remaining'];

        $costSold = $soldBoxes * $costPerBox;
        $costDiscount = $discountBoxes * $costPerBox;
        $costWrittenOff = $writtenOffBoxes * $costPerBox;

        $revenueSold = $soldBoxes * $instantPricePerBox;
        $revenueDiscount = $discountBoxes * $discountPricePerBox;
        $totalRevenue = $revenueSold + $revenueDiscount;

        $totalCostRecognized = $costSold + $costDiscount + $costWrittenOff;
        $grossMargin = $totalRevenue - $totalCostRecognized;

        return [
            'revenue_sold' => $revenueSold,
            'revenue_discount' => $revenueDiscount,
            'revenue_total' => $totalRevenue,
            'cost_sold' => $costSold,
            'cost_discount' => $costDiscount,
            'cost_written_off' => $costWrittenOff,
            'cost_total_recognized' => $totalCostRecognized,
            'gross_margin' => $grossMargin,
            'inventory_value_remaining' => $remainingBoxes * $costPerBox,
        ];
    }

    public function markArrived(int $batchId): void
    {
        $this->updateBatchStatus($batchId, 'arrived');
        $this->processIntentsForArrivedBatch($batchId);
    }

    public function markPurchased(int $batchId): void
    {
        $this->updateBatchStatus($batchId, 'purchased');
        $this->promoteIntentsToOfferSent($batchId);
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
             SET boxes_free = boxes_free - :boxes_decrease,
                 boxes_discount = boxes_discount + :boxes_increase
             WHERE id = :id'
        );
        $stmt->execute(['boxes_decrease' => $boxes, 'boxes_increase' => $boxes, 'id' => $batchId]);
    }

    public function moveAllFreeToDiscountStock(int $batchId): float
    {
        $batch = $this->loadBatch($batchId);
        $boxes = (float)($batch['boxes_free'] ?? 0);
        if ($boxes <= 0) {
            return 0.0;
        }

        $this->moveToDiscountStock($batchId, $boxes);
        return $boxes;
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
             SET boxes_written_off = boxes_written_off + :boxes_written_off,
                 boxes_remaining = boxes_remaining - :boxes_remaining,
                 comment = :comment
             WHERE id = :id'
        );
        $stmt->execute([
            'boxes_written_off' => $boxes,
            'boxes_remaining' => $boxes,
            'comment' => $comment,
            'id' => $batchId,
        ]);
    }

    public function closeBatch(int $batchId): void
    {
        $this->updateBatchStatus($batchId, 'closed');
    }

    public function cancelPendingReservations(int $batchId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT oi.order_id, oi.product_id, oi.purchase_batch_id, oi.boxes, oi.stock_mode
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE oi.purchase_batch_id = ?
               AND oi.stock_mode = 'preorder'
               AND o.status = 'reserved'"
        );
        $stmt->execute([$batchId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($items === []) {
            return 0;
        }

        $affectedOrderIds = [];
        foreach ($items as $item) {
            $orderId = (int)$item['order_id'];
            $productId = (int)$item['product_id'];
            $itemBatchId = (int)$item['purchase_batch_id'];
            $boxes = (float)$item['boxes'];
            if ($boxes <= 0 || $itemBatchId <= 0 || $productId <= 0 || $orderId <= 0) {
                continue;
            }

            $this->stockService->unreserve($productId, $itemBatchId, $boxes, $orderId, 'preorder');
            $affectedOrderIds[$orderId] = true;
        }

        if ($affectedOrderIds === []) {
            return 0;
        }

        $ids = array_keys($affectedOrderIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge(['cancelled'], $ids);
        $upd = $this->pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id IN ({$placeholders}) AND status = 'reserved'");
        $upd->execute($params);

        return count($ids);
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
        if (!in_array($status, self::ALLOWED_BATCH_STATUSES, true)) {
            throw new RuntimeException('Unsupported purchase batch status.');
        }

        $batch = $this->loadBatch($batchId);
        $currentStatus = (string)($batch['status'] ?? '');
        if (!isset(self::ALLOWED_STATUS_TRANSITIONS[$currentStatus])) {
            throw new RuntimeException('Current purchase batch status is unsupported.');
        }

        if ($currentStatus === $status) {
            return;
        }

        if (!in_array($status, self::ALLOWED_STATUS_TRANSITIONS[$currentStatus], true)) {
            throw new RuntimeException('Invalid purchase batch status transition.');
        }

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

    private function promoteIntentsToOfferSent(int $batchId): void
    {
        $batch = $this->loadBatch($batchId);
        $productId = (int)($batch['product_id'] ?? 0);
        if ($productId <= 0) {
            return;
        }

        $offerHours = max(1, (int)($this->pricingService->getSettings()['preorder_offer_expiration_hours'] ?? 48));
        $availableBoxes = max(0.0, (float)($batch['boxes_free'] ?? 0));
        $pricePerBox = max(0.0, (float)($batch['preorder_price_per_box'] ?? 0));
        if ($availableBoxes <= 0 || $pricePerBox <= 0) {
            return;
        }

        $wave = $this->preorderIntentService->allocateOfferWave(
            $productId,
            $availableBoxes,
            $pricePerBox,
            $offerHours
        );

        if (($wave['offered_count'] ?? 0) > 0) {
            $this->pdo->prepare(
                "INSERT INTO notifications (code, description)
                 VALUES (?, ?)"
            )->execute([
                'preorder_offer_sent',
                'По товару #' . $productId . ' отправлены офферы предзаказа: ' . (int)$wave['offered_count'],
            ]);
        }
    }

    private function processIntentsForArrivedBatch(int $batchId): void
    {
        $batch = $this->loadBatch($batchId);
        $productId = (int)($batch['product_id'] ?? 0);
        if ($productId <= 0) {
            return;
        }

        $this->pdo->prepare(
            "UPDATE preorder_intents
             SET status = 'expired', updated_at = NOW()
             WHERE product_id = ?
               AND status = 'offer_sent'
               AND offer_expires_at IS NOT NULL
               AND offer_expires_at < NOW()"
        )->execute([$productId]);

        $readyIdsStmt = $this->pdo->prepare(
            "SELECT id FROM preorder_intents
             WHERE product_id = ?
               AND status = 'confirmed'"
        );
        $readyIdsStmt->execute([$productId]);
        $readyIds = $readyIdsStmt->fetchAll(PDO::FETCH_COLUMN);
        if ($readyIds !== []) {
            $this->pdo->prepare(
                "UPDATE preorder_intents
                 SET status = 'completed',
                     updated_at = NOW()
                 WHERE product_id = ?
                   AND status = 'confirmed'"
            )->execute([$productId]);

            $eventStmt = $this->pdo->prepare(
                "INSERT INTO preorder_intent_events (preorder_intent_id, event_type, from_status, to_status, meta_json, created_at)
                 VALUES (?, 'ready_for_pickup', 'confirmed', 'completed', NULL, NOW())"
            );
            foreach ($readyIds as $intentId) {
                $eventStmt->execute([(int)$intentId]);
            }

            $this->pdo->prepare(
                "INSERT INTO notifications (code, description)
                 VALUES (?, ?)"
            )->execute([
                'preorder_ready_for_pickup',
                'По товару #' . $productId . ' предзаказов готово к выдаче: ' . count($readyIds),
            ]);
        }
    }
}
