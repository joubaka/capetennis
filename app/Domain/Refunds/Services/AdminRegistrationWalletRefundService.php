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
    public function snapshot(Event $event, CategoryEventRegistration $entry, float $percentage, string $reason): array
    {
        abort_unless((int) $entry->categoryEvent?->event_id === (int) $event->id, 404);
        if ($entry->refund_status === 'completed' || $entry->refund_status === 'pending') {
            throw ValidationException::withMessages(['refund' => 'This refund is already completed or pending reconciliation.']);
        }
        $item = RegistrationOrderItems::where('registration_id', $entry->registration_id)
            ->where('category_event_id', $entry->category_event_id)->first();
        if ($item?->order && $item->order->items()->count() !== 1) {
            throw ValidationException::withMessages(['refund' => 'This order contains multiple entries. Reconcile the individual paid amount before refunding.']);
        }
        $payer = $item?->order?->user ?? $entry->user;
        $order = $item?->order;
        if (!$order || !$order->pay_status
            || ((float) $order->wallet_reserved > 0 && (!$order->wallet_debited || !$order->walletTransaction || $order->walletTransaction->type !== 'debit'))
            || ((float) $order->payfast_amount_due > 0 && (!$order->payfast_paid || !$order->payfast_pf_payment_id))) {
            throw ValidationException::withMessages(['refund' => 'The original paid order must be verified before this refund.']);
        }
        if ($order->wallet_debited && (round((float) $order->walletTransaction?->amount, 2) !== round((float) $order->wallet_reserved, 2)
            || (int) $order->walletTransaction?->wallet?->payable_id !== (int) $payer->id
            || $order->walletTransaction?->wallet?->payable_type !== User::class)) {
            throw ValidationException::withMessages(['refund' => 'The wallet payment does not match the original payer.']);
        }
        $payment = $entry->paymentInfo();
        $gross = round((float) ($payment['gross'] ?? 0) + (float) ($payment['wallet_paid'] ?? 0), 2);
        $captured = round(($order->payfast_paid ? (float) $order->payfast_amount_due : 0)
            + ($order->wallet_debited ? (float) $order->wallet_reserved : 0), 2);
        if ($gross !== $captured || $gross !== round((float) $item->item_price, 2)) {
            throw ValidationException::withMessages(['refund' => 'The item price and captured payment do not match. Reconcile before refunding.']);
        }
        $admins = $event->admins()->orderBy('users.id')->get()->filter(fn ($user) => filter_var($user->email, FILTER_VALIDATE_EMAIL));
        if (!$payer || !filter_var($payer->email, FILTER_VALIDATE_EMAIL) || $gross <= 0 || !$entry->is_paid) {
            throw ValidationException::withMessages(['refund' => 'A paid entry and valid payer email are required.']);
        }
        if ($admins->isEmpty()) {
            throw ValidationException::withMessages(['refund' => 'Assign an event admin with a valid email before confirming this refund.']);
        }
        if (!SiteSetting::emailEnabled('player_email_on_wallet_refund')) {
            throw ValidationException::withMessages(['refund' => 'Wallet refund confirmation emails are disabled.']);
        }
        $fee = round($gross * $percentage / 100, 2);
        if ($gross - $fee <= 0) {
            throw ValidationException::withMessages(['percentage' => 'The refund must be greater than zero.']);
        }
        return [
            'entry_id' => $entry->id, 'event_id' => $event->id, 'payer_id' => $payer->id,
            'payer_name' => $payer->name, 'to' => $payer->email,
            'cc' => $admins->pluck('email')->unique()->values()->all(),
            'reply_to' => $admins->pluck('email')->unique()->values()->all(),
            'gross' => $gross, 'fee' => $fee, 'net' => round($gross - $fee, 2),
            'percentage' => $percentage, 'reason' => $reason, 'status' => $entry->status,
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
            $item = RegistrationOrderItems::where('registration_id', $locked->registration_id)->where('category_event_id', $locked->category_event_id)->first();
            $item?->order()->lockForUpdate()->first();
            $snapshot = $this->snapshot($event, $locked, $percentage, $reason);
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
