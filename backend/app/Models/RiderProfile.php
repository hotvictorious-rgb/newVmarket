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
     * active logistics company. Vendor-linked riders remain historical only.
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
        return $this->isEligibleForCentralDelivery()
            && (int) $order->rider_id === (int) $this->id
            && !empty($order->logistics_company_id)
            && (int) $order->logistics_company_id === (int) $this->logistics_company_id;
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
