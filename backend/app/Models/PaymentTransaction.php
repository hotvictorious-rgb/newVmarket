<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'reference',
        'gateway',
        'amount',
        'currency',
        'status',
        'raw_response',
    ];

    protected $casts = [
        'amount' => 'string',
        'raw_response' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
