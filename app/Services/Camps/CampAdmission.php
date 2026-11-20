<?php

namespace App\Services\Camps;

use App\Models\{CampConfiguration, CampNominee, CategoryEvent, Event, Player};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CampAdmission
{
    public function configuration(int $eventId): ?CampConfiguration
    {
        return Schema::hasTable('camp_configurations') ? CampConfiguration::where('event_id', $eventId)->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->first() : null;
    }

    /** Acquire before category locks; the same row serializes all admissions across divisions. */
    public function lockEvents(array $eventIds): void
    {
        if (Schema::hasTable('camp_configurations')) {
            // One genuine locking read avoids an early REPEATABLE READ snapshot
            // while leaving ordinary, unconfigured event admission unchanged.
            Event::join('camp_configurations as camp', 'camp.event_id', '=', 'events.id')
                ->whereIn('events.id', $eventIds)->orderBy('events.id')->lockForUpdate()->get(['events.id']);
        }
    }

    public function assertEligible(CategoryEvent $division, int $playerId, bool $admin = false): void
    {
        $config = $this->configuration((int) $division->event_id);
        if (! $config) {
            if (Schema::hasTable('practice_camp_plans') && \App\Models\PracticeCampPlan::where('event_id', $division->event_id)->whereNull('entry_fee')->exists()) {
                throw new RuntimeException('Review the camp fees and entry settings before opening registration.');
            }
            return;
        }
        $rules = $config->divisions[$division->id] ?? null;
        if (! $rules) throw new RuntimeException('This division is not configured for camp entry.');
        $event = DB::transactionLevel() > 0 ? Event::whereKey($division->event_id)->lockForUpdate()->firstOrFail() : $division->event;
        $player = Player::whereKey($playerId)->when(DB::transactionLevel() > 0, fn ($q) => $q->sharedLock())->firstOrFail();
        if (! $player->dateOfBirth) throw new RuntimeException('Confirm the player date of birth before camp entry.');
        $birth = \Carbon\Carbon::parse($player->dateOfBirth);
        $age = (int) $event->start_date->year - $birth->year;
        if ($age < (int) $rules['min_age'] || $age > (int) $rules['max_age']
            || ((int) $rules['gender'] > 0 && (int) $player->gender !== (int) $rules['gender'])) {
            throw new RuntimeException('This player does not meet this camp division’s age or gender requirements.');
        }
        if (! $admin && (! $event->published || ! $event->signUp
            || ($config->registration_closes_at ? now()->gt($config->registration_closes_at) : ! $event->registrationIsOpen()))) {
            throw new RuntimeException('Camp registration is closed.');
        }
        $approved = $config->method !== 'invitation_only' || CampNominee::where('event_id', $division->event_id)
            ->where('category_event_id', $division->id)->where('player_id', $playerId)->where('status', 'approved')
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->first(['id']);
        if (! $approved) {
            throw new RuntimeException('Only an approved nominee in this camp division may enter.');
        }
    }

    /** Distinct beneficiaries, including unresolved canonical checkout holds. */
    public function occupied(int $eventId, bool $current = false): array
    {
        $categoryIds = CategoryEvent::where('event_id', $eventId)->pluck('id');
        $entries = DB::table('category_event_registrations as entry')->join('player_registrations as player', 'player.registration_id', '=', 'entry.registration_id')
            ->whereIn('entry.category_event_id', $categoryIds)->whereNull('entry.deleted_at')->whereNull('entry.withdrawn_at')
            ->where(fn ($q) => $q->whereNull('entry.status')->orWhereNotIn('entry.status', ['withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded', 'cancelled']))
            ->where('entry.payment_status_id', 1)->select('player.player_id');
        $holds = DB::table('registration_order_items as item')->join('registration_orders as checkout', 'checkout.id', '=', 'item.order_id')
            ->whereIn('item.category_event_id', $categoryIds)
            ->where(fn ($q) => $q->whereNull('checkout.status')->orWhereNotIn('checkout.status', ['cancelled', 'expired']))
            ->where(fn ($q) => $q->where(fn ($u) => $u->where('checkout.pay_status', 0)->where('checkout.payfast_paid', false))
                ->orWhereNotExists(fn ($e) => $e->selectRaw('1')->from('category_event_registrations as existing')->whereColumn('existing.registration_id', 'item.registration_id')))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('category_event_registrations as withdrawn')->whereColumn('withdrawn.registration_id', 'item.registration_id')->whereColumn('withdrawn.category_event_id', 'item.category_event_id')
                ->where(fn ($w) => $w->whereNotNull('withdrawn.deleted_at')->orWhereNotNull('withdrawn.withdrawn_at')->orWhereIn('withdrawn.status', ['withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded', 'cancelled'])))
            ->select('item.player_id');
        $moves = DB::table('category_adjustments as move')->join('category_event_registrations as entry', 'entry.id', '=', 'move.entry_id')
            ->join('player_registrations as player', 'player.registration_id', '=', 'entry.registration_id')
            ->whereIn('move.target_category_id', $categoryIds)->where('move.status', 'pending');
        if ($current) { $entries->lockForUpdate(); $holds->lockForUpdate(); $moves->lockForUpdate(); }

        return $entries->pluck('player_id')->merge($holds->pluck('item.player_id'))->merge($moves->pluck('player.player_id'))->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
    }

    public function assertAvailable(CategoryEvent $division, ?int $playerId = null): void
    {
        $config = $this->configuration((int) $division->event_id);
        if (! $config || $config->capacity === null) return;
        $occupied = $this->occupied((int) $division->event_id, true);
        if (! in_array($playerId, $occupied, true) && count($occupied) >= (int) $config->capacity) {
            throw ValidationException::withMessages(['camp' => 'This camp is full, including places held by unpaid checkouts.']);
        }
    }
}
