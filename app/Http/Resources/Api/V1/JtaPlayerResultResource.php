<?php

namespace App\Http\Resources\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JtaPlayerResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fixture = $this->resource;
        $draw = $fixture->draw;
        $event = $draw->event;
        $category = $draw->categoryEvent?->category;
        $playedAt = $fixture->oop?->time
            ? CarbonImmutable::parse($fixture->oop->time)
            : CarbonImmutable::parse($event->start_date)->startOfDay();

        $payload = [
            'source' => 'cape_tennis',
            'source_match_id' => 'ct-fixture-'.$fixture->id,
            'source_fixture_id' => (int) $fixture->id,
            'event' => [
                'id' => (int) $event->id,
                'name' => (string) $event->name,
                'start_date' => optional($event->start_date)->format('Y-m-d'),
                'end_date' => optional($event->end_date)->format('Y-m-d'),
            ],
            'category' => [
                'id' => $category ? (int) $category->id : null,
                'name' => $category?->name,
            ],
            'draw' => [
                'id' => (int) $draw->id,
                'name' => (string) $draw->drawName,
            ],
            'match' => [
                'stage' => $fixture->stage,
                'round' => $fixture->round === null ? null : (string) $fixture->round,
                'match_number' => $fixture->match_nr === null ? null : (int) $fixture->match_nr,
                'played_at' => $playedAt->toIso8601String(),
                'date_precision' => $fixture->oop?->time ? 'scheduled_time' : 'event_start',
                'match_type' => $fixture->registration1->players->count() === 1 ? 'singles' : 'doubles',
            ],
            'side1' => $this->side($fixture->registration1),
            'side2' => $this->side($fixture->registration2),
            'winner_registration_id' => (int) $fixture->winner_registration,
            'sets' => $fixture->fixtureResults->sortBy('set_nr')->values()->map(fn ($result) => [
                'set_number' => (int) $result->set_nr,
                'side1_games' => (int) $result->registration1_score,
                'side2_games' => (int) $result->registration2_score,
            ])->all(),
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

    private function side($registration): array
    {
        return [
            'registration_id' => (int) $registration->id,
            'players' => $registration->players->map(fn ($player) => [
                'cape_tennis_player_id' => (int) $player->id,
                'display_name' => trim((string) $player->name.' '.(string) $player->surname),
            ])->values()->all(),
        ];
    }

    private function sourceUpdatedAt(): CarbonImmutable
    {
        $fixture = $this->resource;
        $timestamps = collect([
            $fixture->updated_at,
            $fixture->draw?->updated_at,
            $fixture->draw?->event?->updated_at,
            $fixture->draw?->categoryEvent?->category?->updated_at,
            $fixture->registration1?->updated_at,
            $fixture->registration2?->updated_at,
            $fixture->oop?->updated_at,
        ])->merge($fixture->fixtureResults->pluck('updated_at'))
            ->merge($fixture->registration1->players->pluck('updated_at'))
            ->merge($fixture->registration2->players->pluck('updated_at'))
            ->merge($fixture->registration1->players->pluck('pivot.updated_at'))
            ->merge($fixture->registration2->players->pluck('pivot.updated_at'))
            ->filter()
            ->map(fn ($timestamp) => CarbonImmutable::parse($timestamp));

        return $timestamps->sortDesc()->first() ?? CarbonImmutable::parse($fixture->created_at);
    }
}
