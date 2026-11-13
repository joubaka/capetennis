<?php

namespace App\Services;

use App\Models\{CategoryEvent, CategoryEventRegistration, Event, EventNomination, InterprovincialTrialInvitation, RegistrationOrder, RegistrationOrderItems, SiteSetting, Transaction, TrialPaymentProof, User, WalletTransaction};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CavaliersNominationDecline
{
    public function canAct(User $actor, Event $event, int $playerId): bool
    {
        return $playerId > 0 && ($actor->hasRole('super-user') || $actor->can('event.manage', $event) || in_array($playerId, $actor->ownedPlayerIds(), true));
    }

    private function authorize(User $actor, Event $event, CategoryEvent $division, int $playerId): void
    {
        abort_unless(app(CavaliersNominationEligibility::class)->applies($event) && (int) $division->event_id === (int) $event->id, 404);
        abort_unless($this->canAct($actor, $event, $playerId), 403);
        abort_unless($event->published && $event->registrationIsOpen() && SiteSetting::get('registration_open', '1') === '1'
            && $division->nominations_published && ! $division->isLocked(), 422, 'Online nomination decisions are closed for this division.');
    }

    private function draft(CategoryEvent $division)
    {
        return DB::table('cavaliers_trial_roster_drafts')->where('event_id', $division->event_id)->where('category_event_id', $division->id);
    }

    private function fingerprint(CategoryEvent $division): string
    {
        return hash('sha256', json_encode(EventNomination::where('category_event_id', $division->id)->orderBy('id')
            ->get(['id', 'event_id', 'player_id', 'roster_position'])->toArray(), JSON_THROW_ON_ERROR));
    }

    private function assertNoParticipation(CategoryEvent $division, int $playerId, bool $lock = false): void
    {
        $fail = fn () => throw ValidationException::withMessages(['nomination' => 'This player has a registration, checkout or payment record that must be reviewed first. Use the existing checkout or withdrawal process, or contact the event manager.']);
        $entries = CategoryEventRegistration::withTrashed()->where('category_event_id', $division->id)
            ->whereHas('registration.players', fn ($query) => $query->where('players.id', $playerId))->when($lock, fn ($query) => $query->lockForUpdate())->get();
        if ($entries->contains(fn ($entry) => (! $entry->deleted_at && ! $entry->withdrawn_at && ! in_array($entry->status, ['withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded', 'cancelled'], true))
            || $entry->payment_status_id || $entry->admin_payment_status === 'paid' || $entry->pf_transaction_id || $entry->wallet_transaction_id || $entry->refunded_at
            || ($entry->refund_status !== null && ! in_array($entry->refund_status, CategoryEventRegistration::NO_REFUND_STATUSES, true)))) $fail();
        $items = RegistrationOrderItems::where('category_event_id', $division->id)->where('player_id', $playerId)->get(['order_id']);
        $orderIds = $items->pluck('order_id')->unique();
        $orders = RegistrationOrder::whereIn('id', $orderIds)->when($lock, fn ($query) => $query->orderBy('id')->lockForUpdate())->get();
        if ($orders->count() !== $orderIds->count() || $orders->contains(fn ($order) => $order->status !== 'cancelled' || $order->pay_status || $order->payfast_paid
            || $order->wallet_debited || (float) $order->wallet_reserved !== 0.0 || $order->wallet_transaction_id || $order->payfast_pf_payment_id || $order->payfast_handed_off_at)) $fail();
        if (TrialPaymentProof::whereIn('order_id', $orderIds)->whereIn('status', ['pending', 'verified'])->exists()
            || DB::table('registration_manual_receipts')->whereIn('order_id', $orderIds)->exists()
            || DB::table('registration_payment_recoveries')->whereIn('registration_order_id', $orderIds)->exists()
            || WalletTransaction::where('source_type', 'event_registration_wallet_payment')->whereIn('source_id', $orderIds)->exists()
            || Transaction::where(fn ($query) => $query->where('event_id', $division->event_id)->where('category_event_id', $division->id)->where('player_id', $playerId))
                ->orWhere(fn ($query) => $query->whereIn('custom_int5', $orderIds)->where(fn ($query) => $query->whereNull('custom_str5')->orWhere('custom_str5', '')))->exists()) $fail();
    }

    public function preview(User $actor, Event $event, CategoryEvent $division, int $nominationId): array
    {
        $nomination = EventNomination::whereKey($nominationId)->where('event_id', $event->id)->where('category_event_id', $division->id)->firstOrFail();
        $this->authorize($actor, $event, $division, (int) $nomination->player_id);
        abort_unless(EventNomination::where('event_id', $event->id)->where('category_event_id', $division->id)->where('player_id', $nomination->player_id)->count() === 1,
            422, 'This player has ambiguous nominations. Contact the event manager.');
        $this->assertNoParticipation($division, (int) $nomination->player_id);
        $draft = $this->draft($division)->first();
        abort_unless($draft, 422, 'The event manager must save this division roster before online nomination decisions are available.');
        return ['actor_id' => $actor->id, 'event_id' => $event->id, 'category_event_id' => $division->id, 'nomination_id' => $nomination->id,
            'player_id' => (int) $nomination->player_id, 'name' => $nomination->display_name, 'revision' => $draft->revision,
            'fingerprint' => $this->fingerprint($division), 'expires' => now()->addMinutes(30)->timestamp];
    }

    public function confirm(User $actor, Event $event, CategoryEvent $division, array $review): bool
    {
        abort_unless((int) $review['actor_id'] === (int) $actor->id && (int) $review['event_id'] === (int) $event->id
            && (int) $review['category_event_id'] === (int) $division->id && $review['expires'] >= now()->timestamp, 409, 'This review expired. Open the nomination again.');
        return DB::transaction(function () use ($actor, $event, $division, $review) {
            $division = CategoryEvent::whereKey($division->id)->where('event_id', $event->id)->lockForUpdate()->firstOrFail();
            $event = Event::with('eventTypeModel')->findOrFail($event->id);
            $this->authorize($actor, $event, $division, (int) $review['player_id']);
            $draft = $this->draft($division)->lockForUpdate()->first();
            $nomination = EventNomination::whereKey($review['nomination_id'])->where('event_id', $event->id)->where('category_event_id', $division->id)->lockForUpdate()->first();
            $declined = json_decode($draft?->declined ?? '[]', true, flags: JSON_THROW_ON_ERROR);
            if (! $nomination) {
                $recorded = DB::table('activity_log')->where('log_name', 'cavaliers-nomination')->where('description', 'Cavaliers nomination declined')
                    ->where('properties->nomination_id', $review['nomination_id'])->where('properties->event_id', $event->id)
                    ->where('properties->category_event_id', $division->id)->where('properties->player_id', $review['player_id'])->exists();
                abort_unless($recorded && in_array((int) $review['player_id'], $declined, true)
                    && ! EventNomination::where('event_id', $event->id)->where('category_event_id', $division->id)->where('player_id', $review['player_id'])->exists(), 409, 'The nomination changed. Ask the event manager to review it.');
                return false;
            }
            abort_unless($draft && $draft->revision === $review['revision'] && (int) $nomination->player_id === (int) $review['player_id']
                && hash_equals($this->fingerprint($division), $review['fingerprint']), 409, 'The nomination or roster changed. Review the decision again.');
            abort_if(InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->exists(), 422, 'This nomination belongs to a separate invitation workflow. Contact the event manager.');
            $this->assertNoParticipation($division, (int) $review['player_id'], true);
            $selected = json_decode($draft->selected, true, flags: JSON_THROW_ON_ERROR);
            $reserves = json_decode($draft->reserves, true, flags: JSON_THROW_ON_ERROR);
            $playerId = (int) $review['player_id'];
            abort_unless(EventNomination::where('event_id', $event->id)->where('category_event_id', $division->id)->where('player_id', $playerId)->count() === 1,
                422, 'This player has ambiguous nominations. Contact the event manager.');
            abort_if(count($declined) >= 256 && ! in_array($playerId, $declined, true), 422, 'The event manager must review this division before another decline is recorded.');
            $revision = (string) Str::uuid();
            $written = $this->draft($division)->where('revision', $review['revision'])->update([
                'revision' => $revision,
                'selected' => json_encode(array_values(array_diff($selected, [$playerId])), JSON_THROW_ON_ERROR),
                'reserves' => json_encode(array_values(array_diff($reserves, [$playerId])), JSON_THROW_ON_ERROR),
                'declined' => json_encode(array_values(array_unique([...$declined, $playerId])), JSON_THROW_ON_ERROR),
            ]);
            abort_unless($written === 1, 409, 'The roster changed. Review the decision again.');
            activity('cavaliers-nomination')->performedOn($event)->causedBy($actor)
                ->withProperties(['event_id' => $event->id, 'category_event_id' => $division->id, 'nomination_id' => $nomination->id,
                    'player_id' => $playerId, 'previous_revision' => $draft->revision, 'revision' => $revision])->log('Cavaliers nomination declined');
            $nomination->delete();
            return true;
        }, 3);
    }
}
