<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnItem extends Model
{
    protected $fillable = [
        'return_case_id',
        'order_item_id',
        'product_id',
        'quantity',
        'unit_price',
        'line_total',
        'allocated_discount',
        'refundable_amount',
        'item_condition',
        'status',
    ];

    protected $casts = [
        'unit_price' => 'string',
        'line_total' => 'string',
        'allocated_discount' => 'string',
        'refundable_amount' => 'string',
        'quantity' => 'integer',
    ];

    public function returnCase()
    {
        return $this->belongsTo(ReturnCase::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getQuantityReturnedAttribute(): int
    {
        return $this->quantity;
    }

    public function getSubtotalAmountAttribute(): string
    {
        return $this->line_total ?? '0.00';
    }

    public function getConditionClaimedAttribute(): string
    {
        return $this->item_condition ?? 'opened_tested';
    }
}
