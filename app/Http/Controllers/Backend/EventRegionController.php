<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\TeamRegion;
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
   
    $event = Event::findOrFail($request->event_id);

    $regionInput = $request->input('region_id');

    // If numeric → existing region
    if (is_numeric($regionInput)) {
      $region = TeamRegion::findOrFail($regionInput);
    } else {
      // New region → strip quotes if Select2 tags added them
      $cleanName = trim($regionInput, '"');
      $region = TeamRegion::create([
        'region_name' => $cleanName,
        // fill other defaults if needed...
      ]);
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
       
        EventRegion::where('id',$id)->delete();
        return 'deleted';
    }
}
