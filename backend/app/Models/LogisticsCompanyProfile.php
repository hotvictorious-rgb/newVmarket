<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsCompanyProfile extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'registration_number',
        'contact_email',
        'contact_phone',
        'hq_address',
        'lga_id',
        'fleet_size',
        'commission_rate',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function riders()
    {
        return $this->hasMany(RiderProfile::class, 'logistics_company_id');
    }

    public function hqLga()
    {
        return $this->belongsTo(Lga::class, 'lga_id');
    }
}
