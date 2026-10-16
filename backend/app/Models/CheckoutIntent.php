<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class CheckoutIntent extends Model
{
    protected $fillable = [
        'user_id',
        'fingerprint',
        'fulfillment_type',
        'customer_address_id',
        'shop_id',
        'merchandise_subtotal',
        'delivery_fee',
        'cashback_applied',
        'wallet_credit_applied',
        'total_payable',
        'payload_snapshot',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'merchandise_subtotal' => 'string',
        'delivery_fee' => 'string',
        'cashback_applied' => 'string',
        'wallet_credit_applied' => 'string',
        'total_payable' => 'string',
        'payload_snapshot' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function address()
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function isExpired(): bool
    {
        return Carbon::now()->gt($this->expires_at);
    }

    public static function generateFingerprint(array $payload): string
    {
        return hash('sha256', json_encode($payload));
    }
}
