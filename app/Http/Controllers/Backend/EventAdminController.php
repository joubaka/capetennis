<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Draw;

use App\Models\DrawFormats;
use App\Models\Event;
use App\Models\Player;
use App\Models\RegistrationOrderItems;
use App\Models\Team;
use App\Models\TeamFixture;
use App\Models\TeamRegion;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\FixtureService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EventPlayersExport;
use Illuminate\Support\Facades\Log;
use App\Models\Registration;
use App\Models\PlayerRegistration;
use App\Models\CategoryEventRegistration;

class EventAdminController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index()
  {
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
    //
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function show($id)
  {
    $event = Event::with([
      'event_admins',

      // Regions → Teams → Players
      'regions.teams.players',
      'regions.teams.team_players_no_profile',

      // Transactions
      'transactions.order.items.player',
      'transactions.user',
    ])->findOrFail($id);


    $userid = Auth::id();
    $administrator = $event->event_admins->contains('user_id', $userid);

    $players = Player::all();
    $regions = TeamRegion::all();
    $categories = Category::all();
    $drawFormats = DrawFormats::all();

    $capeTennisFeeSet = 15;
    $runningBalance = 0;

    // ===================== 💰 TRANSACTIONS =====================
    $transactions = $event->transactions->map(function ($transaction) use (&$runningBalance, $capeTennisFeeSet) {

      $gross = $transaction->amount_gross ?? 0;
      $itemCount = $transaction->order?->items?->count() ?? 1;

      $payfastFee = $transaction->transaction_type === 'Withdrawal'
        ? abs($transaction->amount_fee ?? 0)
        : ($transaction->amount_fee ?? 0);

      $capeFee = ($transaction->transaction_type === 'Withdrawal' ? 1 : -1)
        * ($capeTennisFeeSet * $itemCount);

      $nett = $gross + $payfastFee + $capeFee;
      $runningBalance += $nett;

      $items = [];

      if ($transaction->transaction_type === 'Withdrawal') {
        $items[] = [
          'name' => optional($transaction->player)->name . ' ' . optional($transaction->player)->surname,
          'category' => optional(optional($transaction->category_event)->category)->name,
          'price' => $transaction->item_price ?? abs($gross),
        ];
      } elseif ($transaction->order?->items) {
        foreach ($transaction->order->items as $item) {
          $items[] = [
            'name' => $item->player->name . ' ' . $item->player->surname,
            'category' => optional(optional($item->category_event)->category)->name,
            'price' => $item->item_price ?? abs($gross),
          ];
        }
      } else {
        $items[] = [
          'name' => $transaction->custom_str2,
          'category' => optional(optional($transaction->category_event)->category)->name,
          'price' => abs($gross),
        ];
      }

      $transaction->calculated_gross = $gross;
      $transaction->calculated_payfast_fee = $payfastFee;
      $transaction->calculated_cape_fee = $capeFee;
      $transaction->calculated_nett = $nett;
      $transaction->calculated_balance = $runningBalance;
      $transaction->item_details = $items;

      return $transaction;
    });

    // ===================== 💾 EVENT 198 LOOKUP =====================
    $playerInfo = [];
    $refEvent = Event::with('regions.teams.players')->find(198);

    if ($refEvent) {
      foreach ($refEvent->regions as $region) {
        foreach ($region->teams as $team) {
          foreach ($team->players as $index => $player) {
            $playerInfo[$player->id] = [
              'region' => $region->short_name ?? $region->name,
              'rank' => $index + 1,
            ];
          }
        }
      }
    }

    // ===================== 🧩 CATEGORIES + DRAW DATA =====================
    $eventCategories = CategoryEvent::with([
      'category',
      'registrations.players',
      'nominations',
      'draws.groups.groupRegistrations.registration.players',
    ])->where('event_id', $event->id)->get();

    foreach ($eventCategories as $cat) {
      $cat->nominations = $cat->nominations
        ->sortBy(fn($n) => $playerInfo[$n->player_id]['rank'] ?? 9999)
        ->values();
    }

    // ===================== 📊 TOTALS =====================
    $grossTotal = $transactions->sum('calculated_gross');
    $payfastFeeTotal = $transactions->sum('calculated_payfast_fee');
    $capeTennisFeeTotal = $transactions->sum('calculated_cape_fee');
    $nettTotal = $transactions->sum('calculated_nett');
    $finalBalance = $transactions->last()?->calculated_balance ?? 0;

    return view('backend.adminPage.show', compact(
      'event',
      'transactions',
      'administrator',
      'eventCategories',
      'players',
      'regions',
      'categories',
      'drawFormats',
      'capeTennisFeeSet',
      'grossTotal',
      'payfastFeeTotal',
      'capeTennisFeeTotal',
      'nettTotal',
      'finalBalance',
      'playerInfo'
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
    //
  }

  public function getEventCategoryData(Request $request)
  {
    $event = Event::find($request->event_id);



    $categoryEvent = CategoryEvent::find($request->categoryEvent);
    $draws = Draw::where('drawName', $request->categoryName)
      ->where('event_id', $request->event_id)

      ->where(function ($query) {
        $query->where('drawType_id', '=', 1)   // First condition inside the orWhere
          ->orWhere('drawType_id', '=', '4'); // Second condition inside the orWhere
      })
      ->get();


    $allfixtures = TeamFixture::whereIn('draw_id', $draws->pluck('id'))->get();


    // Find all teams belonging to regions of the specified event


    // Find orders where the related product's category is 'Electronics' OR price is greater than 100
    $teams = Team::whereHas('regions', function ($query) use ($event) {

      $query->whereHas('events', function ($q) use ($event) {
        $q->where('events.id', $event->id);
      });
    })->get();

    $playerFixtures = [];
    foreach ($teams as $team) {
      foreach ($team->players as $key => $player) {


        // Using filter to find the user based on either column
        $filtered = $allfixtures->filter(function ($item) use ($player) {

          return $item->fixture_players['team1_id'] == $player->id || $item->fixture_players['team2_id'] == $player->id;
        });

        if ($filtered->isNotEmpty()) {
          $collection = new Collection($this->getNumberOfWins($filtered, $player->id));
          $counted = $collection->countBy();

          // Check how many times 'apple' exists
          $wins = $counted->get($player->id, 0); // 0 as the default value if 'apple' does not exist
          $ranking[] = ['name' => $player->name . ' ' . $player->surname, 'points' => $this->convertWinsToScore($key + 1, $wins), 'region' => $team->regions->short_name, 'rank' => ($key + 1)];
          $playerFixtures[$team->name][$key]['fixtures'] = $filtered;
          $playerFixtures[$team->name][$key]['results'] = $this->getResultsTable($filtered, $player->id, $key);
          $playerFixtures[$team->name][$key]['id'] = $player->id;
          $playerFixtures[$team->name][$key]['name'] = $player->name . ' ' . $player->surname;
        } else {
          // $playerFixtures[$playerId][] =   'No products found matching the conditions';
        }

        //$playerFixtures[$playerId] = $filteredOrders;
      }
    }
    $table = [];
    foreach ($playerFixtures as $player) {
    }
    $rankingCollect = new Collection($ranking);
    $rank = $rankingCollect->sortByDesc('points');
    $ranking = $rank->values();
    // Get the first matching user or null

    $html = view('backend.adminPage.admin_show._table.results', compact('playerFixtures', 'ranking'))->render();

    return response()->json(['html' => $html, '$playerFixtures' => $playerFixtures, 'ranking' => $ranking, 'test' => $rank]);
  }

  public function getResultsTable($fixtures, $player_id)
  {

    foreach ($fixtures as $fixture) {
      //return $fixture;
      //return $fixture->team_results;
      $winner = $this->getWinner($fixture);
      if ($player_id == $winner) {
        $res = 1;
      } else {
        $res = 0;
      }
      $results['w/l'][] = $res;
      $results['opponents'][] = $this->getOpponents($fixture, $player_id);


      //$results['region'][] = TeamRegion::find(62);
    }
    return $results;
  }
  public function getNumberOfLosses($fixtures, $player_id)
  {

    foreach ($fixtures as $fixture) {
      //return $fixture->team_results;
      $results[] = $this->getLoser($fixture);
    }
    return $results;
  }

  public function getNumberOfWins($fixtures, $player_id)
  {

    foreach ($fixtures as $fixture) {
      //return $fixture->team_results;
      $results[] = $this->getWinner($fixture);
    }
    return $results;
  }
  public function getWinner($fixture)
  {

    $lastset = $fixture->teamResults->sortBy('set_nr')->last();
    if (isset($lastset)) {
      if ($lastset->team1_score > $lastset->team2_score) {
        return $fixture->fixture_players->team1_id;
      } else {
        return $fixture->fixture_players->team2_id;
        ;
      }
    }
  }
  public function getLoser($fixture)
  {

    $lastset = $fixture->teamResults->sortBy('set_nr')->last();
    if (isset($lastset)) {
      if ($lastset->team1_score > $lastset->team2_score) {
        $data['player'] = Player::find($fixture->fixture_players->team2_id);
        $data['region'] = $fixture->region1;
      } else {
        $data['player'] = Player::find($fixture->fixture_players->team1_id);
        $data['region'] = $fixture->region2;
      }
    }
    return $data;
  }

  function getOpponents($fixture, $player_id)
  {
    $players = $fixture->fixture_players;
    $lastset = $fixture->teamResults->sortBy('set_nr')->last();
    if (isset($lastset)) {

      $data['player'] = Player::find($fixture->fixture_players->team1_id);
      $data['region'] = $fixture->region2;
    }
    if ($players->team1_id == $player_id) {
      $data['player'] = Player::find($fixture->fixture_players->team2_id);
    } else {
      $data['player'] = Player::find($fixture->fixture_players->team1_id);
    }
    $data['score'][] = $fixture->teamResults;
    return $data;
  }

  public function convertWinsToScore($rank, $wins)
  {
    switch ($rank) {
      case '1':
        $score = $this->checkEven($rank, $wins, 100);
        return $score;
        break;
      case '2':
        $score = $this->checkEven($rank, $wins, 50);
        return $score;
        break;
      case '3':
        $score = $this->checkEven($rank, $wins, 35);
        return $score;
        break;
      case '4':
        $score = $this->checkEven($rank, $wins, 35);
        return $score;
        break;
      case '5':
        $score = $this->checkEven($rank, $wins, 12);
        return $score;
        break;
      case '6':
        $score = $this->checkEven($rank, $wins, 12);
        return $score;
        break;
      case '7':
        $score = $this->checkEven($rank, $wins, 2);
        return $score;
        break;
      case '8':
        $score = $this->checkEven($rank, $wins, 2);
        return $score;
        break;

      default:
        $score = $this->checkEven($rank, $wins, 0);
        return $score;
        break;
    }
  }
  public function checkEven($rank, $wins, $multiply)
  {
    if ($rank % 2 == 0) {

      $score = $wins * $multiply;
    } else {
      $score = ($wins + 1) * $multiply;
    }
    return $score;
  }

  public function main($id)
  {
    $event = Event::findOrFail($id);

    $draws = Draw::where('event_id', $id)
      ->withCount('registrations')
      ->get();

    return view('backend.eventAdmin.main', compact('event', 'draws'));
  }


  public function entries($id)
  {

    $event = Event::with('eventCategories.registrations.players')->findOrFail($id);
    return view('backend.adminPage.partials.entries', compact('event'));
  }


  public function draws($id)
  {
    dd($id);
    $event = Event::findOrFail($id);

    $draws = Draw::where('event_id', $id)
      ->withCount('registrations')
      ->get();

    return view('backend.adminPage.partials.draws', compact('event', 'draws'));
  }

  //new stuff here

  public function generateFixtures(Request $request, Event $event, FixtureService $fixtureService)
  {
   
    $mode = $request->string('mode', 'perType')->toString();
    $onlyCategories = $request->input('onlyCategories'); // array|null

    // Build and persist
    $fixtureService->createDrawsAndFixtures($event, $mode, $onlyCategories);

    // Debug data
    $dump = $fixtureService->dumpFixturesByDraw($event, $mode, $onlyCategories);
    $categories = $fixtureService->detectCategoriesFromTeams($event);

    // Extract $regions (age+gender) from the service
    $event->loadMissing(['regions.teams.players']);
    $regions = [];
    foreach ($event->regions as $teamRegion) {
      $regionName = $teamRegion->region_name ?? "Region {$teamRegion->id}";
      foreach ($teamRegion->teams as $team) {
        $teamName = trim($team->name);
        if (preg_match('/^u[\/\s](\d+)\s*(boys|girls)$/i', $teamName, $m)) {
          $age = (int) $m[1];
          $gender = strtolower($m[2]);
          foreach ($team->players as $player) {
            $regions[$regionName][$age][$gender][] = $regionName . ' ' . $player->full_name;
          }
        } else {
          $regions[$regionName]['unmatched'][] = $teamName;
        }
      }
    }

    return response()->json([
      'categories' => $categories,
      'regions' => $regions,
      'fixtures' => $dump,
    ]);
  }



  public function createSingleDraw(Request $request, Event $event, FixtureService $fixtureService)
  {
    \Log::debug('[createSingleDraw] Incoming request', [
      'event_id' => $event->id,
      'request' => $request->all(),
    ]);

    // 🔹 Validation: one of category_id OR category_ids[] is required
    $validated = $request->validate([
      'draw_type_id' => 'required|integer',
      'drawName' => 'required|string|max:255',
      'category_id' => 'required_without:category_ids|nullable|exists:categories,id',
      'category_ids' => 'required_without:category_id|nullable|array',
      'category_ids.*' => 'integer|exists:categories,id',
    ]);

    // 🔹 Normalize categories into an array
    $categoryIds = [];
    if ($request->filled('category_ids')) {
      $categoryIds = $request->input('category_ids');   // already array
    } elseif ($request->filled('category_id')) {
      $categoryIds = [(int) $request->input('category_id')];
    }

    if (empty($categoryIds)) {
      return response()->json([
        'success' => false,
        'message' => 'No category selected',
      ], 422);
    }

    \Log::debug('[createSingleDraw] Normalized categories', $categoryIds);

    // 🔹 Call service (fixture service should accept array now)
    $draw = $fixtureService->createSingleDrawAndFixtures(
      $event,
      $categoryIds,
      (int) $validated['draw_type_id'],
      $validated['drawName']
    );

    \Log::debug('[createSingleDraw] Draw created', [
      'draw_id' => $draw->id ?? null,
      'categories' => $categoryIds,
      'type' => $validated['draw_type_id'],
      'fixtures' => $draw->fixtures->count() ?? 0,
    ]);

    return response()->json([
      'success' => true,
      'message' => 'Draw created successfully',
      'draw' => $draw->loadCount('fixtures'),
    ]);
  }

  public function createIndividualDraw(Request $request, Event $event, FixtureService $fixtureService)
  {
    \Log::debug('[createIndividualDraw] Incoming request', [
      'event_id' => $event->id,
      'request' => $request->all(),
    ]);

    // Only requirement for individual
    $validated = $request->validate([
      'drawName' => 'required|string|max:255',
    ]);

    // DrawType_id is ALWAYS 1 for individual (Singles)
    $drawTypeId = 1;

    // No category attached for individuals
    $categoryId = null;

    // Create the draw
    $draw = Draw::create([
      'event_id' => $event->id,
      'drawName' => $validated['drawName'],
      'drawType_id' => $drawTypeId,
      'category_id' => $categoryId,
      'rounds' => 0,
    ]);

    \Log::debug('[createIndividualDraw] Draw created', [
      'draw_id' => $draw->id,
      'event_id' => $event->id,
    ]);

    return response()->json([
      'success' => true,
      'message' => 'Draw created successfully',
      'draw' => $draw,
    ]);
  }

  public function exportAllPlayersPdf($eventId)
  {
    $event = \App\Models\Event::with([
      'regions.teams.players' => function ($q) {
        $q->withPivot('rank', 'pay_status')->orderBy('team_players.rank');
      },
      'regions.teams.team_players_no_profile'
    ])->findOrFail($eventId);

    $pdf = Pdf::loadView('backend.event.exports.all-players-pdf', compact('event'))
      ->setPaper('A4', 'portrait');

    return $pdf->stream(Str::slug($event->name) . '_players.pdf');
  }

  public function exportPlayersExcel($eventId)
  {
    $event = Event::with([
      'regions.teams.players',
      'regions.teams.team_players_no_profile'
    ])->findOrFail($eventId);

    return Excel::download(new EventPlayersExport($event), "event_players_{$event->id}.xlsx");

  }
  public function importTeamCategoryEvents(Event $event)
  {
    Log::info("🎾 Importing team categories for event {$event->id}");

    // Ensure event->regions, teams, and players are loaded
    $event->load([
      'regions.teams.players'
    ]);

    foreach ($event->regions as $region) {

      Log::info("📍 Region {$region->id}: {$region->name}");

      foreach ($region->teams as $team) {

        $teamName = trim($team->name ?? '');

        Log::info("➡️ Processing Team {$team->id} ({$teamName})");

        if ($teamName === '') {
          Log::warning("🚫 Skipping Team {$team->id} — Missing name");
          continue;
        }

        // 1. Create or find category
        $category = Category::firstOrCreate([
          'name' => $teamName
        ]);

        // 2. Create or find CategoryEvent
        $categoryEvent = CategoryEvent::firstOrCreate([
          'event_id' => $event->id,
          'category_id' => $category->id,
        ]);

        Log::info("📁 CategoryEvent ready", [
          'category_event_id' => $categoryEvent->id,
          'category_name' => $category->name
        ]);

        // ================================
        // ✔ 3–5. Create ONE registration per player
        // ================================
        foreach ($team->players as $player) {

          // 3. Create an individual registration
          $registration = Registration::create([]);

          Log::info("🧾 Registration created", [
            'registration_id' => $registration->id,
            'player' => $player->full_name
          ]);

          // 4. Attach this single player
          PlayerRegistration::create([
            'registration_id' => $registration->id,
            'player_id' => $player->id,
          ]);

          // 5. Attach registration to the category event
          CategoryEventRegistration::create([
            'category_event_id' => $categoryEvent->id,
            'registration_id' => $registration->id,
          ]);

          Log::info("🔗 Player linked to CategoryEvent", [
            'player' => $player->full_name,
            'registration_id' => $registration->id,
            'category_event_id' => $categoryEvent->id
          ]);
        }

      }
    }

    return response()->json([
      'status' => 'ok',
      'message' => 'Teams successfully imported into Category Events.',
    ]);
  }
  public function overview(Event $event)
  {
    // =====================
    // Base relations (always)
    // =====================
    $event->load([
      'event_admins',
    ]);

    $administrator = $event->event_admins
      ->contains('user_id', auth()->id());

    // =====================
    // BASE STATS (shared)
    // =====================
    $stats = [
      'categories' => $event->categories()->count(),
      'matchesPlayed' => $event->fixtures()->whereNotNull('winner_registration')->count(),
      'matchesTotal' => $event->fixtures()->count(),
      'drawsLocked' => $event->draws()->where('locked', 1)->count(),
    ];

    // =====================
    // EVENT-TYPE AWARE ENTRIES
    // =====================
    if ($event->isTeam()) {

      $event->load([
        'regions.teams.teamPlayers.player',
        'regions.teams.team_players_no_profile',
      ]);

      $teams = $event->regions
        ->flatMap(fn($r) => $r->teams);

      $stats['entries'] = $teams->count(); // ✅ TEAMS
      $stats['players'] = $teams->sum(function ($team) {
        return
          $team->teamPlayers->count() +
          $team->team_players_no_profile->count();
      });

    } else {

      // Individual event
      $stats['entries'] = $event->registrations()->count(); // ✅ PLAYERS
    }

    // =====================
    // TEAM EVENT EXTRA DATA
    // =====================
    $teamData = [];

    if ($event->isTeam()) {

      $event->load([
        'transactions.order.items.player',
        'transactions.user',
      ]);

      [$transactions, $totals] = $this->buildEventTransactions($event);
      $eventCategories = $this->loadEventCategories($event);

      $teamData = compact(
        'transactions',
        'totals',
        'eventCategories',
        'administrator'
      );
    }

    return view('backend.event.overview', [
      'event' => $event,
      'stats' => $stats,
      'teamData' => $teamData,
    ]);
  }

  protected function buildEventTransactions(Event $event): array
  {
    $capeTennisFeeSet = 15;
    $runningBalance = 0;

    $transactions = $event->transactions->map(function ($transaction) use (&$runningBalance, $capeTennisFeeSet) {

      $gross = $transaction->amount_gross ?? 0;
      $itemCount = $transaction->order?->items?->count() ?? 1;

      $payfastFee = $transaction->transaction_type === 'Withdrawal'
        ? abs($transaction->amount_fee ?? 0)
        : ($transaction->amount_fee ?? 0);

      $capeFee = ($transaction->transaction_type === 'Withdrawal' ? 1 : -1)
        * ($capeTennisFeeSet * $itemCount);

      $nett = $gross + $payfastFee + $capeFee;
      $runningBalance += $nett;

      $transaction->calculated_gross = $gross;
      $transaction->calculated_payfast_fee = $payfastFee;
      $transaction->calculated_cape_fee = $capeFee;
      $transaction->calculated_nett = $nett;
      $transaction->calculated_balance = $runningBalance;

      return $transaction;
    });

    return [
      $transactions,
      [
        'gross' => $transactions->sum('calculated_gross'),
        'payfast' => $transactions->sum('calculated_payfast_fee'),
        'cape' => $transactions->sum('calculated_cape_fee'),
        'nett' => $transactions->sum('calculated_nett'),
        'balance' => $transactions->last()?->calculated_balance ?? 0,
      ]
    ];
  }
  protected function loadEventCategories(Event $event)
  {
    $categories = CategoryEvent::with([
      'category',
      'registrations.players',
      'nominations',
      'draws.groups.groupRegistrations.registration.players',
    ])->where('event_id', $event->id)->get();

    $playerInfo = $this->loadLegacyEventRanks();

    foreach ($categories as $cat) {
      $cat->nominations = $cat->nominations
        ->sortBy(fn($n) => $playerInfo[$n->player_id]['rank'] ?? 9999)
        ->values();
    }

    return $categories;
  }
  protected function loadLegacyEventRanks(): array
  {
    $playerInfo = [];

    $refEvent = Event::with('regions.teams.players')->find(198);
    if (!$refEvent) {
      return [];
    }

    foreach ($refEvent->regions as $region) {
      foreach ($region->teams as $team) {
        foreach ($team->players as $index => $player) {
          $playerInfo[$player->id] = [
            'region' => $region->short_name ?? $region->name,
            'rank' => $index + 1,
          ];
        }
      }
    }

    return $playerInfo;
  }

  public function entries_new(Event $event)
  {
    $categoryEvents = $event->eventCategories()
      ->with([
        'category',
        'categoryEventRegistrations.registration.players',
      ])
      ->get();

    return view('backend.event.entries', compact('event', 'categoryEvents'));
  }


  public function lockCategory(CategoryEvent $categoryEvent)
  {
    $categoryEvent->update(['locked_at' => now()]);

    return response()->json([
      'success' => true,
      'locked' => true,
    ]);
  }

  public function unlockCategory(CategoryEvent $categoryEvent)
  {
    $categoryEvent->update(['locked_at' => null]);

    return response()->json([
      'success' => true,
      'locked' => false,
    ]);
  }


  public function addPlayerToCategory(
    Request $request,
    CategoryEvent $categoryEvent
  ) {
    abort_if($categoryEvent->isLocked(), 403);

    $request->validate([
      'registration_id' => 'required|exists:registrations,id',
    ]);

    $exists = $categoryEvent->categoryEventRegistrations()
      ->where('registration_id', $request->registration_id)
      ->exists();

    if ($exists) {
      return response()->json([
        'success' => false,
        'message' => 'Player already in category',
      ], 422);
    }

    $entry = $categoryEvent->categoryEventRegistrations()
      ->with('registration.players')
      ->create([
        'registration_id' => $request->registration_id,
        'status' => 'active',
      ]);

    return response()->json([
      'success' => true,
      'count' => $categoryEvent->categoryEventRegistrations()->count(),
      'row' => view(
        'backend.event.partials.entry-row',
        ['reg' => $entry]
      )->render(),
    ]);

  }




  public function removePlayerFromCategory(
    CategoryEvent $categoryEvent,
    Registration $registration,
    Request $request
  ) {
    abort_if($categoryEvent->isLocked(), 403);

    $categoryEvent->categoryEventRegistrations()
      ->where('registration_id', $registration->id)
      ->limit(1)
      ->delete();

    return response()->json([
      'success' => true,
      'count' => $categoryEvent->categoryEventRegistrations()->count(),
    ]);

  }
  public function settings(Event $event)
  {
    $event->load([
      'categoryEvents.category',
      'venues',
    ]);

    return view('backend.event.settings', compact('event'));
  }


}
