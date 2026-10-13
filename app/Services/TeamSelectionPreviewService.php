<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TeamFixture;
use App\Models\TeamResultSelectionDraft;

/** Current event rosters alongside private saved selection decisions. */
class TeamSelectionPreviewService
{
    public function preview(Event $event, ?int $requestedAge): array
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
        $drafts = TeamResultSelectionDraft::where('event_id', $event->id)->get()->keyBy('group_key');
        $ages = $grouped->pluck('group.age')->merge($drafts->keys()->map(fn ($key) => (int) explode('-', $key)[0]))->unique()->sort()->values();
        $age = $requestedAge ?? $ages->first();
        abort_if($requestedAge !== null && ! $ages->contains($requestedAge), 404);
        $regionIds = $event->regions()->pluck('team_regions.id')->map('intval')->all();
        $teamSize = max(1, min(12, (int) ($event->team_result_settings['team_size'] ?? 10)));
        $reserveCount = max(0, min(12 - $teamSize, (int) ($event->team_result_settings['reserve_count'] ?? 2)));
        $groups = collect(['boys', 'girls'])->map(function ($gender) use ($age, $drafts, $grouped, $event, $regionIds, $teamSize, $reserveCount) {
            $key = $age.'-'.$gender;
            $draft = $drafts->get($key);
            $included = array_intersect($regionIds, $draft?->region_ids ?? $event->team_result_settings['region_ids'] ?? $regionIds);
            $selected = array_map('strval', $draft?->selected_keys ?? []);
            $snapshot = collect($draft?->snapshot ?? [])->keyBy(fn ($row) => (string) $row['id']);
            $selection = collect($selected)->map(fn ($key) => $snapshot->get($key))->filter()->sortBy(fn ($row) => $row['draft_position'] ?? $row['position'] ?? PHP_INT_MAX)->values()
                ->map(fn ($row, $position) => array_merge($row, ['reserve' => $position >= $teamSize && $position < $teamSize + $reserveCount,
                    'over_capacity' => $position >= $teamSize + $reserveCount]));
            $reserveKeys = $selection->where('reserve', true)->pluck('id')->map('strval')->all();
            $seen = [];
            $cards = $grouped->filter(fn ($row) => $row['group']['key'] === $key && in_array((int) $row['team']->region_id, $included, true))
                ->map(function ($row) use ($selected, $snapshot, $reserveKeys, &$seen) {
                    $team = app(TeamDrawSideResolver::class)->activeRoster($row['team']);
                    $members = $team->team_players->filter(fn ($member) => (int) $member->player_id > 0 && $member->player
                        && ! $team->team_players_no_profile->contains(fn ($slot) => (int) $slot->rank === (int) $member->rank && (int) $slot->player_profile === (int) $member->player_id))
                        ->map(fn ($member) => ['key' => (string) $member->player_id, 'type' => 'profile', 'id' => (int) $member->player_id,
                            'anchored' => $member->competition_anchor_id !== null, 'rank' => (int) $member->rank, 'name' => trim($member->player->name.' '.$member->player->surname)])
                        ->concat($team->team_players_no_profile->map(fn ($member) => ['key' => 'imported:'.$team->id.':'.($member->competition_anchor_id ?? $member->id),
                            'type' => 'imported', 'id' => (int) $member->id, 'anchored' => $member->competition_anchor_id !== null,
                            'rank' => (int) $member->rank, 'name' => trim($member->name.' '.$member->surname)]))
                        ->filter(fn ($member) => $member['rank'] >= 1 && $member['rank'] <= 12)->groupBy('rank');
                    $rows = collect(range(1, max(10, (int) $members->keys()->max())))->map(function ($rank) use ($members, $selected, $snapshot, $reserveKeys, $team, &$seen) {
                        $players = $members->get($rank, collect())->map(function ($member) use ($selected, $snapshot, $reserveKeys, $team, &$seen) {
                            $evidence = $snapshot->get($member['key']);
                            $isSelected = in_array($member['key'], $selected, true) && $evidence
                                && in_array((int) $team->id, array_map('intval', $evidence['source_team_ids'] ?? []), true);
                            if ($isSelected && isset($evidence['source_participants'])) {
                                $isSelected = collect($evidence['source_participants'])->contains(fn ($source) => (int) $source['team_id'] === (int) $team->id
                                    && $source['type'] === $member['type'] && (int) $source['id'] === $member['id']);
                            } elseif ($member['anchored']) {
                                $isSelected = false; // Legacy snapshots cannot prove a substitution's actual participant.
                            }
                            if ($isSelected) $seen[] = $member['key'];
                            return $member + ['selected' => $isSelected, 'reserve' => $isSelected && in_array($member['key'], $reserveKeys, true), 'team' => $team->name];
                        });
                        return ['rank' => $rank, 'players' => $players];
                    });
                    return ['name' => $team->name, 'region' => $team->regions?->region_name, 'rows' => $rows];
                })->values();
            return ['name' => 'u/'.$age.' '.ucfirst($gender), 'draft' => $draft, 'teams' => $cards, 'selected' => $selection,
                'over_capacity' => $selection->where('over_capacity', true)->values(),
                'missing' => collect(array_diff($selected, $seen))->map(fn ($key) => $snapshot->get($key))->filter()->values()];
        })->filter(fn ($group) => $group['teams']->isNotEmpty() || $group['draft'] !== null)->values();

        return compact('ages', 'age', 'groups', 'teamSize', 'reserveCount');
    }
}
