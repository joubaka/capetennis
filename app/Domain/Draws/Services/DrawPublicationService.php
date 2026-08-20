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
        DrawGuard::requireGenerated($draw, 'publish');

        $readiness = app(DrawReadinessService::class)->for($draw);
        if (! $readiness['ready_to_publish']) {
            throw new \RuntimeException('Draw is not ready to publish: assign participants and generate fixtures first.');
        }

        DB::transaction(function () use ($draw) {
            $draw->published = true;
            $draw->save();
            DrawAuditLog::record($draw->id, 'published', null, ['published' => true]);
        });

        Log::info('[DrawPublication] Draw published', ['draw_id' => $draw->id]);
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
            $draw->published = false;
            $draw->save();
            DrawAuditLog::record($draw->id, 'unpublished', null, ['published' => false]);
        });

        Log::info('[DrawPublication] Draw unpublished', ['draw_id' => $draw->id]);
    }

    public function isPublished(Draw $draw): bool
    {
        return (bool) $draw->published;
    }
}
