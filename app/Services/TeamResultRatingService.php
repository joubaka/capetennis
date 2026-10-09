<?php

namespace App\Services;

use App\Models\Event;
use App\Services\Performance\{PlayerRatingBadgeService, PlayerRatingLeaderboardService, PlayerSharedAbilityService};
use Illuminate\Support\Collection;

/** Private saved-rating context; never changes result-selection ordering. */
class TeamResultRatingService
{
    public function enrich(Collection $ranking, Event $event, ?array $playingGroup): Collection
    {
        if (! PlayerRatingBadgeService::visible() || ! $playingGroup || $ranking->isEmpty()) {
            return $ranking->map(fn ($row) => array_diff_key($row, ['rating_player_id' => true]));
        }
        $cohort = app(PlayerSharedAbilityService::class)->cohort(
            ($event->frontend_type_view === 'masters' ? 'masters · ' : '').$playingGroup['name']
        );
        // The overall cohort leaderboard supplies component positions, independently
        // of this event's candidate regions, formats and result exclusions.
        $leaderboard = app(PlayerRatingLeaderboardService::class)->build(null, $cohort);
        $members = $leaderboard['rows']->keyBy('identity');
        return $ranking->map(function ($row) use ($members, $leaderboard, $cohort) {
            $playerId = $row['rating_player_id'] ?? null;
            $member = $playerId ? $members->get('p:'.$playerId) : null;
            $rating = $member && $member['cohort'] === $cohort ? $member['rating'] : null;
            $row['cape_tennis_rating'] = [
                'cohort' => $cohort,
                'score' => $rating['score'] ?? null,
                'position' => $rating ? $member['position'] : null,
                'component' => $rating['component'] ?? null,
                'snapshot_stale' => (bool) ($leaderboard['snapshot']['snapshot_stale'] ?? false),
                'as_of' => $leaderboard['snapshot']['snapshot_as_of'] ?? null,
                'unavailable_reason' => $rating ? null : ($leaderboard['limitReason'] ?? 'No saved rating for this player in the playing cohort.'),
            ];
            unset($row['rating_player_id']);
            return $row;
        });
    }
}
