<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryLane extends Model
{
    protected $fillable = [
        'origin_lga_id',
        'destination_lga_id',
        'assigned_logistics_company_id',
        'base_fee',
        'bulky_surcharge',
        'estimated_delivery_hours_min',
        'estimated_delivery_hours_max',
        'is_active',
    ];

    protected $casts = [
        'base_fee' => 'string',
        'bulky_surcharge' => 'string',
        'is_active' => 'boolean',
    ];

    public function originLga()
    {
        return $this->belongsTo(Lga::class, 'origin_lga_id');
    }

    public function destinationLga()
    {
        return $this->belongsTo(Lga::class, 'destination_lga_id');
    }

    public function assignedLogisticsCompany()
    {
        return $this->belongsTo(LogisticsCompanyProfile::class, 'assigned_logistics_company_id');
    }

    /**
     * Resolves directional delivery fee between two LGAs using BCMath.
     */
    public static function resolveFee(int $originLgaId, int $destinationLgaId, string $packageTier = 'bike'): ?array
    {
        // Binding retailer policy: fee resolution itself must reject any non-Uyo route,
        // even when an old active inter-LGA lane remains in the database.
        $origin = Lga::with('state')->find($originLgaId);
        $destination = Lga::with('state')->find($destinationLgaId);
        $isUyoLga = static function ($lga): bool {
            return $lga
                && strtolower(trim((string) $lga->name)) === 'uyo'
                && $lga->state
                && strtolower(trim((string) $lga->state->name)) === 'akwa ibom';
        };

        if (!$isUyoLga($origin) || !$isUyoLga($destination)) {
            return null;
        }

        $lane = self::where('origin_lga_id', $originLgaId)
            ->where('destination_lga_id', $destinationLgaId)
            ->where('is_active', true)
            ->first();

        if (!$lane) {
            return null;
        }

        $fee = $lane->base_fee;
        if (in_array(strtolower($packageTier), ['keke', 'bulky'], true)) {
            $fee = bcadd($fee, $lane->bulky_surcharge, 2);
        }

        return [
            'lane_id' => $lane->id,
            'fee' => $fee,
            'min_hours' => $lane->estimated_delivery_hours_min,
            'max_hours' => $lane->estimated_delivery_hours_max,
            'is_available' => true,
        ];
    }
}
