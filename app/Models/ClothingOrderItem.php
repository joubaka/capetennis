<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClothingOrderItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'clothing_order_id',
        'clothing_order_item_id',
        'clothing_item_size',
        'qty',
        'price',
        'line_total',
        'item_name',
        'size_name',
    ];

    protected $casts = [
        'qty' => 'integer',
        'price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function order(){

        return $this->belongsTo(ClothingOrder::class,'clothing_order_id','id');

    }

    public function refundItems()
    {
        return $this->hasMany(ClothingRefundItem::class, 'clothing_order_item_id');
    }

    public function getRefundedQuantityAttribute(): int
    {
        return (int) $this->refundItems->filter(fn ($line) => in_array($line->refund?->refund_status, ['pending', 'completed'], true))->sum('quantity');
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, (int) $this->qty - $this->refunded_quantity);
    }

    public function itemType(){

        return $this->belongsTo(ClothingItemType::class,'clothing_order_item_id','id');

    }

    public function size(){

        return $this->belongsTo(ClothingSize::class,'clothing_item_size','id');

    }


}
