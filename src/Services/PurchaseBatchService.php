<?php
namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

class PurchaseBatchService
{
    private const ALLOWED_BATCH_STATUSES = ['planned', 'purchased', 'arrived', 'closed'];
    private const ALLOWED_STATUS_TRANSITIONS = [
        'planned' => ['purchased', 'closed'],
        'purchased' => ['arrived', 'closed'],
        'arrived' => ['closed'],
        'active' => ['closed'],
        'closed' => [],
    ];

    private PDO $pdo;
    private PricingService $pricingService;
    private StockService $stockService;
    private PreorderIntentService $preorderIntentService;
    private LegacyProductProjectionService $legacyProjection;

    public function __construct(PDO $pdo, ?PricingService $pricingService = null)
    {
        $this->pdo = $pdo;
        $this->pricingService = $pricingService ?? new PricingService($pdo);
        $this->stockService = new StockService($pdo);
        $this->preorderIntentService = new PreorderIntentService($pdo);
        $this->legacyProjection = new LegacyProductProjectionService($pdo);
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
        if (!in_array($status, self::ALLOWED_BATCH_STATUSES, true)) {
            throw new RuntimeException('Unsupported purchase batch status.');
        }
        if ($status !== 'planned' && $boxesTotal <= 0) {
            throw new RuntimeException('boxes_total must be greater than zero.');
        }
        if ($boxesTotal < 0 || $boxesFree < 0 || $boxesReserved < 0 || $purchasePricePerBox < 0 || $extraCostPerBox < 0) {
            throw new RuntimeException('Batch values can not be negative.');
        }
        if ($status !== 'planned' && ($boxesFree + $boxesReserved) > $boxesTotal) {
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
                    preorder_discount_percent,
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
                    :preorder_discount_percent,
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
                'preorder_discount_percent' => $settings['ui_preorder_discount_percent'],
                'instant_margin_percent' => $settings['pricing_instant_margin_percent'],
                'discount_markup_fixed' => $settings['pricing_discount_stock_markup_fixed'],
                'preorder_price_per_box' => $prices['preorder_price_per_box'],
                'instant_price_per_box' => $prices['instant_price_per_box'],
                'discount_price_per_box' => $prices['discount_price_per_box'],
                'preorder_unit_price' => $prices['preorder_unit_price'],
                'instant_unit_price' => $prices['instant_unit_price'],
                'discount_unit_price' => $prices['discount_unit_price'],
                'status' => $status,
                'purchased_at' => $purchasedAt !== '' ? $purchasedAt : null,
                'comment' => $comment,
            ]);

            $batchId = (int)$this->pdo->lastInsertId();

            $this->legacyProjection->updateBatchSnapshot($productId, $batchId, $boxesFree, $boxesReserved, $prices);
            if ($status === 'planned') {
                $this->bindWaitingIntentsToBatch($batchId, $productId, $boxesReserved);
            }

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
        $remaining = (float)($batch['boxes_remaining'] ?? 0);
        if ($remaining < $boxes) {
            throw new RuntimeException('Not enough boxes remaining for write-off.');
        }

        $freeBoxes = max(0.0, (float)($batch['boxes_free'] ?? 0));
        $discountBoxes = max(0.0, (float)($batch['boxes_discount'] ?? 0));

        $writeOffFromFree = min($freeBoxes, $boxes);
        $leftToWriteOff = max(0.0, $boxes - $writeOffFromFree);
        $writeOffFromDiscount = min($discountBoxes, $leftToWriteOff);

        $stmt = $this->pdo->prepare(
            'UPDATE purchase_batches
             SET boxes_written_off = boxes_written_off + :boxes_written_off,
                 boxes_free = boxes_free - :boxes_free,
                 boxes_discount = boxes_discount - :boxes_discount,
                 boxes_remaining = boxes_remaining - :boxes_remaining,
                 comment = :comment
             WHERE id = :id'
        );
        $stmt->execute([
            'boxes_written_off' => $boxes,
            'boxes_free' => $writeOffFromFree,
            'boxes_discount' => $writeOffFromDiscount,
            'boxes_remaining' => $boxes,
            'comment' => $comment,
            'id' => $batchId,
        ]);

