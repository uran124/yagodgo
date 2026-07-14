<?php
namespace Tests;

use App\Services\OrderGroupCreationService;
use PDO;
use PHPUnit\Framework\TestCase;

class OrderGroupCreationServiceTest extends TestCase
{
    private PDO $pdo;
    private OrderGroupCreationService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, points_balance INTEGER DEFAULT 0)');
        $this->pdo->exec('CREATE TABLE addresses (id INTEGER PRIMARY KEY, user_id INTEGER, street TEXT)');
        $this->pdo->exec('CREATE TABLE product_types (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, product_type_id INTEGER, variety TEXT, unit TEXT, box_size REAL, box_unit TEXT, image_path TEXT, is_active INTEGER, free_stock_boxes REAL DEFAULT 0, reserved_stock_boxes REAL DEFAULT 0, discount_stock_boxes REAL DEFAULT 0, sold_stock_boxes REAL DEFAULT 0, written_off_stock_boxes REAL DEFAULT 0, stock_status TEXT NULL)');
        $this->pdo->exec('CREATE TABLE purchase_batches (id INTEGER PRIMARY KEY, product_id INTEGER, status TEXT, purchased_at TEXT, box_size_snapshot REAL, box_unit_snapshot TEXT, boxes_free REAL, boxes_total REAL, boxes_reserved REAL, boxes_discount REAL DEFAULT 0, boxes_sold REAL DEFAULT 0, boxes_written_off REAL DEFAULT 0, boxes_remaining REAL DEFAULT 0, instant_price_per_box REAL, preorder_price_per_box REAL)');
        $this->pdo->exec('CREATE TABLE order_groups (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, created_by_user_id INTEGER NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, comment TEXT NULL)');
        $this->pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, order_group_id INTEGER NULL, user_id INTEGER, address_id INTEGER, slot_id INTEGER NULL, status TEXT, total_amount INTEGER, discount_applied INTEGER DEFAULT 0, points_used INTEGER DEFAULT 0, points_accrued INTEGER DEFAULT 0, coupon_code TEXT NULL, delivery_date TEXT, created_by_user_id INTEGER NULL, created_at TEXT, order_mode TEXT, purchase_batch_id INTEGER NULL, delivery_fee INTEGER DEFAULT 0, delivery_comment TEXT NULL)');
        $this->pdo->exec('CREATE TABLE order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, product_id INTEGER, quantity REAL, boxes REAL, unit_price REAL, stock_mode TEXT, purchase_batch_id INTEGER)');
        $this->pdo->exec('CREATE TABLE points_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, order_id INTEGER, amount INTEGER, transaction_type TEXT, description TEXT, created_at TEXT)');
        $this->pdo->exec('CREATE TABLE cart_items (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, product_id INTEGER, stock_mode TEXT, purchase_batch_id INTEGER, quantity REAL, unit_price REAL, boxes REAL)');
        $this->pdo->exec('CREATE TABLE stock_movements (id INTEGER PRIMARY KEY AUTOINCREMENT, purchase_batch_id INTEGER, product_id INTEGER, order_id INTEGER NULL, user_id INTEGER NULL, movement_type TEXT, stock_mode TEXT, boxes_delta REAL, comment TEXT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec("INSERT INTO users (id, points_balance) VALUES (1, 100)");
        $this->pdo->exec("INSERT INTO addresses (id, user_id, street) VALUES (1, 1, 'ул. Тестовая')");
        $this->pdo->exec("INSERT INTO product_types (id, name) VALUES (1, 'Клубника')");
        $this->pdo->exec("INSERT INTO products (id, product_type_id, variety, unit, box_size, box_unit, image_path, is_active) VALUES (10, 1, 'Клери', 'кг', 2, 'кг', NULL, 1)");
        $this->service = new OrderGroupCreationService($this->pdo);
    }

    public function testCreatesSingleInstantOrder(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, purchased_at, box_size_snapshot, box_unit_snapshot, boxes_free, boxes_total, boxes_reserved, boxes_remaining, instant_price_per_box, preorder_price_per_box) VALUES (1, 10, 'purchased', '2026-07-04', 2, 'кг', 5, 5, 0, 5, 1500, 0)");

        $result = $this->service->createForManualOrder(1, 1, null, [
            ['stock_mode' => 'instant', 'purchase_batch_id' => 1, 'boxes' => 2, 'delivery_date' => '2026-07-15'],
        ], ['delivery_fee' => 300]);

        $this->assertCount(1, $result['order_ids']);
        $order = $this->fetchOrder($result['order_ids'][0]);
        $this->assertSame('new', $order['status']);
        $this->assertSame('instant', $order['order_mode']);
        $this->assertSame(3300, (int)$order['total_amount']);
        $this->assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn());
        $this->assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM stock_movements WHERE movement_type = 'sale' AND stock_mode = 'instant'")->fetchColumn());
        $this->assertSame(3.0, (float)$this->pdo->query('SELECT boxes_free FROM purchase_batches WHERE id = 1')->fetchColumn());
    }

    public function testCreatesLinkedInstantAndPreorderOrders(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, purchased_at, box_size_snapshot, box_unit_snapshot, boxes_free, boxes_total, boxes_reserved, boxes_remaining, instant_price_per_box, preorder_price_per_box) VALUES
            (1, 10, 'purchased', '2026-07-04', 2, 'кг', 5, 5, 0, 5, 1500, 0),
            (2, 10, 'planned', '2026-07-18', 2, 'кг', 0, 5, 1, 5, 0, 1350)");

        $result = $this->service->createForManualOrder(1, 1, 5, [
            ['stock_mode' => 'instant', 'purchase_batch_id' => 1, 'boxes' => 1, 'delivery_date' => '2026-07-15'],
            ['stock_mode' => 'preorder', 'purchase_batch_id' => 2, 'boxes' => 2, 'delivery_date' => '2026-07-18'],
        ], ['delivery_fee' => 300, 'referral_discount' => true, 'points' => 100, 'available_points' => 100]);

        $this->assertCount(2, $result['order_ids']);
        $orders = $this->pdo->query('SELECT order_group_id, status, order_mode, delivery_date, delivery_fee FROM orders ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame($orders[0]['order_group_id'], $orders[1]['order_group_id']);
        $this->assertSame(['new', 'reserved'], array_column($orders, 'status'));
        $this->assertSame([300, 300], array_map('intval', array_column($orders, 'delivery_fee')));
        $this->assertSame(3.0, (float)$this->pdo->query('SELECT boxes_reserved FROM purchase_batches WHERE id = 2')->fetchColumn());
        $this->assertSame(2, (int)$this->pdo->query("SELECT COUNT(*) FROM stock_movements WHERE movement_type IN ('sale', 'reserve')")->fetchColumn());
        $this->assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM points_transactions')->fetchColumn());
    }


    public function testDeliveryFeeChargedOnceForSameDateAddressAndSlot(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, purchased_at, box_size_snapshot, box_unit_snapshot, boxes_free, boxes_total, boxes_reserved, boxes_remaining, instant_price_per_box, preorder_price_per_box) VALUES
            (1, 10, 'purchased', '2026-07-04', 2, 'кг', 5, 5, 0, 5, 1500, 0),
            (2, 10, 'planned', '2026-07-18', 2, 'кг', 0, 5, 0, 5, 0, 1350)");

        $this->service->createForManualOrder(1, 1, 5, [
            ['stock_mode' => 'instant', 'purchase_batch_id' => 1, 'boxes' => 1, 'delivery_date' => '2026-07-18'],
            ['stock_mode' => 'preorder', 'purchase_batch_id' => 2, 'boxes' => 1, 'delivery_date' => '2026-07-18'],
        ], ['delivery_fee' => 300]);

        $fees = $this->pdo->query('SELECT delivery_fee FROM orders ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([300, 0], array_map('intval', $fees));
    }

    public function testFifoCreatesSeparateOrderItemsForOneProduct(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, purchased_at, box_size_snapshot, box_unit_snapshot, boxes_free, boxes_total, boxes_reserved, boxes_remaining, instant_price_per_box, preorder_price_per_box) VALUES
            (1, 10, 'purchased', '2026-07-01', 2, 'кг', 3, 3, 0, 3, 1400, 0),
            (2, 10, 'arrived', '2026-07-02', 2, 'кг', 4, 4, 0, 4, 1500, 0)");

        $result = $this->service->createForManualOrder(1, 1, null, [
            ['stock_mode' => 'instant', 'purchase_batch_id' => 1, 'boxes' => 5, 'delivery_date' => '2026-07-15'],
        ]);

        $items = $this->pdo->query('SELECT purchase_batch_id, boxes FROM order_items WHERE order_id = ' . (int)$result['order_ids'][0] . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame([1, 2], array_map('intval', array_column($items, 'purchase_batch_id')));
        $this->assertSame([3.0, 2.0], array_map('floatval', array_column($items, 'boxes')));
    }


    public function testClientCheckoutUsesSharedServiceAllocationsAndDeletesCartRows(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, purchased_at, box_size_snapshot, box_unit_snapshot, boxes_free, boxes_total, boxes_reserved, boxes_remaining, instant_price_per_box, preorder_price_per_box) VALUES
            (1, 10, 'purchased', '2026-07-04', 2, 'кг', 5, 5, 0, 5, 1500, 0),
            (2, 10, 'planned', '2026-07-18', 2, 'кг', 0, 5, 0, 5, 0, 1350)");
        $this->pdo->exec("INSERT INTO cart_items (id, user_id, product_id, stock_mode, purchase_batch_id, quantity, unit_price, boxes) VALUES
            (101, 1, 10, 'instant', 1, 1, 1500, 1),
            (102, 1, 10, 'preorder', 2, 1, 1350, 1)");

        $result = $this->service->createForClientCheckout(1, [
            ['stock_mode' => 'instant', 'purchase_batch_id' => 1, 'boxes' => 1, 'delivery_date' => '2026-07-15'],
            ['stock_mode' => 'preorder', 'purchase_batch_id' => 2, 'boxes' => 1, 'delivery_date' => '2026-07-18'],
        ], [
            'coupon_code' => 'BERRY',
            'discount_percent' => 10,
            'coupon_points' => 50,
            'points' => 100,
            'available_points' => 100,
            'cart_item_ids_to_delete' => [101, 102],
            'delivery_groups' => [
                'instant|2026-07-15' => ['address_id' => 1, 'slot_id' => 5, 'delivery_fee' => 300, 'delivery_comment' => 'домофон'],
                'preorder|2026-07-18' => ['address_id' => 1, 'slot_id' => 5, 'delivery_fee' => 300, 'delivery_comment' => 'домофон'],
            ],
        ]);

        $this->assertCount(2, $result['order_ids']);
        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM cart_items')->fetchColumn());
        $this->assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM points_transactions')->fetchColumn());
        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM points_transactions WHERE order_id IS NULL')->fetchColumn());
        $this->assertSame(3.0, (float)$this->pdo->query('SELECT boxes_reserved FROM purchase_batches WHERE id = 2')->fetchColumn());
        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(DISTINCT order_group_id) - 1 FROM orders')->fetchColumn());
    }

    public function testFailureRollsBackCreatedOrders(): void
    {
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, purchased_at, box_size_snapshot, box_unit_snapshot, boxes_free, boxes_total, boxes_reserved, boxes_remaining, instant_price_per_box, preorder_price_per_box) VALUES (1, 10, 'planned', '2026-07-18', 2, 'кг', 0, 1, 0, 1, 0, 1350)");

        $this->expectExceptionMessage('На выбранную дату осталось только 1 ящика');
        try {
            $this->service->createForManualOrder(1, 1, null, [
                ['stock_mode' => 'preorder', 'purchase_batch_id' => 1, 'boxes' => 2, 'delivery_date' => '2026-07-18'],
            ]);
        } finally {
            $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
            $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM order_groups')->fetchColumn());
        }
    }

    /** @return array<string,mixed> */
    private function fetchOrder(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
