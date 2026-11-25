<?php

namespace App\Services\InterprovincialTrials;

use App\Models\{CategoryEvent, CategoryEventRegistration, Event, EventNomination, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, Player, RegistrationOrderItems, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Re-add an unpaid nomination without rewriting its earlier response or deletion evidence. */
class NominationReactivationService
{
    public function revision(Event $event, CategoryEvent $category, int $id): string
    {
        return hash('sha256', json_encode($this->state($event, $category, $id), JSON_THROW_ON_ERROR));
    }

    public function reactivate(Event $event, CategoryEvent $category, int $id, User $actor, string $revision, string $reason): void
    {
        abort_unless($actor->hasRole('super-user') || ($actor->hasRole('admin') && $actor->is_event_admin($event->id)), 403);
        abort_unless($event->isInterprovincialTrials(), 404);
        DB::transaction(function () use ($event, $category, $id, $actor, $revision, $reason): void {
            $event = Event::with('eventTypeModel')->lockForUpdate()->findOrFail($event->id);
            $category = CategoryEvent::lockForUpdate()->findOrFail($category->id);
            abort_unless((int) $category->event_id === (int) $event->id && $event->isInterprovincialTrials(), 404);
            EventNomination::whereKey($id)->lockForUpdate()->first();
            $invitations = InterprovincialTrialInvitation::where('nomination_id', $id)->orderBy('id')->lockForUpdate()->get();
            $state = $this->state($event, $category, $id);
            if ($state['audit_id']) {
                $this->ensure(\App\Support\Audit\AuditIntegrity::verify((array) DB::table('audit_events')->find($state['audit_id'])), 'The deletion audit integrity needs review before this nomination can be re-added.');
            }
            $this->ensure(hash_equals($this->revision($event, $category, $id), $revision), 'The nomination or response changed. Reload the list and review it again.');
            $this->ensure(! $category->isLocked(), 'Unlock the category before re-adding a nomination.');
            $snapshot = $state['nomination'];
            $playerId = $snapshot['player_id'] ?? null;
            $this->ensure((is_int($playerId) || (is_string($playerId) && ctype_digit($playerId))) && (int) $playerId > 0
                && Player::whereKey($playerId)->exists(), 'A current player profile is required to re-add this nomination.');
            $playerId = (int) $playerId;
            $this->ensure($invitations->every(fn ($row) => (int) $row->event_id === (int) $event->id
                && (int) $row->category_event_id === (int) $category->id && (int) $row->player_id === $playerId), 'Invitation identity needs review.');
            $this->ensure($invitations->every(fn ($row) => ! $row->order_id && ! $row->registration_id && ! $row->accepted_at && ! $row->paid_at && ! $row->withdrawn_at
                && in_array($row->status, ['prepared', 'sent', 'failed', 'declined', 'admin_declined', 'open_registration', 'cancelled'], true)), 'Use Entries to resolve registration, payment or withdrawal history before re-adding this player.');
            $current = $invitations->last(fn ($row) => $row->status !== 'prepared') ?? $invitations->last();
            $nomination = EventNomination::find($id);
            $this->ensure(! $nomination || $nomination->archived_at || in_array($current?->status, ['declined', 'admin_declined'], true), 'This nomination is already active.');
            $history = $this->history($event, $playerId, true);
            $this->ensure($history === $state['history'], 'Checkout history changed. Reload the nomination list and review it again.');
            $privateEntry = $this->existingPrivateEntry($history, $category, $playerId);
            if (! $privateEntry) $this->assertClosedUnpaidHistory($history, $category, $playerId);
            $this->ensure(! EventNomination::where('category_event_id', $category->id)->where('player_id', $playerId)->where('id', '!=', $id)->exists(), 'This player already has another nomination in this category.');
            if (! $nomination) {
                $nomination = new EventNomination();
                // Never replay arbitrary audit attributes, archived state or financial references.
                $nomination->forceFill(['id' => $id, 'event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $playerId]);
            }
            $nomination->forceFill(['archived_at' => null, 'archived_by' => null, 'archive_reason' => null])->save();
            $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $event->id, 'status' => 'draft',
                'snapshot_hash' => hash('sha256', 'reactivate:'.$id.':'.$revision), 'created_by_user_id' => $actor->id]);
            InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $event->id, 'category_event_id' => $category->id,
                'nomination_id' => $id, 'player_id' => $playerId, 'status' => $privateEntry ? 'paid_confirmed' : 'open_registration',
                'registration_id' => $privateEntry['registration_id'] ?? null]);
            activity('interprovincial-trials')->performedOn($nomination)->causedBy($actor)->withProperties([
                'event_id' => $event->id, 'category_event_id' => $category->id, 'reason' => trim($reason),
                'source_audit_id' => $state['audit_id'], 'before' => $snapshot, 'retained_invitation_ids' => $invitations->pluck('id')->all(),
                'reconnected_existing_private_entry_id' => $privateEntry['id'] ?? null,
                'reconciled_payment' => false,
                'receipt_identity' => $history['receipt_identity'] ?? 'none',
                'retained_cancelled_order_ids' => array_column($history['orders'], 'id'),
                'retained_deleted_entry_ids' => collect($history['entries'])->filter(fn ($entry) => $entry['deleted_at'] !== null)->pluck('id')->all(),
            ])->log('Nomination reactivated by event administrator');
        }, 3);
    }

    private function state(Event $event, CategoryEvent $category, int $id): array
    {
        abort_unless((int) $category->event_id === (int) $event->id, 404);
        $nomination = EventNomination::find($id);
        $audit = null;
        if ($nomination) {
            abort_unless((int) $nomination->event_id === (int) $event->id && (int) $nomination->category_event_id === (int) $category->id, 404);
            $snapshot = $nomination->getAttributes();
        } else {
            $removed = app(NominationHistory::class)->removedQuery($event)->where('a.subject_id', (string) $id)->where('c.id', $category->id)->first();
            abort_unless($removed, 404);
            $audit = DB::table('audit_events')->find($removed->audit_id);
            $snapshot = json_decode($audit->before, true, flags: JSON_THROW_ON_ERROR);
        }
        return ['nomination' => $snapshot, 'audit_id' => $audit?->id, 'category' => CategoryEvent::findOrFail($category->id)->only(['id', 'event_id', 'nominations_published', 'locked_at']),
            'history' => $this->history($event, (int) ($snapshot['player_id'] ?? 0)),
            'invitations' => InterprovincialTrialInvitation::where('nomination_id', $id)->orderBy('id')->get()->map->getAttributes()->all()];
    }

    /** The retained cart is evidence, not participation to restore or an order to reopen. */
    private function history(Event $event, int $playerId, bool $lock = false): array
    {
        $categories = CategoryEvent::where('event_id', $event->id)->pluck('id');
        $entries = CategoryEventRegistration::withTrashed()->whereIn('category_event_id', $categories)
            ->whereHas('registration.players', fn ($query) => $query->where('players.id', $playerId))->orderBy('id');
        $items = RegistrationOrderItems::whereIn('category_event_id', $categories)->where('player_id', $playerId)->orderBy('id');
        if ($lock) { $entries->lockForUpdate(); $items->lockForUpdate(); }
        $entries = $entries->get();
        $items = $items->get();
        if ($entries->isEmpty() && $items->isEmpty()) return array_fill_keys(['entries', 'orders', 'items', 'manual_receipts', 'recoveries', 'transactions', 'wallet', 'handoffs', 'proofs', 'registration_players', 'entry_registrations', 'entry_receipts', 'entry_audits', 'entry_items', 'entry_invitations'], []);
        $registrationIds = $items->pluck('registration_id')->merge($entries->pluck('registration_id'))->unique();
        $orderIds = $items->pluck('order_id')->unique();
        $receiptIdentity = \Illuminate\Support\Facades\Schema::hasColumns('transactions_pf', ['event_id', 'category_event_id', 'player_id'])
            ? (\Illuminate\Support\Facades\Schema::hasColumn('transactions_pf', 'registration_id') ? 'registration_id' : 'legacy_tuple')
            : 'unsupported';
        $receiptQuery = match ($receiptIdentity) {
            'registration_id' => DB::table('transactions_pf')->whereIn('registration_id', $registrationIds),
            // All receipts for the numeric event/player tuple are reviewed, not a convenient matching subset.
            'legacy_tuple' => DB::table('transactions_pf')->where('event_id', $event->id)->where('player_id', $playerId),
            default => DB::table('transactions_pf')->whereRaw('1 = 0'),
        };
        $queries = [
            'orders' => DB::table('registration_orders')->whereIn('id', $orderIds),
            'proofs' => DB::table('trial_payment_proofs')->whereIn('order_id', $orderIds),
            'items' => DB::table('registration_order_items')->whereIn('order_id', $orderIds),

            'manual_receipts' => DB::table('registration_manual_receipts')->whereIn('order_id', $orderIds),
            'recoveries' => DB::table('registration_payment_recoveries')->whereIn('registration_order_id', $orderIds),
            'transactions' => DB::table((new \App\Models\Transaction())->getTable())->whereIn('custom_int5', $orderIds)
                ->where(fn ($query) => $query->whereNull('custom_str5')->orWhere('custom_str5', '')),
            'wallet' => DB::table('wallet_transactions')->whereIn('source_id', $orderIds)->where('source_type', 'event_registration_wallet_payment'),
            'handoffs' => DB::table('activity_log')->where('subject_type', \App\Models\RegistrationOrder::class)->whereIn('subject_id', $orderIds)
                ->whereIn('description', ['Expired unresolved PayFast handoff retained for reconciliation', 'registration checkout handed off to PayFast', 'supervised unresolved PayFast handoff released', 'supervised unresolved PayFast checkout cancelled']),
            'registration_players' => DB::table('player_registrations')->whereIn('registration_id', $registrationIds),
            'entry_registrations' => DB::table('category_event_registrations')->whereIn('registration_id', $registrationIds),
            'entry_receipts' => $receiptQuery,
            'entry_invitations' => DB::table('interprovincial_trial_invitations')->whereIn('registration_id', $registrationIds),
            'entry_audits' => DB::table('activity_log')->where('subject_type', CategoryEventRegistration::class)->whereIn('subject_id', $entries->pluck('id'))
                ->where('description', 'Player registered offline by admin (private collection not reconciled)'),
            'entry_items' => DB::table('registration_order_items')->whereIn('registration_id', $registrationIds),
        ];
        $history = ['entries' => $entries->map->getAttributes()->all(), 'receipt_identity' => $receiptIdentity];
        foreach ($queries as $key => $query) {
            if ($lock) $query->lockForUpdate();
            // Stable row ordering keeps the review hash independent of query plans.
            $history[$key] = $query->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        return $history;
    }

    /** Reconnect an existing operational private entry; never manufacture a reconciled payment. */
    private function existingPrivateEntry(array $history, CategoryEvent $category, int $playerId): ?array
    {
        $candidates = collect($history['entries'])->filter(fn ($entry) => !$entry['deleted_at'] && $entry['status'] === 'active' && $entry['admin_payment_status'] === 'paid');
        if ($candidates->isEmpty()) return null;
        $message = 'The existing private registration needs review in Entries before its nomination can be reconnected.';
        $this->ensure($candidates->count() === 1 && count($history['entries']) === 1 && count($history['entry_registrations']) === 1, $message);
        $entry = $candidates->first();
        $this->ensure((int) $entry['category_event_id'] === (int) $category->id && (int) $entry['payment_status_id'] === 1
            && !$entry['pf_transaction_id'] && !$entry['wallet_transaction_id'] && !$entry['withdrawn_at'] && !$entry['refunded_at'] && !$entry['refund_waived_at']
            && (float) $entry['refund_gross'] === 0.0 && (float) $entry['refund_fee'] === 0.0 && (float) $entry['refund_net'] === 0.0
            && ($entry['refund_status'] === null || in_array($entry['refund_status'], CategoryEventRegistration::NO_REFUND_STATUSES, true)), $message);
        foreach (['items', 'orders', 'entry_items', 'entry_invitations', 'proofs', 'manual_receipts', 'recoveries', 'transactions', 'wallet', 'handoffs'] as $key) $this->ensure($history[$key] === [], $message);
        $players = $history['registration_players'];
        $this->ensure(count($players) === 1 && (int) $players[0]['registration_id'] === (int) $entry['registration_id'] && (int) $players[0]['player_id'] === $playerId, $message);
        $this->ensure(count($history['entry_receipts']) === 1, $message);
        $receipt = $history['entry_receipts'][0];
        if ($history['receipt_identity'] === 'legacy_tuple') $this->ensure(count($history['entry_audits']) === 1, $message);
        $this->ensure(($history['receipt_identity'] === 'legacy_tuple' || (int) ($receipt['registration_id'] ?? 0) === (int) $entry['registration_id'])
            && (int) $receipt['event_id'] === (int) $category->event_id && (int) $receipt['custom_int3'] === (int) $category->event_id
            && (int) $receipt['category_event_id'] === (int) $category->id && (int) $receipt['player_id'] === $playerId
            && (int) $receipt['custom_int4'] === (int) $entry['user_id'] && !$receipt['pf_payment_id'] && !$receipt['custom_int5']
            && ! $receipt['is_test'] && ! $receipt['custom_str5']
            && $receipt['transaction_type'] === 'Registration' && $receipt['item_name'] === 'Admin Entry'
            && (float) $receipt['amount_gross'] === 0.0 && (float) $receipt['amount_fee'] === 0.0 && (float) $receipt['amount_net'] === 0.0, $message);
        $this->ensure(collect($history['entry_audits'])->contains(function ($audit) use ($entry, $receipt, $history) {
            $properties = json_decode($audit['properties'], true);
            if ($history['receipt_identity'] === 'legacy_tuple' && ! $this->sameAdministrativeCreation($entry, $receipt, $audit)) return false;
            return (int) $audit['subject_id'] === (int) $entry['id'] && $audit['causer_type'] === User::class
                && (int) $audit['causer_id'] === (int) $entry['user_id']
                && ($properties['canonical_payment_status_id'] ?? null) === 1 && ($properties['transaction_amount'] ?? null) === 0
                && ($properties['entry_source'] ?? null) === 'admin_offline' && ($properties['collection_status'] ?? null) === 'paid_privately'
                && ($properties['operational_note_only'] ?? null) === true && ($properties['reconciled_payment'] ?? null) === false
                && ($properties['financial_amount_recorded'] ?? null) === false;
        }), $message);
        return $entry;
    }
    private function sameAdministrativeCreation(array $entry, array $receipt, array $audit): bool
    {
        if (empty($entry['created_at']) || empty($receipt['created_at']) || empty($audit['created_at'])) return false;
        try {
            $entryAt = \Illuminate\Support\Carbon::parse($entry['created_at'])->timestamp;
            $receiptAt = \Illuminate\Support\Carbon::parse($receipt['created_at'])->timestamp;
            $auditAt = \Illuminate\Support\Carbon::parse($audit['created_at'])->timestamp;
        } catch (\Throwable) {
            return false;
        }
        return $receiptAt >= $entryAt - 2 && $auditAt >= $receiptAt - 2
            && abs($auditAt - $entryAt) <= 120 && abs($receiptAt - $entryAt) <= 120;
    }
    private function assertClosedUnpaidHistory(array $history, CategoryEvent $category, int $playerId): void
    {
        $message = 'This player has active, paid, withdrawn or unresolved checkout history. Manage the registration in Entries. Only fully cancelled, unpaid checkouts that never reached PayFast can be retained when re-adding.';
        foreach (['manual_receipts', 'recoveries', 'transactions', 'wallet', 'handoffs'] as $key) $this->ensure($history[$key] === [], $message);
        $this->ensure(collect($history['proofs'])->every(fn ($proof) => $proof['status'] === 'rejected'), $message);
        $orders = collect($history['orders'])->keyBy('id');
        $items = collect($history['items']);
        $entries = collect($history['entries']);
        foreach ($items as $item) {
            $order = $orders->get($item['order_id']);
            $this->ensure($order && $order['status'] === 'cancelled' && ! $order['pay_status'] && ! $order['payfast_paid']
                && ! $order['wallet_debited'] && ! $order['payfast_handed_off_at'] && ! $order['payfast_pf_payment_id'] && ! $order['wallet_transaction_id']
                && (float) $order['wallet_reserved'] === 0.0
                && (int) $item['category_event_id'] === (int) $category->id && (int) $item['player_id'] === $playerId
                && (int) $item['user_id'] === (int) $order['user_id'] && $items->where('order_id', $order['id'])->count() === 1
                && (int) round((float) $item['item_price'] * 100) === (int) round((float) $order['total_fee'] * 100), $message);
            $players = collect($history['registration_players'])->where('registration_id', $item['registration_id']);
            $this->ensure($players->count() === 1 && (int) $players->first()['player_id'] === $playerId, $message);
            $matching = $entries->where('registration_id', $item['registration_id'])->where('category_event_id', $item['category_event_id']);
            $this->ensure($matching->count() === 1 && (int) $matching->first()['user_id'] === (int) $order['user_id'], $message);
        }
        foreach ($entries as $entry) {
            $this->ensure($entry['deleted_at'] && $entry['status'] === 'pending_checkout' && ! $entry['payment_status_id']
                && ! $entry['pf_transaction_id'] && ! $entry['wallet_transaction_id'] && $entry['admin_payment_status'] !== 'paid'
                && ! $entry['withdrawn_at'] && ! $entry['refunded_at'] && ! $entry['refund_waived_at']
                && (float) $entry['refund_gross'] === 0.0 && (float) $entry['refund_fee'] === 0.0 && (float) $entry['refund_net'] === 0.0
                && ($entry['refund_status'] === null || in_array($entry['refund_status'], CategoryEventRegistration::NO_REFUND_STATUSES, true))
                && $items->where('registration_id', $entry['registration_id'])->where('category_event_id', $entry['category_event_id'])->count() === 1, $message);
            $registrationId = $entry['registration_id'];
            foreach (['draw_registrations', 'draw_group_registrations'] as $table) {
                $this->ensure(! DB::table($table)->where('registration_id', $registrationId)->exists(), $message);
            }
            $this->ensure(! DB::table('fixtures')->where(fn ($query) => $query->where('registration1_id', $registrationId)->orWhere('registration2_id', $registrationId))->exists(), $message);
        }
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) throw ValidationException::withMessages(['nomination' => $message]);
    }
}
