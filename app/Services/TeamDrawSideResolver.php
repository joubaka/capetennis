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
            return $draw->teams_in_draw->map(fn ($team) => $this->side($draw, $team));
        }
        return collect(array_keys($map))->map(fn ($id) => $this->side($draw, Team::findOrFail($id)));
    }

    public function side(Draw $draw, ?Team $team, ?int $round = null, ?int $fixtureId = null): ?Team
    {
        if (!$team) return null;
        $map = $draw->team_draw_selection['mixed_sides'] ?? [];
        if (!$map) {
            if ($draw->team_draw_selection && ((int) $team->category?->event_id !== (int) $draw->event_id
                || !in_array((int) $team->category_event_id, $draw->team_draw_selection['category_ids'], true))) {
                throw new \InvalidArgumentException('A source team no longer belongs to the selected event and categories.');
            }
            return $this->activeRoster($team, $round, $fixtureId, $draw->id);
        }
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
        return $this->combine($boys, $girls, $round, $fixtureId, $draw->id);
    }

    public function categoryKey(string $name): array
    {
        $name = mb_strtolower(trim($name));
        if (!preg_match('/^(.*?)\s*(boys|girls)(.*)$/u', $name, $m)) {
            return ['group' => $name, 'gender' => null];
        }
        $division = trim(preg_replace('/^[\s\-–]+/u', '', $m[3]));
        if ($division === 'a division') $division = '';
        $age = trim($m[1]);
        if (preg_match('/^u\s*\/?\s*(\d+)$/', $age, $ageMatch)) $age = 'u/'.$ageMatch[1];
        return ['group' => $age.($division ? ' '.$division : ''), 'gender' => $m[2]];
    }

    public function combine(Team $boys, Team $girls, ?int $round = null, ?int $fixtureId = null, ?int $drawId = null): Team
    {
        $boys = $this->activeRoster($boys, $round, $fixtureId, $drawId);
        $girls = $this->activeRoster($girls, $round, $fixtureId, $drawId);
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

    public function activeRoster(Team $team, ?int $round = null, ?int $fixtureId = null, ?int $drawId = null): Team
    {
        $copy = clone $team;
        $profiles = $team->team_players->map(fn ($member) => clone $member);
        $imported = $team->team_players_no_profile->map(fn ($member) => clone $member);
        foreach ($team->competitionSubstitutions as $substitution) {
            $data = $substitution->details;
            if (($data['scope'] ?? 'next') === 'specific' && !in_array($fixtureId, $data['selected_ids'], true)) continue;
            if ($round !== null && in_array($drawId, $data['existing_draw_ids'] ?? [], true)
                && ($data['scope'] ?? 'next') === 'round' && $round < $data['from_round']) continue;
            $rank = $data['source_rank'];
            $profiles = $profiles->reject(fn ($member) => (int) $member->rank === (int) $rank)->values();
            $imported = $imported->reject(fn ($member) => (int) $member->rank === (int) $rank)->values();
            if ($data['new_type'] === 'profile') {
                $member = new \App\Models\TeamPlayer(['team_id' => $team->id, 'player_id' => $data['new_identity_id'], 'rank' => $rank, 'pay_status' => 0]);
                $member->setRelation('player', \App\Models\Player::query()->when(\Illuminate\Support\Facades\DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->find($data['new_identity_id']));
                $profiles->push($member);
            } else {
                $member = clone \App\Models\NoProfileTeamPlayer::query()->when(\Illuminate\Support\Facades\DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->findOrFail($data['new_identity_id']);
                $member->team_id = $team->id;
                $member->rank = $rank;
                $imported->push($member);
            }
            $member->setAttribute('competition_anchor_type', $data['anchor_type']);
            $member->setAttribute('competition_anchor_id', $data['anchor_id']);
        }
        $copy->setRelation('team_players', $profiles);
        $copy->setRelation('team_players_no_profile', $imported);
        return $copy;
    }

    public function label(Draw $draw, ?Team $team): ?string
    {
        if (!$team) return null;
        $entry = $draw->team_draw_selection['mixed_sides'][$team->id] ?? null;
        return $entry ? $entry['name'] : $team->name;
    }

    public function assertMixedSources(Draw $draw, int $teamId, array $profiles, array $imported): void
    {
        $entry = $draw->team_draw_selection['mixed_sides'][$teamId] ?? null;
        if (!$entry) return;
        $counts = [];
        foreach (['boys', 'girls'] as $gender) {
            $source = Team::findOrFail($entry[$gender]);
            $history = $source->competitionSubstitutions->pluck('details');
            $sourceProfiles = array_merge($source->team_players->pluck('player_id')->all(), $history->where('new_type', 'profile')->pluck('new_identity_id')->all());
            $sourceImported = array_merge($source->team_players_no_profile->pluck('id')->all(), $history->where('new_type', 'imported')->pluck('new_identity_id')->all());
            $counts[$gender] = count(array_intersect($profiles, $sourceProfiles)) + count(array_intersect($imported, $sourceImported));
        }
        if ($counts['boys'] !== 1 || $counts['girls'] !== 1 || count($profiles) + count($imported) !== 2) {
            throw new \InvalidArgumentException('A mixed pair must contain exactly one player from each selected boys and girls source roster.');
        }
    }
}
