<?php
namespace App\Observers;
use App\Models\{Announcement, Event};
use App\Services\PlusEventUpdateService;
use Illuminate\Database\Eloquent\Model;

class PlusEventUpdateObserver
{
    private function event(Model $model): ?Event
    {
        return $model instanceof Event ? $model : Event::find($model->event_id);
    }
    public function updating(Model $model): void { if ($event = $this->event($model)) app(PlusEventUpdateService::class)->record($event, baseline: true); }
    public function creating(Model $model): void { if (! $model instanceof Event && ($event = $this->event($model))) app(PlusEventUpdateService::class)->record($event, baseline: true); }
    public function deleting(Model $model): void { $this->updating($model); }
    public function saved(Model $model): void { if ($event = $this->event($model)) app(PlusEventUpdateService::class)->record($event); }
    public function deleted(Model $model): void { if ($event = $this->event($model)) app(PlusEventUpdateService::class)->record($event); }
}
