<?php
namespace Tests;

use App\Services\OrderReturnService;
use PDO;
use PHPUnit\Framework\TestCase;

class OrderReturnServiceTest extends TestCase
{
    private PDO $pdo;
    private OrderReturnService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            points_balance INTEGER DEFAULT 0,
            rub_balance REAL DEFAULT 0,
            has_used_referral_coupon INTEGER DEFAULT 0
        )');
        $this->pdo->exec('CREATE TABLE products (
            id INTEGER PRIMARY KEY,
            free_stock_boxes REAL DEFAULT 0,
            reserved_stock_boxes REAL DEFAULT 0,
            discount_stock_boxes REAL DEFAULT 0,
            sold_stock_boxes REAL DEFAULT 0,
            written_off_stock_boxes REAL DEFAULT 0,
            stock_status TEXT DEFAULT "sold_out"
        )');
        $this->pdo->exec('CREATE TABLE purchase_batches (
            id INTEGER PRIMARY KEY,
            product_id INTEGER,
            boxes_total REAL DEFAULT 0,
            boxes_reserved REAL DEFAULT 0,
            boxes_free REAL DEFAULT 0,
            boxes_discount REAL DEFAULT 0,
            boxes_sold REAL DEFAULT 0,
            boxes_written_off REAL DEFAULT 0,
            boxes_remaining REAL DEFAULT 0,
            status TEXT DEFAULT "active"
        )');
        $this->pdo->exec('CREATE TABLE stock_movements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            purchase_batch_id INTEGER,
            product_id INTEGER,
            order_id INTEGER,
            user_id INTEGER,
            movement_type TEXT,
            stock_mode TEXT,
            boxes_delta REAL,
            comment TEXT
        )');
        $this->pdo->exec('CREATE TABLE orders (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            status TEXT,
            points_used INTEGER DEFAULT 0,
            points_accrued INTEGER DEFAULT 0,
            manager_points_accrued INTEGER DEFAULT 0,
            payment_status TEXT DEFAULT "unpaid"
        )');
        $this->pdo->exec('CREATE TABLE order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER,
            product_id INTEGER,
            purchase_batch_id INTEGER,
            boxes REAL,
            stock_mode TEXT
        )');
        $this->pdo->exec('CREATE TABLE points_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            order_id INTEGER,
            amount INTEGER,
            transaction_type TEXT,
            description TEXT,
            created_at TEXT
        )');
        $this->pdo->exec('CREATE TABLE seller_payouts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            seller_id INTEGER,
            order_id INTEGER,
            gross_amount REAL,
            commission_rate REAL,
            commission_amount REAL,
            payout_amount REAL,
            status TEXT,
            created_at TEXT,
            paid_at TEXT
        )');

        $this->pdo->exec("INSERT INTO users (id, points_balance, rub_balance, has_used_referral_coupon) VALUES
            (1, 100, 0, 1),
            (2, 30, 0, 0),
            (3, 20, 0, 0),
            (4, 0, 70, 0)");
        $this->pdo->exec("INSERT INTO products (id, sold_stock_boxes, written_off_stock_boxes) VALUES (10, 2, 0)");
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, boxes_total, boxes_free, boxes_sold, boxes_written_off, boxes_remaining, status)
            VALUES (100, 10, 10, 8, 2, 0, 8, 'purchased')");
        $this->pdo->exec("INSERT INTO orders (id, user_id, status, points_used, points_accrued, manager_points_accrued, payment_status)
            VALUES (500, 1, 'completed', 5, 20, 6, 'paid')");
        $this->pdo->exec("INSERT INTO order_items (order_id, product_id, purchase_batch_id, boxes, stock_mode)
            VALUES (500, 10, 100, 2, 'instant')");
        $this->pdo->exec("INSERT INTO points_transactions (user_id, order_id, amount, transaction_type, description, created_at) VALUES
            (1, 500, 10, 'accrual', 'Начисление', '2026-06-01 10:00:00'),
            (2, 500, 20, 'accrual', 'Бонус', '2026-06-01 10:00:00'),
            (3, 500, 6, 'accrual', 'Менеджерский бонус', '2026-06-01 10:00:00')");
        $this->pdo->exec("INSERT INTO seller_payouts (seller_id, order_id, gross_amount, commission_rate, commission_amount, payout_amount, status, created_at)
            VALUES (4, 500, 100, 30, 30, 40, 'accrued', '2026-06-01 10:00:00')");

        $this->service = new OrderReturnService($this->pdo);
    }

    public function testReturnCompletedOrderWritesOffStockAndReversesBalances(): void
    {
        $this->service->returnCompletedOrder(500, 9);

        $batch = $this->pdo->query('SELECT boxes_sold, boxes_written_off, boxes_remaining FROM purchase_batches WHERE id = 100')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0.0, (float)$batch['boxes_sold']);
        $this->assertSame(2.0, (float)$batch['boxes_written_off']);
        $this->assertSame(8.0, (float)$batch['boxes_remaining']);

        $balances = $this->pdo->query('SELECT id, points_balance, rub_balance, has_used_referral_coupon FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame(95, (int)$balances[0]['points_balance']);
        $this->assertSame(10, (int)$balances[1]['points_balance']);
        $this->assertSame(14, (int)$balances[2]['points_balance']);
        $this->assertSame(30.0, (float)$balances[3]['rub_balance']);
        $this->assertSame(0, (int)$balances[0]['has_used_referral_coupon']);

        $order = $this->pdo->query('SELECT points_accrued, manager_points_accrued, payment_status FROM orders WHERE id = 500')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0, (int)$order['points_accrued']);
        $this->assertSame(0, (int)$order['manager_points_accrued']);
        $this->assertSame('refund_pending', $order['payment_status']);

        $this->assertSame('cancelled', (string)$this->pdo->query('SELECT status FROM seller_payouts WHERE order_id = 500')->fetchColumn());
        $this->assertSame(1, (int)$this->pdo->query("SELECT COUNT(*) FROM stock_movements WHERE order_id = 500 AND movement_type = 'writeoff'")->fetchColumn());
        $this->assertSame(7, (int)$this->pdo->query('SELECT COUNT(*) FROM points_transactions WHERE order_id = 500')->fetchColumn());
    }

    public function testReturnRejectsNonCompletedOrder(): void
    {
        $this->pdo->exec("UPDATE orders SET status = 'confirmed' WHERE id = 500");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Возврат доступен только для выполненного заказа.');

        $this->service->returnCompletedOrder(500, 9);
    }
}
