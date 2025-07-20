<?php
namespace App\Models;

// В тестовой среде Illuminate может отсутствовать
if (!class_exists('Illuminate\\Database\\Eloquent\\Model')) {
    class_alias('\\stdClass', 'Illuminate\\Database\\Eloquent\\Model');
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

    // Метод для подсчёта реферального бонуса (3% от суммы до скидки)
    public static function calculateReferralBonus(int $sumBeforeDiscount): int
    {
        return (int) floor($sumBeforeDiscount * 0.03);
    }
}
