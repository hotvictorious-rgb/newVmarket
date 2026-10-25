<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnCase extends Model
{
    protected static function booted(): void
    {
        static::saving(function (ReturnCase $returnCase): void {
            $order = $returnCase->order_id ? Order::find($returnCase->order_id) : null;
            $parentOrder = $returnCase->parent_order_id ? Order::find($returnCase->parent_order_id) : null;

            // Retailer returns belong to Victorious MARKET centrally, never to a
            // private supplier. Logistics collection-point choices remain independent.
            if (($order && $order->retailer_model_order) || ($parentOrder && $parentOrder->retailer_model_order)) {
                $returnCase->vendor_id = null;
            }
        });
    }

    protected $hidden = [
        'vendor_id',
        'customer_return_otp',
        'hub_receipt_otp',
        'vendor_return_otp',
        'vendor_disputed',
        'vendor_dispute_reason',
        'vendor_dispute_photo_path',
        'vendor_disputed_at',
    ];

    protected $fillable = [
        'case_number',
        'customer_id',
        'order_id',
        'parent_order_id',
        'vendor_id',
        'collection_point_id',
        'return_reason',
        'customer_description',
        'source',
        'rejection_category',
        'customer_return_otp',
        'customer_collection_photo_path',
        'customer_collected_at',
        'collection_failed_reason',
        'collection_failed_at',
        'destination_type',
        'hub_receipt_otp',
        'hub_received_photo_path',
        'hub_received_at',
        'supplier_handover_failed_reason',
        'supplier_handover_failed_at',
        'assigned_logistics_route_notes',
        'return_delivery_fee_charged',
        'vendor_return_otp',
        'doorstep_photo_path',
        'vendor_return_photo_path',
        'returned_to_vendor_at',
        'returned_by_rider_id',
        'vendor_disputed',
        'vendor_dispute_reason',
        'vendor_dispute_photo_path',
        'vendor_disputed_at',
        'admin_ruling',
        'admin_ruled_at',
        'cost_allocation',
        'status',
        'resolution_type',
        'total_refundable_amount',
        'refund_delivery_fee',
        'delivery_fee_refunded',
        'refunded_amount',
        'paystack_refund_id',
        'refund_claim_token',
        'refund_claimed_at',
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
        'return_delivery_fee_charged' => 'decimal:2',
        'refund_delivery_fee' => 'boolean',
        'vendor_disputed' => 'boolean',
        'customer_collected_at' => 'datetime',
        'collection_failed_at' => 'datetime',
        'hub_received_at' => 'datetime',
        'supplier_handover_failed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'refund_claimed_at' => 'datetime',
        'returned_to_vendor_at' => 'datetime',
        'vendor_disputed_at' => 'datetime',
        'admin_ruled_at' => 'datetime',
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

    public function returnItems()
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

    public function returnedByRider()
    {
        return $this->belongsTo(User::class, 'returned_by_rider_id');
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
