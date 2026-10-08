<?php

namespace App\Domain\Draws\Services;

use App\Domain\Draws\Guards\DrawGuard;
use App\Models\Draw;
use App\Models\DrawAuditLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * DrawPublicationService
 *
 * Canonical service for publishing and unpublishing draws.
 *
 * A published draw is visible to the public / players.
 * It can still be scored but cannot be structurally regenerated.
 */
final class DrawPublicationService
{
    /**
     * Publish the draw.
     *
     * @throws \RuntimeException if the draw has not been generated yet.
     */
    public function publish(Draw $draw): void
    {
        DB::transaction(function () use ($draw) {
            $eventId = (int) $draw->event_id;
            app(\App\Services\TeamDrawAdaptationService::class)->lockEvent($draw->event_id);
            $draw = Draw::query()->lockForUpdate()->findOrFail($draw->id);
            if ((int) $draw->event_id !== $eventId) {
                throw new \RuntimeException('Draw event changed. Refresh before publishing.');
            }
            DrawGuard::requireGenerated($draw, 'publish');
            $readiness = app(DrawReadinessService::class)->for($draw);
            if (! $readiness['ready_to_publish']) {
                $reason = collect($readiness['checks'])->firstWhere('ok', false)['label'] ?? 'complete the readiness checks';
                throw new \RuntimeException('Draw is not ready to publish: '.lcfirst($reason).'.');
            }
            if ($draw->isTeamDraw()) {
                app(\App\Services\TeamDrawScoringPublicationService::class)->enableLocked($draw);
            }
            if (! $draw->published) {
                $draw->update(['published' => true]);
                DrawAuditLog::record($draw->id, 'published', null, ['published' => true]);
            }
        });

        Log::info('[DrawPublication] Draw published', ['draw_id' => $draw->id]);
    }

    public function enableScoring(Draw $draw): void
    {
        DB::transaction(function () use ($draw) {
            $eventId = (int) $draw->event_id;
            app(\App\Services\TeamDrawAdaptationService::class)->lockEvent($draw->event_id);
            $draw = Draw::query()->lockForUpdate()->findOrFail($draw->id);
            if ((int) $draw->event_id !== $eventId) {
                throw new \RuntimeException('Draw event changed. Refresh before enabling scoring.');
            }
            if (! $draw->published || ! $draw->isTeamDraw()) {
                throw new \RuntimeException('Publish the team draw before enabling scoring.');
            }
            app(\App\Services\TeamDrawScoringPublicationService::class)->enableLocked($draw);
        });
    }

    /**
     * Unpublish the draw (take it off public view).
     *
     * @throws \RuntimeException if the draw is locked.
     */
    public function unpublish(Draw $draw): void
    {
        DrawGuard::requireMutable($draw, 'unpublish');

        DB::transaction(function () use ($draw) {
            $draw = Draw::query()->lockForUpdate()->findOrFail($draw->id);
            $draw->update(['published' => false]);
            DrawAuditLog::record($draw->id, 'unpublished', null, [
                'published' => false,
                'schedule_preview_retained' => (bool) $draw->oop_published,
            ]);
        });

        Log::info('[DrawPublication] Draw unpublished', ['draw_id' => $draw->id]);
    }

    public function isPublished(Draw $draw): bool
    {
        return (bool) $draw->published;
    }
}
