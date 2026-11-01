<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerPlusOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'pay_status' => 'boolean', 'payfast_paid' => 'boolean', 'wallet_debited' => 'boolean',
        'price_cents' => 'integer', 'expires_at' => 'immutable_datetime',
        'payfast_handed_off_at' => 'immutable_datetime', 'activated_at' => 'immutable_datetime',
        'term_starts_at' => 'immutable_datetime', 'term_ends_at' => 'immutable_datetime',
        'recurring_consented_at' => 'immutable_datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function player() { return $this->belongsTo(Player::class); }
    public function subscription() { return $this->hasOne(PlayerPlusSubscription::class, 'order_id'); }
    public function subscriptionOrder() { return $this->belongsTo(self::class, 'subscription_order_id'); }
    public function isMonthly(): bool { return $this->plan === 'monthly'; }

    public function mailLogs()
    {
        return $this->morphMany(BulkEmailLog::class, 'related');
    }

    public function currentMembership()
    {
        return $this->hasOne(PlayerPlusMembership::class, 'order_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $order): void {
            if ($order->isDirty(['price_cents', 'plan', 'subscription_order_id', 'recurring_consented_at'])) throw new \LogicException('Player Plus checkout price, plan and consent are immutable.');
        });
    }

    public function resolveRouteBinding($value, $field = null)
    {
        abort_unless(\App\Services\Performance\PlayerPlusService::ready(), 404);
        return parent::resolveRouteBinding($value, $field);
    }
}
