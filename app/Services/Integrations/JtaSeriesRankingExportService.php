<?php

namespace App\Services\Integrations;

use App\Domain\Ranking\Enums\RankingStatus;
use App\Models\CategoryEvent;
use App\Models\Player;
use App\Models\SeriesRanking;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

class JtaSeriesRankingExportService
{
    public function paginate(
        Player $player,
        ?CarbonImmutable $updatedSince,
        int $perPage,
        bool $useCursor = false,
    ): LengthAwarePaginator|CursorPaginator {
        $query = SeriesRanking::query()
            ->with(['series', 'rankingList', 'category', 'player'])
            ->where('player_id', $player->getKey())
            ->where('status', RankingStatus::Published->value)
            ->whereNotNull('run_id')
            ->whereHas('series', fn (Builder $series) => $series->where('leaderboard_published', true))
            ->whereHas('rankingList')
            ->whereHas('category');

        if ($updatedSince) {
            $query->where(function (Builder $changed) use ($updatedSince): void {
                $changed->where('series_rankings.updated_at', '>=', $updatedSince)
                    ->orWhere('series_rankings.published_at', '>=', $updatedSince)
                    ->orWhereHas('series', fn (Builder $series) => $series->where('updated_at', '>=', $updatedSince))
                    ->orWhereHas('rankingList', fn (Builder $list) => $list->where('updated_at', '>=', $updatedSince))
                    ->orWhereHas('category', fn (Builder $category) => $category->where('updated_at', '>=', $updatedSince))
                    ->orWhereHas('player', fn (Builder $player) => $player->where('updated_at', '>=', $updatedSince));
            });
        }

        $query->orderBy('series_rankings.series_id')
            ->orderBy('series_rankings.category_id')
            ->orderBy('series_rankings.id');

        $paginator = $useCursor
            ? $query->cursorPaginate($perPage)->withQueryString()
            : $query->paginate($perPage)->withQueryString();

        $this->hydratePublicLegs($paginator);

        return $paginator;
    }

    private function hydratePublicLegs(LengthAwarePaginator|CursorPaginator $paginator): void
    {
        $rankings = $paginator->getCollection();
        $categoryEventIds = $rankings->flatMap(function (SeriesRanking $ranking) {
            $meta = $this->meta($ranking);

            return collect($meta['counting_legs'] ?? [])
                ->concat($meta['dropped_legs'] ?? [])
                ->concat($meta['legs'] ?? [])
                ->pluck('category_event_id');
        })->filter()->unique()->values();

        $eventIds = CategoryEvent::query()
            ->whereIn('id', $categoryEventIds)
            ->pluck('event_id', 'id');

        $rankings->each(function (SeriesRanking $ranking) use ($eventIds): void {
            $meta = $this->meta($ranking);
            $normalise = function (array $leg, string $status) use ($eventIds): array {
                $categoryEventId = $leg['category_event_id'] ?? null;

                return [
                    'event_id' => isset($leg['event_id'])
                        ? (int) $leg['event_id']
                        : ($eventIds->has($categoryEventId) ? (int) $eventIds->get($categoryEventId) : null),
                    'points' => (float) ($leg['points'] ?? 0),
                    'position' => isset($leg['position']) ? (int) $leg['position'] : null,
                    'status' => $status,
                    'synthetic' => (bool) ($leg['synthetic'] ?? false),
                ];
            };

            if (array_key_exists('counting_legs', $meta) || array_key_exists('dropped_legs', $meta)) {
                $legs = collect($meta['counting_legs'] ?? [])
                    ->map(fn (array $leg) => $normalise($leg, 'counted'))
                    ->concat(collect($meta['dropped_legs'] ?? [])->map(fn (array $leg) => $normalise($leg, 'dropped')));
            } else {
                $legs = collect($meta['legs'] ?? [])->map(function (array $leg) use ($normalise): array {
                    $status = ($leg['status'] ?? null) === 'dropped' || ($leg['colour'] ?? null) === 'red'
                        ? 'dropped'
                        : 'counted';

                    return $normalise($leg, $status);
                });
            }

            $ranking->setAttribute('jta_public_legs', $legs->filter(fn (array $leg) => $leg['event_id'] !== null)->values()->all());
        });
    }

    private function meta(SeriesRanking $ranking): array
    {
        $meta = $ranking->meta_json ?? [];

        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        return is_array($meta) ? $meta : [];
    }
}
