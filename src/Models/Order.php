<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'user_id', 'address_id', 'slot_id', 'assigned_to',
        'status', 'total_amount', 'discount_applied',
        'points_used', 'points_accrued', 'created_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function calculateReferralDiscount(int $sum): int
    {
        return (int) floor($sum * 0.10);
    }

    // Метод для подсчёта максимума списания баллов (30% от суммы после скидки)
    public static function calculateMaxPointsUsage(int $sumAfterDiscount): int
    {
        return (int) floor($sumAfterDiscount * 0.30);
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
