<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisputeAndReturn extends Model
{
    protected $table = 'disputes_and_returns';

    protected $fillable = [
        'order_id',
        'customer_id',
        'reason',
        'customer_description',
        'evidence_photos',
        'status',
        'reviewed_by_admin_id',
        'resolution_notes',
    ];

    protected $casts = [
        'evidence_photos' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function reviewedByAdmin()
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin_id');
    }
}
