<?php
namespace App\Controllers;

use App\Services\PurchaseBatchService;
use App\Services\PreorderIntentService;
use PDO;
use RuntimeException;

class PurchaseBatchesController
{
    private PDO $pdo;
    private PurchaseBatchService $purchaseBatchService;
    private PreorderIntentService $preorderIntentService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->purchaseBatchService = new PurchaseBatchService($pdo);
        $this->preorderIntentService = new PreorderIntentService($pdo);
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
          . "       CASE WHEN pb.boxes_free <= 0 OR pb.comment LIKE '[CLOSED]%' THEN 1 ELSE 0 END AS is_closed,\n"
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
          . '  COALESCE(SUM(CASE WHEN status IN ("active","arrived","purchased") THEN boxes_remaining ELSE 0 END), 0) AS remaining_boxes,
'
          . '  COALESCE(SUM(boxes_written_off), 0) AS written_off_boxes,
'
          . '  COALESCE(AVG(TIMESTAMPDIFF(DAY, purchased_at, NOW())), 0) AS avg_age_days
'
          . 'FROM purchase_batches'
        );
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

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
          . "  COALESCE(SUM(CASE WHEN pi.status = 'confirmed' THEN pi.requested_boxes ELSE 0 END), 0) AS confirmed_boxes
"
          . "FROM preorder_intents pi
"
          . "JOIN products p ON p.id = pi.product_id
"
          . "JOIN product_types t ON t.id = p.product_type_id
"
          . "WHERE pi.status IN ('intent_created', 'offer_sent', 'confirmed')
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

        $payload = [
            'product_id' => (int)($_POST['product_id'] ?? 0),
            'buyer_user_id' => (int)($_SESSION['user_id'] ?? 0),
            'boxes_total' => (float)($_POST['boxes_total'] ?? 0),
            'boxes_reserved' => (float)($_POST['boxes_reserved'] ?? 0),
            'boxes_free' => (float)($_POST['boxes_free'] ?? 0),
            'purchase_price_per_box' => (float)($_POST['purchase_price_per_box'] ?? 0),
            'extra_cost_per_box' => (float)($_POST['extra_cost_per_box'] ?? 0),
            'instant_price_per_box' => $instantPrice,
            'preorder_price_per_box' => $preorderPrice,
            'status' => $status,
            'purchased_at' => (string)($_POST['planned_supply_date'] ?? ''),
            'instant_price_per_box' => $instantPrice,
            'preorder_price_per_box' => $preorderPrice,
            'comment' => trim((string)($_POST['comment'] ?? '')),
        ];

        try {
            $batchId = $this->purchaseBatchService->createBatch($payload);
                    $batchSnapshot = $this->pdo->prepare('UPDATE products SET current_purchase_batch_id = ?, instant_price_per_box = ?, preorder_price_per_box = ?, price = ? WHERE id = ? LIMIT 1');
        $batchSnapshot->execute([(int)($_POST['product_id'] ?? 0), $instantPrice, $preorderPrice, $instantPrice, (int)($_POST['product_id'] ?? 0)]);

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

    public function markPurchased(): void
    {
        $this->ensureCsrfOrRedirect();
        $batchId = (int)($_POST['batch_id'] ?? 0);
        if ($batchId > 0) {
            try {
                $this->purchaseBatchService->markPurchased($batchId);
                $this->setFlash('success', 'Партия отмечена как выкупленная.');
            } catch (RuntimeException $e) {
                $this->setFlash('error', $e->getMessage());
            }
        }
        header('Location: ' . $this->basePath() . '/purchases');
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
            $this->setFlash('success', 'Списание выполнено.');
        }
        header('Location: ' . $this->basePath() . '/purchases');
        exit;
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
        $instantPrice = (float)($_POST['instant_price_per_box'] ?? 0);
        $preorderPrice = (float)($_POST['preorder_price_per_box'] ?? 0);
        if ($status === 'purchased') {
            if ($instantPrice <= 0) {
                $instantPrice = (float)($_POST['purchase_price_per_box'] ?? 0);
            }
            if ($preorderPrice <= 0 && $instantPrice > 0) {
                $preorderPrice = round($instantPrice * 0.9, 2);
            }
        }

        $stmt = $this->pdo->prepare(
            'UPDATE purchase_batches
             SET product_id = :product_id, boxes_total = :boxes_total, boxes_reserved = :boxes_reserved, boxes_free = :boxes_free,
                 purchase_price_per_box = :purchase_price_per_box, extra_cost_per_box = :extra_cost_per_box,
                 instant_price_per_box = :instant_price_per_box, preorder_price_per_box = :preorder_price_per_box,
                 status = :status, purchased_at = :purchased_at, comment = :comment
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $batchId,
            'product_id' => (int)($_POST['product_id'] ?? 0),
            'boxes_total' => (float)($_POST['boxes_total'] ?? 0),
            'boxes_reserved' => (float)($_POST['boxes_reserved'] ?? 0),
            'boxes_free' => (float)($_POST['boxes_free'] ?? 0),
            'purchase_price_per_box' => (float)($_POST['purchase_price_per_box'] ?? 0),
            'extra_cost_per_box' => (float)($_POST['extra_cost_per_box'] ?? 0),
            'instant_price_per_box' => $instantPrice,
            'preorder_price_per_box' => $preorderPrice,
            'status' => $status,
            'purchased_at' => (string)($_POST['planned_supply_date'] ?? ''),
            'comment' => trim((string)($_POST['comment'] ?? '')),
        ]);
                $batchSnapshot = $this->pdo->prepare('UPDATE products SET current_purchase_batch_id = ?, instant_price_per_box = ?, preorder_price_per_box = ?, price = ? WHERE id = ? LIMIT 1');
        $batchSnapshot->execute([(int)($_POST['product_id'] ?? 0), $instantPrice, $preorderPrice, $instantPrice, (int)($_POST['product_id'] ?? 0)]);

        $this->storeBatchPhotos($batchId);
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
        $stmt = $this->pdo->prepare('SELECT comment FROM purchase_batches WHERE id = ? LIMIT 1');
        $stmt->execute([$batchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->setFlash('error', 'Закупка не найдена.');
            header('Location: ' . $this->basePath() . '/purchases');
            exit;
        }

        $comment = trim((string)($row['comment'] ?? ''));
        if (!str_starts_with($comment, '[CLOSED]')) {
            $comment = trim('[CLOSED] ' . $comment);
        }

        $upd = $this->pdo->prepare('UPDATE purchase_batches SET boxes_free = 0, comment = :comment WHERE id = :id LIMIT 1');
        $upd->execute(['id' => $batchId, 'comment' => $comment]);
        $this->setFlash('success', 'Закупка закрыта (без удаления данных).');
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
