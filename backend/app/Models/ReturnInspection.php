<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnInspection extends Model
{
    protected $fillable = [
        'return_case_id',
        'collection_point_id',
        'inspected_by_user_id',
        'condition_rating',
        'serial_number_or_imei',
        'packaging_intact',
        'inspection_notes',
        'inspection_decision',
        'inspected_at',
    ];

    protected $casts = [
        'packaging_intact' => 'boolean',
        'inspected_at' => 'datetime',
    ];

    public function returnCase()
    {
        return $this->belongsTo(ReturnCase::class);
    }

    public function collectionPoint()
    {
        return $this->belongsTo(LogisticsCollectionPoint::class, 'collection_point_id');
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspected_by_user_id');
    }

    public function getInspectionResultAttribute(): string
    {
        return $this->inspection_decision ?? 'accept_for_refund';
    }

    public function getPhysicalConditionAttribute(): string
    {
        return $this->condition_rating ?? 'matches_claim';
    }
}
