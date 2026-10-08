<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;

class EventTeamScope
{
    public function query(Event $event, array $invitedTeamIds = []): Builder
    {
        // Older tournaments linked rosters through event-specific regions.
        // A shared region alone cannot identify which event owns a legacy team.
        $legacyRegions = $event->regions()
            ->whereDoesntHave('events', fn ($query) => $query->where('events.id', '!=', $event->id))
            ->pluck('team_regions.id');

        return Team::query()->withoutGlobalScopes()->where(fn ($query) => $query
            ->whereHas('category', fn ($category) => $category->where('event_id', $event->id))
            ->orWhereIn('id', $invitedTeamIds)
            ->orWhere(fn ($legacy) => $legacy->whereNull('category_event_id')->whereIn('region_id', $legacyRegions)));
    }
}
