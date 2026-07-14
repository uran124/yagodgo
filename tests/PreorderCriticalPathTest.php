<?php
namespace Tests;

use App\Controllers\ClientController;
use PDO;
use PHPUnit\Framework\TestCase;

class PreorderCriticalPathTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        unset($_SESSION['preorder_continue'], $_SESSION['preorder_checkout_intent_id'], $_SESSION['cart_error'], $_SESSION['delivery_date']);

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE preorder_intents (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            product_id INTEGER,
            requested_boxes REAL,
            status TEXT,
            offered_price_per_box REAL,
            purchase_batch_id INTEGER NULL,
            desired_delivery_date TEXT NULL
        )');
        $this->pdo->exec('CREATE TABLE purchase_batches (
            id INTEGER PRIMARY KEY,
            product_id INTEGER,
            status TEXT,
            preorder_price_per_box REAL,
            purchased_at TEXT,
            boxes_total REAL DEFAULT 0,
            boxes_free REAL DEFAULT 0,
            boxes_reserved REAL DEFAULT 0
        )');
        $this->pdo->exec('CREATE TABLE cart_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            product_id INTEGER,
            quantity REAL,
            unit_price REAL,
            stock_mode TEXT,
            purchase_batch_id INTEGER NULL,
            boxes REAL,
            sale_price_per_box REAL
        )');
        $this->pdo->exec('CREATE TABLE products (
            id INTEGER PRIMARY KEY,
            preorder_price_per_box REAL DEFAULT 9999,
            preorder_unit_price REAL DEFAULT 9999,
            price REAL DEFAULT 0,
            box_size REAL DEFAULT 1,
            is_active INTEGER DEFAULT 1
        )');
    }

    public function testConfirmedIntentUsesOfferOrPlannedBatchPriceWithoutProductFallback(): void
    {
        $_SESSION['preorder_continue'] = [
            'intent_id' => 1,
            'product_id' => 10,
            'requested_boxes' => 3.0,
        ];
        $_SESSION['preorder_checkout_intent_id'] = null;

        $this->pdo->exec("INSERT INTO products (id, preorder_price_per_box, preorder_unit_price, price, box_size, is_active) VALUES (10, 7777, 6666, 0, 2, 1)");
        $this->pdo->exec("INSERT INTO preorder_intents (id, user_id, product_id, requested_boxes, status, offered_price_per_box, purchase_batch_id, desired_delivery_date) VALUES (1, 5, 10, 3, 'confirmed', NULL, 50, '2026-05-03')");
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, preorder_price_per_box, purchased_at, boxes_total, boxes_free, boxes_reserved) VALUES (50, 10, 'planned', 1300, '2026-05-01 10:00:00', 5, 0, 1)");

        $controller = new ClientController($this->pdo);
        $method = new \ReflectionMethod(ClientController::class, 'syncPreorderContinueToCart');
        $method->setAccessible(true);
        $method->invoke($controller, 5);

        $row = $this->pdo->query("SELECT unit_price, stock_mode, purchase_batch_id FROM cart_items WHERE user_id = 5 AND product_id = 10 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1300.0, (float)$row['unit_price']);
        $this->assertSame('preorder', (string)$row['stock_mode']);
        $this->assertSame(50, (int)$row['purchase_batch_id']);
    }

    public function testConfirmedIntentDoesNotUseArrivedBatchOrProductFallback(): void
    {
        $_SESSION['preorder_continue'] = [
            'intent_id' => 2,
            'product_id' => 10,
            'requested_boxes' => 1.0,
        ];
        unset($_SESSION['cart_error']);

        $this->pdo->exec("INSERT INTO products (id, preorder_price_per_box, preorder_unit_price, price, box_size, is_active) VALUES (10, 7777, 6666, 9999, 2, 1)");
        $this->pdo->exec("INSERT INTO preorder_intents (id, user_id, product_id, requested_boxes, status, offered_price_per_box, purchase_batch_id, desired_delivery_date) VALUES (2, 5, 10, 1, 'confirmed', NULL, 60, '2026-05-03')");
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, preorder_price_per_box, purchased_at, boxes_total, boxes_free, boxes_reserved) VALUES (60, 10, 'arrived', 0, '2026-05-01 10:00:00', 5, 5, 0)");

        $controller = new ClientController($this->pdo);
        $method = new \ReflectionMethod(ClientController::class, 'syncPreorderContinueToCart');
        $method->setAccessible(true);
        $method->invoke($controller, 5);

        $this->assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM cart_items')->fetchColumn());
        $this->assertNotEmpty($_SESSION['cart_error'] ?? '');
    }

}

