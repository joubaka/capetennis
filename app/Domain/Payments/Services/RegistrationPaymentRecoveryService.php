<?php

namespace App\Domain\Payments\Services;

use App\Models\CategoryEventRegistration;
use App\Models\RegistrationOrder;
use App\Models\RegistrationPaymentRecovery;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RegistrationPaymentRecoveryService
{
    public const INCIDENTS = [
        257 => ['from' => '2026-09-25 00:00:00', 'until' => '2026-09-28 00:00:00', 'fee' => 300.00],
    ];
    public const MAIL_TEMPLATE_VERSION = 'registration-payment-recovery-v1';
    public const MINIMUM_MAIL_LINK_LIFETIME_MINUTES = 30;

    public function inspect(RegistrationOrder $order): array
    {
        $order->loadMissing(['items.category_event.event', 'items.category_event.category', 'items.player', 'user']);
        $reasons = [];

        if (! $order->user || ! filter_var($order->user->email, FILTER_VALIDATE_EMAIL)) $reasons[] = 'missing recipient';
        if ($order->items->isEmpty()) $reasons[] = 'no order items';
        if ((int) $order->pay_status !== 1 || ($order->status ?? null) !== 'completed' || ($order->payment_method ?? null) !== 'free') $reasons[] = 'not an erroneous completed free order';
        if (round((float) $order->total_fee, 2) !== 0.0 || round((float) $order->items->sum('item_price'), 2) !== 0.0) $reasons[] = 'order is not zero value';
        if ($order->payfast_paid || $order->wallet_debited || (float) $order->wallet_reserved > 0 || $order->payfast_pf_payment_id || $order->wallet_transaction_id) $reasons[] = 'order contains payment evidence';
        if (Transaction::query()->where('custom_int5', $order->id)->exists()) $reasons[] = 'PayFast transaction exists';
        if (WalletTransaction::query()->where('source_id', $order->id)->where('source_type', 'like', '%registration%')->exists()) $reasons[] = 'wallet transaction exists';

        $amount = 0.0;
        $eventIds = [];
        foreach ($order->items as $item) {
            $categoryEvent = $item->category_event;
            $entry = CategoryEventRegistration::query()
                ->where('registration_id', $item->registration_id)
                ->where('category_event_id', $item->category_event_id)
                ->first();
            if (! $categoryEvent || ! $entry) { $reasons[] = 'registration relationship missing'; continue; }
            if ($categoryEvent->entry_fee !== null && $categoryEvent->entry_fee !== '') $reasons[] = 'category fee is not the nullable-fee incident fingerprint';
            if ((int) $entry->user_id !== (int) $order->user_id) $reasons[] = 'entry ownership mismatch';
            if (! DB::table('player_registrations')->where('registration_id', $item->registration_id)->where('player_id', $item->player_id)->exists()) $reasons[] = 'player registration relationship mismatch';
            if (Schema::hasColumn('registration_order_items', 'user_id') && $item->user_id !== null && (int) $item->user_id !== (int) $order->user_id) $reasons[] = 'order item ownership mismatch';
            $eventIds[] = (int) $categoryEvent->event_id;
            if ($entry->status !== 'active' || $entry->withdrawn_at) $reasons[] = 'registration withdrawn or inactive';
            if ($entry->pf_transaction_id || $entry->wallet_transaction_id || ! in_array($entry->payment_method, [null, '', 'free'], true)) $reasons[] = 'entry contains payment evidence';
            if (! in_array($entry->refund_status, [null, '', 'not_refunded'], true) || $entry->refund_method || (float) $entry->refund_gross > 0 || $entry->refunded_at) $reasons[] = 'entry contains refund evidence';
            if (! in_array($entry->admin_payment_status, [null, '', 'unpaid'], true)) $reasons[] = 'entry contains alternative payment evidence';
            if (DB::table('withdrawals')->where('registration_id', $item->registration_id)->exists()) $reasons[] = 'withdrawal record exists';
            if (DB::table('registration_order_items as roi')->join('registration_orders as ro', 'ro.id', '=', 'roi.order_id')
                ->where('roi.registration_id', $item->registration_id)->where('roi.category_event_id', $item->category_event_id)
                ->where('roi.order_id', '!=', $order->id)->where('ro.pay_status', 1)->exists()) $reasons[] = 'alternative paid order exists';
            $fee = $categoryEvent->entry_fee === null || $categoryEvent->entry_fee === ''
                ? (float) $categoryEvent->event?->entryFee
                : (float) $categoryEvent->entry_fee;
            if ($fee <= 0) $reasons[] = 'server-derived fee is not positive';
            $amount += round($fee, 2);
        }
        if (count(array_unique($eventIds)) > 1) $reasons[] = 'cross-event order';
        $incident = count(array_unique($eventIds)) === 1 ? (self::INCIDENTS[$eventIds[0]] ?? null) : null;
        if (! $incident || ! $order->created_at || $order->created_at->lt(Carbon::parse($incident['from'])) || $order->created_at->gte(Carbon::parse($incident['until'])) || round($amount, 2) !== round((float) $incident['fee'], 2)) {
            $reasons[] = 'outside exact incident event, time, or fee boundary';
        }

        $state = $this->previewState($order, round($amount, 2));
        return ['eligible' => $reasons === [], 'reasons' => array_values(array_unique($reasons)), 'amount' => round($amount, 2), 'order' => $order, 'state' => $state, 'state_hash' => $this->checksum($state)];
    }

    public function prepare(RegistrationOrder $order, int $actorId): RegistrationPaymentRecovery
    {
        return DB::transaction(function () use ($order, $actorId): RegistrationPaymentRecovery {
            $locked = RegistrationOrder::query()->lockForUpdate()->findOrFail($order->id);
            $existing = RegistrationPaymentRecovery::query()->where('registration_order_id', $locked->id)->first();
            if ($existing) return $existing;

            $inspection = $this->inspect($locked);
            if (! $inspection['eligible']) {
                throw ValidationException::withMessages(['order' => implode('; ', $inspection['reasons'])]);
            }

            $locked->load('items.category_event.event');
            $before = [
                'order' => $locked->only(['pay_status', 'payment_method', 'wallet_reserved', 'wallet_debited', 'payfast_paid', 'payfast_pf_payment_id', 'payfast_amount_due', 'total_fee', 'status']),
                'items' => $locked->items->map(fn ($item) => ['id' => $item->id, 'item_price' => $item->item_price])->all(),
                'entries' => CategoryEventRegistration::query()->where(function ($query) use ($locked) {
                    foreach ($locked->items as $item) $query->orWhere(fn ($pair) => $pair->where('registration_id', $item->registration_id)->where('category_event_id', $item->category_event_id));
                })->get()->map(fn ($entry) => $entry->only(['id', 'registration_id', 'category_event_id', 'payment_status_id', 'payment_method', 'pf_transaction_id', 'wallet_transaction_id', 'status', 'refund_status']))->all(),
            ];
            $first = $locked->items->first();
            $event = $first->category_event->event;
            $linkExpiresAt = now()->addDays(7);
            $mailSnapshot = [
                'recipient' => strtolower((string) $locked->user->email),
                'recipient_name' => (string) $locked->user->name,
                'event' => (string) $event->name,
                'players' => $locked->items->map(fn ($item) => trim(($item->player?->name ?? '').' '.($item->player?->surname ?? '')))->values()->all(),
                'categories' => $locked->items->map(fn ($item) => (string) $item->category_event?->category?->name)->values()->all(),
                'amount' => number_format((float) $inspection['amount'], 2, '.', ''),
                'contact' => (string) ($event->email ?? ''),
                'expires_at' => $linkExpiresAt->toIso8601String(),
                'template' => self::MAIL_TEMPLATE_VERSION,
            ];

            foreach ($locked->items as $item) {
                $fee = $item->category_event->entry_fee === null || $item->category_event->entry_fee === ''
                    ? (float) $item->category_event->event->entryFee
                    : (float) $item->category_event->entry_fee;
                $item->item_price = round($fee, 2);
                $item->save();
            }

            FinanceMutationScope::run(['payment_state_write', 'registration_payment_state_write'], function () use ($locked, $inspection): void {
                $locked->forceFill([
                    'pay_status' => 0, 'payment_method' => 'payfast', 'wallet_reserved' => 0,
                    'wallet_debited' => false, 'payfast_paid' => false, 'payfast_pf_payment_id' => null,
                    'payfast_amount_due' => $inspection['amount'], 'total_fee' => $inspection['amount'], 'status' => 'pending',
                ])->save();
                foreach ($locked->items as $item) {
                    CategoryEventRegistration::query()
                        ->where('registration_id', $item->registration_id)
                        ->where('category_event_id', $item->category_event_id)
                        ->update(['payment_status_id' => 0, 'payment_method' => 'payfast']);
                }
            });

            $recovery = RegistrationPaymentRecovery::create([
                'registration_order_id' => $locked->id, 'user_id' => $locked->user_id,
                'amount_due' => $inspection['amount'], 'before_state' => $before,
                'before_state_checksum' => str_repeat('0', 64),
                'preview_state_hash' => $inspection['state_hash'],
                'mail_snapshot' => $mailSnapshot,
                'mail_snapshot_checksum' => str_repeat('0', 64),
                'link_expires_at' => $linkExpiresAt,
                'prepared_by' => $actorId, 'prepared_at' => now(), 'status' => 'prepared',
            ]);
            $recovery->refresh();
            $recovery->forceFill([
                'before_state_checksum' => $this->checksum($recovery->before_state),
                'mail_snapshot_checksum' => $this->checksum($recovery->mail_snapshot),
            ])->save();
            return $recovery;
        }, 3);
    }

    public function confirmationHash(array $ids, string $action): string
    {
        sort($ids, SORT_NUMERIC);
        return hash_hmac('sha256', $action.'|'.implode(',', $ids), (string) config('app.key'));
    }

    public function previewBatchHash(array $ids): string
    {
        sort($ids, SORT_NUMERIC);
        $states = [];
        foreach ($ids as $id) {
            $order = RegistrationOrder::query()->findOrFail($id);
            $inspection = $this->inspect($order);
            if (! $inspection['eligible']) throw ValidationException::withMessages(['order' => "Order {$id}: ".implode('; ', $inspection['reasons'])]);
            $states[] = $inspection['state'];
        }
        return $this->checksum(['action' => 'prepare', 'states' => $states]);
    }

    public function prepareBatch(array $ids, int $actorId, string $expectedHash): array
    {
        sort($ids, SORT_NUMERIC);
        return DB::transaction(function () use ($ids, $actorId, $expectedHash): array {
            RegistrationOrder::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            abort_unless(count($ids) === RegistrationOrder::query()->whereIn('id', $ids)->count(), 409);
            abort_unless(hash_equals($expectedHash, $this->previewBatchHash($ids)), 409);
            return array_map(fn ($id) => $this->prepare(RegistrationOrder::findOrFail($id), $actorId), $ids);
        }, 3);
    }

    public function mailBatchHash(array $recoveries): string
    {
        usort($recoveries, fn ($a, $b) => $a->id <=> $b->id);
        foreach ($recoveries as $recovery) {
            $this->assertMailSnapshotCurrent($recovery);
            $this->assertMailLinkDispatchable($recovery);
        }
        return $this->checksum(['action' => 'queue-mail', 'snapshots' => array_map(fn ($recovery) => [$recovery->id, $recovery->mail_snapshot_checksum], $recoveries)]);
    }

    public function assertRecoveryLinkActive(RegistrationPaymentRecovery $recovery): void
    {
        abort_unless($recovery->link_expires_at && $recovery->link_expires_at->isFuture(), 410, 'This recovery payment link has expired.');
    }

    public function assertMailLinkDispatchable(RegistrationPaymentRecovery $recovery): void
    {
        $this->assertRecoveryLinkActive($recovery);
        abort_unless(
            $recovery->link_expires_at->gt(now()->addMinutes(self::MINIMUM_MAIL_LINK_LIFETIME_MINUTES)),
            409,
            'The recovery payment link expires too soon to send safely.'
        );
    }

    public function nestedSignedLinkExpiry(RegistrationPaymentRecovery $recovery): Carbon
    {
        $this->assertRecoveryLinkActive($recovery);

        return now()->addMinutes(30)->min($recovery->link_expires_at);
    }

    public function validatePrepared(RegistrationPaymentRecovery $recovery): RegistrationOrder
    {
        abort_unless(hash_equals($recovery->before_state_checksum, $this->checksum($recovery->before_state)), 409, 'Recovery before-state checksum mismatch.');
        abort_unless(hash_equals($recovery->mail_snapshot_checksum, $this->checksum($recovery->mail_snapshot)), 409, 'Recovery mail snapshot checksum mismatch.');
        $order = RegistrationOrder::query()->lockForUpdate()->with(['items.category_event.event', 'items.player'])->findOrFail($recovery->registration_order_id);
        abort_unless((int) $order->user_id === (int) $recovery->user_id && ($order->status ?? null) === 'pending', 409, 'Recovery order ownership or state mismatch.');
        abort_unless((int) $order->pay_status === 0 && ! $order->payfast_paid && $order->payment_method === 'payfast' && round((float) $order->payfast_amount_due, 2) === round((float) $recovery->amount_due, 2) && round((float) $order->total_fee, 2) === round((float) $recovery->amount_due, 2) && round((float) $order->items->sum('item_price'), 2) === round((float) $recovery->amount_due, 2), 409, 'Recovery order payment state mismatch.');
        abort_unless(! $order->wallet_debited && (float) $order->wallet_reserved === 0.0 && ! $order->payfast_pf_payment_id && ! $order->wallet_transaction_id, 409, 'Recovery order contains payment evidence.');
        abort_unless(! Transaction::query()->where('custom_int5', $order->id)->exists(), 409, 'Recovery order has PayFast evidence.');
        abort_unless(! WalletTransaction::query()->where('source_id', $order->id)->where('source_type', 'like', '%registration%')->exists(), 409, 'Recovery order has wallet evidence.');
        foreach ($order->items as $item) {
            $entry = CategoryEventRegistration::query()->where('registration_id', $item->registration_id)->where('category_event_id', $item->category_event_id)->where('user_id', $order->user_id)->lockForUpdate()->first();
            abort_unless($entry && $entry->status === 'active' && ! $entry->withdrawn_at && (int) $entry->payment_status_id === 0, 409, 'Recovery entry relationship mismatch.');
            abort_unless($entry->payment_method === 'payfast' && in_array($entry->admin_payment_status, [null, '', 'unpaid'], true) && ! $entry->pf_transaction_id && ! $entry->wallet_transaction_id && in_array($entry->refund_status, [null, '', 'not_refunded'], true) && ! $entry->refund_method && (float) $entry->refund_gross === 0.0 && ! $entry->refunded_at, 409, 'Recovery entry contains payment or refund evidence.');
            abort_unless(! DB::table('withdrawals')->where('registration_id', $item->registration_id)->exists(), 409, 'Recovery entry has withdrawal evidence.');
            abort_unless(! DB::table('registration_order_items as roi')->join('registration_orders as ro', 'ro.id', '=', 'roi.order_id')->where('roi.registration_id', $item->registration_id)->where('roi.category_event_id', $item->category_event_id)->where('roi.order_id', '!=', $order->id)->where('ro.pay_status', 1)->exists(), 409, 'Recovery entry has alternate payment evidence.');
            abort_unless(DB::table('player_registrations')->where('registration_id', $item->registration_id)->where('player_id', $item->player_id)->exists(), 409, 'Recovery player relationship mismatch.');
        }
        return $order;
    }

    public function assertMailSnapshotCurrent(RegistrationPaymentRecovery $recovery): void
    {
        abort_unless(hash_equals($recovery->mail_snapshot_checksum, $this->checksum($recovery->mail_snapshot)), 409);
        $recovery->loadMissing('user', 'order.items.category_event.event', 'order.items.category_event.category', 'order.items.player');
        $snapshot = $recovery->mail_snapshot;
        abort_unless(strtolower((string) $recovery->user?->email) === $snapshot['recipient'], 409);
        abort_unless(number_format((float) $recovery->amount_due, 2, '.', '') === $snapshot['amount'], 409);
        abort_unless((string) $recovery->order->items->first()?->category_event?->event?->email === $snapshot['contact'], 409);
    }

    public function mailAuthorizationHash(RegistrationPaymentRecovery $recovery): string
    {
        return $this->checksum(['action' => 'send-mail', 'recovery_id' => $recovery->id, 'snapshot' => $recovery->mail_snapshot_checksum]);
    }

    public function authorizeMail(RegistrationPaymentRecovery $recovery, int $actorId): RegistrationPaymentRecovery
    {
        $actor = User::query()->find($actorId);
        abort_unless($actor && $actor->hasRole('super-user'), 403, 'Only a current super-user may authorize recovery mail.');
        return DB::transaction(function () use ($recovery, $actorId): RegistrationPaymentRecovery {
            $locked = RegistrationPaymentRecovery::query()->lockForUpdate()->findOrFail($recovery->id);
            abort_if($locked->mail_authorized_at !== null, 409, 'Recovery mail was already authorized.');
            $this->validatePrepared($locked);
            $this->assertMailSnapshotCurrent($locked);
            $this->assertMailLinkDispatchable($locked);
            $locked->update(['mail_queued_by' => $actorId, 'mail_confirmation_hash' => $this->mailAuthorizationHash($locked), 'mail_authorized_at' => now()]);
            return $locked;
        });
    }

    public function finalizePayfastRecovery(RegistrationPaymentRecovery $recovery, float $amount, array $context): RegistrationOrder
    {
        return DB::transaction(function () use ($recovery, $amount, $context): RegistrationOrder {
            $this->validatePrepared(RegistrationPaymentRecovery::query()->lockForUpdate()->findOrFail($recovery->id));
            return app(RegistrationPaymentService::class)->finalizePayfastPayment($recovery->order, $amount, $context);
        }, 3);
    }

    private function previewState(RegistrationOrder $order, float $amount): array
    {
        return ['order_id' => $order->id, 'user_id' => $order->user_id, 'created_at' => $order->created_at?->toIso8601String(), 'amount' => number_format($amount, 2, '.', ''),
            'items' => $order->items->map(fn ($item) => [$item->id, $item->registration_id, $item->player_id, $item->category_event_id, number_format((float) $item->item_price, 2, '.', '')])->values()->all()];
    }

    private function checksum(array $state): string
    {
        return hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), (string) config('app.key'));
    }
}
