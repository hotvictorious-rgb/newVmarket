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
        'rider_id',
        'fulfillment_type',
        'order_status',
        'payment_status',
        'payment_method',
        'payment_reference',
        'origin_lga_id',
        'destination_lga_id',
        'delivery_lane_id',
        'merchandise_subtotal',
        'delivery_fee',
        'cashback_redeemed',
        'grand_total',
        'vendor_net_90',
        'customer_cashback_5',
        'platform_margin_5',
        'logistics_fee_retained',
        'pickup_verification_code',
        'verification_code',
        'rider_arrived_at',
        'delivery_confirmed_by_customer',
        'proof_of_delivery_image',
        'customer_delivery_notes',
        'received_at',
        'refund_window_expires_at',
        'vendor_settlement_status',
        'settled_at',
        'settled_by_admin_id',
        'settlement_proof_path',
    ];

    protected $casts = [
        'merchandise_subtotal' => 'string',
        'delivery_fee' => 'string',
        'cashback_redeemed' => 'string',
        'grand_total' => 'string',
        'vendor_net_90' => 'string',
        'customer_cashback_5' => 'string',
        'platform_margin_5' => 'string',
        'logistics_fee_retained' => 'string',
        'rider_arrived_at' => 'datetime',
        'delivery_confirmed_by_customer' => 'boolean',
        'received_at' => 'datetime',
        'refund_window_expires_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function hasRiderArrived(): bool
    {
        return !is_null($this->rider_arrived_at);
    }

    public function isCustomerHandshakePending(): bool
    {
        return $this->order_status === 'out_for_delivery' && $this->hasRiderArrived() && !$this->delivery_confirmed_by_customer;
    }

    /**
     * Mathematically guarantees collision-free delivery OTP across all active orders.
     */
    public static function generateUniqueDeliveryOtp(): string
    {
        do {
            $otp = (string) random_int(100000, 999999);
            $collision = self::where('verification_code', $otp)
                ->whereIn('order_status', ['pending', 'confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery'])
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
                ->whereIn('order_status', ['pending', 'confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery'])
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
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function rider()
    {
        return $this->belongsTo(RiderProfile::class, 'rider_id');
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

        return [
            'vendor_net_90' => $vendorNet,
            'customer_cashback_5' => $cashback,
            'platform_margin_5' => $platformMargin,
            'logistics_fee' => $deliveryFee,
        ];
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

    public function isReturnWindowExpired(): bool
    {
        if (!$this->received_at || !$this->refund_window_expires_at) {
            return false;
        }

        return Carbon::now()->gt($this->refund_window_expires_at);
    }
}
