<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Services\BcMathService;
use InvalidArgumentException;

class DeliveryCorridor extends Model
{
    protected $fillable = [
        'code',
        'name',
        'lga_id',
        'landmark_aliases',
        'customer_base_fee',
        'customer_bulky_surcharge_per_unit',
        'estimated_delivery_hours_min',
        'estimated_delivery_hours_max',
        'is_active',
    ];

    protected $casts = [
        'landmark_aliases' => 'array',
        'customer_base_fee' => 'decimal:2',
        'customer_bulky_surcharge_per_unit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function lga()
    {
        return $this->belongsTo(Lga::class);
    }

    public function calculateCustomerFee(int $bulkyQuantity): string
    {
        if ($bulkyQuantity < 0) {
            throw new InvalidArgumentException('Bulky quantity cannot be negative.');
        }

        return BcMathService::add(
            (string) $this->customer_base_fee,
            BcMathService::mul((string) $this->customer_bulky_surcharge_per_unit, (string) $bulkyQuantity)
        );
    }

    public function companyRates()
    {
        return $this->hasMany(LogisticsCompanyCorridorRate::class);
    }

    /**
     * Resolve only an unambiguous, admin-configured landmark alias.
     * Unknown or conflicting landmarks fail closed rather than guessing a route.
     */
    public static function resolveForAddress(int $lgaId, ?string $addressLine, ?string $landmark): ?self
    {
        $address = Str::lower(trim(($landmark ?? '') . ' ' . ($addressLine ?? '')));
        if ($address === '') {
            return null;
        }

        $matches = static::query()
            ->where('lga_id', $lgaId)
            ->where('is_active', true)
            ->get()
            ->filter(function (self $corridor) use ($address) {
                foreach ((array) $corridor->landmark_aliases as $alias) {
                    $normalized = Str::lower(trim((string) $alias));
                    if ($normalized !== '' && Str::contains($address, $normalized)) {
                        return true;
                    }
                }
                return false;
            });

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
