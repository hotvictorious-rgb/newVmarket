<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lga extends Model
{
    protected $fillable = [
        'state_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function shops()
    {
        return $this->hasMany(Shop::class);
    }

    public function outboundLanes()
    {
        return $this->hasMany(DeliveryLane::class, 'origin_lga_id');
    }

    public function inboundLanes()
    {
        return $this->hasMany(DeliveryLane::class, 'destination_lga_id');
    }
}
