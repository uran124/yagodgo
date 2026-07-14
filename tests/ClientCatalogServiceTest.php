<?php
namespace Tests;

use App\Services\ClientCatalogService;
use PDO;
use PHPUnit\Framework\TestCase;

class ClientCatalogServiceTest extends TestCase
{
    private PDO $pdo;
    private ClientCatalogService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE product_types (id INTEGER PRIMARY KEY, name TEXT, alias TEXT)');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, company_name TEXT)');
        $this->pdo->exec('CREATE TABLE purchase_batches (
            id INTEGER PRIMARY KEY,
            product_id INTEGER,
            status TEXT,
            boxes_free REAL DEFAULT 0,
            boxes_discount REAL DEFAULT 0,
            boxes_total REAL DEFAULT 0,
            boxes_reserved REAL DEFAULT 0,
            instant_price_per_box REAL DEFAULT 0,
            preorder_price_per_box REAL DEFAULT 0,
            discount_price_per_box REAL DEFAULT 0,
            purchased_at TEXT
        )');
        $this->pdo->exec('CREATE TABLE purchase_batch_photos (
            id INTEGER PRIMARY KEY,
            purchase_batch_id INTEGER,
            image_path TEXT,
            created_at TEXT
        )');
        $this->pdo->exec('CREATE TABLE products (
            id INTEGER PRIMARY KEY,
            alias TEXT,
            product_type_id INTEGER,
            seller_id INTEGER NULL,
            variety TEXT,
            description TEXT,
            origin_country TEXT,
            box_size REAL,
            box_unit TEXT,
            price REAL,
            preorder_price_per_box REAL DEFAULT 0,
            sale_price REAL,
            is_active INTEGER,
            image_path TEXT,
            delivery_date TEXT NULL,
            current_purchase_batch_id INTEGER NULL,
            free_stock_boxes REAL DEFAULT 0,
            discount_stock_boxes REAL DEFAULT 0,
            stock_status TEXT DEFAULT "sold_out"
        )');
        $this->pdo->exec('CREATE TABLE content_categories (id INTEGER PRIMARY KEY, alias TEXT)');
        $this->pdo->exec('CREATE TABLE materials (
            id INTEGER PRIMARY KEY,
            alias TEXT,
            category_id INTEGER,
            title TEXT,
            short_desc TEXT,
            image_path TEXT,
            created_at TEXT,
            is_active INTEGER DEFAULT 1,
            show_on_home INTEGER DEFAULT 1
        )');

        $this->pdo->exec("INSERT INTO product_types (id, name, alias) VALUES (1, 'Клубника', 'klubnika')");
        $this->pdo->exec("INSERT INTO users (id, name, company_name) VALUES (1, 'Seller One', 'Berry Seller')");
        $this->pdo->exec("INSERT INTO content_categories (id, alias) VALUES (1, 'news')");
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, boxes_free, boxes_discount, boxes_total, boxes_reserved, instant_price_per_box, preorder_price_per_box, discount_price_per_box, purchased_at) VALUES
            (11, 1, 'arrived', 0, 3, 3, 0, 1000, 900, 800, '2025-03-25 08:00:00'),
            (12, 2, 'purchased', 5, 0, 5, 0, 900, 810, 0, '2025-03-24 08:00:00'),
            (13, 4, 'planned', 0, 0, 6, 1, 0, 1070, 0, '2025-03-27 08:00:00'),
            (14, 2, 'planned', 0, 0, 4, 0, 0, 810, 0, '2025-03-29 08:00:00'),
            (15, 1, 'planned', 0, 0, 4, 1, 0, 0, 0, '2025-03-30 08:00:00')
        ");
        $this->pdo->exec("INSERT INTO purchase_batch_photos (id, purchase_batch_id, image_path, created_at) VALUES
            (1, 12, '/batch-regular.jpg', '2025-03-24 09:00:00'),
            (2, 13, '', '2025-03-27 09:00:00')
        ");

        $this->pdo->exec(
            "INSERT INTO products (id, alias, product_type_id, seller_id, variety, description, origin_country, box_size, box_unit, price, preorder_price_per_box, sale_price, is_active, image_path, delivery_date, current_purchase_batch_id, free_stock_boxes, discount_stock_boxes, stock_status) VALUES
            (1, 'sale-product', 1, NULL, 'Клери', 'sale', 'KG', 1, 'кг', 1000, 0, 800, 1, '/sale.jpg', '2025-03-25', 11, 0, 3, 'in_stock'),
            (2, 'regular-product', 1, NULL, 'Азия', 'regular', 'KG', 1, 'кг', 900, 0, 0, 1, '/regular.jpg', '2025-03-24', 12, 5, 0, 'in_stock'),
            (3, 'seller-product', 1, 1, 'Seller', 'seller', 'KG', 1, 'кг', 1200, 0, 0, 1, '/seller.jpg', '2025-03-26', NULL, 0, 0, 'sold_out'),
            (4, 'preorder-product', 1, NULL, 'Pre', 'preorder', 'KG', 1, 'кг', 1100, 1070, 0, 1, '/pre.jpg', NULL, 13, 0, 0, 'preorder')"
        );
        $this->pdo->exec(
            "INSERT INTO materials (id, alias, category_id, title, short_desc, image_path, created_at, is_active, show_on_home) VALUES
            (1, 'material-1', 1, 'Материал', 'Коротко', '/m.jpg', '2025-03-20 10:00:00', 1, 1)"
        );

        $this->service = new ClientCatalogService($this->pdo);
    }

    public function testHomePageDataGroupsProductsByScenario(): void
    {
        $data = $this->service->getHomePageData();

        $this->assertSame('sale-product', $data['saleProducts'][0]['alias']);
        $this->assertSame('regular-product', $data['regularProducts'][0]['alias']);
        $this->assertSame(900.0, (float)$data['regularProducts'][0]['price']);
        $this->assertSame('/batch-regular.jpg', $data['regularProducts'][0]['image_path']);
        $this->assertSame('/regular.jpg', $data['regularProducts'][0]['product_image_path']);
        $this->assertSame('seller-product', $data['sellerProducts'][0]['alias']);
        $this->assertSame('preorder-product', $data['preorderProducts'][0]['alias']);
        $this->assertSame(1070.0, (float)$data['preorderProducts'][0]['price']);
        $this->assertSame('/pre.jpg', $data['preorderProducts'][0]['image_path']);
        $this->assertSame('material-1', $data['materials'][0]['mat_alias']);
        $this->assertSame(1, (int)$data['regularProducts'][0]['has_planned_batch']);
        $this->assertSame(12, (int)$data['regularProducts'][0]['instant_purchase_batch_id']);
        $this->assertSame(900.0, (float)$data['regularProducts'][0]['instant_price_per_box']);
        $this->assertSame(14, (int)$data['regularProducts'][0]['preorder_purchase_batch_id']);
        $this->assertSame('2025-03-29', $data['regularProducts'][0]['preorder_availability_date']);
        $saleByAlias = array_column($data['saleProducts'], null, 'alias');
        $this->assertSame(0, (int)$saleByAlias['sale-product']['has_planned_batch']);
        $this->assertSame(0, (int)($saleByAlias['sale-product']['preorder_purchase_batch_id'] ?? 0));
    }

    public function testCatalogDataReturnsProductsAndTypes(): void
    {
        $data = $this->service->getCatalogData('2025-03-23');

        $this->assertCount(4, $data['products']);
        $this->assertSame('Клубника', $data['types'][0]['name']);
        $productsByAlias = array_column($data['products'], null, 'alias');
        $this->assertSame('sale', $productsByAlias['sale-product']['catalog_section']);
        $this->assertSame(12, (int)$productsByAlias['regular-product']['instant_purchase_batch_id']);
        $this->assertSame(14, (int)$productsByAlias['regular-product']['preorder_purchase_batch_id']);
        $this->assertSame(0, (int)$productsByAlias['sale-product']['has_planned_batch']);
        $this->assertSame('in_stock', $productsByAlias['regular-product']['catalog_section']);
        $this->assertSame(900.0, (float)$productsByAlias['regular-product']['price']);
        $this->assertSame('/batch-regular.jpg', $productsByAlias['regular-product']['image_path']);
        $this->assertSame(1, (int)$productsByAlias['regular-product']['has_planned_batch']);
        $this->assertSame('seller', $productsByAlias['seller-product']['catalog_section']);
        $this->assertSame('preorder', $productsByAlias['preorder-product']['catalog_section']);
        $this->assertSame(1070.0, (float)$productsByAlias['preorder-product']['price']);
        $this->assertSame('/pre.jpg', $productsByAlias['preorder-product']['image_path']);
    }
}
