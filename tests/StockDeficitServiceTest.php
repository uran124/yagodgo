<?php
namespace Tests;

use App\Services\StockDeficitService;
use PDO;
use PHPUnit\Framework\TestCase;

class StockDeficitServiceTest extends TestCase
{
    private PDO $pdo;
    private StockDeficitService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE product_types (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, product_type_id INTEGER, variety TEXT)');
        $this->pdo->exec('CREATE TABLE purchase_batches (
            id INTEGER PRIMARY KEY,
            product_id INTEGER,
            status TEXT,
            boxes_free REAL DEFAULT 0,
            boxes_total REAL DEFAULT 0,
            boxes_reserved REAL DEFAULT 0,
            purchased_at TEXT
        )');
        $this->pdo->exec('CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT, updated_at TEXT)');
        $this->pdo->exec("INSERT INTO product_types (id, name) VALUES (1, 'Клубника'), (2, 'Манго')");
        $this->pdo->exec("INSERT INTO products (id, product_type_id, variety) VALUES (10, 1, 'Альба'), (20, 2, 'Кент')");

        $this->service = new StockDeficitService($this->pdo);
    }

    public function testSummaryCombinesInstantAndPreorderDeficits(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, boxes_free, boxes_total, boxes_reserved, purchased_at) VALUES
            (1, 10, 'purchased', -2, 10, 12, '2026-06-01 10:00:00'),
            (2, 10, 'planned', 0, 0, 3, '2026-06-02 10:00:00'),
            (3, 20, 'planned', 0, 5, 7, '2026-06-03 10:00:00'),
            (4, 20, 'arrived', 4, 5, 0, '2026-06-04 10:00:00')");

        $summary = $this->service->getSummary();

        $this->assertSame(7.0, (float)$summary['total_deficit_boxes']);
        $this->assertSame(2, (int)$summary['products_count']);
        $this->assertSame(10, (int)$summary['rows'][0]['product_id']);
        $this->assertSame(5.0, (float)$summary['rows'][0]['total_deficit_boxes']);
        $this->assertSame(2.0, (float)$summary['rows'][0]['instant_deficit_boxes']);
        $this->assertSame(3.0, (float)$summary['rows'][0]['preorder_deficit_boxes']);
    }

    public function testNoDeficitReturnsEmptyRows(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, boxes_free, boxes_total, boxes_reserved, purchased_at) VALUES
            (1, 10, 'purchased', 2, 10, 1, '2026-06-01 10:00:00'),
            (2, 20, 'planned', 0, 5, 3, '2026-06-03 10:00:00')");

        $summary = $this->service->getSummary();

        $this->assertSame(0.0, (float)$summary['total_deficit_boxes']);
        $this->assertSame(0, (int)$summary['products_count']);
        $this->assertSame([], $summary['rows']);
    }
}
