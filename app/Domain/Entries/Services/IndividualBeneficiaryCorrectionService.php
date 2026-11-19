<?php

declare(strict_types=1);

namespace App\Domain\Entries\Services;

use App\Models\{CategoryAdjustment, CategoryEvent, CategoryEventRegistration, EventNomination, IndividualBeneficiaryCorrection, InterprovincialTrialInvitation, Player, Registration, RegistrationOrder, RegistrationOrderItems, Transaction, User};
use Illuminate\Support\Facades\{Crypt, DB, Schema};
use Illuminate\Validation\ValidationException;

/** Correct current participation while retaining the original payer, item and receipt. */
final class IndividualBeneficiaryCorrectionService
{
    public function run(User $actor, int $orderId, int $targetPlayerId, string $reason, ?string $confirmation = null): array
    {
        abort_unless($actor->fresh()->hasRole('super-user'), 403);
        $this->ensure(strlen(trim($reason)) >= 5 && strlen($reason) <= 1000, 'Give a specific correction reason.');

        return DB::transaction(function () use ($actor, $orderId, $targetPlayerId, $reason, $confirmation) {
            $order = RegistrationOrder::lockForUpdate()->findOrFail($orderId);
            $item = $order->items()->lockForUpdate()->sole();
            $existing = IndividualBeneficiaryCorrection::where('order_id', $orderId)->lockForUpdate()->first();
            if ($existing) {
                $this->ensure((int) $existing->actor_id === (int) $actor->id && (int) $existing->target_player_id === $targetPlayerId
                    && $existing->reason === trim($reason) && $confirmation !== null, 'This payment already has a different correction.');
                $approved = $this->confirmation($confirmation);
                $this->ensure(($approved['fingerprint'] ?? '') === $existing->fingerprint && ($approved['actor_id'] ?? 0) === $actor->id
                    && ($approved['expires'] ?? 0) >= now()->timestamp, 'This confirmation belongs to another correction or expired.');
                $invitation = InterprovincialTrialInvitation::findOrFail($existing->target_invitation_id);
                $this->ensure($this->matches($order, $item, $invitation), 'Corrected participation no longer matches its evidence.');
                return ['applied' => true, 'already_applied' => true, 'correction_id' => $existing->id];
            }
            $category = CategoryEvent::lockForUpdate()->findOrFail($item->category_event_id);
            $event = $category->event()->lockForUpdate()->firstOrFail();
            $this->ensure($event->isInterprovincialTrials() && $event->published && $event->hasOpenRegistrationLifecycle()
                && $category->nominations_published && ! $category->isLocked(), 'A published open Interpro category is required.');
            $this->ensure(! \App\Models\Draw::where('category_event_id', $category->id)->exists(), 'The category must have no draw or competition structure.');
            $this->ensure($order->items()->count() === 1 && $order->pay_status && $order->payfast_paid && $order->status === 'completed'
                && $order->payment_method === 'payfast' && (float) $order->wallet_reserved === 0.0 && ! $order->wallet_debited
                && ! $order->wallet_transaction_id && $order->payfast_handed_off_at && $order->payfast_pf_payment_id
                && (int) $item->user_id === (int) $order->user_id && (int) $item->player_id !== $targetPlayerId
                && $this->cents($order->total_fee) > 0 && $this->cents($item->item_price) === $this->cents($order->total_fee)
                && $this->cents($order->payfast_amount_due) === $this->cents($order->total_fee)
                && $this->cents($category->effectiveEntryFee()) === $this->cents($order->total_fee), 'Only an exact single-player PayFast-only settlement with unchanged fees can be corrected.');
            $registration = Registration::lockForUpdate()->findOrFail($item->registration_id);
            $this->ensure(CategoryEventRegistration::withTrashed()->where('registration_id', $registration->id)->count() === 1
                && RegistrationOrderItems::where('registration_id', $registration->id)->count() === 1, 'Shared historical registration identity cannot be corrected.');
            $entry = CategoryEventRegistration::where('registration_id', $registration->id)->lockForUpdate()->sole();
            $this->ensure((int) $entry->category_event_id === (int) $category->id && (int) $entry->user_id === (int) $order->user_id
                && $entry->status === 'active' && ! $entry->withdrawn_at && ! $entry->refunded_at && ! $entry->refund_method
                && ($entry->refund_status === null || in_array($entry->refund_status, CategoryEventRegistration::NO_REFUND_STATUSES, true))
                && ! (float) $entry->refund_gross && ! (float) $entry->refund_fee && ! (float) $entry->refund_net
                && ! $entry->refund_waived_at && ! $entry->refund_waived_by && ! $entry->refund_waiver_reason && (int) $entry->payment_status_id === 1
                && $entry->payment_method === 'payfast' && ! $entry->wallet_transaction_id && ! $entry->admin_payment_status
                && (string) $entry->pf_transaction_id === (string) $order->payfast_pf_payment_id
                && ! $registration->competition_replacement && ! CategoryAdjustment::where('entry_id', $entry->id)->exists(), 'The original active paid entry must have no withdrawal, refund or category-change history.');
            $this->ensure($registration->players()->count() === 1 && $registration->players()->whereKey($item->player_id)->exists(), 'The original registration player changed.');
            $this->assertNoCompetition($registration->id);
            $source = InterprovincialTrialInvitation::where('order_id', $orderId)->lockForUpdate()->sole();
            $target = InterprovincialTrialInvitation::where('event_id', $event->id)->where('category_event_id', $category->id)
                ->where('player_id', $targetPlayerId)->lockForUpdate()->sole();
            $this->ensure((int) $source->registration_id === (int) $registration->id && (int) $source->player_id === (int) $item->player_id
                && (int) $source->category_event_id === (int) $category->id && (int) $source->event_id === (int) $event->id
                && $source->status === 'paid_confirmed' && $source->paid_at && ! $source->withdrawn_at
                && ! $target->order_id && ! $target->registration_id && ! $target->paid_at && ! $target->withdrawn_at
                && in_array($target->status, ['sent', 'open_registration'], true), 'Both current invitations must match the original payment and an available target nominee.');
            foreach ([$source, $target] as $invitation) {
                $this->ensure(EventNomination::whereKey($invitation->nomination_id)->where('event_id', $event->id)
                    ->where('category_event_id', $category->id)->where('player_id', $invitation->player_id)->exists(), 'Nomination identity changed.');
            }
            $players = Player::whereIn('id', [$item->player_id, $targetPlayerId])->orderBy('id')->lockForUpdate()->get();
            app(EntryEligibilityService::class)->assertCanRegister($category, $targetPlayerId);
            $otherAttempts = $this->assertOtherAttemptsSafe($order, $category, $targetPlayerId);
            $snapshot = $this->paymentSnapshot($order, $item);
            $this->ensure($snapshot['receipt'] !== null, 'The original matching PayFast receipt is required.');
            $preview = ['actor_id' => $actor->id, 'order_id' => $order->id, 'entry_id' => $entry->id,
                'original_player_id' => (int) $item->player_id, 'target_player_id' => $targetPlayerId,
                'event_id' => $event->id, 'category_event_id' => $category->id, 'amount' => number_format((float) $order->total_fee, 2, '.', ''),
                'provider_id' => $order->payfast_pf_payment_id, 'payer_id' => $order->user_id, 'reason' => trim($reason),
                'payment_snapshot' => $snapshot, 'entry' => $entry->getAttributes(), 'other_attempts' => $otherAttempts,
                'players' => $players->map(fn ($player) => $player->only(['id', 'name', 'surname', 'dateOfBirth', 'gender', 'profile_complete', 'updated_at']))->all(),
                'invitations' => [$source->getAttributes(), $target->getAttributes()]];
            $fingerprint = hash('sha256', json_encode($preview, JSON_THROW_ON_ERROR));
            if ($confirmation === null) {
                return ['applied' => false, 'preview' => $preview, 'confirmation' => Crypt::encryptString(json_encode([
                    'fingerprint' => $fingerprint, 'actor_id' => $actor->id, 'expires' => now()->addMinutes(15)->timestamp,
                ], JSON_THROW_ON_ERROR))];
            }
            $approved = $this->confirmation($confirmation);
            $this->ensure(($approved['actor_id'] ?? 0) === $actor->id && ($approved['expires'] ?? 0) >= now()->timestamp
                && hash_equals($fingerprint, $approved['fingerprint'] ?? ''), 'State changed or confirmation expired. Review again.');
            $correction = IndividualBeneficiaryCorrection::create([
                'order_id' => $order->id, 'entry_id' => $entry->id, 'original_player_id' => $item->player_id,
                'target_player_id' => $targetPlayerId, 'source_invitation_id' => $source->id, 'target_invitation_id' => $target->id,
                'actor_id' => $actor->id, 'reason' => trim($reason), 'fingerprint' => $fingerprint,
                'payment_snapshot' => $snapshot, 'invitation_snapshot' => $preview['invitations'],
            ]);
            $registration->players()->sync([$targetPlayerId]);
            $source->forceFill(['order_id' => null, 'registration_id' => null, 'status' => 'sent', 'accepted_at' => null, 'paid_at' => null])->save();
            $target->forceFill(['order_id' => $order->id, 'registration_id' => $registration->id,
                'status' => 'paid_confirmed', 'accepted_at' => now(), 'paid_at' => $source->getOriginal('paid_at') ?? $preview['invitations'][0]['paid_at']])->save();
            activity('individual_beneficiary_correction')->performedOn($correction)->causedBy($actor)
                ->withProperties(['event_id' => $event->id, 'entry_id' => $entry->id, 'order_id' => $order->id,
                    'original_player_id' => $item->player_id, 'target_player_id' => $targetPlayerId, 'reason' => trim($reason)])
                ->log('Corrected mistaken individual registration beneficiary; original payment preserved');
            return ['applied' => true, 'already_applied' => false, 'correction_id' => $correction->id, 'preview' => $preview];
        }, 3);
    }

