<?php

namespace App\Domain\Draws\Services;

use App\Models\Draw;
use App\Models\DrawAuditLog;
use App\Models\TeamFixture;
use Illuminate\Support\Facades\DB;

final class DrawSchedulePublicationService
{
    public function publish(Draw $draw): void
    {
        if (! $this->hasScheduledMatch($draw)) {
            throw new \RuntimeException('Add at least one match time before publishing the schedule.');
        }

        DB::transaction(function () use ($draw) {
            $draw = Draw::query()->lockForUpdate()->findOrFail($draw->id);
            if ($draw->oop_published) {
                return;
            }

            $draw->update(['oop_published' => true]);
            DrawAuditLog::record($draw->id, 'schedule_published', null, [
                'draw_published' => (bool) $draw->published,
                'preview_only' => ! (bool) $draw->published,
            ]);
        });
    }

    public function unpublish(Draw $draw): void
    {
        DB::transaction(function () use ($draw) {
            $draw = Draw::query()->lockForUpdate()->findOrFail($draw->id);
            if (! $draw->oop_published) {
                return;
            }

            $draw->update(['oop_published' => false]);
            DrawAuditLog::record($draw->id, 'schedule_unpublished', null, [
                'draw_published' => (bool) $draw->published,
                'preview_only' => false,
            ]);
        });
    }

    private function hasScheduledMatch(Draw $draw): bool
    {
        return $draw->order_of_play()->whereNotNull('time')->exists()
            || TeamFixture::query()->where('draw_id', $draw->id)->whereNotNull('scheduled_at')->exists();
    }
}
