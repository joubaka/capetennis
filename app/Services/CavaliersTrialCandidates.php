<?php

namespace App\Services;

use App\Domain\TeamDraw\RubberType;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamFixture;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use RuntimeException;

final class CavaliersTrialCandidates
{
    public const TRIALS_EVENT = 252;

    private const INTER_DISTRICTS_EVENT = 242;
    public const SETUP_KEY = 'cavaliers_inter_districts_setup';

    public function __construct(
        private EventTeamScope $scope,
        private TeamResultRankingService $ranking,
        private PlayerEligibilityService $eligibility,
        private SelectedTeamRosterStatus $rosterStatus,
        private PlattelandFinalizedTrialCandidates $platteland,
    ) {}

    private function source(): Event
    {
        $source = Event::query()->find(self::INTER_DISTRICTS_EVENT);
        abort_unless($source?->published && app(TeamSelectionCriteria::class)->isInterDistricts($source), 422,
            'The Inter-Districts source tournament must be published before candidates can be reviewed.');

        return $source;
    }

    public function setup(Event $event, CategoryEvent $division): array
    {
        return $this->settings($this->source(), $event, $division);
    }

    private function settings(Event $source, Event $event, CategoryEvent $division): array
    {
        $regions = $source->regions()->orderBy('region_name')->get(['team_regions.id', 'region_name'])
            ->map(fn ($region) => ['id' => (int) $region->id, 'name' => $region->region_name]);
        $eden = $regions->filter(fn (array $region) => preg_match('/\beden\b/i', $region['name']))->pluck('id');
        $available = $regions->pluck('id');
        $saved = $event->team_result_settings[self::SETUP_KEY][$division->id] ?? [];

        return [
            'regions' => $regions->all(),
            'region_ids' => $available->intersect($saved['region_ids'] ?? $available->diff($eden))->values()->all(),
            'excluded_result_region_ids' => $available->intersect($saved['excluded_result_region_ids'] ?? $eden)->values()->all(),
        ];
    }

    public function preview(Event $event, CategoryEvent $division, User $actor): array
    {
        $group = $this->group($division->category?->name);
        abort_unless($group, 422, 'The division needs an age group and gender to match source rosters.');

        $source = $this->source();
        $setup = $this->settings($source, $event, $division);
        $regionIds = collect($setup['region_ids']);
        $teams = $this->publishedTeams($source);
        $divisionTeams = $teams->whereIn('region_id', $regionIds)
            ->filter(fn (Team $team) => $this->group($team->category?->category?->name ?? $team->name) === $group);
        $missingRegions = $regionIds->diff($divisionTeams->pluck('region_id'));
        $roster = $this->divisionRoster($divisionTeams);

        // Apply the Platteland band rules to this Inter-Districts preview only.
        $source = clone $source;
        $source->team_result_settings = array_replace($source->team_result_settings ?? [], [
            'criteria_preset' => TeamSelectionCriteria::PLATTELAND,
            'excluded_result_region_ids' => $setup['excluded_result_region_ids'],
        ]);
        $platteland = $this->platteland->preview($group, $actor);

        return [
            'candidate_regions' => collect($setup['regions'])->whereIn('id', $regionIds)->pluck('name')->values()->all(),
            'excluded_result_regions' => collect($setup['regions'])->whereIn('id', $setup['excluded_result_region_ids'])->pluck('name')->values()->all(),
            'missing_regions' => collect($setup['regions'])->whereIn('id', $missingRegions)->pluck('name')->values()->all(),
            'platteland_notice' => $platteland['notice'],
            'candidates' => $this->ordered($source, $group, $regionIds, $roster)
                ->concat($platteland['candidates'])->unique('id')
                ->filter(function (array $candidate) use ($event) {
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

    private function divisionRoster(Collection $teams): Collection
    {
        return $teams->flatMap(fn (Team $team) => $this->members($team))
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

    private function ordered(Event $source, string $group, Collection $regionIds, Collection $roster): Collection
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
                    'lost_all' => $row['wins'] === 0 && $row['losses'] > 0,
                    'review_adjacent_band' => ! empty($row['platteland_manual_review']),
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
            'review_adjacent_band' => false,
        ]))->sortBy('band')->map(fn (array $row) => [
            'id' => $row['id'],
            'name' => $row['name'],
            'source' => 'Inter-Districts',
            'team' => $row['team'],
            'rank' => $row['rank'],
            'band' => (2 * $row['band'] - 1).'–'.(2 * $row['band']),
            'wins' => $row['wins'],
            'losses' => $row['losses'],
            'starting_credit' => $row['starting_credit'],
            'lost_all' => $row['lost_all'],
            'review_adjacent_band' => $row['review_adjacent_band'],
        ])->values();
    }
}
