<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClothingRefundItem extends Model
{
    protected $fillable = ['clothing_order_item_id', 'quantity', 'amount'];
    protected $casts = ['quantity' => 'integer', 'amount' => 'decimal:2'];

    public function refund() { return $this->belongsTo(ClothingRefund::class, 'clothing_refund_id'); }
    public function item() { return $this->belongsTo(ClothingOrderItem::class, 'clothing_order_item_id'); }
}
