<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Event, Fixture, OrderOfPlay, Venue};
use App\Services\EventAnnouncementService;
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class EventVenueScheduleController extends Controller
{
    public function index(Event $event, EventAnnouncementService $announcements)
    {
        $this->authorize('event.manage', $event);
        $event->load('draws.venues');
        $eventDraws = $event->draws;
        $eventVenues = $event->venues()->get();
        $drawVenues = $eventDraws->flatMap(fn ($draw) => $draw->venues);

        $availableVenues = $eventVenues->concat($drawVenues)
            ->unique('id')->sortBy('name')->values();
        $courtRows = DB::table('event_venue_courts')->where('event_id', $event->id)
            ->whereIn('venue_id', $availableVenues->pluck('id'))->where('active', true)->orderBy('id')->get()->groupBy('venue_id');
        $courtAllocations = DB::table('draw_venue_court_allocations')->whereIn('draw_id', $eventDraws->pluck('id'))
            ->get()->groupBy(fn ($row) => $row->draw_id.'|'.$row->venue_id);

        $draws = $eventDraws->map(function ($draw) use ($courtAllocations) {
            $allocations = [];
            foreach ($draw->venues as $venue) {
                $allocations[$venue->id] = ($courtAllocations[$draw->id.'|'.$venue->id] ?? collect())
                    ->pluck('court_label')->map(fn ($label) => (string) $label)->all();
            }
            return ['id' => $draw->id, 'name' => $draw->drawName,
                'venues' => $draw->venues->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'court_allocations' => $allocations,
                'locked' => (bool) $draw->locked, 'published' => (bool) $draw->published];
        });
        $venues = $availableVenues->map(function ($venue) use ($drawVenues, $courtRows) {
            $assignedCounts = $drawVenues->where('id', $venue->id)
                ->map(fn ($assigned) => (int) ($assigned->pivot->num_courts ?? 0));
            $count = max(1, (int) ($venue->pivot->num_courts ?? 0), (int) ($assignedCounts->max() ?? 0));
            $courts = ($courtRows[$venue->id] ?? collect())->map(fn ($court) => [
                'label' => (string) $court->label, 'ball_type' => $court->ball_type,
            ])->values();
            if ($courts->isEmpty()) $courts = collect(range(1, $count))->map(fn ($label) => ['label' => (string) $label, 'ball_type' => null]);
            $ballTypes = $courts->map(fn ($court) => $court['ball_type'] ?: 'standard')->unique()->values();
            $numberedLabels = array_map('strval', range(1, $courts->count()));
            return [
                'id' => $venue->id, 'name' => $venue->name,
                'courts' => $courts->count(), 'court_list' => $courts->all(),
                'common_ball_type' => $ballTypes->count() === 1 ? $ballTypes->first() : 'mixed',
                'has_custom_courts' => $courts->pluck('label')->diff($numberedLabels)->isNotEmpty(),
            ];
        });
        $allVenues = Venue::whereNotIn('id', $availableVenues->pluck('id'))->orderBy('name')->get(['id', 'name']);

        $announcementDraft = $announcements->venueAssignmentDraft($event);

        return view('backend.schedule.event-venue-schedule', compact(
            'event', 'draws', 'venues', 'allVenues', 'announcementDraft'
        ));
    }

    public function addVenue(Request $request, Event $event)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'venue_id' => ['nullable', 'integer', 'exists:venues,id', 'required_without:name'],
            'name' => ['nullable', 'string', 'max:191', 'required_without:venue_id', 'regex:/\S/'],
            'courts' => ['required', 'integer', 'min:1', 'max:100'],
            'ball_type' => ['nullable', 'in:orange,green,yellow,red,standard'],
        ]);
        $venue = ! empty($data['venue_id']) ? Venue::findOrFail($data['venue_id']) : tap(new Venue(), function ($venue) use ($data) {
            $venue->forceFill(['name' => trim($data['name'])])->save();
        });
        $event->venues()->syncWithoutDetaching([$venue->id => ['num_courts' => $data['courts']]]);
        foreach (range(1, $data['courts']) as $label) {
            DB::table('event_venue_courts')->insertOrIgnore([
                'event_id' => $event->id, 'venue_id' => $venue->id, 'label' => (string) $label,
                'ball_type' => $data['ball_type'] ?: null, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $courtCount = DB::table('event_venue_courts')->where('event_id', $event->id)
            ->where('venue_id', $venue->id)->where('active', true)->count();
        $event->venues()->syncWithoutDetaching([$venue->id => ['num_courts' => $courtCount]]);
        return response()->json(['message' => "{$venue->name} added with {$courtCount} courts."]);
    }

    public function addCourt(Request $request, Event $event)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'label' => ['required', 'string', 'max:50', 'regex:/\S/'],
            'ball_type' => ['nullable', 'in:orange,green,yellow,red,standard'],
        ]);
        $allowed = $event->venues()->whereKey($data['venue_id'])->exists()
            || DB::table('draw_venues')->whereIn('draw_id', $event->draws()->pluck('id'))->where('venue_id', $data['venue_id'])->exists();
        abort_unless($allowed, 422, 'This venue does not belong to the event.');
        DB::table('event_venue_courts')->updateOrInsert([
            'event_id' => $event->id, 'venue_id' => $data['venue_id'], 'label' => trim($data['label']),
        ], ['ball_type' => $data['ball_type'] ?: null, 'active' => true, 'updated_at' => now(), 'created_at' => now()]);
        $count = DB::table('event_venue_courts')->where('event_id', $event->id)->where('venue_id', $data['venue_id'])->where('active', true)->count();
        $event->venues()->syncWithoutDetaching([$data['venue_id'] => ['num_courts' => $count]]);
        return response()->json(['message' => 'Court saved.']);
    }

    public function configureCourts(Request $request, Event $event, Venue $venue)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'courts' => ['required', 'integer', 'min:1', 'max:100'],
            'ball_type' => ['required', 'in:orange,green,yellow,red,standard'],
        ]);
        $drawIds = $event->draws()->pluck('id');
        $belongs = $event->venues()->whereKey($venue->id)->exists()
            || DB::table('draw_venues')->whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)->exists();
        abort_unless($belongs, 404);

        $labels = array_map('strval', range(1, $data['courts']));
        $existing = DB::table('event_venue_courts')->where('event_id', $event->id)
            ->where('venue_id', $venue->id)->pluck('label')->map(fn ($label) => (string) $label);
        $removed = $existing->diff($labels)->values();
        if ($removed->isNotEmpty()) {
            $lockedAllocation = DB::table('draw_venue_court_allocations')->join('draws', 'draws.id', '=', 'draw_venue_court_allocations.draw_id')
                ->where('draws.event_id', $event->id)->where('draw_venue_court_allocations.venue_id', $venue->id)
                ->whereIn('draw_venue_court_allocations.court_label', $removed)
                ->where(fn ($query) => $query->where('draws.locked', true)->orWhere('draws.published', true))->exists();
            if ($lockedAllocation) return response()->json([
                'message' => 'A court being removed is allocated to a locked or published draw and cannot be changed.',
            ], 422);
            $fixtureIds = Fixture::whereIn('draw_id', $drawIds)->pluck('id');
            $scheduled = OrderOfPlay::where('venue_id', $venue->id)->whereIn('court', $removed)
                ->where(fn ($query) => $query->whereIn('draw_id', $drawIds)->orWhereIn('fixture_id', $fixtureIds))->exists();
            if ($scheduled) return response()->json([
                'message' => 'A court being removed already has scheduled matches. Clear those bookings before reducing or replacing the courts.',
            ], 422);
        }

        DB::transaction(function () use ($event, $venue, $drawIds, $labels, $removed, $data) {
            if ($removed->isNotEmpty()) {
                DB::table('draw_venue_court_allocations')->whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)
                    ->whereIn('court_label', $removed)->delete();
                DB::table('event_venue_courts')->where('event_id', $event->id)->where('venue_id', $venue->id)
                    ->whereIn('label', $removed)->delete();
            }
            foreach ($labels as $label) {
                DB::table('event_venue_courts')->updateOrInsert([
                    'event_id' => $event->id, 'venue_id' => $venue->id, 'label' => $label,
                ], ['ball_type' => $data['ball_type'], 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            $event->venues()->syncWithoutDetaching([$venue->id => ['num_courts' => $data['courts']]]);
            DB::table('draw_venues')->whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)
                ->update(['num_courts' => $data['courts'], 'updated_at' => now()]);
        });

        return response()->json(['message' => "{$venue->name} now has {$data['courts']} {$data['ball_type']} courts."]);
    }

    public function updateAssignments(Request $request, Event $event)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'venues' => ['required', 'array', 'min:1'], 'venues.*.id' => ['required', 'integer', 'exists:venues,id'],
            'venues.*.courts' => ['required', 'integer', 'min:1', 'max:100'],
            'assignments' => ['required', 'array'], 'assignments.*.draw_id' => ['required', 'integer'],
            'assignments.*.venue_ids' => ['present', 'array'], 'assignments.*.venue_ids.*' => ['integer'],
            'assignments.*.court_allocations' => ['present', 'array'],
            'assignments.*.court_allocations.*.venue_id' => ['required', 'integer'],
            'assignments.*.court_allocations.*.court_labels' => ['required', 'array', 'min:1'],
            'assignments.*.court_allocations.*.court_labels.*' => ['string', 'max:50'],
        ]);
        $draws = $event->draws()->whereIn('id', collect($data['assignments'])->pluck('draw_id'))->get()->keyBy('id');
        if ($draws->count() !== count($data['assignments'])) abort(422, 'One or more draws do not belong to this event.');
        $courtCounts = collect($data['venues'])->mapWithKeys(fn ($venue) => [(int) $venue['id'] => (int) $venue['courts']]);
        $allowedVenueIds = $event->venues()->pluck('venues.id')
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
                    $allocationVenueIds = collect($assignment['court_allocations'])->pluck('venue_id')->map(fn ($venueId) => (int) $venueId);
                    if ($allocationVenueIds->count() !== $allocationVenueIds->unique()->count()) {
                        throw new \InvalidArgumentException('A venue can have only one court allocation per age group.');
                    }
                    $allocationVenueIds = $allocationVenueIds->unique()->sort()->values()->all();
                    if ($allocationVenueIds !== collect($venueIds)->unique()->sort()->values()->all()) {
                        throw new \InvalidArgumentException('Choose at least one physical court for every selected venue.');
                    }
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
                    DB::table('draw_venue_court_allocations')->where('draw_id', $draw->id)->delete();
                    foreach ($assignment['court_allocations'] as $allocation) {
                        $venueId = (int) $allocation['venue_id'];
                        if (! in_array($venueId, $venueIds, true)) continue;
                        if (count($allocation['court_labels']) !== count(array_unique($allocation['court_labels']))) {
                            throw new \InvalidArgumentException('The same physical court cannot be selected twice for an age group.');
                        }
                        $validLabels = DB::table('event_venue_courts')->where('event_id', $draw->event_id)
                            ->where('venue_id', $venueId)->where('active', true)->pluck('label')->map(fn ($label) => (string) $label)->all();
                        if (array_diff($allocation['court_labels'], $validLabels)) {
                            throw new \InvalidArgumentException('A selected court is not active at this venue.');
                        }
                        foreach (array_unique($allocation['court_labels']) as $label) {
                            DB::table('draw_venue_court_allocations')->insert([
                                'draw_id' => $draw->id, 'venue_id' => $venueId, 'court_label' => $label,
                                'created_at' => now(), 'updated_at' => now(),
                            ]);
                        }
                    }
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
            'draw_starts' => ['nullable', 'array'],
            'draw_starts.*.draw_id' => ['required', 'integer'],
            'draw_starts.*.start' => ['nullable', 'date'],
        ]);
    }
}
