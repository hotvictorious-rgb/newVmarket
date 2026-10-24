<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderSupplierAssignment extends Model
{
    protected $fillable = [
        'order_id',
        'supplier_vendor_id',
        'supplier_shop_id',
        'status',
        'assigned_at',
        'supplier_confirmed_at',
        'supplier_rejected_at',
        'supplier_response_notes',
        'confirmed_order_items',
        'confirmation_snapshot_hash',
        'supplier_pickup_code',
        'handover_ready_at',
        'collected_at',
        'collection_proof_image_path',
        'collection_notes',
        'pickup_failed_attempts',
        'pickup_locked_until',
        'admin_override_by',
        'admin_override_reason',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'supplier_confirmed_at' => 'datetime',
        'supplier_rejected_at' => 'datetime',
        'confirmed_order_items' => 'array',
        'handover_ready_at' => 'datetime',
        'collected_at' => 'datetime',
        'pickup_locked_until' => 'datetime',
        'pickup_failed_attempts' => 'integer',
    ];

    public static function generateUniquePickupCode(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
            $exists = self::where('supplier_pickup_code', $code)->exists();
        } while ($exists);

        return $code;
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier()
    {
        return $this->belongsTo(VendorProfile::class, 'supplier_vendor_id')->withTrashed();
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'supplier_shop_id')->withTrashed();
    }

    public function overriddenBy()
    {
        return $this->belongsTo(User::class, 'admin_override_by');
    }
}
