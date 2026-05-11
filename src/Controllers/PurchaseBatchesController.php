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
            $this->purchaseBatchService->createBatch($payload);
        } catch (RuntimeException $e) {
            header('Location: ' . $this->basePath() . '/purchases/create?error=' . urlencode($e->getMessage()));
            exit;
        }

        header('Location: ' . $this->basePath() . '/purchases');
        exit;
    }

    public function markArrived(): void
    {
        $batchId = (int)($_POST['batch_id'] ?? 0);
        if ($batchId > 0) {
            $this->purchaseBatchService->markArrived($batchId);
        }
        header('Location: ' . $this->basePath() . '/purchases');
        exit;
    }

    public function moveToDiscount(): void
    {
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
        $batchId = (int)($_POST['batch_id'] ?? 0);
        if ($batchId > 0) {
            $this->purchaseBatchService->closeBatch($batchId);
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
}
