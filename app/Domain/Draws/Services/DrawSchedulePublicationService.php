<?php

namespace App\Domain\Draws\Services;

use App\Models\Draw;
use App\Models\DrawAuditLog;
use App\Models\TeamFixture;
use Illuminate\Support\Facades\DB;

final class DrawSchedulePublicationService
{
    public function publish(Draw $draw, ?string $revision = null): void
    {
        if (! $this->hasScheduledMatch($draw)) {
            throw new \RuntimeException('Add at least one match time before publishing the schedule.');
        }

        $this->mutate($draw, false, $revision);
    }

    public function unpublish(Draw $draw, ?string $revision = null): void
    {
        $this->mutate($draw, true, $revision);
    }

    private function mutate(Draw $draw, bool $hide, ?string $revision): void
    {
        $scope = ['draw_id' => $draw->id] + ($revision !== null ? ['revision' => $revision] : []);
        try {
            $service = app(\App\Services\Scheduling\SchedulePublicationService::class);
            $hide ? $service->hide($draw->event, $scope) : $service->publish($draw->event, $scope);
        } catch (\InvalidArgumentException $exception) {
            throw new \RuntimeException($exception->getMessage(), 0, $exception);
        }
    }

    private function hasScheduledMatch(Draw $draw): bool
    {
        return $draw->order_of_play()->whereNotNull('time')->exists()
            || TeamFixture::query()->where('draw_id', $draw->id)->whereNotNull('scheduled_at')->exists();
    }
}
