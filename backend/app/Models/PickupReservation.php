<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PickupReservation extends Model
{
    protected $fillable = [
        'reservation_code',
        'customer_id',
        'vendor_id',
        'shop_id',
        'product_id',
        'quantity',
        'unit_price',
        'estimated_total',
        'status',
        'inspected_at',
        'inspected_by_staff_id',
        'order_id',
        'expires_at',
    ];

    protected $casts = [
        'unit_price' => 'string',
        'estimated_total' => 'string',
        'quantity' => 'integer',
        'inspected_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function inspectedByStaff()
    {
        return $this->belongsTo(User::class, 'inspected_by_staff_id');
    }

    public static function generateCode(): string
    {
        return 'RES-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function isExpired(): bool
    {
        return Carbon::now()->gt($this->expires_at);
    }
}
