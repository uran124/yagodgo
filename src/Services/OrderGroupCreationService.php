<?php
namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

class OrderGroupCreationService
{
    private PDO $pdo;
    private ManualOrderAvailabilityService $availability;
    private OrderDiscountAllocator $allocator;

    public function __construct(PDO $pdo, ?ManualOrderAvailabilityService $availability = null, ?OrderDiscountAllocator $allocator = null)
    {
        $this->pdo = $pdo;
        $this->availability = $availability ?? new ManualOrderAvailabilityService($pdo);
        $this->allocator = $allocator ?? new OrderDiscountAllocator();
    }

    /**
     * @param array<int,array{stock_mode:string,purchase_batch_id:int,boxes:float,delivery_date:string}> $selectedItems
     * @param array<string,mixed> $options
     * @return array{order_group_id:int,order_ids:array<int,int>,orders:array<int,array<string,mixed>>}
     */
    public function createForManualOrder(int $userId, int $addressId, ?int $slotId, array $selectedItems, array $options = []): array
    {
        if ($userId <= 0 || $addressId <= 0) {
            throw new RuntimeException('Заказ не создан. Проверьте клиента и адрес получения');
        }
        $createdBy = isset($options['created_by_user_id']) ? (int)$options['created_by_user_id'] : null;
        $couponCode = trim((string)($options['coupon_code'] ?? ''));
        $deliveryFee = max(0, (int)($options['delivery_fee'] ?? 0));
        $deliveryComment = trim((string)($options['delivery_comment'] ?? ''));
        $useReferralDiscount = !empty($options['referral_discount']);
        $requestedPoints = max(0, (int)($options['points'] ?? 0));
        $availablePoints = max(0, (int)($options['available_points'] ?? 0));

        try {
            $this->pdo->beginTransaction();
            $this->lockSelectedBatches($selectedItems);
            $prepared = $this->prepareItems($selectedItems);
            if ($prepared === []) {
                throw new RuntimeException('Добавьте товары в заказ');
            }

            $groups = $this->groupPreparedItems($prepared);
            $amounts = array_map(static fn(array $group): int => (int)$group['items_total'], $groups);
            $discounts = $useReferralDiscount ? $this->allocator->allocatePercentDiscount($amounts, 10.0) : array_fill(0, count($groups), 0);
            $afterDiscountAmounts = [];
            foreach ($amounts as $idx => $amount) {
                $afterDiscountAmounts[$idx] = max(0, $amount - (int)($discounts[$idx] ?? 0));
            }
            $points = $this->allocator->allocatePoints($afterDiscountAmounts, $requestedPoints, $availablePoints, array_sum($afterDiscountAmounts));
            $groupId = $this->insertOrderGroup($userId, $createdBy, $deliveryComment);
            $orderIds = [];
            $orders = [];
            $chargedDeliveries = [];
            foreach ($groups as $idx => $group) {
                $mode = (string)$group['stock_mode'];
                $shippingKey = $group['delivery_date'] . '|' . $addressId . '|' . (string)$slotId;
                $orderDeliveryFee = isset($chargedDeliveries[$shippingKey]) ? 0 : $deliveryFee;
                $chargedDeliveries[$shippingKey] = true;
                $discount = (int)($discounts[$idx] ?? 0);
                $pointsUsed = (int)($points[$idx] ?? 0);
                $total = max(0, (int)$group['items_total'] - $discount - $pointsUsed) + $orderDeliveryFee;
                $status = $mode === 'preorder' ? 'reserved' : 'new';
                $orderId = $this->insertOrder([
                    'order_group_id' => $groupId,
                    'user_id' => $userId,
                    'address_id' => $addressId,
                    'slot_id' => $slotId,
                    'status' => $status,
                    'total_amount' => $total,
                    'discount_applied' => $discount,
                    'points_used' => $pointsUsed,
                    'points_accrued' => 0,
                    'coupon_code' => $couponCode,
                    'delivery_date' => $group['delivery_date'],
                    'created_by_user_id' => $createdBy,
                    'order_mode' => $mode,
                    'purchase_batch_id' => count($group['items']) === 1 ? (int)$group['items'][0]['purchase_batch_id'] : null,
                    'delivery_fee' => $orderDeliveryFee,
                    'delivery_comment' => $deliveryComment,
                ]);
                $this->insertItemsAndReserve($orderId, $group['items']);
                $this->createSellerPayouts($orderId, $group['items'], $mode);
                if ($pointsUsed > 0) {
                    $this->insertPointsUsage($userId, $orderId, $pointsUsed);
                }
                $orderIds[] = $orderId;
                $orders[] = [
                    'id' => $orderId,
                    'status' => $status,
                    'order_mode' => $mode,
                    'delivery_date' => $group['delivery_date'],
                    'items_total' => (int)$group['items_total'],
                    'discount_applied' => $discount,
                    'points_used' => $pointsUsed,
                    'delivery_fee' => $orderDeliveryFee,
                    'total_amount' => $total,
                ];
            }
            if (array_sum($points) > 0) {
                $this->pdo->prepare('UPDATE users SET points_balance = points_balance - ? WHERE id = ?')->execute([array_sum($points), $userId]);
            }
            $this->pdo->commit();
            return ['order_group_id' => $groupId, 'order_ids' => $orderIds, 'orders' => $orders];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException($e->getMessage() !== '' ? $e->getMessage() : 'Заказ не создан. Попробуйте ещё раз', 0, $e);
        }
    }


