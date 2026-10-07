<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClothingRefund extends Model
{
    protected $fillable = ['clothing_order_id', 'event_id', 'payer_id', 'requested_by', 'completed_by', 'request_token', 'request_fingerprint', 'refund_method', 'refund_status', 'refund_gross', 'refund_fee', 'refund_net', 'reason', 'external_reference', 'refunded_at'];

    protected $casts = ['refund_gross' => 'decimal:2', 'refund_fee' => 'decimal:2', 'refund_net' => 'decimal:2', 'refunded_at' => 'datetime'];

    public function order() { return $this->belongsTo(ClothingOrder::class, 'clothing_order_id'); }
    public function payer() { return $this->belongsTo(User::class, 'payer_id'); }
    public function items() { return $this->hasMany(ClothingRefundItem::class); }
}
