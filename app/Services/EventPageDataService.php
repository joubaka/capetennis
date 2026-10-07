<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Request-local event-page preparation; never caches payer or permission data. */
final class EventPageDataService
{
    public function prepareScheduleExistence(Event $event, Collection $draws): void
    {
        $scheduledDrawIds = DB::table('published_schedule_assignments')
            ->where('event_id', $event->id)->whereIn('draw_id', $draws->pluck('id'))
            ->distinct()->pluck('draw_id')->flip();
        foreach ($draws as $draw) {
            request()->attributes->set('draw_schedule_exists_'.$event->id.'_'.$draw->id, $scheduledDrawIds->has($draw->id));
        }
    }

    public function drawViewPermissions(?User $user, Collection $draws): Collection
    {
        // DrawPolicy::view depends only on event assignments. Keep this scoped
        // to that ability; mutation policies also depend on each draw's state.
        $permissions = collect();
        foreach ($draws->groupBy('event_id') as $eventDraws) {
            $allowed = (bool) $user?->can('view', $eventDraws->first());
            foreach ($eventDraws as $draw) $permissions->put($draw->id, $allowed);
        }
        return $permissions;
    }

    public function prepareRegions(Event $event): void
    {
        $event->regions->load(['clothingItems', 'teams' => fn ($query) => $query
            ->where(fn ($teams) => $teams->whereNull('category_event_id')
                ->orWhereHas('category', fn ($category) => $category->where('event_id', $event->id)))
            ->with(['category.category', 'team_players.player', 'team_players_no_profile.profile'])]);

        foreach ($event->regions as $region) {
            foreach ($region->teams as $team) {
                // Both aliases are used by the profile and imported roster views.
                $team->setRelation('teamPlayers', $team->team_players);
                $team->category?->setRelation('event', $event);
                foreach ($team->team_players_no_profile as $slot) {
                    $slot->setRelation('team', $team);
                }
            }
        }
    }

    public function attachDraws(Collection $fixtures, Collection $draws): void
    {
        $draws = $draws->keyBy('id');
        foreach ($fixtures as $fixture) {
            $fixture->setRelation('draw', $draws->get($fixture->draw_id));
        }
    }
}
