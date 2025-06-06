<?php
namespace Tests;

use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Models\\Order')) {
            require_once __DIR__ . '/../src/Models/Order.php';
            class_alias('Models\\Order', 'App\\Models\\Order');
        }
    }

    public function testCalculateReferralDiscount(): void
    {
        $this->assertSame(10, \App\Models\Order::calculateReferralDiscount(100));
        $this->assertSame(0, \App\Models\Order::calculateReferralDiscount(5));
    }

    public function testCalculateMaxPointsUsage(): void
    {
        $this->assertSame(30, \App\Models\Order::calculateMaxPointsUsage(100));
    }

    public function testCalculatePersonalBonus(): void
    {
        $this->assertSame(5, \App\Models\Order::calculatePersonalBonus(100));
    }

    public function testCalculateReferralBonus(): void
    {
        $this->assertSame(3, \App\Models\Order::calculateReferralBonus(100));
    }
}
