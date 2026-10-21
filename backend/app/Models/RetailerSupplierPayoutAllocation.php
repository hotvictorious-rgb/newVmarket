<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetailerSupplierPayoutAllocation extends Model
{
    protected $fillable = [
        'retailer_supplier_payout_request_id',
        'supplier_vendor_id',
        'order_id',
        'order_item_id',
        'amount',
        'status',
        'reversed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'reversed_at' => 'datetime',
    ];

    public function payoutRequest()
    {
        return $this->belongsTo(RetailerSupplierPayoutRequest::class, 'retailer_supplier_payout_request_id');
    }

    public function supplier()
    {
        return $this->belongsTo(VendorProfile::class, 'supplier_vendor_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
