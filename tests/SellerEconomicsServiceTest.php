<?php
use PHPUnit\Framework\TestCase;
use App\Services\SellerEconomicsService;

class SellerEconomicsServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE partner_profiles (
            user_id INTEGER PRIMARY KEY,
            partner_type TEXT,
            monetization_model TEXT,
            commission_rate REAL,
            subscription_fee REAL,
            fixed_fee_per_order REAL,
            client_visibility TEXT
        )');
    }

    public function testCommissionSettingsComeFromPartnerProfile(): void
    {
        $this->pdo->exec("INSERT INTO partner_profiles (user_id, partner_type, monetization_model, commission_rate, subscription_fee, fixed_fee_per_order, client_visibility)
            VALUES (10, 'marketplace_seller', 'commission_plus_subscription', 22.5, 3000, 0, 'seller_visible')");

        $result = (new SellerEconomicsService($this->pdo))->calculate(10, 10000, 20000, 1000);

        $this->assertSame('commission_plus_subscription', $result['monetization_model']);
        $this->assertSame(22.5, $result['commission_rate']);
        $this->assertSame(2250.0, $result['commission']);
        $this->assertSame(7750.0, $result['payout']);
        $this->assertSame(500.0, $result['points_applied']);
    }

    public function testSubscriptionAndFixedFeeModelsDoNotUseDefaultCommission(): void
    {
        $service = new SellerEconomicsService($this->pdo);
        $this->pdo->exec("INSERT INTO partner_profiles (user_id, partner_type, monetization_model, commission_rate, subscription_fee, fixed_fee_per_order, client_visibility)
            VALUES (11, 'marketplace_seller', 'subscription', 30, 5000, 0, 'seller_visible')");
        $subscription = $service->calculate(11, 4000, 4000, 0);
        $this->assertSame(0.0, $subscription['commission']);
        $this->assertSame(4000.0, $subscription['payout']);

        $this->pdo->exec("INSERT INTO partner_profiles (user_id, partner_type, monetization_model, commission_rate, subscription_fee, fixed_fee_per_order, client_visibility)
            VALUES (12, 'marketplace_seller', 'fixed_fee_per_order', 30, 0, 350, 'seller_visible')");
        $fixedFee = $service->calculate(12, 2000, 2000, 0);
        $this->assertSame(350.0, $fixedFee['commission']);
        $this->assertSame(1650.0, $fixedFee['payout']);
    }
}
