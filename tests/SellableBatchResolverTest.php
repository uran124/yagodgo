<?php
namespace Tests;

use App\Services\SellableBatchResolver;
use PDO;
use PHPUnit\Framework\TestCase;

class SellableBatchResolverTest extends TestCase
{
    private PDO $pdo;
    private SellableBatchResolver $resolver;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE purchase_batches (
            id INTEGER PRIMARY KEY,
            product_id INTEGER,
            status TEXT,
            boxes_free REAL DEFAULT 0,
            boxes_total REAL DEFAULT 0,
            boxes_reserved REAL DEFAULT 0,
            boxes_discount REAL DEFAULT 0,
            preorder_price_per_box REAL DEFAULT 0,
            instant_price_per_box REAL DEFAULT 0,
            discount_price_per_box REAL DEFAULT 0,
            purchased_at TEXT
        )');
        $this->pdo->exec('CREATE TABLE products (
            id INTEGER PRIMARY KEY,
            preorder_price_per_box REAL DEFAULT 0,
            price REAL DEFAULT 0
        )');

        $this->resolver = new SellableBatchResolver($this->pdo);
    }

    public function testResolveInstantUsesOldestSellableBatch(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, boxes_free, instant_price_per_box, purchased_at) VALUES
            (2, 10, 'arrived', 5, 1800, '2026-05-03 10:00:00'),
            (1, 10, 'purchased', 3, 1700, '2026-05-01 10:00:00')");

        $row = $this->resolver->resolveForProduct(10, 'instant');
        $this->assertNotNull($row);
        $this->assertSame(1, (int)$row['id']);
        $this->assertSame(1700.0, (float)$row['price_per_box']);
        $this->assertSame(3.0, (float)$row['boxes_available']);
    }

    public function testResolveDiscountUsesDiscountStockAndPrice(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, boxes_discount, discount_price_per_box, purchased_at) VALUES
            (5, 11, 'purchased', 2, 990, '2026-05-02 10:00:00')");

        $row = $this->resolver->resolveForProduct(11, 'discount_stock');
        $this->assertNotNull($row);
        $this->assertSame(5, (int)$row['id']);
        $this->assertSame(990.0, (float)$row['price_per_box']);
        $this->assertSame(2.0, (float)$row['boxes_available']);
    }

    public function testResolvePreorderRejectsPlannedBatchWithoutAvailableQuantity(): void
    {
        $this->pdo->exec("INSERT INTO products (id, preorder_price_per_box, price) VALUES (12, 1500, 1700)");
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, boxes_total, boxes_free, boxes_reserved, preorder_price_per_box, purchased_at) VALUES
            (7, 12, 'planned', 0, 0, 5, 1400, '2026-05-02 10:00:00')");

        $row = $this->resolver->resolveForProduct(12, 'preorder');

        $this->assertNull($row);
    }

    public function testResolvePreorderUsesConfirmedFutureBatchWithDeclaredPriceAndAvailability(): void
    {
        $this->pdo->exec("INSERT INTO products (id, preorder_price_per_box, price) VALUES (13, 1500, 1700)");
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, boxes_total, boxes_free, boxes_reserved, preorder_price_per_box, purchased_at) VALUES
            (8, 13, 'planned', 4, 0, 1, 1450, '2026-05-04 10:00:00')");

        $row = $this->resolver->resolveForProduct(13, 'preorder');

        $this->assertNotNull($row);
        $this->assertSame(8, (int)$row['id']);
        $this->assertSame(1450.0, (float)$row['price_per_box']);
        $this->assertSame(3.0, (float)$row['boxes_available']);
    }

}