    /** Exact immutable settlement plus current sole participation; never accepts a merely present mapping. */
    public function matches(RegistrationOrder $order, RegistrationOrderItems $item, InterprovincialTrialInvitation $invitation): bool
    {
        if (! Schema::hasTable('individual_beneficiary_corrections')) return false;
        $mapping = IndividualBeneficiaryCorrection::where('order_id', $order->id)->first();
        if (! $mapping) return false;
        $entry = CategoryEventRegistration::find($mapping->entry_id);
        return $mapping->payment_snapshot === $this->paymentSnapshot($order, $item)
            && $entry && (int) $entry->registration_id === (int) $item->registration_id
            && (int) $entry->category_event_id === (int) $item->category_event_id && (int) $entry->user_id === (int) $order->user_id
            && (int) $entry->payment_status_id === 1 && (string) $entry->pf_transaction_id === (string) $order->payfast_pf_payment_id
            && (int) $mapping->original_player_id === (int) $item->player_id && $order->items()->count() === 1
            && (int) $invitation->id === (int) $mapping->target_invitation_id && (int) $invitation->order_id === (int) $order->id
            && (int) $invitation->nomination_id === (int) ($mapping->invitation_snapshot[1]['nomination_id'] ?? 0)
            && (int) $invitation->registration_id === (int) $item->registration_id && (int) $invitation->player_id === (int) $mapping->target_player_id
            && (int) $invitation->category_event_id === (int) $item->category_event_id
            && (int) $invitation->event_id === (int) $item->category_event?->event_id
            && in_array($invitation->status, ['paid_confirmed', 'withdrawn'], true)
            && $entry->registration->players()->count() === 1
            && $entry->registration->players()->whereKey($mapping->target_player_id)->exists()
            && EventNomination::whereKey($invitation->nomination_id)->where('event_id', $invitation->event_id)
                ->where('category_event_id', $invitation->category_event_id)->where('player_id', $mapping->target_player_id)->exists();
    }

