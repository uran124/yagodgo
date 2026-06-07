<?php
namespace Tests;

use App\Services\OrderStockOrchestrator;
use App\Services\StockService;
use PDO;
use PHPUnit\Framework\TestCase;

class OrderStockOrchestratorTest extends TestCase
{
    private PDO $pdo;
    private OrderStockOrchestrator $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE products (
            id INTEGER PRIMARY KEY,
            free_stock_boxes REAL DEFAULT 0,
            reserved_stock_boxes REAL DEFAULT 0,
            discount_stock_boxes REAL DEFAULT 0,
            sold_stock_boxes REAL DEFAULT 0,
            written_off_stock_boxes REAL DEFAULT 0,
            stock_status TEXT DEFAULT "sold_out"
        )');
        $this->pdo->exec('CREATE TABLE purchase_batches (
            id INTEGER PRIMARY KEY,
            product_id INTEGER,
            boxes_total REAL DEFAULT 0,
            boxes_reserved REAL DEFAULT 0,
            boxes_free REAL DEFAULT 0,
            boxes_discount REAL DEFAULT 0,
            boxes_sold REAL DEFAULT 0,
            boxes_written_off REAL DEFAULT 0,
            boxes_remaining REAL DEFAULT 0,
            status TEXT DEFAULT "purchased",
            purchased_at TEXT
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

        $this->pdo->exec("INSERT INTO products (id, free_stock_boxes, stock_status) VALUES (1, 10, 'in_stock')");
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, boxes_total, boxes_free, boxes_remaining, status, purchased_at) VALUES
            (10, 1, 10, 2, 10, 'purchased', '2026-05-01 10:00:00'),
            (11, 1, 10, 8, 10, 'purchased', '2026-05-02 10:00:00')");

        $this->service = new OrderStockOrchestrator($this->pdo, new StockService($this->pdo));
    }

    public function testPersistOrderItemUsesExplicitPurchaseBatchIdFromCart(): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price, stock_mode, purchase_batch_id) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $this->service->persistOrderItemWithStock($stmt, 100, 1, [
            'quantity' => 2.0,
            'box_size' => 1.0,
            'unit_price' => 1500.0,
            'purchase_batch_id' => 11,
        ], 'instant', false);

        $item = $this->pdo->query('SELECT purchase_batch_id, boxes FROM order_items WHERE order_id = 100')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(11, (int)$item['purchase_batch_id']);
        $this->assertSame(2.0, (float)$item['boxes']);

        $batch11 = $this->pdo->query('SELECT boxes_free, boxes_reserved, boxes_sold FROM purchase_batches WHERE id = 11')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(6.0, (float)$batch11['boxes_free']);
        $this->assertSame(0.0, (float)$batch11['boxes_reserved']);
        $this->assertSame(2.0, (float)$batch11['boxes_sold']);

        $movements = $this->pdo->query('SELECT movement_type, stock_mode FROM stock_movements WHERE order_id = 100 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $movements);
        $this->assertSame('sale', $movements[0]['movement_type']);
        $this->assertSame('instant', $movements[0]['stock_mode']);
    }
    public function testPersistOrderItemOnlyDoesNotCreateStockMovements(): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price, stock_mode, purchase_batch_id) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $this->service->persistOrderItemOnly($stmt, 101, 1, [
            'quantity' => 3.0,
            'box_size' => 1.0,
            'unit_price' => 1500.0,
            'purchase_batch_id' => 11,
        ], 'instant');

        $batch11 = $this->pdo->query('SELECT boxes_free, boxes_reserved, boxes_sold FROM purchase_batches WHERE id = 11')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(8.0, (float)$batch11['boxes_free']);
        $this->assertSame(0.0, (float)$batch11['boxes_reserved']);
        $this->assertSame(0.0, (float)$batch11['boxes_sold']);
        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM stock_movements WHERE order_id = 101')->fetchColumn());
    }

    public function testApplyStockForOrderReservesOnConfirmationAndCompletedCommitsSale(): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price, stock_mode, purchase_batch_id) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $this->service->persistOrderItemOnly($stmt, 102, 1, [
            'quantity' => 2.0,
            'box_size' => 1.0,
            'unit_price' => 1500.0,
            'purchase_batch_id' => 11,
        ], 'instant');

        $this->service->applyStockForOrderId(102);
        $batch11 = $this->pdo->query('SELECT boxes_free, boxes_reserved, boxes_sold FROM purchase_batches WHERE id = 11')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(6.0, (float)$batch11['boxes_free']);
        $this->assertSame(2.0, (float)$batch11['boxes_reserved']);
        $this->assertSame(0.0, (float)$batch11['boxes_sold']);

        $this->service->commitReservedStockByOrderId(102);
        $batch11 = $this->pdo->query('SELECT boxes_free, boxes_reserved, boxes_sold FROM purchase_batches WHERE id = 11')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(6.0, (float)$batch11['boxes_free']);
        $this->assertSame(0.0, (float)$batch11['boxes_reserved']);
        $this->assertSame(2.0, (float)$batch11['boxes_sold']);
    }

    public function testApplyStockAllowsInstantDeficitOnExplicitBatch(): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price, stock_mode, purchase_batch_id) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $this->service->persistOrderItemOnly($stmt, 103, 1, [
            'quantity' => 10.0,
            'box_size' => 1.0,
            'unit_price' => 1500.0,
            'purchase_batch_id' => 10,
        ], 'instant');

        $this->service->applyStockForOrderId(103);
        $batch10 = $this->pdo->query('SELECT boxes_free, boxes_reserved FROM purchase_batches WHERE id = 10')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(-8.0, (float)$batch10['boxes_free']);
        $this->assertSame(10.0, (float)$batch10['boxes_reserved']);
    }

}

