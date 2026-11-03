<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PlayerPlusOrder extends Model
{
    public const WALLET_SOURCE = 'player_plus_wallet_payment';

    /** Paid flags alone cannot grant private access: wallet orders need exact payer ledger evidence. */
    public function scopeVerifiedSettlement(Builder $query): Builder
    {
        return $query->where('player_plus_orders.status', 'completed')->where('player_plus_orders.pay_status', true)
            ->whereNotNull('player_plus_orders.activated_at')->where(function ($paid) {
                $paid->where('player_plus_orders.payfast_paid', true);
                if (!\App\Services\Performance\PlayerPlusService::monthlyReady()) return;
                $paid->orWhere(function ($wallet) {
                    $wallet->where('player_plus_orders.plan', 'annual')->where('player_plus_orders.payfast_paid', false)
                        ->where('player_plus_orders.wallet_debited', true)->where('player_plus_orders.price_cents', '>', 0)
                        ->whereRaw('ROUND(player_plus_orders.wallet_reserved * 100) = player_plus_orders.price_cents')
                        ->where('player_plus_orders.payfast_amount_due', 0)->whereNull('player_plus_orders.payfast_handed_off_at')
                        ->whereNull('player_plus_orders.payfast_pf_payment_id')->whereNull('player_plus_orders.recurring_consented_at')
                        ->whereNull('player_plus_orders.subscription_order_id')
                        ->whereRaw('(SELECT COUNT(*) FROM wallet_transactions wt WHERE wt.source_type = ? AND wt.source_id = player_plus_orders.id) = 1', [self::WALLET_SOURCE])
                        ->whereExists(function ($debit) {
                            $debit->selectRaw('1')->from('wallet_transactions as wt')->join('wallets as w', 'w.id', '=', 'wt.wallet_id')
                                ->where('wt.source_type', self::WALLET_SOURCE)->whereColumn('wt.source_id', 'player_plus_orders.id')
                                ->where('wt.type', 'debit')->whereRaw('ROUND(wt.amount * 100) = player_plus_orders.price_cents')
                                ->whereColumn('w.payable_id', 'player_plus_orders.user_id')->where('w.payable_type', (new User)->getMorphClass());
                        });
                });
            });
    }

    public function hasVerifiedSettlement(): bool
    {
        return self::whereKey($this->id)->verifiedSettlement()->exists();
    }

    public function isWalletPurchase(): bool
    {
        return !$this->payfast_paid && $this->wallet_debited && $this->hasVerifiedSettlement();
    }
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
    public function isRecurring(): bool { return in_array($this->plan, ['monthly', 'annual_recurring'], true); }

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
