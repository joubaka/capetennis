<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Team;

final class EventStandingsService
{
    private const METRICS = ['played', 'wins', 'draws', 'losses', 'points', 'rubber_wins', 'rubber_losses', 'sets_for', 'sets_against', 'games_for', 'games_against'];

    public function forEvent(Event $event, array $filters = [], bool $publishedOnly = false, ?int $drawId = null): array
    {
        abort_if($publishedOnly && !$event->standings_published, 404);
        $teams = Team::query()->without(['team_players', 'team_players_no_profile'])
            ->whereHas('category', fn ($query) => $query->where('event_id', $event->id))
            ->with('regions')->get()->keyBy('id');
        $teamIds = $teams->keys()->all();
        $regions = $event->regions()->get()->keyBy('id');
        $draws = $event->draws()->when($publishedOnly, fn ($query) => $query->where('published', true))
            ->when($drawId !== null, fn ($query) => $query->whereKey($drawId))
            ->with(['draw_types', 'categoryEvent.category', 'team_category'])->orderBy('drawName')->get();
        $sections = [];
        foreach ($draws as $draw) {
            $draw->setRelation('event', $event);
            if (!$draw->isTeamDraw()) { continue; }
            $category = (int) $draw->categoryEvent?->event_id === (int) $event->id ? $draw->categoryEvent->category?->name : null;
            $category ??= (int) $draw->team_category?->eventId === (int) $event->id ? $draw->team_category->ageGroup : null;
            $category ??= 'Unspecified';
            $label = ($category === 'Unspecified' ? $draw->drawName : $category.' '.$draw->drawName).' '.$draw->gender;
            $gender = preg_match('/\b(mixed)\b/i', $label) ? 'Mixed' : (preg_match('/\b(girls?|female|women)\b/i', $label) ? 'Girls / Women' : (preg_match('/\b(boys?|male|men)\b/i', $label) ? 'Boys / Men' : 'Unspecified'));
            $age = preg_match('/\b(?:u\s*[\/-]?\s*|under\s+)(\d+)\b/i', $label, $matches) ? 'U'.$matches[1] : 'Unspecified';
            $rows = app(TeamStandingsService::class)->forDraw($draw, $publishedOnly, $teamIds);
            $legacyRows = [];
            $results = app(TeamRubberResultService::class);
            // Legacy fixtures identify regions directly and have no team tie lifecycle.
            foreach ($draw->fixtures()->whereNull('team_tie_id')
                ->when($publishedOnly, fn ($query) => $query->publicDrawFixtures())
                ->with('teamResults')->get() as $fixture) {
                if (!$regions->has($fixture->region1) || !$regions->has($fixture->region2) || $fixture->region1 === $fixture->region2) { continue; }
                $fixture->setRelation('draw', $draw);
                $outcome = $results->outcome($fixture);
                foreach (['home' => $fixture->region1, 'away' => $fixture->region2] as $side => $regionId) {
                    $legacyRows[$regionId] ??= ['region_id' => $regionId, 'team_id' => null, 'name' => $regions[$regionId]->region_name, 'rank' => null] + array_fill_keys(self::METRICS, 0);
                    if (!$outcome['complete']) { continue; }
                    $other = $side === 'home' ? 'away' : 'home';
                    $legacyRows[$regionId]['points'] += $results->points($fixture, $outcome)[$side];
                    $legacyRows[$regionId]['rubber_wins'] += $outcome['winner'] === $side ? 1 : 0;
                    $legacyRows[$regionId]['rubber_losses'] += $outcome['winner'] === $other ? 1 : 0;
                    foreach (['sets', 'games'] as $metric) {
                        $legacyRows[$regionId][$metric.'_for'] += $outcome[$side.'_'.$metric];
                        $legacyRows[$regionId][$metric.'_against'] += $outcome[$other.'_'.$metric];
                    }
                }
            }
            $legacyRows = array_merge($rows, array_values($legacyRows));
            $drawRules = app(TeamEventRulesService::class)->forDraw($draw);
            $criteria = fn ($row) => $this->criteria($row, $drawRules);
            usort($legacyRows, fn ($a, $b) => ($criteria($b) <=> $criteria($a)) ?: strnatcasecmp($a['name'], $b['name']));
            $previousPoints = null;
            $legacyRank = 0;
            foreach ($legacyRows as $index => &$legacyRow) {
                if ($criteria($legacyRow) !== $previousPoints) { $legacyRank = $index + 1; }
                $legacyRow['rank'] = $legacyRank;
                $previousPoints = $criteria($legacyRow);
            }
            unset($legacyRow);
            $rows = $legacyRows;
            $sections[] = compact('draw', 'category', 'gender', 'age', 'rows');
        }
        $options = [];
        foreach (['gender', 'age', 'category'] as $key) {
            $options[$key] = collect($sections)->pluck($key)->unique()->sort()->values()->all();
        }
        $sections = array_values(array_filter($sections, fn ($section) => collect($filters)->every(fn ($value, $key) => !$value || $section[$key] === $value)));
        $overall = [];
        $ageGroups = [];
        $ageRuleSets = [];
        $ruleSets = [];
        foreach ($sections as $section) {
            $ruleSets[] = json_encode(app(TeamEventRulesService::class)->forDraw($section['draw']));
            $ageRuleSets[$section['age']][] = end($ruleSets);
            foreach ($section['rows'] as $row) {
                $team = $row['team_id'] ? $teams[$row['team_id']] : null;
                $regionId = $row['region_id'] ?? ($regions->has($team?->region_id) ? $team?->region_id : null);
                $key = $regionId ? 'region:'.$regionId : 'team:'.$team->id;
                $overall[$key] ??= ['name' => $regionId ? $regions[$regionId]->region_name : $row['name'], 'teams' => [], 'rank' => null] + array_fill_keys(self::METRICS, 0);
                $ageGroups[$section['age']][$key] ??= ['name' => $overall[$key]['name'], 'points' => 0,
                    'color' => \App\Support\RegionBadge::color($regionId ? $regions[$regionId] : null)];
                $ageGroups[$section['age']][$key]['points'] += $row['points'];
                if ($team) { $overall[$key]['teams'][$team->id] = true; }
                foreach (self::METRICS as $metric) { $overall[$key][$metric] += $row[$metric]; }
            }
        }
        $mixedRules = count(array_unique($ruleSets)) > 1;
        uksort($ageGroups, 'strnatcasecmp');
        $ageMixedRules = [];
        foreach ($ageGroups as $age => &$ageRows) {
            $ageMixedRules[$age] = count(array_unique($ageRuleSets[$age])) > 1;
            $ageRows = array_values($ageRows);
            usort($ageRows, fn ($a, $b) => ($ageMixedRules[$age] ? 0 : ($b['points'] <=> $a['points'])) ?: strnatcasecmp($a['name'], $b['name']));
        }
        unset($ageRows);
        $overall = array_values($overall);
        if ($mixedRules) {
            usort($overall, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        } else {
            $rules = $sections ? app(TeamEventRulesService::class)->forDraw($sections[0]['draw']) : app(TeamEventRulesService::class)->defaults();
            $criteria = fn ($row) => $this->criteria($row, $rules);
            usort($overall, fn ($a, $b) => ($criteria($b) <=> $criteria($a)) ?: strnatcasecmp($a['name'], $b['name']));
            $previous = null;
            $rank = 0;
            foreach ($overall as $index => &$row) {
                if ($criteria($row) !== $previous) { $rank = $index + 1; }
                $row['rank'] = $rank;
                $previous = $criteria($row);
            }
            unset($row);
        }
        $stats = ['draws' => count($sections), 'teams' => count(array_filter(array_unique(array_merge([], ...array_map(fn ($section) => array_column($section['rows'], 'team_id'), $sections))))),
            'ties' => array_sum(array_column($overall, 'played')) / 2,
            'rubbers' => array_sum(array_column($overall, 'rubber_wins'))];
        $breakdowns = [];
        foreach (['gender', 'age', 'category'] as $key) {
            $breakdowns[$key] = collect($sections)->groupBy($key)->map(fn ($group) => [
                'draws' => $group->count(), 'teams' => $group->flatMap(fn ($section) => array_column($section['rows'], 'team_id'))->filter()->unique()->count(),
                'ties' => $group->sum(fn ($section) => array_sum(array_column($section['rows'], 'played')) / 2),
                'rubbers' => $group->sum(fn ($section) => array_sum(array_column($section['rows'], 'rubber_wins'))),
            ])->all();
        }

        return compact('sections', 'overall', 'mixedRules', 'options', 'filters', 'stats', 'breakdowns', 'ageGroups', 'ageMixedRules');
    }

    private function criteria(array $row, array $rules): array
    {
        return array_map(fn ($key) => match ($key) {
            'tie_wins' => $row['wins'], 'rubber_difference' => $row['rubber_wins'] - $row['rubber_losses'],
            'set_difference' => $row['sets_for'] - $row['sets_against'], 'game_difference' => $row['games_for'] - $row['games_against'], default => $row['points'],
        }, $rules['standings_order']);
    }
}
