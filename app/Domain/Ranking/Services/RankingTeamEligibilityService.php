<?php

namespace App\Domain\Ranking\Services;

use App\Models\Series;
use App\Models\SeriesRanking;
use Illuminate\Support\Collection;

final class RankingTeamEligibilityService
{
    /** @return array{eligible: bool, events_played: int, minimum_events: int, reason: ?string} */
    public function assess(SeriesRanking $ranking, ?Series $series = null): array
    {
        $series ??= $ranking->relationLoaded('series') ? $ranking->series : $ranking->series()->first();
        $minimum = max(1, (int) ($series?->minimum_events_for_team_selection ?? 1));
        $eventsPlayed = $this->actualEventsPlayed($ranking);
        $eligible = $eventsPlayed >= $minimum;

        return [
            'eligible' => $eligible,
            'events_played' => $eventsPlayed,
            'minimum_events' => $minimum,
            'reason' => $eligible
                ? null
                : "Not eligible for team selection — {$eventsPlayed} of {$minimum} required events played.",
        ];
    }

    public function actualEventsPlayed(SeriesRanking $ranking): int
    {
        $meta = is_array($ranking->meta_json) ? $ranking->meta_json : [];
        if (array_key_exists('events_played', $meta)) {
            return max(0, (int) $meta['events_played']);
        }

        return collect($meta['counting_legs'] ?? [])
            ->merge($meta['dropped_legs'] ?? [])
            ->filter(fn (array $leg) => empty($leg['synthetic']) && (int) ($leg['position'] ?? 0) > 0)
            ->pluck('category_event_id')
            ->filter()
            ->unique()
            ->count();
    }

    /** @param Collection<int, SeriesRanking> $rankings */
    public function eligible(Collection $rankings, Series $series): Collection
    {
        return $rankings
            ->filter(fn (SeriesRanking $ranking) => $this->assess($ranking, $series)['eligible'])
            ->values();
    }
}