    private function paymentSnapshot(RegistrationOrder $order, RegistrationOrderItems $item): array
    {
        $receipt = Transaction::where('pf_payment_id', $order->payfast_pf_payment_id)->lockForUpdate()->get();
        $tx = $receipt->count() === 1 ? $receipt->first() : null;
        $valid = $tx && (int) $tx->custom_int5 === (int) $order->id && (int) $tx->custom_int4 === (int) $order->user_id
            && (int) $tx->custom_int1 === (int) $item->category_event_id && (int) $tx->custom_int2 === (int) $item->player_id
            && (int) $tx->custom_int3 === (int) $item->category_event?->event_id
            && ! $tx->custom_str5 && ! $tx->is_test && ! $tx->archived_at
            && $this->cents($tx->amount_gross) === $this->cents($order->total_fee)
            && $this->cents($tx->amount_gross) === $this->cents($tx->amount_fee) + $this->cents($tx->amount_net);
        if ($valid) {
            foreach (['custom_int1' => $item->category_event_id, 'custom_int2' => $item->player_id, 'custom_int3' => $item->category_event?->event_id,
                'category_event_id' => $item->category_event_id, 'player_id' => $item->player_id, 'event_id' => $item->category_event?->event_id,
                'registration_id' => $item->registration_id] as $field => $expected) {
                if (array_key_exists($field, $tx->getAttributes()) && $tx->{$field} !== null && (int) $tx->{$field} !== (int) $expected) $valid = false;
            }
            if ($tx->payment_status !== null && $tx->payment_status !== 'COMPLETE') $valid = false;
        }
        return json_decode(json_encode(['order' => $order->only(['id', 'user_id', 'total_fee', 'pay_status', 'payfast_paid', 'payfast_pf_payment_id',
            'payfast_amount_due', 'payfast_handed_off_at', 'wallet_reserved', 'wallet_debited', 'wallet_transaction_id', 'payment_method', 'status']),
            'item' => $item->only(['id', 'order_id', 'registration_id', 'player_id', 'user_id', 'category_event_id', 'item_price']),
            'receipt' => $valid ? $tx->only(['id', 'pf_payment_id', 'custom_int1', 'custom_int2', 'custom_int3', 'custom_int5', 'custom_int4',
                'category_event_id', 'player_id', 'event_id', 'registration_id', 'payment_status', 'is_test', 'archived_at', 'amount_gross', 'amount_fee', 'amount_net']) : null], JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }

    private function assertOtherAttemptsSafe(RegistrationOrder $order, CategoryEvent $category, int $target): array
    {
        $snapshots = [];
        $others = RegistrationOrderItems::where('category_event_id', $category->id)->whereIn('player_id', [$order->items->sole()->player_id, $target])
            ->where('order_id', '!=', $order->id)->lockForUpdate()->get();
        foreach ($others as $other) {
            $attempt = $other->order()->lockForUpdate()->firstOrFail();
            $this->ensure($attempt->status === 'cancelled' && ! $attempt->pay_status && ! $attempt->payfast_paid && ! $attempt->payfast_handed_off_at
                && ! $attempt->payfast_pf_payment_id && ! $attempt->wallet_debited && ! $attempt->wallet_transaction_id && (float) $attempt->wallet_reserved === 0.0
                && ! Transaction::where('custom_int5', $attempt->id)->exists(), 'Another checkout or received payment requires separate reconciliation.');
            $this->ensure(! \App\Models\TrialPaymentProof::where('order_id', $attempt->id)->whereIn('status', ['pending', 'verified'])->exists()
                && ! \App\Models\RegistrationManualReceipt::where('order_id', $attempt->id)->exists()
                && ! \App\Models\WalletTransaction::where('source_id', $attempt->id)->where('source_type', 'event_registration_wallet_payment')->exists(),
                'Another checkout has payment proof, a receipt or a wallet ledger debit.');
            $rows = CategoryEventRegistration::withTrashed()->where('registration_id', $other->registration_id)->lockForUpdate()->get();
            $this->ensure($rows->every(fn ($row) => $row->trashed() && ! $row->payment_status_id && ! $row->pf_transaction_id
                && ! $row->withdrawn_at && ! $row->refunded_at && ! $row->wallet_transaction_id), 'Another entry must remain safely closed and unpaid.');
            $this->assertNoCompetition($other->registration_id);
            $snapshots[] = ['order' => $attempt->getAttributes(), 'item' => $other->getAttributes(), 'entries' => $rows->map->getAttributes()->all()];
        }
        return $snapshots;
    }

    private function assertNoCompetition(int $registrationId): void
    {
        $this->ensure(! Registration::where('competition_replacement->source_registration_id', $registrationId)->exists(), 'Alternate participation prevents identity correction.');
        foreach (['draw_registrations' => 'registration_id', 'draw_group_registrations' => 'registration_id'] as $table => $column) {
            $this->ensure(! DB::table($table)->where($column, $registrationId)->exists(), 'Draw participation prevents identity correction.');
        }
        $this->ensure(! DB::table('fixtures')->where('registration1_id', $registrationId)->orWhere('registration2_id', $registrationId)->exists(), 'Fixture participation prevents identity correction.');
        $this->ensure(! DB::table('results')->where('winner_registration', $registrationId)->orWhere('loser_registration', $registrationId)->exists()
            && ! DB::table('category_results')->where('registration_id', $registrationId)->exists(), 'Recorded results prevent identity correction.');
    }

    private function confirmation(string $token): array
    {
        try { return json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw ValidationException::withMessages(['correction' => 'Invalid correction confirmation.']); }
    }

    private function cents(mixed $value): int { return (int) round((float) $value * 100); }
    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) throw ValidationException::withMessages(['correction' => $message]);
    }
}
