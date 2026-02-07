<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\ClothingItemType;
use App\Models\Draw;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventType;
use App\Models\SellProduct;
use App\Models\TeamFixture;
use App\Models\Fixture;
use App\Models\TeamFixtureResult;
use App\Models\TeamPlayer;
use App\Models\TeamRegion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToArray;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\ClothingOrder;

class EventController extends Controller
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
        dd('create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //return $request;
        $event = new Event();
        $event->name = $request->name;
        $event->start_date = $request->start_date;
        $event->endDate = $request->endDate;
        $event->information = $request->info;
        $event->entryFee = $request->entry_fee;
        $event->logo = $request->logo;
        $event->venues = $request->venues;
        $event->eventType = $request->event_type;
        $event->deadline = $request->deadline;
        if ($request->published == 'on') {
            $event->published = 'published';
        }
        //$event->published = $request->published;
        $event->organizer = $request->organizer;
        $event->email = $request->email;
        if ($request->signUP == 'on') {
            $event->signup = 1;
        }
        //$event->signup = $request->signUP;
        //$event->status = $request->published;
        $event->results_published = $request->results_published;

        $event->admin = $request->admins;
        $event->eventGroup = $request->eventGroup;
        $event->name = $request->name;

        $event->save();
        $notification = array(
            'message' => 'Event added succesfully',
            'alert-type' => 'info'
        );
        return $notification;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
  public function show($id)
  {
    // ---------------------------------------------------------
    // LOAD EVENT + RELATIONSHIPS
    // ---------------------------------------------------------
    $event = Event::with([
      'regions',
      'announcements',
      'files',
      'eventCategories.category',
      'eventCategories.nominations.player',
      'eventCategories.registrations.players',
      'draws.draw_types',
      'draws.venues',
      'series',
    ])->findOrFail($id);

    $regions = $event->regions;
    $user = Auth::user();

    // ---------------------------------------------------------
    // DEADLINE LOGIC
    // ---------------------------------------------------------
    $days = $event->deadline - 1;
    $dLineToClose = Carbon::parse($event->start_date)->subDays($days);

    $signUp = (now()->lt($dLineToClose) && $event->signUp == 1)
      ? 'open'
      : 'closed';

    $sDate = $this->convertDate(Carbon::parse($event->start_date));
    $eDate = $this->convertDate(Carbon::parse($event->end_date));
    $formatEntryLine = $this->convertDate($dLineToClose->copy()->subDay());
    $formatWithdrawalLine = $this->convertDate($dLineToClose->copy()->subDay());

    // ---------------------------------------------------------
    // STATIC TABLES (CACHED)
    // ---------------------------------------------------------
    $eventTypes = cache()->remember('event_types', 3600, fn() => EventType::all());
    $users = cache()->remember('users_all', 3600, fn() => User::all());
    $categories = cache()->remember('categories_all', 3600, fn() => Category::all());
    $clothingItems = cache()->remember('clothing_items', 3600, fn() => ClothingItemType::all());

    // Clothing (example region – replace with config later)
    $clothings = ClothingItemType::where('region_id', 52)
      ->orderBy('ordering')
      ->get();

    // ---------------------------------------------------------
    // EVENT CATEGORIES
    // ---------------------------------------------------------
    $eventCats = $event->eventCategories
      ->sortBy(fn($ec) => $ec->category->name)
      ->values();

    // ---------------------------------------------------------
    // ADMINS
    // ---------------------------------------------------------
    $administrators = EventAdmin::where('event_id', $event->id)->get();

    // ---------------------------------------------------------
    // USER REGISTRATIONS
    // ---------------------------------------------------------
    $userRegistrations = Auth::check()
      ? CategoryEventRegistration::with('registration.players', 'categoryEvent.category')
        ->where('user_id', Auth::id())
        ->whereHas('categoryEvent', fn($q) => $q->where('event_id', $event->id))
        ->get()
      : collect();

    // ---------------------------------------------------------
    // PRODUCTS
    // ---------------------------------------------------------
    $products = SellProduct::where('event_id', $event->id)->get();

    // ---------------------------------------------------------
    // TEAM REGISTRATIONS (PAID)
    // ---------------------------------------------------------
    $teamRegs = TeamPlayer::where('pay_status', 1)->get();

    // ---------------------------------------------------------
    // SORT DRAWS
    // ---------------------------------------------------------
    $eventDraws = $event->draws->sort(function ($a, $b) {
      return [
        $b->published <=> $a->published,
        ($a->draw_types->drawTypeName ?? $a->drawType_id)
        <=>
        ($b->draw_types->drawTypeName ?? $b->drawType_id),
        $a->drawName <=> $b->drawName,
      ];
    })->values();

    $drawIds = $eventDraws->pluck('id');

    // ---------------------------------------------------------
    // FIXTURES PER VENUE
    // ---------------------------------------------------------
    if ($event->eventType == 3) {
      $fixturesPerVenue = TeamFixture::with(['team1', 'team2', 'venue'])
        ->whereIn('draw_id', $drawIds)
        ->orderBy('scheduled_at')
        ->get();
    } elseif ($event->eventType == 13) {
      $fixturesPerVenue = Fixture::with([
        'registration1.players',
        'registration2.players',
        'venue',
        'orderOfPlay',
        'orderOfPlay.venue',
      ])
        ->whereIn('draw_id', $drawIds)
        ->orderBy('match_nr')
        ->orderBy('id')
        ->get();
    } else {
      $fixturesPerVenue = collect();
    }

    $fixturesPerVenueGrouped = $fixturesPerVenue
      ->groupBy(fn($fx) => optional(optional($fx->orderOfPlay)->venue)->name ?? 'Unassigned');

    // ---------------------------------------------------------
    // TEAM FIXTURES
    // ---------------------------------------------------------
    $teamFixtures = TeamFixture::whereIn('draw_id', $drawIds)->get();
    $ties = $teamFixtures->groupBy('tie_nr');
    $rounds = $teamFixtures->groupBy('round_nr');

    // ---------------------------------------------------------
    // USER CLOTHING ORDERS
    // ---------------------------------------------------------
    $myClothingOrders = collect();

    if (Auth::check()) {
      $regionIds = $regions->pluck('id');

      $myClothingOrders = ClothingOrder::with([
        'items.itemType',
        'items.size',
        'team.regions',
      ])
        ->where('user_id', Auth::id())
        ->whereHas('team', fn($q) => $q->whereIn('region_id', $regionIds))
        ->latest()
        ->get();
    }

    // ---------------------------------------------------------
    // NOMINATION LOOKUP (FAST)
    // ---------------------------------------------------------
    $nomRegisteredLookup = $event->eventCategories
      ->flatMap(
        fn($cat) =>
        $cat->registrations->flatMap(
          fn($reg) =>
          $reg->players->map(fn($p) => $p->id . '-' . $cat->id)
        )
      )
      ->flip()
      ->all();

    // ---------------------------------------------------------
    // VIEW
    // ---------------------------------------------------------
    return view('frontend.event.show', compact(
      'fixturesPerVenueGrouped',
      'regions',
      'rounds',
      'ties',
      'eventDraws',
      'clothings',
      'clothingItems',
      'products',
      'administrators',
      'userRegistrations',
      'eventCats',
      'categories',
      'teamRegs',
      'event',
      'user',
      'signUp',
      'eventTypes',
      'users',
      'eDate',
      'sDate',
      'formatEntryLine',
      'formatWithdrawalLine',
      'myClothingOrders',
      'nomRegisteredLookup'
    ));
  }


  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function edit($id)
  {
    $event = Event::with([
      'regions',
      'admins',
      'eventCategories.category',
      'draws.draw_types',
      'draws.venues',
    ])->findOrFail($id);

    // Optional safety: prevent editing events not assigned to a series
    // (enable if you want strict series control)
    /*
    if (request()->has('series_id')) {
      abort_unless((int)$event->series_id === (int)request('series_id'), 403);
    }
    */

    // Static lookup tables (same pattern as show)
    $eventTypes = cache()->remember('event_types', 3600, fn() => EventType::all());
    $users = cache()->remember('users_all', 3600, fn() => User::all());
    $categories = cache()->remember('categories_all', 3600, fn() => Category::all());

    return view('frontend.event.edit', compact(
      'event',
      'eventTypes',
      'users',
      'categories'
    ));
  }



  public function update(Request $request, $id)
  {
    $event = Event::findOrFail($id);

    // Accept serialized "data=" or normal form data
    $payload = $request->has('data')
      ? tap([], fn(&$arr) => parse_str($request->input('data', ''), $arr))
      : $request->all();

    // Validate (trim to what you need)
    $validator = Validator::make($payload, [
      'name' => 'required|string|max:255',
      'start_date' => 'nullable|date',
      'endDate' => 'nullable|date|after_or_equal:start_date',
      'deadline' => 'nullable|string',
      'information' => 'nullable|string',
      'organizer' => 'nullable|string|max:255',
      'email' => 'nullable|email',
      'entry_fee' => 'nullable|numeric',
      'logo' => 'nullable|string|max:255',
      'venues' => 'nullable|string',
      'event_type' => 'required|integer',
      'published' => 'nullable',
      'signUP' => 'nullable',
      'admins' => 'array',
      'admins.*' => 'integer|exists:users,id',
      'categories' => 'array',
      'categories.*' => 'integer|exists:categories,id',
    ]);
    if ($validator->fails()) {
      return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
    }

    $get = fn($k, $d = null) => Arr::get($payload, $k, $d);
    $toYmd = function ($v) {
      if (!$v)
        return null;
      try {
        return Carbon::parse($v)->format('Y-m-d'); } catch (\Throwable) {
        return null; } };
    $toBool = fn($v) => in_array((string) $v, ['1', 'true', 'on'], true);

    $adminIds = collect((array) $get('admins', []))
      ->filter(fn($v) => $v !== null && $v !== '')
      ->map(fn($id) => (int) $id)->unique()->values()->all();

    $categoryIds = collect((array) $get('categories', []))
      ->filter(fn($v) => $v !== null && $v !== '')
      ->map(fn($id) => (int) $id)->unique()->values()->all();

    DB::transaction(function () use ($event, $get, $toYmd, $toBool, $adminIds, $categoryIds) {
      // Scalars
      $event->name = $get('name');
      $event->start_date = $toYmd($get('start_date'));
      $event->endDate = $toYmd($get('endDate'));
      $event->deadline = $get('deadline');          // keep TEXT
      $event->information = $get('information');
      $event->organizer = $get('organizer');
      $event->email = $get('email');
      $event->entryFee = $get('entry_fee');
      $event->logo = $get('logo');
      $event->venues = $get('venues');
      $event->eventType = $get('event_type');

      // Toggles
      $event->published = $toBool($get('published')) ? 'published' : 0;
      $event->signUp = $toBool($get('signUP')) ? 1 : 0;

      // Optional: keep first as "primary" admin in scalar column
      $event->admin = $adminIds[0] ?? null;

      $event->save();
     
      // Save ALL admins & categories
     
     
        $event->admins()->sync($adminIds);
      
      if (method_exists($event, 'categories')) {
        $event->categories()->sync($categoryIds);
      }
    });

    return response()->json([
      'status' => 'ok',
      'message' => 'Event updated successfully',
      'id' => $event->id,
      'saved' => ['admins' => $adminIds, 'categories' => $categoryIds],
    ]);
  }

  public function destroy($id)
    {
    }

    public function success($id)
    {
        $event = Event::find($id);
        return view('frontend.event.confirmation', compact('event'));
    }

    public function cancel()
    {

        return view('frontend.event.cancel');
    }

    public function userEventAjax($id)
    {
        if ($id == 584) {
            $e = Event::orderBy('events.start_date', 'desc')

                ->with('registrations')

                ->get();
        } else {

            $e = Event::whereHas('admins', function ($query) use ($id) {
                return $query->where('user_id', '=', $id);
            })
                ->with('registrations')->orderByDesc('start_date')->get();
        }

        //dd($data);
        return ['data' => $e];
    }
    public function convertDate($date)
    {
        $formatDate = $date->format('D d M Y');
        return $formatDate;
    }

    public function showDraw($id){
        $data['draw'] = Draw::find($id);
        return view('frontend.draw.show',$data);
    }

    public function getWinner($fixture)
    {

        $lastset = $fixture->teamResults->sortBy('set_nr')->last();
        if (isset($lastset)) {
            if ($lastset->team1_score > $lastset->team2_score) {
                return $fixture->region1;
            } else {
                return $fixture->region2;
            }
        }
    }
    public function getLoser($fixture)
    {

        $lastset = $fixture->teamResults->sortBy('set_nr')->last();
        if (isset($lastset)) {
            if ($lastset->team1_score > $lastset->team2_score) {
                return $fixture->region2;
            } else {
                return $fixture->region1;
            }
        }
    }

    public static function teamScore($team, $regions)
    {
        foreach ($regions as $region) {
            $scoreboard[$region->id] = $region;
            $scoreboard[$region->id]['score'] = 0;
        }
        //dd($scoreboard);

        $count = 0;
        //under 10

        $fixtures = TeamFixture::whereIn('draw_id', $team->pluck('id'))

            ->get();

        foreach ($fixtures as $fixture) {
            $result = TeamFixtureResult::where('team_fixture_id', $fixture->id)->orderBy('id', 'desc')->first();

            if (isset($result->team1_score) and isset($result->team2_score)) {

                if ($result->team1_score > $result->team2_score) {
                    $winreg = $result->fixtures->region1;
                    $scoreboard[$winreg]['score'] = $scoreboard[$winreg]['score'] + 1;
                    $count++;
                } else {
                    $count++;
                    $winreg = $result->fixtures->region2;
                    $scoreboard[$winreg]['score'] = $scoreboard[$winreg]['score'] + 1;
                    $count++;
                }
            } else {
                $data['notset'][] = $fixture;
            }
        }
        return $scoreboard;
       
    }

    public function getScoreWinner($drawtype, $fixture)
    {
        $results = $fixture->teamResults;
        // dd($results);
        switch ($drawtype) {
            case '1':
                if ($results->count() == 2) {
                    return 3;
                } else {
                    return 2;
                }

                break;

            default:
            $lastresult = $results->last();

            if ($lastresult->team1_score > $lastresult->team2_score) {
                if ($lastresult->team1_score == 7 && $lastresult->team2_score == 6) {
                    return 2;
                } else {
                    return 3;
                }
            } else {
                if ($lastresult->team2_score == 7 && $lastresult->team1_score == 6) {
                    return 2;
                } else {
                    return 3;
                }
            }
                break;
        }
        return 1;
    }
    public function getScoreLoser($drawtype, $fixture)
    {
        $results = $fixture->teamResults;

        switch ($drawtype) {
            case '1':
                if ($results->count() == 2) {
                    return 0;
                } else {
                    return 1;
                }

                break;

            default:
                $lastresult = $results->last();

                if ($lastresult->team1_score > $lastresult->team2_score) {
                    if ($lastresult->team2_score == 6 && $lastresult->team1_score == 7) {
                        return 1;
                    } else {
                        return 0;
                    }
                } else {
                    if ($lastresult->team1_score == 6  && $lastresult->team2_score == 7) {
                        return 1;
                    } else {
                        return 0;
                    }
                }
                return 0;
                break;
        }
        return;
    }
}
