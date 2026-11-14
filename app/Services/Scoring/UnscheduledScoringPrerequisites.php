<?php

namespace App\Services\Scoring;

use App\Models\{Event, Fixture, User};
use Illuminate\Support\Collection;

/** Read-only organizer assistance; these fixtures never enter the court queue. */
final class UnscheduledScoringPrerequisites
{
    public function forVisibleFixtures(Event $event, Collection $visible, User $actor): Collection
    {
        if ($actor->is_event_score_keeper($event->id)) return collect();
        $anchors = $visible->filter(fn ($fixture) => $fixture instanceof Fixture
            && (int) $fixture->draw?->event_id === (int) $event->id && !$fixture->draw->usesFlexibleMonrad()
            && $fixture->orderOfPlay?->time && $actor->can('saveScore', $fixture->draw))->keyBy('id');
        if ($anchors->isEmpty()) return collect();
        $drawIds = $anchors->pluck('draw_id')->unique()->all();
        $frontier = $anchors; $seen = $visible->pluck('id')->all(); $found = collect(); $scanned = 0;
        for ($depth = 1; $depth <= 10 && $frontier->isNotEmpty() && $scanned < 100; $depth++) {
            $parents = $frontier->keys()->all();
            $candidates = Fixture::query()->whereIn('draw_id', $drawIds)->whereNotIn('id', $seen)
                ->whereHas('draw', fn ($query) => $query->where('event_id', $event->id))
                ->where(fn ($query) => $query->whereIn('parent_fixture_id', $parents)->orWhereIn('loser_parent_fixture_id', $parents))
                ->whereDoesntHave('fixtureResults')->whereNull('winner_registration')
                ->where(fn ($query) => $query->whereNull('match_status')->orWhereIn('match_status', [0, 2]))
                // Stop at booked ancestors, including private/legacy/published bookings.
                ->whereDoesntHave('orderOfPlay')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('schedules')->whereColumn('schedules.fixture_id', 'fixtures.id'))
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('published_schedule_assignments')
                    ->where('fixture_kind', 'individual')->whereColumn('published_schedule_assignments.fixture_id', 'fixtures.id'))
                ->with(['draw.settings', 'draw.flexibleMonrad', 'draw.categoryEvent.category',
                    'registration1.players', 'registration2.players', 'fixtureResults', 'orderOfPlay'])
                ->orderBy('match_nr')->orderBy('id')->limit(100 - $scanned)->get();
            $scanned += $candidates->count(); $next = collect();
            foreach ($candidates as $candidate) {
                $seen[] = $candidate->id;
                $target = collect([$candidate->parent_fixture_id, $candidate->loser_parent_fixture_id])
                    ->map(fn ($id) => $frontier->get($id))->first(fn ($fixture) => $fixture
                        && (int) $fixture->draw_id === (int) $candidate->draw_id);
                if (!$target || !$actor->can('saveScore', $candidate->draw)) continue;
                $anchor = $target->scoring_prerequisite_anchor ?: ['id' => $target->id,
                    'match_nr' => $target->match_nr ?: $target->id, 'label' => $target->playoff_type];
                $candidate->setAttribute('scoring_unscheduled_prerequisite', true);
                $candidate->setAttribute('scoring_prerequisite_depth', $depth);
                $candidate->setAttribute('scoring_prerequisite_anchor', $anchor);
                $found->put($candidate->id, $candidate); $next->put($candidate->id, $candidate);
            }
            $frontier = $next;
        }
        $found = $found->sortByDesc('scoring_prerequisite_depth')->values();
        app(FixtureParticipantLabels::class)->prepare($found);
        return $found;
    }
}
