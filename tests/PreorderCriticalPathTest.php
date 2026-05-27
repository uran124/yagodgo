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

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE preorder_intents (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            product_id INTEGER,
            requested_boxes REAL,
            status TEXT,
            offered_price_per_box REAL
        )');
        $this->pdo->exec('CREATE TABLE purchase_batches (
            id INTEGER PRIMARY KEY,
            product_id INTEGER,
            status TEXT,
            preorder_price_per_box REAL,
            purchased_at TEXT
        )');
        $this->pdo->exec('CREATE TABLE cart_items (
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

        $this->pdo->exec("INSERT INTO products (id, preorder_price_per_box, preorder_unit_price, box_size, is_active) VALUES (10, 7777, 6666, 2, 1)");
        $this->pdo->exec("INSERT INTO preorder_intents (id, user_id, product_id, requested_boxes, status, offered_price_per_box) VALUES (1, 5, 10, 3, 'confirmed', NULL)");
        $this->pdo->exec("INSERT INTO purchase_batches (id, product_id, status, preorder_price_per_box, purchased_at) VALUES (50, 10, 'planned', 1300, '2026-05-01 10:00:00')");

        $controller = new ClientController($this->pdo);
        $method = new \ReflectionMethod(ClientController::class, 'syncPreorderContinueToCart');
        $method->setAccessible(true);
        $method->invoke($controller, 5);

        $row = $this->pdo->query("SELECT unit_price, stock_mode, purchase_batch_id FROM cart_items WHERE user_id = 5 AND product_id = 10 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1300.0, (float)$row['unit_price']);
        $this->assertSame('preorder', (string)$row['stock_mode']);
        $this->assertNull($row['purchase_batch_id']);
    }
}

