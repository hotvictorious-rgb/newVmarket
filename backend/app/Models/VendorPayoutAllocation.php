<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorPayoutAllocation extends Model
{
    protected $fillable = [
        'vendor_payout_request_id',
        'order_id',
        'allocated_amount',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
    ];

    public function payoutRequest()
    {
        return $this->belongsTo(VendorPayoutRequest::class, 'vendor_payout_request_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
