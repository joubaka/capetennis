<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\ExersizeName;
use App\Models\ExersizeType;
use App\Models\Fixture;
use App\Models\FixturePlayer;
use App\Models\Goal;
use App\Models\GoalTheme;
use App\Models\GoalType;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\PracticeDuration;
use App\Models\PracticeFixtures;
use App\Models\PracticeType;
use App\Models\TeamFixture;
use App\Models\TeamFixturePlayer;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Models\UserPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SebastianBergmann\Timer\Duration;

class PlayerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $players = Player::all();
        return ['data' => $players];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
  public function create(Request $request)
  {
    $data = $request->all();

    if ($request->type === 'noProfile' && $request->filled('noProfile')) {
      $noProfile = \App\Models\NoProfileTeamPlayer::find($request->noProfile);

      if ($noProfile) {
        $data['name'] = $noProfile->name ?? '';
        $data['surname'] = $noProfile->surname ?? '';
        $data['noProfileId'] = $noProfile->id;
      }
    }

    return view('frontend.player.create-player', $data);
  }



  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request)
  {
    // Create the Player
    $player = new Player();
    $player->name = $request->player_name;
    $player->surname = $request->player_surname;
    $player->dateOfBirth = $request->dob;
    $player->gender = $request->gender;
    $player->userId = Auth::id();
    $player->cellNr = $request->cell_nr;
    $player->email = $request->email;
    $player->save();

    $notification = [
      'message' => 'Player added successfully',
      'alert-type' => 'info'
    ];

    // If this was triggered from a noProfile placeholder
    if ($request->input('type') === 'noProfile' && $request->filled('noProfile')) {
      $noProfile = NoProfileTeamPlayer::find($request->noProfile);

      if ($noProfile) {
        // Link the new Player to this placeholder
        $noProfile->player_profile = $player->id;
        $noProfile->save();

        // Also update the real TeamPlayer slot if you keep a separate table
        $teamPlayer = TeamPlayer::where('team_id', $request->team)
          ->where('rank', $noProfile->rank)
          ->first();

        if ($teamPlayer) {
          $teamPlayer->player_id = $player->id;
          $teamPlayer->save();
        }
      }

      // Redirect to payment for this player
      return redirect()->route('team.payment.payfast', [
        $request->team,
        $player->id,
        $request->event
      ])->with($notification);
    }else{
      return $player;
    }

    
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
        $data['player'] = Player::find($id);
        return view('backend.player.edit-player', $data);
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
    $player = Player::findOrFail($id);

    // ✅ AJAX update from modal
    if ($request->ajax()) {
      $validated = $request->validate([
        'name' => 'required|string|max:255',
        'surname' => 'required|string|max:255',
        'email' => 'nullable|email|max:255',
        'cell_nr' => 'nullable|string|max:50',
      ]);

      $player->fill([
        'name' => $validated['name'],
        'surname' => $validated['surname'],
        'email' => $validated['email'] ?? $player->email,
        'cellNr' => $validated['cell_nr'] ?? $player->cellNr,
      ])->save();

      return response()->json([
        'success' => true,
        'message' => 'Player updated successfully',
        'player' => $player
      ]);
    }

    // ✅ Regular HTTP update (e.g. from backend edit page)
    $validated = $request->validate([
      'player_name' => 'required|string|max:255',
      'player_surname' => 'required|string|max:255',
      'email' => 'nullable|email|max:255',
      'dob' => 'nullable|date',
      'cell_nr' => 'nullable|string|max:50',
      'gender' => 'nullable|string|max:10',
      'coach' => 'nullable|string|max:255',
    ]);

    $player->update([
      'name' => $validated['player_name'],
      'surname' => $validated['player_surname'],
      'email' => $validated['email'] ?? $player->email,
      'dateOfBirth' => $validated['dob'] ?? $player->dateOfBirth,
      'cellNr' => $validated['cell_nr'] ?? $player->cellNr,
      'gender' => $validated['gender'] ?? $player->gender,
      'coach' => $validated['coach'] ?? $player->coach,
    ]);

    return redirect()->route('backend.player.profile', $player->id)
      ->with('success', 'Player updated successfully.');
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
    public function details($id)
    {
        return view('backend.player.player_details');
    }
    public function profile($id)
    {
        $player = Player::find($id);
        $exersize_types = ExersizeType::all();
        $practice_types = PracticeType::all();
        $physical_exersizes  = ExersizeName::where('exersize_type_id', 1)->get();
        $durations = PracticeDuration::all();
        $general_goal_types = Goal::where('player_id', $id)
            ->whereHas('names', function ($item) {
                return $item->where('goal_type_id', '<', 15);
            })
            ->get();
        $career_goal_types = Goal::where('player_id', $id)
            ->whereHas('names', function ($item) {
                return $item->where('goal_type_id', '>', 14);
            })
            ->get();


        $goal_types = GoalType::all();
        $goal_themes = GoalTheme::all();

        $setsplayed = PracticeFixtures::with('results')

            ->withCount('results')
            ->whereHas('practice', function ($q) use ($id) {
                $q->where('player_id', '=', $id);
            })
            ->get();

        // $matches = $setsplayed->practiceMatches()->count();
        $totsets = $setsplayed->sum('results_count');
        $setswon = PracticeFixtures::with('results')



            ->whereHas('results', function ($q) use ($id) {
                $q->where('winner_registration', '=', $id);
            })
            ->get();


        $setslost = PracticeFixtures::with('results')

            ->withCount('results')
            ->whereHas('practice', function ($q) use ($id) {
                $q->where('player_id', '=', $id);
            })
            ->whereHas('results', function ($q) use ($id) {
                $q->where('loser_registration', '=', $id);
            })
            ->get();
        $players = Player::all();
        $u = Auth::user();

        return view('backend.player.player_profile', compact('u', 'goal_themes', 'goal_types', 'setslost', 'setswon', 'totsets', 'players', 'physical_exersizes', 'practice_types', 'durations', 'player', 'general_goal_types', 'career_goal_types', 'exersize_types'));
    }
    public function results($id)
    {
        $data['results'] = $this->playerEventResults($id);
        $data['player'] = Player::find($id);
        return view('backend.player.player_results',$data);
    }

    function removeProfileFromUser($id)
    {
        UserPlayer::where('id', $id)->delete();
        return 'Profile Removed';
    }

    function addToProfile(Request $request)
    {
        $user = User::find($request->user_id);
        $user->players()->syncWithoutDetaching($request->player_id);
        return $user;
    }

    public function playerEventResults($id)
    {

      
        $teamFixtures = TeamFixturePlayer::where('team1_id', $id)
            ->orWhere('team2_id', $id)
            ->with('fixture')
            ->whereHas('fixture.teamResults')
            ->get();
      
       $t = $teamFixtures->filter(function ($item) {
         
            if( isset($item->fixture->fixture_type)){
               
                 return $item->fixture->fixture_type == 1 ||  $item->fixture->fixture_type == 4 ;
            }
            
        }); 

        $data = [];
        foreach ($t->all() as $key => $value) {
            if (isset($value->fixture)) {
                if (isset($value->fixture->draw)) {
                    $data['event'][] = $value->fixture->draw->events->name;
                    $data['matchup'][] = $value->team1->getFullNameAttribute() . ' vs ' . $value->team2->getFullNameAttribute();
                    // $data['data'][] = $value->fixture->teamResults;
                    $data['data'][$key][] = $value->fixture->id . ' ' . $value->team1->getFullNameAttribute() . ' vs ' . $value->team2->getFullNameAttribute() . ' :' . $value->fixture->draw->events->name . ' ' . $value->fixture->draw->events->start_date;
                    $data['data'][$key]['results'] = $value->fixture->teamResults;
                    $data['fixture'][] = $value;
        
                }
            }
        }

       return $data;
    }

  public function search(Request $request)
  {
    $q = trim($request->get('q', ''));

    $players = Player::query()
      ->when($q, function ($query) use ($q) {
        // Split into words: "John Smith" => ["John", "Smith"]
        $terms = preg_split('/\s+/', $q);

        foreach ($terms as $term) {
          $query->where(function ($sub) use ($term) {
            $sub->where('name', 'like', "%{$term}%")
              ->orWhere('surname', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
          });
        }
      })
      ->limit(10)
      ->get(['id', 'name', 'surname', 'email']);

    return response()->json($players);
  }



  public function attach(Request $request, User $user)
  {
    $request->validate([
      'player_id' => 'required|exists:players,id',
    ]);

    // prevent duplicates
    if ($user->players()->where('player_id', $request->player_id)->exists()) {
      return response()->json([
        'success' => false,
        'message' => 'Player already linked'
      ], 422);
    }

    $user->players()->attach($request->player_id);

    return response()->json([
      'success' => true,
      'message' => 'Player linked successfully'
    ]);
  }

  public function detach($pivotId)
  {
    UserPlayer::where('id', $pivotId)->delete();

    return response()->json([
      'success' => true,
      'message' => 'Player unlinked'
    ]);
  }

  public function attachNoProfile(Request $request)
  {
    $playerId = $request->player_id;
    $teamId = $request->team;
    $eventId = $request->event;
    $noProfileId = $request->noProfile;

    $player = Player::findOrFail($playerId);
    $noProfile = NoProfileTeamPlayer::findOrFail($noProfileId);

    // Update the team player slot
    $teamPlayer = TeamPlayer::where('team_id', $teamId)
      ->where('rank', $noProfile->rank)
      ->first();

    if ($teamPlayer) {
      $teamPlayer->player_id = $player->id;
      $teamPlayer->save();
    }

    // ✅ Link noProfile to the real Player profile
    $noProfile->player_profile = $player->id;
    $noProfile->save();

    // Redirect to payment
    return redirect()->route('team.payment.payfast', [$teamId, $player->id, $eventId])
      ->with('message', 'Player attached successfully');
  }

}
