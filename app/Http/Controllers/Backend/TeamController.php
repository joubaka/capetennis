<?php

namespace App\Http\Controllers\Backend;

use App\Classes\Payfast;
use App\Http\Controllers\Controller;
use App\Imports\ImportUsers;
use App\Imports\UsersImport;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamCategory;
use App\Models\TeamPlayer;
use App\Models\NoProfileTeamPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\CssSelector\Node\FunctionNode;
use App\Services\FixtureService;
use App\Imports\NoProfileTeamImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;


class TeamController extends Controller
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
        $team = new Team();
        $team->name = $request->team_name;
        $team->year = $request->year;
        $team->published = $request->published;
        $team->region_id = $request->region_id;
        $team->num_team_members = $request->num_players;

        $team->save();
        $num_players_in_team = $request->num_players;

        for ($i = 0; $i < $num_players_in_team; $i++) {
            $teamplayer = new TeamPlayer();
            $teamplayer->team_id = $team->id;
            $teamplayer->player_id = 1248;
            $teamplayer->rank = ($i + 1);
            $teamplayer->pay_status = 0;
            $teamplayer->save();
        }

        return $team; //
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

        Team::where('id', $id)->delete();
        return 'deleted';
    }

  public function insertPlayer(Request $request)
  {
    $teamplayer = TeamPlayer::findOrFail($request->pivot);
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
    $order = $request->input('order', []);
    $updated = collect();

    foreach ($order as $item) {
      if ($item['type'] === 'profile') {
        $tp = TeamPlayer::with('player')->find($item['id']);
        if ($tp) {
          $tp->update(['rank' => $item['position']]);
          $tp->setAttribute('type', 'profile');
          $updated->push($tp);
        }
      } elseif ($item['type'] === 'noprofile') {
        $np = NoProfileTeamPlayer::find($item['id']);
        if ($np) {
          $np->update(['rank' => $item['position']]);
          $np->setAttribute('type', 'noprofile');
          $updated->push($np);
        }
      }
    }

    // ✅ Always return sorted by rank and with consistent structure
    $sorted = $updated->sortBy('rank')->values();

    $players = $sorted->map(function ($p) {
      return [
        'id' => $p->id,
        'type' => $p->type,
        'rank' => $p->rank,
        'pay_status' => $p->pay_status ?? 0,
        'player' => $p->relationLoaded('player') ? $p->player : null,
        'name' => $p->name ?? null,
        'surname' => $p->surname ?? null,
        'email' => $p->email ?? null,
        'cellNr' => $p->cellNr ?? null,
      ];
    });

    return response()->json([
      'status' => 'ok',
      'team_id' => $request->input('team_id'),
      'players' => $players,
    ]);
  }


  public function publishTeam($id)
    {
        $team = Team::find($id);
        if ($team->published == 1) {
            $team->published = 0;
        } else {
            $team->published = 1;
        }
        $team->save();
        return $team;
    }

  public function team_payment_payfast($team, $player, $event)
  {
    $data['player'] = Player::findOrFail($player);
    $data['team'] = Team::with('regions')->findOrFail($team);
    $data['user'] = Auth::user();
    $data['event'] = Event::findOrFail($event);
    $data['category'] = ['id' => 0, 'name' => 'Team'];

    $payfast = new Payfast();

    // 🔹 Mode handling
    // 0 = sandbox | 1 = live | 2 = forced test
    if (Auth::id() == 584) {

      $payfast->setMode(0); // sandbox
    } else {
 $payfast->setMode(1); // live
     
    }

    // 🔹 Collect fees
    $eventFee = (float) ($data['event']->entryFee ?? 0);

    $regionFee = (
      $data['team']->regions &&
      (float) $data['team']->regions->region_fee > 0
    )
      ? (float) $data['team']->regions->region_fee
      : 0;

    // 🔹 Total for PayFast
    $total = $eventFee + $regionFee;

    // 🔹 PayFast REQUIRED domain setters
    $payfast->setEvent($data['event']);          // item_name + custom_int3
    $payfast->setAmount($total);                 // amount
    $payfast->setPayer($data['user']);           // custom_str4
    $payfast->setPlayerInfo($data['player']);    // custom_int2
    $payfast->setCategoryEventId($data['category']['id']);

    // 🔥 CRITICAL FOR TEAM PAYMENTS (FIX)
    $payfast->custom_int1 = $data['team']->id;   // team_id
    $payfast->custom_int4 = $data['user']->id;   // user_id

    // 🔹 Pass values to view
    $data['eventFee'] = $eventFee;
    $data['regionFee'] = $regionFee;
    $data['total'] = $total;
    $data['payfast'] = $payfast;
  
    return view('frontend.payfast.team_payment', $data);
  }

  public function changeCategory(Request $request, $id)
    {

        $eventCategory = CategoryEvent::find($request->data);

        $team = Team::find($request->team);

        $team->category_event_id = $eventCategory->id;

        $team->save();

        return $team->category->category->name;
    }

    public function importView()
    {

        return view('backend.import.noProfileImport');
    }



  public function importNoProfile(Request $request)
  {
    $request->validate([
      'file' => 'required|file|mimes:xlsx,xls',
    ]);

    $import = new \App\Imports\NoProfileTeamImport();

    \Maatwebsite\Excel\Facades\Excel::import(
      $import,
      $request->file('file')
    );

    return response()->json([
      'success' => true,
      'team_ids' => $import->getImportedTeamIds(),
    ]);
  }

  public function teamPlayersTable($teamId)
  {
    $team = Team::with([
      'players' => fn($q) => $q->withPivot('rank', 'pay_status')->orderBy('team_players.rank'),
      'team_players_no_profile' => fn($q) => $q->orderBy('rank'),
    ])->findOrFail($teamId);

    return view('backend.adminPage.partials.team_players_table', compact('team'));
  }






  public function downloadTemplate()
  {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Header row
    $sheet->setCellValue('A1', 'Group');
    $sheet->setCellValue('B1', 'Rank');
    $sheet->setCellValue('C1', 'Name');
    $sheet->setCellValue('D1', 'Surname');
    $sheet->setCellValue('E1', 'PayStatus');
    $sheet->setCellValue('F1', 'TeamID');

    // Example row
    $sheet->setCellValue('A2', 'Dogters o/15');
    $sheet->setCellValue('B2', '1');
    $sheet->setCellValue('C2', 'Lisa');
    $sheet->setCellValue('D2', 'van Zyl');
    $sheet->setCellValue('E2', '0');
    $sheet->setCellValue('F2', '721');

    // Autosize columns
    foreach (range('A', 'F') as $col) {
      $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Stream to browser
    $writer = new Xlsx($spreadsheet);
    $response = new StreamedResponse(function () use ($writer) {
      $writer->save('php://output');
    });

    $fileName = 'no_profile_team_import_template.xlsx';
    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->headers->set('Content-Disposition', 'attachment;filename="' . $fileName . '"');
    $response->headers->set('Cache-Control', 'max-age=0');

    return $response;
  }



  public function changePayStatus(Request $request)
  {
    $teamplayer = TeamPlayer::find($request->pivot_id);

    if (!$teamplayer) {
      return response()->json([
        'success' => false,
        'message' => 'Player not found'
      ], 404);
    }

    $teamplayer->pay_status = $teamplayer->pay_status ? 0 : 1;
    $teamplayer->save();

    return response()->json([
      'success' => true,
      'pay_status' => $teamplayer->pay_status,
      'message' => $teamplayer->pay_status
        ? 'Marked as Paid'
        : 'Marked as Not Paid',
    ]);
  }



  public function showRankingImport(Event $event, Team $team)
  {
    $rankingLists = $event->series->ranking_lists; // or however you link
    return view('backend.teams.partials.import-ranking', compact('event', 'team', 'rankingLists'));
  }

  public function importFromRanking(Request $request, Event $event, Team $team)
  {
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
    $np = NoProfileTeamPlayer::find($id);
    if (!$np) {
      return response()->json(['success' => false, 'message' => 'No-profile player not found'], 404);
    }

    $np->update([
      'name' => $request->input('name'),
      'surname' => $request->input('surname'),
    ]);

    return response()->json(['success' => true, 'player' => $np]);
  }



  public function replacePlayer(Request $request)
  {
    $validated = $request->validate([
      'pivot_id' => 'required|integer|exists:team_players,id',
      'player_id' => 'required|integer|exists:players,id',
      'team_id' => 'required|integer|exists:teams,id',
    ]);

    DB::beginTransaction();
    
    try {
      $slot = \App\Models\TeamPlayer::with('player')
        ->where('id', $validated['pivot_id'])
        ->where('team_id', $validated['team_id'])
        ->firstOrFail();

      // 🔹 Replace player in SAME slot
      $slot->update([
        'player_id' => $validated['player_id'],
        'no_profile_id' => null,
        'pay_status' => null,
      ]);

      $player = \App\Models\Player::findOrFail($validated['player_id']);

      DB::commit();

      return response()->json([
        'success' => true,
        'message' => 'Player replaced successfully',
        'slot' => [
          'pivot_id' => $slot->id,
          'rank' => $slot->rank,
          'pay_status' => (int) $slot->pay_status,
          'player' => [
            'name' => $player->name,
            'surname' => $player->surname,
            'email' => $player->email,
            'cell' => $player->cellNr,
          ],
        ],
      ]);
    } catch (\Throwable $e) {
      DB::rollBack();

      \Log::error('Replace player failed', [
        'error' => $e->getMessage(),
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Failed to replace player',
      ], 500);
    }
  }

  public function availablePlayers(Request $request)
  {
    $teamId = $request->team_id;

    $assignedPlayerIds = TeamPlayer::where('team_id', $teamId)
      ->where('player_id', '!=', 0)
      ->pluck('player_id');

    return Player::whereNotIn('id', $assignedPlayerIds)
      ->orderBy('surname')
      ->get(['id', 'name', 'surname']);
  }
  public function addPlayers(Request $request)
  {
    $data = $request->validate([
      'team_id' => 'required|integer|exists:teams,id',
      'player_ids' => 'required|array',
      'player_ids.*' => 'exists:players,id',
    ]);

    $nextRank = TeamPlayer::where('team_id', $data['team_id'])->max('rank') ?? 0;

    foreach ($data['player_ids'] as $playerId) {
      TeamPlayer::create([
        'team_id' => $data['team_id'],
        'player_id' => $playerId,
        'rank' => ++$nextRank,
        'pay_status' => 0,
      ]);
    }

    return response()->json(['success' => true]);
  }



  public function editRoster(Request $request)
  {
    $teamId = (int) $request->get('team_id');

    $team = Team::findOrFail($teamId);

    $slots = TeamPlayer::where('team_id', $teamId)
      ->orderBy('rank')
      ->get([
        'id',
        'rank',
        'player_id',
        'pay_status',
      ]);

    $players = Player::orderBy('surname')
      ->get([
        'id',
        'name',
        'surname',
      ]);

    return response()->json([
      'team' => [
        'id' => $team->id,
        'name' => $team->name,
      ],
      'slots' => $slots,
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

    $teamId = (int) $data['team_id'];
    $preservePayments = (bool) ($data['preserve_payments'] ?? false);

    // Only slots belonging to this team
    $validSlots = TeamPlayer::where('team_id', $teamId)
      ->pluck('id')
      ->all();

    DB::transaction(function () use ($data, $validSlots, $preservePayments) {

      foreach ($data['slots'] as $slotId => $playerId) {

        $slotId = (int) $slotId;
        $playerId = (int) $playerId;

        if (!in_array($slotId, $validSlots, true)) {
          continue;
        }

        // Allow dummy (0) OR valid player
        if ($playerId !== 0 && !Player::whereKey($playerId)->exists()) {
          continue;
        }

        TeamPlayer::whereKey($slotId)->update([
          'player_id' => $playerId,
         // 'no_profile_id' => null,
          'pay_status' => $preservePayments ? DB::raw('pay_status') : 0,
        ]);
      }
    });

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

}
