<?php

namespace App\Classes;

use App\Models\Draw;
use App\Models\Fixture;
use App\Models\FixturePlayer;
use App\Models\FixtureResult;
use App\Models\OrderOfPlay;
use App\Models\Registration;
use App\Models\Result;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use stdClass;

class CapeTennisDraw
{
  public $draw_id, $registrations, $fixtures, $scheduledMatches;

  public function __construct($id)
  {
    $this->draw_id = $id;
    $this->registrations = $this->registrations();
    $this->fixtures = $this->fixtures();
    $this->scheduledMatches = $this->scheduled_matches();
  }

  public function fixtures()
  {
    $models = Fixture::select('fixtures.*')
      ->leftJoin('order_of_plays', 'fixtures.id', '=', 'order_of_plays.fixture_id')
      ->orderByRaw('CASE WHEN order_of_plays.time IS NULL THEN 1 ELSE 0 END, order_of_plays.time ASC')
      ->orderByRaw('bracket_id')
      ->where('fixtures.draw_id', $this->draw_id)
      ->get();

    return $models;
  }
  public function registrations()
  {
    return Draw::find($this->draw_id)->registrations;
  }

  public function scheduled_matches()
  {
    $scheduledMatches = Fixture::has('oop')
      ->with('oop')
      ->where('draw_id', $this->draw_id)
      ->get();
    return $scheduledMatches;
  }
  public static function fixture_result($fixture_id)
  {
    $fixture = Fixture::find($fixture_id);

    $data['num_sets'] = $fixture->results->count();
    $last_set = $fixture->results->last();
    if (isset($last_set->winner_registration)) {
      $data['match_winner']['reg'] = $last_set->winner_registration;
      $data['match_winner']['name'] = Registration::find(
        $last_set->winner_registration
      )->players[0]->getFullNameAttribute();
    }
    if (isset($last_set->loser_registration)) {
      $data['match_loser']['reg'] = $last_set->loser_registration;
      $data['match_loser']['name'] = Registration::find(
        $last_set->loser_registration
      )->players[0]->getFullNameAttribute();
    }

    $sets = $fixture->results;
    $data['score'] = null;
    for ($i = 0; $i < $data['num_sets']; $i++) {
      $data['score'] .= $sets[$i]->registration1_score . ' - ' . $sets[$i]->registration2_score;
      if ($i != $data['num_sets'] - 1) {
        $data['score'] .= ', ';
      }
    }

    return $data;
  }
  public static function getWinnerRegistration($fixture_id, $registration_id)
  {
    $results = FixtureResult::where('fixture_id', $fixture_id)
      ->orderBy('set_nr')
      ->get();
    if ($results->count() > 0) {
      $winnerReg = $results->last();

      if ($registration_id == $winnerReg->w_registration->id) {
        return 'success';
      } else {
        return 'danger';
      }
    } else {
      return '';
    }
  }

  public static function check_if_bye($fixture)
  {
    if ($fixture->registration1_id > 0 && $fixture->registration2_id > 0) {
      return 'false';
    }
  }

  public static function result($id)
  {
    $res = FixtureResult::where('fixture_id', $id)
      ->orderBy('id')
      ->get();
    $result = '';
    if (count($res) == 2) {
      $result =
        $res[0]->registration1_score .
        ' - ' .
        $res[0]->registration2_score .
        ', ' .
        $res[1]->registration1_score .
        ' - ' .
        $res[1]->registration2_score;
    } elseif (count($res) == 1) {
      $result = $res[0]->registration1_score . ' - ' . $res[0]->registration2_score;
    } elseif (count($res) == 3) {
      $result =
        $res[0]->registration1_score .
        ' - ' .
        $res[0]->registration2_score .
        ', ' .
        $res[1]->registration1_score .
        ' - ' .
        $res[1]->registration2_score .
        ', ' .
        ($result = $res[2]->registration1_score . ' - ' . $res[2]->registration2_score);
    }

    return $result;
  }

