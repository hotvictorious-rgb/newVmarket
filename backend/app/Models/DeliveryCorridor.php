<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DeliveryCorridor extends Model
{
    protected $fillable = [
        'code',
        'name',
        'lga_id',
        'landmark_aliases',
        'estimated_delivery_hours_min',
        'estimated_delivery_hours_max',
        'is_active',
    ];

    protected $casts = [
        'landmark_aliases' => 'array',
        'is_active' => 'boolean',
    ];

    public function lga()
    {
        return $this->belongsTo(Lga::class);
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
            ->whereHas('companyRates', function ($rates) {
                $rates->where('is_active', true)
                    ->whereHas('logisticsCompany', function ($companies) {
                        $companies->where('status', 'active')
                            ->whereNull('deleted_at')
                            ->whereHas('hqLga', function ($lga) {
                                $lga->whereRaw('LOWER(TRIM(name)) = ?', ['uyo'])
                                    ->whereHas('state', fn ($state) => $state->whereRaw('LOWER(TRIM(name)) = ?', ['akwa ibom']));
                            });
                    });
            })
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
