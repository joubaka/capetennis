<?php

namespace App\Http\Controllers\Backend;

use App\Services\Payfast;
use App\Http\Controllers\Controller;
use App\Imports\ImportUsers;
use App\Imports\UsersImport;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\TeamCategory;
use App\Models\TeamPlayer;
use App\Models\NoProfileTeamPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\CssSelector\Node\FunctionNode;
use App\Services\FixtureService;
use App\Imports\NoProfileTeamImport;
use App\Imports\ExternalTeamWorkbookParser;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\TeamPaymentOrder;
use App\Domain\Payments\Services\TeamPaymentService;
use App\Domain\Teams\Services\ExternalTeamRosterService;
use App\Domain\Teams\Services\ExternalTeamWorkbookService;
use App\Services\PlayerIdentityService;

class TeamController extends Controller
{
    /** Serialize legacy roster writes with audited competition transitions. */
    public function callAction($method, $parameters)
    {
        $rosterWrites = ['store', 'addToRegion', 'destroy', 'toggleNoProfile', 'insertPlayer', 'order_player_list', 'changeCategory', 'importNoProfile',
            'importNoProfileTeams', 'importFromRanking', 'updateNoProfile', 'addPlayers', 'removePlayer', 'changePlayer', 'editPlayerName', 'updateRoster'];
        if (!in_array($method, $rosterWrites, true)) return parent::callAction($method, $parameters);
        $event = collect($parameters)->first(fn ($value) => $value instanceof Event);
        $team = collect($parameters)->first(fn ($value) => $value instanceof Team);
        $request = request();
        if (!$team && $request->input('team_id', $request->input('team'))) {
            $team = Team::find($request->input('team_id', $request->input('team')));
        }
        if (!$team && $method === 'insertPlayer') $team = TeamPlayer::find($request->input('pivot'))?->team;
        if (!$team && in_array($method, ['destroy', 'toggleNoProfile'], true)) $team = Team::find($parameters['id'] ?? null);
        if (!$team && $method === 'updateNoProfile') {
            $identity = NoProfileTeamPlayer::find($parameters['id'] ?? null);
            $team = $identity?->team_id ? Team::find($identity->team_id) : null;
        }
        $eventId = $event?->id ?? $team?->category?->event_id ?? CategoryEvent::find($request->input('category_event_id'))?->event_id;
        if (!$eventId && $team && $request->integer('event_id')) {
            $candidate = Event::find($request->integer('event_id'));
            if ($candidate && app(ExternalTeamRosterService::class)->teamBelongsToEvent($team, $candidate)) $eventId = $candidate->id;
        }
        if (!$eventId) return parent::callAction($method, $parameters);
        return DB::transaction(function () use ($method, $parameters, $eventId) {
            $adaptation = app(\App\Services\TeamDrawAdaptationService::class);
            $adaptation->lockEvent($eventId);
            abort_if(!in_array($method, ['store', 'addToRegion'], true) && \App\Models\TeamSubstitution::where('event_id', $eventId)->lockForUpdate()->exists(), 409,
                'This event has audited replacements. Generic roster edits and imports are blocked; use the replacement wizard to preserve history.');
            $response = parent::callAction($method, $parameters);
            if (!($response instanceof \Symfony\Component\HttpFoundation\Response) || $response->getStatusCode() < 400) {
                $reports = $adaptation->adaptEvent($eventId);
                $warnings = collect($reports)->flatMap(fn ($report) => $report['warnings'])->unique()->values()->all();
                if ($response instanceof \Illuminate\Http\JsonResponse) {
                    $body = $response->getData(true);
                    $response->setData(array_merge($body, ['adaptation' => $reports, 'warnings' => $warnings]));
                } elseif ($warnings) session()->flash('schedule_adaptation_warning', implode(' ', $warnings));
            }
            return $response;
        });
    }
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
    // Authorize: must provide category_event_id and be able to create team in that category
    $categoryEventId = $request->input('category_event_id');
    if ($categoryEventId) {
      $categoryEvent = CategoryEvent::findOrFail($categoryEventId);
      $this->authorize('team.create', $categoryEvent);
    }

    $request->validate([
      'name' => 'required|string|max:255',
      'year' => 'nullable|integer',
      'region_id' => 'nullable|exists:team_regions,id',
      'num_players' => 'nullable|integer|min:0|max:50'
    ]);

    $team = new Team();
    $team->name = $request->name;
    $team->year = $request->year;
    $team->published = $request->published ?? 0;
    $team->num_team_members = $request->num_players ?? 0;
    $team->category_event_id = $categoryEventId;
    $team->region_id = $request->region_id; // ✅ Direct foreign key
    $team->save();

    // Create dummy team player slots
    $numPlayers = $request->num_players ?? 0;

    for ($i = 0; $i < $numPlayers; $i++) {
      TeamPlayer::create([
        'team_id' => $team->id,
        'player_id' => 0, // Dummy placeholder
        'rank' => $i + 1,
        'pay_status' => 0,
      ]);
    }

