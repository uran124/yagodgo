<?php
namespace App\Support;

final class OrderStatuses
{
    public const RESERVED = 'reserved';
    public const NEW = 'new';
    public const CONFIRMED = 'confirmed';
    public const SHIPPED = 'shipped';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const RETURNED = 'returned';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::RESERVED,
            self::NEW,
            self::CONFIRMED,
            self::SHIPPED,
            self::COMPLETED,
            self::CANCELLED,
            self::RETURNED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function active(): array
    {
        return [self::RESERVED, self::NEW, self::CONFIRMED, self::SHIPPED];
    }

    /**
     * @return array<int, string>
     */
    public static function terminal(): array
    {
        return [self::COMPLETED, self::CANCELLED, self::RETURNED];
    }

    /**
     * @return array<int, string>
     */
    public static function successful(): array
    {
        return [self::COMPLETED];
    }

    public static function isAllowed(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    public static function normalize(string $status): string
    {
        return match ($status) {
            'processing' => self::CONFIRMED,
            'assigned' => self::SHIPPED,
            'delivered' => self::COMPLETED,
            default => $status,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::RESERVED => 'Бронь',
            self::NEW => 'Ожидает подтверждения',
            self::CONFIRMED => 'Подтверждён',
            self::SHIPPED => 'В пути',
            self::COMPLETED => 'Выполнен',
            self::CANCELLED => 'Отменён',
            self::RETURNED => 'Возврат',
        ];
    }

    /**
     * @return array<string, array{label:string,badge:string,bg:string}>
     */
    public static function infoMap(): array
    {
        return [
            self::RESERVED => [
                'label' => 'Бронь',
                'badge' => 'bg-purple-100 text-purple-800',
                'bg' => 'bg-purple-50',
            ],
            self::NEW => [
                'label' => 'Ожидает подтверждения',
                'badge' => 'bg-red-100 text-red-800',
                'bg' => 'bg-red-50',
            ],
            self::CONFIRMED => [
                'label' => 'Подтверждён',
                'badge' => 'bg-yellow-100 text-yellow-800',
                'bg' => 'bg-yellow-50',
            ],
            self::SHIPPED => [
                'label' => 'В пути',
                'badge' => 'bg-green-100 text-green-800',
                'bg' => 'bg-green-50',
            ],
            self::COMPLETED => [
                'label' => 'Выполнен',
                'badge' => 'bg-blue-100 text-blue-800',
                'bg' => 'bg-blue-50',
            ],
            self::CANCELLED => [
                'label' => 'Отменён',
                'badge' => 'bg-gray-100 text-gray-800',
                'bg' => 'bg-gray-50',
            ],
            self::RETURNED => [
                'label' => 'Возврат',
                'badge' => 'bg-orange-100 text-orange-800',
                'bg' => 'bg-orange-50',
            ],
        ];
    }

    /**
     * @return array{label:string,badge:string,bg:string}
     */
    public static function info(string $status): array
    {
        $normalized = self::normalize($status);
        $map = self::infoMap();
        return $map[$normalized] ?? [
            'label' => $status,
            'badge' => 'bg-gray-100 text-gray-800',
            'bg' => '',
        ];
    }
}
