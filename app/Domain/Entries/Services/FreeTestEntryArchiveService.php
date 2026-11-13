<?php

namespace App\Domain\Entries\Services;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\EventExpense;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Transaction;
use App\Models\User;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/** Exact-record maintenance only. Retains source rows and does not settle or refund money. */
class FreeTestEntryArchiveService
{
    private const ACTION = 'Unused free admin test history archived';

    public function run(User $actor, array $ids, string $reason, string $evidence, ?string $fingerprint = null): array
    {
        $data = Validator::make($ids + ['reason' => trim($reason), 'evidence' => trim($evidence)], [
            'event_id' => 'required|integer|min:1', 'category_event_id' => 'required|integer|min:1',
            'player_id' => 'required|integer|min:1', 'entry_id' => 'required|integer|min:1',
            'receipt_id' => 'required|integer|min:1', 'reason' => 'required|string|min:15|max:500',
            'retained_entry_id' => 'sometimes|integer|min:1|different:entry_id',
            'evidence' => 'required|string|min:15|max:500',
        ])->validate();
        $ids = array_map('intval', array_intersect_key($data, array_flip([
            'event_id', 'category_event_id', 'player_id', 'entry_id', 'receipt_id', 'retained_entry_id',
        ])));
        if ($fingerprint !== null && ! preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) {
            $this->reject('Apply requires the exact fingerprint from a fresh preview.');
        }

        return DB::transaction(function () use ($actor, $ids, $data, $fingerprint): array {
            $actor = User::query()->lockForUpdate()->find($actor->id);
            if (! $actor?->hasRole('super-user')) $this->reject('An existing super-user actor is required.');
            $event = Event::query()->lockForUpdate()->find($ids['event_id']);
            if (! $event || ! $event->isIndividual() || $event->isMasters() || $event->isInterprovincialTrials()) {
                $this->reject('Only an ordinary individual event can use this test cleanup.');
            }
            // Normal entry creation locks its category. Lock every category in this
            // otherwise empty event before checking that no new entry appeared.
            $categories = CategoryEvent::where('event_id', $event->id)->orderBy('id')->lockForUpdate()->get();
            $category = $categories->firstWhere('id', $ids['category_event_id']);
            $entry = CategoryEventRegistration::withTrashed()->whereKey($ids['entry_id'])->lockForUpdate()->first();
            $receipt = Transaction::whereKey($ids['receipt_id'])->lockForUpdate()->first();
            $hasReceiptRegistration = Schema::hasColumn('transactions_pf', 'registration_id');
            $registration = $entry ? Registration::whereKey($entry->registration_id)->lockForUpdate()->first() : null;
            if (! $category || ! $entry || ! $receipt || ! $registration
                || (int) $entry->category_event_id !== (int) $category->id
                || (int) $receipt->event_id !== (int) $event->id
                || (int) $receipt->category_event_id !== (int) $category->id
                || ($hasReceiptRegistration && (int) $receipt->registration_id !== (int) $registration->id)
                || (int) $receipt->player_id !== $ids['player_id']) {
                $this->reject('The exact event, category, player, entry and receipt do not match.');
            }
            $retained = isset($ids['retained_entry_id'])
                ? $this->retainedFreeEntry($ids['retained_entry_id'], $entry, $category, $ids['player_id']) : null;
            $prior = Activity::where('log_name', 'maintenance')->where('description', self::ACTION)
                ->where('subject_type', $entry->getMorphClass())->where('subject_id', $entry->id)
                ->lockForUpdate()->first();
            if ($prior) {
                $properties = $prior->properties;
                $before = $properties->get('before');
                $currentLinks = DB::table('player_registrations')->where('registration_id', $registration->id)
                    ->lockForUpdate()->get()->map(fn ($row) => (array) $row)->all();
                if ($fingerprint === null || ! hash_equals((string) $properties->get('fingerprint'), $fingerprint)
                    || $properties->get('ids') !== $ids || (int) $prior->causer_id !== (int) $actor->id
                    || $properties->get('reason') !== $data['reason'] || $properties->get('evidence') !== $data['evidence']
                    || ($before['registration'] ?? null) !== $registration->getAttributes()
                    || ($before['player_links'] ?? null) !== $currentLinks
                    || ($before['retained'] ?? null) !== $retained
                    || $properties->get('after_entry') !== $entry->getAttributes()
                    || $properties->get('after_receipt') !== $receipt->getAttributes()) {
                    $this->reject('This entry has a prior archive; its exact archived state and original request must match.');
                }
                return ['preview' => false, 'already_archived' => true, 'ids' => $ids, 'audit_id' => $prior->id];
            }

            $category->setRelation('event', $event);
            $links = DB::table('player_registrations')->where('registration_id', $registration->id)->lockForUpdate()->get();
            if (! Player::whereKey($ids['player_id'])->exists() || $links->count() !== 1
                || (int) $links->first()->player_id !== $ids['player_id']
                || CategoryEventRegistration::withTrashed()->where('registration_id', $registration->id)->count() !== 1
                || CategoryEventRegistration::withTrashed()->whereIn('category_event_id', $categories->pluck('id'))->count() !== ($retained ? 2 : 1)
                || $registration->competition_replacement !== null) {
                $this->reject('The registration must be a single-player, unshared test in an otherwise empty event.');
            }
            if ($entry->trashed() || $entry->status !== 'withdrawn' || ! $entry->withdrawn_at
                || $entry->admin_payment_status !== 'unpaid' || (int) $entry->payment_status_id !== 1
                || $category->effectiveEntryFee() !== 0.0) {
                $this->reject('Only a withdrawn unpaid administrative test in a currently free category may be archived.');
            }
            foreach (['pf_transaction_id', 'wallet_transaction_id', 'payment_method', 'refunded_at', 'refund_method',
                'refund_waived_at', 'refund_waived_by', 'refund_waiver_reason', 'refund_account_name', 'refund_bank_name',
                'refund_account_number', 'refund_branch_code', 'refund_account_type'] as $field) {
                if (filled($entry->getRawOriginal($field))) $this->reject('Payment, refund or bank evidence prevents test cleanup.');
            }
            foreach (['refund_gross', 'refund_fee', 'refund_net'] as $field) {
                if ((float) $entry->$field !== 0.0) $this->reject('Refund amounts prevent test cleanup.');
            }
            if (! in_array($entry->refund_status, CategoryEventRegistration::NO_REFUND_STATUSES, true)) {
                $this->reject('Refund state prevents test cleanup.');
            }
            if ($receipt->transaction_type !== 'Registration' || $receipt->item_name !== 'Admin Entry'
                || $receipt->archived_at !== null || filled($receipt->pf_payment_id)
                || filled($receipt->custom_int5) || filled($receipt->custom_str5) || filled($receipt->payment_status)
                || (float) $receipt->amount_gross !== 0.0 || (float) $receipt->amount_net !== 0.0
                || (float) $receipt->amount_fee !== 0.0 || (bool) $receipt->is_test
                || (int) $receipt->custom_int3 !== (int) $event->id
                || (int) $receipt->custom_int4 !== (int) $entry->user_id) {
                $this->reject('The receipt must be the exact unused zero-money Admin Entry audit record.');
            }
            if (Transaction::where('event_id', $event->id)->count() !== 1
                || ($hasReceiptRegistration && Transaction::where('registration_id', $registration->id)->count() !== 1)) {
                $this->reject('Additional receipt history prevents test cleanup.');
            }
            $creation = Activity::where('log_name', 'registration')->where('subject_type', $entry->getMorphClass())
                ->where('subject_id', $entry->id)->where('description', 'Player registered offline by admin (private collection not reconciled)')
                ->lockForUpdate()->get();
            if ($creation->count() !== 1 || $creation->first()->properties->get('entry_source') !== 'admin_offline'
                || $creation->first()->properties->get('collection_status') !== 'unpaid'
                || $creation->first()->properties->get('operational_note_only') !== true
                || (int) $creation->first()->properties->get('canonical_payment_status_id') !== 1
                || $creation->first()->properties->get('transaction_amount') !== 0
                || $creation->first()->properties->get('financial_amount_recorded') !== false
                || $creation->first()->properties->get('reconciled_payment') !== false) {
                $this->reject('Canonical original unpaid-admin creation evidence is required.');
            }
            $activity = Activity::where('subject_type', $entry->getMorphClass())->where('subject_id', $entry->id)
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($activity as $record) {
                if ($record->log_name === 'refund'
                    || in_array($record->properties->get('collection_status'), ['paid_privately', 'paid'], true)
                    || in_array($record->properties->get('admin_payment_status_from'), ['paid'], true)
                    || in_array($record->properties->get('admin_payment_status_to'), ['paid'], true)) {
                    $this->reject('A privately paid collection note exists in the audit history.');
                }
            }
            $this->assertNoDependencies($event, $category, $entry, $retained);
            $expenses = EventExpense::where('event_id', $event->id)->orderBy('id')->lockForUpdate()->get();
            $this->assertSyntheticExpenses($expenses, $receipt);
            $before = ['ids' => $ids, 'actor_id' => (int) $actor->id, 'reason' => $data['reason'], 'evidence' => $data['evidence'],
                'receipt_registration_link' => $hasReceiptRegistration ? 'registration_id' : 'unique_event_category_player',
                'retained' => $retained,
                'event' => $event->only(['id', 'eventType', 'entryFee', 'cape_tennis_fee', 'published', 'signUp', 'updated_at']),
                'category' => $category->getAttributes(), 'entry' => $entry->getAttributes(), 'receipt' => $receipt->getAttributes(),
                'registration' => $registration->getAttributes(), 'player_links' => $links->map(fn ($row) => (array) $row)->all(),
                'source_audit' => $activity->map(fn ($row) => $row->only(['id', 'updated_at', 'description', 'properties']))->all(),
                'system_expenses' => $expenses->map(fn ($row) => $row->getAttributes())->all()];
            $current = hash('sha256', json_encode($before, JSON_THROW_ON_ERROR));
            if ($fingerprint === null) {
                return ['preview' => true, 'ids' => $ids, 'fingerprint' => $current,
                    'cape_fee_liability_to_archive' => (float) $receipt->cape_tennis_fee,
                    'system_expense_ids' => $expenses->pluck('id')->all(),
                    'retained_entry_id' => $retained['entry']['id'] ?? null,
                    'effect' => 'Soft-delete this withdrawn test entry; archive its zero-money admin receipt; clear only its generated fee projections. Keep player, registration, identity links and maintenance audit.'];
            }
            if (! hash_equals($current, $fingerprint)) $this->reject('The reviewed test state changed. Obtain a new preview.');

            return FinanceMutationScope::run(['free_test_archive', 'registration_payment_state_write', 'payment_transaction_write'], function () use ($event, $entry, $receipt, $actor, $before, $current, $ids, $category): array {
                $archivedAt = now()->format('Y-m-d H:i:s');
                $entry->delete();
                $ledger = app(FinancialLedgerService::class);
                $ledger->archiveFreeTestReceipt($event, $receipt, $archivedAt);
                $ledger->clearArchivedTestSystemExpenses($event);
                if (isset($ids['retained_entry_id'])
                    && $before['retained'] !== $this->retainedFreeEntry($ids['retained_entry_id'], $entry, $category, $ids['player_id'])) {
                    $this->reject('The retained free entry changed during cleanup.');
                }
                $audit = activity('maintenance')->performedOn($entry)->causedBy($actor)
                    ->withProperties(['ids' => $ids, 'fingerprint' => $current, 'reason' => $before['reason'], 'evidence' => $before['evidence'],
                        'before' => $before, 'after_entry' => $entry->fresh()->getAttributes(),
                        'after_receipt' => $receipt->fresh()->getAttributes(), 'archived_at' => $archivedAt])
                    ->log(self::ACTION);
                return ['preview' => false, 'already_archived' => false, 'ids' => $ids, 'audit_id' => $audit->id];
            });
        });
    }

