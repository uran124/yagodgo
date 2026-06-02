<?php
namespace Tests;

use App\Services\AdminOrdersPageService;
use PDO;
use PHPUnit\Framework\TestCase;

class AdminOrdersPageServiceTest extends TestCase
{
    private PDO $pdo;
    private AdminOrdersPageService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT,
            phone TEXT,
            role TEXT,
            referred_by INTEGER NULL,
            referral_code TEXT NULL,
            has_used_referral_coupon INTEGER DEFAULT 0
        )');
        $this->pdo->exec('CREATE TABLE addresses (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            street TEXT,
            is_primary INTEGER DEFAULT 0,
            created_at TEXT
        )');
        $this->pdo->exec('CREATE TABLE delivery_slots (id INTEGER PRIMARY KEY, time_from TEXT, time_to TEXT)');
        $this->pdo->exec('CREATE TABLE orders (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            address_id INTEGER,
            slot_id INTEGER,
            status TEXT,
            total_amount REAL,
            delivery_date TEXT,
            points_used INTEGER DEFAULT 0,
            coupon_code TEXT NULL,
            discount_applied INTEGER DEFAULT 0,
            comment TEXT NULL,
            created_by_user_id INTEGER NULL,
            created_at TEXT
        )');
        $this->pdo->exec('CREATE TABLE product_types (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec('CREATE TABLE products (
            id INTEGER PRIMARY KEY,
            product_type_id INTEGER,
            variety TEXT,
            unit TEXT,
            box_size REAL,
            box_unit TEXT,
            price REAL DEFAULT 0,
            image_path TEXT NULL,
            is_active INTEGER
        )');
        $this->pdo->exec('CREATE TABLE purchase_batches (
            id INTEGER PRIMARY KEY,
            product_id INTEGER,
            status TEXT,
            purchased_at TEXT,
            box_size_snapshot REAL,
            box_unit_snapshot TEXT,
            boxes_free REAL,
            boxes_total REAL DEFAULT 0,
            boxes_reserved REAL DEFAULT 0,
            instant_price_per_box REAL,
            preorder_price_per_box REAL
        )');
        $this->pdo->exec('CREATE TABLE order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER,
            product_id INTEGER,
            quantity REAL,
            boxes REAL,
            unit_price REAL
        )');
        $this->pdo->exec('CREATE TABLE points_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER,
            created_at TEXT
        )');
        $this->pdo->exec('CREATE TABLE coupons (code TEXT PRIMARY KEY, type TEXT, discount INTEGER, points INTEGER)');

        $this->pdo->exec("INSERT INTO users (id, name, phone, role, referral_code, has_used_referral_coupon) VALUES
            (1, 'Client', '79000000001', 'client', 'REF01', 0),
            (2, 'Manager', '79000000002', 'manager', NULL, 0),
            (3, 'Admin', '79000000003', 'admin', NULL, 0)");
        $this->pdo->exec("INSERT INTO addresses (id, user_id, street, is_primary, created_at) VALUES
            (1, 1, 'Main st', 1, '2025-03-20 10:00:00'),
            (2, 1, 'Second st', 0, '2025-03-21 10:00:00')");
        $this->pdo->exec("INSERT INTO delivery_slots (id, time_from, time_to) VALUES (1, '09:00', '12:00')");
        $this->pdo->exec("INSERT INTO orders (id, user_id, address_id, slot_id, status, total_amount, delivery_date, points_used, coupon_code, discount_applied, comment, created_by_user_id, created_at)
            VALUES (1, 1, 1, 1, 'new', 1200, '2025-03-25', 70, 'POINTS70', 70, 'comment', 3, '2025-03-20 10:00:00')");
        $this->pdo->exec("INSERT INTO product_types (id, name) VALUES (1, 'Клубника')");
        $this->pdo->exec("INSERT INTO products (id, product_type_id, variety, unit, box_size, box_unit, price, image_path, is_active) VALUES
            (1, 1, 'Клери', 'кг', 2, 'кг', 600, '/berry.jpg', 1)");
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, purchased_at, box_size_snapshot, box_unit_snapshot, boxes_free, boxes_total, boxes_reserved, instant_price_per_box, preorder_price_per_box) VALUES
            (10, 1, 'arrived', '2026-05-29 08:00:00', 2, 'кг', 12, 12, 0, 1500, 1300),
            (11, 1, 'planned', '2026-06-01 08:00:00', 2, 'кг', 0, 10, 3, 1600, 1400),
            (12, 1, 'planned', '2026-06-02 08:00:00', 2, 'кг', 0, 5, 1, 0, 0)");
        $this->pdo->exec("INSERT INTO order_items (order_id, product_id, quantity, boxes, unit_price) VALUES
            (1, 1, 2, 1, 600)");
        $this->pdo->exec("INSERT INTO points_transactions (order_id, created_at) VALUES (1, '2025-03-20 11:00:00')");
        $this->pdo->exec("INSERT INTO coupons (code, type, discount, points) VALUES ('POINTS70', 'points', 0, 50)");

        $this->service = new AdminOrdersPageService($this->pdo);
    }

    public function testFindShowDataBuildsOrderPagePayload(): void
    {
        $data = $this->service->findShowData(1);

        $this->assertNotNull($data);
        $this->assertSame('Client', $data['order']['client_name']);
        $this->assertCount(1, $data['items']);
        $this->assertCount(1, $data['transactions']);
        $this->assertSame('POINTS70', $data['coupon']['code']);
        $this->assertSame(20, $data['pointsFromBalance']);
        $this->assertCount(2, $data['addresses']);
        $this->assertCount(1, $data['slots']);
        $this->assertCount(1, $data['products']);
        $this->assertSame(1500.0, (float)$data['products'][0]['price_per_box']);
    }

    public function testBuildCreateDataReturnsSellablePurchaseBatches(): void
    {
        $data = $this->service->buildCreateData();

        $this->assertCount(3, $data['purchaseBatches']);
        $this->assertSame(10, (int)$data['purchaseBatches'][0]['purchase_batch_id']);
        $this->assertSame('in_stock', $data['purchaseBatches'][0]['mode_group']);
        $this->assertSame(1500.0, (float)$data['purchaseBatches'][0]['price_per_box']);
        $this->assertSame(12.0, (float)$data['purchaseBatches'][0]['available_boxes']);
        $this->assertSame(11, (int)$data['purchaseBatches'][1]['purchase_batch_id']);
        $this->assertSame('preorder', $data['purchaseBatches'][1]['mode_group']);
        $this->assertSame(1400.0, (float)$data['purchaseBatches'][1]['price_per_box']);
        $this->assertSame(12, (int)$data['purchaseBatches'][2]['purchase_batch_id']);
        $this->assertSame('preorder', $data['purchaseBatches'][2]['mode_group']);
        $this->assertSame(600.0, (float)$data['purchaseBatches'][2]['price_per_box']);
        $this->assertSame(4.0, (float)$data['purchaseBatches'][2]['available_boxes']);
    }
}
