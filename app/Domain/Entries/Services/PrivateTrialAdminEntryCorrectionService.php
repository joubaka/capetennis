<?php

namespace App\Domain\Entries\Services;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\{CategoryEvent, CategoryEventRegistration, Event, EventNomination, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, Registration, Transaction, User};
use App\Services\InterprovincialTrials\NominationReactivationService;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\{Crypt, DB, Schema, Validator};
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/** An erroneous operational admin entry is retired, never refunded or rewritten as unpaid. */
class PrivateTrialAdminEntryCorrectionService
{
    private const ACTION = 'Erroneous private trial admin entry corrected for normal registration';

    public function run(User $actor, array $ids, string $reason, bool $noFundsCollected, ?string $confirmation = null): array
    {
        $data = Validator::make($ids + ['reason' => trim($reason)], [
            'event_id' => 'required|integer|min:1', 'category_event_id' => 'required|integer|min:1',
            'player_id' => 'required|integer|min:1', 'nomination_id' => 'required|integer|min:1',
            'entry_id' => 'required|integer|min:1', 'receipt_id' => 'required|integer|min:1',
            'reason' => 'required|string|min:15|max:1000',
        ])->validate();
        $ids = array_map('intval', array_intersect_key($data, array_flip(['event_id', 'category_event_id', 'player_id', 'nomination_id', 'entry_id', 'receipt_id'])));
        $this->ensure($noFundsCollected, 'Explicitly confirm that no private funds were collected for the erroneous entry.');
        abort_unless($actor->fresh()->hasRole('super-user'), 403);
        $approved = $confirmation === null ? null : $this->confirmation($confirmation, $actor, $ids, $data['reason']);
        return DB::transaction(function () use ($actor, $ids, $data, $approved) {
            $event = Event::with('eventTypeModel')->lockForUpdate()->findOrFail($ids['event_id']);
            $categories = CategoryEvent::where('event_id', $event->id)->orderBy('id')->lockForUpdate()->get();
            $category = $categories->firstWhere('id', $ids['category_event_id']);
            $nomination = EventNomination::lockForUpdate()->findOrFail($ids['nomination_id']);
            $entry = CategoryEventRegistration::withTrashed()->lockForUpdate()->findOrFail($ids['entry_id']);
            $receipt = Transaction::lockForUpdate()->findOrFail($ids['receipt_id']);
            abort_unless($event->isInterprovincialTrials() && $category
                && (int) $nomination->event_id === (int) $event->id && (int) $nomination->category_event_id === (int) $category->id
                && (int) $nomination->player_id === $ids['player_id'] && (int) $entry->category_event_id === (int) $category->id, 404);
            $prior = Activity::where('subject_type', $entry->getMorphClass())->where('subject_id', $entry->id)->where('description', self::ACTION)->lockForUpdate()->first();
            if ($prior) {
                $this->ensure($approved && (int) $prior->causer_id === (int) $actor->id && $prior->properties->get('ids') === $ids
                    && $prior->properties->get('reason') === $data['reason'] && $prior->properties->get('fingerprint') === $approved['fingerprint']
                    && $prior->properties->get('after') === $this->postState($entry, $receipt, $nomination, (int) $prior->properties->get('new_invitation_id')),
                    'A prior correction exists; only its unchanged original confirmation can be replayed.');
                return ['preview' => false, 'already_corrected' => true, 'ids' => $ids];
            }
            $this->ensure(!$category->isLocked() && $category->nominations_published && $event->published && $event->hasOpenRegistrationLifecycle()
                && (int) $event->signUp === 1 && (!$event->registrationClosesAt() || now()->lte($event->registrationClosesAt()->endOfDay())),
                'Normal nomination registration must be open before retiring the erroneous admin entry.');
            $review = app(NominationReactivationService::class)->reviewPrivateAdminCorrection($event, $category, $nomination, $actor);
            $this->ensure((int) $review['entry']['id'] === $entry->id && (int) $review['history']['entry_receipts'][0]['id'] === $receipt->id
                && $receipt->archived_at === null && !$receipt->payment_status, 'The exact unused private entry and zero-money receipt must match.');
            foreach (['payment_method', 'refund_method', 'refund_account_name', 'refund_bank_name', 'refund_account_number', 'refund_waiver_reason'] as $field) {
                $this->ensure(blank($entry->getRawOriginal($field)), 'Payment or refund evidence prevents this correction.');
            }
            $registration = Registration::lockForUpdate()->findOrFail($entry->registration_id);
            $this->ensure($registration->competition_replacement === null, 'A competition replacement cannot use this correction.');
            $this->assertNoDependencies($event, $entry, $ids['player_id']);
            $invitations = InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->orderBy('id')->lockForUpdate()->get();
            $current = $invitations->last(fn ($row) => $row->status !== 'prepared') ?? $invitations->last();
            $this->ensure($current && $current->status === 'paid_confirmed' && (int) $current->registration_id === (int) $entry->registration_id && !$current->order_id
                && $invitations->every(fn ($row) => (int) $row->event_id === (int) $event->id && (int) $row->category_event_id === (int) $category->id
                    && (int) $row->player_id === $ids['player_id'] && !$row->order_id && !$row->withdrawn_at
                    && (!$row->registration_id || (int) $row->registration_id === (int) $entry->registration_id)), 'The nomination lifecycle needs separate review.');
            $before = ['actor_id' => $actor->id, 'ids' => $ids, 'reason' => $data['reason'], 'no_funds_collected' => true,
                'event' => $event->getAttributes(), 'category' => $category->getAttributes(), 'nomination' => $nomination->getAttributes(),
                'registration' => $registration->getAttributes(), 'history' => $review['history'], 'invitations' => $invitations->map->getAttributes()->all()];
            $fingerprint = hash('sha256', json_encode($before, JSON_THROW_ON_ERROR));
            if (!$approved) {
                return ['preview' => true, 'ids' => $ids, 'cape_fee_liability_to_archive' => (float) $receipt->cape_tennis_fee,
                    'effect' => 'Retire only the erroneous private admin entry and archive its zero-money receipt. Preserve history and fee snapshot; restore normal nomination checkout without refund, withdrawal or email.',
                    'confirmation' => Crypt::encryptString(json_encode(['fingerprint' => $fingerprint, 'actor_id' => $actor->id, 'ids' => $ids,
                        'reason' => $data['reason'], 'expires' => now()->addMinutes(15)->timestamp], JSON_THROW_ON_ERROR))];
            }
            $this->ensure(hash_equals($fingerprint, $approved['fingerprint']), 'Reviewed entry or lifecycle changed. Preview again.');
            return FinanceMutationScope::run(['private_trial_admin_correction', 'registration_payment_state_write', 'payment_transaction_write'], function () use ($actor, $ids, $data, $fingerprint, $before, $event, $category, $nomination, $entry, $receipt) {
                app(EntryService::class)->retireErroneousPrivateTrialEntry($entry, $actor);
                app(FinancialLedgerService::class)->archiveErroneousPrivateAdminReceipt($event, $receipt, now()->format('Y-m-d H:i:s'));
                $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $event->id, 'status' => 'draft', 'snapshot_hash' => $fingerprint, 'created_by_user_id' => $actor->id]);
                $marker = InterprovincialTrialInvitation::create(['event_id' => $event->id, 'category_event_id' => $category->id,
                    'nomination_id' => $nomination->id, 'player_id' => $ids['player_id'], 'batch_id' => $batch->id, 'status' => 'open_registration']);
                activity('interprovincial-trials')->performedOn($entry)->causedBy($actor)->withProperties([
                    'ids' => $ids, 'reason' => $data['reason'], 'no_funds_collected' => true, 'fingerprint' => $fingerprint,
                    'before' => $before, 'new_invitation_id' => $marker->id, 'after' => $this->postState($entry, $receipt, $nomination, $marker->id),
                ])->log(self::ACTION);
                return ['preview' => false, 'already_corrected' => false, 'ids' => $ids, 'new_invitation_id' => $marker->id];
            });
        }, 3);
    }

    /** Preserve normal paid/pending precedence; only this exact correction may supersede its own private marker. */
    /** Exclude only the exact historical operational marker retired by this reviewed correction. */
    public function isRetiredPrivateAdminMarker(InterprovincialTrialInvitation $historical): bool
    {
        if ($historical->status !== 'paid_confirmed' || $historical->order_id || !$historical->registration_id) return false;
        $entry = CategoryEventRegistration::withTrashed()->where('registration_id', $historical->registration_id)
            ->where('category_event_id', $historical->category_event_id)->first();
        if (!$entry || !$entry->trashed() || $entry->status !== 'cancelled_admin_error') return false;
        $audit = Activity::where('subject_type', $entry->getMorphClass())->where('subject_id', $entry->id)->where('description', self::ACTION)->first();
        if (!$audit) return false;
        $ids = $audit->properties->get('ids');
        $after = $audit->properties->get('after');
        $marker = InterprovincialTrialInvitation::find((int) $audit->properties->get('new_invitation_id'));
        $receipt = Transaction::find($ids['receipt_id'] ?? 0);
        return ($ids['event_id'] ?? 0) === (int) $historical->event_id && ($ids['category_event_id'] ?? 0) === (int) $historical->category_event_id
            && ($ids['nomination_id'] ?? 0) === (int) $historical->nomination_id && ($ids['player_id'] ?? 0) === (int) $historical->player_id
            && $receipt && $receipt->archived_at && ($after['entry'] ?? null) === $entry->getAttributes()
            && ($after['receipt'] ?? null) === $receipt->getAttributes() && $marker && $marker->id > $historical->id
            && array_intersect_key($marker->getAttributes(), array_flip(['id', 'batch_id', 'event_id', 'category_event_id', 'nomination_id', 'player_id', 'created_at']))
                === array_intersect_key($after['marker'] ?? [], array_flip(['id', 'batch_id', 'event_id', 'category_event_id', 'nomination_id', 'player_id', 'created_at']))
            && collect($audit->properties->get('before')['invitations'] ?? [])->contains(fn ($row) => $row === $historical->getAttributes());
    }
    private function postState(CategoryEventRegistration $entry, Transaction $receipt, EventNomination $nomination, int $markerId): array
    {
        return ['entry' => CategoryEventRegistration::withTrashed()->findOrFail($entry->id)->getAttributes(), 'receipt' => $receipt->fresh()->getAttributes(),
            'registration' => Registration::findOrFail($entry->registration_id)->getAttributes(),
            'player_links' => DB::table('player_registrations')->where('registration_id', $entry->registration_id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'invitations' => InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->orderBy('id')->get()->map->getAttributes()->all(),
            'nomination' => $nomination->fresh()->getAttributes(), 'marker' => InterprovincialTrialInvitation::findOrFail($markerId)->getAttributes()];
    }

    private function confirmation(string $token, User $actor, array $ids, string $reason): array
    {
        try { $approved = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw ValidationException::withMessages(['correction' => 'Invalid correction confirmation.']); }
        $this->ensure(($approved['actor_id'] ?? null) === $actor->id && ($approved['ids'] ?? null) === $ids
            && ($approved['reason'] ?? null) === $reason && ($approved['expires'] ?? 0) >= now()->timestamp, 'Correction confirmation changed or expired.');
        return $approved;
    }

    private function assertNoDependencies(Event $event, CategoryEventRegistration $entry, int $playerId): void
    {
        $reg = (int) $entry->registration_id;
        $references = [
            'draw_registrations' => ['registration_id' => $reg], 'draw_group_registrations' => ['registration_id' => $reg],
            'registration_sub_draws' => ['registration_id' => $reg], 'fixtures' => ['registration1_id' => $reg, 'registration2_id' => $reg, 'winner_registration' => $reg, 'loser_registration' => $reg],
            'fixture_results' => ['winner_registration' => $reg, 'loser_registration' => $reg], 'positions' => ['registration_id' => $reg],
            'category_results' => ['registration_id' => $reg], 'withdrawals' => ['registration_id' => $reg],
            'category_adjustments' => ['entry_id' => $entry->id], 'individual_beneficiary_corrections' => ['entry_id' => $entry->id],
            'individual_provider_refund_attempts' => ['entry_id' => $entry->id], 'trial_selection_reviews' => ['entry_id' => $entry->id],
            'masters_invitations' => ['registration_id' => $reg], 'event_payouts' => ['event_id' => $event->id],
        ];
        foreach ($references as $table => $columns) {
            if (!Schema::hasTable($table)) continue;
            foreach ($columns as $column => $value) {
                if (Schema::hasColumn($table, $column)) $this->ensure(!DB::table($table)->where($column, $value)->lockForUpdate()->exists(), 'Competition, adjustment, refund or payout dependencies prevent this correction.');
            }
        }
        foreach (['trial_participations', 'team_selection_invitations'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumns($table, ['event_id', 'player_id'])) {
                $this->ensure(!DB::table($table)->where('event_id', $event->id)->where('player_id', $playerId)->lockForUpdate()->exists(), 'Team selection dependencies prevent this correction.');
            }
        }
        $this->ensure(!DB::table('wallet_transactions')->whereIn('source_type', ['admin_full_refund', 'admin_refund', 'event_registration_refund'])
            ->where('source_id', $entry->id)->lockForUpdate()->exists(), 'Wallet refund evidence prevents this correction.');
    }

    private function ensure(bool $condition, string $message): void
    {
        if (!$condition) throw ValidationException::withMessages(['correction' => $message]);
    }
}