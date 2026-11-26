<?php

namespace App\Domain\Refunds\Services;

use App\Domain\Entries\Services\EntryService;
use App\Mail\WalletRefundConfirmationMail;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\RegistrationOrderItems;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminRegistrationWalletRefundService
{
    /** Cancellation refunds each immutable basket item, never an equal division of the basket. */
    public function categoryCancellationSnapshot(Event $event, CategoryEventRegistration $entry, bool $requireReceiptEmail = true): ?array
    {
        abort_unless((int) $entry->categoryEvent?->event_id === (int) $event->id, 404);
        $items = RegistrationOrderItems::where('registration_id', $entry->registration_id)
            ->where('category_event_id', $entry->category_event_id)->get();
        if ($items->isEmpty() && $entry->isAdminEntry() && $entry->admin_payment_status === 'unpaid' && $entry->is_paid
            && !$entry->pf_transaction_id && !$entry->wallet_transaction_id && !\App\Models\CategoryAdjustment::where('entry_id', $entry->id)->exists()) {
            $receipts = DB::table('transactions_pf')->where('registration_id', $entry->registration_id)
                ->where('category_event_id', $entry->category_event_id)->where('event_id', $event->id)->get();
            if ($receipts->count() === 1 && $receipts->every(fn ($receipt) => $receipt->item_name === 'Admin Entry'
                && !$receipt->pf_payment_id && (float) $receipt->amount_gross === 0.0 && (float) $receipt->amount_net === 0.0
                && (float) $receipt->amount_fee === 0.0 && (int) $receipt->custom_int4 === (int) $entry->user_id)) return null;
        }
        if ($items->count() !== 1) {
            throw ValidationException::withMessages(['refund' => 'A unique original checkout item is required. Reconcile this entry before cancelling.']);
        }
        $item = $items->first();
        $order = $item->order()->lockForUpdate()->first();
        $basket = $order?->items()->orderBy('id')->lockForUpdate()->get();
        $payer = $order?->user;
        $gross = round((float) $item->item_price, 2);
        $captured = round(($order?->payfast_paid ? (float) $order->payfast_amount_due : 0)
            + ($order?->wallet_debited ? (float) $order->wallet_reserved : 0), 2);
        $total = $basket ? round((float) $basket->sum('item_price'), 2) : -1;
        $mixed = $basket && $basket->contains(fn ($row) => (int) $row->category_event_id !== (int) $entry->category_event_id);
        $mixedProof = [];
        if ($mixed && (!$order->payfast_paid || (float) $order->wallet_reserved !== 0.0 || $order->wallet_debited
            || $order->wallet_transaction_id || $gross <= 0 || $basket->contains(fn ($row) => round((float) $row->item_price, 2) !== $gross))) {
            throw ValidationException::withMessages(['refund' => 'A mixed-category checkout requires identical verified PayFast-only item prices. Finance must review this basket before cancellation.']);
        }
        foreach ($basket ?? [] as $row) {
            $siblings = CategoryEventRegistration::withTrashed()->where('registration_id', $row->registration_id)
                ->where('category_event_id', $row->category_event_id)->lockForUpdate()->get();
            $sibling = $siblings->first();
            if ($siblings->count() !== 1 || !$row->player_id || !$sibling->registration?->players()->whereKey($row->player_id)->exists()
                || ($sibling->refund_status === 'completed'
                && (round((float) $sibling->refund_gross, 2) !== round((float) $row->item_price, 2)
                    || round((float) $sibling->refund_net, 2) !== round((float) $row->item_price, 2)
                    || (float) $sibling->refund_fee !== 0.0))) {
                throw ValidationException::withMessages(['refund' => 'An existing basket refund needs reconciliation before cancelling.']);
            }
            if ($mixed) {
                $paid = $sibling->paymentInfo();
                $noRefund = in_array((string) $sibling->refund_status, CategoryEventRegistration::NO_REFUND_STATUSES, true)
                    && !$sibling->refunded_at && !filled($sibling->refund_method) && !$sibling->refund_waived_at
                    && (float) $sibling->refund_gross === 0.0 && (float) $sibling->refund_fee === 0.0 && (float) $sibling->refund_net === 0.0;
                $fullRefund = $sibling->refund_status === 'completed' && $sibling->status === 'withdrawn';
                if (!$sibling->is_paid || $sibling->trashed() || !in_array($sibling->status, ['active', 'withdrawn'], true)
                    || (!$noRefund && !$fullRefund) || (int) $sibling->categoryEvent?->event_id !== (int) $event->id
                    || (int) $row->user_id !== (int) $payer?->id || (int) $sibling->user_id !== (int) $payer?->id
                    || !in_array($sibling->payment_method, ['payfast', null], true) || $sibling->wallet_transaction_id
                    || (string) $sibling->pf_transaction_id !== (string) $order->payfast_pf_payment_id
                    || RegistrationOrderItems::where('registration_id', $row->registration_id)->where('category_event_id', $row->category_event_id)->count() !== 1
                    || \App\Models\CategoryAdjustment::where('entry_id', $sibling->id)->exists()
                    || \App\Models\IndividualProviderRefundAttempt::where('entry_id', $sibling->id)->orWhere('pf_payment_id', $order->payfast_pf_payment_id)->exists()
                    || empty($paid['transaction_id']) || ($paid['_legacy'] ?? false)
                    || round((float) ($paid['gross'] ?? 0), 2) !== $gross || (float) ($paid['wallet_paid'] ?? 0) !== 0.0
                    || round((float) ($paid['total_paid'] ?? 0), 2) !== $gross) {
                    throw ValidationException::withMessages(['refund' => 'The mixed-category basket has unresolved funding, identity or refund evidence. Reconcile it before cancelling.']);
                }
                $mixedProof[] = ['entry_id' => $sibling->id, 'status' => $sibling->status, 'refund_status' => $sibling->refund_status,
                    'refund_net' => $sibling->refund_net, 'receipt_id' => $paid['transaction_id'], 'gross' => $paid['gross'], 'wallet_paid' => $paid['wallet_paid']];
            }
        }
        $valid = $order && $payer && $entry->is_paid && $order->pay_status && $gross >= 0
            && $total === round((float) $order->total_fee, 2) && $captured === $total
            && $basket->every(fn ($row) => $row->registration_id && $row->category_event_id && (float) $row->item_price >= 0
                && (!$row->user_id || (int) $row->user_id === (int) $payer->id))
            && $basket->unique(fn ($row) => $row->registration_id.':'.$row->category_event_id)->count() === $basket->count()
            && ((float) $order->payfast_amount_due <= 0 || ($order->payfast_paid && filled($order->payfast_pf_payment_id)
                && (string) $entry->pf_transaction_id === (string) $order->payfast_pf_payment_id))
            && (float) $order->wallet_reserved >= 0 && (float) $order->payfast_amount_due >= 0
            && ((float) $order->wallet_reserved <= 0 || ($order->wallet_debited && $order->walletTransaction?->type === 'debit'
                && round((float) $order->walletTransaction->amount, 2) === round((float) $order->wallet_reserved, 2)
                && $order->walletTransaction->wallet?->payable_type === User::class
                && (int) $order->walletTransaction->wallet?->payable_id === (int) $payer->id
                && (int) $entry->wallet_transaction_id === (int) $order->wallet_transaction_id));
        if (!$valid || \App\Models\CategoryAdjustment::where('entry_id', $entry->id)->exists()
            || \App\Models\IndividualBeneficiaryCorrection::where('order_id', $order?->id)->exists()) {
            throw ValidationException::withMessages(['refund' => 'The original basket, captured funds or beneficiary needs reconciliation before category cancellation.']);
        }
        if ($gross === 0.0) return null;
        if ($requireReceiptEmail && !filter_var($payer->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['refund' => 'The original payer needs a valid refund receipt email.']);
        }
        return ['entry_id' => $entry->id, 'event_id' => $event->id, 'payer_id' => $payer->id,
            'payer_name' => $payer->name, 'to' => strtolower(trim($payer->email)), 'gross' => $gross,
            'order_id' => $order->id, 'order_pf_id' => $order->payfast_pf_payment_id,
            'wallet_transaction_id' => $order->wallet_transaction_id,
            'mixed_basket_proof' => $mixedProof,
            'basket' => $basket->map(fn ($row) => $row->only(['id', 'registration_id', 'category_event_id', 'player_id', 'user_id', 'item_price']))->all()];
    }

    public function snapshot(Event $event, CategoryEventRegistration $entry, float $percentage, string $reason, ?User $actor = null): array
    {
        $financial = $this->financialSnapshot($event, $entry);
        $admins = $event->admins()->orderBy('users.id')->get()->filter(fn ($user) => filter_var($user->email, FILTER_VALIDATE_EMAIL));
        $actor ??= auth()->user();
        if ($admins->isEmpty() && $actor?->hasRole('super-user') && filter_var($actor->email, FILTER_VALIDATE_EMAIL)) {
            $admins = collect([$actor]);
        }
        if ($admins->isEmpty()) {
            throw ValidationException::withMessages(['refund' => 'Assign an event admin with a valid email, or ask a Super User with a valid email to confirm this refund.']);
        }
        if (!SiteSetting::emailEnabled('player_email_on_wallet_refund')) {
            throw ValidationException::withMessages(['refund' => 'Wallet refund confirmation emails are disabled.']);
        }
        $fee = round($financial['gross'] * $percentage / 100, 2);
        if ($financial['gross'] - $fee <= 0) {
            throw ValidationException::withMessages(['percentage' => 'The refund must be greater than zero.']);
        }
        $emails = $admins->pluck('email')->map(fn ($email) => strtolower(trim($email)))->unique()->values()->all();
        return $financial + ['review_actor_id' => $actor?->id, 'cc' => $emails, 'reply_to' => $emails, 'fee' => $fee,
            'net' => round($financial['gross'] - $fee, 2), 'percentage' => $percentage, 'reason' => $reason];
    }

    /** Verified original funding; callers must independently authorize their workflow. */
    public function financialSnapshot(Event $event, CategoryEventRegistration $entry): array
    {
        abort_unless((int) $entry->categoryEvent?->event_id === (int) $event->id, 404);
        if ($entry->refund_status === 'completed' || $entry->refund_status === 'pending') {
            throw ValidationException::withMessages(['refund' => 'This refund is already completed or pending reconciliation.']);
        }
        $adjustment = \App\Models\CategoryAdjustment::where('entry_id', $entry->id)->whereNotNull('applied_at')->latest('id')->first();
        $item = $adjustment ? RegistrationOrderItems::find($adjustment->original_order_item_id)
            : RegistrationOrderItems::where('registration_id', $entry->registration_id)->where('category_event_id', $entry->category_event_id)->first();
        if (! $adjustment && $item?->order && $item->order->items()->count() !== 1) {
            throw ValidationException::withMessages(['refund' => 'This order contains multiple entries. Reconcile the individual paid amount before refunding.']);
        }
        $payer = $item?->order?->user ?? $entry->user;
        $order = $item?->order;
        $paidAdminUpgrade = $adjustment && ! $adjustment->original_order_item_id && $entry->isAdminEntry();
        if ((! $order && ! $paidAdminUpgrade) || ($order && (!$order->pay_status
            || ((float) $order->wallet_reserved > 0 && (!$order->wallet_debited || !$order->walletTransaction || $order->walletTransaction->type !== 'debit'))
            || ((float) $order->payfast_amount_due > 0 && (!$order->payfast_paid || !$order->payfast_pf_payment_id))))) {
            throw ValidationException::withMessages(['refund' => 'The original paid order must be verified before this refund.']);
        }
        if ($order?->wallet_debited && (round((float) $order->walletTransaction?->amount, 2) !== round((float) $order->wallet_reserved, 2)
            || (int) $order->walletTransaction?->wallet?->payable_id !== (int) $payer->id
            || $order->walletTransaction?->wallet?->payable_type !== User::class)) {
            throw ValidationException::withMessages(['refund' => 'The wallet payment does not match the original payer.']);
        }
        $payment = $entry->paymentInfo();
        $gross = round((float) ($payment['gross'] ?? 0) + (float) ($payment['wallet_paid'] ?? 0), 2);
        $captured = round(($order?->payfast_paid ? (float) $order->payfast_amount_due : 0)
            + ($order?->wallet_debited ? (float) $order->wallet_reserved : 0), 2);
        if ($paidAdminUpgrade) {
            $history = \App\Models\CategoryAdjustment::where('entry_id', $entry->id)->whereNotNull('applied_at')->get();
            $verified = $history->where('difference', '>', 0)->every(fn ($row) => $row->payfast_paid && $row->payfast_pf_payment_id
                && (int) $row->user_id === (int) $payer?->id);
            $captured = round($history->where('difference', '>', 0)->sum('difference') - $history->sum('wallet_credit'), 2);
            if (! $verified || $gross !== $captured) { throw ValidationException::withMessages(['refund' => 'The category upgrade receipts need reconciliation.']); }
        }
        if ((! $adjustment && ($gross !== $captured || $gross !== round((float) $item->item_price, 2)))
            || ($adjustment && ((int) $adjustment->user_id !== (int) $payer?->id
                || (! $paidAdminUpgrade && (int) $item?->registration_id !== (int) $entry->registration_id)
                || $gross !== round((float) $adjustment->payment_after['total_paid'], 2)))) {
            throw ValidationException::withMessages(['refund' => 'The item price and captured payment do not match. Reconcile before refunding.']);
        }
        if (!$payer || !filter_var($payer->email, FILTER_VALIDATE_EMAIL) || $gross <= 0 || !$entry->is_paid) {
            throw ValidationException::withMessages(['refund' => 'A paid entry and valid payer email are required.']);
        }
        return [
            'entry_id' => $entry->id, 'event_id' => $event->id, 'payer_id' => $payer->id,
            'payer_name' => $payer->name, 'to' => $payer->email,
            'gross' => $gross, 'status' => $entry->status,
            'funding_order_id' => $order?->id, 'funding_pf_id' => $order?->payfast_pf_payment_id,
            'funding_wallet_transaction_id' => $order?->wallet_transaction_id,
            'event_name' => $event->name, 'player_name' => $entry->display_name,
            'category_name' => $entry->categoryEvent?->category?->name,
        ];
    }

    public function preview(Event $event, CategoryEventRegistration $entry, float $percentage, string $reason): array
    {
        $snapshot = $this->snapshot($event, $entry, $percentage, $reason);
        $preview = clone $entry;
        $preview->refund_gross = $snapshot['gross'];
        $preview->refund_fee = $snapshot['fee'];
        $preview->refund_net = $snapshot['net'];
        $preview->refunded_at = now();
        $mail = new WalletRefundConfirmationMail($preview, $reason, $snapshot['payer_name'], $snapshot['cc'], $snapshot['reply_to'], $snapshot + ['preview' => true]);
        return $snapshot + [
            'html' => $mail->render(),
            'token' => Crypt::encryptString(json_encode(['snapshot' => $snapshot, 'expires' => now()->addMinutes(15)->timestamp], JSON_THROW_ON_ERROR)),
        ];
    }

    public function execute(Event $event, CategoryEventRegistration $entry, User $actor, float $percentage, string $reason, string $token): array
    {
        abort_unless($actor->hasRole('super-user'), 403);
        try {
            $approved = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['preview_token' => 'Refresh the email preview before confirming.']);
        }
        return DB::transaction(function () use ($event, $entry, $actor, $percentage, $reason, $approved) {
            $locked = CategoryEventRegistration::lockForUpdate()->findOrFail($entry->id);
            $adjustment = \App\Models\CategoryAdjustment::where('entry_id', $locked->id)->whereNotNull('applied_at')->latest('id')->first();
            $item = $adjustment ? RegistrationOrderItems::find($adjustment->original_order_item_id)
                : RegistrationOrderItems::where('registration_id', $locked->registration_id)->where('category_event_id', $locked->category_event_id)->first();
            $item?->order()->lockForUpdate()->first();
            $snapshot = $this->snapshot($event, $locked, $percentage, $reason, $actor);
            if (($approved['expires'] ?? 0) < now()->timestamp || ($approved['snapshot'] ?? []) != $snapshot) {
                throw ValidationException::withMessages(['preview_token' => 'The refund or recipients changed. Refresh the email preview before confirming.']);
            }
            $payer = User::lockForUpdate()->findOrFail($snapshot['payer_id']);
            if ($locked->status !== 'withdrawn') {
                app(EntryService::class)->withdrawEntryAsAdmin($locked, $actor);
            }
            $wallet = $payer->wallet ?? $payer->wallet()->create([]);
            $meta = $snapshot + ['initiated_by' => 'super_admin', 'reference' => $event->name];
            $completed = app(RefundExecutionService::class)->executeWalletRefund(
                $locked, $wallet, $snapshot['net'], 'admin_full_refund', $locked->id, $meta,
                ['refund_method' => 'wallet', 'refund_gross' => $snapshot['gross'], 'refund_fee' => $snapshot['fee'], 'refund_net' => $snapshot['net']]
            );
            activity('refund')->performedOn($completed)->causedBy($actor)->withProperties($meta)->log('Super-admin registration wallet refund');
            return ['registration' => $completed, 'payer' => $payer, 'snapshot' => $snapshot];
        });
    }
}
