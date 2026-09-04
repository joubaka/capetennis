<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Event, Fixture, OrderOfPlay};
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class EventVenueScheduleController extends Controller
{
    public function index(Event $event)
    {
        $this->authorize('event.manage', $event);
        $event->load(['draws.venues', 'venues']);

        $legacyVenues = \App\Models\Venue::where('event_id', $event->id)->get();
        $availableVenues = $event->venues->concat($legacyVenues)->concat($event->draws->flatMap->venues)
            ->unique('id')->sortBy('name')->values();

        $draws = $event->draws->map(fn ($draw) => [
            'id' => $draw->id, 'name' => $draw->drawName,
            'venues' => $draw->venues->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'locked' => (bool) $draw->locked, 'published' => (bool) $draw->published,
        ]);
        $venues = $availableVenues->map(function ($venue) use ($event) {
            $assignedCounts = $event->draws->flatMap->venues->where('id', $venue->id)
                ->map(fn ($assigned) => (int) ($assigned->pivot->num_courts ?? 0));
            return [
            'id' => $venue->id, 'name' => $venue->name,
                'courts' => max(1, (int) ($venue->num_courts ?? 0), (int) ($assignedCounts->max() ?? 0)),
            ];
        });

        return view('backend.schedule.event-venue-schedule', compact('event', 'draws', 'venues'));
    }

    public function updateAssignments(Request $request, Event $event)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'venues' => ['required', 'array', 'min:1'], 'venues.*.id' => ['required', 'integer', 'exists:venues,id'],
            'venues.*.courts' => ['required', 'integer', 'min:1', 'max:100'],
            'assignments' => ['required', 'array'], 'assignments.*.draw_id' => ['required', 'integer'],
            'assignments.*.venue_ids' => ['present', 'array'], 'assignments.*.venue_ids.*' => ['integer'],
        ]);
        $draws = $event->draws()->whereIn('id', collect($data['assignments'])->pluck('draw_id'))->get()->keyBy('id');
        if ($draws->count() !== count($data['assignments'])) abort(422, 'One or more draws do not belong to this event.');
        $courtCounts = collect($data['venues'])->mapWithKeys(fn ($venue) => [(int) $venue['id'] => (int) $venue['courts']]);
        $allowedVenueIds = $event->venues()->pluck('venues.id')
            ->merge(\App\Models\Venue::where('event_id', $event->id)->pluck('id'))
            ->merge(DB::table('draw_venues')->whereIn('draw_id', $event->draws()->pluck('id'))->pluck('venue_id'))
            ->unique()->map(fn ($id) => (int) $id)->all();
        if (array_diff($courtCounts->keys()->all(), $allowedVenueIds)) abort(422, 'A venue does not belong to this event.');

        $unscheduled = 0;
        try {
            DB::transaction(function () use ($data, $draws, $courtCounts, &$unscheduled) {
                foreach ($data['assignments'] as $assignment) {
                    $draw = $draws[(int) $assignment['draw_id']];
                    if ($draw->locked || $draw->published) {
                        throw new \InvalidArgumentException("{$draw->drawName} is locked or published and its venue allocation cannot change.");
                    }
                    $venueIds = array_map('intval', $assignment['venue_ids']);
                    if (array_diff($venueIds, $courtCounts->keys()->all())) {
                        throw new \InvalidArgumentException('A selected venue is not available for this event.');
                    }
                    $before = $draw->venues()->pluck('venues.id')->map(fn ($id) => (int) $id)->all();
                    $removed = array_diff($before, $venueIds);
                    $affected = OrderOfPlay::whereHas('fixture', fn ($query) => $query->where('draw_id', $draw->id))
                        ->whereIn('venue_id', $removed);
                    if ((clone $affected)->whereHas('fixture.fixtureResults')->exists()) {
                        throw new \InvalidArgumentException("{$draw->drawName} has played matches at a venue being removed.");
                    }
                    $affectedFixtureIds = (clone $affected)->pluck('fixture_id');
                    $unscheduled += $affectedFixtureIds->count();
                    $affected->delete();
                    Fixture::whereIn('id', $affectedFixtureIds)->update(['scheduled' => 0]);
                    $draw->venues()->sync(collect($venueIds)->mapWithKeys(fn ($venueId) => [
                        $venueId => ['num_courts' => $courtCounts[$venueId]],
                    ])->all());
                    \App\Models\DrawAuditLog::record($draw->id, 'venue_allocation_updated', null, [
                        'before' => $before, 'after' => $venueIds, 'unscheduled_matches' => $affectedFixtureIds->count(),
                    ]);
                }
            });
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Age-group venue allocations saved.', 'unscheduled' => $unscheduled]);
    }

    public function preview(Request $request, Event $event, EventVenueScheduleService $scheduler)
    {
        $this->authorize('event.manage', $event);
        try {
            return response()->json($scheduler->preview($event, $this->validatedOptions($request)));
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function apply(Request $request, Event $event, EventVenueScheduleService $scheduler)
    {
        $this->authorize('event.manage', $event);
        $request->validate(['revision' => ['required', 'string', 'size:64']]);
        try {
            return response()->json($scheduler->apply($event, $this->validatedOptions($request), (string) $request->string('revision')));
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    private function validatedOptions(Request $request): array
    {
        return $request->validate([
            'start' => ['required', 'date'], 'end' => ['nullable', 'date', 'after:start'],
            'duration' => ['required', 'integer', 'min:15', 'max:480'],
            'wave_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'court_gap' => ['required', 'integer', 'min:0', 'max:120'],
            'player_rest' => ['required', 'integer', 'min:0', 'max:480'],
            'draw_ids' => ['nullable', 'array'], 'draw_ids.*' => ['integer'],
            'venue_ids' => ['nullable', 'array'], 'venue_ids.*' => ['integer'],
        ]);
    }
}
