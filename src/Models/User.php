<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'name', 'phone', 'password_hash',
        'referral_code', 'referred_by', 'points_balance'
    ];

    protected $hidden = [
        'password_hash',
    ];

    // Связь «кто пригласил»
    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    // Связь «кого пригласил»
    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    // Транзакции баллов
    public function pointsTransactions()
    {
        return $this->hasMany(PointsTransaction::class, 'user_id');
    }
    
    public function isFirstReferredOrder(): bool
    {
        return $this->referred_by !== null && $this->has_used_referral_coupon == 0;
    }

    // Помощник: списать баллы, создать транзакцию «usage»
    public function usePoints(int $amount, int $orderId = null): void
    {
        if ($amount <= 0 || $this->points_balance < $amount) {
            return;
        }
        $this->points_balance -= $amount;
        $this->save();
        PointsTransaction::create([
            'user_id' => $this->id,
            'order_id' => $orderId,
            'amount' => -$amount,
            'transaction_type' => 'usage',
            'description' => "Списание {$amount} клубничек за заказ #{$orderId}",
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // Помощник: начислить баллы, типом «accrual»
    public function addPoints(int $amount, string $description, int $orderId = null): void
    {
        if ($amount <= 0) {
            return;
        }
        $this->points_balance += $amount;
        $this->save();
        PointsTransaction::create([
            'user_id' => $this->id,
            'order_id' => $orderId,
            'amount' => $amount,
            'transaction_type' => 'accrual',
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }    
    
    
    
}
