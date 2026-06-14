<?php
namespace Tests;

use App\Services\OrderTotalsService;
use PDO;
use PHPUnit\Framework\TestCase;

class OrderTotalsServiceTest extends TestCase
{
    private PDO $pdo;
    private OrderTotalsService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE orders (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            points_used INTEGER DEFAULT 0,
            coupon_code TEXT NULL,
            address_id INTEGER NULL,
            delivery_fee INTEGER DEFAULT 0,
            total_amount INTEGER DEFAULT 0,
            discount_applied INTEGER DEFAULT 0
        )');
        $this->pdo->exec('CREATE TABLE order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER,
            product_id INTEGER,
            quantity REAL,
            unit_price REAL
        )');
        $this->pdo->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            has_used_referral_coupon INTEGER DEFAULT 0
        )');
        $this->pdo->exec('CREATE TABLE coupons (
            code TEXT PRIMARY KEY,
            type TEXT,
            discount REAL,
            points INTEGER
        )');

        $this->service = new OrderTotalsService($this->pdo);
    }

    public function testRecalculateAppliesPercentCouponPointsAndDeliveryFee(): void
    {
        $this->pdo->exec("INSERT INTO users (id, has_used_referral_coupon) VALUES (1, 0)");
        $this->pdo->exec("INSERT INTO coupons (code, type, discount, points) VALUES ('SALE10', 'discount', 10, 0)");
        $this->pdo->exec("INSERT INTO orders (id, user_id, points_used, coupon_code, delivery_fee) VALUES (10, 1, 100, 'SALE10', 250)");
        $this->pdo->exec("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (10, 1, 2, 500), (10, 2, 1, 300)");

        $this->service->recalculate(10);

        $row = $this->fetchOrder(10);
        $this->assertSame(120, (int)$row['discount_applied']);
        $this->assertSame(1330, (int)$row['total_amount']);
    }

    public function testRecalculateAppliesReferralDiscountWhenCouponIsNotFound(): void
    {
        $this->pdo->exec("INSERT INTO users (id, has_used_referral_coupon) VALUES (2, 1)");
        $this->pdo->exec("INSERT INTO orders (id, user_id, points_used, coupon_code, delivery_fee) VALUES (20, 2, 0, 'REFCODE', 0)");
        $this->pdo->exec("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (20, 1, 3, 400)");

        $this->service->recalculate(20);

        $row = $this->fetchOrder(20);
        $this->assertSame(120, (int)$row['discount_applied']);
        $this->assertSame(1080, (int)$row['total_amount']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchOrder(int $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT total_amount, discount_applied FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
