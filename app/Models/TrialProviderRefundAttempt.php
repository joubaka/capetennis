<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrialProviderRefundAttempt extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $attempt) {
            if ($attempt->isDirty(['order_id', 'pf_payment_id', 'amount', 'requested_by'])) {
                throw new \LogicException('The original provider refund claim is immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Provider refund claims are retained for audit.'));
    }
    public function order() { return $this->belongsTo(TeamPaymentOrder::class, 'order_id'); }
    protected $guarded = ['id'];
    protected $casts = ['amount' => 'decimal:2', 'confirmed_at' => 'datetime'];
}
