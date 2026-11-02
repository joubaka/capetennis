<?php

namespace App\Services\Performance;

use App\Domain\Payments\Services\{PaymentOrchestrator, PaymentTransactionService};
use App\Models\{Player, PlayerPlusMembership, PlayerPlusOrder, PlayerPlusSubscription, SiteSetting, User};
use App\Services\Payfast;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

/** Sponsorship is payment ownership only. This service never creates user_players links. */
class PlayerPlusService
{
    public const PRICE_CENTS = 69900;
    // PayFast cannot process less than ZAR 5.00: https://developers.payfast.co.za/
    public const MIN_PRICE_CENTS = 500;
    public const MAX_PRICE_CENTS = 9999999; // Application cap, not a claimed provider limit.
    public const PRICE_SETTING = 'player_plus_annual_price_cents';
    public const MONTHLY_PRICE_SETTING = 'player_plus_monthly_price_cents';
    public const MONTHLY_ENABLED_SETTING = 'player_plus_monthly_enabled';

    public static function monthlyPriceCents(): int
    {
        $value = (string) SiteSetting::get(self::MONTHLY_PRICE_SETTING, '6900');
        return ctype_digit($value) && (int) $value >= self::MIN_PRICE_CENTS && (int) $value <= self::MAX_PRICE_CENTS ? (int) $value : 6900;
    }

    public static function monthlyReady(): bool
    {
        return self::ready() && Schema::hasTable('player_plus_subscriptions') && Schema::hasTable('player_plus_subscription_charges');
    }

    public static function monthlyEnabled(): bool
    {
        return self::monthlyReady() && SiteSetting::get(self::MONTHLY_ENABLED_SETTING, '0') === '1';
    }

    public static function monthlyProviderReady(): bool
    {
        $sandbox = (bool) config('services.payfast.sandbox');
        return filled(config('services.payfast.'.($sandbox ? 'sandbox_id' : 'merchant_id')))
            && filled(config('services.payfast.'.($sandbox ? 'sandbox_key' : 'merchant_key')))
            && filled(config('services.payfast.'.($sandbox ? 'passphrase_sandbox' : 'passphrase_live')) ?: config('services.payfast.passphrase'));
    }

    private function hasUncancelledSubscription(int $playerId, ?int $exceptOrderId = null): bool
    {
        return self::monthlyReady() && PlayerPlusSubscription::whereHas('order', fn ($q) => $q->where('player_id', $playerId))
            ->when($exceptOrderId, fn ($q) => $q->where('order_id', '!=', $exceptOrderId))->whereNull('cancelled_at')->exists();
    }

    public static function priceCents(): int
    {
        $value = (string) SiteSetting::get(self::PRICE_SETTING, (string) self::PRICE_CENTS);
        return ctype_digit($value) && (int) $value >= self::MIN_PRICE_CENTS && (int) $value <= self::MAX_PRICE_CENTS ? (int) $value : self::PRICE_CENTS;
    }

