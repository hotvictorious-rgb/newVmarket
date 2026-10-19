<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'parent_order_id',
        'customer_id',
        'vendor_id',
        'shop_id',
        'collection_point_id',
        'rider_id',
        'fulfillment_type',
        'order_status',
        'payment_status',
        'paid_at',
        'payment_channel',
        'paystack_transaction_id',
        'payment_method',
        'payment_reference',
        'origin_lga_id',
        'destination_lga_id',
        'delivery_lane_id',
        'logistics_company_id',
        'merchandise_subtotal',
        'delivery_fee',
        'cashback_redeemed',
        'wallet_credit_applied',
        'grand_total',
        'vendor_net_90',
        'customer_cashback_5',
        'platform_margin_5',
        'logistics_fee_retained',
        'platform_delivery_fee',
        'delivery_attempt_count',
        'delivery_failure_reason',
        'delivery_attempt_notes',
        'delivery_attempt_photo_path',
        'last_delivery_attempt_at',
        'pickup_verification_code',
        'vendor_handover_ready_at',
        'pickup_confirmed_by_rider',
        'pickup_proof_image',
        'picked_up_at',
        'pickup_notes',
        'pickup_failed_attempts',
        'pickup_locked_until',
        'guest_access_token',
        'verification_code',
        'delivery_failed_attempts',
        'delivery_locked_until',
        'rider_arrived_at',
        'delivery_confirmed_by_customer',
        'proof_of_delivery_image',
        'doorstep_rejection_photos',
        'customer_delivery_notes',
        'received_at',
        'refund_window_expires_at',
        'vendor_settlement_status',
        'settled_at',
        'settled_by_admin_id',
        'settlement_proof_path',
        'delivery_halted_reason',
        'delivery_halted_at',
        'expires_at',
        'paystack_refund_reference',
        'paystack_refund_status',
        'refund_reconciliation_token',
        'refund_reconciliation_claimed_at',
    ];

    /**
     * Sensitive security fields hidden from default array and JSON serialization.
     * Prevents unintentional leakage to couriers, third parties, or logging inspectors.
     */
    protected $hidden = [
        'verification_code',
        'pickup_verification_code',
        'guest_access_token',
    ];

    protected $casts = [
        'merchandise_subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'cashback_redeemed' => 'decimal:2',
        'wallet_credit_applied' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'vendor_net_90' => 'decimal:2',
        'customer_cashback_5' => 'decimal:2',
        'platform_margin_5' => 'decimal:2',
        'logistics_fee_retained' => 'decimal:2',
        'platform_delivery_fee' => 'decimal:2',
        'delivery_attempt_count' => 'integer',
        'last_delivery_attempt_at' => 'datetime',
        'vendor_handover_ready_at' => 'datetime',
        'pickup_confirmed_by_rider' => 'boolean',
        'picked_up_at' => 'datetime',
        'pickup_failed_attempts' => 'integer',
        'pickup_locked_until' => 'datetime',
        'delivery_failed_attempts' => 'integer',
        'delivery_locked_until' => 'datetime',
        'rider_arrived_at' => 'datetime',
        'delivery_confirmed_by_customer' => 'boolean',
        'received_at' => 'datetime',
        'refund_window_expires_at' => 'datetime',
        'settled_at' => 'datetime',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'delivery_halted_at' => 'datetime',
        'doorstep_rejection_photos' => 'array',
    ];

    public function isPickupLocked(): bool
    {
        return !is_null($this->pickup_locked_until) && Carbon::now()->lt($this->pickup_locked_until);
    }

    public function isDeliveryLocked(): bool
    {
        return !is_null($this->delivery_locked_until) && Carbon::now()->lt($this->delivery_locked_until);
    }

    public function hasRiderArrived(): bool
    {
        return !is_null($this->rider_arrived_at);
    }

    public function isDelivery(): bool
    {
        return $this->fulfillment_type === 'doorstep_delivery';
    }

    public function isPickup(): bool
    {
        return in_array($this->fulfillment_type, ['in_shop_pickup', 'in_store_pickup'], true);
    }

    public function isFulfilled(): bool
    {
        if ($this->isDelivery()) {
            return $this->order_status === 'delivered';
        }
        return in_array($this->order_status, ['picked_up', 'delivered'], true);
    }

    public function isCustomerHandshakePending(): bool
    {
        return $this->isDelivery()
            && $this->order_status === 'out_for_delivery'
            && $this->hasRiderArrived()
            && !$this->delivery_confirmed_by_customer;
    }

    public function isVendorHandoverReady(): bool
    {
        return !is_null($this->vendor_handover_ready_at);
    }

    public function isRiderPickupPending(): bool
    {
        return $this->isDelivery()
            && in_array($this->order_status, ['confirmed', 'processing', 'ready_for_dispatch', 'ready_for_pickup'], true)
            && !$this->pickup_confirmed_by_rider;
    }

    public function scopeDelivery($query)
    {
        return $query->where('fulfillment_type', 'doorstep_delivery');
    }

    public function scopePickup($query)
    {
        return $query->whereIn('fulfillment_type', ['in_shop_pickup', 'in_store_pickup']);
    }

    public function scopeFulfilled($query)
    {
        return $query->where(function ($q) {
            $q->where(fn ($sub) => $sub->where('fulfillment_type', 'doorstep_delivery')->where('order_status', 'delivered'))
              ->orWhere(fn ($sub) => $sub->whereIn('fulfillment_type', ['in_shop_pickup', 'in_store_pickup'])->whereIn('order_status', ['picked_up', 'delivered']));
        });
    }

    /**
     * Mathematically guarantees collision-free delivery OTP across all active orders.
     */
    public static function generateUniqueDeliveryOtp(): string
    {
        do {
            $otp = (string) random_int(100000, 999999);
            $collision = self::where('verification_code', $otp)
                ->whereIn('order_status', ['pending', 'confirmed', 'processing', 'ready_for_dispatch', 'ready_for_pickup', 'out_for_delivery'])
                ->exists();
        } while ($collision);

        return $otp;
    }

    /**
     * Mathematically guarantees collision-free pickup OTP across all active orders.
     */
    public static function generateUniquePickupOtp(): string
    {
        do {
            $otp = (string) random_int(100000, 999999);
            $collision = self::where('pickup_verification_code', $otp)
                ->whereIn('order_status', ['pending', 'confirmed', 'processing', 'ready_for_dispatch', 'ready_for_pickup', 'ready_for_counter_pickup', 'out_for_delivery'])
                ->exists();
        } while ($collision);

        return $otp;
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id')->withTrashed();
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id')->withTrashed();
    }

    public function collectionPoint()
    {
        return $this->belongsTo(LogisticsCollectionPoint::class, 'collection_point_id');
    }

    public function isCollectionPointPickup(): bool
    {
        return !is_null($this->collection_point_id);
    }

    public function rider()
    {
        return $this->belongsTo(RiderProfile::class, 'rider_id')->withTrashed();
    }

    public function logisticsCompany()
    {
        return $this->belongsTo(LogisticsCompanyProfile::class, 'logistics_company_id')->withTrashed();
    }

        public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cashbackLedger()
    {
        return $this->hasOne(CustomerCashbackLedger::class);
    }

    public function dispute()
    {
        return $this->hasOne(DisputeAndReturn::class);
    }

    public function parent()
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    public function children()
    {
        return $this->hasMany(Order::class, 'parent_order_id');
    }

    public function cashbackReservation()
    {
        return $this->hasOne(CashbackReservation::class);
    }

    public function journalEntries()
    {
        return $this->hasMany(FinancialJournalEntry::class);
    }

    public function returnCases()
    {
        return $this->hasMany(ReturnCase::class);
    }

    public function payoutAllocations()
    {
        return $this->hasMany(VendorPayoutAllocation::class);
    }

    /**
     * Authoritative unallocated vendor payable balance.
     * Prevents oversettlement and partial-allocation double withdrawals.
     */
    public function getRemainingPayableToVendorAttribute(): string
    {
        $net = (string)($this->vendor_net_90 ?? '0.00');
        $allocated = (string)$this->payoutAllocations()
            ->where('status', '!=', 'reversed')
            ->whereHas('payoutRequest', function ($q) {
                $q->where('status', '!=', 'rejected');
            })
            ->sum('allocated_amount');

        $remaining = \App\Services\BcMathService::sub($net, $allocated);
        if (\App\Services\BcMathService::comp($remaining, '0.00') <= 0) {
            return '0.00';
        }
        return $remaining;
    }

    /**
     * Generate cryptographically secure, high-entropy unique payment reference (CSPRNG).
     */
    public static function generateUniquePaymentReference(): string
    {
        return 'VM-PAY-' . date('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(16));
    }

    public function originLga()
    {
        return $this->belongsTo(Lga::class, 'origin_lga_id');
    }

    public function destinationLga()
    {
        return $this->belongsTo(Lga::class, 'destination_lga_id');
    }

    /**
     * Mathematical Commercial Split Engine (90 / 5 / 5 Invariant) via BCMath.
     * Guarantees Zero Float Drift: Δ = ₦0.00.
     */
    public static function computeSplits(string $merchandiseSubtotal, string $deliveryFee = '0.00'): array
    {
        // 90% guaranteed to merchant
        $vendorNet = bcmul($merchandiseSubtotal, '0.90', 2);
        
        // 5% customer cashback rewards
        $cashback = bcmul($merchandiseSubtotal, '0.05', 2);
        
        // 5% platform retained margin (10% total commission - 5% cashback)
        $totalCommission = bcmul($merchandiseSubtotal, '0.10', 2);
        $platformMargin = bcsub($totalCommission, $cashback, 2);

        // Verification proof: $vendorNet + $cashback + $platformMargin == $merchandiseSubtotal
        $sum = bcadd(bcadd($vendorNet, $cashback, 2), $platformMargin, 2);
        $delta = bcsub($merchandiseSubtotal, $sum, 2);

        if (bccomp($delta, '0.00', 2) !== 0) {
            // Apply delta adjustment to platform margin to maintain mathematical conservation
            $platformMargin = bcadd($platformMargin, $delta, 2);
        }

        // Delivery fee split: 90% Logistics Company / 10% Victorious Market
        $logisticsNet = bcmul($deliveryFee, '0.90', 2);
        $platformDeliveryFee = bcsub($deliveryFee, $logisticsNet, 2);

        return [
            'vendor_net_90' => $vendorNet,
            'customer_cashback_5' => $cashback,
            'platform_margin_5' => $platformMargin,
            'delivery_fee' => $deliveryFee,
            'logistics_fee' => $deliveryFee,
            'logistics_net_90' => $logisticsNet,
            'platform_delivery_fee_10' => $platformDeliveryFee,
        ];
    }

    public function getLogisticsNetCutAttribute(): string
    {
        return $this->logistics_fee_retained ?? bcmul((string)$this->delivery_fee, '0.90', 2);
    }

    public function getPlatformDeliveryCutAttribute(): string
    {
        return $this->platform_delivery_fee ?? bcmul((string)$this->delivery_fee, '0.10', 2);
    }

    /**
     * 24-Hour Return Window Checks:
     * Window begins strictly upon customer physical receipt (received_at).
     */
    public function isWithinReturnWindow(): bool
    {
        if (!$this->received_at || !$this->refund_window_expires_at) {
            return false;
        }

        return Carbon::now()->lte($this->refund_window_expires_at);
    }

    public function isRefundWindowActive(): bool
    {
        return $this->isWithinReturnWindow();
    }

    public function isReturnWindowExpired(): bool
    {
        if (!$this->received_at || !$this->refund_window_expires_at) {
            return false;
        }

        return Carbon::now()->gt($this->refund_window_expires_at);
    }
}
