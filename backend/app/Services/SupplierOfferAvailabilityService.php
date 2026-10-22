<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\Shop;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SupplierOfferAvailabilityService
{
    /**
     * Record an administrator-verified supplier availability statement.
     *
     * This does not contact the supplier or prove stock independently: the
     * actor is responsible for recording a real confirmation received through
     * the business's approved supplier-check process. Expiry is supplied
     * explicitly and is never silently invented.
     */
    /**
     * Record a supplier-owned unavailable declaration. The offer is removed from
     * sourcing immediately and remains blocked for at least 24 hours even if an
     * administrator later attempts to reconfirm it.
     */
    public function markUnavailable(
        int $offerId,
        User $actor,
        string $reason,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): SupplierOffer {
        if ($actor->user_type !== 'vendor' || $actor->status !== 'active') {
            throw new InvalidArgumentException('Only an active supplier owner may mark their offer unavailable.');
        }

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'reason' => 'A reason of 1 to 500 characters is required.',
            ]);
        }

        return DB::transaction(function () use ($offerId, $actor, $reason, $ipAddress, $userAgent) {
            $offer = SupplierOffer::query()->lockForUpdate()->findOrFail($offerId);
            $vendor = VendorProfile::query()
                ->whereKey($offer->vendor_id)
                ->where('user_id', $actor->id)
                ->whereNull('deleted_at')
                ->where('marketplace_status', 'active')
                ->first();

            if (!$vendor) {
                throw new InvalidArgumentException('You may only update availability for your own active supplier offers.');
            }

            $shop = Shop::query()
                ->whereKey($offer->shop_id)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('vendor_id', $vendor->id)
                ->with('lga.state')
                ->first();

            if (!$shop
                || Shop::where('vendor_id', $vendor->id)->whereNull('deleted_at')->where('is_active', true)->count() !== 1
                || strtolower(trim((string) ($shop->lga->name ?? ''))) !== 'uyo'
                || strtolower(trim((string) ($shop->lga->state->name ?? ''))) !== 'akwa ibom') {
                throw new InvalidArgumentException('Only your single active Uyo supplier shop may change offer availability.');
            }

            // Repeated clicks do not reset or extend an already-running cooldown.
            if (!$offer->is_available && $offer->last_unavailable_at
                && $offer->last_unavailable_at->gt(now()->subHours(24))) {
                return $offer;
            }

            $oldValues = [
                'is_available' => (bool) $offer->is_available,
                'last_unavailable_at' => $offer->last_unavailable_at?->toISOString(),
                'last_unavailable_by' => $offer->last_unavailable_by,
                'last_unavailable_reason' => $offer->last_unavailable_reason,
            ];

            $offer->forceFill([
                'is_available' => false,
                'last_unavailable_at' => now(),
                'last_unavailable_by' => $actor->id,
                'last_unavailable_reason' => $reason,
            ])->save();

            AdminAuditLog::create([
                'user_id' => $actor->id,
                'action' => 'supplier_offer_marked_unavailable',
                'target_type' => SupplierOffer::class,
                'target_id' => $offer->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'is_available' => false,
                    'last_unavailable_at' => $offer->last_unavailable_at?->toISOString(),
                    'last_unavailable_by' => $actor->id,
                    'last_unavailable_reason' => $reason,
                    'product_id' => $offer->product_id,
                    'vendor_id' => $offer->vendor_id,
                    'shop_id' => $offer->shop_id,
                    'cooldown_hours' => 24,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return $offer->refresh();
        }, 3);
    }

    public function confirm(
        int $offerId,
        User $actor,
        Carbon $expiresAt,
        ?string $notes = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): SupplierOffer {
        if ($actor->user_type !== 'super_admin' || $actor->status !== 'active') {
            throw new InvalidArgumentException('Only an active super administrator may confirm supplier availability.');
        }

        if (!$expiresAt->isFuture()) {
            throw ValidationException::withMessages([
                'availability_expires_at' => 'Availability expiry must be in the future.',
            ]);
        }

        return DB::transaction(function () use ($offerId, $actor, $expiresAt, $notes, $ipAddress, $userAgent) {
            $offer = SupplierOffer::query()->lockForUpdate()->findOrFail($offerId);
            $vendor = VendorProfile::query()
                ->whereKey($offer->vendor_id)
                ->whereNull('deleted_at')
                ->where('marketplace_status', 'active')
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->first();

            $shop = Shop::query()
                ->whereKey($offer->shop_id)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('vendor_id', $offer->vendor_id)
                ->first();

            $activeShopCount = Shop::where('vendor_id', $offer->vendor_id)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->count();

            if (!$vendor || !$shop || $activeShopCount !== 1) {
                throw ValidationException::withMessages([
                    'supplier_offer' => 'Availability requires an active supplier, a matching active shop, and exactly one active shop for that supplier.',
                ]);
            }

            $shop->loadMissing('lga.state');
            if (strtolower(trim((string) ($shop->lga->name ?? ''))) !== 'uyo'
                || strtolower(trim((string) ($shop->lga->state->name ?? ''))) !== 'akwa ibom') {
                throw ValidationException::withMessages([
                    'supplier_offer' => 'Only active supplier shops physically based in Uyo, Akwa Ibom State may be confirmed for retailer sourcing.',
                ]);
            }

            $normalizedNotes = $notes !== null ? trim($notes) : null;
            $requestedExpiry = $expiresAt->copy()->utc()->startOfSecond();

            // Safe retry: the same actor repeating the same still-current
            // confirmation must not create another audit event.
            if (
                $offer->is_available
                && $offer->availability_confirmed_by === $actor->id
                && $offer->availability_confirmed_at !== null
                && $offer->availability_expires_at !== null
                && $offer->availability_expires_at->copy()->utc()->startOfSecond()->equalTo($requestedExpiry)
                && ($offer->notes !== null ? trim($offer->notes) : null) === $normalizedNotes
                && $offer->availability_expires_at->isFuture()
            ) {
                return $offer;
            }

            $oldValues = [
                'is_available' => (bool) $offer->is_available,
                'availability_confirmed_at' => $offer->availability_confirmed_at?->toISOString(),
                'availability_confirmed_by' => $offer->availability_confirmed_by,
                'availability_expires_at' => $offer->availability_expires_at?->toISOString(),
                'notes' => $offer->notes,
            ];

            $offer->forceFill([
                'is_available' => true,
                'availability_confirmed_at' => now(),
                'availability_confirmed_by' => $actor->id,
                'availability_expires_at' => $requestedExpiry,
                'notes' => $normalizedNotes,
            ])->save();

            AdminAuditLog::create([
                'user_id' => $actor->id,
                'action' => 'supplier_offer_availability_confirmed',
                'target_type' => SupplierOffer::class,
                'target_id' => $offer->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'is_available' => true,
                    'availability_confirmed_at' => $offer->availability_confirmed_at?->toISOString(),
                    'availability_confirmed_by' => $actor->id,
                    'availability_expires_at' => $offer->availability_expires_at?->toISOString(),
                    'notes' => $offer->notes,
                    'product_id' => $offer->product_id,
                    'vendor_id' => $offer->vendor_id,
                    'shop_id' => $offer->shop_id,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return $offer->refresh();
        }, 3);
    }
}
