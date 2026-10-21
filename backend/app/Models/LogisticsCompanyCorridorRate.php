<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsCompanyCorridorRate extends Model
{
    protected $fillable = [
        'logistics_company_id',
        'delivery_corridor_id',
        'base_payable',
        'bulky_surcharge_per_unit',
        'tariff_version',
        'is_active',
    ];

    protected $casts = [
        'base_payable' => 'decimal:2',
        'bulky_surcharge_per_unit' => 'decimal:2',
        'tariff_version' => 'integer',
        'is_active' => 'boolean',
    ];

    public function logisticsCompany()
    {
        return $this->belongsTo(LogisticsCompanyProfile::class, 'logistics_company_id');
    }

    public function corridor()
    {
        return $this->belongsTo(DeliveryCorridor::class, 'delivery_corridor_id');
    }
}
