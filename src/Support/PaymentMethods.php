<?php
namespace App\Support;

final class PaymentMethods
{
    public const ONLINE_ROBOKASSA = 'online_robokassa';
    public const CASH_ON_DELIVERY = 'cash_on_delivery';
    public const CASH_PICKUP = 'cash_pickup';
    public const CARD_ON_DELIVERY = 'card_on_delivery';
    public const CARD_PICKUP = 'card_pickup';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::ONLINE_ROBOKASSA,
            self::CASH_ON_DELIVERY,
            self::CASH_PICKUP,
            self::CARD_ON_DELIVERY,
            self::CARD_PICKUP,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ONLINE_ROBOKASSA => 'Онлайн Robokassa',
            self::CASH_ON_DELIVERY => 'Наличными при доставке',
            self::CASH_PICKUP => 'Наличными при самовывозе',
            self::CARD_ON_DELIVERY => 'Картой при доставке',
            self::CARD_PICKUP => 'Картой при самовывозе',
        ];
    }

    public static function label(?string $method): string
    {
        if ($method === null || $method === '') {
            return 'Не выбран';
        }

        $labels = self::labels();
        return $labels[$method] ?? $method;
    }

    public static function settingKey(string $method): string
    {
        return 'payment_method_' . $method . '_enabled';
    }
}
