<?php

namespace App\Services\Performance;

use App\Models\Event;
use App\Services\EventTeamScope;
use Illuminate\Support\Facades\DB;

/** Read-only projection of saved estimates and actual category membership. */
class PlayerRatingLeaderboardService
{
    private const MEMBER_LIMIT = 10000;

    public function build(?Event $event, string $selected = '', string $search = ''): array
    {
        $snapshot = app(PlayerAbilitySnapshotStore::class)->current();
        $ratings = app(PlayerSharedAbilityService::class)->badgeSnapshot();
        $fields = DB::table('category_events as ce')->join('categories as c', 'c.id', '=', 'ce.category_id')
            ->join('events as e', 'e.id', '=', 'ce.event_id')
            ->when($event, fn ($q) => $q->where('ce.event_id', $event->id))
            ->limit(10001)->get(['ce.id', 'ce.event_id', 'c.name']);
        if ($fields->count() > self::MEMBER_LIMIT) {
            return ['rows' => collect(), 'cohorts' => [], 'snapshot' => $snapshot, 'limitReason' => 'Too many category contexts to display safely. Narrow the view to an event.'];
        }
        $events = Event::query()->with('eventTypeModel')->whereIn('id', $fields->pluck('event_id')->unique())->get(['id', 'eventType'])->keyBy('id');
        $contexts = [];
        foreach ($fields as $field) {
            $division = app(PlayerPerformancePilotService::class)->division($field->name);
            $label = $division['reason'] && $division['reason'] !== 'No explicit A/B division in category name'
                ? 'Unresolved category: '.$field->name
                : app(PlayerSharedAbilityService::class)->cohort(($events[$field->event_id]->frontend_type_view === 'masters' ? 'masters · ' : '').$division['cohort']);
            $contexts[$field->id] = $label;
        }
        $cohorts = array_values(array_unique([...array_values($contexts), ...($event ? [] : array_keys($snapshot['cohorts']))]));
        if ($event && app(EventTeamScope::class)->query($event)->whereNull('category_event_id')->exists()) {
            $cohorts[] = 'Unresolved legacy category';
        }
        sort($cohorts, SORT_NATURAL | SORT_FLAG_CASE);
        if (!$event && $selected === '') {
            return compact('cohorts', 'snapshot') + ['rows' => collect(), 'limitReason' => null];
        }
        $fieldIds = array_keys(array_filter($contexts, fn ($cohort) => !$selected || $cohort === $selected));
        $members = [];
        $add = function ($id, string $name, string $cohort, string $identity, ?string $region = null) use (&$members, $ratings, $event) {
            $key = $cohort.'|'.$identity;
            $estimate = collect($ratings[$id] ?? [])->first(fn ($r) => $r['cohort'] === $cohort);
            $regions = $members[$key]['regions'] ?? [];
            if ($event && trim((string) $region) !== '') {
                $regions[] = trim($region);
            }
            $regions = array_values(array_unique($regions));
            sort($regions, SORT_NATURAL | SORT_FLAG_CASE);
            $members[$key] = ['player_id' => $id, 'name' => $name, 'cohort' => $cohort, 'identity' => $identity, 'regions' => $regions,
                'rating' => $estimate, 'component' => $estimate['component'] ?? 'Unrated'];
        };
        $individual = DB::table('category_event_registrations as cer')
            ->join('player_registrations as pr', 'pr.registration_id', '=', 'cer.registration_id')
            ->join('players as p', 'p.id', '=', 'pr.player_id')->whereIn('cer.category_event_id', $fieldIds)
            ->limit(self::MEMBER_LIMIT + 1)
            ->get(['p.id', 'p.name', 'p.surname', 'cer.category_event_id']);
        $teamQuery = $event ? app(EventTeamScope::class)->query($event)->withoutEagerLoads() : DB::table('teams');
        $teams = $teamQuery->when($selected === 'Unresolved legacy category', fn ($q) => $q->whereNull('category_event_id'))
            ->when(($selected && $selected !== 'Unresolved legacy category') || !$event, fn ($q) => $q->whereIn('category_event_id', $fieldIds))
            ->limit(self::MEMBER_LIMIT + 1)->get(['id', 'category_event_id', 'region_id']);
        $teamContexts = $teams->mapWithKeys(fn ($team) => [$team->id => $contexts[$team->category_event_id] ?? 'Unresolved legacy category'])->all();
        $regionNames = $event ? DB::table('team_regions')->whereIn('id', $teams->pluck('region_id')->filter()->unique())
            ->pluck('short_name', 'id') : collect();
        $teamRegions = $teams->mapWithKeys(fn ($team) => [$team->id => $regionNames[$team->region_id] ?? null])->all();
        $roster = DB::table('team_players as tp')->join('players as p', 'p.id', '=', 'tp.player_id')
            ->whereIn('tp.team_id', array_keys($teamContexts))->limit(self::MEMBER_LIMIT + 1)->get(['p.id', 'p.name', 'p.surname', 'tp.team_id']);
        $imported = DB::table('no_profile_team_players as np')->leftJoin('players as p', 'p.id', '=', 'np.player_profile')
            ->whereIn('np.team_id', array_keys($teamContexts))->limit(self::MEMBER_LIMIT + 1)
            ->get(['np.id', 'np.name', 'np.surname', 'np.team_id', 'p.id as player_profile', 'p.name as profile_name', 'p.surname as profile_surname']);
        if ($individual->count() + $roster->count() + $imported->count() > self::MEMBER_LIMIT || $teams->count() > self::MEMBER_LIMIT) {
            return compact('cohorts', 'snapshot') + ['rows' => collect(), 'limitReason' => 'This selection exceeds the safe display limit. Choose a smaller cohort or event.'];
        }
        foreach ($individual as $member) { $add($member->id, trim($member->name.' '.$member->surname), $contexts[$member->category_event_id], 'p:'.$member->id); }
        foreach ($roster as $member) { $add($member->id, trim($member->name.' '.$member->surname), $teamContexts[$member->team_id], 'p:'.$member->id, $teamRegions[$member->team_id]); }
        foreach ($imported as $member) {
            $id = $member->player_profile ? (int) $member->player_profile : null;
            $name = $id ? trim($member->profile_name.' '.$member->profile_surname) : trim($member->name.' '.$member->surname);
            $add($id, $name, $teamContexts[$member->team_id], $id ? 'p:'.$id : 'np:'.$member->id, $teamRegions[$member->team_id]);
        }
        if (!$event) {
            $snapshotIds = [];
            foreach ($ratings as $id => $estimates) {
                if (!isset($members[$selected.'|p:'.$id]) && collect($estimates)->contains(fn ($r) => $r['cohort'] === $selected)) {
                    $snapshotIds[] = (int) $id;
                }
            }
            foreach (DB::table('players')->whereIn('id', $snapshotIds)->limit(self::MEMBER_LIMIT + 1)->get(['id', 'name', 'surname']) as $player) {
                $add((int) $player->id, trim($player->name.' '.$player->surname), $selected, 'p:'.$player->id);
            }
        }
        if (count($members) > self::MEMBER_LIMIT) {
            return compact('cohorts', 'snapshot') + ['rows' => collect(), 'limitReason' => 'This selection exceeds the safe display limit. Choose a smaller cohort or event.'];
        }
        $rows = collect($members)->sort(fn ($a, $b) => strnatcasecmp($a['cohort'], $b['cohort'])
            ?: (($a['rating'] === null) <=> ($b['rating'] === null))
            ?: strnatcasecmp($a['component'], $b['component'])
            ?: (($b['rating']['score'] ?? 0) <=> ($a['rating']['score'] ?? 0))
            ?: strnatcasecmp($a['name'], $b['name']) ?: strcmp($a['identity'], $b['identity']))->values();
        $positions = [];
        $rows = $rows->map(function ($row) use (&$positions) {
            $group = $row['cohort'].'|'.$row['component'];
            $row['position'] = $row['rating'] ? ($positions[$group] = ($positions[$group] ?? 0) + 1) : null;
            return $row;
        });
        $rows = $rows->filter(function ($row) use ($search) {
            foreach (preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $token) {
                if (mb_stripos($row['name'], $token) === false) { return false; }
            }
            return true;
        })->values();
        return compact('rows', 'cohorts', 'snapshot') + ['limitReason' => null];
    }
}
