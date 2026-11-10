<?php

namespace App\Services;

use App\Domain\TeamDraw\RubberType;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamFixture;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use RuntimeException;

final class CavaliersTrialCandidates
{
    public const TRIALS_EVENT = 252;

    private const INTER_DISTRICTS_EVENT = 242;
    private const PLATTELAND_EVENT = 241;
    private const CAVALIERS_REGION = 'Cavaliers Platteland 2026';
    private const PLATTELAND_REGIONS = [
        'Overberg Primary Schools 2026',
        'Witzenberg/Breede Vallei/Langeberg Primary Schools 2026',
        'West Coast Primary Schools 2026',
    ];

    public function __construct(
        private EventTeamScope $scope,
        private TeamResultRankingService $ranking,
        private PlayerEligibilityService $eligibility,
        private SelectedTeamRosterStatus $rosterStatus,
    ) {}

    public function preview(Event $event, CategoryEvent $division): array
    {
        $group = $this->group($division->category?->name);
        abort_unless($group, 422, 'The division needs an age group and gender to match source rosters.');

        $interDistricts = Event::query()->find(self::INTER_DISTRICTS_EVENT);
        $platteland = Event::query()->find(self::PLATTELAND_EVENT);
        abort_unless($interDistricts?->published && $interDistricts->isTeam()
            && $interDistricts->name === 'Inter-Districts Tournament Primary Schools 2026'
            && $platteland?->published && $platteland->isTeam()
            && $platteland->name === 'Platteland Tennis Tournament Primary Schools 2026', 422,
            'Both source tournaments must be published before candidates can be reviewed.');

        $interTeams = $this->publishedTeams($interDistricts);
        $interRegionIds = $interDistricts->regions()->where('region_name', self::CAVALIERS_REGION)->pluck('team_regions.id')->map(fn ($id) => (int) $id);
        $interRoster = $this->divisionRoster($interTeams->whereIn('region_id', $interRegionIds), $group);
        abort_if($interRoster->isEmpty(), 422, 'The Cavaliers Inter-Districts roster for this division is unavailable.');

        $plattelandRegionIds = $platteland->regions()->whereIn('region_name', self::PLATTELAND_REGIONS)->pluck('team_regions.id')->map(fn ($id) => (int) $id);
        $plattelandTeams = $this->publishedTeams($platteland);
        $plattelandRoster = $this->divisionRoster($plattelandTeams->whereIn('region_id', $plattelandRegionIds), $group);
        $interPlayerIds = $interTeams->flatMap(fn (Team $team) => $this->members($team))->pluck('id')->unique();
        $plattelandRoster = $plattelandRoster->whereNotIn('id', $interPlayerIds);

        // Use the Platteland rules for this preview even on historical copies
        // without a saved preset. Never change the source event settings.
        $platteland = clone $platteland;
        $platteland->team_result_settings = array_replace($platteland->team_result_settings ?? [], [
            'criteria_preset' => TeamSelectionCriteria::PLATTELAND,
            'excluded_result_region_ids' => $plattelandTeams->pluck('region_id')
                ->diff($plattelandRegionIds)->values()->all(),
        ]);

        return [
            'candidates' => $this->ordered($interDistricts, $group, $interRegionIds, $interRoster, 'Inter-Districts')
                ->concat($this->ordered($platteland, $group, $plattelandRegionIds, $plattelandRoster, 'Platteland'))
                ->unique('id')->filter(function (array $candidate) use ($event) {
                    try {
                        $this->eligibility->assertEligible($candidate['id'], $event);
                        return true;
                    } catch (QueryException $exception) {
                        throw $exception;
                    } catch (RuntimeException) {
                        return false;
                    }
                })->values()->all(),
        ];
    }

    private function publishedTeams(Event $event): Collection
    {
        $teams = $this->scope->query($event)->where('published', true)
            ->with(['category.category', 'team_players.player', 'team_players_no_profile.profile'])->get();
        $status = $this->rosterStatus->classify($event, $teams);
        return $teams->each(function (Team $team) use ($status) {
            $team->setRelation('team_players', $team->team_players->reject(fn ($slot) => isset($status['profile'][$team->id][$slot->player_id])));
            $team->setRelation('team_players_no_profile', $team->team_players_no_profile->reject(fn ($slot) => isset($status['imported'][$team->id][$slot->id])));
        });
    }

    private function group(?string $name): ?string
    {
        return $this->ranking->group(new TeamFixture(['age' => $name]))['key'] ?? null;
    }

    private function divisionRoster(Collection $teams, string $group): Collection
    {
        return $teams->filter(fn (Team $team) => $this->group($team->category?->category?->name ?? $team->name) === $group)
            ->flatMap(fn (Team $team) => $this->members($team))
            ->filter(fn (array $member) => $member['rank'] > 0)
            ->sortBy([['rank', 'asc'], ['name', 'asc'], ['id', 'asc']])
            ->unique('id')->keyBy('id');
    }

    private function members(Team $team): Collection
    {
        $members = $team->noProfile
            ? $team->team_players_no_profile->map(fn ($member) => [$member->profile, $member->rank])
            : $team->team_players->map(fn ($member) => [$member->player, $member->rank]);

        return $members
            ->filter(fn (array $member) => $member[0])
            ->map(fn (array $member) => [
                'id' => (int) $member[0]->id,
                'name' => $member[0]->full_name,
                'rank' => (int) $member[1],
                'team_id' => (int) $team->id,
                'team' => $team->name,
            ]);
    }

    private function ordered(Event $source, string $group, Collection $regionIds, Collection $roster, string $label): Collection
    {
        if ($roster->isEmpty()) {
            return collect();
        }

        $ranked = $this->ranking->ranking($source, $group, $regionIds->all(), [RubberType::SINGLES, RubberType::REVERSE_SINGLES], publishedOnly: true)
            ->map(function (array $row) use ($roster) {
                $id = (int) ($row['source_participants'][0]['profile_id'] ?? 0);
                $member = $roster->get($id);
                if (! $member || $row['source_team_ids'] !== [$member['team_id']]) {
                    return null;
                }
                return array_replace($member, [
                    'rank' => $row['rank'],
                    'band' => $row['band_index'],
                    'wins' => $row['wins'],
                    'losses' => $row['losses'],
                    'starting_credit' => $row['starting_credit'],
                    'lost_all' => $row['rank'] >= 7 && $row['rank'] <= 10 && $row['wins'] === 0 && $row['losses'] > 0,
                ]);
            })->filter()->unique('id');

        // Within a band, use the result order first, then the roster order for
        // players whose published results cannot be resolved.
        return $ranked->concat($roster->whereNotIn('id', $ranked->pluck('id'))->map(fn (array $member) => $member + [
            'band' => (int) ceil($member['rank'] / 2),
            'wins' => null,
            'losses' => null,
            'starting_credit' => null,
            'lost_all' => false,
        ]))->sortBy('band')->map(fn (array $row) => [
            'id' => $row['id'],
            'name' => $row['name'],
            'source' => $label,
            'team' => $row['team'],
            'rank' => $row['rank'],
            'band' => (2 * $row['band'] - 1).'–'.(2 * $row['band']),
            'wins' => $row['wins'],
            'losses' => $row['losses'],
            'starting_credit' => $row['starting_credit'],
            'lost_all' => $label === 'Inter-Districts' && $row['lost_all'],
        ])->values();
    }
}
