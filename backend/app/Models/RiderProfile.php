<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderProfile extends Model
{
    protected $fillable = [
        'user_id',
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
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function logisticsCompany()
    {
        return $this->belongsTo(LogisticsCompanyProfile::class, 'logistics_company_id');
    }

    public function assignedLga()
    {
        return $this->belongsTo(Lga::class, 'assigned_lga_id');
    }

    public function assignedOrders()
    {
        return $this->hasMany(Order::class, 'rider_id');
    }

    public function isFleetRider(): bool
    {
        return !empty($this->logistics_company_id);
    }
}
