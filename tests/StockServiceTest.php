<?php
namespace Tests;

use App\Services\StockService;
use PDO;
use PHPUnit\Framework\TestCase;

class StockServiceTest extends TestCase
{
    private PDO $pdo;
    private StockService $service;

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
            status TEXT DEFAULT "active"
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

        $this->pdo->exec('INSERT INTO products (id, free_stock_boxes, reserved_stock_boxes, stock_status) VALUES (1, 10, 0, "in_stock")');
        $this->pdo->exec('INSERT INTO purchase_batches (id, product_id, boxes_total, boxes_reserved, boxes_free, boxes_remaining, status) VALUES (1, 1, 30, 0, 10, 30, "active")');

        $this->service = new StockService($this->pdo);
    }

    public function testReserveUpdatesCountersAndCreatesMovement(): void
    {
        $this->service->reserve(1, 1, 3, 42, 'instant');

        $batch = $this->pdo->query('SELECT boxes_free, boxes_reserved, boxes_remaining FROM purchase_batches WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(7.0, (float)$batch['boxes_free']);
        $this->assertSame(3.0, (float)$batch['boxes_reserved']);
        $this->assertSame(30.0, (float)$batch['boxes_remaining']);

        $product = $this->pdo->query('SELECT free_stock_boxes, reserved_stock_boxes, stock_status FROM products WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(7.0, (float)$product['free_stock_boxes']);
        $this->assertSame(3.0, (float)$product['reserved_stock_boxes']);
        $this->assertSame('in_stock', $product['stock_status']);

        $movement = $this->pdo->query('SELECT movement_type, stock_mode, boxes_delta FROM stock_movements ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('reserve', $movement['movement_type']);
        $this->assertSame('instant', $movement['stock_mode']);
        $this->assertSame(-3.0, (float)$movement['boxes_delta']);
    }


    public function testInstantReserveCanCreateNegativeFreeStockForDeficit(): void
    {
        $this->service->reserve(1, 1, 12, 123, 'instant');

        $batch = $this->pdo->query('SELECT boxes_free, boxes_reserved FROM purchase_batches WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(-2.0, (float)$batch['boxes_free']);
        $this->assertSame(12.0, (float)$batch['boxes_reserved']);

        $product = $this->pdo->query('SELECT free_stock_boxes, reserved_stock_boxes FROM products WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(-2.0, (float)$product['free_stock_boxes']);
        $this->assertSame(12.0, (float)$product['reserved_stock_boxes']);
    }

    public function testSellMovesFromReservedToSold(): void
    {
        $this->service->reserve(1, 1, 5, 42, 'instant');
        $this->service->sell(1, 1, 5, 42);

        $batch = $this->pdo->query('SELECT boxes_reserved, boxes_sold, boxes_remaining FROM purchase_batches WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0.0, (float)$batch['boxes_reserved']);
        $this->assertSame(5.0, (float)$batch['boxes_sold']);
        $this->assertSame(25.0, (float)$batch['boxes_remaining']);
    }


    public function testSellAvailableMovesFreeStockDirectlyToSold(): void
    {
        $this->service->sellAvailable(1, 1, 4, 91, 'instant');

        $batch = $this->pdo->query('SELECT boxes_free, boxes_reserved, boxes_sold, boxes_remaining FROM purchase_batches WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(6.0, (float)$batch['boxes_free']);
        $this->assertSame(0.0, (float)$batch['boxes_reserved']);
        $this->assertSame(4.0, (float)$batch['boxes_sold']);
        $this->assertSame(26.0, (float)$batch['boxes_remaining']);

        $movement = $this->pdo->query('SELECT movement_type, stock_mode, boxes_delta FROM stock_movements ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('sale', $movement['movement_type']);
        $this->assertSame('instant', $movement['stock_mode']);
        $this->assertSame(-4.0, (float)$movement['boxes_delta']);
    }

    public function testReturnSaleRestoresStockAndCreatesSchemaCompatibleMovement(): void
    {
        $this->service->sellAvailable(1, 1, 4, 91, 'instant');
        $this->service->returnSale(1, 1, 4, 91, 'instant');

        $batch = $this->pdo->query('SELECT boxes_free, boxes_sold, boxes_remaining FROM purchase_batches WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(10.0, (float)$batch['boxes_free']);
        $this->assertSame(0.0, (float)$batch['boxes_sold']);
        $this->assertSame(30.0, (float)$batch['boxes_remaining']);

        $movement = $this->pdo->query('SELECT movement_type, stock_mode, boxes_delta FROM stock_movements ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('return_to_stock', $movement['movement_type']);
        $this->assertSame('instant', $movement['stock_mode']);
        $this->assertSame(4.0, (float)$movement['boxes_delta']);
    }

    public function testPreorderReserveUsesPlannedLimitAndIncreasesReserved(): void
    {
        $this->service->reserve(1, 1, 2, 77, 'preorder');

        $batch = $this->pdo->query('SELECT boxes_free, boxes_reserved, boxes_remaining FROM purchase_batches WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(10.0, (float)$batch['boxes_free']);
        $this->assertSame(2.0, (float)$batch['boxes_reserved']);
        $this->assertSame(30.0, (float)$batch['boxes_remaining']);

        $movement = $this->pdo->query('SELECT movement_type, stock_mode, boxes_delta FROM stock_movements ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('reserve', $movement['movement_type']);
        $this->assertSame('preorder', $movement['stock_mode']);
        $this->assertSame(-2.0, (float)$movement['boxes_delta']);
    }

    public function testUnreserveRestoresModeStockAndDecreasesReserved(): void
    {
        $this->service->reserve(1, 1, 4, 55, 'instant');
        $this->service->unreserve(1, 1, 4, 55, 'instant');

        $batch = $this->pdo->query('SELECT boxes_free, boxes_reserved, boxes_remaining FROM purchase_batches WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(10.0, (float)$batch['boxes_free']);
        $this->assertSame(0.0, (float)$batch['boxes_reserved']);
        $this->assertSame(30.0, (float)$batch['boxes_remaining']);
    }

    public function testWriteOffRejectsInvariantViolationAndRollsBack(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Batch invariant failed: boxes_remaining mismatch.');

        $this->service->writeOff(1, 31, 7, 'Too much write off');
    }

    public function testGetAvailableBoxesReadsPurchaseBatchesNotProducts(): void
    {
        $this->pdo->exec('UPDATE products SET free_stock_boxes = 999 WHERE id = 1');
        $this->pdo->exec('UPDATE purchase_batches SET boxes_free = 4 WHERE id = 1');

        $available = $this->service->getAvailableBoxes(1, 'instant');
        $this->assertSame(4.0, $available);
    }

    public function testLegacyProjectionCanBeDisabledViaEnvFlag(): void
    {
        putenv('LEGACY_PRODUCT_PROJECTION_ENABLED=0');
        $service = new StockService($this->pdo);

        $service->reserve(1, 1, 2, 88, 'instant');

        $product = $this->pdo->query('SELECT free_stock_boxes, reserved_stock_boxes FROM products WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(10.0, (float)$product['free_stock_boxes']);
        $this->assertSame(0.0, (float)$product['reserved_stock_boxes']);

        putenv('LEGACY_PRODUCT_PROJECTION_ENABLED');
    }

}
