<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TeamFixture;
use App\Models\TeamResultSelectionDraft;

/** Current event rosters alongside private saved selection decisions. */
class TeamSelectionPreviewService
{
    public function preview(Event $event, ?int $requestedAge, ?string $requestedGender = null): array
    {
        $teams = app(EventTeamScope::class)->query($event)->with([
            'category.category', 'regions', 'competitionSubstitutions',
            'team_players' => fn ($query) => $query->whereBetween('rank', [1, 12])->with('player'),
            'team_players_no_profile' => fn ($query) => $query->whereBetween('rank', [1, 12]),
        ])->orderBy('name')->limit(501)->get();
        abort_if($teams->count() > 500, 422, 'This preview supports up to 500 event teams.');
        $rankings = app(TeamResultRankingService::class);
        $grouped = $teams->map(function ($team) use ($rankings) {
            $fixture = new TeamFixture(['age' => ($team->category?->category?->name ?? '').' '.$team->name]);
            return ['team' => $team, 'group' => $rankings->group($fixture)];
        })->filter(fn ($row) => $row['group'] !== null);
        $finalization = \App\Models\EventSelectionFinalization::where('event_id', $event->id)->with('actor:id,name')->first();
        $drafts = app(SelectionPreviewSnapshotService::class)->drafts($event, $finalization);
        $draftVersions = $drafts->mapWithKeys(fn ($draft, $key) => [$key => (int) $draft->version])->all();
        $navigationGroups = $grouped->pluck('group')->merge($drafts->keys()->map(function ($key) {
            if (! preg_match('/^([1-9][0-9]?)-(boys|girls)$/', $key, $matches)) return null;
            return ['key' => $key, 'name' => 'u/'.$matches[1].' '.ucfirst($matches[2]), 'age' => (int) $matches[1]];
        }))->filter()->unique('key')->map(fn ($group) => $group + ['gender' => explode('-', $group['key'])[1]])
            ->sortBy(fn ($group) => $group['age'] * 2 + ($group['gender'] === 'girls' ? 1 : 0))->values();
        $ages = $navigationGroups->pluck('age')->unique()->values();
        $choices = $navigationGroups->filter(fn ($group) => ($requestedAge === null || $group['age'] === $requestedAge)
            && ($requestedGender === null || $group['gender'] === $requestedGender));
        abort_if(($requestedAge !== null || $requestedGender !== null) && $choices->isEmpty(), 404);
        $active = $choices->first();
        $age = $active['age'] ?? null;
        $gender = $active['gender'] ?? null;
        $activeGroupKey = $active['key'] ?? null;
        $regionIds = $event->regions()->pluck('team_regions.id')->map('intval')->all();
        $teamSize = $finalization?->team_size ?? max(1, min(12, (int) ($event->team_result_settings['team_size'] ?? 10)));
        $reserveCount = $finalization?->reserve_count ?? max(0, min(12 - $teamSize, (int) ($event->team_result_settings['reserve_count'] ?? 2)));
        $groups = collect($active ? [$gender] : [])->map(function ($gender) use ($age, $drafts, $grouped, $event, $regionIds, $teamSize, $reserveCount) {
            $key = $age.'-'.$gender;
            $draft = $drafts->get($key);
            $included = array_intersect($regionIds, $draft?->region_ids ?? $event->team_result_settings['region_ids'] ?? $regionIds);
            $selected = array_map('strval', $draft?->selected_keys ?? []);
            $snapshot = collect($draft?->snapshot ?? [])->keyBy(fn ($row) => (string) $row['id']);
            $threads = \App\Models\SelectionPreviewComment::where('event_id', $event->id)->where('group_key', $key)
                ->select('player_key')->selectRaw('COUNT(*) AS total')->groupBy('player_key')->pluck('total', 'player_key');
            $reviews = $this->bandReviews($snapshot);
            $selection = collect($selected)->map(fn ($key) => $snapshot->get($key))->filter()->sortBy(fn ($row) => $row['draft_position'] ?? $row['position'] ?? PHP_INT_MAX)->values()
                ->map(fn ($row, $position) => array_merge($row, ['note' => trim((string) ($draft->reasons[(string) $row['id']] ?? '')) !== '' || (int) $threads->get((string) $row['id'], 0) > 0,
                    'review' => $reviews[(string) $row['id']] ?? null, 'reserve' => $position >= $teamSize && $position < $teamSize + $reserveCount,
                    'over_capacity' => $position >= $teamSize + $reserveCount]));
            $reserveKeys = $selection->where('reserve', true)->pluck('id')->map('strval')->all();
            $seen = [];
            $cards = $grouped->filter(fn ($row) => $row['group']['key'] === $key && in_array((int) $row['team']->region_id, $included, true))
                ->map(function ($row) use ($selected, $snapshot, $reserveKeys, $draft, $threads, $reviews, &$seen) {
                    $team = app(TeamDrawSideResolver::class)->activeRoster($row['team']);
                    $members = $team->team_players->filter(fn ($member) => (int) $member->player_id > 0 && $member->player
                        && ! $team->team_players_no_profile->contains(fn ($slot) => (int) $slot->rank === (int) $member->rank && (int) $slot->player_profile === (int) $member->player_id))
                        ->map(fn ($member) => ['key' => (string) $member->player_id, 'type' => 'profile', 'id' => (int) $member->player_id,
                            'anchored' => $member->competition_anchor_id !== null, 'rank' => (int) $member->rank, 'name' => trim($member->player->name.' '.$member->player->surname)])
                        ->concat($team->team_players_no_profile->map(fn ($member) => ['key' => 'imported:'.$team->id.':'.($member->competition_anchor_id ?? $member->id),
                            'type' => 'imported', 'id' => (int) $member->id, 'anchored' => $member->competition_anchor_id !== null,
                            'rank' => (int) $member->rank, 'name' => trim($member->name.' '.$member->surname)]))
                        ->filter(fn ($member) => $member['rank'] >= 1 && $member['rank'] <= 12)->groupBy('rank');
                    $rows = collect(range(1, max(10, (int) $members->keys()->max())))->map(function ($rank) use ($members, $selected, $snapshot, $reserveKeys, $team, $draft, $threads, $reviews, &$seen) {
                        $players = $members->get($rank, collect())->map(function ($member) use ($selected, $snapshot, $reserveKeys, $team, $draft, $threads, $reviews, &$seen) {
                            $evidence = $snapshot->get($member['key']);
                            $matches = $evidence
                                && in_array((int) $team->id, array_map('intval', $evidence['source_team_ids'] ?? []), true);
                            if ($matches && isset($evidence['source_participants'])) {
                                $matches = collect($evidence['source_participants'])->contains(fn ($source) => (int) $source['team_id'] === (int) $team->id
                                    && $source['type'] === $member['type'] && (int) $source['id'] === $member['id']);
                            } elseif ($member['anchored']) {
                                $matches = false; // Legacy snapshots cannot prove a substitution's actual participant.
                            }
                            $isSelected = $matches && in_array($member['key'], $selected, true);
                            if ($isSelected) $seen[] = $member['key'];
                            return $member + ['selected' => $isSelected, 'reserve' => $isSelected && in_array($member['key'], $reserveKeys, true),
                                'note' => $matches && (trim((string) ($draft?->reasons[$member['key']] ?? '')) !== '' || (int) $threads->get($member['key'], 0) > 0),
                                'review' => $matches ? ($reviews[$member['key']] ?? null) : null, 'team' => $team->name];
                        });
                        return ['rank' => $rank, 'players' => $players];
                    });
                    return ['name' => $team->name, 'region' => $team->regions?->region_name, 'rows' => $rows];
                })->values();
            return ['name' => 'u/'.$age.' '.ucfirst($gender), 'key' => $key, 'draft' => $draft, 'teams' => $cards, 'selected' => $selection,
                'over_capacity' => $selection->where('over_capacity', true)->values(),
                'missing' => collect(array_diff($selected, $seen))->map(fn ($key) => $snapshot->get($key))->filter()->values()];
        })->filter(fn ($group) => $group['teams']->isNotEmpty() || $group['draft'] !== null)->values();

        return compact('ages', 'age', 'gender', 'activeGroupKey', 'navigationGroups', 'groups', 'teamSize', 'reserveCount', 'finalization', 'draftVersions');
    }

