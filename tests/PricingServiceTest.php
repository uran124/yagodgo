<?php
namespace Tests;

use App\Services\PricingService;
use PDO;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    private PDO $pdo;
    private PricingService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');

        $this->service = new PricingService($this->pdo);
    }

    public function testCalculateFromPurchaseUsesDefaults(): void
    {
        $result = $this->service->calculateFromPurchase(1000.0, 2.0);

        $this->assertSame(1300.0, $result['preorder_price_per_box']);
        $this->assertSame(1500.0, $result['instant_price_per_box']);
        $this->assertSame(1100.0, $result['discount_price_per_box']);
        $this->assertSame(650.0, $result['preorder_unit_price']);
        $this->assertSame(750.0, $result['instant_unit_price']);
        $this->assertSame(550.0, $result['discount_unit_price']);
    }

    public function testCalculateFromPurchaseUsesDbSettingsAndRoundingStep(): void
    {
        $this->pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES
            ('pricing_preorder_margin_percent', '25'),
            ('pricing_instant_margin_percent', '47'),
            ('pricing_discount_stock_markup_fixed', '150'),
            ('pricing_rounding_step', '5')
        ");

        $result = $this->service->calculateFromPurchase(1180.0, 2.0);

        $this->assertSame(1475.0, $result['preorder_price_per_box']);
        $this->assertSame(1730.0, $result['instant_price_per_box']);
        $this->assertSame(1330.0, $result['discount_price_per_box']);
        $this->assertSame(737.5, $result['preorder_unit_price']);
        $this->assertSame(865.0, $result['instant_unit_price']);
        $this->assertSame(665.0, $result['discount_unit_price']);
    }

    public function testFloorToStepNeverUsesZeroStep(): void
    {
        $this->assertSame(1234.0, $this->service->floorToStep(1234.9, 0));
    }
}
