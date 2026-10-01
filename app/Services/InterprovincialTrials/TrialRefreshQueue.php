<?php

namespace App\Services\InterprovincialTrials;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrialRefreshQueue
{
    private array $eventIds = [];
    private array $categoryIds = [];

    public function remember(int $eventId, bool $scoresChanged = false, ?int $categoryId = null): void
    {
        $this->eventIds[$eventId] = ($this->eventIds[$eventId] ?? false) || $scoresChanged;
        if ($scoresChanged && $categoryId) { $this->categoryIds[$eventId][] = $categoryId; }
    }

    public function flush(): void
    {
        $events = $this->eventIds;
        $categories = $this->categoryIds;
        $ids = array_keys($events);
        $this->eventIds = [];
        $this->categoryIds = [];
        if ($ids === [] || ! Schema::hasTable('trial_programmes')) {
            return;
        }
        DB::afterCommit(function () use ($ids, $events, $categories) {
            foreach (Event::whereIn('id', $ids)->with('eventTypeModel')->get() as $event) {
                app(TrialProgrammeService::class)->refresh($event, $events[$event->id], array_unique($categories[$event->id] ?? []));
                // Withdrawal progression inside refresh is already included in that snapshot.
                unset($this->eventIds[$event->id], $this->categoryIds[$event->id]);
            }
        });
    }
}