    /**
     * @param array<int,array{stock_mode:string,purchase_batch_id:int,boxes:float,delivery_date:string}> $selectedItems
     * @param array<string,mixed> $options
     * @return array{order_group_id:int,order_ids:array<int,int>,orders:array<int,array<string,mixed>>,deleted_cart_item_ids:array<int,int>}
     */
    public function createForClientCheckout(int $userId, array $selectedItems, array $options = []): array
    {
        if ($userId <= 0) {
            throw new RuntimeException('Заказ не создан. Проверьте клиента');
        }

        $couponCode = trim((string)($options['coupon_code'] ?? ''));
        $deliveryGroups = is_array($options['delivery_groups'] ?? null) ? $options['delivery_groups'] : [];
        $cartItemIds = array_values(array_unique(array_filter(array_map('intval', (array)($options['cart_item_ids_to_delete'] ?? [])))));
        $createdBy = isset($options['created_by_user_id']) ? (int)$options['created_by_user_id'] : null;
        $groupComment = trim((string)($options['comment'] ?? 'client checkout'));

        try {
            $this->pdo->beginTransaction();
            $this->lockSelectedBatches($selectedItems);
            $prepared = $this->prepareItems($selectedItems);
            if ($prepared === []) {
                throw new RuntimeException('Добавьте товары в заказ');
            }

            $groups = $this->groupPreparedItems($prepared);
            $amounts = array_map(static fn(array $group): int => (int)$group['items_total'], $groups);
            $discountPercent = max(0.0, (float)($options['discount_percent'] ?? 0));
            $percentDiscounts = $discountPercent > 0 ? $this->allocator->allocatePercentDiscount($amounts, $discountPercent) : array_fill(0, count($groups), 0);

            $afterPercent = [];
            foreach ($amounts as $idx => $amount) {
                $afterPercent[$idx] = max(0, (int)$amount - (int)($percentDiscounts[$idx] ?? 0));
            }
            $couponPointDiscounts = $this->allocator->allocateFixedAmount($afterPercent, max(0, (int)($options['coupon_points'] ?? 0)));
            $afterCouponPoints = [];
            foreach ($afterPercent as $idx => $amount) {
                $afterCouponPoints[$idx] = max(0, (int)$amount - (int)($couponPointDiscounts[$idx] ?? 0));
            }
            $userPoints = $this->allocator->allocatePoints(
                $afterCouponPoints,
                max(0, (int)($options['points'] ?? 0)),
                max(0, (int)($options['available_points'] ?? 0)),
                array_sum($afterCouponPoints)
            );

            $groupId = $this->insertOrderGroup($userId, $createdBy > 0 ? $createdBy : null, $groupComment);
            $orderIds = [];
            $orders = [];
            $chargedDeliveries = [];

            foreach ($groups as $idx => $group) {
                $delivery = is_array($deliveryGroups[$group['group_key']] ?? null) ? $deliveryGroups[$group['group_key']] : [];
                $addressId = (int)($delivery['address_id'] ?? 0);
                if ($addressId <= 0) {
                    throw new RuntimeException('Выберите адрес получения для каждого заказа');
                }
                $slotId = isset($delivery['slot_id']) && $delivery['slot_id'] !== '' ? (int)$delivery['slot_id'] : null;
                $shippingKey = $group['delivery_date'] . '|' . $addressId . '|' . (string)$slotId;
                $deliveryFee = max(0, (int)($delivery['delivery_fee'] ?? 0));
                $orderDeliveryFee = isset($chargedDeliveries[$shippingKey]) ? 0 : $deliveryFee;
                $chargedDeliveries[$shippingKey] = true;

                $mode = (string)$group['stock_mode'];
                $status = $mode === 'preorder' ? 'reserved' : 'new';
                $discount = (int)($percentDiscounts[$idx] ?? 0);
                $couponPointDiscount = (int)($couponPointDiscounts[$idx] ?? 0);
                $pointsUsed = (int)($userPoints[$idx] ?? 0);
                $totalPointsDiscount = $couponPointDiscount + $pointsUsed;
                $total = max(0, (int)$group['items_total'] - $discount - $totalPointsDiscount) + $orderDeliveryFee;

                $orderId = $this->insertOrder([
                    'order_group_id' => $groupId,
                    'user_id' => $userId,
                    'address_id' => $addressId,
                    'slot_id' => $slotId,
                    'status' => $status,
                    'total_amount' => $total,
                    'discount_applied' => $discount,
                    'points_used' => $totalPointsDiscount,
                    'points_accrued' => 0,
                    'coupon_code' => $couponCode,
                    'delivery_date' => $group['delivery_date'],
                    'created_by_user_id' => $createdBy > 0 ? $createdBy : null,
                    'order_mode' => $mode,
                    'purchase_batch_id' => count($group['items']) === 1 ? (int)$group['items'][0]['purchase_batch_id'] : null,
                    'delivery_fee' => $orderDeliveryFee,
                    'delivery_distance_km' => $delivery['distance_km'] ?? null,
                    'delivery_tariff_zone_id' => $delivery['delivery_tariff_zone_id'] ?? null,
                    'delivery_pricing_source' => $delivery['delivery_pricing_source'] ?? null,
                    'delivery_comment' => trim((string)($delivery['delivery_comment'] ?? '')),
                    'reserved_at' => $mode === 'preorder' ? date('Y-m-d H:i:s') : null,
                    'bonuses_allowed' => 1,
                    'coupons_allowed' => 1,
                ]);
                $this->insertItemsAndReserve($orderId, $group['items']);
                $this->createSellerPayouts($orderId, $group['items'], $mode);
                if ($pointsUsed > 0) {
                    $this->insertPointsUsage($userId, $orderId, $pointsUsed);
                }
                $orderIds[] = $orderId;
                $orders[] = [
                    'id' => $orderId,
                    'group_key' => $group['group_key'],
                    'status' => $status,
                    'order_mode' => $mode,
                    'delivery_date' => $group['delivery_date'],
                    'items_total' => (int)$group['items_total'],
                    'discount_applied' => $discount,
                    'coupon_points_used' => $couponPointDiscount,
                    'points_used' => $pointsUsed,
                    'delivery_fee' => $orderDeliveryFee,
                    'total_amount' => $total,
                ];
            }

            $totalUserPoints = array_sum($userPoints);
            if ($totalUserPoints > 0) {
                $this->pdo->prepare('UPDATE users SET points_balance = points_balance - ? WHERE id = ?')->execute([$totalUserPoints, $userId]);
            }
            if ($cartItemIds !== []) {
                $placeholders = implode(',', array_fill(0, count($cartItemIds), '?'));
                $params = array_merge([$userId], $cartItemIds);
                $this->pdo->prepare("DELETE FROM cart_items WHERE user_id = ? AND id IN ($placeholders)")->execute($params);
            }
            $this->pdo->commit();
            return ['order_group_id' => $groupId, 'order_ids' => $orderIds, 'orders' => $orders, 'deleted_cart_item_ids' => $cartItemIds];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException($e->getMessage() !== '' ? $e->getMessage() : 'Заказ не создан. Попробуйте ещё раз', 0, $e);
        }
    }


