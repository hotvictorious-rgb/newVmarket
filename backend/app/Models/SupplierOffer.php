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
        'notes',
    ];

    protected $casts = [
        'wholesale_cost' => 'string',
        'is_available' => 'boolean',
    ];

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
