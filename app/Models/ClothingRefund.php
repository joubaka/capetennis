<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClothingRefund extends Model
{
    protected $fillable = ['clothing_order_id', 'event_id', 'payer_id', 'requested_by', 'completed_by', 'requested_by_name', 'completed_by_name', 'request_token', 'request_fingerprint', 'refund_method', 'refund_status', 'refund_gross', 'refund_fee', 'refund_net', 'reason', 'external_reference', 'refunded_at', 'clothing_deadline_at', 'late_refund_note', 'reconciliation_status', 'reconciliation_note', 'reconciled_by', 'reconciled_at'];

    protected $casts = ['refund_gross' => 'decimal:2', 'refund_fee' => 'decimal:2', 'refund_net' => 'decimal:2', 'refunded_at' => 'datetime', 'clothing_deadline_at' => 'immutable_datetime', 'reconciled_at' => 'datetime'];

    public function order() { return $this->belongsTo(ClothingOrder::class, 'clothing_order_id'); }
    public function payer() { return $this->belongsTo(User::class, 'payer_id'); }
    public function requestedBy() { return $this->belongsTo(User::class, 'requested_by'); }
    public function completedBy() { return $this->belongsTo(User::class, 'completed_by'); }
    public function historicalImport() { return $this->hasOne(ClothingRefundHistoricalImport::class); }
    public function items() { return $this->hasMany(ClothingRefundItem::class); }
}
