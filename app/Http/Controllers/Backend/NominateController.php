<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\EventNomination;
use Illuminate\Http\Request;


class NominateController extends Controller
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
        return 'nominate';
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
      'event_id' => 'required|integer|exists:events,id',
      'category_event_id' => 'required|integer|exists:category_events,id',
      'players' => 'required|array|min:1',
      'players.*' => 'integer|exists:players,id',
    ]);

    $categoryEvent = CategoryEvent::findOrFail($validated['category_event_id']);

    \DB::transaction(function () use ($validated, $categoryEvent) {
      // Remove existing nominations for this category
      EventNomination::where('category_event_id', $categoryEvent->id)->delete();

      foreach ($validated['players'] as $playerId) {
        EventNomination::create([
          'event_id' => $categoryEvent->event_id,
          'category_event_id' => $categoryEvent->id,
          'player_id' => $playerId,
        ]);
      }
    });

    // Return list of players for UI update
    $players = EventNomination::where('category_event_id', $categoryEvent->id)
      ->with('player:id,name,surname,email,cellNr')
      ->get()
      ->map(function ($nom) {
        return [
          'nomination_id' => $nom->id,
          'name' => $nom->player->name,
          'surname' => $nom->player->surname,
          'email' => $nom->player->email,
          'cellNr' => $nom->player->cellNr,
        ];
      });

    return response()->json([
      'success' => true,
      'message' => 'Nominations saved successfully!',
      'nominations' => $players,
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
    public function destroy(Request $request)
    {
       
        $res = EventNomination::where('id',$request->id)->delete();
        return $res;
    }

    public function nominationInCategory($id){
        return EventNomination::where('category_event_id',$id)->with('player')->get();
    }

  public function togglePublish($id)
  {
    $nomination = CategoryEvent::findOrFail($id);

    // Toggle the publish flag
    $nomination->nominations_published = $nomination->nominations_published ? 0 : 1;
    $nomination->save();

    // Prepare boolean for message clarity
    $published = (bool) $nomination->nominations_published;

    return response()->json([
      'success' => true,
      'message' => $published
        ? 'Nomination list published!'
        : 'Nomination list unpublished!',
      'published' => $published,
    ]);
  }


  public function playersForCategory($id)
  {
    $categoryEvent = CategoryEvent::findOrFail($id);

    // 🧩 Get ALL players in the system (not only event-linked)
    $allPlayers = \App\Models\Player::select('id', 'name', 'surname', 'email', 'cellNr')
      ->orderBy('surname')
      ->get();

    // 🧩 Get nominated player IDs for this category
    $nominatedIds = EventNomination::where('category_event_id', $id)
      ->pluck('player_id')
      ->toArray();

    // 🧠 Return proper data for Select2
    $players = $allPlayers->map(function ($p) use ($nominatedIds) {
      return [
        'id' => $p->id,
        'text' => trim("{$p->name} {$p->surname}") ?: 'Unknown Player',
        'email' => $p->email ?? '',
        'cellNr' => $p->cellNr ?? '',
        'nominated' => in_array($p->id, $nominatedIds),
      ];
    });

    return response()->json($players);
  }

  public function getSelected($categoryId)
  {
    // Return array of player_ids already nominated for this category
    $ids = \App\Models\EventNomination::where('category_event_id', $categoryId)
      ->pluck('player_id')
      ->toArray();

    return response()->json($ids);
  }

 

  public function save(Request $request)
  {
    $data = $request->validate([
      'category_event_id' => 'required|integer|exists:category_events,id',
      'player_ids' => 'array',
      'player_ids.*' => 'integer|exists:players,id',
    ]);

    $categoryEventId = $data['category_event_id'];
    $playerIds = $data['player_ids'] ?? [];

    // 🧹 Remove old nominations
    EventNomination::where('category_event_id', $categoryEventId)->delete();

    // 💾 Insert new nominations
    foreach ($playerIds as $pid) {
      EventNomination::create([
        'category_event_id' => $categoryEventId,
        'player_id' => $pid,
      ]);
    }

    return response()->json(['success' => true, 'count' => count($playerIds)]);
  }

  public function partialTable($id)
  {
    $category = CategoryEvent::with(['nominations.player'])->findOrFail($id);
    return view('backend.nominations.partials.table', compact('category'));
  }
  public function remove(Request $request)
  {
    $request->validate([
      'nomination_id' => 'required|integer|exists:event_nominations,id',
    ]);

    $nomination = \App\Models\EventNomination::findOrFail($request->nomination_id);
    $nomination->delete();

    return response()->json([
      'success' => true,
      'message' => 'Nomination removed successfully!',
      'removed_id' => $request->nomination_id,
    ]);
  }



}
