<?php
namespace Tests;

use App\Services\PricingService;
use App\Services\PurchaseBatchService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PurchaseBatchServiceTest extends TestCase
{
    private PDO $pdo;
    private PurchaseBatchService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');
        $this->pdo->exec('CREATE TABLE products (
            id INTEGER PRIMARY KEY,
            box_size REAL,
            box_unit TEXT,
            price REAL,
            current_purchase_batch_id INTEGER,
            free_stock_boxes REAL DEFAULT 0,
            reserved_stock_boxes REAL DEFAULT 0,
            preorder_price_per_box REAL DEFAULT 0,
            instant_price_per_box REAL DEFAULT 0,
            discount_price_per_box REAL DEFAULT 0,
            preorder_unit_price REAL DEFAULT 0,
            instant_unit_price REAL DEFAULT 0,
            discount_unit_price REAL DEFAULT 0,
            stock_status TEXT DEFAULT "sold_out"
        )');
        $this->pdo->exec('CREATE TABLE purchase_batches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER,
            buyer_user_id INTEGER,
            box_size_snapshot REAL,
            box_unit_snapshot TEXT,
            boxes_total REAL,
            boxes_reserved REAL,
            boxes_free REAL,
            boxes_remaining REAL,
            purchase_price_per_box REAL,
            extra_cost_per_box REAL,
            cost_price_per_box REAL,
            preorder_margin_percent REAL,
            instant_margin_percent REAL,
            discount_markup_fixed REAL,
            preorder_price_per_box REAL,
            instant_price_per_box REAL,
            discount_price_per_box REAL,
            preorder_unit_price REAL,
            instant_unit_price REAL,
            discount_unit_price REAL,
            status TEXT,
            comment TEXT
        )');

        $this->pdo->exec("INSERT INTO products (id, box_size, box_unit, price) VALUES (1, 2.0, 'кг', 0)");

        $pricingService = new PricingService($this->pdo);
        $this->service = new PurchaseBatchService($this->pdo, $pricingService);
    }

    public function testCreateBatchPersistsBatchAndSyncsProductSnapshot(): void
    {
        $batchId = $this->service->createBatch([
            'product_id' => 1,
            'boxes_total' => 30,
            'boxes_reserved' => 18,
            'boxes_free' => 10,
            'purchase_price_per_box' => 1000,
            'extra_cost_per_box' => 20,
            'comment' => 'Morning purchase',
        ]);

        $this->assertGreaterThan(0, $batchId);

        $batch = $this->pdo->query('SELECT * FROM purchase_batches WHERE id = ' . (int)$batchId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(30.0, (float)$batch['boxes_total']);
        $this->assertSame(1000.0, (float)$batch['purchase_price_per_box']);
        $this->assertSame(1020.0, (float)$batch['cost_price_per_box']);
        $this->assertSame(1300.0, (float)$batch['preorder_price_per_box']);
        $this->assertSame(1500.0, (float)$batch['instant_price_per_box']);
        $this->assertSame(1100.0, (float)$batch['discount_price_per_box']);

        $product = $this->pdo->query('SELECT * FROM products WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame((float)$batchId, (float)$product['current_purchase_batch_id']);
        $this->assertSame(10.0, (float)$product['free_stock_boxes']);
        $this->assertSame(18.0, (float)$product['reserved_stock_boxes']);
        $this->assertSame(750.0, (float)$product['price']);
        $this->assertSame('in_stock', $product['stock_status']);
    }

    public function testCreateBatchRejectsInvalidAllocation(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service->createBatch([
            'product_id' => 1,
            'boxes_total' => 5,
            'boxes_reserved' => 4,
            'boxes_free' => 3,
            'purchase_price_per_box' => 1000,
        ]);
    }

    public function testCalculateBatchPnlReturnsExpectedMetrics(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (
            id, product_id, buyer_user_id, box_size_snapshot, box_unit_snapshot,
            boxes_total, boxes_reserved, boxes_free, boxes_remaining,
            purchase_price_per_box, extra_cost_per_box, cost_price_per_box,
            preorder_margin_percent, instant_margin_percent, discount_markup_fixed,
            preorder_price_per_box, instant_price_per_box, discount_price_per_box,
            preorder_unit_price, instant_unit_price, discount_unit_price,
            boxes_sold, boxes_discount, boxes_written_off, status
        ) VALUES (
            99, 1, NULL, 2.0, 'кг',
            30, 0, 0, 20,
            1000, 20, 1020,
            30, 50, 100,
            1300, 1500, 1100,
            650, 750, 550,
            5, 3, 2, 'active'
        )");

        $pnl = $this->service->calculateBatchPnl(99);

        $this->assertSame(7500.0, $pnl['revenue_sold']);
        $this->assertSame(3300.0, $pnl['revenue_discount']);
        $this->assertSame(10800.0, $pnl['revenue_total']);
        $this->assertSame(10200.0, $pnl['cost_total_recognized']);
        $this->assertSame(600.0, $pnl['gross_margin']);
        $this->assertSame(20400.0, $pnl['inventory_value_remaining']);
    }

}

