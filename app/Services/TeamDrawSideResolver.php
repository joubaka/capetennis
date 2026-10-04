<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\Team;
use Illuminate\Support\Collection;

/** Resolves a draw side from existing rosters; never persists composite teams. */
class TeamDrawSideResolver
{
    public function teams(Draw $draw): Collection
    {
        $map = $draw->team_draw_selection['mixed_sides'] ?? [];
        if (!$map) {
            return $draw->teams_in_draw;
        }
        return collect(array_keys($map))->map(fn ($id) => $this->side($draw, Team::findOrFail($id)));
    }

    public function side(Draw $draw, ?Team $team): ?Team
    {
        if (!$team) return null;
        $map = $draw->team_draw_selection['mixed_sides'] ?? [];
        if (!$map) return $team;
        $entry = $map[$team->id] ?? null;
        if (!$entry) throw new \InvalidArgumentException('Team is not a selected mixed side.');
        $sources = Team::with(['category.category', 'team_players.player', 'team_players_no_profile'])
            ->whereIn('id', [$entry['boys'], $entry['girls']])->get()->keyBy('id');
        $boys = $sources->get($entry['boys']);
        $girls = $sources->get($entry['girls']);
        foreach ([$boys, $girls] as $source) {
            if (!$source || (int) $source->category?->event_id !== (int) $draw->event_id
                || (int) $source->region_id !== (int) $entry['region_id']
                || !in_array((int) $source->category_event_id, $draw->team_draw_selection['category_ids'], true)) {
                throw new \InvalidArgumentException('A mixed side source no longer belongs to the selected event, category and region.');
            }
        }
        if ($this->categoryKey($boys->category->category->name)['gender'] !== 'boys'
            || $this->categoryKey($girls->category->category->name)['gender'] !== 'girls'
            || $this->categoryKey($boys->category->category->name)['group'] !== $this->categoryKey($girls->category->category->name)['group']) {
            throw new \InvalidArgumentException('Mixed source categories must be boys and girls in the same age and division.');
        }
        return $this->combine($boys, $girls);
    }

    public function categoryKey(string $name): array
    {
        $name = mb_strtolower(trim($name));
        if (!preg_match('/^(u\s*\/\s*\d+)\s*(boys|girls)(.*)$/u', $name, $m)) {
            return ['group' => $name, 'gender' => null];
        }
        $division = trim(preg_replace('/^[\s\-–]+/u', '', $m[3]));
        if ($division === 'a division') $division = '';
        return ['group' => 'u/'.preg_replace('/\D/', '', $m[1]).($division ? ' '.$division : ''), 'gender' => $m[2]];
    }

    public function combine(Team $boys, Team $girls): Team
    {
        $side = clone $boys;
        $side->name = $boys->name.' + '.$girls->name;
        $profiles = collect(); $imported = collect();
        foreach ([1 => $boys, 2 => $girls] as $offset => $source) {
            foreach ($source->team_players as $member) {
                $copy = clone $member;
                $copy->rank = ((int) $member->rank - 1) * 2 + $offset;
                $profiles->push($copy);
            }
            foreach ($source->team_players_no_profile as $member) {
                $copy = clone $member;
                $copy->rank = ((int) $member->rank - 1) * 2 + $offset;
                $imported->push($copy);
            }
        }
        $side->setRelation('team_players', new \Illuminate\Database\Eloquent\Collection($profiles->all()));
        $side->setRelation('team_players_no_profile', new \Illuminate\Database\Eloquent\Collection($imported->all()));
        return $side;
    }

    public function label(Draw $draw, ?Team $team): ?string
    {
        if (!$team) return null;
        $entry = $draw->team_draw_selection['mixed_sides'][$team->id] ?? null;
        return $entry ? $entry['name'] : $team->name;
    }
}