    /** @param array<int,array<string,mixed>> $selectedItems */
    private function lockSelectedBatches(array $selectedItems): void
    {
        if ((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }
        $batchIds = [];
        foreach ($selectedItems as $item) {
            $batchId = (int)($item['purchase_batch_id'] ?? 0);
            if ($batchId > 0) {
                $batchIds[$batchId] = $batchId;
            }
        }
        if ($batchIds === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM purchase_batches WHERE id IN ($placeholders) FOR UPDATE");
        $stmt->execute(array_values($batchIds));
    }

    /** @param array<int,array{stock_mode:string,purchase_batch_id:int,boxes:float,delivery_date:string}> $selectedItems @return array<int,array<string,mixed>> */
    private function prepareItems(array $selectedItems): array
    {
        $prepared = [];
        $instantByProductAndDate = [];

        foreach ($selectedItems as $input) {
            $mode = (string)($input['stock_mode'] ?? '');
            $batchId = (int)($input['purchase_batch_id'] ?? 0);
            $boxes = (float)($input['boxes'] ?? 0);
            $deliveryDate = substr((string)($input['delivery_date'] ?? ''), 0, 10);
            if ($boxes <= 0) {
                continue;
            }
            if (!in_array($mode, ['instant', 'preorder'], true) || $batchId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deliveryDate)) {
                throw new RuntimeException('Заказ не создан. Проверьте выбранные товары и даты');
            }
            if ($mode === 'preorder') {
                $item = $this->availability->loadPreorderAllocation($batchId, $boxes);
                $item['delivery_date'] = $deliveryDate;
                $prepared[] = $item;
                continue;
            }

            $productId = $this->loadInstantProductId($batchId);
            $key = $productId . '|' . $deliveryDate;
            if (!isset($instantByProductAndDate[$key])) {
                $instantByProductAndDate[$key] = ['product_id' => $productId, 'delivery_date' => $deliveryDate, 'boxes' => 0.0];
            }
            $instantByProductAndDate[$key]['boxes'] += $boxes;
        }

        foreach ($instantByProductAndDate as $request) {
            foreach ($this->availability->allocateInstantFifo((int)$request['product_id'], (float)$request['boxes']) as $allocation) {
                $allocation['delivery_date'] = (string)$request['delivery_date'];
                $prepared[] = $allocation;
            }
        }

        return $prepared;
    }


    /** @param array<int,array<string,mixed>> $prepared @return array<int,array<string,mixed>> */
    private function groupPreparedItems(array $prepared): array
    {
        $groupsByKey = [];
        foreach ($prepared as $item) {
            $key = $item['stock_mode'] . '|' . $item['delivery_date'];
            if (!isset($groupsByKey[$key])) {
                $groupsByKey[$key] = [
                    'group_key' => $key,
                    'stock_mode' => $item['stock_mode'],
                    'delivery_date' => $item['delivery_date'],
                    'items' => [],
                    'items_total' => 0,
                ];
            }
            $groupsByKey[$key]['items'][] = $item;
            $groupsByKey[$key]['items_total'] += (int)round((float)$item['boxes'] * (float)$item['price_per_box']);
        }
        return array_values($groupsByKey);
    }

    private function loadInstantProductId(int $batchId): int
    {
        $stmt = $this->pdo->prepare("SELECT pb.product_id FROM purchase_batches pb JOIN products p ON p.id = pb.product_id WHERE pb.id = ? AND pb.status IN ('purchased', 'arrived') AND pb.instant_price_per_box > 0 AND p.is_active = 1");
        $stmt->execute([$batchId]);
        $productId = (int)($stmt->fetchColumn() ?: 0);
        if ($productId <= 0) {
            throw new RuntimeException('Товар закончился, обновите количество');
        }
        return $productId;
    }

    private function insertOrderGroup(int $userId, ?int $createdBy, string $comment): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO order_groups (user_id, created_by_user_id, comment) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $createdBy && $createdBy > 0 ? $createdBy : null, $comment !== '' ? $comment : null]);
        return (int)$this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    private function insertOrder(array $data): int
    {
        $columns = [];
        $placeholders = [];
        $values = [];
        foreach ($data as $column => $value) {
            if ($this->columnExists('orders', $column)) {
                $columns[] = $column;
                $placeholders[] = '?';
                $values[] = $value;
            }
        }
        if ($this->columnExists('orders', 'created_at')) {
            $columns[] = 'created_at';
            $placeholders[] = $this->nowExpression();
        }
        $stmt = $this->pdo->prepare('INSERT INTO orders (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')');
        $stmt->execute($values);
        return (int)$this->pdo->lastInsertId();
    }

    /** @param array<int,array<string,mixed>> $items */
    private function insertItemsAndReserve(int $orderId, array $items): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price, stock_mode, purchase_batch_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $orchestrator = new OrderStockOrchestrator($this->pdo, new StockService($this->pdo));
        foreach ($items as $item) {
            $boxSize = (float)$item['box_size'];
            $boxes = (float)$item['boxes'];
            $orchestrator->persistOrderItemWithStock(
                $stmt,
                $orderId,
                (int)$item['product_id'],
                [
                    'quantity' => $boxes,
                    'box_size' => $boxSize,
                    'unit_price' => (float)$item['price_per_box'],
                    'purchase_batch_id' => (int)$item['purchase_batch_id'],
                ],
                (string)$item['stock_mode'],
                (string)$item['stock_mode'] === 'preorder'
            );
        }
    }


    /** @param array<int,array<string,mixed>> $items */
    private function createSellerPayouts(int $orderId, array $items, string $mode): void
    {
        if ($mode === 'discount_stock' || !$this->tableExists('seller_payouts')) {
            return;
        }
        $sellerTotals = [];
        $sellerModes = [];
        $productStmt = $this->pdo->prepare('SELECT p.seller_id, u.work_mode FROM products p LEFT JOIN users u ON u.id = p.seller_id WHERE p.id = ?');
        foreach ($items as $item) {
            $productStmt->execute([(int)$item['product_id']]);
            $row = $productStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $sellerId = (int)($row['seller_id'] ?? 0);
            if ($sellerId <= 0) {
                continue;
            }
            $sellerTotals[$sellerId] = ($sellerTotals[$sellerId] ?? 0.0) + ((float)$item['boxes'] * (float)$item['price_per_box']);
            $sellerModes[$sellerId] = (string)($row['work_mode'] ?? 'berrygo_store');
        }
        if ($sellerTotals === []) {
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO seller_payouts (seller_id, order_id, gross_amount, commission_rate, commission_amount, payout_amount) VALUES (?, ?, ?, ?, ?, ?)');
        $economics = new SellerEconomicsService($this->pdo);
        foreach ($sellerTotals as $sellerId => $gross) {
            $record = $economics->payoutRecord((int)$sellerId, (float)$gross, $sellerModes[$sellerId] ?? 'berrygo_store');
            $stmt->execute([
                (int)$sellerId,
                $orderId,
                (float)$gross,
                (float)$record['commission_rate'],
                (float)$record['commission'],
                (float)$record['payout'],
            ]);
        }
    }

    private function insertPointsUsage(int $userId, int $orderId, int $points): void
    {
        if (!$this->tableExists('points_transactions')) {
            return;
        }
        $desc = "Списание {$points} клубничек за заказ #{$orderId}";
        $stmt = $this->pdo->prepare("INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES (?, ?, ?, 'usage', ?, " . $this->nowExpression() . ")");
        $stmt->execute([$userId, $orderId, -$points, $desc]);
    }

    private function columnExists(string $table, string $column): bool
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->query('PRAGMA table_info(' . $table . ')');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
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

    private function nowExpression(): string
    {
        return (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? "datetime('now')" : 'NOW()';
    }
}
