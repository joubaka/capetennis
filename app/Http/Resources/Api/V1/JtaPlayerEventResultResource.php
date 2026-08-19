<?php

namespace App\Http\Resources\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JtaPlayerEventResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $result = $this->resource;
        $event = $result->event;
        $registration = $result->registration;
        $sourceId = sprintf(
            'ct-placement-%d-%d-%d',
            $result->event_id,
            $result->category_id,
            $result->registration_id,
        );

        $payload = [
            'source' => 'cape_tennis',
            'result_type' => 'placement',
            'source_result_id' => $sourceId,
            'event' => [
                'id' => (int) $event->id,
                'name' => (string) $event->name,
                'start_date' => optional($event->start_date)->format('Y-m-d'),
                'end_date' => optional($event->end_date)->format('Y-m-d'),
            ],
            'category' => [
                'id' => (int) $result->category->id,
                'name' => (string) $result->category->name,
            ],
            'registration_id' => (int) $registration->id,
            'players' => $registration->players->map(fn ($player) => [
                'cape_tennis_player_id' => (int) $player->id,
                'display_name' => trim((string) $player->name.' '.(string) $player->surname),
            ])->values()->all(),
            'placement_type' => $registration->players->count() === 1 ? 'singles' : 'doubles',
            'position' => (int) $result->position,
            'field_size' => (int) $result->field_size,
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
        $result = $this->resource;
        $timestamps = collect([
            $result->updated_at,
            $result->event?->updated_at,
            $result->category?->updated_at,
            $result->registration?->updated_at,
        ])->merge($result->registration->players->pluck('updated_at'))
            ->merge($result->registration->players->pluck('pivot.updated_at'))
            ->filter()
            ->map(fn ($timestamp) => CarbonImmutable::parse($timestamp));

        return $timestamps->sortDesc()->first() ?? CarbonImmutable::parse($result->created_at);
    }
}
