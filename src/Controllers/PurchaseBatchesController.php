<?php
namespace App\Controllers;

use App\Services\PurchaseBatchService;
use App\Services\PreorderIntentService;
use App\Services\LegacyProductProjectionService;
use App\Services\PricingService;
use App\Services\StockDeficitService;
use PDO;
use RuntimeException;

class PurchaseBatchesController
{
    private PDO $pdo;
    private PurchaseBatchService $purchaseBatchService;
    private PreorderIntentService $preorderIntentService;
    private LegacyProductProjectionService $legacyProjectionService;
    private PricingService $pricingService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->purchaseBatchService = new PurchaseBatchService($pdo);
        $this->preorderIntentService = new PreorderIntentService($pdo);
        $this->legacyProjectionService = new LegacyProductProjectionService($pdo);
        $this->pricingService = new PricingService($pdo);
    }

    public function index(): void
    {
        $statusFilter = trim((string)($_GET['status'] ?? ''));
        $buyerFilter = (int)($_GET['buyer_id'] ?? 0);

        $sql =
            'SELECT pb.*, p.variety, t.name AS product_name, u.name AS buyer_name,
'
          . '       TIMESTAMPDIFF(DAY, pb.purchased_at, NOW()) AS age_days,
'
          . "       CASE WHEN pb.status = 'closed' THEN 1 ELSE 0 END AS is_closed,\n"
          . "       CASE\n"
          . "         WHEN pb.status = 'planned' THEN CASE WHEN (COALESCE(NULLIF(pb.boxes_total, 0), pb.boxes_free + pb.boxes_reserved) - pb.boxes_reserved) > 0 THEN 1 ELSE 0 END\n"
          . "         WHEN pb.status IN ('active','purchased','arrived') THEN CASE WHEN (COALESCE(pb.boxes_free, 0) + COALESCE(pb.boxes_discount, 0)) > 0 THEN 1 ELSE 0 END\n"
          . "         ELSE 0\n"
          . "       END AS is_active_for_list,\n"
          . '       photo.image_path AS preview_photo,
'
          . "       COALESCE(sm_writeoff.comments_count, 0) AS writeoff_comments_count,\n"
          . "       COALESCE(sm_unreserve.comments_count, 0) AS cancel_reserve_comments_count,\n"
          . "       0 AS discount_comments_count\n"
          . 'FROM purchase_batches pb
'
          . 'JOIN products p ON p.id = pb.product_id
'
          . 'JOIN product_types t ON t.id = p.product_type_id
'
          . 'LEFT JOIN users u ON u.id = pb.buyer_user_id
'
          . 'LEFT JOIN (
'
          . '  SELECT purchase_batch_id, MAX(id) AS latest_photo_id
'
          . '  FROM purchase_batch_photos
'
          . '  GROUP BY purchase_batch_id
'
          . ') latest_photo ON latest_photo.purchase_batch_id = pb.id
'
          . 'LEFT JOIN purchase_batch_photos photo ON photo.id = latest_photo.latest_photo_id
'
          . "LEFT JOIN (
"
          . "  SELECT purchase_batch_id, COUNT(*) AS comments_count
"
          . "  FROM stock_movements
"
          . "  WHERE movement_type = 'writeoff' AND COALESCE(comment, '') <> ''
"
          . "  GROUP BY purchase_batch_id
"
          . ") sm_writeoff ON sm_writeoff.purchase_batch_id = pb.id\n"
          . "LEFT JOIN (
"
          . "  SELECT purchase_batch_id, COUNT(*) AS comments_count
"
          . "  FROM stock_movements
"
          . "  WHERE movement_type = 'unreserve'
"
          . "  GROUP BY purchase_batch_id
"
          . ") sm_unreserve ON sm_unreserve.purchase_batch_id = pb.id";

        $conditions = [];
        $params = [];
        if ($statusFilter !== '') {
            $conditions[] = 'pb.status = ?';
            $params[] = $statusFilter;
        }
        if ($buyerFilter > 0) {
            $conditions[] = 'pb.buyer_user_id = ?';
            $params[] = $buyerFilter;
        }
        if ($conditions) {
            $sql .= '
WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= '
ORDER BY is_closed ASC, pb.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $buyers = $this->pdo->query("SELECT id, name FROM users WHERE role = 'buyer' OR role = 'admin' OR role = 'manager' ORDER BY name")
            ->fetchAll(PDO::FETCH_ASSOC);

        $summaryStmt = $this->pdo->query(
            'SELECT
'
          . '  COUNT(*) AS total_batches,
'
          . '  COALESCE(SUM(CASE WHEN status IN ("planned","active","arrived","purchased") THEN boxes_remaining ELSE 0 END), 0) AS remaining_boxes,
'
          . '  COALESCE(SUM(boxes_written_off), 0) AS written_off_boxes,
'
          . '  COALESCE(AVG(TIMESTAMPDIFF(DAY, purchased_at, NOW())), 0) AS avg_age_days
'
          . 'FROM purchase_batches'
        );
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stockDeficitSummary = (new StockDeficitService($this->pdo))->getSummary();

        $preorderDemandStmt = $this->pdo->query(
            "SELECT
"
          . "  p.id AS product_id,
"
          . "  p.variety,
"
          . "  t.name AS product_name,
"
          . "  COALESCE(SUM(pi.requested_boxes), 0) AS requested_boxes,
"
          . "  COUNT(*) AS intents_count,
"
          . "  COALESCE(SUM(CASE WHEN pi.status IN ('confirmed','awaiting_price_confirmation','offer_sent') THEN pi.requested_boxes ELSE 0 END), 0) AS confirmed_boxes
"
          . "FROM preorder_intents pi
"
          . "JOIN products p ON p.id = pi.product_id
"
          . "JOIN product_types t ON t.id = p.product_type_id
"
          . "WHERE pi.status IN ('waiting_batch','linked_to_batch','awaiting_price_confirmation','offer_sent','confirmed','intent_created')
"
          . "GROUP BY p.id, p.variety, t.name
"
          . "ORDER BY requested_boxes DESC, t.name, p.variety"
        );
        $preorderDemand = $preorderDemandStmt->fetchAll(PDO::FETCH_ASSOC);
        $preorderDemandTotals = [
            'requested_boxes' => 0.0,
            'confirmed_boxes' => 0.0,
            'intents_count' => 0,
            'products_count' => count($preorderDemand),
        ];
        foreach ($preorderDemand as $row) {
            $preorderDemandTotals['requested_boxes'] += (float)($row['requested_boxes'] ?? 0);
            $preorderDemandTotals['confirmed_boxes'] += (float)($row['confirmed_boxes'] ?? 0);
            $preorderDemandTotals['intents_count'] += (int)($row['intents_count'] ?? 0);
        }

        viewAdmin('purchases/index', [
            'pageTitle' => 'Закупки',
            'batches' => $batches,
            'buyers' => $buyers,
            'filters' => [
                'status' => $statusFilter,
                'buyer_id' => $buyerFilter,
            ],
            'summary' => $summary,
            'preorderDemand' => $preorderDemand,
            'preorderDemandTotals' => $preorderDemandTotals,
            'stockDeficitRows' => $stockDeficitSummary['rows'],
            'stockDeficitTotals' => $stockDeficitSummary,
            'basePath' => $this->basePath(),
            'flash' => $this->pullFlash(),
        ]);
    }

    public function create(): void
    {
        $products = $this->pdo->query(
            'SELECT p.id, p.variety, p.box_size, p.box_unit, t.name AS product_name
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             WHERE p.is_active = 1
             ORDER BY t.name, p.variety'
        )->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('purchases/create', [
            'pageTitle' => 'Новая закупка',
            'products' => $products,
            'basePath' => $this->basePath(),
            'flash' => $this->pullFlash(),
        ]);
    }

    public function show(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT pb.*, p.variety, t.name AS product_name, u.name AS buyer_name
             FROM purchase_batches pb
             JOIN products p ON p.id = pb.product_id
             JOIN product_types t ON t.id = p.product_type_id
             LEFT JOIN users u ON u.id = pb.buyer_user_id
             WHERE pb.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$batch) {
            $this->setFlash('error', 'Партия не найдена.');
            header('Location: ' . $this->basePath() . '/purchases');
            exit;
        }

        $movementsStmt = $this->pdo->prepare(
            'SELECT sm.*, u.name AS user_name
             FROM stock_movements sm
             LEFT JOIN users u ON u.id = sm.user_id
             WHERE sm.purchase_batch_id = ?
             ORDER BY sm.id DESC'
        );
        $movementsStmt->execute([$id]);

        $photosStmt = $this->pdo->prepare(
            'SELECT * FROM purchase_batch_photos WHERE purchase_batch_id = ? ORDER BY id DESC'
        );
        $photosStmt->execute([$id]);

        $pnl = $this->purchaseBatchService->calculateBatchPnl($id);

        $reservedStmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(requested_boxes), 0)
             FROM preorder_intents
             WHERE purchase_batch_id = ?
               AND status IN ('linked_to_batch','awaiting_price_confirmation','offer_sent','confirmed')"
        );
        $reservedStmt->execute([$id]);
        $reservedIntentBoxes = (float)$reservedStmt->fetchColumn();

        $products = $this->pdo->query(
            'SELECT p.id, p.variety, p.box_size, p.box_unit, t.name AS product_name
             FROM products p
             JOIN product_types t ON t.id = p.product_type_id
             WHERE p.is_active = 1
             ORDER BY t.name, p.variety'
        )->fetchAll(PDO::FETCH_ASSOC);

        viewAdmin('purchases/show', [
            'pageTitle' => 'Партия #' . $id,
            'basePath' => $this->basePath(),
            'batch' => $batch,
            'movements' => $movementsStmt->fetchAll(PDO::FETCH_ASSOC),
            'photos' => $photosStmt->fetchAll(PDO::FETCH_ASSOC),
            'pnl' => $pnl,
            'products' => $products,
            'reservedIntentBoxes' => $reservedIntentBoxes,
            'flash' => $this->pullFlash(),
        ]);
    }


    public function exportPnlCsv(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT pb.id, pb.purchased_at, pb.status, p.variety, t.name AS product_name
             FROM purchase_batches pb
             JOIN products p ON p.id = pb.product_id
             JOIN product_types t ON t.id = p.product_type_id
             WHERE pb.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$batch) {
            http_response_code(404);
            echo 'Batch not found';
            return;
        }

        $pnl = $this->purchaseBatchService->calculateBatchPnl($id);

        $fileName = 'purchase_batch_' . $id . '_pnl.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            return;
        }

        fwrite($out, "ï»¿");

        fputcsv($out, ['batch_id', 'product', 'purchased_at', 'status', 'metric', 'value']);
        $productTitle = trim((string)$batch['product_name'] . ' ' . (string)$batch['variety']);

        foreach ($pnl as $metric => $value) {
            fputcsv($out, [
                (int)$batch['id'],
                $productTitle,
                (string)$batch['purchased_at'],
                (string)$batch['status'],
                (string)$metric,
                (float)$value,
            ]);
        }

        fclose($out);
    }

    public function store(): void
    {
        $this->ensureCsrfOrRedirect();

        $status = 'planned';
        $productId = (int)($_POST['product_id'] ?? 0);
        $purchasePrice = (float)($_POST['purchase_price_per_box'] ?? 0);
        $boxSize = $this->getProductBoxSize($productId);
        $prices = $this->pricingService->calculateFromPurchase($purchasePrice, $boxSize);
        $instantPrice = (float)$prices['instant_price_per_box'];
        $preorderPrice = (float)$prices['preorder_price_per_box'];
        $plannedSupplyDate = $this->nullablePostedDate('planned_supply_date');
        $coveredDates = $this->coveredDeliveryDatesForDate($plannedSupplyDate);
        $preorderCountSql = "SELECT COALESCE(SUM(requested_boxes), 0)
             FROM preorder_intents
             WHERE product_id = ? AND status IN ('waiting_batch','linked_to_batch','awaiting_price_confirmation','offer_sent','confirmed','intent_created')";
        $preorderCountParams = [$productId];
        if ($coveredDates !== []) {
            $preorderCountSql .= " AND (desired_delivery_date IS NULL OR DATE(desired_delivery_date) IN (" . implode(',', array_fill(0, count($coveredDates), '?')) . "))";
            $preorderCountParams = array_merge($preorderCountParams, $coveredDates);
        }
        $preorderCountStmt = $this->pdo->prepare($preorderCountSql);
        $preorderCountStmt->execute($preorderCountParams);
        $preorderBoxes = (float)$preorderCountStmt->fetchColumn();

        $requestedBoxesTotal = max(0.0, (float)($_POST['boxes_total'] ?? 0));

        $requestedBoxesReserved = (float)($_POST['boxes_reserved'] ?? $preorderBoxes);
        if ($requestedBoxesReserved < 0) {
            $requestedBoxesReserved = 0.0;
        }

        $requestedBoxesFree = max($requestedBoxesTotal - $requestedBoxesReserved, 0.0);

        $payload = [
            'product_id' => $productId,
            'buyer_user_id' => (int)($_SESSION['user_id'] ?? 0),
            'boxes_total' => $requestedBoxesTotal,
            'boxes_reserved' => $requestedBoxesReserved,
            'boxes_free' => $requestedBoxesFree,
            'purchase_price_per_box' => $purchasePrice,
            'extra_cost_per_box' => (float)($_POST['extra_cost_per_box'] ?? 0),
            'instant_price_per_box' => $instantPrice,
            'preorder_price_per_box' => $preorderPrice,
            'instant_unit_price' => (float)$prices['instant_unit_price'],
            'preorder_unit_price' => (float)$prices['preorder_unit_price'],
            'status' => $status,
            'purchased_at' => $plannedSupplyDate,
            'comment' => trim((string)($_POST['comment'] ?? '')),
        ];

        try {
            $batchId = $this->purchaseBatchService->createBatch($payload);
            // compatibility-only projection for legacy admin/reporting surfaces
            $this->legacyProjectionService->updateBatchSnapshot($productId, $batchId, $requestedBoxesFree, $requestedBoxesReserved, [
                'preorder_price_per_box' => $preorderPrice,
                'instant_price_per_box' => $instantPrice,
                'discount_price_per_box' => $instantPrice,
                'preorder_unit_price' => $preorderPrice,
                'instant_unit_price' => $instantPrice,
                'discount_unit_price' => $instantPrice,
            ]);

            $this->storeBatchPhotos($batchId);
            $this->setFlash('success', 'Закупка успешно создана.');
        } catch (RuntimeException $e) {
            $this->setFlash('error', $e->getMessage());
            header('Location: ' . $this->basePath() . '/purchases/create');
            exit;
        }

        header('Location: ' . $this->basePath() . '/purchases');
        exit;
    }

    public function markArrived(): void
    {
        $this->ensureCsrfOrRedirect();
        $batchId = (int)($_POST['batch_id'] ?? 0);
        if ($batchId > 0) {
            try {
                $this->ensureBatchPhotosBeforeArrived($batchId);
                $this->purchaseBatchService->markArrived($batchId);
                $movedBoxes = 0.0;
                if (isset($_POST['move_leftovers_to_discount'])) {
                    $movedBoxes = $this->purchaseBatchService->moveAllFreeToDiscountStock($batchId);
                }
                $suffix = $movedBoxes > 0 ? ' Остаток в уценку: ' . number_format($movedBoxes, 2, '.', ' ') . ' ящ.' : '';
                $this->setFlash('success', 'Партия отмечена как готовая к выдаче.' . $suffix);
            } catch (RuntimeException $e) {
                $this->setFlash('error', $e->getMessage());
            }
        }
        header('Location: ' . $this->basePath() . '/purchases');
        exit;
    }

    private function ensureBatchPhotosBeforeArrived(int $batchId): void
    {
        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM purchase_batch_photos WHERE purchase_batch_id = ?');
        $countStmt->execute([$batchId]);
        $count = (int)$countStmt->fetchColumn();
        if ($count >= 2) {
            return;
        }

        $batchStmt = $this->pdo->prepare(
            'SELECT pb.product_id, p.image_path
             FROM purchase_batches pb
             JOIN products p ON p.id = pb.product_id
             WHERE pb.id = ?
             LIMIT 1'
        );
        $batchStmt->execute([$batchId]);
        $row = $batchStmt->fetch(PDO::FETCH_ASSOC);
        $fallbackImage = trim((string)($row['image_path'] ?? ''));
        if ($fallbackImage === '') {
            return;
        }

        $insertStmt = $this->pdo->prepare(
            'INSERT INTO purchase_batch_photos (purchase_batch_id, image_path) VALUES (?, ?)'
        );
        while ($count < 2) {
            $insertStmt->execute([$batchId, $fallbackImage]);
            $count++;
        }
    }

    public function markPurchased(): void
    {
        $this->ensureCsrfOrRedirect();
        $batchId = (int)($_POST['batch_id'] ?? 0);
        if ($batchId <= 0) {
            header('Location: ' . $this->basePath() . '/purchases');
            exit;
        }

        try {
            $batchStmt = $this->pdo->prepare('SELECT * FROM purchase_batches WHERE id = ? LIMIT 1');
            $batchStmt->execute([$batchId]);
            $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);
            if (!$batch) {
                throw new RuntimeException('Закупка не найдена.');
            }
            if ((string)($batch['status'] ?? '') !== 'planned') {
                throw new RuntimeException('В статус Выкуплена можно перевести только запланированную закупку.');
            }

            $productId = (int)($batch['product_id'] ?? 0);
            $purchasePrice = (float)($_POST['purchase_price_per_box'] ?? $batch['purchase_price_per_box'] ?? 0);
            $boxesTotal = max(0.0, (float)($_POST['boxes_total'] ?? $batch['boxes_total'] ?? 0));
            if ($boxesTotal <= 0) {
                throw new RuntimeException('Укажите количество выкупленных ящиков.');
            }

            $boxSize = $this->getProductBoxSize($productId);
            $prices = $this->pricingService->calculateFromPurchase($purchasePrice, $boxSize);
            $settings = $this->pricingService->getSettings();
            $instantPosted = (float)($_POST['instant_price_per_box'] ?? 0);
            $preorderPosted = (float)($_POST['preorder_price_per_box'] ?? 0);
            $instantPrice = $instantPosted > 0 ? $instantPosted : (float)$prices['instant_price_per_box'];
            $preorderPrice = $preorderPosted > 0 ? $preorderPosted : (float)$prices['preorder_price_per_box'];

            $reservedStmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(requested_boxes), 0)
                 FROM preorder_intents
                 WHERE purchase_batch_id = ?
                   AND status IN ('linked_to_batch','awaiting_price_confirmation','offer_sent','confirmed')"
            );
            $reservedStmt->execute([$batchId]);
            $reservedRequested = max(0.0, (float)$reservedStmt->fetchColumn());
            $reservedAllocated = min($reservedRequested, $boxesTotal);
            $boxesFree = max(0.0, $boxesTotal - $reservedAllocated);

            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                'UPDATE purchase_batches
                 SET boxes_total = :boxes_total,
                     boxes_reserved = :boxes_reserved,
                     boxes_free = :boxes_free,
                     boxes_remaining = :boxes_remaining,
                     purchase_price_per_box = :purchase_price_per_box,
                     preorder_margin_percent = :preorder_margin_percent,
                     preorder_discount_percent = :preorder_discount_percent,
                     instant_margin_percent = :instant_margin_percent,
                     instant_price_per_box = :instant_price_per_box,
                     preorder_price_per_box = :preorder_price_per_box,
                     instant_unit_price = :instant_unit_price,
                     preorder_unit_price = :preorder_unit_price,
                     purchased_at = :purchased_at,
                     status = "purchased",
                     comment = :comment
                 WHERE id = :id
                   AND status = "planned"
                 LIMIT 1'
            );
            $stmt->execute([
                'id' => $batchId,
                'boxes_total' => $boxesTotal,
                'boxes_reserved' => $reservedAllocated,
                'boxes_free' => $boxesFree,
                'boxes_remaining' => $boxesTotal,
                'purchase_price_per_box' => $purchasePrice,
                'preorder_margin_percent' => (float)$settings['pricing_preorder_margin_percent'],
                'preorder_discount_percent' => (float)$settings['ui_preorder_discount_percent'],
                'instant_margin_percent' => (float)$settings['pricing_instant_margin_percent'],
                'instant_price_per_box' => $instantPrice,
                'preorder_price_per_box' => $preorderPrice,
                'instant_unit_price' => $instantPrice,
                'preorder_unit_price' => $preorderPrice,
                'purchased_at' => $this->resolvePurchasedAtForPurchase($batch),
                'comment' => trim((string)($_POST['comment'] ?? $batch['comment'] ?? '')),
            ]);
            if ($stmt->rowCount() < 1) {
                throw new RuntimeException('Не удалось перевести закупку в статус Выкуплена.');
            }
            $this->pdo->commit();

            $this->storeBatchPhotos($batchId);
            $this->purchaseBatchService->markPurchased($batchId);
            $this->legacyProjectionService->updateBatchSnapshot($productId, $batchId, $boxesFree, $reservedAllocated, [
                'preorder_price_per_box' => $preorderPrice,
                'instant_price_per_box' => $instantPrice,
                'discount_price_per_box' => $instantPrice,
                'preorder_unit_price' => $preorderPrice,
                'instant_unit_price' => $instantPrice,
                'discount_unit_price' => $instantPrice,
            ]);

            $msg = 'Партия отмечена как выкупленная. Свободно: ' . number_format($boxesFree, 0, '.', ' ') . ' ящ.';
            if ($reservedRequested > $reservedAllocated) {
                $msg .= ' Броней больше, чем выкуплено: часть останется в ожидании следующей закупки.';
            }
            $this->setFlash('success', $msg);
        } catch (RuntimeException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->setFlash('error', $e->getMessage());
        }

        header('Location: ' . $this->basePath() . '/purchases/' . $batchId);
        exit;
    }

    public function moveToDiscount(): void
    {
        $this->ensureCsrfOrRedirect();
        if (!in_array((string)($_SESSION['role'] ?? ''), ['admin', 'manager'], true)) {
            $this->setFlash('error', 'Операция доступна только администратору или менеджеру.');
            header('Location: ' . $this->basePath() . '/purchases');
            exit;
        }
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $boxes = (float)($_POST['boxes'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($batchId > 0 && $boxes > 0) {
            if ($reason === '') { $reason = 'Без причины'; }
            $this->purchaseBatchService->moveToDiscountStock($batchId, $boxes);
            $this->purchaseBatchService->autoCloseEligibleBatches($batchId);
            $this->setFlash('success', 'Часть партии переведена в выгодный остаток.');
        }
        header('Location: ' . $this->basePath() . '/purchases');
        exit;
    }

    public function cancelReservations(): void
    {
        $this->ensureCsrfOrRedirect();
        $batchId = (int)($_POST['batch_id'] ?? 0);
        if ($batchId > 0) {
            try {
                $count = $this->purchaseBatchService->cancelPendingReservations($batchId);
                $this->setFlash('success', 'Отменено броней: ' . $count);
            } catch (RuntimeException $e) {
                $this->setFlash('error', $e->getMessage());
            }
        }
        header('Location: ' . $this->basePath() . '/purchases');
        exit;
    }

    public function reservationsList(): void
    {
        $batchId = (int)($_GET['batch_id'] ?? 0);
        header('Content-Type: application/json; charset=utf-8');
        if ($batchId <= 0) {
            echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT o.id AS order_id,
                        COALESCE(u.name, 'Без имени') AS customer_name,
                        COALESCE(u.phone, '') AS customer_phone,
                        SUM(oi.boxes) AS reserved_qty
                 FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id
                 LEFT JOIN users u ON u.id = o.user_id
                 WHERE oi.purchase_batch_id = :batch_id
                   AND o.status = 'reserved'
                 GROUP BY o.id, u.name, u.phone
                 ORDER BY o.id DESC"
            );
            $stmt->execute(['batch_id' => $batchId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('reservationsList failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'items' => [],
                'error' => 'Не удалось получить список броней.'
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }


    public function preorderIntentsByProduct(): void
    {
        $productId = (int)($_GET['product_id'] ?? 0);
        $batchId = (int)($_GET['batch_id'] ?? 0);
        $matchingOnly = (int)($_GET['matching_only'] ?? 0) === 1;
        $coveredDates = $this->coveredDeliveryDatesForDate($_GET['planned_supply_date'] ?? null);

        if ($batchId > 0) {
            $batchStmt = $this->pdo->prepare('SELECT id, product_id, purchased_at FROM purchase_batches WHERE id = ? LIMIT 1');
            $batchStmt->execute([$batchId]);
            $batch = $batchStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($batch) {
                $productId = (int)($batch['product_id'] ?? $productId);
                $coveredDates = $this->coveredDeliveryDatesForDate($batch['purchased_at'] ?? null);
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        if ($productId <= 0) {
            echo json_encode(['items' => [], 'covered_dates' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $sql = "SELECT pi.id, pi.status, pi.requested_boxes, pi.desired_delivery_date, pi.purchase_batch_id,
                       COALESCE(u.name,'Без имени') AS customer_name, COALESCE(u.phone,'') AS customer_phone
                FROM preorder_intents pi
                JOIN users u ON u.id = pi.user_id
                WHERE pi.product_id = ?
                  AND pi.status IN ('waiting_batch','linked_to_batch','awaiting_price_confirmation','offer_sent','confirmed','intent_created')";
        $params = [$productId];
        if ($matchingOnly && $coveredDates !== []) {
            $sql .= " AND (pi.desired_delivery_date IS NULL OR DATE(pi.desired_delivery_date) IN (" . implode(',', array_fill(0, count($coveredDates), '?')) . "))";
            $params = array_merge($params, $coveredDates);
        }
        $sql .= " ORDER BY CASE WHEN pi.desired_delivery_date IS NULL THEN 1 ELSE 0 END, pi.desired_delivery_date ASC, pi.created_at ASC, pi.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$item) {
            $desiredDate = $this->normalizeDateString($item['desired_delivery_date'] ?? null);
            $item['desired_delivery_date'] = $desiredDate;
            $item['desired_delivery_date_label'] = $desiredDate ? date('d.m.Y', strtotime($desiredDate)) : 'Не имеет значения';
            $item['matches_batch_window'] = $coveredDates === [] ? null : ($desiredDate === null || in_array($desiredDate, $coveredDates, true));
        }
        unset($item);

        echo json_encode([
            'items' => $items,
            'batch_id' => $batchId > 0 ? $batchId : null,
            'covered_dates' => $coveredDates,
            'covered_date_labels' => array_map(static fn (string $date): string => date('d.m.Y', strtotime($date)), $coveredDates),
            'matching_only' => $matchingOnly,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function preorderIntentDecision(): void
    {
        $this->ensureCsrfOrRedirect();
        $intentId = (int)($_POST['intent_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $batchId = (int)($_POST['batch_id'] ?? 0);
        if ($intentId <= 0 || !in_array($action, ['confirm','decline','link'], true)) {
            header('Location: ' . $this->basePath() . '/purchases');
            exit;
        }
        if ($action === 'link') {
            $linked = $this->linkPreorderIntentToBatch($intentId, $batchId);
            $this->setFlash($linked ? 'success' : 'error', $linked ? 'Предзаказ добавлен в закупку.' : 'Не удалось добавить предзаказ: дата не подходит или статус уже изменён.');
            header('Location: ' . $this->basePath() . '/purchases');
            exit;
        }

        $result = $this->preorderIntentService->decideByManager($intentId, $action);
        if ($result['ok']) {
            $this->setFlash('success', $action === 'confirm' ? 'Предзаказ подтверждён.' : 'Предзаказ отменён.');
        } else {
            $this->setFlash('error', 'Не удалось изменить предзаказ: статус уже изменён или недоступен для операции.');
        }
        header('Location: ' . $this->basePath() . '/purchases');
        exit;
    }

    private function linkPreorderIntentToBatch(int $intentId, int $batchId): bool
    {
        if ($intentId <= 0 || $batchId <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT pi.id, pi.product_id, pi.status, pi.desired_delivery_date, pb.product_id AS batch_product_id, pb.purchased_at
             FROM preorder_intents pi
             JOIN purchase_batches pb ON pb.id = ?
             WHERE pi.id = ?
             LIMIT 1"
        );
        $stmt->execute([$batchId, $intentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)$row['product_id'] !== (int)$row['batch_product_id']) {
            return false;
        }

        $allowedStatuses = ['waiting_batch', 'intent_created', 'linked_to_batch'];
        if (!in_array((string)($row['status'] ?? ''), $allowedStatuses, true)) {
            return false;
        }

        $coveredDates = $this->coveredDeliveryDatesForDate($row['purchased_at'] ?? null);
        $desiredDate = $this->normalizeDateString($row['desired_delivery_date'] ?? null);
        if ($desiredDate !== null && $coveredDates !== [] && !in_array($desiredDate, $coveredDates, true)) {
            return false;
        }

        $update = $this->pdo->prepare(
            "UPDATE preorder_intents
             SET purchase_batch_id = ?, status = 'linked_to_batch', updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
               AND status IN ('waiting_batch','intent_created','linked_to_batch')"
        );
        $update->execute([$batchId, $intentId]);
        if ($update->rowCount() < 1) {
            return false;
        }

        $this->logPreorderEvent($intentId, 'manager_linked_to_batch', (string)$row['status'], 'linked_to_batch', [
            'purchase_batch_id' => $batchId,
            'desired_delivery_date' => $desiredDate,
            'covered_delivery_dates' => $coveredDates,
        ]);
        return true;
    }

    public function maintenancePreorders(): void
    {
        $this->ensureCsrfOrRedirect();
        $ttlHours = max(1, (int)(get_setting('preorder_unconfirmed_cancel_hours', '48') ?? '48'));
        $expired = $this->preorderIntentService->expireOffers();
        $cancelled = $this->preorderIntentService->cancelUnconfirmedByDeadline($ttlHours);
        $this->setFlash('success', 'Обновлено предзаказов: истекло ' . $expired . ', отменено неподтвержденных ' . $cancelled . '.');
        header('Location: ' . $this->basePath() . '/purchases');
        exit;
    }

    public function writeOff(): void
    {
        $this->ensureCsrfOrRedirect();
        if (!in_array((string)($_SESSION['role'] ?? ''), ['admin', 'manager'], true)) {
            $this->setFlash('error', 'Операция доступна только администратору или менеджеру.');
            header('Location: ' . $this->basePath() . '/purchases');
            exit;
        }
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $boxes = (float)($_POST['boxes'] ?? 0);
        $comment = trim((string)($_POST['comment'] ?? ''));
        if ($comment === '') { $comment = 'Без причины'; }
        if ($batchId > 0 && $boxes > 0) {
            $this->purchaseBatchService->writeOff($batchId, $boxes, $comment);
            $this->purchaseBatchService->autoCloseEligibleBatches($batchId);
            $this->setFlash('success', 'Списание выполнено.');
        }
        header('Location: ' . $this->basePath() . '/purchases');
        exit;
    }

    public function uploadPhotos(): void
    {
        $this->ensureCsrfOrRedirect();
        $batchId = (int)($_POST['batch_id'] ?? 0);
        if ($batchId <= 0) {
            $this->setFlash('error', 'Некорректная закупка.');
            header('Location: ' . $this->basePath() . '/purchases');
            exit;
        }

        $this->storeBatchPhotos($batchId);
        $this->setFlash('success', 'Фото закупки обновлены.');
        header('Location: ' . $this->basePath() . '/purchases/' . $batchId);
        exit;
    }

    /** @return array<string,mixed>|null */
    private function loadBatchForDateConfirmation(int $batchId): ?array
    {
        if ($batchId <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id, product_id, purchased_at, status FROM purchase_batches WHERE id = ? LIMIT 1');
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        return $batch ?: null;
    }

    private function requestDateConfirmationForBatch(int $batchId, ?string $oldSupplyDate, ?string $newSupplyDate, string $reason): int
    {
        $batch = $this->loadBatchForDateConfirmation($batchId);
        if (!$batch) {
            return 0;
        }
        $productId = (int)($batch['product_id'] ?? 0);
        if ($productId <= 0) {
            return 0;
        }

        $newCoveredDates = $this->coveredDeliveryDatesForDate($newSupplyDate);
        $nextSupply = $this->findNextSupplyForProduct($productId, $batchId, $newSupplyDate ?? $oldSupplyDate);
        $stmt = $this->pdo->prepare(
            "SELECT id, status, desired_delivery_date
             FROM preorder_intents
             WHERE purchase_batch_id = ?
               AND status IN ('linked_to_batch','awaiting_price_confirmation','offer_sent','confirmed')
             ORDER BY id ASC"
        );
        $stmt->execute([$batchId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $requested = 0;
        foreach ($items as $item) {
            $intentId = (int)($item['id'] ?? 0);
            $desiredDate = $this->normalizeDateString($item['desired_delivery_date'] ?? null);
            if ($reason === 'rescheduled' && $desiredDate !== null && $newCoveredDates !== [] && in_array($desiredDate, $newCoveredDates, true)) {
                continue;
            }
            $proposedDate = $this->proposedDeliveryDateForChange($desiredDate, $oldSupplyDate, $newSupplyDate, $nextSupply['date'] ?? null);
            $this->logPreorderEvent($intentId, 'date_change_requested', (string)($item['status'] ?? ''), (string)($item['status'] ?? ''), [
                'reason' => $reason,
                'purchase_batch_id' => $batchId,
                'old_supply_date' => $oldSupplyDate,
                'new_supply_date' => $newSupplyDate,
                'old_desired_delivery_date' => $desiredDate,
                'proposed_delivery_date' => $proposedDate,
                'next_batch_id' => $nextSupply['id'] ?? null,
                'next_supply_date' => $nextSupply['date'] ?? null,
            ]);
            $requested++;
        }

        return $requested;
    }

    private function proposedDeliveryDateForChange(?string $desiredDate, ?string $oldSupplyDate, ?string $newSupplyDate, ?string $nextSupplyDate): ?string
    {
        if ($newSupplyDate === null) {
            return $nextSupplyDate;
        }
        if ($desiredDate === null || $oldSupplyDate === null) {
            return $newSupplyDate;
        }
        $oldTs = strtotime($oldSupplyDate);
        $desiredTs = strtotime($desiredDate);
        if ($oldTs === false || $desiredTs === false) {
            return $newSupplyDate;
        }
        $offsetDays = max(0, min(2, (int)floor(($desiredTs - $oldTs) / 86400)));
        return (new \DateTimeImmutable($newSupplyDate))->modify('+' . $offsetDays . ' day')->format('Y-m-d');
    }

    /** @return array{id:int,date:string}|null */
    private function findNextSupplyForProduct(int $productId, int $excludeBatchId, ?string $afterDate): ?array
    {
        if ($productId <= 0) {
            return null;
        }
        $afterDate = $this->normalizeDateString($afterDate) ?? date('Y-m-d');
        $stmt = $this->pdo->prepare(
            "SELECT id, DATE(purchased_at) AS supply_date
             FROM purchase_batches
             WHERE product_id = ?
               AND id <> ?
               AND status IN ('planned','purchased','arrived')
               AND purchased_at IS NOT NULL
               AND DATE(purchased_at) > ?
             ORDER BY purchased_at ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$productId, $excludeBatchId, $afterDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return ['id' => (int)$row['id'], 'date' => (string)$row['supply_date']];
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
        } catch (\Throwable) {
            // audit logging is non-blocking
        }
    }

    /** @return array<int,string> */
    private function coveredDeliveryDatesForDate(mixed $value): array
    {
        $date = $this->normalizeDateString($value);
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

    private function nullablePostedDate(string $field): ?string
    {
        $date = trim((string)($_POST[$field] ?? ''));
        return $date !== '' ? $date : null;
    }

    /** @param array<string,mixed> $batch */
    private function resolvePurchasedAtForPurchase(array $batch): string
    {
        $postedDate = $this->nullablePostedDate('planned_supply_date');
        if ($postedDate !== null) {
            return $postedDate;
        }

        $currentDate = trim((string)($batch['purchased_at'] ?? ''));
        return $currentDate !== '' ? $currentDate : date('Y-m-d');
    }

    public function deletePhoto(): void
    {
        $this->ensureCsrfOrRedirect();
        $photoId = (int)($_POST['photo_id'] ?? 0);
        if ($photoId <= 0) {
            header('Location: ' . $this->basePath() . '/purchases');
            exit;
        }

        $stmt = $this->pdo->prepare('SELECT id, purchase_batch_id, image_path FROM purchase_batch_photos WHERE id = ? LIMIT 1');
        $stmt->execute([$photoId]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$photo) {
            $this->setFlash('error', 'Фото не найдено.');
            header('Location: ' . $this->basePath() . '/purchases');
            exit;
        }

        $del = $this->pdo->prepare('DELETE FROM purchase_batch_photos WHERE id = ?');
        $del->execute([$photoId]);

        $path = __DIR__ . '/../../' . ltrim((string)$photo['image_path'], '/');
        if (is_file($path)) {
            @unlink($path);
        }

        $this->setFlash('success', 'Фото удалено.');
        header('Location: ' . $this->basePath() . '/purchases/' . (int)$photo['purchase_batch_id']);
        exit;
    }

    public function update(): void
    {
        $this->ensureCsrfOrRedirect();
        $batchId = (int)($_POST['batch_id'] ?? 0);
        if ($batchId <= 0) {
            $this->setFlash('error', 'Некорректная закупка.');
            header('Location: ' . $this->basePath() . '/purchases');
            exit;
        }
        $status = (string)($_POST['status'] ?? 'planned');
        $productId = (int)($_POST['product_id'] ?? 0);
        $purchasePrice = (float)($_POST['purchase_price_per_box'] ?? 0);
        $boxSize = $this->getProductBoxSize($productId);
        $prices = $this->pricingService->calculateFromPurchase($purchasePrice, $boxSize);
        $settings = $this->pricingService->getSettings();
        // Batch-first pricing: prices are derived from purchase price by default,
        // but admin/buyer may correct final prices manually on the batch.
        $instantPosted = (float)($_POST['instant_price_per_box'] ?? 0);
        $preorderPosted = (float)($_POST['preorder_price_per_box'] ?? 0);
        $instantPrice = $instantPosted > 0 ? $instantPosted : (float)$prices['instant_price_per_box'];
        $preorderPrice = $preorderPosted > 0 ? $preorderPosted : (float)$prices['preorder_price_per_box'];

        $boxesTotal = max(0.0, (float)($_POST['boxes_total'] ?? 0));
        $boxesReserved = max(0.0, (float)($_POST['boxes_reserved'] ?? 0));
        $boxesFree = max(0.0, (float)($_POST['boxes_free'] ?? 0));

        $currentStmt = $this->pdo->prepare(
            'SELECT product_id, status, purchased_at, boxes_sold, boxes_written_off, boxes_discount
               FROM purchase_batches
              WHERE id = ?
              LIMIT 1'
        );
        $currentStmt->execute([$batchId]);
        $currentBatch = $currentStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $boxesSold = (float)($currentBatch['boxes_sold'] ?? 0);
        $boxesWrittenOff = (float)($currentBatch['boxes_written_off'] ?? 0);
        $boxesDiscount = (float)($currentBatch['boxes_discount'] ?? 0);
        $boxesRemaining = $boxesTotal - $boxesSold - $boxesWrittenOff;

        if ($boxesRemaining < -0.01) {
            $this->setFlash('error', 'Куплено ящиков не может быть меньше уже проданных и списанных ящиков.');
            header('Location: ' . $this->basePath() . '/purchases/' . $batchId);
            exit;
        }

        if (($boxesFree + $boxesReserved + $boxesDiscount) > ($boxesRemaining + 0.01)) {
            $this->setFlash('error', 'Свободно + резерв + уценка не может быть больше остатка партии. Проверьте количество ящиков.');
            header('Location: ' . $this->basePath() . '/purchases/' . $batchId);
            exit;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE purchase_batches
             SET product_id = :product_id,
                 boxes_total = :boxes_total,
                 boxes_reserved = :boxes_reserved,
                 boxes_free = :boxes_free,
                 boxes_remaining = :boxes_remaining,
                 purchase_price_per_box = :purchase_price_per_box,
                 extra_cost_per_box = :extra_cost_per_box,
                 preorder_margin_percent = :preorder_margin_percent,
                 preorder_discount_percent = :preorder_discount_percent,
                 instant_margin_percent = :instant_margin_percent,
                 instant_price_per_box = :instant_price_per_box,
                 preorder_price_per_box = :preorder_price_per_box,
                 instant_unit_price = :instant_unit_price,
                 preorder_unit_price = :preorder_unit_price,
                 status = :status,
                 purchased_at = :purchased_at,
                 comment = :comment
             WHERE id = :id
             LIMIT 1'
        );
        $postedSupplyDate = $this->nullablePostedDate('planned_supply_date');
        $oldSupplyDate = $this->normalizeDateString($currentBatch['purchased_at'] ?? null);

        $stmt->execute([
            'id' => $batchId,
            'product_id' => $productId,
            'boxes_total' => $boxesTotal,
            'boxes_reserved' => $boxesReserved,
            'boxes_free' => $boxesFree,
            'boxes_remaining' => max(0.0, $boxesRemaining),
            'purchase_price_per_box' => (float)($_POST['purchase_price_per_box'] ?? 0),
            'extra_cost_per_box' => (float)($_POST['extra_cost_per_box'] ?? 0),
            'preorder_margin_percent' => (float)$settings['pricing_preorder_margin_percent'],
            'preorder_discount_percent' => (float)$settings['ui_preorder_discount_percent'],
            'instant_margin_percent' => (float)$settings['pricing_instant_margin_percent'],
            'instant_price_per_box' => $instantPrice,
            'preorder_price_per_box' => $preorderPrice,
            'instant_unit_price' => (float)$prices['instant_unit_price'],
            'preorder_unit_price' => (float)$prices['preorder_unit_price'],
            'status' => $status,
            'purchased_at' => $postedSupplyDate,
            'comment' => trim((string)($_POST['comment'] ?? '')),
        ]);
        // compatibility-only projection for legacy admin/reporting surfaces
        $this->legacyProjectionService->updateBatchSnapshot($productId, $batchId, $boxesFree, $boxesReserved, [
            'preorder_price_per_box' => $preorderPrice,
            'instant_price_per_box' => $instantPrice,
            'discount_price_per_box' => $instantPrice,
            'preorder_unit_price' => $preorderPrice,
            'instant_unit_price' => $instantPrice,
            'discount_unit_price' => $instantPrice,
        ]);

        $this->storeBatchPhotos($batchId);
        $newSupplyDate = $this->normalizeDateString($postedSupplyDate);
        if ($oldSupplyDate !== $newSupplyDate) {
            $this->requestDateConfirmationForBatch($batchId, $oldSupplyDate, $newSupplyDate, 'rescheduled');
        }
        $this->setFlash('success', 'Закупка обновлена.');
        header('Location: ' . $this->basePath() . '/purchases/' . $batchId);
        exit;
    }

    public function close(): void
    {
        $this->ensureCsrfOrRedirect();
        $batchId = (int)($_POST['batch_id'] ?? 0);
        if ($batchId <= 0) {
            $this->setFlash('error', 'Некорректная закупка.');
            header('Location: ' . $this->basePath() . '/purchases');
            exit;
        }
        $reason = trim((string)($_POST['close_reason'] ?? 'Ручное закрытие'));
        $batchBeforeClose = $this->loadBatchForDateConfirmation($batchId);
        try {
            $this->purchaseBatchService->closeBatch($batchId, $reason !== '' ? $reason : 'Ручное закрытие');
            if ($batchBeforeClose) {
                $this->requestDateConfirmationForBatch($batchId, $this->normalizeDateString($batchBeforeClose['purchased_at'] ?? null), null, 'cancelled');
            }
            $this->setFlash('success', 'Закупка закрыта (без удаления данных).');
        } catch (RuntimeException $e) {
            $this->setFlash('error', $e->getMessage());
        }
        header('Location: ' . $this->basePath() . '/purchases');
        exit;
    }

    private function basePath(): string
    {
        $role = (string)($_SESSION['role'] ?? '');
        return match ($role) {
            'manager' => '/manager',
            'buyer' => '/buyer',
            default => '/admin',
        };
    }

    private function getProductBoxSize(int $productId): float
    {
        if ($productId <= 0) {
            return 1.0;
        }

        $stmt = $this->pdo->prepare('SELECT box_size FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$productId]);
        $boxSize = (float)$stmt->fetchColumn();

        return $boxSize > 0 ? $boxSize : 1.0;
    }

    private function storeBatchPhotos(int $batchId): void
    {
        if (!isset($_FILES['photos']) || !is_array($_FILES['photos']['tmp_name'] ?? null)) {
            return;
        }

        $tmpFiles = $_FILES['photos']['tmp_name'];
        $errors = $_FILES['photos']['error'] ?? [];
        $names = $_FILES['photos']['name'] ?? [];
        foreach ($tmpFiles as $idx => $tmpPath) {
            $errorCode = (int)($errors[$idx] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode !== UPLOAD_ERR_OK || !is_string($tmpPath) || $tmpPath === '') {
                continue;
            }

            $src = @imagecreatefromstring((string)file_get_contents($tmpPath));
            if (!$src) {
                continue;
            }

            $extSafeName = pathinfo((string)($names[$idx] ?? ''), PATHINFO_FILENAME);
            $fileName = uniqid('batch_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $extSafeName) . '_', true) . '.webp';
            $absPath = __DIR__ . '/../../uploads/' . $fileName;
            $ok = imagewebp($src, $absPath, 82);
            imagedestroy($src);

            if (!$ok) {
                continue;
            }

            $relPath = '/uploads/' . $fileName;
            $stmt = $this->pdo->prepare(
                'INSERT INTO purchase_batch_photos (purchase_batch_id, image_path) VALUES (:batch_id, :image_path)'
            );
            $stmt->execute([
                'batch_id' => $batchId,
                'image_path' => $relPath,
            ]);
        }
    }

    private function ensureCsrfOrRedirect(): void
    {
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
            $this->setFlash('error', 'Неверный CSRF токен.');
            header('Location: ' . $this->basePath() . '/purchases');
            exit;
        }
    }

    private function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array<string, string>|null
     */
    private function pullFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        if (!is_array($flash)) {
            return null;
        }

        return [
            'type' => (string)($flash['type'] ?? ''),
            'message' => (string)($flash['message'] ?? ''),
        ];
    }
}