    return response()->json([
      'success' => true,
      'id' => $team->id,
      'name' => $team->name,
      'published' => $team->published,
      'num_team_members' => $team->num_team_members,
      'region_id' => $team->region_id
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

    public function rename(Request $request, Event $event, Team $team)
    {
        abort_unless((int) $team->category?->event_id === (int) $event->id, 404);
        $this->authorize('team.update', $team);

        $name = $request->input('name');
        $request->merge(['name' => is_string($name) ? trim($name) : $name]);
        $validated = $request->validateWithBag('teamRename', [
            'name' => ['required', 'string', 'max:255'],
        ]);

        $team->update(['name' => $validated['name']]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Team name updated.']);
        }
        return redirect()->route('admin.events.teams', $event)
            ->with('team_rename_success', 'Team name updated.')
            ->with('renamed_team_id', $team->id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $team = Team::findOrFail($id);
        $this->authorize('team.delete', $team);
        app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->assertRosterEditable($team);

        abort_if(\App\Models\TeamTie::where(fn ($q) => $q->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id))
            ->where(fn ($q) => $q->where('status', \App\Models\TeamTie::STATUS_COMPLETED)->orWhereHas('rubbers', fn ($r) => $r->where('match_status', '!=', 0)->orWhereHas('fixtureResults')))->exists(), 409,
            'This team has played history. Remove it from upcoming draw membership instead of deleting it.');
        \App\Models\TeamTie::where(fn ($q) => $q->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id))->get()->each(function ($tie) {
            $tie->rubbers()->get()->each(fn ($fixture) => $fixture->fixturePlayers()->delete());
            $tie->rubbers()->delete();
            $tie->delete();
        });
        Team::where('id', $id)->delete();
        return 'deleted';
    }

  public function insertPlayer(Request $request)
  {
    $teamplayer = TeamPlayer::findOrFail($request->pivot);
    $team = $teamplayer->team;
    $this->authorize('team.players.manage', $team);
    app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->assertRosterEditable($team);

    $teamplayer->player_id = $request->player;
    $teamplayer->save();

    $player = $teamplayer->player; // eager load relation

    return response()->json([
      'success' => true,
      'id' => $teamplayer->id,
      'player' => [
        'id' => $player->id,
        'name' => $player->name,
        'surname' => $player->surname,
        'email' => $player->email,
        'cellNr' => $player->cellNr,
      ],
    ]);
  }



 


  public function addToRegion(Request $request)
  {
    Log::info('addToRegion: request received', $request->all());

    $validated = $request->validate([
      'team_name' => 'required|string|max:255',
      'num_players' => 'nullable|integer|min:1',
      'year' => 'nullable|string|max:10',
      'region_id' => 'required|integer|exists:team_regions,id',
      'published' => 'nullable|boolean',
    ]);

    // Authorize: user must be able to create teams in this event context
    // For region-based teams, we check the admin/convenor role
    $this->authorize('team.admin', Event::class);

    Log::info('addToRegion: validated data', $validated);

    return DB::transaction(function () use ($validated) {

      // 1️⃣ Create team
      $team = Team::create([
        'name' => $validated['team_name'],
        'num_team_members' => $validated['num_players'] ?? 0,
        'year' => $validated['year'] ?? date('Y'),
        'region_id' => $validated['region_id'],
        'published' => $validated['published'] ?? 0,
      ]);

      Log::info('addToRegion: team created', [
        'team_id' => $team->id,
        'region_id' => $team->region_id,
      ]);

      // 2️⃣ Create dummy team players
      $slots = (int) ($validated['num_players'] ?? 0);

      Log::info('addToRegion: creating dummy slots', [
        'slots' => $slots,
      ]);

      if ($slots > 0) {
        $rows = [];

        for ($i = 1; $i <= $slots; $i++) {
          $rows[] = [
            'team_id' => $team->id,
            'player_id' => 0,   // dummy slot
            'rank' => $i,
            'pay_status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
          ];
        }

        Log::info('addToRegion: inserting team_players rows', $rows);

        TeamPlayer::insert($rows);
      }

      Log::info('addToRegion: completed successfully');

      return response()->json([
        'success' => true,
        'region_id' => $team->region_id,
        'team' => [
          'id' => $team->id,
          'name' => $team->name,
        ],
      ]);
    });
  }


  public function order_player_list(Request $request)
  {
    if ($request->filled('direction')) {
      [$event, $region, $team] = $this->rosterContext($request);
      $data = $request->validate(['direction' => ['required', 'in:up,down'], 'slot_id' => ['required', 'integer'], 'expected_rank' => ['required', 'integer', 'min:1'], 'expected_player_id' => ['required', 'integer', 'min:0']]);
      app(\App\Services\TeamRosterService::class)->change($event, $region, $team, $request->user(), 'move', $data);

      return response()->json(['message' => 'Player order updated.']);
    }
    $teamId = (int) $request->input('team_id');
    $team = Team::findOrFail($teamId);
    $this->authorize('team.players.manage', $team);
    app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->assertRosterEditable($team);

    [$event, $region, $team] = $this->rosterContext($request);
    $data = $request->validate(['order' => ['required', 'array', 'min:1', 'max:100'], 'order.*.position' => ['required', 'integer', 'min:1'], 'order.*.id' => ['nullable', 'integer'], 'order.*.team_player_id' => ['nullable', 'integer'], 'order.*.no_profile_id' => ['nullable', 'integer']]);
    $orderedIds = collect($data['order'])->sortBy('position')->map(fn ($row) => $team->noProfile
        ? ($row['no_profile_id'] ?? $row['id'] ?? 0) : ($row['team_player_id'] ?? $row['id'] ?? 0))->all();
    app(\App\Services\TeamRosterService::class)->reorder($event, $region, $team, $request->user(), $orderedIds);

    $players = $team->noProfile ? $team->team_players_no_profile()->orderBy('rank')->get() : $team->team_players()->with('player')->get();
    return response()->json(['status' => 'ok', 'team_id' => $team->id, 'players' => $players->map(fn ($slot) => ['id' => $slot->id, 'type' => $team->noProfile ? 'noprofile' : 'profile', 'rank' => $slot->rank, 'pay_status' => $slot->pay_status, 'player' => $team->noProfile ? null : $slot->player, 'name' => $slot->name, 'surname' => $slot->surname])]);
  }
  public function publishTeam(Request $request, $id)
  {
    $team = Team::findOrFail($id);
    $this->authorize('team.update', $team);

    $validated = $request->validate([
      'published' => 'sometimes|required|boolean',
    ]);

    // Keep the legacy toggle fallback for older callers while allowing the
    // AJAX UI to send an explicit, retry-safe target state.
    $published = array_key_exists('published', $validated)
      ? (bool) $validated['published']
      : !(bool) $team->published;

    $team->update(['published' => $published]);

    return response()->json([
      'success' => true,
      'team_id' => $team->id,
      'published' => (bool) $team->published,
      'message' => $team->published ? 'Team published.' : 'Team unpublished.',
    ]);
  }

  public function publishRegionTeams(Request $request, Event $event, TeamRegion $region)
  {
    $this->authorize('event-draw.view', $event);

    abort_unless(
      $event->regions()->whereKey($region->id)->exists(),
      404,
      'This region does not belong to the event.'
    );

    $validated = $request->validate(['published' => 'sometimes|required|boolean']);
    // Older callers publish only; explicit target states make current retries safe.
    $targetState = array_key_exists('published', $validated) ? (bool) $validated['published'] : true;
    $teams = Team::query()
      ->where('region_id', $region->id)
      ->whereHas('category', fn ($query) => $query->where('event_id', $event->id));

    $teamIds = (clone $teams)->pluck('id');
    $total = $teamIds->count();
    $changed = (clone $teams)->where('published', ! $targetState)->update(['published' => $targetState]);
    $status = $targetState ? 'published' : 'unpublished';
    $message = $total === 0 ? 'No teams in this region yet.' : ($changed > 0
      ? $changed.' '.str('team')->plural($changed).' '.$status.'.'
      : 'All teams in this region were already '.$status.'.');

    return response()->json([
      'success' => true,
      'event_id' => $event->id,
      'region_id' => $region->id,
      'total' => $total,
      'published' => $changed,
      'changed' => $changed,
      'state' => $targetState,
      'team_ids' => $teamIds,
      'message' => $message,
    ]);
  }



  public function team_payment_payfast(Request $request, $teamId, $playerId, $eventId)
  {
    // require authenticated user
    $user = auth()->user();
    if (!$user) {
        return redirect()->route('home')->withErrors('Please log in to continue.');
    }

    $team = \App\Models\Team::findOrFail($teamId);
    $player = \App\Models\Player::findOrFail($playerId);
    $event = \App\Models\Event::findOrFail($eventId);

    try {
      app(ExternalTeamRosterService::class)->assertSelectedRole($event, $team, $player);
      app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)
        ->beginRegistration($event->id, $team->id, $player->id, $user);
      app(ExternalTeamRosterService::class)->assertCanRegister($user, $event, $team, $player);
    } catch (\Illuminate\Validation\ValidationException $exception) {
      return redirect()->back()->withErrors($exception->errors());
    } catch (\RuntimeException $exception) {
      return redirect()->back()->withErrors($exception->getMessage());
    }

    // compute fees (adapt the regionFee computation to your schema)
    $entryFee = (float) ($event->entryFee ?? 0);
    $regionFee = 0;
    if (isset($team->region_id)) {
        $region = TeamRegion::find($team->region_id);
        // replace below with your actual region fee logic
        $regionFee = $region ? (float) ($region->region_fee ?? 0) : 0;
    }
    $total = round($entryFee + $regionFee, 2);

    // create or load TeamPaymentOrder (unique constraint prevents duplicates)
    try {
      if ($request->isMethod('post')) {
        $request->validate(['custom_int5' => ['required', 'integer', 'min:1']]);
        $order = TeamPaymentOrder::findOrFail($request->integer('custom_int5'));
        abort_unless((int) $order->user_id === (int) $user->id
          && (int) $order->event_id === (int) $event->id
          && (int) $order->team_id === (int) $team->id
          && (int) $order->effective_player_id === (int) $player->id, 403);
        if ($order->withdrawn_at) {
          return redirect()->route('events.show', $event)->withErrors(['payment' => 'This checkout was cancelled or restarted. Start registration again from the event.']);
        }
      } else {
        $order = app(TeamPaymentService::class)->ensureOrder($user, $team, $player, $event, $total);
      }
    } catch (\Illuminate\Validation\ValidationException $exception) {
      $busyMessage = 'Someone is busy processing a payment. Please wait five minutes before trying again.';
      if ($exception->errors() !== ['payment' => [$busyMessage]]) {
        throw $exception;
      }

      $context = app(TeamPaymentService::class)->pendingRestartContext($user, $team, $player, $event);
      return redirect()->route('events.show', $event)->with('team_payment_recovery', [
        'event_id' => (int) $event->id,
        'restart_url' => \Illuminate\Support\Facades\URL::temporarySignedRoute('team.payment.restart', now()->addMinutes(10), [
          'team' => $team->id, 'player' => $player->id, 'event' => $event->id,
          'expected_order' => $context['order'], 'expected_state' => $context['state'], 'actor' => $context['actor'],
        ]),
        'message' => $busyMessage,
      ]);
    }
    $selectionInvitation = app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->attachOrder($order);

    $walletBalance = round((float) ($user->wallet->balance ?? 0), 2);
    $walletReserved = round((float) ($order->wallet_reserved ?? 0), 2);
    $continuingHandoff = (int) $order->user_id === (int) $user->id
      && app(TeamPaymentService::class)->hasUnresolvedPayfastHandoff($order);
    if ($continuingHandoff) {
      $total = round((float) $order->total_amount, 2);
      $regionFee = max(0, round($total - $entryFee, 2));
    }

    if (!$continuingHandoff && $walletReserved > $walletBalance) {
      $walletReserved = 0.00;
      $order = app(TeamPaymentService::class)->reservePayment($order, 0, round($total, 2));
    }

    $payfastDueStored = round((float) ($order->payfast_amount_due ?? $total), 2);
    $calculatedPayfastDue = round($total - $walletReserved, 2);
    $payfastDue = $continuingHandoff ? $payfastDueStored : (abs($payfastDueStored - $calculatedPayfastDue) > 0.01 ? $calculatedPayfastDue : $payfastDueStored);

    if ($payfastDue < 0) {
      $payfastDue = 0.00;
    }

    $autoSubmitPayfast = $request->isMethod('post');
    if ($autoSubmitPayfast) {
      try {
        $order = app(TeamPaymentService::class)->recordPayfastHandoff($order, $user, $payfastDue);
      } catch (\Illuminate\Validation\ValidationException $exception) {
        return redirect()->back()->withErrors($exception->errors());
      }
    }

    return $this->renderTeamPaymentCheckout(
      $order, $user, $team, $player, $event, $total, $regionFee,
      $walletBalance, $walletReserved, $payfastDue, $autoSubmitPayfast,
      $selectionInvitation
    );
  }

