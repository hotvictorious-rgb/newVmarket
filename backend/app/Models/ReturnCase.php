<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnCase extends Model
{
    protected $fillable = [
        'case_number',
        'customer_id',
        'order_id',
        'parent_order_id',
        'vendor_id',
        'collection_point_id',
        'return_reason',
        'customer_description',
        'status',
        'resolution_type',
        'total_refundable_amount',
        'refund_delivery_fee',
        'delivery_fee_refunded',
        'refunded_amount',
        'paystack_refund_id',
        'refund_proof_path',
        'bank_reference',
        'refunded_at',
        'reviewed_by_admin_id',
        'admin_notes',
    ];

    protected $casts = [
        'total_refundable_amount' => 'string',
        'delivery_fee_refunded' => 'string',
        'refunded_amount' => 'string',
        'refund_delivery_fee' => 'boolean',
        'refunded_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function parentOrder()
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function collectionPoint()
    {
        return $this->belongsTo(LogisticsCollectionPoint::class, 'collection_point_id');
    }

    public function items()
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function evidence()
    {
        return $this->hasMany(ReturnEvidence::class);
    }

    public function inspections()
    {
        return $this->hasMany(ReturnInspection::class);
    }

    public function recoveryEntries()
    {
        return $this->hasMany(VendorRecoveryLedger::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin_id');
    }

    public function getTotalReturnAmountAttribute(): string
    {
        return $this->total_refundable_amount ?? '0.00';
    }

    public function getCustomerReasonAttribute(): ?string
    {
        return $this->customer_description ?? $this->return_reason;
    }

    public function getRefundReferenceAttribute(): ?string
    {
        return $this->bank_reference ?? $this->paystack_refund_id;
    }

    public function getRefundReceiptPathAttribute(): ?string
    {
        return $this->refund_proof_path;
    }

    public static function generateCaseNumber(): string
    {
        return 'RET-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6));
    }
}
