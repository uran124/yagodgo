<?php

namespace Tests;

use App\Services\PreorderIntentService;
use PDO;
use PHPUnit\Framework\TestCase;

class PreorderIntentServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
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
    }

    public function testAllocateOfferWaveUsesFifoWithinAvailableBoxes(): void
    {
        $this->pdo->exec("INSERT INTO preorder_intents (user_id, product_id, requested_boxes, status, created_at, updated_at) VALUES
            (1, 10, 3, 'intent_created', '2026-01-01 10:00:00', '2026-01-01 10:00:00'),
            (2, 10, 2, 'intent_created', '2026-01-01 10:01:00', '2026-01-01 10:01:00'),
            (3, 10, 4, 'intent_created', '2026-01-01 10:02:00', '2026-01-01 10:02:00')
        ");

        $service = new PreorderIntentService($this->pdo);
        $result = $service->allocateOfferWave(10, 5, 1200, 4);

        $this->assertSame(2, $result['offered_count']);
        $this->assertSame(5.0, $result['allocated_boxes']);

        $statuses = $this->pdo->query("SELECT id, status FROM preorder_intents ORDER BY id ASC")->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame('offer_sent', $statuses[1]);
        $this->assertSame('offer_sent', $statuses[2]);
        $this->assertSame('intent_created', $statuses[3]);
        $eventCount = (int)$this->pdo->query("SELECT COUNT(*) FROM preorder_intent_events WHERE event_type = 'offer_sent'")->fetchColumn();
        $this->assertSame(2, $eventCount);
    }

    public function testExpireOffersUpdatesOnlyExpiredOfferSentRows(): void
    {
        $this->pdo->exec("INSERT INTO preorder_intents (user_id, product_id, requested_boxes, status, offer_expires_at, created_at, updated_at) VALUES
            (1, 10, 1, 'offer_sent', '2020-01-01 00:00:00', '2020-01-01 00:00:00', '2020-01-01 00:00:00'),
            (2, 10, 1, 'offer_sent', '2999-01-01 00:00:00', '2020-01-01 00:00:00', '2020-01-01 00:00:00'),
            (3, 10, 1, 'intent_created', NULL, '2020-01-01 00:00:00', '2020-01-01 00:00:00')
        ");

        $service = new PreorderIntentService($this->pdo);
        $affected = $service->expireOffers();

        $this->assertSame(1, $affected);
        $status1 = $this->pdo->query("SELECT status FROM preorder_intents WHERE id = 1")->fetchColumn();
        $status2 = $this->pdo->query("SELECT status FROM preorder_intents WHERE id = 2")->fetchColumn();
        $this->assertSame('expired', $status1);
        $this->assertSame('offer_sent', $status2);
        $expireEvents = (int)$this->pdo->query("SELECT COUNT(*) FROM preorder_intent_events WHERE event_type = 'offer_expired'")->fetchColumn();
        $this->assertSame(1, $expireEvents);
    }

    public function testReallocateForProductSendsOffersWithProvidedPrice(): void
    {
        $this->pdo->exec("INSERT INTO preorder_intents (user_id, product_id, requested_boxes, status, created_at, updated_at) VALUES
            (10, 33, 1, 'intent_created', '2026-01-02 10:00:00', '2026-01-02 10:00:00'),
            (11, 33, 1, 'intent_created', '2026-01-02 10:01:00', '2026-01-02 10:01:00')
        ");

        $service = new PreorderIntentService($this->pdo);
        $result = $service->reallocateForProduct(33, 1, 1450, 4);

        $this->assertSame(1, $result['offered_count']);
        $this->assertSame(1.0, $result['allocated_boxes']);

        $row = $this->pdo
            ->query("SELECT status, offered_price_per_box FROM preorder_intents WHERE id = 1")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('offer_sent', $row['status']);
        $this->assertSame(1450.0, (float)$row['offered_price_per_box']);

        $row2 = $this->pdo
            ->query("SELECT status FROM preorder_intents WHERE id = 2")
            ->fetchColumn();
        $this->assertSame('intent_created', $row2);
    }
}
