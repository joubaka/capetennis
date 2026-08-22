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
    public function for(Player $player, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        $entries = CategoryEventRegistration::query()
            ->with(['categoryEvent.event', 'categoryEvent.category', 'registration.players'])
            ->whereHas('players', fn ($query) => $query->whereKey($player->id))
            ->latest('id')->limit($limit)->get();

        $placements = Position::query()
            ->with(['categoryEvent.event', 'categoryEvent.category'])
            ->where('player_id', $player->id)
            ->whereHas('categoryEvent.event', fn ($query) => $query->where('published', true)->where('results_published', true))
            ->latest('id')->limit($limit)->get();

        $rankingScores = RankingScores::query()
            ->with(['rankingList'])
            ->where('player_id', $player->id)
            ->latest('updated_at')->limit($limit)->get();

        $seriesRankings = SeriesRanking::query()
            ->with(['series', 'category', 'rankingList'])
            ->where('player_id', $player->id)
            // The canonical publication workflow records status=published.
            // Keep the timestamp fallback for older rows created before the
            // lifecycle column was introduced.
            ->where(function ($query): void {
                $query->where('status', RankingStatus::Published->value)
                    ->orWhereNotNull('published_at');
            })
            ->latest('published_at')->limit($limit)->get();

        $teamAppearances = TeamFixturePlayer::query()
            ->with(['fixture.draw.event', 'fixture.venue', 'fixture.teamResults'])
            ->where(function ($query) use ($player): void {
                $query->where('team1_id', $player->id)->orWhere('team2_id', $player->id);
            })
            ->whereHas('fixture.draw', fn ($query) => $query->where('published', true))
            ->latest('id')->limit($limit)->get();

        return compact('entries', 'placements', 'rankingScores', 'seriesRankings', 'teamAppearances');
    }
}
