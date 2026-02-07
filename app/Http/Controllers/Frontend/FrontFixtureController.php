<?php

namespace App\Http\Controllers\Frontend;

use App\Classes\CapeTennisDraw;
use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\Player;
use App\Models\TeamFixture;
use App\Models\Fixture;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\TeamFixtureResult;
use App\Services\InterproDrawBuilder;


class FrontFixtureController extends Controller
{
  protected InterproDrawBuilder $builder;

  public function __construct(InterproDrawBuilder $builder)
  {
    $this->builder = $builder;
  }
  public function show($id)
  {
 $draw = Draw::with('event')->findOrFail($id);
 
    if ($draw->event?->eventType == 13) {

     
   
return $this->showInterproFixtures($draw);
    }

    
      return $this->showTeamFixtures($draw);
  }

  protected function showTeamFixtures(Draw $draw)
  {
    $fixtures = TeamFixture::with([
      'team1',
      'team2',
      'fixtureResults',
      'venue',
      'region1Name',
      'region2Name'
    ])
      ->where('draw_id', $draw->id)
      ->orderBy('scheduled_at', 'asc')
      ->orderByRaw('CAST(round_nr AS UNSIGNED)')
      ->orderByRaw('CAST(tie_nr AS UNSIGNED)')
      ->orderByRaw('CAST(home_rank_nr AS UNSIGNED)')
      ->orderBy('match_nr')
      ->get();

    if ($fixtures->isEmpty()) {
      abort(404, 'No fixtures found for this draw.');
    }

    $data = [
      'fixtures' => $fixtures,
      'draw' => $draw,
      'event' => $draw->event,
    ];

    // Admin / Convenor
    if (auth()->check() && auth()->user()->is_convenor($draw->event_id)) {
      $data['players'] = Player::orderBy('name')->get();
      return view('backend.draw.team.draw-show-team', $data);
    }

    // Frontend
    return view('frontend.fixture.draw-fixtures-show-team', $data);
  }


  public function showInterproFixtures(Draw $draw)
  {
    // ---------------------------------------------
    // LOAD FIXTURES
    // ---------------------------------------------
    $fixtures = Fixture::with([
      'registration1.players',
      'registration2.players',
      'fixtureResults',
      'venue',
      'orderOfPlay'
    ])
      ->where('draw_id', $draw->id)
      ->orderByRaw('CAST(match_nr AS UNSIGNED)')
      ->get();

    if ($fixtures->isEmpty()) {
      abort(404, 'No fixtures found for this draw.');
    }

    // ---------------------------------------------
    // LOAD HUB (fixtures + OOP + standings)
    // ---------------------------------------------
    $hub = $this->builder->loadRoundRobinHub($draw);

    // ---------------------------------------------
    // GROUPS JSON — MUST MATCH ADMIN
    // ---------------------------------------------
    $groupsJson = $draw->groups
      ->map(function ($g) {
        return [
          'id' => $g->id,
          'name' => $g->name,

          'registrations' => $g->groupRegistrations
            ->map(function ($gr) {
              $reg = $gr->registration;
              $player = $reg?->players?->first();

              return [
                'id' => $reg?->id,
                'display_name' => $player?->full_name ?? 'Unknown',
                'seed' => $gr->seed ?? 9999,
              ];
            })
            ->values(),
        ];
      })
      ->values();

    // ---------------------------------------------
    // VIEW OUTPUT
    // ---------------------------------------------
    $data = [
      'fixtures' => $fixtures,
      'draw' => $draw,
      'event' => $draw->event,

      // ✔ RR Data for JS
      'rrFixtures' => $hub['rrFixtures'],
      'groupsJson' => $groupsJson,   // ✔ FIXED (correct variable)
      'oops' => $hub['oops'],
      'standings' => $hub['standings'],
    ];

    // Convenor sees backend version
    if (auth()->check() && auth()->user()->is_convenor($draw->event_id)) {
      return view('backend.draw.individual.interproDrawConvenor', $data);
    }

    // Public view
    return view('backend.draw.individual.interproDraw', $data);
  }






