<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\DrawType;

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
use App\Models\EventExpense;
use App\Models\EventIncomeItem;
use App\Models\ExpenseType;
use Illuminate\Support\Facades\Storage;
use App\Models\Registration;
use App\Models\PlayerRegistration;
use App\Models\CategoryEventRegistration;
use App\Models\TeamSelectionInvitation;
use App\Services\EventOperationsService;

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
  private function teamWorkspace(Event $event)
  {
    $event->load(['event_admins', 'regions', 'eventCategories.category']);
    $teamSelectionInvitations = TeamSelectionInvitation::query()
      ->where('event_id', $event->id)
      ->whereHas('selectionImport', fn ($query) => $query->whereIn('status', ['draft', 'sent']))
      ->orderBy('team_id')->orderBy('queue_position')->get()->groupBy('team_id')->toBase();
    $teams = app(\App\Services\EventTeamScope::class)->query($event, $teamSelectionInvitations->keys()->all())
      ->with(['category.category', 'teamPlayers', 'team_players_no_profile'])
      ->orderBy('name')->get()->groupBy('region_id');
    $event->regions->each(function ($region) use ($teams) {
      $region->setRelation('teams', $teams->get($region->id, new Collection));
      // This relation depends on the slot instance; map it explicitly by team/rank.
      $region->teams->each(function ($team) {
        $team->teamPlayers->each(fn ($slot) => $slot->setRelation('noProfile',
          $team->team_players_no_profile->firstWhere('rank', $slot->rank)));
        $slots = $team->teamPlayers->keyBy('rank');
        foreach ($team->team_players_no_profile as $imported) {
          if (!$slots->has($imported->rank)) {
            $slots->put($imported->rank, (new \App\Models\TeamPlayer([
              'team_id' => $team->id, 'rank' => $imported->rank,
              'player_id' => (int) $imported->player_profile, 'pay_status' => $imported->pay_status,
            ]))->setRelation('noProfile', $imported));
          }
        }
        $team->setRelation('workspaceSlots', $slots->sortKeys()->values());
      });
    });

    if (request()->has('roster_region')) {
      $data = request()->validate(['roster_region' => 'required|integer', 'panel' => 'sometimes|in:players,order']);
      $region = $event->regions->firstWhere('id', (int) $data['roster_region']);
      abort_unless($region, 404);
      $region->teams->loadMissing('teamPlayers.player');
      $region->teams->loadMissing('team_players_no_profile.profile');
      $region->teams->each(function ($team) {
        $team->workspaceSlots->filter(fn ($slot) => !$slot->exists)->each(fn ($slot) => $slot->setRelation('player', $slot->noProfile?->profile));
      });
      $invitations = $teamSelectionInvitations->toBase()->only($region->teams->modelKeys())->flatten(1);
      (new Collection($invitations->all()))->load('player');
      $orderLocked = TeamFixture::whereHas('draw', fn ($query) => $query->where('event_id', $event->id))->exists()
        || \App\Models\TeamTie::whereHas('draw', fn ($query) => $query->where('event_id', $event->id))->exists();
      return view('backend.adminPage.admin_show.tabs.'.(($data['panel'] ?? 'players') === 'order' ? 'order-region' : 'players-region'),
        compact('event', 'region', 'teamSelectionInvitations', 'orderLocked'));
    }

    $allCategories = Category::orderBy('name')->get();
    $regions = TeamRegion::orderBy('region_name')->get();
    $players = collect();
    $administrator = $event->event_admins->contains('user_id', Auth::id());
    $data = compact('event', 'teamSelectionInvitations', 'allCategories', 'regions', 'players', 'administrator');
    if (request()->boolean('workspace')) {
      return view('backend.adminPage.admin_show.team-workspace', $data);
    }
    return view('backend.adminPage.show', $data);
  }

  public function show($id)
  {
    $workspaceEvent = Event::findOrFail($id);
    $this->authorize('event-draw.view', $workspaceEvent);
    if ((int) $workspaceEvent->eventType === 3) {
      return $this->teamWorkspace($workspaceEvent);
    }
    $event = Event::with([
      'event_admins',

      // A region can belong to multiple events. Only load teams whose category
      // belongs to this event so counts and bulk actions remain event-scoped.
      'regions.teams' => function ($query) use ($id) {
        $query
          ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('event_id', $id))
          ->with(['players', 'team_players_no_profile', 'category.category']);
      },

      // Transactions
      'transactions.order.items.player',
      'transactions.user',
    ])->findOrFail($id);

    $this->authorize('event-draw.view', $event);

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
            'name' => optional($item->player)->name . ' ' . optional($item->player)->surname,
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


    $allCategories = \App\Models\Category::orderBy('name')->get();

    // Ranking-managed team rosters are projected into team_players, while the
    // invitation ledger remains authoritative for selected/reserve state.
    $teamSelectionInvitations = TeamSelectionInvitation::query()
      ->with(['player', 'selectionImport'])
      ->where('event_id', $event->id)
      ->whereHas('selectionImport', fn ($query) => $query->whereIn('status', ['draft', 'sent']))
      ->orderBy('team_id')
      ->orderBy('queue_position')
      ->get()
      ->groupBy('team_id');

    // A region is shared by many historical events. Once explicit event-team
    // links exist, do not leak those other teams into this event workspace.
    $explicitTeamIds = Team::query()->withoutGlobalScopes()
      ->whereHas('category', fn ($query) => $query->where('event_id', $event->id))
      ->pluck('id')
      ->merge($teamSelectionInvitations->keys())
      ->map(fn ($teamId) => (int) $teamId)
      ->unique();
    if ($explicitTeamIds->isNotEmpty()) {
      $explicitTeamsByRegion = Team::query()->withoutGlobalScopes()
        ->whereIn('id', $explicitTeamIds)
        ->with(['players', 'team_players_no_profile', 'category.category'])
        ->get()
        ->groupBy(fn (Team $team) => (int) $team->region_id);

      $event->regions->each(function (TeamRegion $region) use ($explicitTeamsByRegion): void {
        $region->setRelation('teams', $explicitTeamsByRegion->get((int) $region->id, collect())->values());
      });
    }


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
      'playerInfo',
      'allCategories',
      'teamSelectionInvitations',
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
    if ($request->has('result_group')) {
      // Form submissions convert an empty optional exclusion list to null.
      if ($request->exists('excluded_result_region_ids') && $request->input('excluded_result_region_ids') === null) {
        $request->merge(['excluded_result_region_ids' => []]);
      }
      foreach (['regions', 'formats', 'excluded_result_region_ids'] as $field) {
        if (is_string($request->input($field))) $request->merge([$field => $request->input($field) === '' ? [] : explode(',', $request->input($field))]);
      }
      $data = $request->validate([
        'event_id' => 'required|integer|exists:events,id', 'result_group' => 'required|string|max:30',
        'regions' => 'present|array', 'regions.*' => 'integer|distinct',
        'formats' => 'present|array', 'formats.*' => 'string|distinct|in:singles,reverse_singles',
        'excluded_result_region_ids' => 'sometimes|array|max:100', 'excluded_result_region_ids.*' => 'integer|distinct',
      ]);
      $event = Event::findOrFail($data['event_id']);
      $this->authorize('event.manage', $event);
      $service = app(\App\Services\TeamResultRankingService::class);
      $setup = $service->setup($event);
      abort_unless($setup['groups']->contains('key', $data['result_group']), 404);
      $regions = array_map('intval', $data['regions']);
      $excludedResultRegions = array_map('intval', $data['excluded_result_region_ids'] ?? []);
      if (array_diff($regions, $setup['regions']->pluck('id')->all()) || array_diff($excludedResultRegions, $setup['regions']->pluck('id')->all()) || array_diff($data['formats'], $setup['formats']->all())) {
        throw \Illuminate\Validation\ValidationException::withMessages(['setup' => 'Choose regions and formats from this event.']);
      }
      $ranking = $service->ranking($event, $data['result_group'], $regions, $data['formats'], $excludedResultRegions, includeRatings: true);
      return response()->json(['html' => view('backend.adminPage.admin_show._table.result-selection', compact('ranking'))->render(), 'ranking' => $ranking])->header('Cache-Control', 'no-store, private');
    }
    $data = $request->validate(['event_id' => 'required|integer|exists:events,id', 'categoryEvent' => 'required|integer|exists:category_events,id']);
    $event = Event::findOrFail($data['event_id']);
    $this->authorize('event-draw.view', $event);

    $categoryEvent = CategoryEvent::with('category')->where('event_id', $event->id)->findOrFail($data['categoryEvent']);
    $draws = Draw::where('event_id', $event->id)
      ->where(function ($query) use ($categoryEvent) {
        $query->where('category_event_id', $categoryEvent->id)
          ->orWhere(function ($legacy) use ($categoryEvent) {
            $legacy->whereNull('category_event_id')->where('drawName', $categoryEvent->category?->name);
          });
      })->pluck('id');
    $resultService = app(\App\Services\TeamRubberResultService::class);
    $allfixtures = TeamFixture::whereIn('draw_id', $draws)
      ->with(['fixturePlayers.player1', 'fixturePlayers.player2', 'teamResults', 'draw'])
      ->get()->filter(function ($fixture) use ($resultService) {
        if (! $fixture->isSingles() || $fixture->fixturePlayers->count() !== 1) return false;
        $players = $fixture->fixturePlayers->first();
        if (! $players->player1 || ! $players->player2) return false;
        $outcome = $resultService->outcome($fixture);
        return $outcome['complete'] && $outcome['winner'];
      });
    $teams = Team::where('category_event_id', $categoryEvent->id)->with(['players', 'regions'])->get();
    $playerFixtures = [];
    $ranking = [];
    foreach ($teams as $team) {
      foreach ($team->players as $player) {
        $rank = (int) $player->pivot->rank;
        $filtered = $allfixtures->filter(function ($fixture) use ($player) {
          $slot = $fixture->fixturePlayers->first();
          return (int) $slot->team1_id === (int) $player->id || (int) $slot->team2_id === (int) $player->id;
        });
        if ($filtered->isEmpty() || $rank < 1) continue;
        $wins = $filtered->filter(fn ($fixture) => (int) $this->getWinner($fixture) === (int) $player->id)->count();
        $ranking[] = [
          'name' => $player->name . ' ' . $player->surname,
          'points' => $this->convertWinsToScore($rank, $wins),
          'region' => $team->regions?->short_name ?? '', 'rank' => $rank,
        ];
        $playerFixtures[$team->name][$rank - 1] = [
          'fixtures' => $filtered, 'results' => $this->getResultsTable($filtered, $player->id),
          'id' => $player->id, 'name' => $player->name . ' ' . $player->surname,
        ];
      }
    }
    $ranking = collect($ranking)->sort(fn ($a, $b) => ((int) ceil($a['rank'] / 2) <=> (int) ceil($b['rank'] / 2))
      ?: ($b['points'] <=> $a['points']))->values();
    $html = view('backend.adminPage.admin_show._table.results', compact('playerFixtures', 'ranking'))->render();

    return response()->json(['html' => $playerFixtures ? $html : '<div class="alert alert-light border" role="status">No results recorded for this category.</div>', 'ranking' => $ranking]);
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
    $outcome = app(\App\Services\TeamRubberResultService::class)->outcome($fixture);
    if (! $fixture->isSingles() || ! $outcome['complete'] || ! $outcome['winner']) return null;
    $players = $fixture->fixturePlayers->first();
    return $outcome['winner'] === 'home' ? $players?->team1_id : $players?->team2_id;
  }

  public function getLoser($fixture)
  {
    $winner = $this->getWinner($fixture);
    if (! $winner) return null;
    return $this->getOpponents($fixture, $winner);
  }

  public function getOpponents($fixture, $player_id)
  {
    $players = $fixture->fixturePlayers->first();
    $home = (int) $players->team1_id === (int) $player_id;
    return [
      'player' => $home ? $players->player2 : $players->player1,
      'region' => $home ? $fixture->region2 : $fixture->region1,
      'score' => [$fixture->teamResults->sortBy('set_nr')],
    ];
  }

  public function convertWinsToScore($rank, $wins)
  {
    return app(\App\Services\TeamResultRankingService::class)->points((int) $rank, (int) $wins);
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
    $this->authorize('event-draw.view', $event);

    // The legacy AJAX shell would insert the full workspace inside another
    // backend layout, duplicating navigation IDs and fixed-menu positioning.
    return redirect()->route('headOffice.show', $event);
  }


  public function entries($id)
  {
    $event = Event::with('eventCategories.registrations.players')->findOrFail($id);
    $this->authorize('event-draw.view', $event);

    return view('backend.adminPage.partials.entries', compact('event'));
  }


  public function draws($id)
  {
    $event = Event::findOrFail($id);
    $this->authorize('event-draw.view', $event);

    return redirect()->route('headOffice.show', $event);
  }

  //new stuff here

  public function generateFixtures(Request $request, Event $event, FixtureService $fixtureService)
  {
    $this->authorize('draw.create', $event);

    abort_if($event->isInterprovincialTrials(), 422, 'Create Trials fixtures through the individual draw setup.');

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
    $this->authorize('draw.create', $event);

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
    $this->authorize('individual-draw.create', $event);

    \Log::debug('[createIndividualDraw] Incoming request', [
      'event_id' => $event->id,
      'request' => $request->all(),
    ]);

    // Only requirement for individual
    $validated = $request->validate([
      'drawName' => 'required|string|max:255',
      'draw_type_id' => [
        'nullable',
        'integer',
        \Illuminate\Validation\Rule::exists('draw_types', 'id')->where('type', 'individual'),
      ],
      'category_selection_mode' => ['nullable', 'in:automatic,manual'],
      'category_event_id' => [
        'required_with:category_selection_mode',
        'nullable',
        'integer',
        \Illuminate\Validation\Rule::exists('category_events', 'id')->where('event_id', $event->id),
      ],
    ]);

    if (($validated['category_selection_mode'] ?? null) === 'automatic') {
      $categories = CategoryEvent::query()->where('event_id', $event->id)
        ->join('categories', 'categories.id', '=', 'category_events.category_id')
        ->get(['category_events.id as pivot_id', 'categories.name']);
      $standardIds = array_map(fn ($choice) => $choice->pivot_id, \App\Support\IndividualDrawCategoryChoices::make($categories));
      if (! in_array((int) ($validated['category_event_id'] ?? 0), $standardIds, true)) {
        throw \Illuminate\Validation\ValidationException::withMessages([
          'category_event_id' => 'Choose a standard category, or use manual selection for a specific division or duplicate category.',
        ]);
      }
    }

    $individualDrawTypes = DrawType::query()->where('type', 'individual')->orderBy('id')->get();
    $drawTypeId = $validated['draw_type_id']
      ?? ($individualDrawTypes->firstWhere('drawTypeName', 'Singles') ?? $individualDrawTypes->first())?->id;

    abort_unless($drawTypeId, 422, 'Configure an individual Singles draw type first.');

    // A category-scoped draw is the safe default. Null remains supported for
    // explicitly event-wide draws and older integrations.
    $categoryId = $validated['category_event_id'] ?? null;

    // Create the draw
    $draw = Draw::create([
      'event_id' => $event->id,
      'drawName' => $validated['drawName'],
      'drawType_id' => $drawTypeId,
      'category_event_id' => $categoryId,
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
      'setup_url' => route('draw.setup.show', $draw),
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

    $this->authorize('event-draw.view', $event);

    $pdf = Pdf::loadView('backend.event.exports.all-players-pdf', compact('event'))
      ->setPaper('A4', 'portrait');

    return $pdf->stream(Str::slug($event->name) . '_players.pdf');
  }

  public function exportPlayersExcel($eventId)
  {
    $event = Event::with([
      'regions.teams.players'
    ])->findOrFail($eventId);

    $this->authorize('event-draw.view', $event);

    return Excel::download(new EventPlayersExport($event), "event_players_{$event->id}.xlsx");

  }
  public function importTeamCategoryEvents(Event $event)
  {
    $this->authorize('draw.create', $event);

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
    $this->authorize('event-draw.view', $event);

    if ($event->isInterprovincialTrials()) {
      $user = request()->user();
      abort_unless(
        $user && ($user->hasRole('super-user') || ($user->hasRole('admin') && $user->is_event_admin($event->id))),
        403
      );
    }

    $operations = app(EventOperationsService::class)->for($event);

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
      // Count only active, paid player registrations for backend statistics
      $stats['entries'] = $event->registrations()
        ->where('status', '!=', 'withdrawn')
        ->where('payment_status_id', 1)
        ->count(); // ✅ PLAYERS (excluding withdrawn and unpaid)
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

    $financeData = $event->isTeam()
      ? $this->buildFinanceData($event, $operations['financeTotals'], $operations['clothingReceipts'])
      : [];

    if ($event->isInterprovincialTrials()) {
      $interproBatch = \App\Models\InterprovincialTrialInvitationBatch::query()
        ->where('event_id', $event->id)
        ->latest('id')
        ->first();

      $currentIds = \App\Models\InterprovincialTrialInvitation::query()
        ->where('event_id', $event->id)
        ->selectRaw('COALESCE(MAX(CASE WHEN status != ? THEN id END), MAX(id))', ['prepared'])
        ->groupBy('nomination_id');
      $invitationStates = \App\Models\InterprovincialTrialInvitation::query()
        ->where('event_id', $event->id)
        ->whereIn('id', $currentIds)
        ->selectRaw('status, COUNT(*) as aggregate')
        ->groupBy('status')
        ->pluck('aggregate', 'status');

      $categoryCount = \App\Models\CategoryEvent::query()
        ->where('event_id', $event->id)
        ->count();
      $publishedCategoryCount = \App\Models\CategoryEvent::query()
        ->where('event_id', $event->id)
        ->where('nominations_published', true)
        ->count();
      $registrationClosesAt = $event->registrationClosesAt()?->endOfDay();
      $registrationOpen = (bool) $event->published
        && (int) $event->signUp === 1
        && $event->hasOpenRegistrationLifecycle()
        && (! $registrationClosesAt || now()->lte($registrationClosesAt));

      $interproStats = [
        'categories' => $categoryCount,
        'nominations' => \App\Models\EventNomination::query()->where('event_id', $event->id)->count(),
        'batch' => $interproBatch?->status ?? 'Not prepared',
        'queued' => (int) ($invitationStates['queued'] ?? 0),
        'sent' => (int) ($invitationStates['sent'] ?? 0),
        'acceptedPendingPayment' => (int) ($invitationStates[\App\Models\InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT] ?? 0),
        'paidConfirmed' => (int) ($invitationStates[\App\Models\InterprovincialTrialInvitation::PAID_CONFIRMED] ?? 0),
        'declinedOrWithdrawn' => (int) (($invitationStates[\App\Models\InterprovincialTrialInvitation::DECLINED] ?? 0)
          + ($invitationStates[\App\Models\InterprovincialTrialInvitation::WITHDRAWN] ?? 0)),
        'failed' => (int) ($invitationStates['failed'] ?? 0),
        'publication' => $publishedCategoryCount === 0
          ? 'Unpublished'
          : ($publishedCategoryCount === $categoryCount ? 'Published' : 'Partially published'),
        'registration' => $registrationOpen ? 'Open' : 'Closed',
      ];

      return view('backend.event.interprovincial-trials.overview', compact(
        'event', 'operations', 'interproBatch', 'interproStats'
      ));
    }

    if ($event->isMasters()) {
      $event->load(['series', 'categoryEvents.category']);
      $rankingCategoryLinks = \App\Models\MastersRankingCategoryLink::with(['rankingList.category', 'categoryEvent.category'])
        ->where('event_id', $event->id)->get();
      $mastersBatch = \App\Models\MastersInvitationBatch::where('event_id', $event->id)->where('status', '!=', 'restarted')->latest('id')->first();
      $mastersReadiness = $mastersBatch
        ? app(\App\Services\Masters\MastersInvitationService::class)->readiness($mastersBatch)
        : null;
      $rankingLists = $event->series?->ranking_lists()->with('category')->get() ?? collect();
      $publishedRuns = $event->series
        ? \App\Models\SeriesRanking::where('series_id', $event->series_id)
          ->where('status', 'published')->whereNotNull('run_id')->select('run_id')
          ->distinct()->orderByDesc('run_id')->pluck('run_id')
        : collect();

      return view('backend.event.masters.overview', compact(
        'event', 'stats', 'operations', 'mastersBatch', 'mastersReadiness', 'rankingLists', 'publishedRuns', 'rankingCategoryLinks'
      ));
    }

    return view('backend.event.overview', [
      'event' => $event,
      'stats' => $stats,
      'teamData' => $teamData,
      'venues' => \App\Models\Venue::all(), // <-- this line is required!
      'operations' => $operations,
    ] + $financeData);
  }

  protected function buildFinanceData(Event $event, ?array $canonicalTotals = null, ?array $clothingReceipts = null): array
  {
    $feePerEntry = (float) $event->cape_tennis_fee;

    $transactions = Transaction::with([
      'user',
      'order.items.player',
      'order.items.category_event.category',
    ])
      ->where('event_id', $event->id)
      ->where('transaction_type', 'Registration')
      ->where('amount_gross', '>', 0)
      ->orderByDesc('created_at')
      ->get();

    $totalGross             = $canonicalTotals['registration_received'] ?? $transactions->sum('amount_gross');
    $totalPayfastFees       = ($canonicalTotals['pf_fees'] ?? $transactions->sum('amount_fee'))
      - ($clothingReceipts['totals']['fees'] ?? 0);
    $totalEntries           = $canonicalTotals['total_entries']
      ?? $transactions->sum(fn($t) => $t->order?->items?->count() ?? 1);
    $totalCapeTennisFees    = abs($canonicalTotals['cape_fees'] ?? ($totalEntries * $feePerEntry));
    $netRegistrationIncome  = $canonicalTotals['registration_net'] ?? ($totalGross - abs($totalPayfastFees) - $totalCapeTennisFees);
    $registrationRefundAdjustment = round($netRegistrationIncome - ($totalGross - abs($totalPayfastFees) - $totalCapeTennisFees), 2);

    $incomeItems      = $event->incomeItems()->get();
    $totalIncomeItems = $incomeItems->sum(fn($i) => $i->calculatedTotal());
    $grandTotalIncome = $netRegistrationIncome + ($clothingReceipts['totals']['net'] ?? 0) + $totalIncomeItems;

    $convenors = $event->convenors()
      ->with('user')
      ->orderByRaw("CASE role WHEN 'hoof' THEN 1 WHEN 'hulp' THEN 2 WHEN 'admin' THEN 3 ELSE 4 END")
      ->get();

    $expenses = EventExpense::where('event_id', $event->id)
      ->with(['paidByConvenor.user', 'approvedByUser', 'reimbursedByUser'])
      ->orderByDesc('created_at')
      ->get();

    // Keep derived system fees visible to the existing UI without mutating
    // expense history during a GET request. The ledger remains authoritative.
    if (!$expenses->whereIn('expense_type', ['payfast'])->count() && abs($totalPayfastFees) > 0) {
      $expenses->push(new EventExpense([
        'event_id' => $event->id,
        'expense_type' => 'payfast',
        'description' => 'PayFast fees (derived from ledger)',
        'amount' => abs($totalPayfastFees),
        'date' => now(),
      ]));
    }
    if (!$expenses->whereIn('expense_type', ['cape_tennis_fee'])->count() && $totalCapeTennisFees > 0) {
      $expenses->push(new EventExpense([
        'event_id' => $event->id,
        'expense_type' => 'cape_tennis_fee',
        'description' => 'Cape Tennis fee (derived from ledger)',
        'amount' => $totalCapeTennisFees,
        'date' => now(),
      ]));
    }

    $expenseTypes = ExpenseType::asOptions();

    $systemTypes         = ['payfast', 'cape_tennis_fee'];
    $operationalExpenses = $expenses->reject(fn($e) => in_array($e->expense_type, $systemTypes));
    $systemExpenseRows   = $expenses->filter(fn($e) => in_array($e->expense_type, $systemTypes));

    $expensesByConvenor = $operationalExpenses->groupBy('paid_by_convenor_id');
    $expensesByType     = $operationalExpenses->groupBy('expense_type');
    $totalExpenses      = $operationalExpenses->sum(fn($e) => $e->calculatedAmount());
    $totalSystemFees    = $systemExpenseRows->sum(fn($e) => $e->calculatedAmount());
    $totalBudget        = $operationalExpenses->whereNotNull('budget_amount')->sum('budget_amount');
    $pendingApproval    = $operationalExpenses->whereNull('approved_at')->count();
    $pendingReimbursement = $operationalExpenses->whereNotNull('approved_at')->whereNull('reimbursed_at')->count();

    $recon = $convenors->map(function ($convenor) use ($operationalExpenses) {
      $paid = $operationalExpenses
        ->where('paid_by_convenor_id', $convenor->id)
        ->sum(fn($e) => $e->calculatedAmount());

      return [
        'convenor'   => $convenor,
        'total_paid' => $paid,
        'owed_back'  => $paid,
        'reimbursed' => $operationalExpenses
          ->where('paid_by_convenor_id', $convenor->id)
          ->whereNotNull('reimbursed_at')
          ->sum(fn($e) => $e->calculatedAmount()),
      ];
    });

    $budgetCapWarning = $event->budget_cap
      ? ($totalExpenses / $event->budget_cap) >= 0.9
      : false;

    $netProfit = $grandTotalIncome - $totalExpenses;

    // Income by category (individual events)
    $incomeByCategory = collect();
    foreach ($transactions as $t) {
      $items     = $t->order?->items ?? collect();
      $itemCount = $items->count();
      if ($itemCount === 0) continue;
      $amtPerItem = (float) $t->amount_gross / $itemCount;
      foreach ($items as $item) {
        $catName = $item->category_event?->category?->name ?? 'Unknown';
        $current = $incomeByCategory->get($catName, ['entries' => 0, 'amount' => 0.0]);
        $incomeByCategory->put($catName, [
          'entries' => $current['entries'] + 1,
          'amount'  => $current['amount'] + $amtPerItem,
        ]);
      }
    }
    $incomeByCategory = $incomeByCategory->sortKeys();

    return compact(
      'totalGross',
      'totalPayfastFees',
      'totalCapeTennisFees',
      'totalEntries',
      'feePerEntry',
      'netRegistrationIncome',
      'registrationRefundAdjustment',
      'incomeByCategory',
      'clothingReceipts',
      'incomeItems',
      'totalIncomeItems',
      'grandTotalIncome',
      'convenors',
      'expenses',
      'expenseTypes',
      'expensesByConvenor',
      'expensesByType',
      'totalExpenses',
      'totalSystemFees',
      'totalBudget',
      'pendingApproval',
      'pendingReimbursement',
      'recon',
      'budgetCapWarning',
      'netProfit'
    );
  }

  protected function buildEventTransactions(Event $event): array
  {
    $runningBalance = 0;
    $capeTennisFeeSet = (float) $event->cape_tennis_fee;

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
    // Load categories but only include paid registrations for backend views
    $categories = CategoryEvent::with([
      'category',
      'registrations' => function ($query) {
        $query->where('payment_status_id', 1)->with('players');
      },
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
    $this->authorize('event-draw.view', $event);

    $categoryEvents = $event->eventCategories()
      ->with([
        'category',
        'categoryEventRegistrations' => function ($query) {
          $query->where('payment_status_id', 1)->with('registration.players');
        },
      ])
      ->get();

    return view('backend.event.entries', compact('event', 'categoryEvents'));
  }


  public function lockCategory(CategoryEvent $categoryEvent)
  {
    $this->authorize('category.manage', $categoryEvent);

    $categoryEvent->update(['locked_at' => now()]);

    return response()->json([
      'success' => true,
      'locked' => true,
    ]);
  }

  public function unlockCategory(CategoryEvent $categoryEvent)
  {
    $this->authorize('category.manage', $categoryEvent);

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
    $this->authorize('category.manage', $categoryEvent);

    abort_if($categoryEvent->isLocked(), 403);

    $request->validate([
      'registration_id' => 'required|exists:registrations,id',
    ]);

    $exists = $categoryEvent->activeRegistrations()
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
        'payment_status_id' => 1,
      ]);

    return response()->json([
      'success' => true,
      'count' => $categoryEvent->activeRegistrations()->count(),
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
    $this->authorize('category.manage', $categoryEvent);

    abort_if($categoryEvent->isLocked(), 403);

    $cer = $categoryEvent->categoryEventRegistrations()
      ->where('registration_id', $registration->id)
      ->first();

    if ($cer) {
      app(\App\Domain\Entries\Services\EntryService::class)
        ->withdrawEntryAsAdmin($cer, auth()->user());
    }

    return response()->json([
      'success' => true,
      'count' => $categoryEvent->activeRegistrations()->count(),
    ]);

  }
  public function settings(Event $event)
  {
    $this->authorize('event-draw.view', $event);

    $event->load([
      'categoryEvents.category',
      'venues',
    ]);

    return view('backend.event.settings', compact('event'));
  }

  public function fixtures(Event $event)
  {
    $this->authorize('event-draw.view', $event);

    $event->load([
      'draws.categoryEvent.category',
      'draws.groups',
      'venues',
    ]);

    $draws = $event->draws()
      ->with([
        'categoryEvent.category',
        'drawFixtures.registration1.players',
        'drawFixtures.registration2.players',
        'drawFixtures.fixtureResults',
        'groups.fixtures',
      ])
      ->withCount('drawFixtures')
      ->get();

    $stats = [
      'totalFixtures' => $draws->sum('draw_fixtures_count'),
      'completedFixtures' => $event->fixtures()->whereNotNull('winner_registration')->count(),
      'pendingFixtures' => $event->fixtures()->whereNull('winner_registration')->count(),
    ];

    return view('backend.event.fixtures', compact('event', 'draws', 'stats'));
  }


}
