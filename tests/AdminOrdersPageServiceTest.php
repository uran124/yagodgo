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
            created_at TEXT,
            last_checkout_comment TEXT NULL,
            delivery_distance_km REAL NULL,
            delivery_distance_m INTEGER NULL,
            delivery_distance_provider TEXT NULL
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
            preorder_price_per_box REAL DEFAULT 0,
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
        $this->pdo->exec('CREATE TABLE production_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER NULL,
            executor_type TEXT NULL,
            executor_id INTEGER NULL,
            fulfillment_model TEXT NOT NULL DEFAULT "by_berrygo_on_site",
            production_location TEXT NOT NULL DEFAULT "shop",
            status TEXT NOT NULL DEFAULT "new",
            production_deadline TEXT NULL,
            handover_deadline TEXT NULL,
            bonus_type TEXT NOT NULL DEFAULT "internal_bonus",
            bonus_value REAL NOT NULL DEFAULT 0,
            bonus_amount_locked REAL NOT NULL DEFAULT 0,
            materials_required TEXT NULL,
            materials_delivery_required INTEGER NOT NULL DEFAULT 0,
            materials_delivery_cost REAL NOT NULL DEFAULT 0,
            result_delivery_required INTEGER NOT NULL DEFAULT 0,
            result_delivery_cost REAL NOT NULL DEFAULT 0,
            estimated_materials_cost REAL NOT NULL DEFAULT 0,
            estimated_acquiring_cost REAL NOT NULL DEFAULT 0,
            estimated_margin_amount REAL NULL,
            minimum_margin_amount REAL NOT NULL DEFAULT 0,
            margin_status TEXT NOT NULL DEFAULT "unknown",
            manager_comment TEXT NULL,
            assigned_at TEXT NULL,
            started_at TEXT NULL,
            photo_uploaded_at TEXT NULL,
            approved_at TEXT NULL,
            handed_over_at TEXT NULL,
            completed_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE production_job_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id INTEGER NOT NULL,
            order_id INTEGER NOT NULL,
            from_status TEXT NULL,
            to_status TEXT NOT NULL,
            changed_by_user_id INTEGER NULL,
            changed_by_role TEXT NULL,
            comment TEXT NULL,
            created_at TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE production_job_photos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id INTEGER NOT NULL,
            order_id INTEGER NOT NULL,
            image_path TEXT NOT NULL,
            photo_type TEXT NOT NULL DEFAULT "ready",
            review_status TEXT NOT NULL DEFAULT "pending",
            reviewed_by_user_id INTEGER NULL,
            reviewed_at TEXT NULL,
            review_comment TEXT NULL,
            created_at TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE production_executor_settings (
            user_id INTEGER PRIMARY KEY,
            executor_type TEXT NOT NULL DEFAULT "internal_staff",
            is_active INTEGER NOT NULL DEFAULT 1,
            can_work_on_site INTEGER NOT NULL DEFAULT 1,
            can_work_remote INTEGER NOT NULL DEFAULT 0,
            current_mode TEXT NOT NULL DEFAULT "offline",
            default_fulfillment_model TEXT NOT NULL DEFAULT "by_berrygo_on_site",
            default_bonus_percent REAL NOT NULL DEFAULT 10,
            default_bonus_amount REAL NOT NULL DEFAULT 0,
            max_active_jobs INTEGER NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');

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
        $this->pdo->exec("INSERT INTO production_jobs (id, order_id, product_id, executor_type, executor_id, fulfillment_model, production_location, status, production_deadline, handover_deadline, bonus_type, bonus_value, bonus_amount_locked, materials_delivery_required, materials_delivery_cost, result_delivery_required, result_delivery_cost, manager_comment, created_at, updated_at)
            VALUES (5, 1, 1, 'internal_staff', 2, 'by_berrygo_on_site', 'shop', 'assigned', '2026-06-16 15:00:00', '2026-06-16 16:00:00', 'internal_bonus', 10, 300, 0, 0, 0, 0, 'Сделать на смене', '2026-06-16 12:00:00', '2026-06-16 12:00:00')");
        $this->pdo->exec("INSERT INTO production_job_events (job_id, order_id, from_status, to_status, changed_by_user_id, changed_by_role, comment, created_at)
            VALUES (5, 1, 'new', 'assigned', 3, 'admin', 'production_job_assigned', '2026-06-16 12:05:00')");
        $this->pdo->exec("INSERT INTO production_job_photos (job_id, order_id, image_path, photo_type, review_status, created_at)
            VALUES (5, 1, '/uploads/production/ready.webp', 'ready', 'pending', '2026-06-16 12:10:00')");
        $this->pdo->exec("INSERT INTO production_executor_settings (user_id, executor_type, is_active, current_mode, default_fulfillment_model, default_bonus_percent, default_bonus_amount, max_active_jobs, created_at, updated_at) VALUES
            (2, 'internal_staff', 1, 'on_shift', 'by_berrygo_on_site', 10, 300, 2, '2026-06-16 12:00:00', '2026-06-16 12:00:00'),
            (3, 'internal_staff', 1, 'offline', 'by_berrygo_on_site', 10, 0, 1, '2026-06-16 12:00:00', '2026-06-16 12:00:00')");

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
        $this->assertCount(1, $data['productionJobs']);
        $this->assertSame(5, (int)$data['productionJobs'][0]['id']);
        $this->assertSame('assigned', $data['productionJobs'][0]['status']);
        $this->assertSame('production_job_assigned', $data['productionJobs'][0]['events'][0]['comment']);
        $this->assertCount(1, $data['productionJobs'][0]['photos']);
        $this->assertSame('/uploads/production/ready.webp', $data['productionJobs'][0]['photos'][0]['image_path']);
        $this->assertSame('pending', $data['productionJobs'][0]['photos'][0]['review_status']);
        $this->assertCount(1, $data['productionExecutors']);
        $this->assertSame('on_shift', $data['productionExecutors'][0]['current_mode']);
    }

    public function testBuildCreateDataReturnsManualOrderOffersWithoutLegacyPurchaseBatchList(): void
    {
        $data = $this->service->buildCreateData();

        $this->assertArrayNotHasKey('purchaseBatches', $data);
        $this->assertCount(1, $data['inStockOffers']);
        $this->assertSame(10, (int)$data['inStockOffers'][0]['purchase_batch_id']);
        $this->assertSame('instant', $data['inStockOffers'][0]['stock_mode']);
        $this->assertSame(1500.0, (float)$data['inStockOffers'][0]['price_per_box']);
        $this->assertSame(12.0, (float)$data['inStockOffers'][0]['available_boxes']);

        $this->assertCount(1, $data['preorderOffers']);
        $this->assertSame(11, (int)$data['preorderOffers'][0]['purchase_batch_id']);
        $this->assertSame('preorder', $data['preorderOffers'][0]['stock_mode']);
        $this->assertSame(1400.0, (float)$data['preorderOffers'][0]['price_per_box']);
        $this->assertSame(7.0, (float)$data['preorderOffers'][0]['available_boxes']);
    }
}
