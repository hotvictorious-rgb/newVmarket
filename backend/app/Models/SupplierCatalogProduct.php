<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierCatalogProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'supplier_vendor_id',
        'supplier_shop_id',
        'product_name',
        'description',
        'photo_path',
        'brand',
        'model',
        'variant',
        'condition',
        'package_tier',
        'specifications',
        'source_price',
        'is_in_stock',
        'status',
        'canonical_product_id',
        'linked_by_admin_id',
        'linked_at',
        'match_notes',
    ];

    protected $casts = [
        'source_price' => 'string',
        'is_in_stock' => 'boolean',
        'specifications' => 'array',
        'linked_at' => 'datetime',
    ];

    // Source price, supplier identity, and match metadata must never be serialized
    // through public product endpoints.
    protected $hidden = [
        'supplier_vendor_id',
        'supplier_shop_id',
        'source_price',
        'canonical_product_id',
        'linked_by_admin_id',
        'linked_at',
        'match_notes',
        'deleted_at',
    ];

    public function supplier()
    {
        return $this->belongsTo(VendorProfile::class, 'supplier_vendor_id')->withTrashed();
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'supplier_shop_id')->withTrashed();
    }

    public function canonicalProduct()
    {
        return $this->belongsTo(Product::class, 'canonical_product_id')->withTrashed();
    }

    public function linkedOffer()
    {
        return $this->hasOne(SupplierOffer::class, 'supplier_catalog_product_id');
    }

    public function linkedByAdmin()
    {
        return $this->belongsTo(User::class, 'linked_by_admin_id');
    }
}