        $this->legacyProjection->syncAggregatesFromBatches((int)$batch['product_id']);
    }

    public function closeBatch(int $batchId, string $reason = 'Ручное закрытие'): void
    {
        $batch = $this->loadBatch($batchId);
        $this->updateBatchStatus($batchId, 'closed');
        $stmt = $this->pdo->prepare('UPDATE purchase_batches SET closed_at = CURRENT_TIMESTAMP, close_reason = ? WHERE id = ? LIMIT 1');
        $stmt->execute([$reason, $batchId]);
        $this->legacyProjection->syncAggregatesFromBatches((int)$batch['product_id']);
    }

    /**
     * Close batches that no longer have free stock, active reservations or unfinished orders.
     */
    public function autoCloseEligibleBatches(?int $batchId = null): int
    {
        $where = "status IN ('planned','active','purchased','arrived')
              AND COALESCE(boxes_free, 0) <= 0
              AND COALESCE(boxes_discount, 0) <= 0
              AND NOT EXISTS (
                SELECT 1 FROM preorder_intents pi
                WHERE pi.purchase_batch_id = purchase_batches.id
                  AND pi.status IN ('linked_to_batch','awaiting_price_confirmation','confirmed','offer_sent')
              )
              AND NOT EXISTS (
                SELECT 1 FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                WHERE oi.purchase_batch_id = purchase_batches.id
                  AND o.status NOT IN ('completed','cancelled','returned')
              )";
        $params = [];
        if ($batchId !== null && $batchId > 0) {
            $where .= ' AND id = ?';
            $params[] = $batchId;
        }
        $stmt = $this->pdo->prepare("UPDATE purchase_batches SET status = 'closed', closed_at = CURRENT_TIMESTAMP, close_reason = COALESCE(close_reason, 'Автозакрытие: нет активных остатков и обязательств') WHERE {$where}");
        $stmt->execute($params);
        return $stmt->rowCount();
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
        $upd = $this->pdo->prepare("UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id IN ({$placeholders}) AND status = 'reserved'");
        $upd->execute($params);

        return count($ids);
    }


    private function bindWaitingIntentsToBatch(int $batchId, int $productId, float $reservedCapacity): void
    {
        if ($batchId <= 0 || $productId <= 0) {
            return;
        }
        $batch = $this->loadBatch($batchId);
        $coveredDates = $this->coveredDeliveryDatesForBatch($batch);
        $limitByCapacity = $reservedCapacity > 0;
        $remaining = $reservedCapacity;
        $select = $this->pdo->prepare(
            "SELECT id, requested_boxes, desired_delivery_date
             FROM preorder_intents
             WHERE product_id = ?
               AND status IN ('waiting_batch','intent_created')
               AND (purchase_batch_id IS NULL OR purchase_batch_id = 0)
             ORDER BY created_at ASC, id ASC"
        );
        $select->execute([$productId]);
        $items = $select->fetchAll(PDO::FETCH_ASSOC);
        $update = $this->pdo->prepare(
            "UPDATE preorder_intents
             SET purchase_batch_id = ?, status = 'linked_to_batch', updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        foreach ($items as $item) {
            $desiredDate = $this->normalizeDateString($item['desired_delivery_date'] ?? null);
            if ($desiredDate !== null && $coveredDates !== [] && !in_array($desiredDate, $coveredDates, true)) {
                continue;
            }
            $need = (float)($item['requested_boxes'] ?? 0);
            if ($need <= 0) {
                continue;
            }
            if ($limitByCapacity && $need > $remaining) {
                continue;
            }
            $update->execute([$batchId, (int)$item['id']]);
            $this->logPreorderEvent((int)$item['id'], 'linked_to_batch', null, 'linked_to_batch', [
                'purchase_batch_id' => $batchId,
                'desired_delivery_date' => $desiredDate,
                'covered_delivery_dates' => $coveredDates,
            ]);
            if ($limitByCapacity) {
                $remaining -= $need;
            }
        }
    }

    /**
     * @param array<string,mixed> $batch
     * @return array<int,string>
     */
    private function coveredDeliveryDatesForBatch(array $batch): array
    {
        $date = $this->normalizeDateString($batch['purchased_at'] ?? null);
        if ($date === null) {
            return [];
        }
        $start = new \DateTimeImmutable($date);
        return [
            $start->format('Y-m-d'),
            $start->modify('+1 day')->format('Y-m-d'),
            $start->modify('+2 day')->format('Y-m-d'),
        ];
    }

    private function normalizeDateString(mixed $value): ?string
    {
        $raw = trim((string)($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }

    private function logPreorderEvent(int $intentId, string $eventType, ?string $fromStatus, ?string $toStatus, ?array $meta = null): void
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO preorder_intent_events (preorder_intent_id, event_type, from_status, to_status, meta_json, created_at)
                 VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
            )->execute([
                $intentId,
                $eventType,
                $fromStatus,
                $toStatus,
                $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (Throwable) {
            // audit logging is non-blocking
        }
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, float>
     */
    private function calculatePricesFromSettings(float $purchasePricePerBox, float $boxSize, array $settings): array
    {
        $roundingStep = max(1, (int)($settings['pricing_rounding_step'] ?? 10));
        $instantMargin = (float)($settings['pricing_instant_margin_percent'] ?? 50);
        $preorderDiscount = max(0.0, min(99.0, (float)($settings['ui_preorder_discount_percent'] ?? 10)));
        $discountMarkup = (float)($settings['pricing_discount_stock_markup_fixed'] ?? 100);

        $instantPricePerBox = floor(($purchasePricePerBox * (1 + $instantMargin / 100)) / $roundingStep) * $roundingStep;
        $preorderPricePerBox = floor(($instantPricePerBox * (1 - $preorderDiscount / 100)) / $roundingStep) * $roundingStep;
        $discountPricePerBox = $purchasePricePerBox + $discountMarkup;

        $safeBoxSize = $boxSize > 0 ? $boxSize : 1.0;

        return [
            'preorder_price_per_box' => $preorderPricePerBox,
            'instant_price_per_box' => $instantPricePerBox,
            'discount_price_per_box' => $discountPricePerBox,
            'preorder_unit_price' => round($preorderPricePerBox / $safeBoxSize, 0),
            'instant_unit_price' => round($instantPricePerBox / $safeBoxSize, 0),
            'discount_unit_price' => round($discountPricePerBox / $safeBoxSize, 0),
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

    private function promoteIntentsToOfferSent(int $batchId): void
    {
        $batch = $this->loadBatch($batchId);
        $productId = (int)($batch['product_id'] ?? 0);
        if ($productId <= 0) {
            return;
        }

        $offerHours = (int)(get_setting('preorder_offer_expiration_hours', '48') ?? '48');
        $offerHours = max(1, $offerHours);
        $expiresAt = (new \DateTimeImmutable('now'))->modify('+' . $offerHours . ' hours')->format('Y-m-d H:i:s');
        $pricePerBox = max(0.0, (float)($batch['preorder_price_per_box'] ?? 0));
        if ($pricePerBox <= 0) {
            return;
        }

        // FIFO: first booking gets first right to confirm final price.
        $capacity = max(0.0, (float)($batch['boxes_total'] ?? 0) - (float)($batch['boxes_free'] ?? 0));
        if ($capacity <= 0) {
            $capacity = max(0.0, (float)($batch['boxes_total'] ?? 0));
        }

        $select = $this->pdo->prepare(
            "SELECT id, requested_boxes, status, desired_delivery_date, expected_price_per_box, discount_percent_snapshot
             FROM preorder_intents
             WHERE product_id = ?
               AND (purchase_batch_id = ? OR purchase_batch_id IS NULL OR purchase_batch_id = 0)
               AND status IN ('linked_to_batch','waiting_batch','intent_created')
             ORDER BY created_at ASC, id ASC"
        );
        $select->execute([$productId, $batchId]);
        $items = $select->fetchAll(PDO::FETCH_ASSOC);
        $coveredDates = $this->coveredDeliveryDatesForBatch($batch);

        $offered = 0;
        $allocated = 0.0;
        $update = $this->pdo->prepare(
            "UPDATE preorder_intents
             SET purchase_batch_id = ?, status = 'awaiting_price_confirmation',
                 offered_price_per_box = ?, offer_expires_at = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        foreach ($items as $item) {
            $desiredDate = $this->normalizeDateString($item['desired_delivery_date'] ?? null);
            if ($desiredDate !== null && $coveredDates !== [] && !in_array($desiredDate, $coveredDates, true)) {
                continue;
            }
            $need = (float)($item['requested_boxes'] ?? 0);
            if ($need <= 0 || ($allocated + $need) > $capacity) {
                continue;
            }
            $from = (string)($item['status'] ?? 'linked_to_batch');
            $expectedPrice = isset($item['expected_price_per_box']) ? (float)$item['expected_price_per_box'] : 0.0;
            $priceDelta = $expectedPrice > 0 ? round($pricePerBox - $expectedPrice, 2) : null;
            $update->execute([$batchId, $pricePerBox, $expiresAt, (int)$item['id']]);
            $this->logPreorderEvent((int)$item['id'], 'price_confirmation_requested', $from, 'awaiting_price_confirmation', [
                'purchase_batch_id' => $batchId,
                'expected_price_per_box' => $expectedPrice > 0 ? $expectedPrice : null,
                'offered_price_per_box' => $pricePerBox,
                'price_delta_per_box' => $priceDelta,
                'discount_percent_snapshot' => isset($item['discount_percent_snapshot']) ? (float)$item['discount_percent_snapshot'] : null,
                'offer_expires_at' => $expiresAt,
                'desired_delivery_date' => $desiredDate,
                'covered_delivery_dates' => $coveredDates,
            ]);
            $allocated += $need;
            $offered++;
        }

        if ($offered > 0) {
            $this->pdo->prepare(
                "INSERT INTO notifications (code, description)
                 VALUES (?, ?)"
            )->execute([
                'preorder_price_confirmation_requested',
                'Поставка пришла. Предзаказам по товару #' . $productId . ' отправлено подтверждение цены: ' . $offered,
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
             SET status = 'expired', updated_at = CURRENT_TIMESTAMP
             WHERE product_id = ?
               AND status = 'offer_sent'
               AND offer_expires_at IS NOT NULL
               AND offer_expires_at < CURRENT_TIMESTAMP"
        )->execute([$productId]);

        // Arrived means the batch is physically ready for pickup/delivery.
        // Do not auto-complete reservations here: confirmed reservations are converted to cart items by the client.
        $this->pdo->prepare(
            "INSERT INTO notifications (code, description)
             VALUES (?, ?)"
        )->execute([
            'batch_ready_for_pickup',
            'Партия #' . $batchId . ' по товару #' . $productId . ' готова к выдаче.',
        ]);
    }
}
