<?php

namespace App\Http\Resources\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JtaPlayerSeriesRankingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ranking = $this->resource;
        $sourceId = sprintf(
            'ct-series-ranking-%d-%d-%d',
            $ranking->series_id,
            $ranking->category_id,
            $ranking->player_id,
        );

        $payload = [
            'source' => 'cape_tennis',
            'result_type' => 'series_ranking',
            'source_result_id' => $sourceId,
            'series' => [
                'id' => (int) $ranking->series->id,
                'name' => (string) $ranking->series->name,
                'year' => $ranking->series->year !== null ? (int) $ranking->series->year : null,
            ],
            'ranking_list_id' => (int) $ranking->ranking_list_id,
            'category' => [
                'id' => (int) $ranking->category->id,
                'name' => (string) $ranking->category->name,
            ],
            'player' => [
                'cape_tennis_player_id' => (int) $ranking->player->id,
                'display_name' => trim((string) $ranking->player->name.' '.(string) $ranking->player->surname),
            ],
            'rank_position' => (int) $ranking->rank_position,
            'total_points' => (float) $ranking->total_points,
            'published_at' => optional($ranking->published_at)->toIso8601String(),
            'event_legs' => $ranking->getAttribute('jta_public_legs') ?? [],
        ];

        return array_merge(
            array_slice($payload, 0, 3, true),
            [
                'source_version' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                'source_updated_at' => $this->sourceUpdatedAt()->toIso8601String(),
            ],
            array_slice($payload, 3, null, true),
        );
    }

    private function sourceUpdatedAt(): CarbonImmutable
    {
        $ranking = $this->resource;
        $timestamps = collect([
            $ranking->updated_at,
            $ranking->published_at,
            $ranking->series?->updated_at,
            $ranking->rankingList?->updated_at,
            $ranking->category?->updated_at,
            $ranking->player?->updated_at,
        ])->filter()->map(fn ($timestamp) => CarbonImmutable::parse($timestamp));

        return $timestamps->sortDesc()->first() ?? CarbonImmutable::parse($ranking->created_at);
    }
}
