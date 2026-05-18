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
        $this->pdo->exec('CREATE TABLE purchase_batches (id INTEGER PRIMARY KEY, status TEXT)');
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
            created_at TEXT
        )');

        $this->pdo->exec("INSERT INTO product_types (id, name, alias) VALUES (1, 'Клубника', 'klubnika')");
        $this->pdo->exec("INSERT INTO users (id, name, company_name) VALUES (1, 'Seller One', 'Berry Seller')");
        $this->pdo->exec("INSERT INTO content_categories (id, alias) VALUES (1, 'news')");
        $this->pdo->exec("INSERT INTO purchase_batches (id, status) VALUES (11, 'arrived'), (12, 'purchased'), (13, 'planned')");

        $this->pdo->exec(
            "INSERT INTO products (id, alias, product_type_id, seller_id, variety, description, origin_country, box_size, box_unit, price, sale_price, is_active, image_path, delivery_date, current_purchase_batch_id, free_stock_boxes, discount_stock_boxes, stock_status) VALUES
            (1, 'sale-product', 1, NULL, 'Клери', 'sale', 'KG', 1, 'кг', 1000, 800, 1, '/sale.jpg', '2025-03-25', 11, 0, 3, 'in_stock'),
            (2, 'regular-product', 1, NULL, 'Азия', 'regular', 'KG', 1, 'кг', 900, 0, 1, '/regular.jpg', '2025-03-24', 12, 5, 0, 'in_stock'),
            (3, 'seller-product', 1, 1, 'Seller', 'seller', 'KG', 1, 'кг', 1200, 0, 1, '/seller.jpg', '2025-03-26', NULL, 0, 0, 'sold_out'),
            (4, 'preorder-product', 1, NULL, 'Pre', 'preorder', 'KG', 1, 'кг', 1100, 0, 1, '/pre.jpg', NULL, 13, 0, 0, 'preorder')"
        );
        $this->pdo->exec(
            "INSERT INTO materials (id, alias, category_id, title, short_desc, image_path, created_at) VALUES
            (1, 'material-1', 1, 'Материал', 'Коротко', '/m.jpg', '2025-03-20 10:00:00')"
        );

        $this->service = new ClientCatalogService($this->pdo);
    }

    public function testHomePageDataGroupsProductsByScenario(): void
    {
        $data = $this->service->getHomePageData();

        $this->assertSame('sale-product', $data['saleProducts'][0]['alias']);
        $this->assertSame('regular-product', $data['regularProducts'][0]['alias']);
        $this->assertSame('seller-product', $data['sellerProducts'][0]['alias']);
        $this->assertSame('preorder-product', $data['preorderProducts'][0]['alias']);
        $this->assertSame('material-1', $data['materials'][0]['mat_alias']);
    }

    public function testCatalogDataReturnsProductsTypesAndDebugInfo(): void
    {
        $data = $this->service->getCatalogData('2025-03-23');

        $this->assertCount(4, $data['products']);
        $this->assertSame(4, $data['debugData']['productsCount']);
        $this->assertSame('2025-03-23', $data['debugData']['today']);
        $this->assertSame('Клубника', $data['types'][0]['name']);
        $this->assertSame('sale-product', $data['products'][0]['alias']);
        $this->assertSame('sale', $data['products'][0]['catalog_section']);
        $this->assertSame('in_stock', $data['products'][1]['catalog_section']);
        $this->assertSame('seller', $data['products'][2]['catalog_section']);
        $this->assertSame('preorder', $data['products'][3]['catalog_section']);
    }
}
