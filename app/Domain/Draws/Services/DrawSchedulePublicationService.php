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

        app(\App\Services\Scheduling\SchedulePublicationService::class)->publish($draw->event, ['draw_id' => $draw->id]);
    }

    public function unpublish(Draw $draw): void
    {
        app(\App\Services\Scheduling\SchedulePublicationService::class)->hide($draw->event, ['draw_id' => $draw->id]);
    }

    private function hasScheduledMatch(Draw $draw): bool
    {
        return $draw->order_of_play()->whereNotNull('time')->exists()
            || TeamFixture::query()->where('draw_id', $draw->id)->whereNotNull('scheduled_at')->exists();
    }
}
