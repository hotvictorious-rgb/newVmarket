<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorRecoveryLedger extends Model
{
    protected $fillable = [
        'vendor_id',
        'return_case_id',
        'idempotency_key',
        'order_id',
        'amount',
        'status',
        'notes',
        'recovered_at',
    ];

    protected $casts = [
        'amount' => 'string',
        'recovered_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function returnCase()
    {
        return $this->belongsTo(ReturnCase::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
