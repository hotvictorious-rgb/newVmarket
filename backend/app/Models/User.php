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
        'google_id',
        'phone',
        'user_type',
        'status',
        'avatar',
        'fcm_token',
        'password',
        'is_demo',
    ];

    protected $hidden = [
        'password',
        'is_demo',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_demo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            // Strict Invariant: Super Admin accounts can NEVER be demo accounts under any circumstance.
            if ($user->user_type === 'super_admin') {
                $user->is_demo = false;
            }
        });
    }

    /**
     * Resolve the single canonical Super Admin email configured strictly in the environment.
     */
    public static function getSuperAdminEmail(): string
    {
        return strtolower(trim((string) (
            config('auth.superadmin.email')
            ?: env('SUPERADMIN_EMAIL')
            ?: env('SUPER_ADMIN_EMAIL')
            ?: 'admin@victoriousmarket.com.ng'
        )));
    }

    // Role verification helpers
    public function isSuperAdmin(): bool
    {
        if ($this->user_type !== 'super_admin' || $this->status !== 'active') {
            return false;
        }

        $canonicalEmail = self::getSuperAdminEmail();
        if ($canonicalEmail !== '') {
            $userEmail = strtolower(trim((string) $this->email));
            if ($userEmail === $canonicalEmail) {
                return true;
            }

            // In testing environments, allow test fixtures unless strict single-super-admin is explicitly enabled
            if (app()->environment('testing') && !config('auth.strict_env_super_admin_in_tests', false)) {
                return true;
            }

            return false;
        }

        return true;
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