    public static function amount(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function parseAmount(string $amount): ?int
    {
        if (! preg_match('/^(0|[1-9][0-9]{0,4})(?:\.([0-9]{1,2}))?$/D', $amount, $match)) return null;
        return ((int) $match[1] * 100) + (int) str_pad($match[2] ?? '', 2, '0');
    }

    public static function ready(): bool
    {
        return Schema::hasTable('player_plus_orders') && Schema::hasTable('player_plus_memberships');
    }

    public static function salesEnabled(): bool
    {
        return self::ready() && SiteSetting::get(PlayerRatingAccess::SETTING, '0') === '1';
    }

    public function __construct(private PaymentOrchestrator $payments, private PaymentTransactionService $receipts) {}

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['membership' => $message]);
    }

    public function start(Player $player, User $payer, string $token, ?int $quotedPrice = null, string $plan = 'annual', bool $recurringConsent = false): PlayerPlusOrder
    {
        abort_unless(self::salesEnabled(), 404);
        if (! in_array($plan, ['annual', 'monthly', 'annual_recurring'], true)) $this->reject('Choose a supported membership plan.');
        if ($plan === 'monthly' && (! self::monthlyEnabled() || ! $recurringConsent)) $this->reject('Monthly purchases need recurring-payment consent and must be available.');
        if ($plan === 'annual_recurring' && (! self::monthlyReady() || ! $recurringConsent)) $this->reject('Annual subscriptions need recurring-payment consent and must be available.');
        return DB::transaction(function () use ($player, $payer, $token, $quotedPrice, $plan, $recurringConsent) {
            Player::whereKey($player->id)->lockForUpdate()->firstOrFail();
            $this->checkAdminAccess($player);
            $existing = PlayerPlusOrder::where('request_token', $token)->lockForUpdate()->first();
            if ($existing) {
                abort_unless((int) $existing->user_id === (int) $payer->id && (int) $existing->player_id === (int) $player->id, 409);
                if (($existing->plan ?? 'annual') !== $plan) $this->reject('This request belongs to a different membership plan.');
                return $existing;
            }
            if (self::monthlyReady() && PlayerPlusSubscription::whereHas('order', fn ($q) => $q->where('player_id', $player->id))
                ->whereNull('cancelled_at')->exists()) {
                $this->reject('This player has an automatic-renewal subscription. Its payer must stop automatic renewal before starting another purchase.');
            }
            if (PlayerPlusMembership::where('player_id', $player->id)->where('ends_at', '>', now())->exists()) {
                $this->reject('This player already has Player Plus. Renew manually after the current membership expires.');
            }
            $pending = PlayerPlusOrder::where('player_id', $player->id)->where('status', 'pending')->lockForUpdate()->get();
            foreach ($pending as $attempt) {
                if ($attempt->expires_at->isFuture()) {
                    if ((int) $attempt->user_id === (int) $payer->id && ($attempt->plan ?? 'annual') === $plan) { return $attempt; }
                    $this->reject('A checkout for this player is already in progress. Please try again later.');
                }
                $this->close($attempt, 'expired');
            }
            $price = $plan === 'monthly' ? self::monthlyPriceCents() : self::priceCents();
            if ($quotedPrice !== null && $quotedPrice !== $price) {
                $this->reject('The membership price has changed. Please review the player membership page before continuing.');
            }
            $order = PlayerPlusOrder::create(['player_id' => $player->id, 'user_id' => $payer->id,
                'request_token' => $token, 'price_cents' => $price, 'expires_at' => now()->addMinutes(5)]
                + (self::monthlyReady() ? ['plan' => $plan, 'recurring_consented_at' => $recurringConsent ? now() : null] : []));
            return $this->payments->initiatePayment($order, 0, $price / 100);
        }, 3);
    }

    public function handoff(PlayerPlusOrder $order, User $payer): PlayerPlusOrder
    {
        abort_unless(self::salesEnabled(), 404);
        return DB::transaction(function () use ($order, $payer) {
            Player::whereKey($order->player_id)->lockForUpdate()->firstOrFail();
            $order = PlayerPlusOrder::lockForUpdate()->findOrFail($order->id);
            abort_unless((int) $order->user_id === (int) $payer->id, 403);
            $this->checkAdminAccess($order->player);
            if ($order->isRecurring() && (! self::monthlyReady() || ! $order->recurring_consented_at)) $this->reject('Subscription purchases are unavailable.');
            if ($order->isMonthly() && ! self::monthlyEnabled()) $this->reject('Monthly purchases are unavailable.');
            if ($order->isRecurring() && ! self::monthlyProviderReady()) $this->reject('Recurring payments are not configured yet. Please contact Cape Tennis support.');
            if ($this->hasUncancelledSubscription((int) $order->player_id, (int) $order->id)) {
                $this->reject('This player has another subscription awaiting cancellation. Its payer must stop automatic renewal first.');
            }
            if ($order->status !== 'pending' || !$order->expires_at->isFuture() || $order->payfast_handed_off_at) {
                $this->reject('This checkout is no longer available for payment. Check its status before starting again.');
            }
            if ($order->price_cents < self::MIN_PRICE_CENTS) {
                $this->reject('This checkout is below PayFast\'s R'.self::amount(self::MIN_PRICE_CENTS).' minimum. Close it and start a new checkout at the current annual price.');
            }
            if (PlayerPlusMembership::where('player_id', $order->player_id)->where('ends_at', '>', now())->exists()) {
                $this->reject('This player already has an active Player Plus membership.');
            }
            $order->update(['payfast_handed_off_at' => now(), 'expires_at' => now()->addMinutes(5)]);
            return $order;
        }, 3);
    }

    public function form(PlayerPlusOrder $order): string
    {
        $payfast = new Payfast;
        $payfast->setMode(config('services.payfast.sandbox') ? 0 : 1);
        $payfast->setPayer($order->user);
        $payfast->custom_int2 = $order->player_id;
        $payfast->custom_int4 = $order->user_id;
        $payfast->custom_int5 = $order->id;
        $payfast->custom_str5 = 'PlayerPlus';
        $payfast->setAmount($order->price_cents / 100);
        $payfast->setItem($order->isRecurring() ? 'Player Plus - '.($order->isMonthly() ? 'monthly' : 'annual').' subscription' : 'Player Plus - 12 months');
        if ($order->isRecurring()) {
            $date = $order->payfast_handed_off_at->timezone('Africa/Johannesburg');
            $payfast->setSubscription(($order->isMonthly() ? $date->addMonthNoOverflow() : $date->addYearNoOverflow())->toDateString(), $order->isMonthly() ? 3 : 6);
        }
        $payfast->setReturnUrl(route('player-plus.orders.show', $order));
        $payfast->setCancelUrl(route('player-plus.orders.show', $order));
        return $payfast->getForm();
    }

    public function cancel(PlayerPlusOrder $order, User $payer): PlayerPlusOrder
    {
        return DB::transaction(function () use ($order, $payer) {
            Player::whereKey($order->player_id)->lockForUpdate()->firstOrFail();
            $order = PlayerPlusOrder::lockForUpdate()->findOrFail($order->id);
            abort_unless((int) $order->user_id === (int) $payer->id, 403);
            if ($order->status !== 'pending' || $order->pay_status) { return $order; }
            if ($order->payfast_handed_off_at && $order->expires_at->isFuture()) {
                $this->reject('Please wait for the five-minute payment window to finish before closing this checkout.');
            }
            return $this->close($order, !$order->expires_at->isFuture() ? 'expired' : 'cancelled');
        }, 3);
    }

    private function close(PlayerPlusOrder $order, string $status): PlayerPlusOrder
    {
        $order = $this->payments->cancelPayment($order);
        $order->update(['status' => $status]);
        return $order;
    }

    public function closeUnpaidAsAdmin(PlayerPlusOrder $order, User $actor, string $reason): void
    {
        abort_unless($actor->hasRole('super-user'), 403);
        if (mb_strlen(trim($reason)) < 10) $this->reject('Record a support reason of at least 10 characters.');
        DB::transaction(function () use ($order, $actor, $reason) {
            Player::whereKey($order->player_id)->lockForUpdate()->firstOrFail();
            $order = PlayerPlusOrder::lockForUpdate()->findOrFail($order->id);
            if (in_array($order->status, ['cancelled', 'expired'], true) && ! $order->pay_status && ! $order->payfast_paid && ! $order->wallet_debited
                && ! $order->activated_at && ! $order->term_starts_at && ! $order->term_ends_at && ! filled($order->payfast_pf_payment_id)
                && ! filled($order->reconciliation_reason) && ! app(PlayerPlusReconciliation::class)->receipts($order)->exists()) return;
            if ($order->status !== 'pending' || $order->pay_status || $order->payfast_paid || $order->activated_at
                || $order->wallet_debited || $order->term_starts_at || $order->term_ends_at
                || filled($order->reconciliation_reason) || filled($order->payfast_pf_payment_id)
                || app(PlayerPlusReconciliation::class)->receipts($order)->exists()
                || ($order->payfast_handed_off_at && $order->expires_at->isFuture())) {
                $this->reject('This checkout has payment evidence or an active payment window and cannot be closed here. Review its payment status.');
            }
            $before = ['status' => $order->status];
            $this->close($order, $order->expires_at->isFuture() ? 'cancelled' : 'expired');
            $audit = app(\App\Support\Audit\AuditWriter::class)->record(['category' => 'business', 'action' => 'player-plus.admin-checkout-closed',
                'subject' => $order, 'actor' => $actor, 'reason' => trim($reason), 'before' => $before, 'after' => ['status' => $order->status]], true);
            if (! $audit) throw new \RuntimeException('Checkout closure could not be audited.');
        }, 3);
    }

    private function checkAdminAccess(Player $player): void
    {
        if (! PlayerPlusManagement::ready()) return;
        $override = $player->plusOverride()->first();
        if ($override?->mode === 'suspended') $this->reject('Player Plus access is deactivated. Contact Cape Tennis support before purchasing.');
        if ($override?->mode === 'grant' && $override->starts_at?->lte(now()) && $override->ends_at?->isFuture()) {
            $this->reject('This player already has current admin-granted access. Purchase after that access expires.');
        }
    }

    /** Invoked only by the shared signature/merchant verifier, never by a browser return. */
    public function receive(array $data): void
    {
        if (($data['payment_status'] ?? null) !== 'COMPLETE') { return; }
        DB::transaction(function () use ($data) {
            $reference = PlayerPlusOrder::findOrFail((int) ($data['custom_int5'] ?? 0));
            Player::whereKey($reference->player_id)->lockForUpdate()->firstOrFail();
            $order = PlayerPlusOrder::lockForUpdate()->findOrFail($reference->id);
            foreach (['custom_str5' => 'PlayerPlus', 'custom_int2' => $order->player_id,
                'custom_int4' => $order->user_id, 'custom_int5' => $order->id, 'custom_int1' => '', 'custom_int3' => ''] as $key => $expected) {
                if ((string) ($data[$key] ?? '') !== (string) $expected) { $this->reject('Payment identity mismatch.'); }
            }
            $providerId = (string) ($data['pf_payment_id'] ?? '');
            if ($providerId === '' || strlen($providerId) > 120 || ! $order->payfast_handed_off_at
                || self::parseAmount((string) ($data['amount_gross'] ?? '')) !== $order->price_cents) {
                $this->reject('Payment amount or handoff mismatch.');
            }
            if ($order->isRecurring()) {
                $this->receiveMonthly($order, $data, $providerId);
                return;
            }
            // Receipt replay protection is shared across all payment types, not just Plus.
            $this->receipts->record(array_merge($data, ['cape_tennis_fee' => 0]), $order);
            if ($order->payfast_paid) {
                if ($order->payfast_pf_payment_id !== $providerId) {
                    $this->payments->recordVerifiedPaymentForReconciliation($order, $providerId,
                        'Additional payment '.$providerId.' received for an already settled checkout. Original membership unchanged.');
                    app(PlayerPlusNotifications::class)->record($order->fresh(), 'reconciliation');
                }
                return;
            }
            $reason = null;
            if ($order->status !== 'pending' || !$order->expires_at->isFuture()) {
                $reason = 'Payment arrived after checkout cancellation or expiry.';
            } elseif (PlayerPlusMembership::where('player_id', $order->player_id)->where('ends_at', '>', now())->exists()) {
                $reason = 'The player already has active membership.';
            } elseif ($this->hasUncancelledSubscription((int) $order->player_id)) {
                $reason = 'The player has an outstanding subscription mandate; review before activating this annual purchase.';
            }
            if ($reason) {
                $this->payments->recordVerifiedPaymentForReconciliation($order, $providerId, $reason);
                app(PlayerPlusNotifications::class)->record($order->fresh(), 'reconciliation');
                return;
            }
            $order = $this->payments->finalizePayment($order, ['payment_method' => 'payfast',
                'pf_payment_id' => $providerId, 'payfast_amount_received' => $data['amount_gross']]);
            $starts = now()->toImmutable();
            $ends = $starts->addYearNoOverflow();
            PlayerPlusMembership::updateOrCreate(['player_id' => $order->player_id], [
                'order_id' => $order->id, 'starts_at' => $starts, 'ends_at' => $ends,
            ]);
            $order->update(['activated_at' => $starts, 'term_starts_at' => $starts, 'term_ends_at' => $ends]);
            app(PlayerPlusNotifications::class)->record($order, 'activation');
        }, 3);
    }

    /** Called inside the root order/player lock, only after the shared verified ITN gates. */
    private function receiveMonthly(PlayerPlusOrder $root, array $data, string $providerId): void
    {
        if (! self::monthlyReady() || $root->subscription_order_id || ! $root->recurring_consented_at) $this->reject('Subscription payment consent does not match.');
        $token = (string) ($data['token'] ?? '');
        if (! preg_match('/^[A-Za-z0-9_-]{1,120}$/D', $token)) $this->reject('The verified subscription token is missing or invalid.');
        $tokenHash = hash('sha256', $token);
        $subscription = PlayerPlusSubscription::where('order_id', $root->id)->lockForUpdate()->first();
        if ($subscription && ! hash_equals($subscription->provider_token_hash, $tokenHash)) $this->reject('Subscription identity mismatch.');
        if (! $subscription) {
            if ($root->payfast_paid || PlayerPlusSubscription::where('provider_token_hash', $tokenHash)->exists()) $this->reject('Subscription token is already assigned.');
            $date = $root->payfast_handed_off_at->timezone('Africa/Johannesburg')->startOfDay();
            $anchor = $root->isMonthly() ? $date->addMonthNoOverflow() : $date->addYearNoOverflow();
            $subscription = PlayerPlusSubscription::create(['order_id' => $root->id, 'provider_token' => $token,
                'provider_token_hash' => $tokenHash, 'billing_anchor_at' => $anchor, 'next_billing_at' => $anchor]);
        }
        $charge = DB::table('player_plus_subscription_charges')->where('provider_payment_id', $providerId)->first();
        if ($charge && (int) $charge->subscription_id !== (int) $subscription->id) $this->reject('Provider receipt belongs to another subscription.');
        // Keep the original, verified custom fields. In particular custom_int5 remains the mandate order,
        // so shared receipt replay checks and payer statements retain the provider's real provenance.
        $this->receipts->record(array_merge($data, ['cape_tennis_fee' => 0]), $root);
        if ($charge) return;

        if (! $root->payfast_paid) {
            $reason = $root->status !== 'pending' || ! $root->expires_at->isFuture()
                ? 'Initial subscription payment arrived after checkout cancellation or expiry.' : null;
            if (PlayerPlusMembership::where('player_id', $root->player_id)->where('ends_at', '>', now())->exists()) {
                $reason ??= 'The player already has active membership.';
            }
            if ($this->hasUncancelledSubscription((int) $root->player_id, (int) $root->id)) {
                $reason ??= 'The player has another outstanding subscription mandate.';
            }
            if ($reason) {
                $this->payments->recordVerifiedPaymentForReconciliation($root, $providerId, $reason);
                $subscription->update(['status' => 'cancel_pending', 'cancellation_requested_at' => now()]);
                $this->queueRequestedCancellation($subscription);
                $this->recordMonthlyCharge($subscription, $root, $providerId, null);
                app(PlayerPlusNotifications::class)->record($root->fresh(), 'reconciliation');
                return;
            }
            $root = $this->payments->finalizePayment($root, ['payment_method' => 'payfast',
                'pf_payment_id' => $providerId, 'payfast_amount_received' => $data['amount_gross']]);
            $this->activateMonthlyTerm($root, now()->toImmutable(), $subscription->next_billing_at);
            $this->recordMonthlyCharge($subscription, $root, $providerId, 1);
            return;
        }

        $cycle = $subscription->paid_cycles + 1;
        $starts = $subscription->next_billing_at;
        $ends = $root->isMonthly() ? $subscription->billing_anchor_at->addMonthsNoOverflow($cycle - 1)
            : $subscription->billing_anchor_at->addYearsNoOverflow($cycle - 1);
        $membership = PlayerPlusMembership::where('player_id', $root->player_id)->first();
        $currentOrder = $membership ? PlayerPlusOrder::find($membership->order_id) : null;
        $reason = null;
        if ($subscription->status !== 'active' || $subscription->cancellation_requested_at) {
            $reason = 'Payment received after automatic-renewal cancellation was requested.';
        } elseif (now()->lt($starts) || now()->gte($ends)) {
            $reason = 'Payment is outside the next expected subscription billing period; possible additional or delayed charge.';
            if (now()->gte($ends)) {
                $subscription->update(['status' => 'cancel_pending', 'cancellation_requested_at' => now()]);
                $this->queueRequestedCancellation($subscription);
            }
        } elseif (! $currentOrder || ((int) $currentOrder->id !== (int) $root->id && (int) $currentOrder->subscription_order_id !== (int) $root->id)) {
            $reason = 'The current membership no longer belongs to this subscription.';
        }
        $renewal = PlayerPlusOrder::create(['player_id' => $root->player_id, 'user_id' => $root->user_id,
            'request_token' => (string) \Illuminate\Support\Str::uuid(), 'price_cents' => $root->price_cents,
            'plan' => $root->plan, 'subscription_order_id' => $root->id, 'recurring_consented_at' => $root->recurring_consented_at,
            'payfast_handed_off_at' => $root->payfast_handed_off_at, 'expires_at' => now()]);
        $renewal = $this->payments->initiatePayment($renewal, 0, $root->price_cents / 100);
        if ($reason) {
            $this->payments->recordVerifiedPaymentForReconciliation($renewal, $providerId, $reason);
            $this->recordMonthlyCharge($subscription, $renewal, $providerId, null);
            app(PlayerPlusNotifications::class)->record($renewal->fresh(), 'reconciliation');
            return;
        }
        $renewal = $this->payments->finalizePayment($renewal, ['payment_method' => 'payfast',
            'pf_payment_id' => $providerId, 'payfast_amount_received' => $data['amount_gross']]);
        $this->activateMonthlyTerm($renewal, $starts, $ends);
        $this->recordMonthlyCharge($subscription, $renewal, $providerId, $cycle);
        $subscription->update(['paid_cycles' => $cycle, 'next_billing_at' => $ends]);
    }

    private function activateMonthlyTerm(PlayerPlusOrder $order, $starts, $ends): void
    {
        PlayerPlusMembership::updateOrCreate(['player_id' => $order->player_id], [
            'order_id' => $order->id, 'starts_at' => $starts, 'ends_at' => $ends,
        ]);
        $order->update(['activated_at' => now(), 'term_starts_at' => $starts, 'term_ends_at' => $ends]);
        app(PlayerPlusNotifications::class)->record($order, 'activation');
    }

    private function recordMonthlyCharge(PlayerPlusSubscription $subscription, PlayerPlusOrder $order, string $providerId, ?int $cycle): void
    {
        DB::table('player_plus_subscription_charges')->insert(['subscription_id' => $subscription->id, 'order_id' => $order->id,
            'provider_payment_id' => $providerId, 'cycle_number' => $cycle, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function cancelSubscription(PlayerPlusOrder $order, User $payer, ?string $adminReason = null): void
    {
        if ($adminReason !== null) {
            abort_unless($payer->hasRole('super-user'), 403);
            if (mb_strlen(trim($adminReason)) < 10 || mb_strlen($adminReason) > 1500) $this->reject('Record a meaningful support reason from 10 to 1500 characters.');
        }
        $rootId = $order->subscription_order_id ?: $order->id;
        $subscription = DB::transaction(function () use ($rootId, $payer, $adminReason, $order) {
            $root = PlayerPlusOrder::findOrFail($rootId);
            abort_unless((int) $root->player_id === (int) $order->player_id && (int) $root->user_id === (int) $order->user_id, 404);
            abort_unless((int) $root->user_id === (int) $payer->id || ($payer->hasRole('super-user') && mb_strlen(trim($adminReason ?? '')) >= 10), 403);
            Player::whereKey($root->player_id)->lockForUpdate()->firstOrFail();
            $root = PlayerPlusOrder::lockForUpdate()->findOrFail($rootId);
            $subscription = PlayerPlusSubscription::where('order_id', $root->id)->lockForUpdate()->firstOrFail();
            if ($subscription->cancelled_at) return $subscription;
            if ($adminReason !== null) $this->auditSubscription($root, $payer, 'admin-cancellation-attempt', trim($adminReason));
            if (! $subscription->cancellation_requested_at) {
                $subscription->update(['status' => 'cancel_pending', 'cancellation_requested_at' => now()]);
                $this->auditSubscription($root, $payer, 'cancellation-requested', $adminReason);
            }
            return $subscription;
        }, 3);
        if ($subscription->cancelled_at) return;
        // Intent is committed first: a simultaneous ITN is recorded for reconciliation, never extended.
        if (! app(PlayerPlusSubscriptionGateway::class)->cancel($subscription->provider_token)) {
            $this->reject('Cancellation is requested but PayFast has not confirmed it. Automatic charges may continue. Retry or contact support; paid access is unchanged.');
        }
        DB::transaction(function () use ($subscription, $payer, $adminReason) {
            $locked = PlayerPlusSubscription::lockForUpdate()->findOrFail($subscription->id);
            if ($locked->cancelled_at) return;
            $locked->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $this->auditSubscription($locked->order, $payer, 'cancelled', $adminReason);
        }, 3);
    }

    private function auditSubscription(PlayerPlusOrder $root, ?User $payer, string $action, ?string $reason = null): void
    {
        $audit = app(\App\Support\Audit\AuditWriter::class)->record(['category' => 'business',
            'action' => 'player-plus.subscription-'.$action,
            'actor' => $payer ?? (object) ['name' => null, 'userName' => null, 'email' => null],
            'actor_type' => $payer ? 'user' : 'system', 'source' => $payer ? 'web' : 'system', 'subject' => $root,
            'reason' => $reason, 'metadata' => ['plan' => $root->plan]], true);
        if (! $audit) throw new \RuntimeException('The subscription change could not be recorded. Please try again.');
    }

    private function queueRequestedCancellation(PlayerPlusSubscription $subscription): void
    {
        $this->auditSubscription($subscription->order, null, 'automatic-cancellation-requested');
        \App\Jobs\StopPlayerPlusSubscription::dispatch($subscription->id)->afterCommit();
    }

    public function stopRequestedSubscription(int $subscriptionId): void
    {
        $subscription = PlayerPlusSubscription::findOrFail($subscriptionId);
        if ($subscription->cancelled_at || $subscription->status !== 'cancel_pending' || ! $subscription->cancellation_requested_at) return;
        if (! app(PlayerPlusSubscriptionGateway::class)->cancel($subscription->provider_token)) {
            throw new \RuntimeException('PayFast has not confirmed requested subscription cancellation. Operator reconciliation is required.');
        }
        DB::transaction(function () use ($subscription) {
            $locked = PlayerPlusSubscription::lockForUpdate()->findOrFail($subscription->id);
            if ($locked->cancelled_at) return;
            $locked->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $this->auditSubscription($locked->order, null, 'automatically-cancelled');
        }, 3);
    }
}