  public function restartTeamPayment(Request $request, Team $team, Player $player, Event $event)
  {
    abort_unless((int) $request->query('actor') === (int) auth()->id(), 403);
    $request->validate(['confirm_restart' => ['accepted']]);
    try {
      $order = app(TeamPaymentService::class)->restartPendingCheckout(
        auth()->user(), $team, $player, $event,
        (int) $request->query('expected_order'), (string) $request->query('expected_state')
      );
    } catch (\Illuminate\Validation\ValidationException $exception) {
      return redirect()->route('events.show', $event)->withErrors($exception->errors());
    } catch (\RuntimeException $exception) {
      return redirect()->route('events.show', $event)->withErrors(['payment' => $exception->getMessage()]);
    }

    return redirect()->route('team.checkout', $order);
  }

  private function renderTeamPaymentCheckout(
    $order, $user, $team, $player, $event, $total, $regionFee,
    $walletBalance, $walletReserved, $payfastDue, bool $autoSubmitPayfast,
    $selectionInvitation = null
  ) {
    // Prepare Payfast instance
    $payfast = new \App\Services\Payfast();
    // Sandbox only on non-production environments (local/staging) for user 584.
    // On production (remote) always use live regardless of user.
    $isLocalTest = !app()->environment('production') && $user->id == 584;
    $payfast->setMode($isLocalTest ? 0 : 1);

    // set core fields
    $payfast->setEvent($event);                     // sets custom_int3, custom_str3, item_name
    $payfast->setPlayerInfo($player);               // sets custom_int2, custom_str2
    $payfast->setPayer($user);                      // sets custom_str4
    $payfast->custom_int4 = $user->id;              // user_id (must be set so no empty field is posted)
    $payfast->amount = number_format($payfastDue, 2, '.', ''); // amount expects formatted string
    // For team orders we don't have RegistrationOrder type — set custom_int5 manually
    $payfast->custom_int5 = $order->id;
    $payfast->custom_str5 = 'TeamOrder';

    // Configure urls (notify team, cancel and return)
    // notify team ITN route
    $payfast->setTeamNotifyUrl(route('notify.team'));
    // cancel/return should use routes that exist in your app
    $cancelUrl = $selectionInvitation
      ? route('team-selection.invitations.show', $selectionInvitation)
      : route('team.checkout', ['order' => $order->id]);
    $payfast->setCancelUrl($cancelUrl);
    $payfast->setReturnUrl(route('event.success', ['id' => $event->id]) . '?email=' . urlencode($user->email));
    Log::info('TEAM PAYFAST INIT', [
      'order_id' => $order->id,
      'notify_url' => route('notify.team'),
      'amount' => $payfast->amount,
      'mode' => $isLocalTest ? 'sandbox' : 'live',
    ]);

    // pass variables expected by the blade
    return view('frontend.payfast.team_payment', [
        'payfast' => $payfast,
        'event' => $event,
        'user' => $user,
        'regionFee' => $regionFee,
        'team' => $team,
        'player' => $player,
        'order' => $order,
        'cancelUrl' => $cancelUrl,
        'total' => $total,
        'walletBalance' => $walletBalance,
        'walletReserved' => $walletReserved,
        'payfastDue' => $payfastDue,
        'autoSubmitPayfast' => $autoSubmitPayfast,
    ]);
  }

