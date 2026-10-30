<?php

namespace App\Services\Draw;

use App\Models\{Draw, FixtureResult, FlexibleMonradDraw, Player, Registration};
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
            $flexible = $draw->usesFlexibleMonrad()
                ? FlexibleMonradDraw::where('draw_id', $draw->id)->lockForUpdate()->first() : null;
            abort_if($draw->usesFlexibleMonrad() && !$flexible?->graph, 409, 'Generate and review this draw before replacing a player.');
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
            if ($flexible) {
                $this->assertMappedReplacement($flexible, $fixtures, $outgoing);
                abort_unless(app(FlexibleMonradService::class)->eligible($draw)->whereKey($outgoing)->exists(),
                    409, 'This player is no longer active in the draw. Use the withdrawal workflow.');
            }
            abort_if($this->hasStarted($draw, $outgoing, $fixtures, true), 409, 'An alternate cannot join after this player’s first match has started.');
            $remaining = $fixtures;
            abort_unless($remaining->contains(fn ($fx) => in_array($outgoing, [(int) $fx->registration1_id, (int) $fx->registration2_id], true)), 422, 'This player has no unstarted matches remaining.');
            // A competition identity only: no paid entry, payer, account link, or financial state is created.
            $incoming = Registration::create(['competition_replacement' => ['draw_id' => $draw->id, 'show_alternate' => $label,
                'source_registration_id' => $sourceEntry?->registration_id]]);
            $incoming->players()->attach($playerId);
            if ($flexible) {
                $draft = $this->replacePlayerSources($flexible->draft, $outgoing, $incoming->id);
                $graph = $this->replacePlayerSources($flexible->graph, $outgoing, $incoming->id);
                $graph['players'] = array_map(fn ($id) => (int) $id === $outgoing ? $incoming->id : $id, $graph['players']);
                if (isset($draft['additional_registration_ids'])) {
                    $draft['additional_registration_ids'] = array_values(array_diff($draft['additional_registration_ids'], [$outgoing]));
                }
                $flexible->fill(['draft' => $draft, 'graph' => $graph, 'revision' => $flexible->revision + 1])->save();
            }
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

    private function assertMappedReplacement(FlexibleMonradDraw $record, $fixtures, int $outgoing): void
    {
        $graph = $record->graph;
        $map = $record->fixture_map ?? [];
        $nodes = $graph['nodes'] ?? [];
        $players = array_map('intval', $graph['players'] ?? []);
        $slots = $record->draft['slots'] ?? [];
        abort_unless(is_array($nodes) && $nodes && is_array($map)
            && count(array_diff(array_keys($nodes), array_keys($map))) === 0
            && count(array_diff(array_keys($map), array_keys($nodes))) === 0
            && count(array_unique($map)) === count($map)
            && count(array_diff(array_map('intval', $map), $fixtures->pluck('id')->all())) === 0
            && count($map) === $fixtures->count()
            && count(array_keys($players, $outgoing, true)) === 1
            && collect($slots)->contains(fn ($slot) => ($slot['type'] ?? null) === 'player' && (int) ($slot['id'] ?? 0) === $outgoing)
            && collect($nodes)->contains(fn ($node) => collect($node['sources'] ?? [])->contains(fn ($source) => ($source['type'] ?? null) === 'player' && (int) ($source['id'] ?? 0) === $outgoing)),
            409, 'This draw’s saved positions and fixtures are incomplete. Restore the draw before replacing a player.');
        abort_if(in_array($outgoing, array_map('intval', $graph['withdrawn'] ?? []), true)
            || in_array($outgoing, array_map('intval', array_keys($graph['late_withdrawals'] ?? [])), true),
            409, 'This player has already been withdrawn from the draw. Use the withdrawal workflow.');
    }

    /** Replace only explicitly typed player sources; numbers such as match IDs and ranks are untouched. */
    private function replacePlayerSources(array $value, int $outgoing, int $incoming): array
    {
        return $this->mapPlayerSources($value, [$outgoing => $incoming]);
    }

    private function mapPlayerSources(array $value, array $ids): array
    {
        if (($value['type'] ?? null) === 'player' && isset($ids[(int) ($value['id'] ?? 0)])) $value['id'] = $ids[(int) $value['id']];
        foreach ($value as $key => $child) {
            if (is_array($child)) $value[$key] = $this->mapPlayerSources($child, $ids);
        }
        return $value;
    }

    /** Swap identities in place; match IDs and all saved time/court assignments stay intact. */
    public function swapPositions(Draw $draw, int $first, int $second, int $revision): void
    {
        DB::transaction(function () use ($draw, $first, $second, $revision) {
            // Match the canonical scheduler's serialization before validating retained bookings.
            DB::table('venues')->orderBy('id')->limit(1)->lockForUpdate()->get();
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            DB::table('events')->where('id', $draw->event_id)->lockForUpdate()->get();
            abort_if($draw->locked || $draw->isTeamDraw(), 409, 'Choose an unlocked singles draw.');
            abort_unless($first !== $second, 422, 'Choose two different players to swap.');
            $record = FlexibleMonradDraw::where('draw_id', $draw->id)->lockForUpdate()->first();
            abort_unless($record?->graph, 409, 'Generate the draw before swapping players.');
            abort_unless($record->revision === $revision, 409, 'The draw changed. Reload it before swapping players.');
            $fixtures = $draw->drawFixtures()->with(['fixtureResults', 'orderOfPlay'])->lockForUpdate()->get();
            foreach ([$first, $second] as $id) {
                $this->assertMappedReplacement($record, $fixtures, $id);
                abort_unless($draw->registrations()->where('registrations.id', $id)->exists()
                    && app(FlexibleMonradService::class)->eligible($draw)->whereKey($id)->exists(),
                    422, 'Choose two active players in this draw.');
                abort_if($this->hasStarted($draw, $id, $fixtures, true), 409, 'Players cannot be swapped after either player’s first match has started.');
            }
            $ids = [$first => $second, $second => $first];
            $graph = $this->mapPlayerSources($record->graph, $ids);
            $graph['players'] = array_map(fn ($id) => $ids[(int) $id] ?? $id, $graph['players']);
            $record->fill(['draft' => $this->mapPlayerSources($record->draft, $ids), 'graph' => $graph, 'revision' => $revision + 1])->save();
            foreach ($fixtures as $fixture) {
                foreach (['registration1_id', 'registration2_id', 'hint_registration1_id', 'hint_registration2_id', 'winner_registration'] as $field) {
                    if (isset($ids[(int) $fixture->$field])) $fixture->$field = $ids[(int) $fixture->$field];
                }
                if ($fixture->isDirty()) $fixture->save();
            }
            $draw = $draw->fresh();
            $matches = (array) app(FlexibleMonradService::class)->state($draw)['matches'];
            $possible = \App\Domain\Draws\Services\ScheduleAvailability::participants($matches);
            $affected = collect($matches)->filter(fn ($match, $key) => array_intersect([$first, $second], $possible[$key] ?? []))->pluck('id');
            $bookings = \App\Models\OrderOfPlay::whereIn('fixture_id', $affected)->whereNotNull('time')->lockForUpdate()->get();
            foreach ($bookings as $booking) {
                $fixture = $fixtures->firstWhere('id', $booking->fixture_id)->fresh();
                $conflict = app(\App\Domain\Draws\Services\ScheduleConflictService::class)->conflict($draw, $fixture,
                    (int) $booking->venue_id, (string) $booking->court, (string) $booking->time,
                    (int) ($booking->duration_minutes ?: 75), null, (int) ($booking->gap_minutes ?? 0));
                if ($conflict) throw ValidationException::withMessages(['swap' => $conflict]);
            }
            \App\Models\DrawAuditLog::record($draw->id, 'players_swapped', null,
                ['registration_ids' => [$first, $second], 'revision' => $record->revision, 'fixture_ids' => $affected->all()]);
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
