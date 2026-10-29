<?php

namespace App\Services\Performance;

use App\Models\{CategoryEvent, Event, NoProfileTeamPlayer, TeamPlayer};
use App\Services\{EventTeamScope, TeamSelection\RegionManagerAccessService};

/** Ratings for the authorized roster, never an unscoped player lookup. */
class RegionalRatingAccess
{
    public function playerIds(CategoryEvent $field): array
    {
        $actor = auth()->user();
        $event = $field->event;
        if (!$actor || !$event || !$event->isTeam()) return [];
        $regions = app(RegionManagerAccessService::class)->managedRegionIds($actor, $event);
        $teams = app(EventTeamScope::class)->query($event)->where('category_event_id', $field->id)
            ->whereIn('region_id', $regions)->pluck('teams.id');

        return TeamPlayer::withoutGlobalScopes()->whereIn('team_id', $teams)->where('player_id', '>', 0)->pluck('player_id')
            ->merge(NoProfileTeamPlayer::whereIn('team_id', $teams)->where('player_profile', '>', 0)->pluck('player_profile'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public function visible(): bool
    {
        if (!auth()->check()) return false;
        if (request()->routeIs('backend.player-performance.badges')) {
            $field = CategoryEvent::find(request()->integer('category_event_id'));
            return $field && $this->playerIds($field) !== [];
        }
        if (!request()->routeIs('admin.events.teams', 'backend.team-selection.index')) return false;
        $event = request()->route('event');
        if (!$event instanceof Event) $event = Event::find($event);
        if (!$event) return false;
        $key = 'regional_rating_visibility:'.auth()->id().':'.$event->id;
        if (!request()->attributes->has($key)) {
            request()->attributes->set($key, app(RegionManagerAccessService::class)->canAccessWorkspace(auth()->user(), $event));
        }

        return (bool) request()->attributes->get($key);
    }
}