    /** The sole exception to an empty event is one explicitly reviewed frontend free entry. */
    private function retainedFreeEntry(int $id, CategoryEventRegistration $target, CategoryEvent $category, int $playerId): array
    {
        $entry = CategoryEventRegistration::withTrashed()->whereKey($id)->lockForUpdate()->first();
        if (! $entry || $entry->id === $target->id || (int) $entry->category_event_id !== (int) $category->id
            || (int) $entry->registration_id === (int) $target->registration_id
            || (int) $entry->user_id !== (int) $target->user_id || $entry->trashed()
            || $entry->status !== 'active' || (int) $entry->payment_status_id !== 1
            || ! in_array($entry->payment_method, [null, '', 'free'], true)
            || $entry->admin_payment_status !== null) {
            $this->reject('The retained entry must be a separate active frontend free entry for the exact player, category and payer.');
        }
        foreach (['withdrawn_at', 'withdrawn_by', 'pf_transaction_id', 'wallet_transaction_id', 'refunded_at', 'refund_method',
            'refund_waived_at', 'refund_waived_by', 'refund_waiver_reason', 'refund_account_name', 'refund_bank_name',
            'refund_account_number', 'refund_branch_code', 'refund_account_type'] as $field) {
            if (filled($entry->getRawOriginal($field))) $this->reject('The retained entry has withdrawal, payment or refund evidence.');
        }
        if (! in_array($entry->refund_status, CategoryEventRegistration::NO_REFUND_STATUSES, true)
            || (float) $entry->refund_gross !== 0.0 || (float) $entry->refund_fee !== 0.0 || (float) $entry->refund_net !== 0.0) {
            $this->reject('The retained entry has refund state.');
        }
        $registration = Registration::whereKey($entry->registration_id)->lockForUpdate()->first();
        $links = DB::table('player_registrations')->where('registration_id', $entry->registration_id)->lockForUpdate()->get();
        $items = DB::table('registration_order_items')->where('registration_id', $entry->registration_id)->orderBy('id')->lockForUpdate()->get();
        if (! $registration || $registration->competition_replacement !== null || $links->count() !== 1
            || (int) $links->first()->player_id !== $playerId || $items->count() !== 1
            || CategoryEventRegistration::withTrashed()->where('registration_id', $registration->id)->count() !== 1) {
            $this->reject('The retained registration must be unshared with one exact player and one free order item.');
        }
        $item = $items->first();
        $order = \App\Models\RegistrationOrder::whereKey($item->order_id)->lockForUpdate()->first();
        if (! $order || (int) $order->user_id !== (int) $entry->user_id || $order->status !== 'completed'
            || ! $order->pay_status || $order->payment_method !== 'free' || $order->payfast_paid || $order->wallet_debited
            || (float) $order->total_fee !== 0.0 || (float) $order->wallet_reserved !== 0.0
            || (float) $order->payfast_amount_due !== 0.0 || filled($order->payfast_pf_payment_id)
            || $order->payfast_handed_off_at !== null || $order->wallet_transaction_id !== null
            || (int) $item->player_id !== $playerId || (int) $item->category_event_id !== (int) $category->id
            || (int) $item->user_id !== (int) $entry->user_id || (float) $item->item_price !== 0.0
            || $order->items()->count() !== 1) {
            $this->reject('The retained order must be a completed, wholly free frontend order with no payment or reservation evidence.');
        }
        foreach (['registration_manual_receipts', 'trial_payment_proofs', 'trial_participation_receipts',
            'trial_participation_proofs'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('order_id', $order->id)->lockForUpdate()->first()) {
                $this->reject('The retained order has manual payment or proof evidence.');
            }
        }
        if (Schema::hasTable('registration_payment_recoveries')
            && DB::table('registration_payment_recoveries')->where('registration_order_id', $order->id)->lockForUpdate()->first()) {
            $this->reject('The retained order has payment recovery evidence.');
        }
        $provider = Transaction::where(function ($query) use ($order, $registration) {
            $query->where(fn ($match) => $match->where('custom_int5', $order->id)
                ->where(fn ($kind) => $kind->whereNull('custom_str5')->orWhere('custom_str5', '')));
            if (Schema::hasColumn('transactions_pf', 'registration_id')) $query->orWhere('registration_id', $registration->id);
        })->lockForUpdate()->first();
        $wallet = DB::table('wallet_transactions')->where(function ($query) use ($order, $entry) {
            $query->where(fn ($payment) => $payment->where('source_type', 'event_registration_wallet_payment')->where('source_id', $order->id))
                ->orWhere(fn ($refund) => $refund->whereIn('source_type', ['admin_full_refund', 'admin_refund', 'event_registration_refund'])
                    ->where('source_id', $entry->id));
        })->lockForUpdate()->first();
        if ($provider || $wallet) $this->reject('The retained order has provider or wallet history.');
        return ['entry' => $entry->getAttributes(), 'registration' => $registration->getAttributes(),
            'player_links' => $links->map(fn ($row) => (array) $row)->all(), 'order' => $order->getAttributes(),
            'items' => $items->map(fn ($row) => (array) $row)->all()];
    }

