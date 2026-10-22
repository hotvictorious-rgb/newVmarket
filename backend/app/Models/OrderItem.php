<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'supplier_offer_id',
        'supplier_vendor_id',
        'supplier_shop_id',
        'supplier_name_snapshot',
        'supplier_shop_name_snapshot',
        'supplier_cost_snapshot',
        'supplier_cost_currency',
        'supplier_snapshot_at',
        'fulfillment_supplier_offer_id',
        'fulfillment_supplier_vendor_id',
        'fulfillment_supplier_shop_id',
        'fulfillment_supplier_name_snapshot',
        'fulfillment_supplier_shop_name_snapshot',
        'fulfillment_supplier_cost_snapshot',
        'fulfillment_supplier_snapshot_at',
        'product_name',
        'package_tier_snapshot',
        'is_bulky_snapshot',
        'unit_price',
        'cost_price',
        'quantity',
        'line_total',
        'accepted_quantity',
        'rejected_quantity',
        'rejection_category',
        'rejection_notes',
        'defect_photo_path',
        'item_delivery_status',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'supplier_cost_snapshot' => 'decimal:2',
        'supplier_snapshot_at' => 'datetime',
        'fulfillment_supplier_cost_snapshot' => 'decimal:2',
        'fulfillment_supplier_snapshot_at' => 'datetime',
        'line_total' => 'decimal:2',
        'is_bulky_snapshot' => 'boolean',
        'quantity' => 'integer',
        'accepted_quantity' => 'integer',
        'rejected_quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function supplierOffer()
    {
        return $this->belongsTo(SupplierOffer::class)->withTrashed();
    }

    public function supplierVendor()
    {
        return $this->belongsTo(VendorProfile::class, 'supplier_vendor_id')->withTrashed();
    }

    public function supplierShop()
    {
        return $this->belongsTo(Shop::class, 'supplier_shop_id')->withTrashed();
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
