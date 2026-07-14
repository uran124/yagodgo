<?php
namespace Tests;

use App\Services\OrderGroupCreationService;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AcceptanceOrderGroupFlowTest extends TestCase
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

        $this->pdo->exec("INSERT INTO users (id, points_balance) VALUES (1, 500)");
        $this->pdo->exec("INSERT INTO addresses (id, user_id, street) VALUES (1, 1, 'ул. Тестовая')");
        $this->pdo->exec("INSERT INTO product_types (id, name) VALUES (1, 'Клубника')");
        $this->pdo->exec("INSERT INTO products (id, product_type_id, variety, unit, box_size, box_unit, image_path, is_active) VALUES (10, 1, 'Клери', 'кг', 2, 'кг', NULL, 1)");
        $this->service = new OrderGroupCreationService($this->pdo);
    }

    public function testSinglePreorderCreatesReservedOrderAndReserveMovement(): void
    {
        $this->insertBatch(20, 'planned', '2026-07-19', 0, 5, 0, 0, 1350);

        $result = $this->service->createForManualOrder(1, 1, null, [
            ['stock_mode' => 'preorder', 'purchase_batch_id' => 20, 'boxes' => 2, 'delivery_date' => '2026-07-19'],
        ]);

        $this->assertCount(1, $result['order_ids']);
        $order = $this->fetchOrder((int)$result['order_ids'][0]);
        $this->assertSame('reserved', $order['status']);
        $this->assertSame('preorder', $order['order_mode']);
        $this->assertSame(2.0, (float)$this->pdo->query('SELECT boxes_reserved FROM purchase_batches WHERE id = 20')->fetchColumn());
        $this->assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM stock_movements WHERE movement_type = 'reserve' AND stock_mode = 'preorder'")->fetchColumn());
    }

    public function testSameProductInstantAndPreorderCreatesSeparateLinkedOrdersAndItems(): void
    {
        $this->insertBatch(11, 'purchased', '2026-07-04', 3, 3, 0, 1500, 0);
        $this->insertBatch(21, 'planned', '2026-07-20', 0, 4, 0, 0, 1300);

        $result = $this->service->createForManualOrder(1, 1, 7, [
            ['stock_mode' => 'instant', 'purchase_batch_id' => 11, 'boxes' => 1, 'delivery_date' => '2026-07-15'],
            ['stock_mode' => 'preorder', 'purchase_batch_id' => 21, 'boxes' => 2, 'delivery_date' => '2026-07-20'],
        ], ['delivery_fee' => 250]);

        $this->assertCount(2, $result['order_ids']);
        $this->assertSame(1, (int)$this->pdo->query('SELECT COUNT(DISTINCT order_group_id) FROM orders')->fetchColumn());
        $this->assertSame(['instant', 'preorder'], $this->pdo->query('SELECT stock_mode FROM order_items ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        $this->assertSame([11, 21], array_map('intval', $this->pdo->query('SELECT purchase_batch_id FROM order_items ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function testTwoPreordersWithDifferentDatesCreateTwoDeliveryFees(): void
    {
        $this->insertBatch(21, 'planned', '2026-07-20', 0, 4, 0, 0, 1300);
        $this->insertBatch(22, 'planned', '2026-07-22', 0, 4, 0, 0, 1400);

        $this->service->createForManualOrder(1, 1, 7, [
            ['stock_mode' => 'preorder', 'purchase_batch_id' => 21, 'boxes' => 1, 'delivery_date' => '2026-07-20'],
            ['stock_mode' => 'preorder', 'purchase_batch_id' => 22, 'boxes' => 1, 'delivery_date' => '2026-07-22'],
        ], ['delivery_fee' => 300]);

        $this->assertSame([300, 300], array_map('intval', $this->pdo->query('SELECT delivery_fee FROM orders ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
        $this->assertSame(['2026-07-20', '2026-07-22'], $this->pdo->query('SELECT delivery_date FROM orders ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testCurrentBatchPriceIsUsedWhenFormWasOpenedEarlier(): void
    {
        $this->insertBatch(11, 'purchased', '2026-07-04', 3, 3, 0, 1600, 0);

        $result = $this->service->createForManualOrder(1, 1, null, [
            ['stock_mode' => 'instant', 'purchase_batch_id' => 11, 'boxes' => 1, 'delivery_date' => '2026-07-15'],
        ]);

        $item = $this->pdo->query('SELECT unit_price, boxes FROM order_items WHERE order_id = ' . (int)$result['order_ids'][0])->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(800.0, (float)$item['unit_price']);
        $this->assertSame(1600, (int)$this->fetchOrder((int)$result['order_ids'][0])['total_amount']);
    }

    public function testClosedBatchRollsBackEntireGroup(): void
    {
        $this->insertBatch(11, 'purchased', '2026-07-04', 3, 3, 0, 1500, 0);
        $this->insertBatch(21, 'closed', '2026-07-20', 0, 4, 0, 0, 1300);

        try {
            $this->service->createForManualOrder(1, 1, 7, [
                ['stock_mode' => 'instant', 'purchase_batch_id' => 11, 'boxes' => 1, 'delivery_date' => '2026-07-15'],
                ['stock_mode' => 'preorder', 'purchase_batch_id' => 21, 'boxes' => 1, 'delivery_date' => '2026-07-20'],
            ]);
            $this->fail('Expected closed preorder batch to reject the whole grouped order.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Дата будущей поставки изменилась', $e->getMessage());
        }

        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn());
        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn());
        $this->assertSame(3.0, (float)$this->pdo->query('SELECT boxes_free FROM purchase_batches WHERE id = 11')->fetchColumn());
    }

    public function testClientCheckoutGroupTotalsMatchCreatedOrders(): void
    {
        $this->insertBatch(11, 'purchased', '2026-07-04', 3, 3, 0, 1500, 0);
        $this->insertBatch(21, 'planned', '2026-07-20', 0, 4, 0, 0, 1300);
        $this->pdo->exec("INSERT INTO cart_items (id, user_id, product_id, stock_mode, purchase_batch_id, quantity, unit_price, boxes) VALUES
            (101, 1, 10, 'instant', 11, 1, 1500, 1),
            (102, 1, 10, 'preorder', 21, 2, 1300, 2)");

        $result = $this->service->createForClientCheckout(1, [
            ['stock_mode' => 'instant', 'purchase_batch_id' => 11, 'boxes' => 1, 'delivery_date' => '2026-07-15'],
            ['stock_mode' => 'preorder', 'purchase_batch_id' => 21, 'boxes' => 2, 'delivery_date' => '2026-07-20'],
        ], [
            'discount_percent' => 10,
            'points' => 100,
            'available_points' => 500,
            'cart_item_ids_to_delete' => [101, 102],
            'delivery_groups' => [
                'instant|2026-07-15' => ['address_id' => 1, 'slot_id' => 7, 'delivery_fee' => 300],
                'preorder|2026-07-20' => ['address_id' => 1, 'slot_id' => 7, 'delivery_fee' => 300],
            ],
        ]);

        $serviceTotal = array_sum(array_map(static fn(array $order): int => (int)$order['total_amount'], $result['orders']));
        $dbTotal = (int)$this->pdo->query('SELECT SUM(total_amount) FROM orders')->fetchColumn();
        $this->assertSame($serviceTotal, $dbTotal);
        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM cart_items')->fetchColumn());
        $this->assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM points_transactions')->fetchColumn());
        $this->assertSame(400, (int)$this->pdo->query('SELECT points_balance FROM users WHERE id = 1')->fetchColumn());
    }

    private function insertBatch(int $id, string $status, string $date, float $free, float $total, float $reserved, float $instantPrice, float $preorderPrice): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO purchase_batches (id, product_id, status, purchased_at, box_size_snapshot, box_unit_snapshot, boxes_free, boxes_total, boxes_reserved, boxes_remaining, instant_price_per_box, preorder_price_per_box) VALUES (?, 10, ?, ?, 2, "кг", ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$id, $status, $date, $free, $total, $reserved, $total, $instantPrice, $preorderPrice]);
    }

    /** @return array<string,mixed> */
    private function fetchOrder(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
