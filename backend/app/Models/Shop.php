<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Shop extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'name',
        'slug',
        'address',
        'country_id',
        'state_id',
        'lga_id',
        'latitude',
        'longitude',
        'phone',
        'email',
        'is_primary_branch',
        'is_active',
        'pickup_enabled',
        'pickup_prep_time_minutes',
        'pickup_opening_time',
        'pickup_closing_time',
    ];

    protected $casts = [
        'is_primary_branch' => 'boolean',
        'is_active' => 'boolean',
        'pickup_enabled' => 'boolean',
        'pickup_prep_time_minutes' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'deleted_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id')->withTrashed();
    }

    public function lga()
    {
        return $this->belongsTo(Lga::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function employees()
    {
        return $this->hasMany(VendorEmployeeProfile::class, 'shop_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'shop_id');
    }

    public function pickupReservations()
    {
        return $this->hasMany(PickupReservation::class, 'shop_id');
    }

    public function deliveryCoverages()
    {
        return $this->hasMany(ShopDeliveryCoverage::class, 'shop_id');
    }

    /**
     * Checks if this shop's riders cover a destination LGA or entire state.
     */
    public function coversDestination(int $destinationLgaId, ?int $destinationStateId = null): bool
    {
        if (!$destinationStateId) {
            $destinationStateId = Lga::where('id', $destinationLgaId)->value('state_id');
        }

        return $this->deliveryCoverages()
            ->where('is_active', true)
            ->where(function ($q) use ($destinationLgaId, $destinationStateId) {
                $q->where('lga_id', $destinationLgaId);
                if ($destinationStateId) {
                    $q->orWhere(function ($stateQuery) use ($destinationStateId) {
                        $stateQuery->whereNull('lga_id')
                                   ->where('state_id', $destinationStateId);
                    });
                }
            })
            ->exists();
    }

    /**
     * Active state from database column, with helper for full cascade
     */
    public function getIsActiveAttribute($value): bool
    {
        return (bool)($value ?? true);
    }

    public function isFullyActive(): bool
    {
        return $this->is_active && ($this->vendor ? $this->vendor->marketplace_status === 'active' : true);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'shop_id');
    }

    /**
     * Check if shop has in-flight unfulfilled orders.
     */
    public function hasInFlightOrders(): bool
    {
        return $this->orders()
            ->whereIn('order_status', ['pending', 'confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery'])
            ->exists();
    }

    /**
     * Check if shop has pending escrow balances.
     */
    public function hasPendingEscrow(): bool
    {
        return $this->orders()
            ->whereIn('vendor_settlement_status', ['held', 'eligible'])
            ->exists();
    }

    /**
     * Check if shop has active in-store pickup reservations.
     */
    public function hasActivePickupReservations(): bool
    {
        return $this->pickupReservations()
            ->whereIn('status', ['reserved', 'pending'])
            ->exists();
    }

    /**
     * Clearance guard: Validates whether a shop can be safely deactivated or closed.
     */
    public function canBeClosedOrDeactivated(?string &$reason = null): bool
    {
        if ($this->hasInFlightOrders()) {
            $reason = 'Shop branch has active in-flight customer orders that must be fulfilled or resolved first.';
            return false;
        }

        if ($this->hasPendingEscrow()) {
            $reason = 'Shop branch has pending escrow settlement balances that must be cleared first.';
            return false;
        }

        if ($this->hasActivePickupReservations()) {
            $reason = 'Shop branch has pending customer pickup reservations awaiting collection.';
            return false;
        }

        // Primary branch protection: cannot deactivate primary branch if other active branches exist
        $otherActiveShops = self::where('vendor_id', $this->vendor_id)
            ->where('id', '!=', $this->id)
            ->where('is_active', true)
            ->exists();

        if ($this->is_primary_branch && $otherActiveShops) {
            $reason = 'Cannot close or deactivate the primary branch while other branches are operational. Please designate another active branch as primary first.';
            return false;
        }

        return true;
    }

    /**
     * Deletion guard: In e-commerce & fintech, commercial entities with transaction history
     * can NEVER be deleted due to financial ledgers, audit trails, and tax compliance.
     */
    public function canBeSafelyDeleted(?string &$reason = null): bool
    {
        if ($this->orders()->exists()) {
            $reason = 'Shop branch cannot be deleted because permanent historical transaction records and financial ledgers exist.';
            return false;
        }

        if ($this->pickupReservations()->exists()) {
            $reason = 'Shop branch cannot be deleted because historical customer reservations exist.';
            return false;
        }

        if ($this->products()->exists()) {
            $reason = 'Shop branch cannot be deleted while products are still assigned to it. Reassign or remove products first.';
            return false;
        }

        $otherActiveShops = self::where('vendor_id', $this->vendor_id)
            ->where('id', '!=', $this->id)
            ->exists();

        if ($this->is_primary_branch && $otherActiveShops) {
            $reason = 'Cannot delete the primary branch while other branches exist. Designate another branch as primary first.';
            return false;
        }

        return true;
    }

    /**
     * Designates this shop as the merchant's primary branch atomically.
     */
    public function makePrimaryBranch(): bool
    {
        return DB::transaction(function () {
            self::where('vendor_id', $this->vendor_id)
                ->where('id', '!=', $this->id)
                ->update(['is_primary_branch' => false]);

            $this->is_primary_branch = true;
            $this->is_active = true;
            return $this->save();
        });
    }

    /**
     * Atomically transfers all products from this shop to another branch of the same vendor.
     */
    public function transferProductsTo(Shop $targetShop): int
    {
        if ((int)$this->vendor_id !== (int)$targetShop->vendor_id) {
            throw new \InvalidArgumentException('Cannot transfer products to a shop owned by another merchant.');
        }

        if ((int)$this->id === (int)$targetShop->id) {
            return 0;
        }

        return DB::transaction(function () use ($targetShop) {
            return Product::where('shop_id', $this->id)
                ->update(['shop_id' => $targetShop->id]);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereHas('vendor', function ($q) {
                $q->where('marketplace_status', 'active');
            });
    }
}
