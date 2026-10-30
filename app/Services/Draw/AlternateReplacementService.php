<?php

namespace App\Services\Draw;

use App\Models\{Draw, FixtureResult, Player, Registration};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AlternateReplacementService
{
    public function hasStarted(Draw $draw, int $outgoing, ?\Illuminate\Support\Collection $fixtures = null, bool $locked = false, ?\Illuminate\Support\Collection $startedIds = null): bool
    {
        $fixtures ??= $draw->drawFixtures()->with('fixtureResults')->get();
        $path = $fixtures->filter(fn ($fx) => in_array($outgoing, [(int) $fx->registration1_id, (int) $fx->registration2_id], true))->keyBy('id');
        // Feeder links point from an earlier match to its winner/loser destination.
        do {
            $before = $path->count();
            foreach ($fixtures as $fixture) {
                if (($path->has($fixture->parent_fixture_id) || $path->has($fixture->loser_parent_fixture_id))
                    && in_array($outgoing, [(int) $fixture->registration1_id, (int) $fixture->registration2_id,
                        (int) $fixture->hint_registration1_id, (int) $fixture->hint_registration2_id, (int) $fixture->winner_registration], true)) {
                    $path->put($fixture->id, $fixture);
                }
            }
        } while ($path->count() !== $before);
        if ($path->contains(fn ($fx) => (int) $fx->match_status === 2 || ((int) $fx->registration1_id > 0 && (int) $fx->registration2_id > 0
            && ((int) $fx->match_status === 1 || $fx->winner_registration !== null)))) return true;
        $results = FixtureResult::whereIn('fixture_id', $path->keys());
        $starts = \App\Models\DrawAuditLog::where('draw_id', $draw->id)->whereIn('fixture_id', $path->keys())->where('action', 'match_started');
        if (!$locked && $startedIds !== null) {
            return $path->contains(fn ($fx) => $fx->fixtureResults->isNotEmpty()) || $startedIds->intersect($path->keys())->isNotEmpty();
        }
        // Current locking reads prevent a stale repeatable-read snapshot accepting a just-started branch.
        return ($locked ? $results->lockForUpdate()->get()->isNotEmpty() : $results->exists())
            || ($locked ? $starts->lockForUpdate()->get()->isNotEmpty() : $starts->exists());
    }

    public function replace(Draw $draw, int $outgoing, int $playerId, bool $label, int $actor): Registration
    {
        return DB::transaction(function () use ($draw, $outgoing, $playerId, $label, $actor) {
            $draw = Draw::query()->lockForUpdate()->findOrFail($draw->id);
            abort_if($draw->locked || $draw->isTeamDraw(), 409, 'Choose an unlocked singles draw.');
            abort_if($draw->usesFlexibleMonrad(), 409, 'Alternates for Flexible Monrad require its placement editor.');
            abort_unless($draw->category_event_id, 422, 'Choose a player category for this draw first.');
            $old = $draw->registrations()->where('registrations.id', $outgoing)->with('players')->first();
            abort_unless($old && $old->players->count() === 1, 422, 'Choose a current singles player in this draw.');
            $player = Player::query()->lockForUpdate()->findOrFail($playerId);
            $sourceEntry = app(GroupAssignmentService::class)->eligible($draw)
                ->whereNull('deleted_at')
                ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', [
                    'withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded', 'refund_requested', 'refunded', 'cancelled',
                ]))
                ->whereHas('registration.players', fn ($q) => $q->whereKey($playerId))->lockForUpdate()->first();
            $paidEntry = $sourceEntry !== null;
            abort_if($draw->event->isInterprovincialTrials() && !$paidEntry, 422, 'Trials alternates must already have an active paid entry in this category. Use the existing Entries workflow first.');
            abort_if(!$paidEntry && Registration::whereHas('players', fn ($q) => $q->whereKey($playerId))
                ->whereHas('categoryEvents', fn ($q) => $q->where('category_events.id', $draw->category_event_id))->exists(),
                422, 'Manage this player’s existing category entry before selecting them as an alternate.');
            try {
                app(\App\Services\PlayerEligibilityService::class)->assertEligible($player, $draw->event);
            } catch (\RuntimeException $exception) {
                throw ValidationException::withMessages(['player_id' => $exception->getMessage()]);
            }
            abort_if(Registration::whereHas('players', fn ($q) => $q->whereKey($playerId))
                ->whereHas('draws', fn ($q) => $q->where('draws.category_event_id', $draw->category_event_id)
                    ->where('draws.id', '!=', $draw->id))->exists(), 422, 'This player is already assigned to another draw in this category.');
            $key = app(\App\Services\TeamDrawSideResolver::class)->categoryKey($draw->categoryEvent->category->name);
            $gender = in_array($player->gender, [1, '1', 'male'], true) ? 'boys' : (in_array($player->gender, [2, '2', 'female'], true) ? 'girls' : null);
            if (($key['gender'] && $gender !== $key['gender']) || (preg_match('/^u\/(\d+)/', $key['group'], $age)
                && (!$player->dateOfBirth || Carbon::parse($draw->event->start_date)->year - Carbon::parse($player->dateOfBirth)->year > (int) $age[1]))) {
                throw ValidationException::withMessages(['player_id' => 'The alternate must meet this category’s age and gender eligibility.']);
            }
            abort_if($draw->registrations()->whereHas('players', fn ($q) => $q->where('players.id', $playerId))->exists(), 422, 'This player is already in the draw.');
            $fixtures = $draw->drawFixtures()->with('fixtureResults')->lockForUpdate()->get();
            abort_if($this->hasStarted($draw, $outgoing, $fixtures, true), 409, 'An alternate cannot join after this player’s first match has started.');
            $remaining = $fixtures;
            abort_unless($remaining->contains(fn ($fx) => in_array($outgoing, [(int) $fx->registration1_id, (int) $fx->registration2_id], true)), 422, 'This player has no unstarted matches remaining.');
            // A competition identity only: no paid entry, payer, account link, or financial state is created.
            $incoming = Registration::create(['competition_replacement' => ['draw_id' => $draw->id, 'show_alternate' => $label,
                'source_registration_id' => $sourceEntry?->registration_id]]);
            $incoming->players()->attach($playerId);
            $history = $draw->team_draw_selection ?? [];
            $history['singles_alternates'][] = ['outgoing_registration_id' => $outgoing, 'incoming_registration_id' => $incoming->id,
                'player_id' => $playerId, 'actor_id' => $actor, 'replaced_at' => now()->toIso8601String(), 'show_alternate' => $label];
            $draw->team_draw_selection = $history;
            $draw->save();
            DB::table('draw_registrations')->where('draw_id', $draw->id)->where('registration_id', $outgoing)->update(['registration_id' => $incoming->id]);
            DB::table('draw_group_registrations')->whereIn('draw_group_id', $draw->groups()->pluck('id'))->where('registration_id', $outgoing)->update(['registration_id' => $incoming->id]);
            foreach ($remaining as $fixture) {
                foreach (['registration1_id', 'registration2_id', 'hint_registration1_id', 'hint_registration2_id', 'winner_registration'] as $field) {
                    if ((int) $fixture->$field === $outgoing) $fixture->$field = $incoming->id;
                }
                if ($fixture->isDirty()) $fixture->save();
            }
            \App\Models\DrawAuditLog::record($draw->id, 'alternate_replaced', null, end($history['singles_alternates']) + ['fixture_ids' => $fixtures->pluck('id')->all()]);
            return $incoming;
        }, 3);
    }

    /** Resolve draw-only competition identities when their paid source entry withdraws. */
    public function withdrawalIdentities(\Illuminate\Support\Collection $drawIds, \Illuminate\Support\Collection $registrationIds): \Illuminate\Support\Collection
    {
        $aliases = Registration::whereIn('competition_replacement->draw_id', $drawIds)
            ->whereIn('competition_replacement->source_registration_id', $registrationIds)->lockForUpdate()->pluck('id');
        return $registrationIds->concat($aliases)->map(fn ($id) => (int) $id)->unique()->values();
    }
}
