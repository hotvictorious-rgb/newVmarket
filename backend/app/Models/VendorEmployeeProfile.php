<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorEmployeeProfile extends Model
{
    protected $fillable = [
        'user_id',
        'vendor_id',
        'shop_id',
        'role',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * Branch Isolation Barrier: Checks if employee is authorized for the given branch.
     */
    public function canAccessBranch(int $shopId): bool
    {
        return (int)$this->shop_id === $shopId;
    }
}
