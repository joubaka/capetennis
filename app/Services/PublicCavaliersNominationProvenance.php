<?php

namespace App\Services;

use App\Models\{Event, TeamFixture};
use App\Support\EventTypePresentation;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Public roster provenance only; never reads private candidate/finalization previews. */
final class PublicCavaliersNominationProvenance
{
    public function __construct(private EventTeamScope $scope, private SelectedTeamRosterStatus $roles, private TeamResultRankingService $groups) {}

    public function forEvent(Event $event): array
    {
        if ((int) $event->id !== CavaliersTrialCandidates::TRIALS_EVENT || ! $event->published || EventTypePresentation::template($event) !== 'cavaliers_trials') return [];
        $divisions = $event->eventCategories->filter(fn ($division) => (int) $division->event_id === (int) $event->id && $division->nominations_published);
        if ($divisions->count() > 100) return [];
        $nominees = $divisions->flatMap(fn ($division) => $division->nominations->where('event_id', $event->id)->pluck('player_id'))->filter()->unique();
        if ($nominees->isEmpty() || $nominees->count() > 3200) return [];
        $priorGroups = $divisions->mapWithKeys(function ($division) {
            $group = $this->group($division->category?->name);
            return [$division->id => $group && $group['age'] > 1 ? ($group['age'] - 1).'-'.explode('-', $group['key'], 2)[1] : null];
        });
        $evidence = [];
        foreach (Event::whereIn('id', [242, 241])->where('published', true)->get() as $source) {
            $label = (int) $source->id === 242 && app(TeamSelectionCriteria::class)->isInterDistricts($source) ? 'Inter-Districts'
                : ((int) $source->id === 241 && $source->isTeam() && $source->name === 'Platteland Tennis Tournament Primary Schools 2026' ? 'Platteland' : null);
            if (! $label) continue;
            $teams = $this->scope->query($source)->where('published', true)
                ->where(fn ($query) => $query->whereHas('team_players', fn ($members) => $members->whereIn('player_id', $nominees))
                    ->orWhereHas('team_players_no_profile', fn ($members) => $members->whereIn('player_profile', $nominees)))
                ->with(['category.category', 'regions',
                    'team_players' => fn ($query) => $query->whereIn('player_id', $nominees),
                    'team_players_no_profile' => fn ($query) => $query->whereIn('player_profile', $nominees),
                ])->limit(501)->get();
            if ($teams->count() > 500) continue;
            try { $roles = $this->roles->classify($source, $teams); }
            catch (HttpException) { continue; } // Optional metadata stays absent if public role resolution is unavailable.
            foreach ($teams as $team) {
                $group = $this->group($team->category?->category?->name ?? $team->name)['key'] ?? null;
                if (! $group || ! $priorGroups->contains($group)) continue;
                $region = trim($team->regions?->short_name ?: ($team->regions?->region_name ?? ''));
                $members = $team->noProfile ? $team->team_players_no_profile : $team->team_players;
                foreach ($members as $member) {
                    $playerId = (int) ($team->noProfile ? $member->player_profile : $member->player_id);
                    $reserve = $team->noProfile ? isset($roles['imported'][$team->id][$member->id]) : isset($roles['profile'][$team->id][$playerId]);
                    if ($reserve || $member->rank < 1 || ! $nominees->contains($playerId)) continue;
                    $evidence[$group][$playerId][$source->id.':'.$team->region_id] = $region !== '' ? ['region' => $region, 'source' => $label] : null;
                }
            }
        }
        $badges = [];
        foreach ($divisions as $division) {
            foreach ($division->nominations->where('event_id', $event->id) as $nominee) {
                $rows = $evidence[$priorGroups[$division->id] ?? ''][$nominee->player_id] ?? [];
                if (count($rows) === 1 && ($badge = reset($rows))) $badges[$division->id][$nominee->id] = $badge;
            }
        }
        return $badges;
    }

    private function group(?string $name): ?array
    {
        return $this->groups->group(new TeamFixture(['age' => $name]));
    }
}