  public function changeCategory(Request $request, $id)
    {
        $team = Team::findOrFail($request->team);
        $this->authorize('team.update', $team);
        app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->assertRosterEditable($team);

        $eventCategory = CategoryEvent::findOrFail($request->data);

        // Authorization on the current team does not grant access to move it
        // into a different event. Enforce event isolation on the destination.
        $teamEvent = optional(optional($team->category)->event);
        $targetEvent = optional($eventCategory->event);

        if (!$teamEvent || !$targetEvent || $teamEvent->id !== $targetEvent->id) {
            abort(403);
        }

        $team->category_event_id = $eventCategory->id;
        $team->save();

        return $team->category?->category?->name ?? $eventCategory->category?->name ?? $eventCategory->name ?? 'Updated';
    }

    public function importView()
    {
        $this->authorize('team.admin', Event::class);

        return view('backend.import.noProfileImport');
    }



  public function importNoProfile(
    Request $request,
    Event $event,
    Team $team,
    ExternalTeamRosterService $rosters,
    PlayerIdentityService $identities
  )
  {
    $this->authorize('event.manage', $event);
    $rosters->assertTeamBelongsToEvent($team, $event);
    app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->assertRosterEditable($team);

    $request->validate([
      'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
      'confirmed' => 'nullable|boolean',
    ]);

    try {
      $import = new NoProfileTeamImport();
      Excel::import($import, $request->file('file'));
      $rows = $import->rows();
      $errors = array_merge($import->errors(), $rosters->validateImport($team, $rows));

      $preview = collect($rows)->map(function (array $row) use ($identities): array {
        $candidates = $row['date_of_birth']
          ? collect([$identities->find($row['name'], $row['surname'], $row['date_of_birth'])])->filter()
          : $identities->findNameCandidates($row['name'], $row['surname']);

        return [
          'rank' => $row['rank'],
          'name' => $row['name'],
          'surname' => $row['surname'],
          'date_of_birth' => $row['date_of_birth'],
          'candidate_count' => $candidates->count(),
        ];
      })->values();

      if ($errors !== []) {
        return response()->json([
          'success' => false,
          'message' => 'The roster has validation errors. Nothing was imported.',
          'errors' => array_values(array_unique($errors)),
          'preview' => $preview,
        ], 422);
      }

      if (! $request->boolean('confirmed')) {
        return response()->json([
          'success' => true,
          'requires_confirmation' => true,
          'message' => 'Review the roster before confirming the import.',
          'preview' => $preview,
          'row_count' => count($rows),
        ]);
      }

      $rosters->import($team, $rows, $request->user());

      return response()->json([
        'success' => true,
        'requires_confirmation' => false,
        'message' => count($rows).' roster players imported into '.$team->name.'.',
        'imported_count' => count($rows),
        'team_id' => $team->id,
      ]);
    } catch (\Throwable $e) {
      \Log::error('NoProfile import failed', [
        'error' => $e->getMessage(),
      ]);

      return response()->json([
        'success' => false,
        'message' => 'The roster could not be read. Check the template and try again.',
      ], 422);
    }
  }

