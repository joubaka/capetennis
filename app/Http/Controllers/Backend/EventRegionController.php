<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\TeamRegion;
use App\Models\Team;
use Illuminate\Http\Request;

class EventRegionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
  public function store(Request $request)
  {
    $validated = $request->validate([
      'event_id' => ['required', 'integer', 'exists:events,id'],
      'region_id' => ['required'],
    ]);

    $event = Event::findOrFail($validated['event_id']);
    $this->authorize('event-draw.view', $event);

    $regionInput = $validated['region_id'];

    // If numeric → existing region
    if (is_numeric($regionInput)) {
      $region = TeamRegion::findOrFail($regionInput);
    } else {
      // New region → strip quotes if Select2 tags added them
      $cleanName = trim($regionInput, '"');
      if ($cleanName === '') {
        return response()->json(['message' => 'A region name is required.'], 422);
      }
      $region = TeamRegion::whereRaw('LOWER(region_name) = ?', [mb_strtolower($cleanName)])->first();
      if (!$region) {
        $region = TeamRegion::create([
          'region_name' => $cleanName,
        ]);
      }
    }

    // Attach to event (ignore if already attached)
    $event->regions()->syncWithoutDetaching([$region->id]);

    // Get the pivot ID
    $pivotId = $event->regions()
      ->where('region_id', $region->id)
      ->first()
      ->pivot->id;

    return response()->json([
      'id' => $region->id,          // region id
      'region_name' => $region->region_name, // clean name
      'pivot_id' => $pivotId              // pivot id for detach/remove
    ]);
  }


  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
  public function destroy($id)
  {
    $eventRegion = EventRegion::with('events')->findOrFail($id);
    $event = $eventRegion->events;
    $this->authorize('event-draw.view', $event);

    $hasTeams = Team::where('region_id', $eventRegion->region_id)
      ->whereHas('category', fn ($query) => $query->where('event_id', $event->id))
      ->exists();
    if ($hasTeams) {
      return response()->json([
        'message' => 'This region cannot be removed while it still has teams. Move or delete the teams first.',
      ], 409);
    }

    $eventRegion->delete();

    return response()->json(['message' => 'Region removed from the event.']);
  }
}
