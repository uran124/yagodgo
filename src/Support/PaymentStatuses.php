<?php
namespace App\Support;

final class PaymentStatuses
{
    public const UNPAID = 'unpaid';
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const REFUND_PENDING = 'refund_pending';
    public const REFUNDED = 'refunded';
    public const PARTIALLY_REFUNDED = 'partially_refunded';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::UNPAID,
            self::PENDING,
            self::PAID,
            self::FAILED,
            self::REFUND_PENDING,
            self::REFUNDED,
            self::PARTIALLY_REFUNDED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::UNPAID => 'Не оплачен',
            self::PENDING => 'Ожидает оплаты',
            self::PAID => 'Оплачен',
            self::FAILED => 'Ошибка оплаты',
            self::REFUND_PENDING => 'Ожидает возврата',
            self::REFUNDED => 'Возвращён',
            self::PARTIALLY_REFUNDED => 'Частично возвращён',
        ];
    }

    /**
     * @return array<string, array{label:string,badge:string}>
     */
    public static function infoMap(): array
    {
        return [
            self::UNPAID => [
                'label' => 'Не оплачен',
                'badge' => 'bg-gray-100 text-gray-800',
            ],
            self::PENDING => [
                'label' => 'Ожидает оплаты',
                'badge' => 'bg-yellow-100 text-yellow-800',
            ],
            self::PAID => [
                'label' => 'Оплачен',
                'badge' => 'bg-green-100 text-green-800',
            ],
            self::FAILED => [
                'label' => 'Ошибка оплаты',
                'badge' => 'bg-red-100 text-red-800',
            ],
            self::REFUND_PENDING => [
                'label' => 'Ожидает возврата',
                'badge' => 'bg-orange-100 text-orange-800',
            ],
            self::REFUNDED => [
                'label' => 'Возвращён',
                'badge' => 'bg-blue-100 text-blue-800',
            ],
            self::PARTIALLY_REFUNDED => [
                'label' => 'Частично возвращён',
                'badge' => 'bg-purple-100 text-purple-800',
            ],
        ];
    }

    /**
     * @return array{label:string,badge:string}
     */
    public static function info(?string $status): array
    {
        $status = $status !== null && $status !== '' ? $status : self::UNPAID;
        $map = self::infoMap();
        return $map[$status] ?? [
            'label' => $status,
            'badge' => 'bg-gray-100 text-gray-800',
        ];
    }
}
