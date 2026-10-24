<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $fillable = [
        'country_id',
        'name',
        'code',
        'state_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = $value;
        $this->attributes['state_code'] = $value;
    }

    public function setStateCodeAttribute($value): void
    {
        $this->attributes['state_code'] = $value;
        $this->attributes['code'] = $value;
    }

    public function getCodeAttribute($value): ?string
    {
        return $value ?? ($this->attributes['state_code'] ?? null);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function lgas()
    {
        return $this->hasMany(Lga::class);
    }
}
