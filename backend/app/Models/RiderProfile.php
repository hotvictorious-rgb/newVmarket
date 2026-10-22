<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RiderProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'vendor_id',
        'shop_id',
        'logistics_company_id',
        'assigned_lga_id',
        'vehicle_type',
        'license_plate',
        'nin_number',
        'is_active',
        'is_online',
        'current_latitude',
        'current_longitude',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'deleted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id')->withTrashed();
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id')->withTrashed();
    }

    public function logisticsCompany()
    {
        return $this->belongsTo(LogisticsCompanyProfile::class, 'logistics_company_id')->withTrashed();
    }

    public function assignedLga()
    {
        return $this->belongsTo(Lga::class, 'assigned_lga_id');
    }

    public function assignedOrders()
    {
        return $this->hasMany(Order::class, 'rider_id');
    }

    public function isVendorRider(): bool
    {
        return !empty($this->vendor_id);
    }

    /**
     * New Victorious MARKET delivery work is centrally dispatched through one
     * active logistics company. Vendor-linked or shop-linked riders remain historical only.
     */
    public function isEligibleForCentralDelivery(): bool
    {
        // A central-delivery rider must have exactly one operational owner:
        // an active logistics company. Legacy vendor OR shop linkage is not
        // allowed to coexist with central dispatch, even if vendor_id is null.
        if (!$this->is_active
            || $this->vendor_id !== null
            || $this->shop_id !== null
            || !$this->logistics_company_id) {
            return false;
        }

        if ($this->user && $this->user->status !== 'active') {
            return false;
        }

        $assignedLga = $this->assignedLga;
        if (!$assignedLga) {
            return false;
        }
        $assignedLga->loadMissing('state');
        if (strtolower(trim((string) ($assignedLga->name ?? ''))) !== 'uyo'
            || strtolower(trim((string) ($assignedLga->state->name ?? ''))) !== 'akwa ibom') {
            return false;
        }

        $company = $this->logisticsCompany;
        return $company !== null
            && $company->deleted_at === null
            && $company->status === 'active'
            && (!$company->user || $company->user->status === 'active');
    }

    /**
     * Validate the rider's single active logistics-company relationship against
     * the company assigned to a specific new retailer delivery.
     */
    public function isEligibleForCentralOrder(Order $order): bool
    {
        if (!$this->isEligibleForCentralDelivery()
            || (int) $order->rider_id !== (int) $this->id
            || empty($order->logistics_company_id)
            || (int) $order->logistics_company_id !== (int) $this->logistics_company_id) {
            return false;
        }

        // Preserve legacy historical/marketplace routing; strict vehicle and
        // corridor locks apply to the centrally managed retailer launch model.
        if (!$order->retailer_model_order) {
            return true;
        }

        $vehicle = strtolower(trim((string) $this->vehicle_type));
        $requiresKeke = $order->requiresKekeForDelivery();
        if ($requiresKeke && !in_array($vehicle, ['keke', 'tricycle'], true)) {
            return false;
        }
        if (!$requiresKeke && !in_array($vehicle, ['bike', 'motorbike', 'motorcycle'], true)) {
            return false;
        }

        // Dispatch lock: an exclusive Keke order cannot share a rider's active
        // trip with any other customer order. Bikes may only batch standard
        // orders whose snapshotted destination corridor is exactly the same.
        $activeStatuses = ['confirmed', 'processing', 'ready_for_dispatch', 'ready_for_pickup', 'out_for_delivery'];
        $otherActiveOrders = $this->assignedOrders()
            ->where('orders.id', '<>', $order->id)
            ->where('orders.fulfillment_type', 'doorstep_delivery')
            ->where('orders.retailer_model_order', true)
            ->whereIn('orders.order_status', $activeStatuses)
            ->get();

        if ($requiresKeke) {
            return $otherActiveOrders->isEmpty();
        }

        foreach ($otherActiveOrders as $otherOrder) {
            if ($otherOrder->requiresKekeForDelivery()
                || empty($order->delivery_corridor_id)
                || (int) $otherOrder->delivery_corridor_id !== (int) $order->delivery_corridor_id) {
                return false;
            }
        }

        return true;
    }

    public function isFleetRider(): bool
    {
        return !empty($this->logistics_company_id);
    }

    /**
     * Check if courier currently holds parcels in physical custody during transit.
     */
    public function hasActiveCustodyOrders(): bool
    {
        return $this->assignedOrders()
            ->where('order_status', 'out_for_delivery')
            ->exists();
    }

    /**
     * Clearance guard: Validates whether courier profile can be deactivated.
     */
    public function canBeDeactivated(?string &$reason = null): bool
    {
        if ($this->hasActiveCustodyOrders()) {
            $reason = 'Courier has active customer parcels in physical custody (out for delivery). Complete doorstep handovers or return to hub before deactivation.';
            return false;
        }

        return true;
    }

    /**
     * Deletion guard: In e-commerce & fintech, entities with delivery history
     * can NEVER be deleted due to chain-of-custody audit logs.
     */
    public function canBeSafelyDeleted(?string &$reason = null): bool
    {
        if ($this->assignedOrders()->exists()) {
            $reason = 'Courier profile cannot be deleted because permanent historical delivery logs exist.';
            return false;
        }

        return true;
    }
}
