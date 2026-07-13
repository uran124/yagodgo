<?php
namespace Tests;

use App\Services\ManualOrderAvailabilityService;
use PDO;
use PHPUnit\Framework\TestCase;

class ManualOrderAvailabilityServiceTest extends TestCase
{
    private PDO $pdo;
    private ManualOrderAvailabilityService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE product_types (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, product_type_id INTEGER, variety TEXT, unit TEXT, box_size REAL, box_unit TEXT, image_path TEXT, is_active INTEGER)');
        $this->pdo->exec('CREATE TABLE purchase_batches (id INTEGER PRIMARY KEY, product_id INTEGER, status TEXT, purchased_at TEXT, box_size_snapshot REAL, box_unit_snapshot TEXT, boxes_free REAL, boxes_total REAL, boxes_reserved REAL, instant_price_per_box REAL, preorder_price_per_box REAL)');
        $this->pdo->exec("INSERT INTO product_types (id, name) VALUES (1, 'Клубника')");
        $this->pdo->exec("INSERT INTO products (id, product_type_id, variety, unit, box_size, box_unit, image_path, is_active) VALUES (10, 1, 'Клери', 'кг', 2, 'кг', '/img.jpg', 1), (11, 1, 'Азия', 'кг', 2, 'кг', NULL, 0)");
        $this->service = new ManualOrderAvailabilityService($this->pdo);
    }

    public function testBuildOffersFiltersInStockAndConfirmedPreorders(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, purchased_at, box_size_snapshot, box_unit_snapshot, boxes_free, boxes_total, boxes_reserved, instant_price_per_box, preorder_price_per_box) VALUES
            (1, 10, 'purchased', '2026-07-04', 2, 'кг', 3, 3, 0, 1500, 0),
            (2, 10, 'planned', '2026-07-18', 2, 'кг', 0, 5, 2, 0, 1350),
            (3, 10, 'planned', NULL, 2, 'кг', 0, 5, 0, 0, 1350),
            (4, 11, 'purchased', '2026-07-04', 2, 'кг', 5, 5, 0, 1500, 0)");

        $offers = $this->service->buildOffers();

        $this->assertCount(1, $offers['inStockOffers']);
        $this->assertSame(1, $offers['inStockOffers'][0]['purchase_batch_id']);
        $this->assertSame('instant', $offers['inStockOffers'][0]['stock_mode']);
        $this->assertCount(1, $offers['preorderOffers']);
        $this->assertSame(2, $offers['preorderOffers'][0]['purchase_batch_id']);
        $this->assertSame(3.0, $offers['preorderOffers'][0]['available_boxes']);
    }

    public function testInstantFifoSplitsOneProductAcrossBatches(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, purchased_at, box_size_snapshot, box_unit_snapshot, boxes_free, boxes_total, boxes_reserved, instant_price_per_box, preorder_price_per_box) VALUES
            (5, 10, 'purchased', '2026-07-01', 2, 'кг', 3, 3, 0, 1400, 0),
            (6, 10, 'arrived', '2026-07-03', 2, 'кг', 4, 4, 0, 1500, 0)");

        $allocations = $this->service->allocateInstantFifo(10, 5);

        $this->assertSame([5, 6], array_column($allocations, 'purchase_batch_id'));
        $this->assertSame([3.0, 2.0], array_column($allocations, 'boxes'));
    }
}
