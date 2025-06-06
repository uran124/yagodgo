<?php
namespace Models;

use Illuminate\Database\Eloquent\Model;

class PointsTransaction extends Model
{
    protected $table = 'points_transactions';

    public $timestamps = false; // у нас есть своё created_at

    protected $fillable = [
        'user_id', 'order_id', 'amount', 'transaction_type', 'description', 'created_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order()
    {
        return $this->belongsTo(\Models\Order::class, 'order_id');
    }
}
