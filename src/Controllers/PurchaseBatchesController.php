<?php
namespace App\Controllers;

use App\Services\PurchaseBatchService;
use PDO;
use RuntimeException;

class PurchaseBatchesController
{
    private PDO $pdo;
    private PurchaseBatchService $purchaseBatchService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->purchaseBatchService = new PurchaseBatchService($pdo);
    }

    public function index(): void
    {
        $stmt = $this->pdo->query(
            'SELECT pb.*, p.variety, t.name AS product_name, u.name AS buyer_name
             FROM purchase_batches pb
             JOIN products p ON p.id = pb.product_id
             JOIN product_types t ON t.id = p.product_type_id
             LEFT JOIN users u ON u.id = pb.buyer_user_id
             ORDER BY pb.id DESC'
        );

        viewAdmin('purchases/index', [
            'pageTitle' => 'Закупки',
            'batches' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'basePath' => $this->basePath(),
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
            'error' => trim((string)($_GET['error'] ?? '')),
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

        viewAdmin('purchases/show', [
            'pageTitle' => 'Партия #' . $id,
            'basePath' => $this->basePath(),
            'batch' => $batch,
            'movements' => $movementsStmt->fetchAll(PDO::FETCH_ASSOC),
            'photos' => $photosStmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
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
            'status' => (string)($_POST['status'] ?? 'purchased'),
            'comment' => trim((string)($_POST['comment'] ?? '')),
        ];

        try {
            $batchId = $this->purchaseBatchService->createBatch($payload);
            $this->storeBatchPhotos($batchId);
        } catch (RuntimeException $e) {
            header('Location: ' . $this->basePath() . '/purchases/create?error=' . urlencode($e->getMessage()));
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
            $this->purchaseBatchService->markArrived($batchId);
        }
        header('Location: ' . $this->basePath() . '/purchases');
        exit;
    }

    public function moveToDiscount(): void
    {
        $this->ensureCsrfOrRedirect();
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $boxes = (float)($_POST['boxes'] ?? 0);
        if ($batchId > 0 && $boxes > 0) {
            $this->purchaseBatchService->moveToDiscountStock($batchId, $boxes);
        }
        header('Location: ' . $this->basePath() . '/purchases');
        exit;
    }

    public function writeOff(): void
    {
        $this->ensureCsrfOrRedirect();
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $boxes = (float)($_POST['boxes'] ?? 0);
        $comment = trim((string)($_POST['comment'] ?? 'Write-off'));
        if ($batchId > 0 && $boxes > 0) {
            $this->purchaseBatchService->writeOff($batchId, $boxes, $comment);
        }
        header('Location: ' . $this->basePath() . '/purchases');
        exit;
    }

    public function close(): void
    {
        $this->ensureCsrfOrRedirect();
        $batchId = (int)($_POST['batch_id'] ?? 0);
        if ($batchId > 0) {
            $this->purchaseBatchService->closeBatch($batchId);
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
            header('Location: ' . $this->basePath() . '/purchases');
            exit;
        }

        $del = $this->pdo->prepare('DELETE FROM purchase_batch_photos WHERE id = ?');
        $del->execute([$photoId]);

        $path = __DIR__ . '/../../' . ltrim((string)$photo['image_path'], '/');
        if (is_file($path)) {
            @unlink($path);
        }

        header('Location: ' . $this->basePath() . '/purchases/' . (int)$photo['purchase_batch_id']);
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
            header('Location: ' . $this->basePath() . '/purchases?error=csrf');
            exit;
        }
    }
}
