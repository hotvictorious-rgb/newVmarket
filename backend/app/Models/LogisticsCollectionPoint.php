<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsCollectionPoint extends Model
{
    protected $fillable = [
        'logistics_company_id',
        'state_id',
        'lga_id',
        'name',
        'code',
        'address',
        'landmark',
        'contact_phone',
        'contact_person',
        'operating_hours',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function logisticsCompany()
    {
        return $this->belongsTo(LogisticsCompanyProfile::class, 'logistics_company_id');
    }

    public function company()
    {
        return $this->belongsTo(LogisticsCompanyProfile::class, 'logistics_company_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function lga()
    {
        return $this->belongsTo(Lga::class, 'lga_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'collection_point_id');
    }

    /**
     * Generates a unique collection point code (e.g. CP-UYO-8291).
     */
    public static function generateUniqueCode(string $lgaName = 'HUB'): string
    {
        $prefix = 'CP-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $lgaName), 0, 3));
        do {
            $code = $prefix . '-' . random_int(1000, 9999);
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
