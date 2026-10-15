<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
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
        'pickup_enabled',
        'pickup_prep_time_minutes',
        'pickup_opening_time',
        'pickup_closing_time',
    ];

    protected $casts = [
        'is_primary_branch' => 'boolean',
        'pickup_enabled' => 'boolean',
        'pickup_prep_time_minutes' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
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
}
