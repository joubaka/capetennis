<?php

namespace App\Services\Scheduling;

use App\Models\{Event, TeamFixture};
use Illuminate\Support\Collection;

/** Roster-position venue assignments for automatic planning; manual placement can override. */
final class RankVenuePreferences
{
    public function normalize(Event $event, array $rules): array
    {
        return $this->normalizeRules($event, $rules);
    }

    public function normalizeDay(Event $event, \App\Models\Draw $draw, array $rules, array $venueIds): array
    {
        if ((int) $draw->event_id !== (int) $event->id) throw new \InvalidArgumentException('The selected draw must belong to this event.');
        $allowed = $event->venues()->pluck('venues.id')->merge($draw->venues()->pluck('venues.id'))->unique()->all();
        if (array_diff($venueIds, $allowed)) throw new \InvalidArgumentException('Choose venues in this event.');
        $rules = array_map(fn ($rule) => array_replace($rule, ['draw_ids' => [(int) $draw->id]]), $rules);
        return $this->normalizeRules($event, $rules, [(int) $draw->id => array_map('intval', $venueIds)]);
    }

    private function normalizeRules(Event $event, array $rules, array $dayVenues = []): array
    {
        if (count($rules) > 50) throw new \InvalidArgumentException('Use at most 50 roster rank bands.');
        $ids = collect($rules)->flatMap(fn ($rule) => $rule['draw_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        $draws = $event->draws()->with('venues')->whereIn('id', $ids)->get()->keyBy('id');
        if ($draws->count() !== $ids->count()) throw new \InvalidArgumentException('A rank preference draw does not belong to this event.');
        $normalized = [];
        $used = [];
        foreach ($rules as $rule) {
            $drawIds = array_values(array_unique(array_map('intval', $rule['draw_ids'] ?? [])));
            sort($drawIds);
            $min = (int) ($rule['min_rank'] ?? 0);
            $max = (int) ($rule['max_rank'] ?? 0);
            $venue = (int) ($rule['venue_id'] ?? 0);
            if (! $drawIds || $min < 1 || $max < $min || $max > 100) throw new \InvalidArgumentException('Each rank band needs selected team draws and valid roster ranks from 1 to 100.');
            foreach ($drawIds as $id) {
                $draw = $draws[$id];
                if (! $draw->isTeamDraw()) throw new \InvalidArgumentException('Roster rank preferences apply to team draws only.');
                if (isset($dayVenues[$id])) {
                    if (! in_array($venue, $dayVenues[$id], true)) throw new \InvalidArgumentException('Choose a selected destination venue for each position band on this day.');
                } elseif (! $draw->venues->contains('id', $venue)) {
                    throw new \InvalidArgumentException('A rank preference venue must be assigned to every selected draw.');
                }
                foreach ($used[$id] ?? [] as [$lower, $upper]) {
                    if ($min <= $upper && $max >= $lower) throw new \InvalidArgumentException('Roster rank bands cannot overlap within a draw.');
                }
                $used[$id][] = [$min, $max];
            }
            $normalized[] = ['draw_ids' => $drawIds, 'min_rank' => $min, 'max_rank' => $max, 'venue_id' => $venue];
        }
        usort($normalized, fn ($a, $b) => [$a['draw_ids'], $a['min_rank'], $a['max_rank'], $a['venue_id']] <=> [$b['draw_ids'], $b['min_rank'], $b['max_rank'], $b['venue_id']]);
        return $normalized;
    }

    public function assigned(Event $event, array $rules): array
    {
        $draws = $event->draws()->with('venues')->get()->keyBy('id');
        return collect($rules)->map(function ($rule) use ($draws) {
            $rule['draw_ids'] = array_values(array_filter($rule['draw_ids'] ?? [], fn ($id) => isset($draws[$id])
                && $draws[$id]->isTeamDraw() && $draws[$id]->venues->contains('id', (int) $rule['venue_id'])));
            return $rule;
        })->filter(fn ($rule) => $rule['draw_ids'])->values()->all();
    }

    public function active(array $rules, array $drawIds): array
    {
        return collect($rules)->map(function ($rule) use ($drawIds) {
            $rule['draw_ids'] = array_values(array_intersect(array_map('intval', $rule['draw_ids'] ?? []), $drawIds));
            return $rule;
        })->filter(fn ($rule) => $rule['draw_ids'])->values()->all();
    }

    public function retained(array $rules, array $replacedDrawIds): array
    {
        return collect($rules)->map(function ($rule) use ($replacedDrawIds) {
            $rule['draw_ids'] = array_values(array_diff(array_map('intval', $rule['draw_ids'] ?? []), $replacedDrawIds));
            return $rule;
        })->filter(fn ($rule) => $rule['draw_ids'])->values()->all();
    }

    public function choices(Collection $fixtures, array $rules, string $policy): array
    {
        if (! in_array($policy, ['highest_ranked', 'manual'], true)) throw new \InvalidArgumentException('Choose a valid cross-band rule.');
        if (! $rules || $fixtures->isEmpty()) return [];
        app(\App\Services\TeamFixtureLineupPresenter::class)->prepare($fixtures);
        $choices = [];
        foreach ($fixtures as $fixture) $choices[$fixture->id] = $this->choice($fixture, $rules, $policy);
        return $choices;
    }

    private function choice(TeamFixture $fixture, array $rules, string $policy): array
    {
        $bands = array_values(array_filter($rules, fn ($rule) => in_array((int) $fixture->draw_id, $rule['draw_ids'], true)));
        $players = collect($fixture->lineup_display ?? [])->flatMap(fn ($side) => $side['players'] ?? []);
        $ranks = $players->pluck('rank')->filter(fn ($rank) => is_numeric($rank) && (int) $rank > 0)->map(fn ($rank) => (int) $rank)->sort()->values()->all();
        $choice = ['venue_id' => null, 'ranks' => $ranks, 'manual' => false, 'warning' => null];
        if (! $bands) return $choice;
        $sources = collect($fixture->lineup_rank_sources ?? [])->flatten()->all();
        $expected = 2 * (int) ($fixture->player_count_per_team ?: ($fixture->isDoubles() ? 2 : 1));
        if (! $ranks || count($ranks) !== $players->count() || $players->count() < $expected
            || count(array_filter($sources, fn ($source) => in_array($source, ['snapshot', 'roster', 'legacy'], true))) !== $players->count()) {
            $choice['warning'] = 'Roster ranks are unavailable; normal venue scheduling is used.';
            return $choice;
        }
        $mapped = [];
        foreach ($ranks as $rank) {
            $band = collect($bands)->first(fn ($rule) => $rank >= $rule['min_rank'] && $rank <= $rule['max_rank']);
            if (! $band) {
                $choice['warning'] = 'A roster rank falls outside the configured bands; normal venue scheduling is used.';
                return $choice;
            }
            $mapped[] = $band['venue_id'];
        }
        $cross = count(array_unique($mapped)) > 1;
        $choice['venue_id'] = $mapped[0];
        if ($cross) {
            $choice['manual'] = $policy === 'manual';
            $choice['warning'] = $choice['manual'] ? 'Players span different rank venues; choose a venue manually.'
                : "Players span different rank venues; the highest-ranked player's venue is assigned.";
        }
        return $choice;
    }

    public function manualWarnings(TeamFixture $fixture, int $venue): array
    {
        $event = $fixture->draw?->event;
        if (! $event) return [];
        $draft = json_decode((string) \Illuminate\Support\Facades\DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
        $rules = $this->active($draft['rank_venue_preferences'] ?? [], [(int) $fixture->draw_id]);
        $roundSetup = collect($draft['round_venue_setups'] ?? [])->first(fn ($row) => (int) $row['draw_id'] === (int) $fixture->draw_id && (int) $row['round'] === (int) $fixture->round_nr);
        if ($roundSetup) $rules = $roundSetup['rank_venue_preferences'];
        $choice = $this->choices(collect([$fixture]), $rules, $draft['cross_band_policy'] ?? 'highest_ranked')[$fixture->id] ?? null;
        $warnings = $choice && $choice['warning'] ? [$choice['warning']] : [];
        if ($choice && $choice['venue_id'] && $choice['venue_id'] !== $venue) $warnings[] = 'This placement differs from the roster rank venue assignment.';
        return $warnings;
    }
}
