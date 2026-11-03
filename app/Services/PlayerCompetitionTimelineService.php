<?php

namespace App\Services;

use App\Models\CategoryEventRegistration;
use App\Models\Player;
use App\Models\Position;
use App\Models\RankingScores;
use App\Models\SeriesRanking;
use App\Models\TeamFixturePlayer;
use App\Domain\Ranking\Enums\RankingStatus;

/**
 * Canonical read model for a player's competition history.
 * Existing entry, placement, ranking, and team-draw records remain the source
 * of truth; this class only composes bounded, publication-aware reads.
 */
class PlayerCompetitionTimelineService
{
    public function for(Player $player, int $limit = 50, bool $dashboardOnly = false): array
    {
        $limit = max(1, min(100, $limit));

        $entries = $dashboardOnly ? collect() : CategoryEventRegistration::query()
            ->with(['categoryEvent.event', 'categoryEvent.category', 'registration.players'])
            ->whereHas('players', fn ($query) => $query->whereKey($player->id))
            ->latest('id')->limit($limit)->get();

        $placements = Position::query()
            ->with(['categoryEvent.event', 'categoryEvent.category'])
            ->where('player_id', $player->id)
            ->whereHas('categoryEvent.event', fn ($query) => $query->where('published', true)->where('results_published', true))
            ->latest('id')->limit($limit)->get();

        $rankingScores = $dashboardOnly ? collect() : RankingScores::query()
            ->with(['rankingList'])
            ->where('player_id', $player->id)
            ->latest('updated_at')->limit($limit)->get();

        $seriesRankings = SeriesRanking::query()
            ->with(['series', 'category', 'rankingList'])
            ->where('player_id', $player->id)
            ->whereHas('series', fn ($query) => $query->where('leaderboard_published', true))
            ->where(function ($scope) {
                $scope->where('status', RankingStatus::Published->value)->whereNotNull('run_id')->where('run_id', function ($query) {
                $query->from('series_rankings as latest')->select('latest.run_id')
                    ->whereColumn('latest.series_id', 'series_rankings.series_id')
                    ->where('latest.status', RankingStatus::Published->value)->whereNotNull('latest.run_id')
                    ->orderByDesc('latest.published_at')->orderByDesc('latest.updated_at')->limit(1);
                });
                $scope->orWhere(function ($legacy) {
                    $legacy->whereNull('run_id')->where(fn ($state) => $state->where('status', RankingStatus::Published->value)
                        ->orWhere(fn ($old) => $old->whereNull('status')->whereNotNull('published_at')))
                        ->whereNotExists(function ($current) {
                            $current->selectRaw('1')->from('series_rankings as modern')
                                ->whereColumn('modern.series_id', 'series_rankings.series_id')
                                ->where('modern.status', RankingStatus::Published->value)->whereNotNull('modern.run_id');
                        });
                });
            })
            ->latest('published_at')->limit($limit)->get();
        $seriesRankings = $seriesRankings->filter(fn ($ranking) => app(\App\Domain\Ranking\Services\RankingTeamEligibilityService::class)
            ->publiclyRanked(collect([$ranking]), $ranking->series)->isNotEmpty())->values();

        $teamAppearances = $dashboardOnly ? collect() : TeamFixturePlayer::query()
            ->with(['fixture.draw.event', 'fixture.venue', 'fixture.teamResults'])
            ->where(function ($query) use ($player): void {
                $query->where('team1_id', $player->id)->orWhere('team2_id', $player->id);
            })
            ->whereHas('fixture.draw', fn ($query) => $query->where('published', true))
            ->latest('id')->limit($limit)->get();

        return compact('entries', 'placements', 'rankingScores', 'seriesRankings', 'teamAppearances');
    }
}
