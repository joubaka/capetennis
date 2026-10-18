<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamProviderRefundAttempt extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['gross' => 'decimal:2', 'fee' => 'decimal:2', 'net' => 'decimal:2', 'amount' => 'decimal:2', 'wallet_amount' => 'decimal:2', 'confirmed_at' => 'datetime', 'authorized_withdrawn_at' => 'datetime', 'authorized_deadline' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $attempt): void {
            if ($attempt->isDirty(['order_id', 'requested_by', 'payer_id', 'beneficiary_player_id', 'pf_payment_id', 'fee_mode', 'review_fingerprint', 'gross', 'fee', 'net', 'amount', 'wallet_amount', 'authorized_withdrawn_at', 'authorized_deadline'])) {
                throw new \LogicException('The original team provider refund claim is immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Provider refund claims are retained for audit.'));
    }
}
