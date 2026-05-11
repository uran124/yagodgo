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
