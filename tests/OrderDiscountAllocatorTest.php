<?php
namespace Tests;

use App\Services\OrderDiscountAllocator;
use PHPUnit\Framework\TestCase;

class OrderDiscountAllocatorTest extends TestCase
{
    public function testAllocatesDiscountProportionallyWithRoundingRemainderOnFirstOrder(): void
    {
        $allocator = new OrderDiscountAllocator();

        $this->assertSame([0 => 34, 1 => 33, 2 => 33], $allocator->allocateFixedAmount([100, 100, 100], 100));
    }

    public function testPointsCannotExceedBalanceRequestOrAfterDiscountTotal(): void
    {
        $allocator = new OrderDiscountAllocator();

        $this->assertSame([0 => 60, 1 => 40], $allocator->allocatePoints([300, 200], 900, 100, 500));
        $this->assertSame([0 => 30, 1 => 20], $allocator->allocatePoints([300, 200], 50, 100, 500));
        $this->assertSame([0 => 3, 1 => 2], $allocator->allocatePoints([300, 200], 50, 100, 5));
    }
}
