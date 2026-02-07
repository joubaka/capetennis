<?php

namespace App\Http\Controllers\backend;

use App\Classes\Brackets;
use App\Classes\CapeTennisDraw;
use App\Classes\MonradFeedin;
use App\Helpers\Fixtures;
use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\Venues;
use App\Models\DrawFormats;
use App\Models\DrawRegistrations;
use App\Models\DrawSetting;
use App\Models\DrawType;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\Player;
use App\Models\Registration;
use App\Models\TeamFixture;

use App\Services\CtBracket;
use App\Services\DrawBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DrawController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index()
  {
    $data['event'] = Event::find($_GET['id']);

    return view('backend.draw.draw-index', $data);

    if ($data['event']->eventType == 3) {
      return view('backend.draw.team.draw-index-team', $data);
    } else {
      return view('backend.draw.individual.draw-index-individual', $data);
    }
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
    $draw = new Draw();
    $draw->drawName = $request->name;
    $draw->event_id = $request->event_id;

    $draw->save();

    $settings = new DrawSetting();
    $settings->draw_id = $draw->id;
    $settings->num_sets = $request->num_sets;
    $settings->save();
    return redirect()->back();
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function show($id, Request $request)
  {
   
    // return $request;
    // 1= ind,2=camp,3=team,4high school,5 cav,6 ind pdf,7 plat,8 plat high,9 parentchind
    //dd('check');
    $data['drawTypes'] = DrawType::all();
    $data['drawFormats'] = DrawFormats::all();
    $data['venues'] = Venues::all();
    $data['event'] = Draw::find($id)->event;
    // dd($data['bracket']);
    /// teamm event
   
    if ($data['event']->eventType == 3) {
      $data['fixtures'] = TeamFixture::where('draw_id', $id)->get();
      $data['players'] = Player::all();
      $draw = Draw::find($id);
      $data['draw'] = $draw;
      return view('backend.draw.team.draw-show-team', $data);
    } elseif ($data['event']->eventType == 5) {
      //cavaliers trials
      $draw = Draw::find($id);
      $data['players'] = Player::all();
      $data['draw'] = $draw;
      $size = 32;
      $printDraw = new MonradFeedin($draw, $size);

      $data['printDraw'] = $printDraw->print();

      $data['allData'] = Fixture::where('draw_id', $id)
        ->get()
        ->sortBy('id');
      $data['bracket'] = new CapeTennisDraw($draw->id);
      $data['fixtures'] = Fixture::where('draw_id', $draw->id)->get();
      //           dd('showdraw 6',$data);
      return view('backend.draw.individual.cavaliersTrials', $data);
    } elseif ($data['event']->eventType == 6) {
      $draw = Draw::find($id);
      $data['players'] = Player::all();
      $data['draw'] = $draw;
      $size = 32;
      $printDraw = new MonradFeedin($draw, $size);
      $data['printDraw'] = $printDraw->print();

      $data['allData'] = Fixture::where('draw_id', $id)
        ->get()
        ->sortBy('id');
      $data['bracket'] = new CapeTennisDraw($draw->id);
      $data['fixtures'] = Fixture::where('draw_id', $draw->id)->get();
      // dd('showdraw 6',$data);
      return view('backend.draw.individual.draw-show-individual', $data);
    } else {
      dd('showdraw else', $data['draw']);
      return view('backend.draw.individual.draw-show-individual', $data);
    }
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
    $draw = DrawSetting::where('draw_id', $id)->first();
    $draw->draw_type_id = $request->draw_type;
    $draw->draw_format_id = $request->draw_format;
    $draw->num_sets = $request->num_sets;
    $draw->save();

    $d = Draw::find($id);
    $d->drawName = $request->name;
    $d->save();

    $responce = Fixtures::createMonrad32Fixtures($d->id);
    //  return $responce;
    return redirect()->back();
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id)
  {
    $draw = Draw::find($id);

    if (!$draw) {
      return response()->json(['success' => false, 'message' => 'Draw not found.'], 404);
    }

    try {
      // Team fixtures
      if ($draw->drawFixtures()->count() > 0) {
        TeamFixture::where('draw_id', $id)->delete();
        $draw->delete();

        return response()->json([
          'success' => true,
          'message' => '✅ Draw and team fixtures deleted.'
        ]);
      }

      // Individual fixtures
      if ($draw->fixtures()->count() > 0) {
        Fixture::where('draw_id', $id)->delete();
        $draw->delete();

        return response()->json([
          'success' => true,
          'message' => '✅ Draw and fixtures deleted.'
        ]);
      }

      // No fixtures
      $draw->delete();

      return response()->json([
        'success' => true,
        'message' => '✅ Draw deleted.'
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => '❌ Error deleting draw: ' . $e->getMessage(),
      ], 500);
    }
  }

  public function unlock_draw(Request $request, $draw)
  {
    // Optional: Only allow admins or authorized users
    // $this->authorize('update', $draw);

    // Update draw lock status
    $draw = Draw::find($draw);
    $draw->locked = false;
    $draw->save();

    return response()->json([
      'success' => true,
      'message' => 'Draw has been locked.',
    ]);
  }
 public function lock_draw(Request $request, Draw $draw)
{
    $draw->locked = $request->boolean('lock');
    $draw->save();

    if ($draw->locked) {
        $builder = new DrawBuilder($draw);

        // 🧠 Step 1: Rank players
        $builder->rankPlayers();

        // 🪪 Step 2: Get ranked player output before continuing
        $ranked = $builder->getFinalPositionsVerbose(); // custom debug method below

        // 🧩 Step 3: Assign codes
        $builder->assignSeedingCodes();

        // 🏗️ Step 4: Generate draw tree
        $fixtureMap = $builder->generatePlayoffFixtures();

        return response()->json([
            'message' => 'Fixtures created',
            'fixture_map' => $fixtureMap,
            'ranked_players' => $ranked,
            'draw' => $draw,
        ]);
    } else {
        Fixture::where('draw_id', $draw->id)
            ->where('stage', '!=', 'RR')
            ->delete();

        return response()->json([
            'status' => 'ok',
            'locked' => $draw->locked,
        ]);
    }
}




  public function draw_index($id)
  {
    $data['event'] = Event::find($id);
    return view('backend.draw.individual.draw-index-individual', $data);
  }

  public function add_draw_registration(Request $request, $id)
  {
    $draw = Draw::find($id);

    foreach ($request->players as $player) {
      $draw->registrations()->attach($player);
    }

    return redirect()->back();
  }

  public function remove_draw_registration(Request $request, $id)
  {
    DrawRegistrations::where('registration_id', $id)
      ->where('draw_id', $request->draw_id)
      ->delete();

    return 'delete';
  }

  public function add_draw_registration_category(Request $request, $id)
  {
    $draw = Draw::find($id);
    $eventCategory = CategoryEvent::find($request->category);
    $draw = Draw::find($id);
    $eventCategory = CategoryEvent::find($request->category);
    foreach ($eventCategory->registrations as $key => $value) {
      $draw->registrations()->attach($value->id);
    }

    return redirect()->back();
  }

  public function change_seed(Request $request, $id)
  {
    $drawReg = DrawRegistrations::where('registration_id', $request->reg)
      ->where('draw_id', $id)
      ->first();
    $drawReg->seed = $request->seed;
    $drawReg->save();
    return $drawReg;
    return $request;
  }

  public function changeAllSeeds(Request $request)
  {
    $order = $request->neworder;
    foreach ($order as $key => $o) {
      $d = DrawRegistrations::where('registration_id', $o)->first();
      $d->seed = $key + 1;
      $d->save();
    }
    return $request;
  }

  public function togglePublish($id)
  {
    $draw = Draw::findOrFail($id);

    $draw->published = !$draw->published;
    $draw->save();

    return response()->json([
      'success' => true,
      'published' => (bool) $draw->published,
      'id' => $draw->id,
    ]);
  }

  public function togglePublishSchedule($id)
  {
    //  $draw = Draw::where('id', $id)->with('drawFixtures', function ($item) {
    //     return $item->with('results');
    // })->get();

    $draw = Draw::find($id);

    if ($draw->oop_published == 1) {
      $draw->oop_published = 0;
    } else {
      $draw->oop_published = 1;
    }

    $draw->save();
    return $draw;
  }

  public function getPDF(Request $request, $id)
  {
    $data['draw'] = Draw::find($id);
    //$data = ['title' => 'Printable View', 'content' => 'This is the content to print.'];
    //dd($data);
    // Generate PDF from Blade view
    $pdf = PDF::loadView('frontend.draw.print', $data);
    $pdf->setPaper('A4', 'portrait');

    // Enable scaling for PDF content to fit the page
    $pdf->setOption('isHtml5ParserEnabled', true); // Enable HTML5 parsing for better scaling
    $pdf->setOption('isPhpEnabled', true); // Enable PHP for dynamic content (if needed)

    return view('frontend.draw.print', $data);



    $data['draw'] = Draw::find($id);

    $data['event'] = Draw::find($id)->events;
    $data['bracket'] = new CapeTennisDraw($id);

    $pdf = Pdf::loadView('frontend.draw.show', $data)->setOptions(['defaultFont' => 'sans-serif']);

    return $pdf->download('pdfview.pdf');
  }

  public function getAjaxVenues($id)
  {
    $draw = Draw::find($id);
    return $draw->venues->pluck('id')->toArray();
  }

  public function addVenueDraw($drawId)
  {
    $draw = Draw::findOrFail($drawId);

    $venueId = request('venue');
    $numCourts = request('numCourts');

    // If exists → updates
    // If not → inserts
    $draw->venues()->syncWithoutDetaching([
      $venueId => ['num_courts' => $numCourts]
    ]);

    return 'success save venue';
  }

  public function removeVenueDraw($drawId)
  {
    $draw = Draw::find($drawId);
    $venueId = $_GET['venue'];
    $draw->venues()->detach([$venueId]);
    return 'success remove venue';
  }

  public function generateFromModal(Request $request)
  {

    $validated = $request->validate([
      'event_id' => 'required|exists:events,id',
      'draw_name' => 'required|string|max:255',
      'draw_format_id' => 'required|in:1,2,3',
    ]);

    $draw = new Draw();
    $draw->event_id = $validated['event_id'];
    $draw->drawName = $validated['draw_name'];
    $draw->drawType_id = $validated['draw_format_id'];
    $draw->published = false;


    $draw->save();
    $drawSettings = new DrawSetting();
    $drawSettings->draw_id = $draw->id;
    $drawSettings->save();
    return redirect()->back()->with('success', 'Draw created successfully.');
  }


  public function generate(Request $request)
  {

    $request->validate([
      'category_event_id' => 'required|exists:category_events,id',
      'draw_name' => 'required|string|max:255',
      'draw_type' => 'required|string|max:255',
    ]);
    dd($request);
    // Optional: get the event ID from the CategoryEvent relation
    $categoryEvent = \App\Models\CategoryEvent::findOrFail($request->category_event_id);

    $draw = Draw::create([
      'drawName' => $request->draw_name,
      'drawType_id' => $request->draw_type,
      'category_event_id' => $categoryEvent->id,
      'event_id' => $categoryEvent->event_id,
      'published' => 0,
      'oop_published' => 0,
      'locked' => 0,
      'oop_created' => 0,
    ]);

    // Optionally call generator based on type
    switch ($request->draw_type) {
      case 'round_robin':
        // $this->generateRoundRobin($draw);
        break;
      case 'knockout':
        // $this->generateKnockout($draw);
        break;
    }

    return back()->with('success', 'Draw "' . $draw->drawName . '" created successfully!');
  }

  public function showver1($id)
  {
    $draw = Draw::with([
      'categoryEvent.category',
      'registrations.players',
      'drawFormat'
    ])->findOrFail($id);

    $drawFormats = DrawFormats::all();
    $drawTypes = \App\Models\DrawType::all();

    // Extract names for the draw preview
    $players = $draw->registrations->pluck('players.0.name')->toArray();

    // Build Monrad-style rounds (8-player example)
    $rounds = [
      1 => [
        ['player1' => $players[0] ?? 'TBD', 'player2' => $players[7] ?? 'TBD'],
        ['player1' => $players[3] ?? 'TBD', 'player2' => $players[4] ?? 'TBD'],
        ['player1' => $players[2] ?? 'TBD', 'player2' => $players[5] ?? 'TBD'],
        ['player1' => $players[1] ?? 'TBD', 'player2' => $players[6] ?? 'TBD'],
      ],
      2 => [
        ['player1' => 'Winner M1', 'player2' => 'Winner M2'],
        ['player1' => 'Winner M3', 'player2' => 'Winner M4'],
      ],
      3 => [
        ['player1' => 'Winner SF1', 'player2' => 'Winner SF2'],
      ]
    ];

    return view('backend.draw.show2', compact('draw', 'drawFormats', 'drawTypes', 'rounds'));
  }

  public function addPlayers(Request $request, Draw $draw)
  {
    $request->validate([
      'players' => 'required|array',
      'players.*' => 'exists:players,id',
    ]);

    foreach ($request->players as $playerId) {
      $draw->players()->syncWithoutDetaching($playerId);
    }

    return redirect()->back()->with('success', 'Players added to draw.');
  }
  public function manage($id)
  {
    $draw = Draw::with(['categoryEvent.category', 'registrations.players'])->findOrFail($id);



    return view('backend.draw.manage', compact('draw'));
  }

  public function players($id)
  {



    $draw = Draw::with(['categoryEvent.category', 'registrations.players'])->findOrFail($id);



    return view('backend.draw.manage-players', compact('draw'));
  }





  public function settings($id)
  {
    $draw = Draw::with([
      'categoryEvent.category',
      'registrations.players',
      'event.registrations',
      'drawFixtures.fixtureResults',
      'drawFixtures.registration1.players',
      'drawFixtures.registration2.players'
    ])->findOrFail($id);

    $drawTypes = DrawType::all();
    $drawFormats = DrawFormats::all();

    $currentFormat = $drawFormats->firstWhere('id', $draw->settings->draw_format_id)?->name ?? '';
    $supportsBoxes = Str::contains(strtolower($currentFormat), 'round robin');

    $numBoxes = $draw->settings->boxes ?? 2;

    $registrations = $draw->registrations->sort(function ($a, $b) {
      $aSeed = $a->pivot->seed ?? null;
      $bSeed = $b->pivot->seed ?? null;

      if ($aSeed && $bSeed) return $aSeed <=> $bSeed;
      if ($aSeed) return -1;
      if ($bSeed) return 1;

      $aSurname = strtolower(optional($a->players->first())->surname ?? '');
      $bSurname = strtolower(optional($b->players->first())->surname ?? '');
      return $aSurname <=> $bSurname;
    })->values();

    if ($supportsBoxes && $numBoxes > 1) {
      foreach ($registrations as $i => $reg) {
        $cycle = (int) floor($i / $numBoxes);
        $indexInCycle = $i % $numBoxes;

        $boxNumber = $cycle % 2 === 0
          ? $indexInCycle + 1
          : $numBoxes - $indexInCycle;

        $reg->pivot->box_number = $boxNumber;
        $reg->pivot->save();
      }
    }

    $splitBoxes = collect();
    if ($supportsBoxes) {
      $grouped = $registrations
        ->filter(fn($reg) => isset($reg->pivot->box_number))
        ->groupBy(fn($reg) => $reg->pivot->box_number)
        ->sortKeys();

      for ($i = 1; $i <= $numBoxes; $i++) {
        $splitBoxes[$i] = $grouped->get($i, collect());
      }
    }

    // 🔥 Add this line: generate the bracket HTML
    $bracketHtml = (new DrawBuilder($draw))->generatePlayoffDraw();

    return view('backend.draw.draw-settings', [
      'draw' => $draw,
      'drawTypes' => $drawTypes,
      'drawFormats' => $drawFormats,
      'supportsBoxes' => $supportsBoxes,
      'numBoxes' => $numBoxes,
      'splitBoxes' => $splitBoxes,
      'registrations' => $registrations,
      'bracketHtml' => $bracketHtml // 👈 Pass to view
    ]);
  }





  public function getPlayers(Draw $draw)
  {

    return response()->json($draw->players()->with('team', 'category')->get());
  }



  public function importFromCategory(Draw $draw)
  {
    $categoryEvent = $draw->categoryEvent;

    if (!$categoryEvent) {
      abort(404, 'This draw is not linked to a category event.');
    }

    // Load all players in the registrations under this category event
    $registrations = $categoryEvent->registrations()->with('players')->get();

    foreach ($registrations as $registration) {
      foreach ($registration->players as $player) {
        $draw->registrations()->syncWithoutDetaching($registration->id);
      }
    }

    return response()->json(['message' => 'All players from category event added to draw.']);
  }


  public function addPlayerDraw(Request $request, Draw $draw)
  {
    $draw->registrations()->syncWithoutDetaching($request->player_id);
    return response()->noContent();
  }

  public function removePlayerDraw(Request $request, Draw $draw)
  {
    $draw->registrations()->detach($request->player_id);
    return response()->noContent();
  }

  public function updatePlayers(Request $request, $id)
  {
    $draw = Draw::findOrFail($id);
    $playerIds = $request->input('players', []);

    $draw->registrations()->sync($playerIds);

    return response()->json(['message' => 'Players updated successfully.']);
  }


  public function addPlayer(Request $request, Draw $draw)
  {
    $registrationId = $request->input('registration_id');

    // Fetch registration and validate
    $registration = Registration::with('players')->find($registrationId);
    if (!$registration || $registration->players->isEmpty()) {
      return response()->json([
        'error' => 'Invalid registration or no players found.'
      ], 404);
    }

    // Ensure not in another draw for same category_event
    $categoryEventId = $draw->category_event_id;
    $alreadyAssigned = Draw::where('category_event_id', $categoryEventId)
      ->whereHas('registrations', function ($q) use ($registrationId) {
        $q->where('registration_id', $registrationId);
      })
      ->exists();

    if ($alreadyAssigned) {
      return response()->json([
        'error' => 'Player already assigned to another draw in this event.'
      ], 400);
    }

    // Attach if not already added to this draw
    if (!$draw->registrations()->where('registration_id', $registrationId)->exists()) {
      $draw->registrations()->attach($registrationId);
    }

    return response()->json([
      'message' => 'Player added successfully.',
      'player_id' => $registrationId,
      'draw_id' => $draw->id,
      'player_name' => $registration->players[0]->name . ' ' . $registration->players[0]->surname
    ]);
  }



  public function showBracket()
  {
    $matches = [
      ['player1' => 'Alice', 'player2' => 'Bob'],
      ['player1' => 'Charlie', 'player2' => 'David'],
      // ...
    ];

    $bracket = app(CtBracket::class)->build(32, $matches);

    return view('backend.draw.formats.monrad', compact('bracket'));
  }

  public function addCategoryPlayers(Request $request)
  {

    $request->validate([
      'category_id' => 'required|exists:category_events,id',
      'draw_id' => 'required|exists:draws,id',
    ]);

    $draw = Draw::with('registrations.players')->findOrFail($request->draw_id);

    $categoryEvent = CategoryEvent::with('registrations.players')->findOrFail($request->category_id);

    $existingPlayerIds = $draw->registrations->flatMap(function ($reg) {
      return $reg->players->pluck('id');
    })->unique()->toArray();

    $attached = 0;

    foreach ($categoryEvent->registrations as $registration) {
      foreach ($registration->players as $player) {
        if (!in_array($player->id, $existingPlayerIds)) {
          $draw->registrations()->syncWithoutDetaching($registration->id);
          $attached++;
        }
      }
    }

    return response()->json(['message' => "$attached players added."]);
  }




  public function addPlayerToDraw(Request $request)
  {


    $request->validate([
      'draw_id' => 'required|integer|exists:draws,id',
      'player_ids' => 'required|array',

    ]);




    $draw = Draw::findOrFail($request->draw_id);

    $added = 0;
    $skipped = 0;

    foreach ($request->player_ids as $playerId) {

      $exists = DB::table('draw_registrations')
        ->where('draw_id', $draw->id)
        ->where('registration_id', $playerId)
        ->exists();

      if (!$exists) {

        DB::table('draw_registrations')->insert([
          'draw_id' => $draw->id,
          'registration_id' => $playerId,
          'created_at' => now(),
          'updated_at' => now(),
        ]);
        $added++;
      } else {
        $skipped++;
      }
    }

    return response()->json([
      'message' => "{$added} player(s) added to draw, {$skipped} skipped (already present)."
    ]);
  }



  public function getDrawPlayers(Draw $draw)
  {

    $registrations = $draw->registrations; // assumes Draw has players() relation via Registration

    return view('backend.draw.partials.draw-players', compact('registrations', 'draw'));
  }

  // In DrawController.php

  public function removePlayer(Request $request)
  {

    $request->validate([
      'registration_id' => 'required|integer|exists:registrations,id',
      'draw_id' => 'required|integer|exists:draws,id',
    ]);

    $draw = Draw::findOrFail($request->draw_id);
    $draw->registrations()->detach($request->registration_id);

    return response()->json(['message' => 'Player removed.']);
  }

  public function clearPlayers(Request $request)
  {

    $request->validate([
      'draw_id' => 'required|integer|exists:draws,id',
    ]);

    $draw = Draw::findOrFail($request->draw_id);
    $draw->registrations()->detach();



    return response()->json(['message' => 'All players removed.']);
  }



  public function updateSettings(Request $request, Draw $draw)
  {

    // Save to draw_settings (create or update)
    DrawSetting::updateOrCreate(
      ['draw_id' => $draw->id], // Match by draw
      [
        'boxes' => $request->input('boxes'),
        'playoff_size' => $request->input('playoff_size'),
        'num_sets' => $request->input('num_sets'),
        'draw_type_id' => $request->input('draw_type_id'),
        'draw_format_id' => $request->input('draw_format_id'),

      ]
    );

    return back()->with('success', 'Draw settings updated successfully.');
  }

  public function getSplitBoxes(Request $request, $id)
  {
    $draw = Draw::with([
      'registrations' => function ($q) {
        $q->orderByRaw('COALESCE(draw_registrations.seed, 99999)')
          ->orderBy('draw_registrations.box_number');
      },
      'registrations.players',
      'settings'
    ])->findOrFail($id);

    $numBoxes = max((int) $request->input('boxes', 2), 1);

    // Sort by seed
    $registrations = $draw->registrations->sort(function ($a, $b) {
      $aSeed = $a->pivot->seed ?? PHP_INT_MAX;
      $bSeed = $b->pivot->seed ?? PHP_INT_MAX;
      return $aSeed <=> $bSeed;
    })->values();

    // Reset all box numbers
    foreach ($registrations as $reg) {
      $draw->registrations()->updateExistingPivot($reg->id, ['box_number' => null]);
    }

    // Serpentine box assignment
    $splitBoxes = [];
    foreach ($registrations as $i => $registration) {
      $cycle = (int) floor($i / $numBoxes);
      $indexInCycle = $i % $numBoxes;

      $boxNumber = $cycle % 2 === 0
        ? $indexInCycle + 1               // left to right
        : $numBoxes - $indexInCycle;      // right to left

      $splitBoxes[$boxNumber][] = $registration;

      $draw->registrations()->updateExistingPivot($registration->id, [
        'box_number' => $boxNumber,
      ]);
    }

    // Save box count
    $draw->settings->boxes = $numBoxes;
    $draw->settings->save();

    return view('backend.draw.partials.split-box-preview', [
      'splitBoxes' => collect($splitBoxes)->sortKeys(),
    ])->render();
  }





  public function updateSeeds(Request $request, $id)
  {
    $data = $request->validate([
      'ordered_seeds' => 'required|array',
      'ordered_seeds.*.registration_id' => 'required|integer',
      'ordered_seeds.*.seed' => 'required|integer',
    ]);

    foreach ($data['ordered_seeds'] as $item) {
      DB::table('draw_registrations')
        ->where('draw_id', $id)
        ->where('registration_id', $item['registration_id'])
        ->update(['seed' => $item['seed']]);
    }

    return response()->json(['message' => 'Seeds updated']);
  }
  public function assignBoxNumbers(Request $request, $id)
  {
    $draw = Draw::with(['registrations.players', 'settings'])->findOrFail($id);
    $numBoxes = (int) $request->input('boxes', 2);

    $templates = $this->generateSnakeTemplates(8, 64);
    $template = $templates[$numBoxes] ?? null;

    if (!$template) {
      return response()->json(['message' => 'Template not defined for ' . $numBoxes . ' boxes.'], 422);
    }

    $registrations = $draw->registrations->sort(function ($a, $b) {
      $aSeed = $a->pivot->seed ?? PHP_INT_MAX;
      $bSeed = $b->pivot->seed ?? PHP_INT_MAX;
      return $aSeed <=> $bSeed;
    })->values();

    foreach ($registrations as $i => $reg) {
      $seed = $reg->pivot->seed ?? ($i + 1);
      $box = $template[$seed] ?? null;

      if ($box) {
        DB::table('draw_registrations') // ✅ singular
          ->where('draw_id', $draw->id)
          ->where('registration_id', $reg->id)
          ->update(['box_number' => $box]);
      }
    }

    return response()->json(['message' => 'Box numbers assigned.']);
  }



  public function generateSnakeTemplates($maxBoxes = 8, $maxSeeds = 64)
  {
    $templates = [];

    for ($boxes = 1; $boxes <= $maxBoxes; $boxes++) {
      $templates[$boxes] = [];
      $seed = 1;
      $direction = 1;

      while ($seed <= $maxSeeds) {
        $range = range(1, $boxes);
        if ($direction === -1) {
          $range = array_reverse($range);
        }

        foreach ($range as $box) {
          if ($seed > $maxSeeds) break;
          $templates[$boxes][$seed] = $box;
          $seed++;
        }

        $direction *= -1;
      }
    }

    return $templates;
  }

  public function generateRoundRobinFixtures(Request $request, $id)
  {
    $draw = Draw::findOrFail($id);
    $builder = new DrawBuilder($draw);

    try {
      $builder->generateRoundRobinFixtures($draw);

      return response()->json(['message' => 'Round Robin fixtures generated successfully.']);
    } catch (\Throwable $e) {
      return response()->json([
        'message' => 'Error generating fixtures.',
        'error' => $e->getMessage(),
      ], 500);
    }
  }
  public function getDrawPreview($id)
  {
    $draw = Draw::with([
      'drawFixtures.registration1.players',
      'drawFixtures.registration2.players'
    ])->findOrFail($id);


    return view('backend.draw.partials.draw-preview', compact('draw'));
  }

  public function showBoxMatrix($drawId)
  {
    $draw = Draw::with(['registrations.players'])->findOrFail($drawId);

    $boxes = $draw->registrations->filter(fn($r) => $r->pivot->box_number)
      ->groupBy(fn($r) => $r->pivot->box_number)
      ->sortKeys();

    return view('backend.draw.roundrobin-matrix', compact('draw', 'boxes'));
  }

  public function renderBoxSVG($drawId, $boxNumber)
  {
    $draw = Draw::with(['registrations.players'])->findOrFail($drawId);

    $players = $draw->registrations
      ->filter(fn($r) => $r->pivot->box_number == $boxNumber)
      ->sortBy(fn($r) => $r->pivot->seed ?? PHP_INT_MAX)
      ->values();

    $count = $players->count();
    $cellSize = 80;
    $width = ($count + 1) * $cellSize;
    $height = ($count + 1) * $cellSize;

    $svg = [
      '<?xml version="1.0" encoding="UTF-8"?>',
      "<svg xmlns='http://www.w3.org/2000/svg' width='{$width}' height='{$height}' style='font-family:Arial;font-size:14px'>",
    ];

    // Headers
    for ($i = 0; $i < $count; $i++) {
      $name = strtoupper($players[$i]->players->first()?->short_name ?? 'N/A');
      $y = ($i + 1) * $cellSize + 25;
      $x = ($i + 1) * $cellSize + 10;
      $svg[] = "<text x='10' y='{$y}'>{$name}</text>";
      $svg[] = "<text x='{$x}' y='20' transform='rotate(-45 {$x},20)'>{$name}</text>";
    }

    // Grid
    for ($row = 0; $row < $count; $row++) {
      for ($col = 0; $col < $count; $col++) {
        $x = ($col + 1) * $cellSize;
        $y = ($row + 1) * $cellSize;
        $fill = $row === $col ? '#eee' : 'white';
        $svg[] = "<rect x='{$x}' y='{$y}' width='{$cellSize}' height='{$cellSize}' fill='{$fill}' stroke='black' />";

        if ($row !== $col) {
          $svg[] = "<text x='" . ($x + 30) . "' y='" . ($y + 45) . "' text-anchor='middle'>-</text>";
        }
      }
    }

    $svg[] = "</svg>";
    return response(implode("\n", $svg))->header('Content-Type', 'image/svg+xml');
  }
public function drawPreview(Draw $draw)
{
    $builder = new DrawBuilder($draw);
    $builder->rankPlayers()->assignSeedingCodes();
 $fixtureMap =  $draw->drawFixtures->groupBy('bracket_id');

 $isDrawLocked =  $draw->is_locked;
   if (request()->ajax()) {

        return view('backend.draw.partials.playoff_svg', compact('fixtureMap', 'isDrawLocked'))->render();
    }

     return view('backend.draw.partials.seeded-playoff-draw', [
        'draw' => $draw,
        'fixtureMap' => $draw->drawFixtures->groupBy('bracket_id'),

        'isDrawLocked' => $draw->is_locked,
    ])->render();
}



  public function getBoxMatrix(Draw $draw, $box)
  {

    $boxNumber = (int) $box;
    $registrations = $draw->registrations
      ->filter(fn($r) => $r->pivot->box_number == $boxNumber);

    return view('backend.draw.partials.single-box-matrix', compact('draw', 'boxNumber', 'registrations'))->render();
  }

public function json(Draw $draw)
{
    // Eager-load everything for the draw fixtures
    $draw->load([
        'drawFixtures.registration1.players',
        'drawFixtures.registration2.players',
        // winner_registration is an ID only, so not a relation!
    ]);

    // Build a map of all registration IDs for quick lookup
    $regNames = $draw->registrations
        ->load('players')
        ->mapWithKeys(function ($reg) {
            return [$reg->id => optional($reg->players->first())->full_name ?? ''];
        })
        ->all();

    $regNames[0] = 'Bye'; // Add 'Bye' as registration_id 0

    $fixtureMap = $draw->drawFixtures->mapWithKeys(function ($f) use ($regNames) {
        // Winner lookup by ID:
        $winnerId = $f->winner_registration;
        $winnerName = $winnerId !== null && isset($regNames[$winnerId])
            ? $regNames[$winnerId]
            : ($winnerId === 0 ? 'Bye' : '');

        return [
            "{$f->bracket_id}-{$f->match_nr}" => [
                'id'         => $f->id,
                'p1'         => $regNames[$f->registration1_id ?? 0] ?? '',
                'p2'         => $regNames[$f->registration2_id ?? 0] ?? '',
                'winner'     => $winnerName,
                'match_nr'   => $f->match_nr,
                'bracket_id' => $f->bracket_id,
            ]
        ];
    })->all();

    return response()->json([
        'fixtureMap'   => $fixtureMap,
        'isDrawLocked' => (bool) $draw->is_locked,
    ]);
}

  public function storeVenues(Request $request, Draw $draw)
  {
    $venueIds = $request->input('venue_id', []);
    $numCourts = $request->input('num_courts', []);

    // Validation
    $validated = $request->validate([
      'venue_id' => 'required|array',
      'venue_id.*' => 'required|exists:venues,id',
      'num_courts' => 'required|array',
      'num_courts.*' => 'required|integer|min:1',
    ]);

    // Build sync data for pivot
    $syncData = [];
    foreach ($venueIds as $i => $id) {
      if ($id) {
        $syncData[$id] = ['num_courts' => $numCourts[$i] ?? 1];
      }
    }

    // Save
    $draw->venues()->sync($syncData);

    // Return JSON for AJAX
    if ($request->ajax()) {
      $venues = $draw->venues()->withPivot('num_courts')->get();
      return response()->json([
        'success' => true,
        'message' => 'Venues updated successfully.',
        'venues' => $venues,
      ]);
    }

    // Fallback for non-AJAX form submit
    return redirect()->back()->with('success', 'Venues updated successfully.');
  }


  public function editVenues(Draw $draw)
  {
    $draw->load(['venues' => fn($q) => $q->withPivot('num_courts')]);
    $allVenues = Venue::all(['id', 'name']);

    return response()->json([
      'venues' => $draw->venues->map(fn($v) => [
        'id' => $v->id,
        'num_courts' => $v->pivot->num_courts,
      ]),
      'allVenues' => $allVenues,
      'storeUrl' => route('backend.draw.venues.store', $draw->id),
    ]);
  }
  public function getVenues(Draw $draw)
  {
    $venues = $draw->venues()->get()->map(function ($venue) {
      return [
        'id' => $venue->id,
        'name' => $venue->name,
        'num_courts' => $venue->pivot->num_courts ?? 1,
      ];
    });

    return response()->json($venues);
  }
  public function createDraw(Event $event, Request $request)
  {
    $draw = new Draw();
    $draw->event_id = $event->id;
    $draw->drawName = $request->input('drawName', 'New Draw');
    $draw->draw_type_id = $request->input('draw_type_id'); // optional field
    $draw->save();

    return response()->json([
      'success' => true,
      'draw' => $draw
    ]);
  }
  public function saveGroups(Request $request, Draw $draw)
  {
    foreach ($request->groups as $g) {

      $groupId = $g['group_id'];
      $regs = $g['registrations'];

      $draw->groups()
        ->where('id', $groupId)
        ->first()
        ->registrations()
        ->sync($regs);
    }

    return response()->json(['status' => 'ok']);
  }



}
