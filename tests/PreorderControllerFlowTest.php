<?php

namespace {
    if (!function_exists('requireClient')) {
        function requireClient(): void
        {
        }
    }
}

namespace Tests {

use App\Controllers\ClientController;
use PDO;
use PHPUnit\Framework\TestCase;

class PreorderControllerFlowTest extends TestCase
{
    private PDO $pdo;
    private ClientController $controller;

    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION = ['user_id' => 10, 'role' => 'client'];

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("CREATE TABLE preorder_intents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            product_id INTEGER,
            requested_boxes REAL,
            status TEXT,
            offered_price_per_box REAL,
            offer_expires_at TEXT,
            checkout_token TEXT,
            created_at TEXT,
            updated_at TEXT
        )");
        $this->pdo->exec("CREATE TABLE preorder_intent_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            preorder_intent_id INTEGER,
            event_type TEXT,
            from_status TEXT,
            to_status TEXT,
            meta_json TEXT,
            created_at TEXT
        )");
        $this->pdo->exec("CREATE TABLE products (
            id INTEGER PRIMARY KEY,
            product_type_id INTEGER,
            is_active INTEGER,
            alias TEXT,
            variety TEXT,
            box_size REAL,
            preorder_price_per_box REAL,
            preorder_unit_price REAL
        )");
        $this->pdo->exec("CREATE TABLE product_types (id INTEGER PRIMARY KEY, alias TEXT, name TEXT)");

        $this->controller = new ClientController($this->pdo);
    }

    public function testConfirmPreorderIntentGeneratesHexTokenAndConfirmedStatus(): void
    {
        $this->pdo->exec("INSERT INTO preorder_intents (id,user_id,product_id,requested_boxes,status,offer_expires_at,created_at,updated_at)
            VALUES (1,10,5,2,'offer_sent','2999-01-01 00:00:00','2026-01-01 00:00:00','2026-01-01 00:00:00')");

        ob_start();
        $this->controller->confirmPreorderIntent(1);
        $json = (string)ob_get_clean();
        $payload = json_decode($json, true);

        $this->assertTrue((bool)$payload['ok']);
        $this->assertSame('confirmed', $payload['status']);
        $this->assertMatchesRegularExpression('#^/preorder/continue/[a-f0-9]{48}$#', (string)$payload['continue_url']);

        $status = $this->pdo->query("SELECT status FROM preorder_intents WHERE id = 1")->fetchColumn();
        $this->assertSame('confirmed', $status);
    }

    public function testConfirmPreorderIntentRejectsDifferentOwner(): void
    {
        $this->pdo->exec("INSERT INTO preorder_intents (id,user_id,product_id,requested_boxes,status,offer_expires_at,created_at,updated_at)
            VALUES (2,11,5,2,'offer_sent','2999-01-01 00:00:00','2026-01-01 00:00:00','2026-01-01 00:00:00')");

        ob_start();
        $this->controller->confirmPreorderIntent(2);
        $json = (string)ob_get_clean();
        $payload = json_decode($json, true);

        $this->assertFalse((bool)$payload['ok']);
        $this->assertSame('Предзаказ не найден', $payload['error']);
    }
}

}