    private function bandReviews(\Illuminate\Support\Collection $snapshot): array
    {
        $candidates = $snapshot->filter(function ($row) {
            $ranks = array_unique(array_map('intval', $row['ranks'] ?? [$row['rank'] ?? 0]));
            $teams = array_unique(array_map('intval', $row['source_team_ids'] ?? []));
            $participants = collect($row['source_participants'] ?? [])->unique(fn ($source) => $source['team_id'].':'.$source['type'].':'.$source['id']);
            return count($ranks) === 1 && reset($ranks) > 0 && count($teams) === 1 && reset($teams) > 0
                && $participants->count() <= 1 && ! ($row['band_attribution_uncertain'] ?? false)
                && (int) ($row['draft_position'] ?? $row['position'] ?? 0) > 0;
        });
        $flags = [];
        foreach ($candidates as $lower) foreach ($candidates as $higher) {
            $lowRank = (int) ($lower['ranks'][0] ?? $lower['rank']);
            $highRank = (int) ($higher['ranks'][0] ?? $higher['rank']);
            if ((int) $lower['source_team_ids'][0] !== (int) $higher['source_team_ids'][0] || ceil($lowRank / 2) <= ceil($highRank / 2)
                || ($lower['draft_position'] ?? $lower['position']) >= ($higher['draft_position'] ?? $higher['position'])) continue;
            foreach ([[$lower, 'above', 'Placed above '.$higher['name'].' (roster '.$highRank.') from a higher roster band—review.'],
                [$higher, 'below', 'Placed below '.$lower['name'].' (roster '.$lowRank.') from a lower roster band—review.']] as [$row, $kind, $message]) {
                $id = (string) $row['id'];
                $flags[$id]['kind'] ??= $kind;
                $flags[$id]['messages'][] = $message;
            }
        }
        return $flags;
    }
}
