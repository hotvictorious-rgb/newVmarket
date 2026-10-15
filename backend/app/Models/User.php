<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'user_type',
        'status',
        'avatar',
        'fcm_token',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Role verification helpers
    public function isSuperAdmin(): bool
    {
        return $this->user_type === 'super_admin';
    }

    public function isAdminEmployee(): bool
    {
        return $this->user_type === 'admin_employee';
    }

    public function isVendor(): bool
    {
        return $this->user_type === 'vendor';
    }

    public function isVendorEmployee(): bool
    {
        return $this->user_type === 'vendor_employee';
    }

    public function isLogisticsCompany(): bool
    {
        return $this->user_type === 'logistics_company';
    }

    public function isRider(): bool
    {
        return $this->user_type === 'rider';
    }

    public function isCustomer(): bool
    {
        return $this->user_type === 'customer';
    }

    // Relationships to actor profiles
    public function adminEmployeeProfile()
    {
        return $this->hasOne(AdminEmployeeProfile::class);
    }

    public function vendorProfile()
    {
        return $this->hasOne(VendorProfile::class);
    }

    public function vendorEmployeeProfile()
    {
        return $this->hasOne(VendorEmployeeProfile::class);
    }

    public function logisticsCompanyProfile()
    {
        return $this->hasOne(LogisticsCompanyProfile::class);
    }

    public function riderProfile()
    {
        return $this->hasOne(RiderProfile::class);
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function cashbackLedgers()
    {
        return $this->hasMany(CustomerCashbackLedger::class, 'customer_id');
    }
}
