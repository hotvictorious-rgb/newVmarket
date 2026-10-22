<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierOffer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'vendor_id',
        'shop_id',
        'wholesale_cost',
        'is_available',
        'availability_confirmed_at',
        'availability_confirmed_by',
        'availability_expires_at',
        'unavailability_penalty_expires_at',
        'notes',
    ];

    protected $casts = [
        'wholesale_cost' => 'string',
        'is_available' => 'boolean',
        'availability_confirmed_at' => 'datetime',
        'availability_expires_at' => 'datetime',
        'unavailability_penalty_expires_at' => 'datetime',
    ];

    /**
     * Check if this supplier offer is currently blocked by a 24-hour stockout penalty.
     */
    public function isPenaltyActive(): bool
    {
        return $this->unavailability_penalty_expires_at !== null
            && $this->unavailability_penalty_expires_at->isFuture();
    }

    public function availabilityConfirmer()
    {
        return $this->belongsTo(User::class, 'availability_confirmed_by');
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id')->withTrashed();
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class)->withTrashed();
    }
}
