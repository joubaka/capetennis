<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Models\Event;
use App\Models\EventRegion;
use App\Models\EventRegionManager;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RegionManagerAccessService
{
    public function isEventManager(User $user, Event $event): bool
    {
        return $user->hasRole('super-user')
            || $user->is_event_admin($event->id)
            || (! $user->is_event_score_keeper($event->id) && $user->is_convenor($event->id));
    }

    public function canManage(User $user, EventRegion $eventRegion): bool
    {
        $event = $eventRegion->relationLoaded('events') ? $eventRegion->events : $eventRegion->events()->first();
        if (! $event) return false;
        if ($this->isEventManager($user, $event)) return true;

        return (int) $this->manager($eventRegion)?->id === (int) $user->id;
    }

    public function manager(EventRegion $eventRegion): ?User
    {
        $explicit = EventRegionManager::query()->where('event_region_id', $eventRegion->id)->with('user')->first();
        if ($explicit) return $explicit->user;

        return $this->defaultManager($eventRegion);
    }

    public function defaultManager(EventRegion $eventRegion): ?User
    {
        $candidates = $this->defaultManagerCandidates($eventRegion);

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /** @return Collection<int, User> */
    public function defaultManagerCandidates(EventRegion $eventRegion): Collection
    {
        $eventRegion->loadMissing(['events', 'rankingSource']);
        $seriesId = $eventRegion->rankingSource?->series_id;
        if ($seriesId) {
            $eventIds = Event::query()->where('series_id', $seriesId)->pluck('id');
            if ($eventIds->isNotEmpty()) {
                $userIds = DB::table('event_admins')
                    ->whereIn('event_id', $eventIds)
                    ->select('user_id')
                    ->groupBy('user_id')
                    ->havingRaw('COUNT(DISTINCT event_id) = ?', [$eventIds->count()])
                    ->orderBy('user_id')
                    ->pluck('user_id');

                return User::query()->whereIn('id', $userIds)->orderBy('id')->get();
            }
        }

        $fallbackIds = DB::table('event_admins')->where('event_id', $eventRegion->event_id)
            ->orderBy('id')->pluck('user_id');

        return User::query()->whereIn('id', $fallbackIds)->orderBy('id')->get();
    }
}
