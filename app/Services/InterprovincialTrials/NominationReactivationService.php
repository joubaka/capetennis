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
            $this->assertClosedUnpaidHistory($history, $category, $playerId);
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
                'nomination_id' => $id, 'player_id' => $playerId, 'status' => 'open_registration']);
            activity('interprovincial-trials')->performedOn($nomination)->causedBy($actor)->withProperties([
                'event_id' => $event->id, 'category_event_id' => $category->id, 'reason' => trim($reason),
                'source_audit_id' => $state['audit_id'], 'before' => $snapshot, 'retained_invitation_ids' => $invitations->pluck('id')->all(),
                'retained_cancelled_order_ids' => array_column($history['orders'], 'id'),
                'retained_deleted_entry_ids' => array_column($history['entries'], 'id'),
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
        if ($entries->isEmpty() && $items->isEmpty()) return array_fill_keys(['entries', 'orders', 'items', 'manual_receipts', 'recoveries', 'transactions', 'wallet', 'handoffs', 'proofs', 'registration_players'], []);
        $orderIds = $items->pluck('order_id')->unique();
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
            'registration_players' => DB::table('player_registrations')->whereIn('registration_id', $items->pluck('registration_id')),
        ];
        $history = ['entries' => $entries->map->getAttributes()->all()];
        foreach ($queries as $key => $query) {
            if ($lock) $query->lockForUpdate();
            // Stable row ordering keeps the review hash independent of query plans.
            $history[$key] = $query->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        return $history;
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
