<?php
namespace Tests;

use App\Support\PaymentMethods;
use App\Support\PaymentStatuses;
use PHPUnit\Framework\TestCase;

class PaymentSupportTest extends TestCase
{
    public function testPaymentStatusesContainRefundPending(): void
    {
        $this->assertContains('refund_pending', PaymentStatuses::all());
        $this->assertSame('Ожидает возврата', PaymentStatuses::labels()['refund_pending']);
    }

    public function testEmptyPaymentStatusFallsBackToUnpaid(): void
    {
        $this->assertSame('Не оплачен', PaymentStatuses::info(null)['label']);
        $this->assertSame('Не оплачен', PaymentStatuses::info('')['label']);
    }

    public function testPaymentMethodLabelsAndSettingKeys(): void
    {
        $this->assertSame('Онлайн Robokassa', PaymentMethods::label('online_robokassa'));
        $this->assertSame('payment_method_cash_pickup_enabled', PaymentMethods::settingKey('cash_pickup'));
    }
}