  public function importNoProfileTeams(
    Request $request,
    Event $event,
    TeamRegion $region,
    ExternalTeamWorkbookParser $parser,
    ExternalTeamWorkbookService $workbooks
  ) {
    $this->authorize('event.manage', $event);
    $workbooks->assertRegionBelongsToEvent($event, $region);

    $validated = $request->validate([
      'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
      'confirmed' => 'nullable|boolean',
      'expected_players' => 'nullable|integer|min:1|max:50',
      'fill_missing_players' => 'nullable|boolean',
      'team_prefix' => 'nullable|string|max:100',
      'sheet_name' => 'nullable|string|max:255',
      'selected_team_keys' => 'nullable|array',
      'selected_team_keys.*' => ['string', 'regex:/^(boys|girls)-u\d{2}$/'],
    ]);

    $expectedPlayers = (int) ($validated['expected_players'] ?? 8);
    $teamPrefix = trim((string) ($validated['team_prefix'] ?? $region->short_name ?? $region->region_name));
    if ($teamPrefix === '') {
      $teamPrefix = $region->region_name;
    }

    try {
      $parsed = $parser->parse(
        $request->file('file')->getRealPath(),
        $expectedPlayers,
        $validated['sheet_name'] ?? null
      );
    } catch (\Throwable $e) {
      Log::error('External team workbook could not be read', [
        'event_id' => $event->id,
        'region_id' => $region->id,
        'error' => $e->getMessage(),
      ]);

      return response()->json([
        'success' => false,
        'message' => 'The workbook could not be read. Check the file format and try again.',
      ], 422);
    }

    if ($parsed['errors'] !== []) {
      return response()->json([
        'success' => false,
        'message' => 'No importable team roster was found. Nothing was imported.',
        'errors' => $parsed['errors'],
        'sheets' => $parsed['sheets'],
      ], 422);
    }

    $preview = $workbooks->preview(
      $event,
      $region,
      $parsed['teams'],
      $teamPrefix,
      $expectedPlayers,
      $request->boolean('fill_missing_players')
    );

    if (! $request->boolean('confirmed')) {
      $completeTeams = collect($preview)->where('selectable', true);
      $placeholderCount = $completeTeams->sum('placeholder_count');

      return response()->json([
        'success' => true,
        'requires_confirmation' => true,
        'message' => 'Review the detected teams and select the complete rosters to import.',
        'selected_sheet' => $parsed['selected_sheet'],
        'sheets' => $parsed['sheets'],
        'teams' => $preview,
        'complete_team_count' => $completeTeams->count(),
        'complete_player_count' => $completeTeams->sum('player_count'),
        'placeholder_player_count' => $placeholderCount,
      ]);
    }

    $imported = $workbooks->import(
      $event,
      $region,
      $preview,
      $validated['selected_team_keys'] ?? [],
      $teamPrefix,
      $expectedPlayers,
      $request->user()
    );

    return response()->json([
      'success' => true,
      'requires_confirmation' => false,
      'message' => count($imported).' no-profile teams imported into '.$region->region_name.'.',
      'team_count' => count($imported),
      'player_count' => array_sum(array_column($imported, 'player_count')),
      'teams' => $imported,
    ]);
  }
  public function teamPlayersTable($teamId)
  {
    $team = Team::with([
      'players' => fn($q) => $q->withPivot('rank', 'pay_status')->orderBy('team_players.rank'),
      'team_players_no_profile' => fn($q) => $q->orderBy('rank'),
    ])->findOrFail($teamId);

    $this->authorize('team.view', $team);

    return view('backend.adminPage.partials.team_players_table', compact('team'));
  }





