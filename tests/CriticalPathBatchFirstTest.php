<?php
namespace Tests;

use App\Services\OrderStockOrchestrator;
use App\Services\SellableBatchResolver;
use App\Services\StockService;
use PDO;
use PHPUnit\Framework\TestCase;

class CriticalPathBatchFirstTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, box_size REAL DEFAULT 1)');
        $this->pdo->exec('CREATE TABLE purchase_batches (
            id INTEGER PRIMARY KEY,
            product_id INTEGER,
            status TEXT,
            boxes_total REAL DEFAULT 0,
            boxes_free REAL DEFAULT 0,
            boxes_discount REAL DEFAULT 0,
            boxes_reserved REAL DEFAULT 0,
            boxes_sold REAL DEFAULT 0,
            boxes_written_off REAL DEFAULT 0,
            boxes_remaining REAL DEFAULT 0,
            instant_price_per_box REAL DEFAULT 0,
            discount_price_per_box REAL DEFAULT 0,
            preorder_price_per_box REAL DEFAULT 0,
            purchased_at TEXT
        )');
        $this->pdo->exec('CREATE TABLE cart_items (
            user_id INTEGER,
            product_id INTEGER,
            purchase_batch_id INTEGER NULL,
            stock_mode TEXT,
            quantity REAL,
            unit_price REAL,
            boxes REAL
        )');
        $this->pdo->exec('CREATE TABLE order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER,
            product_id INTEGER,
            quantity REAL,
            boxes REAL,
            unit_price REAL,
            stock_mode TEXT,
            purchase_batch_id INTEGER
        )');
        $this->pdo->exec('CREATE TABLE stock_movements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            purchase_batch_id INTEGER,
            product_id INTEGER,
            order_id INTEGER,
            user_id INTEGER,
            movement_type TEXT,
            stock_mode TEXT,
            boxes_delta REAL,
            comment TEXT
        )');

        $this->pdo->exec("INSERT INTO products (id, box_size) VALUES (1, 2.0)");
        $this->pdo->exec("INSERT INTO purchase_batches (
            id, product_id, status, boxes_total, boxes_free, boxes_remaining, instant_price_per_box, purchased_at
        ) VALUES (
            101, 1, 'purchased', 10, 6, 10, 1500, '2026-05-01 10:00:00'
        )");
    }

    public function testCatalogToCartToCheckoutPersistsBatchAndDeductsSameBatch(): void
    {
        // catalog/addToCart equivalent: resolve sellable batch
        $resolver = new SellableBatchResolver($this->pdo);
        $batch = $resolver->resolveForProduct(1, 'instant');
        $this->assertNotNull($batch);
        $this->assertSame(101, (int)$batch['id']);

        // cart snapshot
        $this->pdo->prepare(
            'INSERT INTO cart_items (user_id, product_id, purchase_batch_id, stock_mode, quantity, unit_price, boxes) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([7, 1, (int)$batch['id'], 'instant', 2.0, (float)$batch['price_per_box'], 2.0]);

        // checkout equivalent: persist order item with explicit purchase_batch_id and apply stock ops
        $stock = new StockService($this->pdo);
        $orchestrator = new OrderStockOrchestrator($this->pdo, $stock);
        $stmtItem = $this->pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price, stock_mode, purchase_batch_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $orchestrator->persistOrderItemWithStock($stmtItem, 500, 1, [
            'quantity' => 2.0,
            'box_size' => 2.0,
            'unit_price' => 1500.0,
            'purchase_batch_id' => 101,
        ], 'instant', false);

        $orderItem = $this->pdo->query('SELECT purchase_batch_id, boxes FROM order_items WHERE order_id = 500 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(101, (int)$orderItem['purchase_batch_id']);
        $this->assertSame(2.0, (float)$orderItem['boxes']);

        $batchAfter = $this->pdo->query('SELECT boxes_free, boxes_sold FROM purchase_batches WHERE id = 101')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(4.0, (float)$batchAfter['boxes_free']);
        $this->assertSame(2.0, (float)$batchAfter['boxes_sold']);
    }
}

