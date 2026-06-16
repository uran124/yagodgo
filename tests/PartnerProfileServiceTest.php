<?php
use PHPUnit\Framework\TestCase;
use App\Services\PartnerProfileService;

class PartnerProfileServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT)');
        $this->pdo->exec('CREATE TABLE production_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            executor_type TEXT NULL,
            executor_id INTEGER NULL,
            status TEXT NOT NULL DEFAULT "new"
        )');
        $this->pdo->exec('CREATE TABLE partner_profiles (
            user_id INTEGER PRIMARY KEY,
            partner_type TEXT NOT NULL DEFAULT "production_partner",
            status TEXT NOT NULL DEFAULT "draft",
            default_fulfillment_model TEXT NOT NULL DEFAULT "by_partner_under_berrygo_brand",
            monetization_model TEXT NOT NULL DEFAULT "commission",
            client_visibility TEXT NOT NULL DEFAULT "berrygo_only",
            commission_rate REAL NOT NULL DEFAULT 30,
            subscription_fee REAL NOT NULL DEFAULT 0,
            fixed_fee_per_order REAL NOT NULL DEFAULT 0,
            default_bonus_percent REAL NOT NULL DEFAULT 10,
            max_active_jobs INTEGER NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )');
    }

    public function testSaveNormalizesPartnerProfileAndUpdatesExistingRecord(): void
    {
        $service = new PartnerProfileService($this->pdo);

        $this->assertTrue($service->save([
            'user_id' => 10,
            'partner_type' => 'marketplace_seller',
            'status' => 'active',
            'default_fulfillment_model' => 'by_seller',
            'monetization_model' => 'commission_plus_subscription',
            'client_visibility' => 'seller_visible',
            'commission_rate' => 30,
            'subscription_fee' => 5000,
            'fixed_fee_per_order' => 150,
            'default_bonus_percent' => 0,
            'max_active_jobs' => 4,
            'notes' => 'seller foundation',
        ]));

        $profile = $service->find(10);
        $this->assertSame('marketplace_seller', $profile['partner_type']);
        $this->assertSame('seller_visible', $profile['client_visibility']);
        $this->assertSame(5000.0, (float)$profile['subscription_fee']);

        $this->assertTrue($service->save([
            'user_id' => 10,
            'partner_type' => 'bad-type',
            'status' => 'paused',
            'client_visibility' => 'bad-visibility',
        ]));

        $updated = $service->find(10);
        $this->assertSame('production_partner', $updated['partner_type']);
        $this->assertSame('paused', $updated['status']);
        $this->assertSame('berrygo_only', $updated['client_visibility']);
    }

    public function testEligibleProductionExecutorsUsesPartnerProfileLimits(): void
    {
        $service = new PartnerProfileService($this->pdo);
        $this->pdo->exec("INSERT INTO users (id, name, role) VALUES (1, 'Florist', 'manager'), (2, 'Partner Lab', 'partner'), (3, 'Seller', 'seller')");
        $this->assertTrue($service->save(['user_id' => 1, 'partner_type' => 'internal_staff', 'status' => 'active', 'max_active_jobs' => 2]));
        $this->assertTrue($service->save(['user_id' => 2, 'partner_type' => 'production_partner', 'status' => 'active', 'max_active_jobs' => 1]));
        $this->assertTrue($service->save(['user_id' => 3, 'partner_type' => 'marketplace_seller', 'status' => 'active', 'max_active_jobs' => 5]));
        $this->pdo->exec("INSERT INTO production_jobs (executor_type, executor_id, status) VALUES ('production_partner', 2, 'assigned')");

        $executors = $service->eligibleProductionExecutors();

        $this->assertCount(1, $executors);
        $this->assertSame(1, (int)$executors[0]['id']);
        $this->assertSame('internal_staff', $executors[0]['executor_type']);
    }
}