  public function downloadTemplate(Event $event, Team $team, ExternalTeamRosterService $rosters)
  {
    $this->authorize('event.manage', $event);
    $rosters->assertTeamBelongsToEvent($team, $event);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Header row
    $sheet->setCellValue('A1', 'Rank');
    $sheet->setCellValue('B1', 'Name');
    $sheet->setCellValue('C1', 'Surname');
    $sheet->setCellValue('D1', 'DateOfBirth');
    $sheet->setCellValue('E1', 'Email');
    $sheet->setCellValue('F1', 'Cell');

    // Example row
    $sheet->setCellValue('A2', '1');
    $sheet->setCellValue('B2', 'Lisa');
    $sheet->setCellValue('C2', 'van Zyl');
    $sheet->setCellValue('D2', '2013-05-20');

    // Autosize columns
    foreach (range('A', 'F') as $col) {
      $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Stream to browser
    $writer = new Xlsx($spreadsheet);
    $response = new StreamedResponse(function () use ($writer) {
      $writer->save('php://output');
    });

    $fileName = 'external_roster_team_'.$team->id.'.xlsx';
    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->headers->set('Content-Disposition', 'attachment;filename="' . $fileName . '"');
    $response->headers->set('Cache-Control', 'max-age=0');

    return $response;
  }



  public function changePayStatus(Request $request)
  {
    $teamplayer = TeamPlayer::findOrFail($request->pivot_id);
    $team = $teamplayer->team;
    $this->authorize('team.players.manage', $team);
    app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->assertRosterEditable($team);

    return response()->json([
      'success' => false,
      'message' => 'Payment status is derived from payment records. Use event finances to record or review payments.',
      'url' => route('admin.events.finances', ['event' => $team->category->event_id]),
    ], 409);
  }



  public function showRankingImport(Event $event, Team $team)
  {
    $this->authorize('team.view', $team);

    $rankingLists = $event->series->ranking_lists; // or however you link
    return view('backend.teams.partials.import-ranking', compact('event', 'team', 'rankingLists'));
  }

  public function importFromRanking(Request $request, Event $event, Team $team)
  {
    $this->authorize('team.players.manage', $team);
    app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->assertRosterEditable($team);

    $request->validate([
      'ranking_list_id' => 'required|exists:ranking_lists,id',
    ]);

    $ranking = RankingList::with('players')
      ->findOrFail(14);

    foreach ($ranking->players as $player) {
      $team->players()->syncWithoutDetaching([$player->id => ['pay_status' => 0]]);
    }

    return response()->json(['success' => true]);
  }
  public function toggleNoProfile(Request $request, $id)
  {
    $team = Team::findOrFail($id);
    $this->authorize('team.update', $team);
    app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->assertRosterEditable($team);

    // Flip state (or use passed value)
    $team->noProfile = $request->has('state')
      ? ($request->state == 1 ? 0 : 1)
      : ($team->noProfile == 1 ? 0 : 1);

    $team->save();

    return response()->json([
      'team' => $team,
      'state' => $team->noProfile,
      'html' => $team->noProfile == 1
        ? '<span class="badge bg-label-warning">Disable NoProfile</span>'
        : '<span class="badge bg-label-info">Enable NoProfile</span>'
    ]);
  }
  public function updateNoProfile(Request $request, $id)
  {
    $np = NoProfileTeamPlayer::findOrFail($id);
    abort_if(!$np->team_id, 409, 'Standalone competition identities are immutable. Use an audited replacement instead.');
    $team = $np->team;
    $this->authorize('team.players.manage', $team);
    app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->assertRosterEditable($team);

    $np->update([
      'name' => $request->input('name'),
      'surname' => $request->input('surname'),
    ]);

    return response()->json(['success' => true, 'player' => $np]);
  }



  public function replacePlayer(Request $request)
  {
    $team = Team::findOrFail($request->input('team_id'));
    $this->authorize('team.players.manage', $team);
    return response()->json(['message' => 'Use the replacement wizard to preview affected matches and preserve participant/payment history.', 'url' => route('backend.team-substitutions.show', $team)], 409);
  }

  public function availablePlayers(Request $request)
  {
    $teamId = $request->team_id;
    $team = Team::findOrFail($teamId);
    if ($request->filled('q')) {
      $this->rosterContext($request);
      $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
      $players = Player::query();
      foreach (preg_split('/\s+/u', trim($data['q']), -1, PREG_SPLIT_NO_EMPTY) as $term) {
        $players->where(fn ($query) => $query->where('name', 'like', '%'.$term.'%')->orWhere('surname', 'like', '%'.$term.'%'));
      }

      return response()->json(['players' => $players->orderBy('surname')->limit(20)->get(['id', 'name', 'surname'])->map(fn ($player) => ['id' => $player->id, 'name' => trim($player->name.' '.$player->surname)])]);
    }
    $this->authorize('team.players.manage', $team);

    $assignedPlayerIds = TeamPlayer::where('team_id', $teamId)
      ->where('player_id', '!=', 0)
      ->pluck('player_id');

    return Player::whereNotIn('id', $assignedPlayerIds)
      ->orderBy('surname')
      ->get(['id', 'name', 'surname']);
  }
  public function addPlayers(Request $request)
  {
    [$event, $region, $team] = $this->rosterContext($request);
    $data = $request->validate($team->noProfile
      ? ['name' => ['required', 'string', 'max:100'], 'surname' => ['required', 'string', 'max:100']]
      : ['player_ids' => ['required', 'array', 'min:1', 'max:50'], 'player_ids.*' => ['required', 'integer', 'distinct', 'exists:players,id']]);
    DB::transaction(function () use ($team, $event, $region, $request, $data) {
      foreach ($team->noProfile ? [null] : $data['player_ids'] as $playerId) {
        app(\App\Services\TeamRosterService::class)->change($event, $region, $team, $request->user(), 'add', $data + ['player_id' => $playerId]);
      }
    });

    return response()->json(['success' => true, 'message' => 'Players added.']);
  }

  public function removePlayer(Request $request)
  {
    [$event, $region, $team] = $this->rosterContext($request);
    $data = $request->validate(['slot_id' => ['required', 'integer'], 'expected_rank' => ['required', 'integer', 'min:1'], 'expected_player_id' => ['required', 'integer', 'min:0']]);
    app(\App\Services\TeamRosterService::class)->change($event, $region, $team, $request->user(), 'remove', $data);

    return response()->json(['message' => 'Player removed.']);
  }

  public function editPlayerName(Request $request)
  {
    [$event, $region, $team] = $this->rosterContext($request);
    $data = $request->validate([
      'slot_id' => ['required', 'integer', 'min:1'], 'expected_rank' => ['required', 'integer', 'min:1'],
      'expected_player_id' => ['required', 'integer', 'in:0'],
      'expected_name' => ['required', 'string', 'max:100'], 'expected_surname' => ['required', 'string', 'max:100'],
      'name' => ['required', 'string', 'max:100'], 'surname' => ['required', 'string', 'max:100'],
    ]);
    app(\App\Services\TeamRosterService::class)->editName($event, $region, $team, $request->user(), $data);

    return response()->json(['message' => 'Name corrected.']);
  }

  public function changePlayer(Request $request)
  {
    [$event, $region, $team] = $this->rosterContext($request);
    $data = $request->validate([
      'slot_id' => ['required', 'integer', 'min:1'], 'expected_rank' => ['required', 'integer', 'min:1'],
      'expected_player_id' => ['required', 'integer', 'min:0'],
    ] + ($team->noProfile ? ['name' => ['required', 'string', 'max:100'], 'surname' => ['required', 'string', 'max:100']]
      : ['player_id' => ['required', 'integer', 'exists:players,id']]));
    app(\App\Services\TeamRosterService::class)->replace($event, $region, $team, $request->user(), $data);

    return response()->json(['message' => 'Player changed.']);
  }

  private function rosterContext(Request $request): array
  {
    $data = $request->validate(['team_id' => ['required', 'integer', 'exists:teams,id'], 'event_id' => ['sometimes', 'integer']]);
    $team = Team::findOrFail($data['team_id']);
    $event = $team->category?->event ?? Event::findOrFail($data['event_id'] ?? 0);
    if (isset($data['event_id'])) abort_unless((int) $event->id === (int) $data['event_id'], 404);
    $region = \App\Models\EventRegion::where('event_id', $event->id)->where('region_id', $team->region_id)->first();
    app(\App\Services\TeamRosterService::class)->authorize($event, $region, $team, $request->user());

    return [$event, $region, $team];
  }

  public function editRoster(Request $request)
  {
    $teamId = (int) $request->get('team_id');

    $team = Team::findOrFail($teamId);
    $this->authorize('team.players.manage', $team);

    $slots = TeamPlayer::where('team_id', $teamId)
      ->orderBy('rank')
      ->get(['id', 'rank', 'player_id', 'pay_status'])
      ->map(fn($s) => [
        'id'         => (int) $s->id,
        'rank'       => (int) $s->rank,
        'player_id'  => (int) $s->player_id,
        'pay_status' => (int) $s->pay_status,
      ]);

    $players = Player::orderBy('surname')
      ->get(['id', 'name', 'surname'])
      ->map(fn($p) => [
        'id'      => (int) $p->id,
        'name'    => $p->name,
        'surname' => $p->surname,
      ]);

    return response()->json([
      'team' => [
        'id'   => (int) $team->id,
        'name' => $team->name,
      ],
      'slots'   => $slots,
      'players' => $players,
    ]);
  }

  public function updateRoster(Request $request)
  {
    $data = $request->validate([
      'team_id' => 'required|integer|exists:teams,id',
      'slots' => 'required|array',
      'slots.*' => 'nullable|integer',
      'preserve_payments' => 'nullable|boolean',
    ]);

    $team = Team::findOrFail($data['team_id']);
    $this->authorize('team.players.manage', $team);
    app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->assertRosterEditable($team);

    $teamId = (int) $data['team_id'];
    $conflict = DB::transaction(function () use ($data, $teamId) {
      $slots = TeamPlayer::where('team_id', $teamId)->lockForUpdate()->get()->keyBy('id');
      foreach ($data['slots'] as $slotId => $playerId) {
        $slot = $slots->get((int) $slotId);
        abort_unless($slot, 422, 'A submitted roster slot does not belong to this team.');
        if ((int) $slot->player_id !== (int) $playerId) {
          return true;
        }
      }
      return false;
    });
    if ($conflict) {
      return response()->json([
        'success' => false,
        'message' => 'Use the replacement wizard to preserve participant and payment history.',
        'url' => route('backend.team-substitutions.show', $team),
      ], 409);
    }

    // Return updated roster for frontend refresh
    $updatedSlots = TeamPlayer::where('team_id', $teamId)
      ->orderBy('rank')
      ->with('player')
      ->get()
      ->map(fn($s) => [
        'id' => $s->id,
        'rank' => $s->rank,
        'pay_status' => (int) $s->pay_status,
        'player' => $s->player
          ? [
            'id' => $s->player->id,
            'name' => $s->player->name,
            'surname' => $s->player->surname,
            'email' => $s->player->email,
            'cell' => $s->player->cellNr,
          ]
          : null,
      ]);

    return response()->json([
      'success' => true,
      'slots' => $updatedSlots,
    ]);
  }

  public function replaceForm(Request $request)
  {

    $pivotId = $request->pivot_id;
    $teamId = $request->team_id;
    $rank = $request->rank;

    $slot = TeamPlayer::with(['player'])
      ->findOrFail($pivotId);

    $team = $slot->team;
    $this->authorize('team.players.manage', $team);

    $players = Player::orderBy('name')->get();

    logger()->info('Players loaded for replaceForm', [
      'count' => $players->count(),
      'contains_jean' => $players->contains(
        fn($p) =>
        str_contains(strtolower($p->name), 'jean') &&
        str_contains(strtolower($p->surname), 'joubert')
      )
    ]);

    return view('backend.team.partials.replace-player-form', compact(
      'slot',
      'players',
      'teamId',
      'rank'
    ));
  }
  public function teamHybridComplete(int $orderId)
  {
    Log::info('TEAM WALLET COMPLETE START', [
      'order_id' => $orderId,
      'user_id' => auth()->id()
    ]);

    $user = auth()->user();

    if (!$user) {
      return redirect()->route('events.index')
        ->withErrors('Session expired.');
    }

    $order = \App\Models\TeamPaymentOrder::lockForUpdate()
      ->with('user.wallet')
      ->find($orderId);

    if (!$order) {
      return redirect()->route('events.index')
        ->withErrors('Order not found.');
    }

    // Verify the user owns this order
    if ($order->user_id !== $user->id) {
      abort(403);
    }

    // Verify authorization on the team if needed for event context
    if ($order->team_id) {
      $team = Team::findOrFail($order->team_id);
      $this->authorize('team.players.manage', $team);
    }

    try {
      app(\App\Services\PlayerEligibilityService::class)->assertEligible((int) $order->effective_player_id, (int) $order->event_id);
    } catch (\RuntimeException $exception) {
      return redirect()->route('events.index')->withErrors($exception->getMessage());
    }

    if ($order->pay_status == 1) {
      return redirect()->route('events.success', $order->event_id);
    }

    $order = app(TeamPaymentService::class)->finalizePayment($order, [
      'payment_method' => 'WALLET',
      'wallet_source_type' => 'team_registration_wallet_payment',
      'wallet_meta' => [
        'order_id' => $order->id,
        'reference' => optional($order->event)->name ?? 'Team Registration',
      ],
      'pf_payment_id' => 'wallet-team-order-' . $order->id,
      'payfast_amount_due' => 0,
    ]);

    Log::info('TEAM WALLET COMPLETE SUCCESS', [
      'order_id' => $order->id
    ]);

    $teamWalletEvent = optional($order->event)->name ?? 'Team Event';
    $teamWalletPlayer = \App\Models\Player::find($order->effective_player_id);

    activity('registration')
      ->performedOn($order)
      ->causedBy($user)
      ->withProperties([
        'order_id' => $order->id,
        'event' => $teamWalletEvent,
        'player' => $teamWalletPlayer ? trim($teamWalletPlayer->name . ' ' . $teamWalletPlayer->surname) : '',
        'team_id' => $order->team_id,
        'method' => 'wallet',
        'amount' => $order->wallet_reserved,
      ])
      ->log("Team registration paid via wallet for {$teamWalletEvent}");

    return redirect()->route('events.success', $order->event_id)
      ->with('success', 'Team payment completed using wallet.');
  }
  public function teamHybridReserve(Request $request)
  {
    $order = TeamPaymentOrder::findOrFail($request->order_id);

    // Verify the user is authorized for this team
    if ($order->team_id) {
      $team = Team::findOrFail($order->team_id);
      $this->authorize('team.players.manage', $team);
    }

    try {
      app(\App\Services\PlayerEligibilityService::class)->assertEligible((int) $order->effective_player_id, (int) $order->event_id);
    } catch (\RuntimeException $exception) {
      return back()->withErrors($exception->getMessage());
    }

    $order = app(TeamPaymentService::class)->reservePayment(
      $order,
      (float) $request->wallet_applied,
      (float) $request->remaining_amount
    );

    if ($order->payfast_amount_due <= 0) {
      return redirect()->route('team.hybrid.complete', $order->id);
    }

    return redirect()->route('team.payment.payfast', [
      $order->team_id,
      $order->effective_player_id,
      $order->event_id
    ]);
  }
  public function checkout($orderId)
  {
    $order = \App\Models\TeamPaymentOrder::with('team', 'player', 'event', 'user')
      ->findOrFail($orderId);

    if ((int) $order->user_id !== (int) auth()->id()) {
      abort(403);
    }
    if ($order->withdrawn_at) {
      return redirect()->route('events.show', $order->event)->withErrors(['payment' => 'This checkout was cancelled or restarted. Start registration again from the event.']);
    }

    try {
      app(ExternalTeamRosterService::class)->assertCanRegister(
        auth()->user(), $order->event, $order->team, $order->effectivePlayer
      );
    } catch (\Illuminate\Validation\ValidationException $exception) {
      return redirect()->back()->withErrors($exception->errors());
    } catch (\RuntimeException $exception) {
      return redirect()->back()->withErrors($exception->getMessage());
    }

    $user = auth()->user();
    $total = round((float) $order->total_amount, 2);
    $walletReserved = round((float) $order->wallet_reserved, 2);

    return $this->renderTeamPaymentCheckout(
      $order, $user, $order->team, $order->effectivePlayer, $order->event,
      $total, max(0, round($total - (float) $order->event->entryFee, 2)),
      round((float) ($user->wallet->balance ?? 0), 2), $walletReserved,
      max(0, round((float) $order->payfast_amount_due, 2)), false
    );
  }
  public function teamHybridPay(Request $request)
  {
    $orderId = (int) $request->custom_int5;
    $walletApplied = (float) $request->wallet_applied;
    $remaining = (float) $request->remaining_amount;

    $user = auth()->user();
    $wallet = $user?->wallet;

    if (!$user || !$wallet) {
      return back()->withErrors('Wallet not found.');
    }

    $order = \App\Models\TeamPaymentOrder::findOrFail($orderId);

    // Verify the user is authorized for this team
    if ($order->team_id) {
      $team = Team::findOrFail($order->team_id);
      $this->authorize('team.players.manage', $team);
    }

    try {
      app(\App\Services\PlayerEligibilityService::class)->assertEligible((int) $order->effective_player_id, (int) $order->event_id);
    } catch (\RuntimeException $exception) {
      return back()->withErrors($exception->getMessage());
    }

    if ($order->pay_status == 1 || $order->payfast_paid) {
      return back()->with('success', 'Order already paid.');
    }

    $order = app(TeamPaymentService::class)->reservePayment($order, round($walletApplied, 2), round($remaining, 2));

    if ($remaining <= 0) {
      return redirect()->route('team.hybrid.complete', $orderId);
    }

    // Send to PayFast
    $payfast = new \App\Services\Payfast();
    $payfast->setMode($user->id == 584 ? 0 : 1);

    return view('frontend.payfast.pay_now', [
      'payfast' => $payfast,
      'amount' => $remaining,
      'orderId' => $orderId,
      'custom_wallet_reserved' => $walletApplied,
      'return_url' => route('team.checkout', ['order' => $orderId, 'type' => 'team']),
      'cancel_url' => route('team.hybrid.cancel', $orderId),
      'notify_url' => route('notify.team'),
    ]);
  }

}
