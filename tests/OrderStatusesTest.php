<?php
namespace Tests;

use App\Support\OrderStatuses;
use PHPUnit\Framework\TestCase;

class OrderStatusesTest extends TestCase
{
    public function testNewLifecycleStatusesAreAllowed(): void
    {
        $this->assertSame([
            'reserved',
            'new',
            'confirmed',
            'shipped',
            'completed',
            'cancelled',
            'returned',
        ], OrderStatuses::all());
    }

    public function testLegacyStatusesNormalizeToNewLifecycle(): void
    {
        $this->assertSame('confirmed', OrderStatuses::normalize('processing'));
        $this->assertSame('shipped', OrderStatuses::normalize('assigned'));
        $this->assertSame('completed', OrderStatuses::normalize('delivered'));
    }

    public function testLabelsUseAgreedRussianCopy(): void
    {
        $labels = OrderStatuses::labels();
        $this->assertSame('Ожидает подтверждения', $labels['new']);
        $this->assertSame('Подтверждён', $labels['confirmed']);
        $this->assertSame('Отменён', $labels['cancelled']);
    }
}
