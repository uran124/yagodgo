<?php
namespace App\Models;

if (!class_exists('Illuminate\\Database\\Eloquent\\Model')) {
    require_once __DIR__ . '/../Support/EloquentModelStub.php';
}

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'user_id', 'address_id', 'slot_id', 'assigned_to',
        'status', 'total_amount', 'discount_applied',
        'points_used', 'points_accrued', 'manager_points_accrued', 'coupon_code', 'created_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function calculateReferralDiscount(int $sum): int
    {
        return (int) floor($sum * 0.10);
    }

    // Метод для подсчёта максимума списания баллов (без ограничений)
    public static function calculateMaxPointsUsage(int $sumAfterDiscount): int
    {
        return (int) $sumAfterDiscount;
    }

    // Метод для подсчёта личного начисления (5% от суммы после списания баллов)
    public static function calculatePersonalBonus(int $sumAfterPoints): int
    {
        return (int) floor($sumAfterPoints * 0.05);
    }

    // Метод для подсчёта реферального бонуса: для партнёра 10% с первого заказа клиента, затем 3%
    public static function calculateReferralBonus(int $sumBeforeDiscount, bool $isPartner = false, bool $isFirstClientOrder = false): int
    {
        if (!$isPartner) {
            return (int) floor($sumBeforeDiscount * 0.03);
        }

        $rate = $isFirstClientOrder ? 0.10 : 0.03;
        return (int) floor($sumBeforeDiscount * $rate);
    }

    // Управляющий менеджер получает базовые 3% с каждой продажи проекта
    public static function calculateProjectManagerBonus(int $sumBeforeDiscount): int
    {
        return (int) floor($sumBeforeDiscount * 0.03);
    }

    // Если клиент пришёл по ссылке менеджера, менеджер получает ещё 3% сверх базовых 3%
    public static function calculateManagerReferralBonus(int $sumBeforeDiscount): int
    {
        return (int) floor($sumBeforeDiscount * 0.03);
    }
}