  public function drawFixtures($id)
  {
    // Load draw + event
    $draw = Draw::with('event')->findOrFail($id);

    // Detect team vs individual
    $isTeamEvent = ($draw->event?->eventType == 3);

    // ---------------------------------------------------------
    // FIXTURE LOADERS
    // ---------------------------------------------------------
    if ($isTeamEvent) {

      // TEAM FIXTURES
      $fixtures = TeamFixture::with([
        'team1',
        'team2',
        'fixtureResults',
        'venue',
        'region1Name',
        'region2Name'
      ])
        ->where('draw_id', $id)
        ->orderBy('scheduled_at', 'asc')
        ->orderByRaw('CAST(round_nr AS UNSIGNED)')
        ->orderByRaw('CAST(tie_nr AS UNSIGNED)')
        ->orderByRaw('CAST(home_rank_nr AS UNSIGNED)')
        ->orderBy('match_nr')
        ->get();

    } else {

      // INDIVIDUAL FIXTURES
      $fixtures = Fixture::with([
        'registration1.players',
        'registration2.players',
        'fixtureResults',
        'venue',
        'orderOfPlay'
      ])
        ->where('draw_id', $id)
        ->orderByRaw('CAST(match_nr AS UNSIGNED)')
        ->get();

    }
    
    // ---------------------------------------------------------
    // Empty fixtures
    // ---------------------------------------------------------
    if ($fixtures->isEmpty()) {
      abort(404, 'No fixtures found for this draw.');
    }

    // ---------------------------------------------------------
    // Data
    // ---------------------------------------------------------
    $data = [
      'fixtures' => $fixtures,
      'draw' => $draw,
      'event' => $draw->event,
    ];

    // ---------------------------------------------------------
    // ADMIN / CONVENOR VIEW
    // ---------------------------------------------------------
    if (Auth::check()) {
      $user = auth()->user();
      $eventId = $draw->event?->id;

      if ($eventId && $user->is_convenor($eventId)) {

        if ($isTeamEvent) {
          $data['players'] = Player::orderBy('name')->get();
          return view('backend.draw.team.draw-show-team', $data);
        }

        // Individual admin view
        return view('backend.draw.individual.draw-show-individual', $data);
      }
    }

    // ---------------------------------------------------------
    // FRONTEND VIEWS
    // ---------------------------------------------------------
    if ($isTeamEvent) {
      return view('frontend.fixture.draw-fixtures-show-team', $data);
    }

    return view('frontend.fixture.draw-fixtures-show', $data);
  }

  public function drawFixturesRound($event, $var, $type)
    {

        $eventDraws = Draw::where('event_id', $event)->get()->pluck('id');
        if ($type == 'tie') {
            $data['fixtures'] = TeamFixture::whereIn('draw_id', $eventDraws)
                ->where('tie_nr', $var)->get();
        } else {
            $data['fixtures'] = TeamFixture::whereIn('draw_id', $eventDraws)
                ->where('round_nr', $var)->get();
        }



        if (Auth::check()) {

            $user = User::find(auth()->user()->id);

            if (count($user->is_convenor($data['fixtures'][0]->draw->events->id)) > 0) {

                $data['players'] = Player::all();


                return view('backend.draw.team.draw-show-team', $data);
            } else {

                $data['players'] = Player::all();
                return view('frontend.fixture.draw-fixtures-show', $data);
            }
        } else {
            $data['players'] = Player::all();


            return view('frontend.fixture.draw-fixtures-show', $data);
        }
    }

    public function bracketFixtures($id){
        $data['draw'] = Draw::find($id);
$data['event'] =Draw::find($id)->events;
$data['bracket'] = new CapeTennisDraw($id);
//dd($data);
        return view('frontend.draw.fixtures.showFixtures',$data);
           
    }

  public function saveScore(Request $request, TeamFixture $fixture)
  {
    $rules = [];
    for ($i = 1; $i <= 3; $i++) {
      $rules["set{$i}_home"] = 'nullable|integer|min:0';
      $rules["set{$i}_away"] = 'nullable|integer|min:0';
    }

    $validated = $request->validate($rules);

    foreach (range(1, 3) as $i) {
      $home = $validated["set{$i}_home"] ?? null;
      $away = $validated["set{$i}_away"] ?? null;

      if ($home !== null || $away !== null) {
        $winnerId = null;
        $loserId = null;

        if ($home > $away) {
          $winnerId = 1; // home team
          $loserId = 2;
        } elseif ($away > $home) {
          $winnerId = 2; // away team
          $loserId = 1;
        }

        TeamFixtureResult::updateOrCreate(
          ['team_fixture_id' => $fixture->id, 'set_nr' => $i],
          [
            'team1_score' => $home,
            'team2_score' => $away,
            'match_winner_id' => $winnerId,
            'match_loser_id' => $loserId,
          ]
        );
      } else {
        TeamFixtureResult::where('team_fixture_id', $fixture->id)
          ->where('set_nr', $i)
          ->delete();
      }
    }

    if ($request->ajax()) {
      $fixture->load('fixtureResults');
      $lastSet = $fixture->fixtureResults->last();
      $winner = null;

      if ($lastSet) {
        if ($lastSet->team1_score > $lastSet->team2_score) {
          $winner = 'home';
        } elseif ($lastSet->team2_score > $lastSet->team1_score) {
          $winner = 'away';
        } else {
          $winner = 'draw';
        }
      }

      return response()->json([
        'success' => true,
        // this partial should render the scores in <td>
        'html' => view('frontend.fixture.partials.result-col', compact('fixture'))->render(),
        'winner' => $winner,
        'scores' => $fixture->fixtureResults->mapWithKeys(function ($r) {
          return [
            "set{$r->set_nr}_home" => $r->team1_score,
            "set{$r->set_nr}_away" => $r->team2_score,
          ];
        }),
      ]);
    }

    return redirect()
      ->back()
      ->with('success', 'Scores updated successfully.');
  }




}