    private function assertNoDependencies(Event $event, CategoryEvent $category, CategoryEventRegistration $entry, ?array $retained = null): void
    {
        $registrationId = (int) $entry->registration_id;
        $registrationIds = $retained ? [$registrationId, (int) $retained['entry']['registration_id']] : [$registrationId];
        $references = [
            'registration_order_items' => ['registration_id' => $registrationId, 'category_event_id' => $category->id],
            'draw_registrations' => ['registration_id' => $registrationId],
            'draw_group_registrations' => ['registration_id' => $registrationId],
            'registration_sub_draws' => ['registration_id' => $registrationId],
            'fixtures' => ['registration1_id' => $registrationId, 'registration2_id' => $registrationId,
                'winner_registration' => $registrationId, 'loser_registration' => $registrationId],
            'fixture_results' => ['winner_registration' => $registrationId, 'loser_registration' => $registrationId],
            'practice_fixtures' => ['registration1_id' => $registrationId, 'registration2_id' => $registrationId],
            'practice_results' => ['winner_registration' => $registrationId, 'loser_registration' => $registrationId],
            'positions' => ['registration_id' => $registrationId], 'category_results' => ['registration_id' => $registrationId],
            'withdrawals' => ['registration_id' => $registrationId], 'category_adjustments' => ['entry_id' => $entry->id],
            'trial_selection_reviews' => ['entry_id' => $entry->id],
            'masters_invitations' => ['registration_id' => $registrationId],
            'interprovincial_trial_invitations' => ['registration_id' => $registrationId],
            'draws' => ['event_id' => $event->id], 'team_payment_orders' => ['event_id' => $event->id],
            'trial_participations' => ['event_id' => $event->id], 'team_selection_invitations' => ['event_id' => $event->id],
            'event_payouts' => ['event_id' => $event->id], 'registration_manual_receipts' => ['event_id' => $event->id],
            'trial_payment_proofs' => ['event_id' => $event->id],
            'trial_participation_receipts' => ['event_id' => $event->id], 'trial_provider_refund_attempts' => ['event_id' => $event->id],
            'clothing_orders' => ['event_id' => $event->id],
        ];
        foreach ($references as $table => $columns) {
            if (! Schema::hasTable($table)) continue;
            $available = array_flip(Schema::getColumnListing($table));
            $columns = array_intersect_key($columns, $available);
            if (! $columns) continue;
            foreach ($columns as $column => $value) {
                if (in_array($column, ['registration_id', 'registration1_id', 'registration2_id', 'winner_registration', 'loser_registration'], true)) {
                    $columns[$column] = $registrationIds;
                } elseif ($column === 'entry_id' && $retained) {
                    $columns[$column] = [$entry->id, $retained['entry']['id']];
                }
            }
            $query = DB::table($table)->where(function ($query) use ($columns) {
                foreach ($columns as $column => $value) {
                    is_array($value) ? $query->orWhereIn($column, $value) : $query->orWhere($column, $value);
                }
            });
            if ($table === 'registration_order_items' && $retained) {
                $query->whereNotIn('id', array_column($retained['items'], 'id'));
            }
            if ($query->lockForUpdate()->first()) {
                $this->reject('Related '.$table.' evidence prevents test cleanup.');
            }
        }
        if (DB::table('wallet_transactions')->where(function ($query) use ($event, $entry) {
            $query->where('meta->event_id', $event->id)
                ->orWhere(fn ($source) => $source->whereIn('source_type', ['admin_full_refund', 'admin_refund', 'event_registration_refund'])
                    ->where('source_id', $entry->id));
        })->lockForUpdate()->first()) {
            $this->reject('Event wallet evidence prevents test cleanup.');
        }
    }

    private function assertSyntheticExpenses($expenses, Transaction $receipt): void
    {
        foreach ($expenses as $expense) {
            $expected = match ($expense->expense_type) {
                'cape_tennis_fee' => ['Cape Tennis fee (auto-synced)', abs((float) $receipt->cape_tennis_fee)],
                'payfast' => ['PayFast fees (auto-synced)', 0.0],
                default => null,
            };
            if ($expected === null || $expense->description !== $expected[0]
                || round((float) $expense->amount, 2) !== $expected[1]
                || round($expense->calculatedAmount(), 2) !== $expected[1]
                || $expense->paid_by_convenor_id || $expense->reimbursed_at || $expense->reimbursed_by
                || $expense->approved_at || $expense->approved_by || filled($expense->receipt_path)
                || filled($expense->recipient_name) || filled($expense->convenor_name) || $expense->budget_amount !== null) {
                $this->reject('An expense is not the untouched synthetic projection of this exact admin test.');
            }
        }
        if ($expenses->pluck('expense_type')->duplicates()->isNotEmpty()) {
            $this->reject('Duplicate system fee projections require separate review.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['archive' => $message]);
    }
}
