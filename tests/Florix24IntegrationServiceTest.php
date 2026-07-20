<?php
namespace Tests;

use App\Services\Florix24InboundStatusService;
use App\Services\Florix24IntegrationService;
use App\Services\Florix24WebhookException;
use PDO;
use PHPUnit\Framework\TestCase;

class Florix24IntegrationServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is required.');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema();
        $this->seedSettings();
    }

    public function testOnlyOrdersCreatedAfterIntegrationEnabledAreQueued(): void
    {
        $this->insertOrder(10, '2026-07-19 09:59:59');
        $this->insertOrder(11, '2026-07-19 10:00:00');
        $service = new Florix24IntegrationService($this->pdo);

        $this->assertFalse($service->enqueueNewOrder(10, 'site'));
        $this->assertTrue($service->enqueueNewOrder(11, 'admin'));
        $this->assertSame(1, (int)$this->pdo->query('SELECT COUNT(*) FROM integration_outbox')->fetchColumn());

        $payload = json_decode((string)$this->pdo->query('SELECT payload_json FROM integration_outbox')->fetchColumn(), true);
        $this->assertSame('11', $payload['external_order_id']);
        $this->assertSame('admin', $payload['source_context']);
        $this->assertSame('new', $payload['status']);
        $this->assertSame('Клубника Клери', $payload['items'][0]['name']);
    }

    public function testOnlyMainStatusesAreQueued(): void
    {
        $this->insertOrder(12, '2026-07-19 10:01:00');
        $service = new Florix24IntegrationService($this->pdo);
        $service->enqueueNewOrder(12, 'site');

        $this->assertFalse($service->enqueueStatusChange(12, 'confirmed', 'shipped'));
        $this->assertTrue($service->enqueueStatusChange(12, 'new', 'confirmed', 1, 'Администратор', 'admin'));
        $this->assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM integration_outbox')->fetchColumn());
    }

    public function testInvalidWebhookSignatureIsRejected(): void
    {
        $raw = json_encode([
            'event_id' => 'f24-test-1',
            'external_order_id' => '12',
            'status' => 'confirmed',
        ], JSON_UNESCAPED_UNICODE);

        try {
            (new Florix24InboundStatusService($this->pdo))->handle((string)$raw, [
                'x-florix-timestamp' => (string)time(),
                'x-florix-signature' => 'sha256=wrong',
                'x-florix-event-id' => 'f24-test-1',
            ]);
            $this->fail('Expected signature rejection.');
        } catch (Florix24WebhookException $e) {
            $this->assertSame(401, $e->httpStatus);
            $this->assertSame('signature_invalid', $e->errorCode);
        }
    }

    private function seedSettings(): void
    {
        $values = [
            'florix24_enabled' => '1',
            'florix24_base_url' => 'https://florix24.ru',
            'florix24_api_token' => 'test-token',
            'florix24_webhook_secret' => 'test-secret',
            'florix24_send_orders' => '1',
            'florix24_send_statuses' => '1',
            'florix24_receive_statuses' => '1',
            'florix24_auto_retry' => '1',
            'florix24_enabled_at' => '2026-07-19 10:00:00',
        ];
        $stmt = $this->pdo->prepare('INSERT INTO settings(setting_key, setting_value) VALUES (?, ?)');
        foreach ($values as $key => $value) {
            $stmt->execute([$key, $value]);
        }
    }

    private function insertOrder(int $id, string $createdAt): void
    {
        $this->pdo->prepare("INSERT INTO orders
            (id, user_id, address_id, slot_id, status, total_amount, delivery_date, created_at, delivery_fee, payment_status, discount_applied, points_used)
            VALUES (?, 1, 1, 1, 'new', 1350, '2026-07-20', ?, 300, 'unpaid', 0, 0)")
            ->execute([$id, $createdAt]);
        $this->pdo->prepare("INSERT INTO order_items(order_id, product_id, quantity, boxes, unit_price, stock_mode)
            VALUES (?, 1, 2, 2, 525, 'instant')")->execute([$id]);
    }

    private function createSchema(): void
    {
        $this->pdo->exec('CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, phone TEXT, email TEXT, role TEXT, referred_by INTEGER, points_balance INTEGER DEFAULT 0, rub_balance REAL DEFAULT 0, has_used_referral_coupon INTEGER DEFAULT 0)');
        $this->pdo->exec("INSERT INTO users(id,name,phone,email,role) VALUES (1,'Анна','79000000000','a@example.test','client')");
        $this->pdo->exec('CREATE TABLE addresses (id INTEGER PRIMARY KEY, user_id INTEGER, street TEXT, apartment TEXT, recipient_name TEXT, recipient_phone TEXT)');
        $this->pdo->exec("INSERT INTO addresses VALUES (1,1,'Ленина 10','25','Мария','79000000001')");
        $this->pdo->exec('CREATE TABLE delivery_slots (id INTEGER PRIMARY KEY, time_from TEXT, time_to TEXT)');
        $this->pdo->exec("INSERT INTO delivery_slots VALUES (1,'14:00:00','16:00:00')");
        $this->pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, order_group_id INTEGER, user_id INTEGER, address_id INTEGER, slot_id INTEGER, status TEXT, total_amount REAL, delivery_date TEXT, created_by_user_id INTEGER, discount_applied INTEGER, points_used INTEGER, points_accrued INTEGER DEFAULT 0, manager_points_accrued INTEGER DEFAULT 0, created_at TEXT, coupon_code TEXT, comment TEXT, order_mode TEXT DEFAULT "instant", delivery_fee REAL DEFAULT 0, delivery_distance_km REAL, delivery_comment TEXT, payment_status TEXT, payment_method TEXT, payment_provider TEXT, payment_amount REAL)');
        $this->pdo->exec('CREATE TABLE product_types (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo->exec("INSERT INTO product_types VALUES (1,'Клубника')");
        $this->pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, product_type_id INTEGER, variety TEXT, unit TEXT)');
        $this->pdo->exec("INSERT INTO products VALUES (1,1,'Клери','ящик')");
        $this->pdo->exec('CREATE TABLE order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, product_id INTEGER, quantity REAL, boxes REAL, unit_price REAL, stock_mode TEXT, purchase_batch_id INTEGER)');
        $this->pdo->exec('CREATE TABLE florix24_order_links (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER UNIQUE, external_order_id TEXT UNIQUE, florix_order_id INTEGER, florix_order_number TEXT, sync_status TEXT, last_synced_at TEXT, last_error TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE integration_outbox (id INTEGER PRIMARY KEY AUTOINCREMENT, integration_code TEXT, event_id TEXT UNIQUE, event_type TEXT, entity_type TEXT, entity_id INTEGER, payload_json TEXT, status TEXT, attempts INTEGER, next_attempt_at TEXT, last_attempt_at TEXT, response_http_code INTEGER, response_body TEXT, last_error TEXT, created_at TEXT, sent_at TEXT)');
        $this->pdo->exec('CREATE TABLE integration_inbox_events (id INTEGER PRIMARY KEY AUTOINCREMENT, integration_code TEXT, event_id TEXT, event_type TEXT, entity_type TEXT, entity_id INTEGER, payload_json TEXT, status TEXT, error_message TEXT, created_at TEXT, processed_at TEXT, UNIQUE(integration_code,event_id))');
    }
}
