<?php

namespace App\Services\Performance;

use App\Domain\Payments\Services\{PaymentOrchestrator, PaymentTransactionService};
use App\Models\{Player, PlayerPlusMembership, PlayerPlusOrder, SiteSetting, User};
use App\Services\Payfast;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

/** Sponsorship is payment ownership only. This service never creates user_players links. */
class PlayerPlusService
{
    public const PRICE_CENTS = 49900;
    public const PRICE_SETTING = 'player_plus_annual_price_cents';

    public static function priceCents(): int
    {
        $value = (string) SiteSetting::get(self::PRICE_SETTING, (string) self::PRICE_CENTS);
        return ctype_digit($value) && (int) $value >= 100 && (int) $value <= 9999999 ? (int) $value : self::PRICE_CENTS;
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

    public function start(Player $player, User $payer, string $token, ?int $quotedPrice = null): PlayerPlusOrder
    {
        abort_unless(self::salesEnabled(), 404);
        return DB::transaction(function () use ($player, $payer, $token, $quotedPrice) {
            Player::whereKey($player->id)->lockForUpdate()->firstOrFail();
            $existing = PlayerPlusOrder::where('request_token', $token)->lockForUpdate()->first();
            if ($existing) {
                abort_unless((int) $existing->user_id === (int) $payer->id && (int) $existing->player_id === (int) $player->id, 409);
                return $existing;
            }
            if (PlayerPlusMembership::where('player_id', $player->id)->where('ends_at', '>', now())->exists()) {
                $this->reject('This player already has Player Plus. Renew manually after the current membership expires.');
            }
            $pending = PlayerPlusOrder::where('player_id', $player->id)->where('status', 'pending')->lockForUpdate()->get();
            foreach ($pending as $attempt) {
                if ($attempt->expires_at->isFuture()) {
                    if ((int) $attempt->user_id === (int) $payer->id) { return $attempt; }
                    $this->reject('A checkout for this player is already in progress. Please try again later.');
                }
                $this->close($attempt, 'expired');
            }
            $price = self::priceCents();
            if ($quotedPrice !== null && $quotedPrice !== $price) {
                $this->reject('The yearly price has changed. Please review the player membership page before continuing.');
            }
            $order = PlayerPlusOrder::create(['player_id' => $player->id, 'user_id' => $payer->id,
                'request_token' => $token, 'price_cents' => $price, 'expires_at' => now()->addMinutes(5)]);
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
            if ($order->status !== 'pending' || !$order->expires_at->isFuture() || $order->payfast_handed_off_at) {
                $this->reject('This checkout is no longer available for payment. Check its status before starting again.');
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
        $payfast->setItem('Player Plus - 12 months');
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
}
