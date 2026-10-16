<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'unit_price',
        'quantity',
        'line_total',
        'accepted_quantity',
        'rejected_quantity',
        'rejection_category',
        'rejection_notes',
        'defect_photo_path',
        'item_delivery_status',
    ];

    protected $casts = [
        'unit_price' => 'string',
        'line_total' => 'string',
        'quantity' => 'integer',
        'accepted_quantity' => 'integer',
        'rejected_quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
