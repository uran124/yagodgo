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
            is_active INTEGER
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
            (2, 'Manager', '79000000002', 'manager', NULL, 0)");
        $this->pdo->exec("INSERT INTO addresses (id, user_id, street, is_primary, created_at) VALUES
            (1, 1, 'Main st', 1, '2025-03-20 10:00:00'),
            (2, 1, 'Second st', 0, '2025-03-21 10:00:00')");
        $this->pdo->exec("INSERT INTO delivery_slots (id, time_from, time_to) VALUES (1, '09:00', '12:00')");
        $this->pdo->exec("INSERT INTO orders (id, user_id, address_id, slot_id, status, total_amount, delivery_date, points_used, coupon_code, discount_applied, comment, created_at)
            VALUES (1, 1, 1, 1, 'new', 1200, '2025-03-25', 70, 'POINTS70', 70, 'comment', '2025-03-20 10:00:00')");
        $this->pdo->exec("INSERT INTO product_types (id, name) VALUES (1, 'Клубника')");
        $this->pdo->exec("INSERT INTO products (id, product_type_id, variety, unit, box_size, box_unit, is_active) VALUES
            (1, 1, 'Клери', 'кг', 2, 'кг', 1)");
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
    }
}
