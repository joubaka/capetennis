<?php

use App\Models\Draw;
use App\Services\Scheduling\{EventVenueScheduleService, RoundRobinPlayoffScheduleService};
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('draws') || ! Schema::hasTable('fixtures')
            || ! Schema::hasColumn('fixtures', 'registration1_source_group_id')) {
            return;
        }

        $playoffs = app(RoundRobinPlayoffScheduleService::class);
        Draw::with(['settings', 'groups'])->whereHas('settings', fn ($query) =>
            $query->where('workflow', 'round_robin_playoffs'))
            ->orderBy('id')->each(fn (Draw $draw) => $playoffs->prepare($draw));

        $this->repairOverbergUnderTenBoys();
    }

    /**
     * Event 233 was verified before this migration: six fixed RR bookings,
     * no scores, and two configured position-pair playoffs. Add only those
     * missing bookings and retain every existing booking unchanged.
     */
    private function repairOverbergUnderTenBoys(): void
    {
        $draw = Draw::with(['event', 'settings', 'groups', 'venues'])
            ->where('event_id', 233)->where('drawName', 'U/10B Boys')->first();
        if (! $draw || $draw->locked || $draw->published) {
            return;
        }

        $roundRobin = $draw->drawFixtures()->where('stage', 'RR')->with(['orderOfPlay', 'fixtureResults'])->get();
        $playoffs = $draw->drawFixtures()->where('stage', '!=', 'RR')
            ->whereNotNull('registration1_source_group_id')->with('orderOfPlay')->get();
        if ($roundRobin->count() !== 6 || $roundRobin->contains(fn ($fixture) =>
            ! $fixture->orderOfPlay || $fixture->fixtureResults->isNotEmpty())
            || $playoffs->count() !== 2) {
            return;
        }

        $missing = $playoffs->filter(fn ($fixture) => ! $fixture->orderOfPlay);
        if ($missing->isEmpty()) {
            return;
        }
        if ($missing->count() !== 2 || $playoffs->count() !== $missing->count()) {
            throw new RuntimeException('U/10B Boys playoff repair found a partial booking state; no schedule was changed.');
        }

        $slots = $roundRobin->pluck('orderOfPlay')->sortBy('time')->values();
        $venueIds = $slots->pluck('venue_id')->unique()->map(fn ($id) => (int) $id)->values()->all();
        if (count($venueIds) !== 1 || ! $draw->venues->pluck('id')->contains($venueIds[0])) {
            throw new RuntimeException('U/10B Boys playoff repair could not confirm one existing assigned venue.');
        }

        $start = Carbon::parse($slots->first()->time);
        $duration = max(15, (int) ($slots->first()->duration_minutes ?: 45));
        $times = $slots->pluck('time')->map(fn ($time) => Carbon::parse($time))->unique(fn ($time) => $time->timestamp)->values();
        $spacing = $times->count() > 1 ? $times[0]->diffInMinutes($times[1]) : $duration;
        $rest = max(0, $spacing - $duration);
        $end = $start->copy()->setTime(18, 0);
        if ($end->lte($start)) {
            $end->addDay();
        }

        $options = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'duration' => $duration,
            'wave_minutes' => $duration,
            'court_gap' => (int) ($slots->first()->gap_minutes ?? 0),
            'player_rest' => $rest,
            'draw_ids' => [$draw->id],
            'venue_ids' => $venueIds,
        ];

        $scheduler = app(EventVenueScheduleService::class);
        $preview = $scheduler->preview($draw->event->fresh(), $options);
        $plannedIds = collect($preview['matches'])->pluck('fixture_id')->map(fn ($id) => (int) $id)->sort()->values();
        $expectedIds = $missing->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        if ($plannedIds->all() !== $expectedIds->all() || ! empty($preview['unscheduled'])) {
            throw new RuntimeException('U/10B Boys playoff repair could not safely plan exactly the two missing matches.');
        }

        $scheduler->apply($draw->event->fresh(), $options, $preview['revision']);
    }

    public function down(): void
    {
        // Tournament fixtures and bookings may have become live operational
        // records after deployment, so a rollback deliberately preserves them.
    }
};
