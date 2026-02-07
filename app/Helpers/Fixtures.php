<?php

namespace App\Helpers;

use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\DrawRegistrations;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\EventTeam;
use App\Models\Fixture;
use App\Models\Player;
use App\Models\SubDraw;
use App\Models\Team;
use App\Models\TeamFixture;
use App\Models\TeamFixturePlayer;
use App\Models\TeamFixtureResult;
use App\Models\TeamRegion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class Fixtures

{
  public function __construct() {}

  public static function lockDraw() {}

  public static function getRegionName($region_id)
  {
    $teamName = TeamRegion::find($region_id);
    //dd($teamName);
    return $teamName->region_name;
  }
  public static function get_regions($draw_id)
  {
    $event = Draw::find($draw_id)->events;

    if ($event->eventType == 3) {
      $regions = EventRegion::where('event_id', $event->id)->get();


      return $regions;
    } else {
      return "Error,not a team event event";
    }
  }
  public static function teams_in_region($region)
  {

    $teams = $region->regions->teams;



    return $teams;
  }
  public static function makeRegionFixtures($regions, int $rounds = null, bool $shuffle = false, int $seed = null): array
  {
    $teams = $regions->toArray();
    $teamCount = count($teams);

    if ($teamCount < 2) {
      return [];
    }

    // Account for odd number of teams by adding a bye
    if ($teamCount % 2 === 1) {
      $teams[] = null;
      $teamCount++;
    }

    // Shuffle teams if required
    if ($shuffle) {
      if ($seed !== null) {
        $teams = self::seededShuffle($teams, $seed);
      } else {
        shuffle($teams);
      }
    }

    $halfTeamCount = $teamCount / 2;
    $rounds = $rounds ?? ($teamCount - 1);
    $schedule = [];

    for ($round = 1; $round <= $rounds; $round++) {
      foreach ($teams as $key => $team) {
        if ($key >= $halfTeamCount) {
          break;
        }
        $team1 = $team;
        $team2 = $teams[$key + $halfTeamCount];

        // Skip bye matches
        if ($team1 === null || $team2 === null) {
          continue;
        }

        $schedule[$round][] = [$team1, $team2];
      }

      // Rotate teams while keeping the first team fixed
      self::rotate($teams);
    }

    return $schedule;
  }

  private static function seededShuffle(array $array, int $seed): array
  {
    mt_srand($seed);
    for ($i = count($array) - 1; $i > 0; $i--) {
      $j = mt_rand(0, $i);
      [$array[$i], $array[$j]] = [$array[$j], $array[$i]]; // Swap elements
    }
    mt_srand(); // Reset RNG to prevent side effects
    return $array;
  }



  public static function insertSinglesFixtures($id, $fixture_type, $regions_in_tie1, $regions_in_tie2, $team1, $team2, $count, $round, $tie)
  {




    for ($i = 0; $i < count($team1); $i++) {
      $fixture = new TeamFixture();
      $fixture->fixture_type = $fixture_type;
      $fixture->draw_id = $id;
      $fixture->numSets = 3;
      $fixture->match_nr = $count;
      $fixture->round_nr = $round;
      $fixture->tie_nr = $tie;
      $fixture->region1 = $regions_in_tie1;
      $fixture->region2 = $regions_in_tie2;
      $fixture->age = Draw::find($id)->drawName;
      $fixture->save();




      if ($fixture->fixture_type == 5) {
        $teamFixturePlayer = new TeamFixturePlayer();
        $teamFixturePlayer->team1_id = $team1[$i]->id;
        $teamFixturePlayer->team2_id = $team2[$i]->id;
        $teamFixturePlayer->team_fixture_id = $fixture->id;
        if (!$teamFixturePlayer->save()) {
          dd('error');
        }
      }
      $count++;
    }

    return $count;
  }
  public static function insertDoublesFixtures($id, $fixture_type, $regions_in_tie1, $regions_in_tie2, $team1, $team2, $count, $round, $tie)
  {


    for ($i = 0; $i < count($team1); $i += 2) {
      $fixture = new TeamFixture();
      $fixture->fixture_type = $fixture_type;
      $fixture->draw_id = $id;
      $fixture->numSets = 3;
      $fixture->match_nr = $count;
      $fixture->round_nr = $round;
      $fixture->tie_nr = $tie;
      $fixture->region1 = $regions_in_tie1;
      $fixture->region2 = $regions_in_tie2;
      $fixture->age = Draw::find($id)->drawName;
      $fixture->save();

      if ($fixture->fixture_type == 5) {
        $teamFixturePlayer = new TeamFixturePlayer();
        $teamFixturePlayer->team1_id = $team1[$i]->id;
        $teamFixturePlayer->team2_id = $team2[$i]->id;
        $teamFixturePlayer->team_fixture_id = $fixture->id;

        if (!$teamFixturePlayer->save()) {
          dd('error');
        }
        $teamFixturePlayer = new TeamFixturePlayer();
        $teamFixturePlayer->team1_id = $team1[($i + 1)]->id;
        $teamFixturePlayer->team2_id = $team2[($i + 1)]->id;
        $teamFixturePlayer->team_fixture_id = $fixture->id;

        if (!$teamFixturePlayer->save()) {
          dd('error');
        }
      }
      $count++;
    }

    return $count;
  }
  public static function insertMixedFixtures($id, $fixture_type, $regions_in_tie1, $regions_in_tie2, $team1, $team2, $count, $tie, $round)
  {



    $teamNum = count($team1) / 2;
    for ($i = 0; $i < $teamNum; $i++) {
      $fixture = new TeamFixture();
      $fixture->fixture_type = $fixture_type;
      $fixture->draw_id = $id;
      $fixture->numSets = 3;
      $fixture->match_nr = $count;
      $fixture->round_nr = $round;
      $fixture->tie_nr = $tie;
      $fixture->region1 = $regions_in_tie1;
      $fixture->region2 = $regions_in_tie2;
      $fixture->age = Draw::find($id)->drawName;
      $fixture->save();

      if ($fixture->fixture_type == 5) {
        $teamFixturePlayer = new TeamFixturePlayer();
        $teamFixturePlayer->team1_id = $team1[$i]->id;
        $teamFixturePlayer->team2_id = $team2[$i]->id;
        $teamFixturePlayer->team_fixture_id = $fixture->id;

        if (!$teamFixturePlayer->save()) {
          dd('error');
        }
        $teamFixturePlayer = new TeamFixturePlayer();
        $teamFixturePlayer->team1_id = $team1[($i + $teamNum)]->id;
        $teamFixturePlayer->team2_id = $team2[($i + $teamNum)]->id;
        $teamFixturePlayer->team_fixture_id = $fixture->id;

        if (!$teamFixturePlayer->save()) {
          dd('error');
        }
      }
      $count++;
    }

    return $count;
  }

  public static function createTeamFixtures($draw, $fixture_type, $region1, $region2, $team1, $team2, $count, $tie, $round)
  {
    $numPlayersInTeam = $team1->first()->team_players->count();

    switch ($fixture_type) {
      case 1:
        for ($i = 0; $i < $numPlayersInTeam; $i++) {
          $fixture = self::createFixture($draw, $fixture_type, $region1, $region2, $count, $tie, $round, $i + 1);

          $team1First = $team1->first();
          $team2First = $team2->first();

          if ($team1First && $team1First->team_players->isNotEmpty() && isset($team1First->team_players[$i])) {
            $player1 = $team1First->team_players[$i]->player_id;
          } else {
            return 'error 1';
          }

          if ($team2First && $team2First->team_players->isNotEmpty() && isset($team2First->team_players[$i])) {
            $player2 = $team2First->team_players[$i]->player_id;
          } else {
            return 'error 2';
          }
          $tfp = new TeamFixturePlayer();
          $tfp->team1_id = $player1;
          $tfp->team2_id = $player2;
          $tfp->team_fixture_id = $fixture->id;
          $tfp->save();



          $count++;
        }


      case 2:
        for ($i = 0; $i < $numPlayersInTeam / 2; $i++) {
          $fixture = self::createFixture($draw, $fixture_type, $region1, $region2, $count, $tie, $round, $i + 1);

          $team1First = $team1->first();
          $team2First = $team2->first();

          if ($team1First && $team1First->team_players->isNotEmpty() && isset($team1First->team_players[$i])) {
          } else {
            return 'error 1';
          }

          if ($team2First && $team2First->team_players->isNotEmpty() && isset($team2First->team_players[$i])) {
          } else {
            return 'error 2';
          }


          $tfp = new TeamFixturePlayer();
          $tfp->team1_id = $team1First->team_players[$i]->player_id;
          $tfp->team2_id = $team2First->team_players[($i+1)]->player_id;
          $tfp->team_fixture_id = $fixture->id;
          $tfp->save();
          $tfp = new TeamFixturePlayer();
          $tfp->team1_id =  $team1First->team_players[$i]->player_id;
          $tfp->team2_id =  $team2First->team_players[($i+1)]->player_id;
          $tfp->team_fixture_id = $fixture->id;
          $tfp->save();


          $count++;
        }

        break;
      case 4:



        for ($i = 0; $i < $numPlayersInTeam; $i++) {
          $fixture = self::createFixture($draw, $fixture_type, $region1, $region2, $count, $tie, $round, $i + 1);
          if ($i === 0 || $i % 2 === 0) {
            $team1First = $team1->first();
            $team2First = $team2->first();
            $player1 = $team1First->team_players[$i]->player_id;
            $player2 = $team1First->team_players[($i + 1)]->player_id;
            // $i is either 0 or an even number
            $tfp = new TeamFixturePlayer();
            $tfp->team1_id = $player1;
            $tfp->team2_id = $player2;
            $tfp->team_fixture_id = $fixture->id;
            $tfp->save();
          } else {
            $team1First = $team1->first();
            $team2First = $team2->first();
            $player1 = $team1First->team_players[$i]->player_id;
            $player2 = $team1First->team_players[($i - 1)]->player_id;
            // $i is either 0 or an even number
            $tfp = new TeamFixturePlayer();
            $tfp->team1_id = $player1;
            $tfp->team2_id = $player2;
            $tfp->team_fixture_id = $fixture->id;
            $tfp->save();
          }
        }
        break;



      case 3:

        for ($i = 0; $i < $numPlayersInTeam; $i++) {




          $team1First = $team1['boys']->first();
          $team2First = $team2->first();

 $fixture = self::createFixture($draw, $fixture_type, $region1, $region2, $count, $tie, $round, $i + 1);



          $count++;
        }
        break;

      case 5:
        $numInTeam = count($team1);
        for ($i = 0; $i < $numInTeam; $i++) {
          $fixture = TeamFixture::create([
            'fixture_type' => $fixture_type,
            'draw_id' => $draw->id,
            'numSets' => 3,
            'match_nr' => $count
          ]);

          TeamFixturePlayer::create([
            'team1_id' => $team1[$i]->id,
            'team2_id' => $team2[$i]->id,
            'team_fixture_id' => $fixture->id
          ]);

          $count++;
        }
        break;
    }

    return $count;
  }

  /**
   * Helper function to create a fixture.
   */
  private static function createFixture($draw, $fixture_type, $region1, $region2, $count, $tie, $round, $rank)
  {
    return TeamFixture::create([
      'fixture_type' => $fixture_type,
      'draw_id' => $draw->id,
      'numSets' => 3,
      'match_nr' => $count,
      'round_nr' => $round,
      'rank_nr' => $rank,
      'region1' => $region1->region_id,
      'tie_nr' => $tie + 1,
      'region2' => $region2->region_id,
      'age' => $draw->drawName
    ]);
  }

  /**
   * Assigns players to a fixture.
   */
  private static function assignPlayersToFixture($fixture_id, $team1, $team2, $i)
  {
    if (!isset($team1[$i]) || !isset($team2[$i])) {
      return;
    }

    TeamFixturePlayer::create([
      'team1_id' => $team1[$i]->id,
      'team2_id' => $team2[$i]->id,
      'team_fixture_id' => $fixture_id
    ]);

    $offset = count($team1) / 2;
    if (isset($team1[$i + $offset]) && isset($team2[$i + $offset])) {
      TeamFixturePlayer::create([
        'team1_id' => $team1[$i + $offset]->id,
        'team2_id' => $team2[$i + $offset]->id,
        'team_fixture_id' => $fixture_id
      ]);
    }
  }




  public static function make_schedule(array $teams, int $rounds = null, bool $shuffle = true, int $seed = null): array
  {
    $teamCount = count($teams);
    if ($teamCount < 2) {
      return [];
    }
    //Account for odd number of teams by adding a bye
    if ($teamCount % 2 === 1) {
      array_push($teams, null);
      $teamCount += 1;
    }

    $halfTeamCount = $teamCount / 2;
    if ($rounds === null) {
      $rounds = $teamCount - 1;
    }
    $schedule = [];
    for ($round = 1; $round <= $rounds; $round += 1) {
      foreach ($teams as $key => $team) {
        if ($key >= $halfTeamCount) {
          break;
        }
        $team1 = $team;
        $team2 = $teams[$key + $halfTeamCount];
        //Home-away swapping
        $matchup = $round % 2 === 0 ? [$team1, $team2] : [$team2, $team1];
        $schedule[$round][] = $matchup;
      }
      Fixtures::rotate($teams);
    }
    return $schedule;
  }
  public static function rotate(array &$items)
  {
    $itemCount = count($items);
    if ($itemCount < 3) {
      return;
    }
    $lastIndex = $itemCount - 1;
    /**
     * Though not technically part of the round-robin algorithm, odd-even
     * factor differentiation included to have intuitive behavior for arrays
     * with an odd number of elements
     */
    $factor = (int) ($itemCount % 2 === 0 ? $itemCount / 2 : ($itemCount / 2) + 1);
    $topRightIndex = $factor - 1;
    $topRightItem = $items[$topRightIndex];
    $bottomLeftIndex = $factor;
    $bottomLeftItem = $items[$bottomLeftIndex];
    for ($i = $topRightIndex; $i > 0; $i -= 1) {
      $items[$i] = $items[$i - 1];
    }
    for ($i = $bottomLeftIndex; $i < $lastIndex; $i += 1) {
      $items[$i] = $items[$i + 1];
    }
    $items[1] = $bottomLeftItem;
    $items[$lastIndex] = $topRightItem;
  }
  static public function hasResult($fixture_id)
  {

    $res = TeamFixtureResult::where('team_fixture_id', $fixture_id)->get();

    $result = 0;
    if (count($res) > 0) {
      $result = 1;
    }

    return $result;
  }
  static public function getWinner($id)
  {

    $fixture = TeamFixtureResult::where('team_fixture_id', $id)->orderBy('id', 'desc')->first();
    $result = 0;

    if (isset($fixture)) {
      if (Fixtures::hasResult($fixture->team_fixture_id)) {
        if ($fixture->team1_score > $fixture->team2_score) {
          $result = 1;
        } else {
          $result = 2;
        }
      }
    }



    return $result;
  }

  static public function getResult($id)
  {
    $res = TeamFixtureResult::where('team_fixture_id', $id)->orderBy('id')->get();
    $result = '';
    if (count($res) == 2) {
      $result = $res[0]->team1_score . ' - ' . $res[0]->team2_score . ', ' . $res[1]->team1_score . ' - ' . $res[1]->team2_score;
    } elseif (count($res) == 1) {
      $result = $res[0]->team1_score . ' - ' . $res[0]->team2_score;
    } elseif (count($res) == 3) {
      $result = $res[0]->team1_score . ' - ' . $res[0]->team2_score . ', ' . $res[1]->team1_score . ' - ' . $res[1]->team2_score . ', ' . $result = $res[2]->team1_score . ' - ' . $res[2]->team2_score;
    }


    return $result;
  }

  static public function calcTeamScores($event_id)
  {
    $regions = Event::find($event_id)->region_in_events;
    foreach ($regions as $region) {
      $scores[$region->id] = Fixtures::getScorePerRegion($region);
    }

    return $scores[1];
  }

  public static function getScorePerRegion($region)
  {

    $data['regionScoreboard'] = Fixtures::getRegionScore($region->teams);
    $data['u/10'] = Fixtures::teamScore(array(42, 47, 51, 55, 63, 71, 68));
    $data['u/11'] = Fixtures::teamScore(array(43, 48, 52, 56, 64, 72, 67));
    $data['u/12'] = Fixtures::teamScore(array(44, 53, 65, 49, 57, 69, 0));

    $data['u/13'] = Fixtures::teamScore(array(45, 54, 66, 50, 58, 70, 74));
    return $data;
  }
  public static function getRegionScore($teams)
  {
    $fixtures = TeamFixture::all();
    $results = TeamFixtureResult::all();



    $west = 0;
    $witz = 0;
    $over = 0;


    foreach ($fixtures as $fixture) {
      $result = TeamFixtureResult::where('team_fixture_id', $fixture->id)->latest()->first();
      if (isset($result->team1_score) and isset($result->team2_score)) {
        if ($result->team1_score > $result->team2_score) {

          switch ($result->fixtures->team1[0]->teams[0]->regions->id) {
            case 1:
              $over += 1;

              break;
            case 2:
              $west += 1;

              break;
            case 3:
              $witz += 1;

              break;
          }
        } else {
          switch ($result->fixtures->team2[0]->teams[0]->regions->id) {
            case 1:
              $over += 1;

              break;
            case 2:
              $west += 1;

              break;
            case 3:
              $witz += 1;

              break;
          }
        }
      }
    }
    $data['over'] = $over;
    $data['west'] = $west;
    $data['witz'] = $witz;

    return $data;
  }
  public static function teamScore($team)
  {

    $west = 0;
    $witz = 0;
    $over = 0;
    $count = 0;
    //under 10
    $fixtures = TeamFixture::where('draw_id', $team[0])
      ->orwhere('draw_id',  $team[1])
      ->orwhere('draw_id',  $team[2])
      ->orwhere('draw_id',  $team[3])
      ->orwhere('draw_id',  $team[4])
      ->orwhere('draw_id',  $team[5])
      ->orwhere('draw_id',  $team[6])
      ->get();

    foreach ($fixtures as $fixture) {
      $result = TeamFixtureResult::where('team_fixture_id', $fixture->id)->orderBy('id', 'desc')->first();
      if (isset($result->team1_score) and isset($result->team2_score)) {
        if ($result->team1_score > $result->team2_score) {
          $count++;
          switch ($result->fixtures->team1[0]->teams[0]->regions->id) {
            case 1:
              $over += 1;

              break;
            case 2:
              $west += 1;

              break;
            case 3:
              $witz += 1;

              break;
            default:
              dd($result->fixtures->team1[0]->teams);
              break;
          }
        } else {
          $count++;
          switch ($result->fixtures->team2[0]->teams[0]->regions->id) {
            case 1:
              $over++;

              break;
            case 2:
              $west++;

              break;
            case 3:
              $witz++;

              break;
          }
        }
      } else {
        $data['notset'][] = $fixture;
      }
    }
    $data['over'] = $over;
    $data['west'] = $west;
    $data['witz'] = $witz;
    $data['totalfix'] = count($fixtures);
    $data['count'] = $count;
    return $data;
  }


  public static function getTieScore($tie)
  {
    $team1_wins = 0;
    $team2_wins = 0;
    $team1points = 0;
    $team2points = 0;
    $times = 0;
    foreach ($tie as $fixture) {

      if ($fixture->teamResults) {

        $result = $fixture->teamResults;
        $times++;
        if (count($result) > 0) {
          if ($result->last()->team1_score > $result->last()->team2_score) {
            $team1_wins++;
            if (count($fixture->teamResults) == 2) {
              $team1points += 3;
            } elseif (count($fixture->teamResults) == 3) {
              $team1points += 2;
              $team2points++;
            } elseif (count($fixture->teamResults) == 1) {
              if ($result->last()->team1_score > $result->last()->team2_score) {
                $team1points += 2;
                if (($result->last()->team1_score - $result->last()->team2_score) == 1) {
                  $team2points += 1;
                }
              }
            }
          } else {
            $team2_wins++;
            if (count($fixture->teamResults) == 2) {
              $team2points += 3;
            } elseif (count($fixture->teamResults) == 3) {
              $team2points += 2;
              $team1points++;
            } elseif (count($fixture->teamResults) == 1) {
              if ($result->last()->team2_score > $result->last()->team1_score) {
                $team2points += 2;
                if (($result->last()->team2_score - $result->last()->team1_score) == 1) {
                  $team1points += 1;
                }
              }
            }
          }
        } else {
          dd($times);
        }
      }
    }
    $r = 'Team 1: ' . $team1_wins . ' Team 2: ' . $team2_wins . ' team points: ' . $team1points . ' team 2 points: ' . $team2points;

    $scores = array('team1points' => $team1points, 'team2points' => $team2points);
    return $scores;
  }

  public static function getTeamScoresForEvent($age)
  {
    $event = Event::find(26);

    if ($age == '10') {
      $d = ['id' => 90, 'id' => 91, 'id' => 92, 'id' => 93];
    }

    if ($age == 10) {
      $draws = $event->order_of_plays10;
    } elseif ($age == 11) {
      $draws = $event->order_of_plays11;
    } elseif ($age == 12) {
      $draws = $event->order_of_plays12;
    } elseif ($age == 13) {
      $draws = $event->order_of_plays13;
    }



    $regions_in_event = $event->region_in_events;
    foreach ($regions_in_event as  $key => $region) {
      $scoreboard[$region->id] = 0;
    }
    $scoreboard['matches'] = 0;
    $scoreboard['Outstanding'] = 0;
    $times = 0;
    foreach ($draws as $draw) {
      $fixtures_in_draw = $draw->fixtures;

      foreach ($fixtures_in_draw as $result) {


        if (count($result->teamResults) > 0) {

          $region1 = $result->region1;
          $region2 = $result->region2;


          if ($result->teamResults->last()->team1_score > $result->teamResults->last()->team2_score) {


            $search = Fixtures::searchForId($region1, $scoreboard);
            $scoreboard[$search]++;

            $scoreboard['matches']++;
          } else {
            $search = Fixtures::searchForId($region2, $scoreboard);
            $scoreboard[$search]++;
            $scoreboard['matches']++;
          }
        } else {
          $scoreboard['Outstanding']++;
        }
      }
    }

    return $scoreboard;
  }





  public static function searchForId($id, $array)
  {

    foreach ($array as $key => $val) {

      if ($key == $id) {
        return $key;
      }
    }
    return null;
  }

  public static function getWins($player_id, $event_id, $age)
  {
    $draws = Event::find($event_id)->draws_singles($event_id, $player_id, $age)->get();
    $merged = new Collection();

    foreach ($draws as $draw) {
      $merged = $merged
        ->merge($draw->fixtures);



      //all draws in event

    }


    $mer = $merged->filter(function ($value, $key) use ($player_id) {

      if ($value->team1[0]->id == $player_id) {
        return $value->team1[0]->id == $player_id;
      } else {
        return $value->team2[0]->id == $player_id;
      }
    });

    return $mer;
  }

  public static function getLosts()
  {
    return 3;
  }

  public static function createEventFixtures($draw, $matchup, $category, $round)
  {

    if ($draw->drawType_id == 1) {
      $count = 1;

      $numplays = count($matchup[0]['teams']);
      for ($i = 0; $i <= $numplays; $i++) {
        $fixture = new TeamFixture();
        $fixture->fixture_type = $draw->drawType_id;
        $fixture->draw_id = $draw->id;
        $fixture->numSets = 0;
        $fixture->round_nr = $round;
        $fixture->match_nr = $count;
        $fixture->region1 = $matchup[0]['teams']['id'];

        $fixture->region2 = $matchup[1]['id'];
        /*


                $fixture->tie_nr = $tie;


               */
        $fixture->age = $draw->drawName;

        $fixture->save();

        if ($fixture->fixture_type == 1) {
          $teamFixturePlayer = new TeamFixturePlayer();
          $player1 = Team::find($matchup[0]['id'])->team_players;
          $player2 = Team::find($matchup[1]['id'])->team_players;
          // $teamFixturePlayer->team1_id = $player1[$i]->id;


          //  $teamFixturePlayer->team2_id = $player2[$i]->id;
          // $teamFixturePlayer->team2_id = Team::find($matchup[1]['id']);
          $teamFixturePlayer->team_fixture_id = $fixture->id;
          // $teamFixturePlayer->save();
          $fixtures[] = $fixture;
        }
        $count++;
        $fixtures['error'] = Team::find($matchup[0]['id']);
      }
      return $fixtures;
    } else {
      return 'no Draw Type';
    }
  }

  public static function createMixedFixtures($draw, $fixture_type, $region1, $region2, $team1, $team2, $count, $tie, $round)
  {

    $numPlayersInTeam = count($team1['boys'][0]['team_players']);
    // dd('check your numplayers in code');
    for ($i = 0; $i < $numPlayersInTeam; $i++) {
      $fixture = new TeamFixture();
      $fixture->fixture_type = $fixture_type;
      $fixture->draw_id = $draw->id;
      $fixture->numSets = 3;
      $fixture->match_nr = $count;
      $fixture->round_nr = $round;
      $fixture->rank_nr = ($i + 1);
      $fixture->region1 = $region1->region_id;
      $fixture->tie_nr = ($tie + 1);
      $fixture->region2 = $region2->region_id;
      $fixture->age = $draw->drawName;
      $fixture->save();



        $teamFixturePlayer = new TeamFixturePlayer();
        $teamFixturePlayer->team1_id = $team1['boys'][0]['team_players'][$i]['player_id'];
        $teamFixturePlayer->team2_id = $team2['boys'][0]['team_players'][$i]['player_id'];;
        $teamFixturePlayer->team_fixture_id = $fixture->id;
        $teamFixturePlayer->save();

        $teamFixturePlayer = new TeamFixturePlayer();
        $teamFixturePlayer->team1_id = $team1['boys'][0]['team_players'][$i]['player_id'];
        $teamFixturePlayer->team2_id = $team2['girls'][0]['team_players'][$i]['player_id'];;
        $teamFixturePlayer->team_fixture_id = $fixture->id;
        $teamFixturePlayer->save();



      $count++;
    }


    return $count;
  }

  public static function getTeamWins($player, $perAge, $age)
  {
    $merged = $perAge[$age];






    $mer = $merged->filter(function ($value, $key) use ($player) {

      if ($value->team1[0]->id == $player->id) {
        return $value->team1[0]->id == $player->id;
      } else {
        return $value->team2[0]->id == $player->id;
      }
    });
    return $mer;
  }

  public static function nomReg($playerid, $categoryEventId)
  {

    $player = Player::find($playerid);

    $reg =  CategoryEventRegistration::where('category_event_id', $categoryEventId)



      ->get();



    if ($reg->contains(function ($r) use ($playerid) {
      return $r->registration->players[0]->id === $playerid;
    })) {
      return 'Registered';
    } else {
      return 'Not Registered';
    }
  }

  public static function createMonrad32Fixtures($draw_id)
  {


    $numfixtures = 0;
    $response = "error";

    $draw = Draw::find($draw_id);
    // $players = $draw->registrations;
    $players = DrawRegistrations::with('registrations')->where('draw_id', $draw_id)->orderByRaw('-seed desc')->get()->pluck('registrations');
    // dd($players[0]->players);
    //return $players[27]->players;

    Fixture::where('draw_id', $draw_id)->delete();
    // create platinum bracket 1-2


    for ($i = 0; $i < 31; $i++) {


      $fixture = new Fixture();
      $fixture->draw_id = $draw->id;
      $fixture->match_nr = $i + 1;
      $fixture->bracket_id = 1;
      $fixture->round = 1;



      if ($i < 16) {
        //template for seeding normal
        switch ($i) {
          case 0:
            $play1 = 0;
            $play2 = 31;

            break;
          case 1:
            $play1 = 16;
            $play2 = 15;
            break;
          case 2:
            $play1 = 8;
            $play2 = 23;
            break;
          case '3':
            $play1 = 24;
            $play2 = 7;
            break;
          case '4':
            $play1 = 4;
            $play2 = 27;
            break;
          case '5':
            $play1 = 20;
            $play2 = 11;
            break;
          case '6':
            $play1 = 12;
            $play2 = 19;
            break;
          case '7':
            $play1 = 28;
            $play2 = 3;
            break;
          case '8':
            $play1 = 2;
            $play2 = 29;
            break;
          case '9':
            $play1 = 18;
            $play2 = 13;
            break;
          case '10':
            $play1 = 10;
            $play2 = 21;
            break;
          case '11':
            $play1 = 26;
            $play2 = 5;
            break;
          case '12':
            $play1 = 6;
            $play2 = 25;
            break;
          case '13':
            $play1 = 22;
            $play2 = 9;
            break;
          case '14':
            $play1 = 14;
            $play2 = 17;
            break;
          case '15':
            $play1 = 30;
            $play2 = 1;
            break;
        }

        if (isset($players[$play1])) {
          $fixture->registration1_id = $players[$play1]->id;
        } else {
          $fixture->registration1_id = 0;
        }




        if (isset($players[$play2])) {
          $fixture->registration2_id = $players[$play2]->id;
        } else {
          $fixture->registration2_id = 0;
        }
      } elseif ($i >= 16 && $i <= 23) {
        $fixture->round = 2;
      } elseif ($i >= 24 && $i <= 27) {
        $fixture->round = 3;
      } elseif ($i >= 28 && $i <= 29) {
        $fixture->round = 4;
      } elseif ($i == 30) {
        $fixture->round = 5;
      }
      if ($fixture->save()) {
        $response = "success - " . ($i + 1) . " fixtures created";
        $numfixtures += 1;
      }
    }
    //create 3-4 bracket

    for ($i = 0; $i < 1; $i++) {
      $fixture = new Fixture();
      $fixture->draw_id = $draw->id;
      $fixture->match_nr = $i + 1;
      $fixture->bracket_id = 2;
      $fixture->round = 1;
      if ($fixture->save()) {
        $response = "success - " . ($i + 1) . " fixtures created";
        $numfixtures += 1;
      }
    }



    //create gold bracket 5-6

    for ($i = 0; $i < 27; $i++) {
      $fixture = new Fixture();
      $fixture->draw_id = $draw->id;
      $fixture->match_nr = $i + 1;
      $fixture->bracket_id = 3;
      $fixture->round = 1;
      //have to insert round id


      if ($i < 8) {
        $fixture->round = 1;
      } elseif ($i >= 8 && $i <= 15) {
        $fixture->round = 2;
      } elseif ($i >= 16 && $i <= 19) {
        $fixture->round = 3;
      } elseif ($i >= 20 && $i <= 23) {
        $fixture->round = 4;
      } elseif ($i >= 24 && $i <= 25) {
        $fixture->round = 5;
      } elseif ($i == 26) {
        $fixture->round = 6;
      }




      if ($fixture->save()) {
        $response = "success - " . ($i + 1) . " fixtures created";
        $numfixtures += 1;
      }
    }

    //create 7-8 bracket

    for ($i = 0; $i < 1; $i++) {
      $fixture = new Fixture();
      $fixture->draw_id = $draw->id;
      $fixture->match_nr = $i + 1;
      $fixture->bracket_id = 4;
      $fixture->round = 1;
      if ($fixture->save()) {
        $response = "success - " . ($i + 1) . " fixtures created";
        $numfixtures += 1;
      }
    }
    //create 9-12 bracket

    for ($i = 0; $i < 4; $i++) {
      $fixture = new Fixture();
      $fixture->draw_id = $draw->id;
      $fixture->match_nr = $i + 1;
      $fixture->bracket_id = 5;
      if ($i < 2) {
        $fixture->round = 1;
      } elseif ($i == 2  || $i == 3) {
        $fixture->round = 2;
      }
      if ($fixture->save()) {
        $response = "success - " . ($i + 1) . " fixtures created";
        $numfixtures += 1;
      }
    }
    //create 13-16 bracket

    for ($i = 0; $i < 4; $i++) {
      $fixture = new Fixture();
      $fixture->draw_id = $draw->id;
      $fixture->match_nr = $i + 1;
      $fixture->bracket_id = 6;
      if ($i < 2) {
        $fixture->round = 1;
      } elseif ($i == 2 || $i == 3) {
        $fixture->round = 2;
      }
      if ($fixture->save()) {
        $response = "success - " . ($i + 1) . " fixtures created";
        $numfixtures += 1;
      }
    }
    // create 17-24 bracket
    for ($i = 0; $i < 12; $i++) {
      $fixture = new Fixture();
      $fixture->draw_id = $draw->id;
      $fixture->match_nr = $i + 1;
      $fixture->bracket_id = 7;
      if ($i < 4) {
        $fixture->round = 1;
      } elseif ($i >= 4 && $i <= 5 || $i >= 7 && $i <= 8) {
        $fixture->round = 2;
      } elseif ($i == 6 || $i == 9 || $i == 10 || $i == 11) {
        $fixture->round = 3;
      }
      if ($fixture->save()) {
        $response = "success - " . ($i + 1) . " fixtures created";
        $numfixtures += 1;
      }
    }


    // create 25-32 bracket
    for ($i = 0; $i < 12; $i++) {
      $fixture = new Fixture();
      $fixture->draw_id = $draw->id;
      $fixture->match_nr = $i + 1;
      $fixture->bracket_id = 8;
      if ($i < 4) {
        $fixture->round = 1;
      } elseif ($i >= 4 && $i <= 5 || $i >= 7 && $i <= 8) {
        $fixture->round = 2;
      } elseif ($i == 6 || $i == 9 || $i == 10 ||  $i == 11) {
        $fixture->round = 3;
      }
      if ($fixture->save()) {
        $response = "success - " . ($i + 1) . " fixtures created";
        $numfixtures += 1;
      }
    }

    // $response = Brackets::update_byes($draw->id);

    $draw->locked = 1;
    $draw->save();






    return $response;
  }

  public static function getNoProfileTeam($fixture, $t, $rank)
  {

    $search = $fixture->age;
    $region = null;

    if ($t === 1) {
      $region = TeamRegion::find($fixture->region1);
      $search = $fixture->age;
      // dd('region1',$region);
      $team = Team::where('region_id', $region->id)
        ->where('name', 'LIKE', '%' . $search . '%')
        ->get();
    } else {
      $region = TeamRegion::find($fixture->region2);
      // dd('regoin2',$region);
      $search = $fixture->age;
      $team = Team::where('region_id', $region->id)
        ->where('name', 'LIKE', '%' . $search . '%')
        ->get();
    }

    // if singles
    if ($fixture->fixture_type == 1) {
      if ($t === 1) {
        $region = TeamRegion::find($fixture->region1);
        $search = $fixture->age;
        // dd('region1',$region);
        $team = Team::where('region_id', $region->id)
          ->where('name', 'LIKE', '%' . $search . '%')
          ->get();
      } else {
        $region = TeamRegion::find($fixture->region2);
        // dd('regoin2',$region);
        $search = $fixture->age;
        $team = Team::where('region_id', $region->id)
          ->where('name', 'LIKE', '%' . $search . '%')
          ->get();
      }
      if (isset($team[0]->team_players_no_profile[($rank - 1)])) {
        return $team[0]->team_players_no_profile[($rank - 1)]->name . ' ' . $team[0]->team_players_no_profile[($rank - 1)]->surname;
      } else {
        return $region->region_name . ' nr ' . $rank;
      }

      // if singles reverse
    } else if ($fixture->fixture_type == 4) {

      if (isset($team[0]->team_players_no_profile[($rank - 1)])) {
        if ($t == 2) {

          $values = [1, 3, 5, 7];
          if (in_array($rank, $values)) {
            return $team[0]->team_players_no_profile[($rank)]->name . ' ' . $team[0]->team_players_no_profile[($rank)]->surname;
          } else {
            return $team[0]->team_players_no_profile[($rank - 2)]->name . ' ' . $team[0]->team_players_no_profile[($rank - 2)]->surname;
          }
        } else {


          return $team[0]->team_players_no_profile[($rank - 1)]->name . ' ' . $team[0]->team_players_no_profile[($rank - 1)]->surname;
        }
      } else {
        return $region->region_name . ' nr ' . $rank;
      }
    } else if ($fixture->fixture_type == 2) {

      if (isset($team[0]->team_players_no_profile[($rank - 1)])) {
       if($team[0]->noProfile == 1){
return $team[0]->team_players_no_profile[(($rank * 2) - 2)]->name . ' ' . $team[0]->team_players_no_profile[(($rank * 2) - 2)]->surname . '/' . $team[0]->team_players_no_profile[(($rank * 2) - 1)]->name . ' ' . $team[0]->team_players_no_profile[(($rank * 2) - 1)]->surname;;

       } else {
        return '';
       }
         } else {
        return $region->region_name . ' nr ' . $rank;
      }
    }
  }
  public static function getNoProfileMixedTeam($fixture, $t, $rank)
  {

    if ($t === 1) {
      $region = TeamRegion::find($fixture->region1);

      $search = $fixture->age;
      $search = str_replace(' ', '', $search);
      $cleaned = str_replace("Mixed", "", $search);

      // dd('region1',$region);
      $team = Team::where('region_id', $region->id)
        ->where('name', 'LIKE', '%' . $cleaned . '%')
        ->orderBy('name')
        ->get();
    } else {
      $region = TeamRegion::find($fixture->region2);

      $search = $fixture->age;
      $search = str_replace(' ', '', $search);
      $cleaned = str_replace("Mixed", "", $search);

      // dd('region1',$region);
      $team = Team::where('region_id', $region->id)
        ->where('name', 'LIKE', '%' . $cleaned . '%')
        ->orderBy('name')
        ->get();
    }

    if (isset($team[0]->team_players_no_profile[($rank - 1)])) {

      return $team[0]->team_players_no_profile[($rank - 1)]->name . ' ' . $team[0]->team_players_no_profile[($rank - 1)]->surname . '/' . $team[1]->team_players_no_profile[($rank - 1)]->name . ' ' . $team[1]->team_players_no_profile[($rank - 1)]->surname;;
    } else {

      return 'test';
      return $region->region_name . ' nr ' . $rank;
    }
  }
}