  public static function get_update_fixture($fixture, $win_or_loose)
  {
    if ($win_or_loose == 'winner') {
      if ($fixture->bracket_id == 1) {
        switch ($fixture->match_nr) {
          case '1':
            $match_nr = 17;
            break;
          case '2':
            $match_nr = 17;
            break;
          case '3':
            $match_nr = 18;
            break;
          case '4':
            $match_nr = 18;
            break;
          case '5':
            $match_nr = 19;
            break;
          case '6':
            $match_nr = 19;
            break;
          case '7':
            $match_nr = 20;
            break;
          case '8':
            $match_nr = 20;
            break;
          case '9':
            $match_nr = 21;
            break;
          case '10':
            $match_nr = 21;
            break;
          case '11':
            $match_nr = 22;
            break;
          case '12':
            $match_nr = 22;
            break;
          case '13':
            $match_nr = 23;
            break;
          case '14':
            $match_nr = 23;
            break;
          case '15':
            $match_nr = 24;
            break;
          case '16':
            $match_nr = 24;
            break;
          case '17':
            $match_nr = 25;
            break;
          case '18':
            $match_nr = 25;
            break;
          case '19':
            $match_nr = 26;
            break;
          case '20':
            $match_nr = 26;
            break;
          case '21':
            $match_nr = 27;
            break;
          case '22':
            $match_nr = 27;
            break;
          case '23':
            $match_nr = 28;
            break;
          case '24':
            $match_nr = 28;
            break;
          case '25':
            $match_nr = 29;
            break;
          case '26':
            $match_nr = 29;
            break;
          case '27':
            $match_nr = 30;
            break;
          case '28':
            $match_nr = 30;
            break;
          case '29':
            $match_nr = 31;
            break;
          case '30':
            $match_nr = 31;
            break;
        }
      } elseif ($fixture->bracket_id == 3) {
        switch ($fixture->match_nr) {
          case '1':
            $match_nr = 9;
            break;
          case '2':
            $match_nr = 10;
            break;
          case '3':
            $match_nr = 11;
            break;
          case '4':
            $match_nr = 12;
            break;
          case '5':
            $match_nr = 13;
            break;
          case '6':
            $match_nr = 14;
            break;
          case '7':
            $match_nr = 15;
            break;
          case '8':
            $match_nr = 16;
            break;
          case '9':
            $match_nr = 17;
            break;
          case '10':
            $match_nr = 17;
            break;
          case '11':
            $match_nr = 18;
            break;
          case '12':
            $match_nr = 18;
            break;
          case '13':
            $match_nr = 19;
            break;
          case '14':
            $match_nr = 19;
            break;
          case '15':
            $match_nr = 20;
            break;
          case '16':
            $match_nr = 20;
            break;
          case '17':
            $match_nr = 21;
            break;
          case '18':
            $match_nr = 22;
            break;
          case '19':
            $match_nr = 23;
            break;
          case '20':
            $match_nr = 24;
            break;
          case '21':
            $match_nr = 25;
            break;
          case '22':
            $match_nr = 25;
            break;
          case '23':
            $match_nr = 26;
            break;
          case '24':
            $match_nr = 26;
            break;
          case '25':
            $match_nr = 27;
            break;
          case '26':
            $match_nr = 27;
            break;
        }
      } elseif ($fixture->bracket_id == 5) {
        switch ($fixture->match_nr) {
          case '1':
            $match_nr = 3;
            break;
          case '2':
            $match_nr = 3;
            break;
        }
      } elseif ($fixture->bracket_id == 6) {
        switch ($fixture->match_nr) {
          case '1':
            $match_nr = 3;
            break;
          case '2':
            $match_nr = 3;
            break;
        }
      } elseif ($fixture->bracket_id == 7 || $fixture->bracket_id == 8) {
        switch ($fixture->match_nr) {
          case '1':
            $match_nr = 5;
            break;
          case '2':
            $match_nr = 5;
            break;
          case '3':
            $match_nr = 6;
            break;
          case '4':
            $match_nr = 6;
            break;
          case '5':
            $match_nr = 7;
            break;
          case '6':
            $match_nr = 7;
            break;
          case '8':
            $match_nr = 10;
            break;
          case '9':
            $match_nr = 10;
            break;
          case '12':
            $match_nr = 0;
            break;
        }
      } else {
        return dd('error - no bracket' . $fixture);
      }
      if (!$match_nr == 0) {
        $fix = Fixture::where('match_nr', $match_nr)
          ->where('draw_id', $fixture->draw_id)
          ->where('bracket_id', $fixture->bracket_id)
          ->get();
        return $fix[0]->id;
      } else {
        return 0;
      }
    } else {
      if ($fixture->bracket_id == 1) {
        switch ($fixture->match_nr) {
          case '1':
            $match_nr = 1;
            break;
          case '2':
            $match_nr = 1;
            break;
          case '3':
            $match_nr = 2;
            break;
          case '4':
            $match_nr = 2;
            break;
          case '5':
            $match_nr = 3;
            break;
          case '6':
            $match_nr = 3;
            break;
          case '7':
            $match_nr = 4;
            break;
          case '8':
            $match_nr = 4;
            break;
          case '9':
            $match_nr = 5;
            break;
          case '10':
            $match_nr = 5;
            break;
          case '11':
            $match_nr = 6;
            break;
          case '12':
            $match_nr = 6;
            break;
          case '13':
            $match_nr = 7;
            break;
          case '14':
            $match_nr = 7;
            break;
          case '15':
            $match_nr = 8;
            break;
          case '16':
            $match_nr = 8;
            break;
          case '17':
            $match_nr = 16;
            break;
          case '18':
            $match_nr = 15;
            break;
          case '19':
            $match_nr = 14;
            break;
          case '20':
            $match_nr = 13;
            break;
          case '21':
            $match_nr = 12;
            break;
          case '22':
            $match_nr = 11;
            break;
          case '23':
            $match_nr = 10;
            break;
          case '24':
            $match_nr = 9;
            break;
          case '25':
            $match_nr = 21;
            break;
          case '26':
            $match_nr = 22;
            break;
          case '27':
            $match_nr = 23;
            break;
          case '28':
            $match_nr = 24;
            break;
          case '29':
            $match_nr = 1;
            break;
          case '30':
            $match_nr = 1;
            break;
        }
      } elseif ($fixture->bracket_id == 3) {
        switch ($fixture->match_nr) {
          case '1':
            $match_nr = 1;
            break;
          case '2':
            $match_nr = 1;
            break;
          case '3':
            $match_nr = 2;
            break;
          case '4':
            $match_nr = 2;
            break;
          case '5':
            $match_nr = 3;
            break;
          case '6':
            $match_nr = 3;
            break;
          case '7':
            $match_nr = 4;
            break;
          case '8':
            $match_nr = 4;
            break;
          case '9':
            $match_nr = 1;
            break;
          case '10':
            $match_nr = 1;
            break;
          case '11':
            $match_nr = 2;
            break;
          case '12':
            $match_nr = 2;
            break;
          case '13':
            $match_nr = 3;
            break;
          case '14':
            $match_nr = 3;
            break;
          case '15':
            $match_nr = 4;
            break;
          case '16':
            $match_nr = 4;
            break;
          case '17':
            $match_nr = 1;
            break;
          case '18':
            $match_nr = 1;
            break;
          case '19':
            $match_nr = 2;
            break;
          case '20':
            $match_nr = 2;
            break;
          case '21':
            $match_nr = 1;
            break;
          case '22':
            $match_nr = 1;
            break;
          case '23':
            $match_nr = 2;
            break;
          case '24':
            $match_nr = 2;
            break;
          case '25':
            $match_nr = 1;
            break;
          case '26':
            $match_nr = 1;
            break;
        }
      } elseif ($fixture->bracket_id == 5) {
        switch ($fixture->match_nr) {
          case '1':
            $match_nr = 4;
            break;
          case '2':
            $match_nr = 4;
            break;
        }
      } elseif ($fixture->bracket_id == 6) {
        switch ($fixture->match_nr) {
          case '1':
            $match_nr = 4;
            break;
          case '2':
            $match_nr = 4;
            break;
        }
      } elseif ($fixture->bracket_id == 7 || $fixture->bracket_id == 8) {
        switch ($fixture->match_nr) {
          case '1':
            $match_nr = 8;
            break;
          case '2':
            $match_nr = 8;
            break;
          case '3':
            $match_nr = 9;
            break;
          case '4':
            $match_nr = 9;
            break;
          case '5':
            $match_nr = 11;
            break;
          case '6':
            $match_nr = 11;
            break;
          case '8':
            $match_nr = 12;
            break;
          case '9':
            $match_nr = 12;
            break;
          case '12':
            $match_nr = 0;
            break;
        }
      }
      switch ($fixture->bracket_id) {
        case '1':
          if ($fixture->round == 4) {
            $new_brack = 2;
          } elseif ($fixture->round == 5) {
            $new_brack = 1;
          } else {
            $new_brack = 3;
          }

          break;
        case '3':
          if ($fixture->round == 1) {
            $new_brack = 8;
          } elseif ($fixture->round == 2) {
            $new_brack = 7;
          } elseif ($fixture->round == 3) {
            $new_brack = 6;
          } elseif ($fixture->round == 4) {
            $new_brack = 5;
          } elseif ($fixture->round == 5) {
            $new_brack = 4;
          }
          break;
        default:
          $new_brack = $fixture->bracket_id;
          break;
      }

      if (!$match_nr == 0) {
        $fix = Fixture::where('match_nr', $match_nr)
          ->where('draw_id', $fixture->draw_id)
          ->where('bracket_id', $new_brack)
          ->get();
        return $fix[0]->id;
      } else {
        return 0;
      }
    }
  }
  public static function update_winner_fixture($to_update_fix_id, $winner_registration, $from_fixture)
  {
    $fixture_to_update = Fixture::find($to_update_fix_id);
    //even
    if ($from_fixture->match_nr % 2 == 0) {
      if ($from_fixture->bracket_id == 3 && $from_fixture->round == 1) {
        $fixture_to_update->registration1_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 2) {
        $fixture_to_update->registration2_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 3) {
        $fixture_to_update->registration1_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 4) {
        $fixture_to_update->registration2_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 8) {
        $fixture_to_update->registration1_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 9) {
        $fixture_to_update->registration2_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 3) {
        $fixture_to_update->registration1_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 2 && $from_fixture->match_nr == 8) {
        $fixture_to_update->registration1_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 3) {
        $fixture_to_update->registration1_id = $winner_registration;
      } else {
        $fixture_to_update->registration2_id = $winner_registration;
      }
    }
    //odd
    else {
      if ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 5) {
        $fixture_to_update->registration1_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 9) {
        $fixture_to_update->registration2_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 3) {
        $fixture_to_update->registration2_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 5) {
        $fixture_to_update->registration1_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 2 && $from_fixture->match_nr == 9) {
        $fixture_to_update->registration2_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 2 && $from_fixture->match_nr == 5) {
        $fixture_to_update->registration1_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 3) {
        $fixture_to_update->registration2_id = $winner_registration;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 2) {
        $fixture_to_update->registration2_id = $winner_registration;
      } else {
        $fixture_to_update->registration1_id = $winner_registration;
      }
    }
    if ($fixture_to_update->save()) {
      return $fixture_to_update;
    } else {
      return 'error in update';
    }
  }

  public static function update_loser_fixture($to_update_fix_id, $loser_regisistration, $from_fixture)
  {
    $fixture_to_update = Fixture::find($to_update_fix_id);
    if ($from_fixture->match_nr % 2 == 0) {
      if ($from_fixture->bracket_id == 3 && $from_fixture->round == 2) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 5 && $from_fixture->round == 1) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 1) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 2) {
        $fixture_to_update->registration1_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 1) {
        if ($from_fixture->match_nr == 5) {
          $fixture_to_update->registration1_id = $loser_regisistration;
        } else {
          $fixture_to_update->registration2_id = $loser_regisistration;
        }
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 2) {
        $fixture_to_update->registration1_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 6 && $from_fixture->round == 1) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 3) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 4) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 5) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 1 && $from_fixture->round == 1) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 1) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 1 && $from_fixture->round == 4) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id < 4 && $from_fixture->round == 2) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id < 4 && $from_fixture->round == 3) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } else {
        $fixture_to_update->registration1_id = $loser_regisistration;
      }
    } else {
      if ($from_fixture->bracket_id == 3 && $from_fixture->round == 2) {
        $fixture_to_update->registration1_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 5 && $from_fixture->round == 1) {
        $fixture_to_update->registration1_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 1) {
        $fixture_to_update->registration1_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 2) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 2) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 1) {
        $fixture_to_update->registration1_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 6 && $from_fixture->round == 1) {
        $fixture_to_update->registration1_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 3) {
        $fixture_to_update->registration1_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 4) {
        $fixture_to_update->registration1_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 5) {
        $fixture_to_update->registration1_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id < 4 && $from_fixture->round == 2) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id < 4 && $from_fixture->round == 3) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id < 1 && $from_fixture->round == 1) {
        $fixture_to_update->registration1_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 1) {
        $fixture_to_update->registration1_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id == 1 && $from_fixture->round == 4) {
        $fixture_to_update->registration1_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id < 4 && $from_fixture->round == 2) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } elseif ($from_fixture->bracket_id < 4 && $from_fixture->round == 3) {
        $fixture_to_update->registration2_id = $loser_regisistration;
      } else {
        $fixture_to_update->registration1_id = $loser_regisistration;
      }
    }
    if ($fixture_to_update->save());
    return $to_update_fix_id;
  }

  public static function run_update($fixture, $loser_registration, $winner_registration)
  {
    $match_to_update_winner = Brackets::get_update_fixture($fixture, 'winner');

    $match_to_update_loser = Brackets::get_update_fixture($fixture, 'loser');
    //Brackets::update_byes($fixture->draw_id);

    $match_from_update = $fixture;

    $res['winner']['fixture'] = Brackets::update_winner_fixture($match_to_update_winner, $winner_registration, $match_from_update);

    $res['loser'] = Brackets::update_loser_fixture($match_to_update_loser, $loser_registration, $match_from_update);

   // $res['previousResult'] = CapeTennisDraw::checkForPreviousResult($match_to_update_winner,$match_to_update_loser);

    return $res;
  }

  public function run_delete_update($fixture)
  {
    $response['winMatch'] = Fixture::find(Brackets::get_update_fixture($fixture, 'winner'));

    $response['looseMatch'] = Fixture::find(Brackets::get_update_fixture($fixture, 'loser'));
    $winReg =  $this->regToDeleteWinner($fixture);
    $loserReg =  $this->regToDeleteLoser($fixture);


    if($winReg === 1){
      $response['winMatch']->registration1_id = null;
      $response['winMatch']->save();
    }else{
      $response['winMatch']->registration2_id = null;
      $response['winMatch']->save();
    }
    if($loserReg === 1){
      $response['looseMatch']->registration1_id = null;
      $response['looseMatch']->save();
    }else{
      $response['looseMatch']->registration2_id = null;
      $response['looseMatch']->save();
    }

   // $response['looseregToUpdate'] = $this->regToDeleteLoser($fixture);
    return $response;
  }

  public function regToDeleteWinner($from_fixture)
  {
    // $fixture_to_update = Fixture::find($to_update_fix_id);
    //even
    if ($from_fixture->match_nr % 2 == 0) {
      if ($from_fixture->bracket_id == 3 && $from_fixture->round == 1) {
        return 1;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 2) {
        return 2;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 3) {
        return 1;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 4) {
        return 2;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 8) {
        return 1;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 9) {
        return 2;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 3) {
        return 1;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 2 && $from_fixture->match_nr == 8) {
        return 1;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 3) {
        return 1;
      } else {
        return 2;
      }
    }
    //odd
    else {
      if ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 5) {
        return 1;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 9) {
        return 2;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 3) {
        return 2;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 5) {
        return 1;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 2 && $from_fixture->match_nr == 9) {
        return 2;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 2 && $from_fixture->match_nr == 5) {
        return 1;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 3) {
        return 2;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 2) {
        return 2;
      } else {
        return 1;
      }
    }
  }

  public function regToDeleteLoser($from_fixture){

    //even
    if ($from_fixture->match_nr % 2 == 0) {
      if ($from_fixture->bracket_id == 3 && $from_fixture->round == 1) {
        return 1;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 2) {
        return 2;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 3) {
        return 1;
      } elseif ($from_fixture->bracket_id == 3 && $from_fixture->round == 4) {
        return 2;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 8) {
        return 1;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 9) {
        return 2;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 3) {
        return 1;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 2 && $from_fixture->match_nr == 8) {
        return 1;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 3) {
        return 1;
      } else {
        return 2;
      }
    }
    //odd
    else {
      if ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 5) {
        return 1;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 9) {
        return 2;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 3) {
        return 2;
      } elseif ($from_fixture->bracket_id == 8 && $from_fixture->round == 2 && $from_fixture->match_nr == 5) {
        return 1;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 2 && $from_fixture->match_nr == 9) {
        return 2;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 2 && $from_fixture->match_nr == 5) {
        return 1;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 3) {
        return 2;
      } elseif ($from_fixture->bracket_id == 7 && $from_fixture->round == 2) {
        return 2;
      } else {
        return 1;
      }
    }
  }

  public static function checkForPreviousResult($fixtureToCheckWinner,$fixtureToCheckLoser)
  {
    $winnerFixture = Fixture::find($fixtureToCheckWinner);
    $drawFixtures = $winnerFixture->draws->drawFixtures;
    $wr1 = $winnerFixture->registration1_id;
    $wr2 = $winnerFixture->registration2_id;
$f = Fixture::where('registration1_id',$wr1)->where('registration2_id',$wr2)->where('draw_id',$winnerFixture->draws->id)->get();
return $f;
    $loserFixture = Fixture::find($fixtureToCheckLoser);
    $lr1 = $loserFixture->registration1_id;
    $lr2 = $loserFixture->registration2_id;


    return $drawFixtures;
  }

  public function getFixtureFrom($fixture)
  {
    return $fixture->id;
  }
}
