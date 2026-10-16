<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'vendor_id',
        'shop_id',
        'category_id',
        'name',
        'slug',
        'description',
        'thumbnail',
        'images',
        'unit_price',
        'is_in_stock',
        'inventory_quantity',
        'listing_status',
        'listing_confirmed_at',
        'is_vmarket_flagship',
        'is_approved',
        'package_tier',
    ];

    protected $casts = [
        'unit_price' => 'string',
        'is_in_stock' => 'boolean',
        'is_vmarket_flagship' => 'boolean',
        'is_approved' => 'boolean',
        'images' => 'array',
        'listing_confirmed_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * 7-Day Marketplace Freshness Invariant:
     * Product listings require explicit merchant confirmation every 7 days.
     */
    public function isFresh(): bool
    {
        if (!$this->listing_confirmed_at) {
            return false;
        }

        return Carbon::now()->diffInDays($this->listing_confirmed_at) <= 7;
    }

    /**
     * Public Marketplace Eligibility Scope:
     * Returns products that are active, approved, listed, and within the 7-day freshness window.
     */
    public function scopeMarketplaceEligible(Builder $query): Builder
    {
        return $query->where('is_approved', true)
            ->where('listing_status', 'listed')
            ->whereNotNull('listing_confirmed_at')
            ->where('listing_confirmed_at', '>=', Carbon::now()->subDays(7))
            ->whereHas('vendor', function ($q) {
                $q->where('marketplace_status', 'active');
            })
            ->whereHas('shop');
    }
}
