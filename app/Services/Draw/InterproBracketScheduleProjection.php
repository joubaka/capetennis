<?php

namespace App\Services\Draw;

use App\Models\Draw;
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Support\Collection;

/** Audience-specific bracket metadata; working bookings never enter publication caches. */
final class InterproBracketScheduleProjection
{
    public function forDraw(Draw $draw): Collection
    {
        $published=app(SchedulePublicationService::class)
            ->publishedRows($draw->event,includeParticipants:false,drawId:$draw->id)
            ->where('fixture_kind','individual')->keyBy('fixture_id');
        $schedules=$published->map(fn($row)=>['time'=>$row['scheduled_at'],'venue'=>$row['venue_name'],
            'court'=>$row['court']]);
        // Never accept a query flag, event-admin assignment or template argument as preview authority.
        if (!(auth()->user()?->hasRole('super-user') ?? false)) return $schedules;
        $fixtures=$draw->drawFixtures()->where('stage','!=','RR')->with('orderOfPlay.venue')
            ->whereHas('orderOfPlay',fn($query)=>$query->whereNotNull('time'))->get();
        foreach ($fixtures as $fixture) {
            $fixture->setRelation('draw',$draw);
            if (InterproRoundRobinFormat::noMatchRequired($fixture)) continue;
            $slot=$fixture->orderOfPlay;
            if ((int)$slot->draw_id !== (int)$draw->id) continue;
            $row=$published->get($fixture->id);
            $time=\Carbon\Carbon::parse($slot->time)->format('Y-m-d H:i:s');
            $preview=!$row || $row['scheduled_at'] !== $time || (string)$row['court'] !== (string)$slot->court
                || (int)$row['venue_id'] !== (int)$slot->venue_id;
            if ($preview) $schedules->put($fixture->id,['time'=>$time,'venue'=>$slot->venue?->name ?? 'Venue unassigned',
                'court'=>$slot->court,'preview'=>true]);
        }
        return $schedules;
    }
}
