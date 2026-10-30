<?php

namespace App\Services\Scheduling;

use App\Models\Draw;
use App\Services\Draw\PlayoffParticipationService;
use Illuminate\Support\Collection;

/** Read-only coverage of configured playoff stages, separate from existing bookings. */
final class PlayoffScheduleReadinessService
{
    public function pending(Collection $draws): Collection
    {
        (new \Illuminate\Database\Eloquent\Collection($draws->all()))->loadMissing('settings', 'groups.groupRegistrations');
        return $draws->filter(fn (Draw $draw) => $draw->settings?->workflow === 'round_robin_playoffs')
            ->map(function (Draw $draw) {
                $existing = $draw->drawFixtures()->where('stage', '!=', 'RR')->distinct()->pluck('stage')
                    ->map(fn ($stage) => strtoupper((string) $stage));
                $missing = collect($draw->settings->playoff_config)->filter(fn ($entry) => ($entry['enabled'] ?? false)
                    && !app(PlayoffParticipationService::class)->isDormant($draw, $entry)
                    && !$existing->contains(strtoupper($entry['slug'] ?? 'MAIN')));
                return ['draw_id' => $draw->id, 'draw_name' => $draw->drawName,
                    'missing_stages' => $missing->pluck('name')->values()->all(), 'partly_prepared' => $existing->isNotEmpty()];
            })->filter(fn ($item) => $item['missing_stages'] !== [])->values();
    }
}
