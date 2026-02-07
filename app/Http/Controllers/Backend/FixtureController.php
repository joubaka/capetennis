<?php

namespace App\Http\Controllers\backend;

use App\Classes\CapeTennisDraw;
use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\DrawVenue;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\OrderOfPlay;
use App\Models\Player;
use App\Models\Result;
use App\Models\Team;
use App\Models\TeamFixture;
use App\Models\TeamFixturePlayer;
use App\Models\TeamFixtureResult;
use App\Models\TeamPlayer;
use App\Models\Venues;
use App\Services\DrawBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf;
use PhpParser\Builder\Property;

use function PHPUnit\Framework\isNull;

class FixtureController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function updatePlayersNames(Request $request, $id)
  {
    $draw = Draw::find($id);
    $feedback = [];

    foreach ($draw->fixtures as $fixture) {
      // Skip if both teams have no profile
      if ($fixture->region2Name->no_profile == 1 && $fixture->region1Name->no_profile == 1) {
        continue;
      }

      // Determine which team needs updating
      $team1First = null;
      $team2First = null;

      if ($fixture->region2Name->no_profile == 1) {
        $team1First = Team::where('region_id', $fixture->region1)
          ->where('name', $fixture->age)
          ->with('team_players') // Eager load players
          ->first();
      } elseif ($fixture->region1Name->no_profile == 1) {
        $team2First = Team::where('region_id', $fixture->region2)
          ->where('name', $fixture->age)
          ->with('team_players') // Eager load players
          ->first();
      }

      // Ensure valid team data exists
      if (!$team1First && !$team2First) {
        continue;
      }

      // Fetch all team fixture players in one query
      $teamFixturePlayers = TeamFixturePlayer::where('team_fixture_id', $fixture->id)->get();

      // Player index to ensure unique IDs
      $playerIndex = 0;

      foreach ($teamFixturePlayers as $player) {
        $updater = TeamFixturePlayer::find($player->id);

        // Assign unique players for team1
        if ($team1First && isset($team1First->team_players[$playerIndex])) {
          $updater->team1_id = $team1First->team_players[$playerIndex]->player_id;
        }

        // Assign unique players for team2
        if ($team2First && isset($team2First->team_players[$playerIndex])) {
          $updater->team2_id = $team2First->team_players[$playerIndex]->player_id;
        }

        if ($updater->save()) {
          $feedback[] = [
            'fixture_id' => $fixture->id,
            'team_fixture_player_id' => $player->id,
            'team1_id' => $updater->team1_id ?? null,
            'team2_id' => $updater->team2_id ?? null,
          ];
        }

        $playerIndex++; // Increment to ensure unique player selection
      }
    }

    return response()->json(['message' => 'Players updated successfully', 'data' => $feedback]);
  }




  public function index(Request $request)
  {
    if ($request->draw) {
      if ($request->value == 1) {
        //return TeamFixture::where('draw_id', $request->draw)->get();

        return TeamFixture::where('draw_id', $request->draw)
          ->with('region2name')
          ->with('region1name')
          ->get();
      } elseif ($request->value == 2) {
        return TeamFixture::where('draw_id', $request->draw)
          ->with('region2name')
          ->with('region1name')
          ->get()
          ->groupBy('tie_nr');
      }
    } else {
      return TeamFixture::all();
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
   * @param \Illuminate\Http\Request $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request)
  {
    //
  }

  /**
   * Display the specified resource.
   *
   * @param int $id
   * @return \Illuminate\Http\Response
   */
  public function show($id)
  {
    //
  }

  /**
   * Show the form for editing the specified resource.
   *
   * @param int $id
   * @return \Illuminate\Http\Response
   */
  public function edit($id)
  {
    //
  }

  /**
   * Update the specified resource in storage.
   *
   * @param \Illuminate\Http\Request $request
   * @param int $id
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, $id)
  {
    //
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param int $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id)
  {
    //
  }

  public function insertResult(Request $request)
  {

    $responce = null;
    if ($request->type == 'team') {
      $fixture = TeamFixture::find($request->fixture_id);

      $count = 0;

      for ($i = 0; $i < count($request->set_player1); $i++) {
        if (isset($request->set_player1[$i])) {
          $temp1 = $request->set_player1[$i];
          $temp2 = $request->set_player2[$i];

          $result = new TeamFixtureResult();
          $result->team_fixture_id = $fixture->id;
          $result->team1_score = $temp1;
          $result->team2_score = $temp2;
          $result->set_nr = $i + 1;

          $result->save();

          $count++;
        }
      }
      return TeamFixture::find($request->fixture_id)->teamResults;
      // return   TeamFixtureResult::where('team_fixture_id', $fixture->id)->get();
      // return $fixture->teamResults;
    } elseif ($request->type == 'individual') {

      FixtureResult::where('fixture_id', $request->fixture_id)->delete();
      $fixture = Fixture::find($request->fixture_id);

      $count = 0;
      for ($i = 0; $i < count($request->set_player1); $i++) {
        if (isset($request->set_player1[$i])) {
          $temp1 = $request->set_player1[$i];
          $temp2 = $request->set_player2[$i];

          $result = new FixtureResult();
          $result->fixture_id = $fixture->id;
          $result->registration1_score = $temp1;
          $result->registration2_score = $temp2;
          $result->set_nr = $i + 1;
          if ($request->set_player1[$i] > $request->set_player2[$i]) {
            $result->winner_registration = $fixture->registration1_id;
            $result->loser_registration = $fixture->registration2_id;
          } else {
            $result->winner_registration = $fixture->registration2_id;
            $result->loser_registration = $fixture->registration1_id;
          }
          $result->save();

          $count++;
        }
      }


      // update fixture with winnner and loser
      // update fixture with winnner and loser

      // CapeTennisDraw::update_winner_fixture($fixture->id,$fixture->fixtureResults->last->w_registration);

      // CapeTennisDraw::update_winner_fixture($fixture->id,$fixture->fixtureResults->last->w_registration);
      $last = $fixture->fixtureResults->last();
      $responce['winner'] = $last->winner_registration;
      $responce['loser'] = $last->loser_registration;
      $responce['results'] = $fixture->fixtureResults;
      $responce['id'] = $fixture->id;

      $responce['update'] =  CapeTennisDraw::run_update($fixture, $responce['loser'], $responce['winner']);

      return $responce;
    } elseif ($request->type == 'individualNew') {

      FixtureResult::where('fixture_id', $request->fixture_id)->delete();
      $fixture = Fixture::find($request->fixture_id);
      $fixture->match_status = 2;

      $fixture->save();
      $sets = $request->input('sets');

      foreach ($sets as $setNr => $scores) {
        $p1 = $scores['player1'];
        $p2 = $scores['player2'];
        if (!is_null($p1) && !is_null($p2)) {
          $result = new FixtureResult();
          $result->fixture_id = $fixture->id;
          $result->registration1_score = $p1;
          $result->registration2_score = $p2;
          $result->set_nr = $setNr;
          $result->winner_registration = $p1 > $p2 ? $fixture->registration1_id : $fixture->registration2_id;
          $result->loser_registration = $p1 > $p2 ? $fixture->registration2_id : $fixture->registration1_id;
          $result->save();
        }
      }




      $last = $fixture->fixtureResults->last();

      $last = $fixture->fixtureResults->last();

      $responce['winner'] = $last->winner_registration;
      $responce['loser'] = $last->loser_registration;
      $responce['results'] = $fixture->fixtureResults;
      $responce['id'] = $fixture->id;
      $responce['fixture'] = $fixture;

      if ($fixture->stage !== 'RR') {
        $fixture->winner_registration = $last->winner_registration;
        $fixture->match_status = 3;
        $fixture->save();

        // ✅ Refetch the fixture from DB to ensure it's up-to-date


        $this->assignWinnerToParentSide($fixture);
        $this->assignLoserToConsolationSide($fixture);
      $responce['updates'] = DrawBuilder::autoAdvanceAllByesForDraw($fixture->draw_id);

      }
    }
    return $responce;
  }
  protected function assignWinnerToParentSide(Fixture $childFixture): void
  {
    $winner = $childFixture->winner_registration;

    if (!$winner || !$childFixture->parent_fixture_id) return;

    $parent = Fixture::find($childFixture->parent_fixture_id);
    if (!$parent) return;

    // Determine position in parent by checking which fixture fed it
    $childId = $childFixture->id;

    // Fetch all children of the parent
    $siblings = Fixture::where('parent_fixture_id', $parent->id)
      ->orderBy('match_nr')
      ->get();

    if ($siblings->count() === 2) {
      // Use order to assign correct side
      if ($siblings[0]->id === $childId) {
        $parent->registration1_id = $winner; // Left
      } elseif ($siblings[1]->id === $childId) {
        $parent->registration2_id = $winner; // Right
      }
    } else {
      // Fallback to match_nr: odd to reg1, even to reg2
      $matchNr = $childFixture->match_nr;
      if ($matchNr % 2 === 1) {
        $parent->registration1_id = $winner;
      } else {
        $parent->registration2_id = $winner;
      }
    }

    // Auto-advance logic if one side is BYE
    $r1 = $parent->registration1_id;
    $r2 = $parent->registration2_id;

    if ($r1 === 0 && $r2 > 0) {
      $parent->winner_registration = $r2;
      $parent->match_status = 3;
    } elseif ($r2 === 0 && $r1 > 0) {
      $parent->winner_registration = $r1;
      $parent->match_status = 3;
    } elseif ($r1 === 0 && $r2 === 0) {
      $parent->winner_registration = 0;
      $parent->match_status = 5;
    }

    $parent->save();
  }
  protected function assignLoserToConsolationSide(Fixture $childFixture): void
  {
    $loser = $this->getLoserId($childFixture);
    if (is_null($loser) || !$childFixture->loser_parent_fixture_id) return;

    $parent = Fixture::find($childFixture->loser_parent_fixture_id);
    if (!$parent) return;

    $childId = $childFixture->id;

    if (in_array($childFixture->loser_feeder_slot, [1, 2])) {
      // Use explicit feeder slot if available
      if ($childFixture->loser_feeder_slot === 1) {
        $parent->registration1_id = $loser;
      } else {
        $parent->registration2_id = $loser;
      }
    } else {
      // Fallback: use sibling order to assign
      $siblings = Fixture::where('loser_parent_fixture_id', $parent->id)
        ->orderBy('match_nr')
        ->get();

      if ($siblings->count() === 2) {
        if ($siblings[0]->id === $childId) {
          $parent->registration1_id = $loser;
        } elseif ($siblings[1]->id === $childId) {
          $parent->registration2_id = $loser;
        }
      } else {
        // Final fallback to match number
        $matchNr = $childFixture->match_nr;
        if ($matchNr % 2 === 1) {
          $parent->registration1_id = $loser;
        } else {
          $parent->registration2_id = $loser;
        }
      }
    }

    // Auto-advance
    $r1 = $parent->registration1_id;
    $r2 = $parent->registration2_id;

    if ($r1 === 0 && $r2 > 0) {
      $parent->winner_registration = $r2;
      $parent->match_status = 3;
    } elseif ($r2 === 0 && $r1 > 0) {
      $parent->winner_registration = $r1;
      $parent->match_status = 3;
    } elseif ($r1 === 0 && $r2 === 0) {
      $parent->winner_registration = 0;
      $parent->match_status = 5;
    }

    $parent->save();
  }


  protected function getLoserId(Fixture $fixture): ?int
  {
    $winner = $fixture->winner_registration;

    // If winner is not set, we can't determine the loser
    if (is_null($winner)) {
      return null;
    }

    $p1 = $fixture->registration1_id;
    $p2 = $fixture->registration2_id;

    // If both players are missing, or it's a Bye vs Bye
    if (is_null($p1) && is_null($p2)) {
      return null;
    }

    // Return the player who is NOT the winner
    if ($winner === $p1) {
      return $p2;
    }

    if ($winner === $p2) {
      return $p1;
    }

    // Fallback: winner doesn't match either? Something's wrong
    return null;
  }

  public function updateResult(Request $request)
  {
    if ($request->type == 'team') {
      $fixture = TeamFixture::find($request->fixture_id);
      TeamFixtureResult::where('team_fixture_id', $fixture->id)->delete();

      $count = 0;

      for ($i = 0; $i < count($request->reg1Set); $i++) {
        if (isset($request->reg1Set[$i])) {
          $temp1 = $request->reg1Set[$i];
          $temp2 = $request->reg2Set[$i];

          $result = new TeamFixtureResult();
          $result->team_fixture_id = $fixture->id;
          $result->team1_score = $temp1;
          $result->team2_score = $temp2;
          $result->set_nr = $i + 1;

          $result->save();

          $count++;
        }
      }
      return $fixture->teamResults;
    } else {
      FixtureResult::where('fixture_id', $request->fixture_id)->delete();
      $fixture = Fixture::find($request->fixture_id);

      $count = 0;

      for ($i = 0; $i < count($request->reg1Set); $i++) {
        if (isset($request->reg1Set[$i])) {
          $temp1 = $request->reg1Set[$i];
          $temp2 = $request->reg2Set[$i];

          $result = new FixtureResult();
          $result->fixture_id = $fixture->id;
          $result->registration1_score = $temp1;
          $result->registration2_score = $temp2;
          $result->set_nr = $i + 1;
          if ($request->reg1Set[$i] > $request->reg2Set[$i]) {
            $result->winner_registration = $fixture->registration1_id;
            $result->loser_registration = $fixture->registration2_id;
          } else {
            $result->winner_registration = $fixture->registration2_id;
            $result->loser_registration = $fixture->registration1_id;
          }
          $result->save();

          $count++;
          $fixture->results()->attach($result->id);
        }
      }
      return '$fixture->results';
    }
  }

  public function ajax($id)
  {
    $result = Fixture::find($id)->results;
    return $result;
  }

  public function deleteResult($id)
  {

    TeamFixtureResult::where('team_fixture_id', $id)->delete();
    return redirect()->back();
  }
  public function deleteIndResult($id)
  {

    FixtureResult::where('fixture_id', $id)->delete();
    $draw = Fixture::find($id)->draws;
    $ctd = new CapeTennisDraw($draw->id);
    $response['delete update'] = $ctd->run_delete_update(Fixture::find($id));
    $response['fixture'] = Fixture::find($id);
    return $response;
  }

  public function fixtures_create_pdf(Request $request)
  {
    // retreive all records from db
    $data['fixtures'] = Draw::find($request->fixtures)->fixtures;
    $data['name'] = Draw::find($request->fixtures)->events->name . ' ' . Draw::find($request->fixtures)->drawName;
    //return $name;

    $pdf = Pdf::loadView('backend.draw.pdf.pdf-team', $data);
    // download PDF file with download method

    return $pdf->download($data['name'] . '.pdf');
    //return 'hall0';
  }

  public function fixtures_create_pdf_venue(Request $request)
  {
    $ids = $request->input('fixtures');
    // retreive all records from db
    $data['f'] = TeamFixture::whereIn('id', $ids)->get();
    $data['fixtures'] = $data['f']->sortBy(function ($item) {
      return $item->schedule->time;
    });
    $data['name'] = $data['fixtures'][0]->schedule->venue->name;
    //return $name;

    $pdf = Pdf::loadView('backend.draw.pdf.pdf-team', $data);
    // download PDF file with download method

    return $pdf->download($data['name'] . '.pdf');
    //return 'hall0';
  }

  public function stages(Request $request)
  {
    return TeamFixture::where('draw_id', $request->draw)
      ->with('region2name')
      ->with('region1name')
      ->get()
      ->groupBy('stage_nr');
  }

  public function ties(Request $request)
  {
    return TeamFixture::where('draw_id', $request->draw)
      ->with('region2name')
      ->with('region1name')
      ->get()
      ->groupBy('tie_nr');
  }

  public function updatePlayer(Request $request)
  {
    $fixture = TeamFixture::find($_GET['fixture']);

    if ($fixture->fixture_type == 1 || $fixture->fixture_type == 4) {
      $fixture = TeamFixturePlayer::where('team_fixture_id', $fixture->id)->first();

      $fixture->team1_id = $_GET['player1'];
      $fixture->team2_id = $_GET['player2'];

      $fixture->save();
    }
    return redirect()->back();
  }

  public function fixtures_venue($event_id, $venue_id)
  {
    $venue = Venues::find($venue_id);
    $event = Event::find($event_id);
    // dd($userRegistrations);
    $eventDraws = $event->draws->sortByDesc('published');

    $fixturesPerVenue = TeamFixture::whereIn('draw_id', $eventDraws->pluck('id'))->get();
    //dd($fixturesPerVenue);
    $data['fixtures'] = $fixturesPerVenue->filter(function ($item) use ($venue) {
      if (isset($item->schedule->venue_id)) {
        return $item->schedule->venue_id == $venue->id;
      } else {
      }
    });
    //$data['f'] = collect($data['fixtures'])->sortBy('fixture_type')->values();

    $data['fixtures'] = $data['fixtures']
      ->sortBy(function ($fixture) {
        // Access the time column from the related schedule
        return $fixture->schedule->time ?? null;
      })
      ->values();

    //$data['f'] = $data['fixtures']->all();
    //dd($data['f']);
    //$data['f'] = $data['f']->sortBy('fixture_type');
    $data['players'] = Player::all();

    return view('backend.fixture.fixtures-venue', $data);
  }

  public function autoScheduleFixtures($draw_id, Request $request)
  {
    $draw = Draw::find($request->drawId);
    $day = $request->daySelected;
    $daySelectedDate = $request->daySelectedDate;
    $event = $draw->events;
    $venues = $draw->venues->pluck('id');

    $drawsWithVenue = Draw::whereHas('venues', function ($query) use ($venues) {
      $query->whereIn('venue_id', [$venues]);
    })
      ->where('event_id', $event->id)
      ->get();

    if ($drawsWithVenue->count() > 0) {
      $venue = $draw->venues->first();
      $numcourts = DrawVenue::where('draw_id', $draw_id)
        ->where('venue_id', $venue->id)
        ->first()->num_courts;
      //   return 'have to check start and finish times';

      $daySchedule = $this->getDayScheduleDefault($day);

      $dayFixtures = $this->getDayFixtures($daySchedule, $draw);

      $chunks = $dayFixtures->chunk($numcourts);

      $startTime = new Carbon($request->startTime);
      $endTime = new Carbon($request->endTime); // 5:00 PM

      $sessionDuration = CarbonInterval::minutes(45); // 75 minutes session
      $sessions = [];

      while ($startTime->lessThan($endTime)) {
        $sessionEnd = $startTime->copy()->add($sessionDuration);

        // Ensure the session end doesn't exceed the overall end time
        if ($sessionEnd->greaterThan($endTime)) {
          $sessionEnd = $endTime;
        }

        $sessions[] = [
          'start' => $startTime->format('H:i'),
          'end' => $sessionEnd->format('H:i'),
        ];

        // Move to the next session
        $startTime = $sessionEnd;
      }
      $oop = new Collection();
      foreach ($chunks as $key => $chunk) {
        foreach ($chunk as $match) {
          $oop->push(['time' => $sessions[$key]['start'], 'match' => $match]);
          OrderOfPlay::where('fixture_id', $match->id)->delete();
          $order = new OrderOfPlay();
          $order->draw_id = $draw->id;
          $order->fixture_id = $match->id;
          $datetime = Carbon::parse("{$daySelectedDate} {$sessions[$key]['start']}");
          $order->time = $datetime->toDateTimeString();
          $order->venue_id = $venue->id;
          $order->save();
        }
      }
      return $order;
    } else {
      $draws = $drawsWithVenue;


      return $draws;
    }
  }

  public function getDayScheduleDefault($day)
  {
    $schedule = null;
    if ($day == 1) {
      $schedule[1] = ['bracket' => 1, 'stage' => 1, 'lowMatch' => 0, 'highMatch' => 17]; //plat 1
      $schedule[2] = ['bracket' => 3, 'stage' => 1, 'lowMatch' => 0, 'highMatch' => 5]; //gold 1
      $schedule[3] = ['bracket' => 1, 'stage' => 2, 'lowMatch' => 16, 'highMatch' => 21]; //plat
      $schedule[4] = ['bracket' => 3, 'stage' => 1, 'lowMatch' => 4, 'highMatch' => 9]; //gold 5-8
      $schedule[5] = ['bracket' => 1, 'stage' => 2, 'lowMatch' => 20, 'highMatch' => 25]; //plat
    } elseif ($day == 2) {
      $schedule[1] = ['bracket' => 3, 'stage' => 2, 'lowMatch' => 0, 'highMatch' => 92]; //gold 2
      $schedule[2] = ['bracket' => 1, 'stage' => 3, 'lowMatch' => 0, 'highMatch' => 92]; //plat 3

      $schedule[3] = ['bracket' => 8, 'stage' => 1, 'lowMatch' => 0, 'highMatch' => 92]; //25-32 - 1
      $schedule[4] = ['bracket' => 7, 'stage' => 1, 'lowMatch' => 0, 'highMatch' => 92]; //17-24 -1
      $schedule[5] = ['bracket' => 3, 'stage' => 3, 'lowMatch' => 0, 'highMatch' => 92]; //gold 3
      $schedule[6] = ['bracket' => 1, 'stage' => 4, 'lowMatch' => 0, 'highMatch' => 92]; //plat 4

      $schedule[7] = ['bracket' => 8, 'stage' => 2, 'lowMatch' => 0, 'highMatch' => 92]; //25-32 - 2
      $schedule[8] = ['bracket' => 7, 'stage' => 2, 'lowMatch' => 0, 'highMatch' => 92]; //17-24 -2
      $schedule[9] = ['bracket' => 6, 'stage' => 1, 'lowMatch' => 0, 'highMatch' => 92]; //13-16 1
      $schedule[10] = ['bracket' => 8, 'stage' => 3, 'lowMatch' => 0, 'highMatch' => 92]; //25-32 - 3
      $schedule[11] = ['bracket' => 3, 'stage' => 4, 'lowMatch' => 0, 'highMatch' => 92]; //gold 4
    } elseif ($day == 3) {
      $schedule[1] = ['bracket' => 3, 'stage' => 5, 'lowMatch' => 0, 'highMatch' => 92]; // gold semi final
      $schedule[2] = ['bracket' => 5, 'stage' => 1, 'lowMatch' => 0, 'highMatch' => 92]; //9-12 - 1
      $schedule[3] = ['bracket' => 6, 'stage' => 2, 'lowMatch' => 0, 'highMatch' => 92]; //13-16 - 2 final
      $schedule[4] = ['bracket' => 7, 'stage' => 3, 'lowMatch' => 0, 'highMatch' => 92]; //17-24 -3
      $schedule[5] = ['bracket' => 1, 'stage' => 5, 'lowMatch' => 0, 'highMatch' => 92]; //plat final
      $schedule[6] = ['bracket' => 2, 'stage' => 1, 'lowMatch' => 0, 'highMatch' => 92]; // 3/4 playoff
      $schedule[7] = ['bracket' => 3, 'stage' => 6, 'lowMatch' => 0, 'highMatch' => 92]; // gold final
      $schedule[8] = ['bracket' => 5, 'stage' => 2, 'lowMatch' => 0, 'highMatch' => 92]; //9-12 final
      $schedule[9] = ['bracket' => 4, 'stage' => 1, 'lowMatch' => 0, 'highMatch' => 92]; // 7-8 playoff - final
    }
    // return '$schedule';
  }
  public function getDayFixtures($daySchedule, $draw): collection
  {
    $stageFixtures = new Collection();

    foreach ($daySchedule as $key => $schedulestage) {
      $fixtures = $draw->drawFixtures->sortBy(function ($item) {
        return $item->match_nr;
      });

      $stageFixtures = $stageFixtures->merge(
        $fixtures->where('bracket_id', '=', $schedulestage['bracket'])->where('stage', '=', $schedulestage['stage'])->whereBetween('match_nr', [$schedulestage['lowMatch'], $schedulestage['highMatch']])
      );
    }

    $stageFixtures->map(function ($item, $key) {
      $item->drawMatchNr = $key + 1;
    });
    return $stageFixtures;
  }
  public static function generatePlayoffFixtures(Draw $draw)
  {

    $builder = new DrawBuilder($draw);

    $fixtureMap = $builder
      ->rankPlayers()
      ->assignSeedingCodes()
      ->generatePlayoffFixtures();


    return response()->json([
      'message' => 'Fixtures created',
      'fixture_map' => $fixtureMap,

      'draw' => $draw
    ]);
  }
}
