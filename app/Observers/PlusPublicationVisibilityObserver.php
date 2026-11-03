<?php

namespace App\Observers;

use App\Models\{Draw, DrawSetting, Event};
use App\Services\PlusPublicationNotificationService;

class PlusPublicationVisibilityObserver
{
    private function hasSchedule(DrawSetting $setting): bool
    {
        return app(PlusPublicationNotificationService::class)->ready()
            && \Illuminate\Support\Facades\DB::table('published_schedule_assignments')->where('draw_id', $setting->draw_id)->exists();
    }

    public function creating(Draw|DrawSetting|Event $model): void
    {
        if ($model instanceof DrawSetting && $model->schedule_visibility !== null && $model->draw && $this->hasSchedule($model)) {
            app(PlusPublicationNotificationService::class)->record($model->draw, baseline: true);
        }
    }

    public function created(Draw|DrawSetting|Event $model): void
    {
        if ($model instanceof DrawSetting && $model->schedule_visibility !== null && $model->draw && $this->hasSchedule($model)) {
            app(PlusPublicationNotificationService::class)->record($model->draw);
        }
    }
    public function updating(Draw|DrawSetting|Event $model): void
    {
        if ($model instanceof DrawSetting && $model->isDirty('schedule_visibility') && $model->draw && $this->hasSchedule($model)) {
            app(PlusPublicationNotificationService::class)->record($model->draw, baseline: true);
        }
    }

    public function updated(Draw|DrawSetting|Event $model): void
    {
        $service = app(PlusPublicationNotificationService::class);
        if ($model instanceof Event && $model->wasChanged('published')) $service->eventVisibilityChanged($model);
        if ($model instanceof Draw && $model->wasChanged('published')) $service->record($model, trackedOnly: true);
        if ($model instanceof DrawSetting && $model->wasChanged('schedule_visibility') && $model->draw && $this->hasSchedule($model)) $service->record($model->draw);
    }
}
