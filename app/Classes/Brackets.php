<?php

namespace App\Classes;

use App\Models\Draw;
use App\Models\Fixture;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Brackets
{
  public function __construct() {}

  public static function topLine($x, $x2, $y, $y2)
  {
    return "<line id='line_top' y1=$y y2=$y2 x1=$x x2=$x2 stroke='black'></line>";
  }
  public static function bottomLine($x, $x2, $y, $y2)
  {
    return "<line id='line_top' y1=$y y2=$y2 x1=$x x2=$x2 stroke='black'></line>";
  }
  public static function downLine($x, $x2, $y, $y2)
  {
    return "<line id='line_top' y1=$y y2=$y2 x1=$x x2=$x2 stroke='black'></line>";
  }

  public static function player1($x, $y, $bracket_fix)
  {
    $data = '';
   // dd($bracket_fix);
    if ($bracket_fix->registration1_id !== null) {
      if ($bracket_fix->registration1_id == 0) {
        $data .= "<text font-family='Noto Sans JP' font-size='20' id='svg_43' y=$y x=$x font-weight='bold'> Bye </text>";
      } else {
        $data .= "<text font-family='Noto Sans JP' font-size='20' id='svg_43' y=$y x=$x font-weight='bold'>";

        $data .= $bracket_fix->registrations1->players[0]->getFullNameAttribute() . '</text>';
      }
    } else {
    }

    return $data;
  }

  public static function match_nr($x, $y, $bracket_fix)
  {
    $data = '';

    $data .=
      "<text  font-family='Noto Sans JP' font-size=8' id='svg_43' y='$y' x='$x'> (" . $bracket_fix->id . ')</text>';
    return $data;
  }
  public static function play_time($x, $y, $bracket_fix)
  {
    // Base offsets
    $originalY = $y - 10; // existing design
    $xvenue = $x;

    // Guest → move DOWN by 5px
    if (Auth::guest()) {
      $originalY += 5;   // only guests shifted down
      $x += 0;          // your existing guest horizontal shift
    }

    $yvenue = $originalY + 20;

    if (!$bracket_fix->oop) {
      return '';
    }

    // Visibility
    $draw = $bracket_fix->draw;
    $isPublished = optional($draw)->oop_published == 1;
    $isAdmin = Auth::check() && Auth::user()->id == 584;

    if (!($isPublished || $isAdmin)) {
      return '';
    }

    // Format data
    $newtime = strtotime($bracket_fix->oop->time);
    $timeFormatted = date('l g:i A', $newtime);
    $venue = $bracket_fix->oop->venue->name ?? '';

    // Render
    $data = "<text fill='red' font-family='Noto Sans JP' font-size='14' y='$originalY' x='$x'>";
    $data .= "Not Before: $timeFormatted</text>";

    $data .= "<text fill='red' font-family='Noto Sans JP' font-size='16' y='$yvenue' x='$xvenue'>";
    $data .= $venue . "</text>";

    return $data;
  }

  public static function match_score($x, $y, $bracket_fix)
  {
    $data = '';

    $data .= "<text font-family='Noto Sans JP' font-size=12' id='svg_43' y=$y x=$x>";
    $data .= Brackets::getResultForDraw($bracket_fix) == null ? '' : Brackets::getResultForDraw($bracket_fix);

    $data .= '</text>';

    return $data;
  }

  public static function player2($x, $y, $bracket_fix)
  {
    $data = '';

    if ($bracket_fix->registration2_id !== null) {
      if ($bracket_fix->registration2_id < 1) {
        $data .= "<text font-family='Noto Sans JP' font-size='20' id='svg_43' y=$y x=$x font-weight='bold'> Bye </text>";
      } else {
        $data .= "<text font-family='Noto Sans JP' font-size='20' id='svg_43' y=$y x=$x font-weight='bold'>";

        $data .=
          $bracket_fix->registrations2->players[0]->name .
          ' ' .
          $bracket_fix->registrations2->players[0]->surname .
          '</text>';
      }
    } else {
    }

    return $data;
  }
  public static function block($leftMargin, $topMargin, $width, $height)
  {
    return "<rect style='stroke:rgb(0,0,0);fill:none' id='svg_41' height=$height width=$width y=$leftMargin x=$topMargin />";
  }

  public static function matchup($leftMargin, $topMargin, $round, $bracket_fix)
  {
    $width = 200;

    switch ($round) {
      case 1:
        $height = 80;
        break;

      case 2:
        $height = 160;
        break;

      case 3:
        $height = 320;
        break;

      case 4:
        $height = 640;
        break;

      case 5:
        $height = 1280;
        break;
    }

    //10.10.200,60

    $brack = '';

    $brack .= Brackets::topLine($leftMargin, $leftMargin + $width, $topMargin, $topMargin);

    $brack .= Brackets::bottomLine(
      $leftMargin,
      $leftMargin + $width,
      $topMargin + $height - $height / 4,
      $topMargin + $height - $height / 4
    );
    $brack .= Brackets::downLine(
      $leftMargin + $width,
      $leftMargin + $width,
      $topMargin,
      $topMargin + $height - $height / 4
    );
    $brack .= Brackets::player1($leftMargin + 5, $topMargin - 4, $bracket_fix);
    $brack .= Brackets::player2($leftMargin + 5, $topMargin + $height - $height / 4 - 4, $bracket_fix);

    if (!Auth::guest()) {
      if (Auth::user()->id == 0) {
        $brack .= Brackets::match_nr($leftMargin, $topMargin + $height / 2 - 10, $bracket_fix);
      }
    }

    $brack .= Brackets::match_score($leftMargin + 210, $topMargin + $height / 2 + 5, $bracket_fix);
    $brack .= Brackets::play_time($leftMargin, $topMargin + $height / 2 - 15, $bracket_fix);

    echo $brack;
    return $height + $height / 2;
  }
  public static function matchup_winner($y1, $y2, $x1, $x2)
  {
    $data = '';

    $data .= "<line y1='$y1' y2='$y2' x1='$x1' x2='$x2' stroke='black'></line>";

    return $data;
  }

  public static function get_bracket_plat($draw)
  {
    echo '<svg width="1200" height="1900" >
        <g xmlns="http://www.w3.org/2000/svg">';

    $start = 20;

    foreach ($draw->bracket_fixtures(1, 0, 16) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start, 1, $bracket_fix);
    }

    $start = 40;

    foreach ($draw->bracket_fixtures(1, 17, 24) as $key => $bracket_fix) {
      $start += Brackets::matchup(210, $start, 2, $bracket_fix);
    }

    $start = 80;

    foreach ($draw->bracket_fixtures(1, 25, 28) as $key => $bracket_fix) {
      $start += Brackets::matchup(410, $start, 3, $bracket_fix);
    }

    $start = 160;

    foreach ($draw->bracket_fixtures(1, 29, 30) as $key => $bracket_fix) {
      $start += Brackets::matchup(610, $start, 4, $bracket_fix);
    }

    $start = 320;

    foreach ($draw->bracket_fixtures(1, 31, 31) as $key => $bracket_fix) {
      $start += Brackets::matchup(810, $start, 5, $bracket_fix);
      $line = Brackets::topLine(1010, 1210, 900, 900);
      echo $line;
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=895 x=1020 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    echo '</g>
    </svg>';
  }

  public static function get_bracket_3_4($draw)
  {
    echo '<div class="mb-3"><h3>Draw - Position 3-4</h3></div>';
    echo '<svg width="1600" height="100" >
        <g xmlns="http://www.w3.org/2000/svg">';

    $start = 20;

    foreach ($draw->bracket_fixtures(2, 1, 1) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start, 1, $bracket_fix);
      echo Brackets::topLine(210, 410, 50, 50);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=45 x=215 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    echo '</g>
    </svg>';
  }
  public static function get_bracket_gold($draw, $bracket)
  {
    echo '<div class="mb-3"><h3>Gold Draw - Position 5-6</h3></div>';
    echo '<svg width="1600" height="1000">
        <g xmlns="http://www.w3.org/2000/svg">';

    $start = 20;

    foreach ($draw->bracket_fixtures($bracket, 0, 8) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start, 1, $bracket_fix);
    }

    $start = 40;

    foreach ($draw->bracket_fixtures($bracket, 9, 16) as $key => $bracket_fix) {
      $start += Brackets::matchup(210, $start + 10, 1, $bracket_fix);
    }

    $start = 80;

    foreach ($draw->bracket_fixtures($bracket, 17, 20) as $key => $bracket_fix) {
      $start += Brackets::matchup(410, $start, 2, $bracket_fix);
    }

    $start = 160;

    foreach ($draw->bracket_fixtures($bracket, 21, 24) as $key => $bracket_fix) {
      $start += Brackets::matchup(610, $start - 20, 2, $bracket_fix);
    }

    $start = 320;

    foreach ($draw->bracket_fixtures($bracket, 25, 26) as $key => $bracket_fix) {
      $start += Brackets::matchup(810, $start - 120, 3, $bracket_fix);
    }
    $start = 320;

    foreach ($draw->bracket_fixtures($bracket, 27, 27) as $key => $bracket_fix) {
      $start += Brackets::matchup(1010, $start, 4, $bracket_fix);
      echo Brackets::topLine(1210, 1410, 540, 540);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=535 x=1215 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    echo '</g>
    </svg>';
  }

  public static function get_bracket_7_8($draw, $bracket)
  {
    echo '<div class="mb-3"><h3>Draw - Position 7-8</h3></div>';
    echo '<svg width="1600" height="150">
        <g xmlns="http://www.w3.org/2000/svg">';

    $start = 20;

    foreach ($draw->bracket_fixtures($bracket, 1, 1) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start, 1, $bracket_fix);
      echo Brackets::topLine(210, 410, 50, 50);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=45 x=215 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    echo '</g>
    </svg>';
  }

  public static function get_bracket_9_12($draw, $bracket)
  {
    echo '<div class="mb-3"><h3>Draw - Position 9-12</h3></div>';
    echo '<svg width="1600" height="400">
        <g xmlns="http://www.w3.org/2000/svg">';

    $start = 20;

    foreach ($draw->bracket_fixtures($bracket, 1, 2) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start, 1, $bracket_fix);
    }
    $start = 40;

    foreach ($draw->bracket_fixtures($bracket, 3, 3) as $key => $bracket_fix) {
      $start += Brackets::matchup(210, $start + 10, 2, $bracket_fix);
      echo Brackets::topLine(410, 610, 110, 110);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=105 x=415 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    $start = 250;
    foreach ($draw->bracket_fixtures($bracket, 4, 4) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start, 1, $bracket_fix);
      echo Brackets::topLine(210, 410, 280, 280);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=275 x=215 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    echo '</g>
    </svg>';
  }
  public static function get_bracket_13_16($draw, $bracket)
  {
    echo '<div class="mb-3"><h3>Draw - Position 13-16</h3></div>';
    echo '<svg width="1600" height="400">
        <g xmlns="http://www.w3.org/2000/svg">';

    $start = 20;

    foreach ($draw->bracket_fixtures($bracket, 1, 2) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start, 1, $bracket_fix);
    }
    $start = 40;

    foreach ($draw->bracket_fixtures($bracket, 3, 3) as $key => $bracket_fix) {
      $start += Brackets::matchup(210, $start + 10, 2, $bracket_fix);
      echo Brackets::topLine(410, 610, 110, 110);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=105 x=415 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    $start = 250;
    foreach ($draw->bracket_fixtures($bracket, 4, 4) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start, 1, $bracket_fix);
      echo Brackets::topLine(210, 410, 280, 280);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=275 x=215 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    echo '</g>
    </svg>';
  }
  public static function get_bracket_17_24($draw, $bracket)
  {
    echo '<div class="mb-3"><h3>Draw - Position 17-24</h3></div>';
    echo '<svg width="1600" height="1050">
        <g xmlns="http://www.w3.org/2000/svg">';

    $start = 20;

    foreach ($draw->bracket_fixtures($bracket, 1, 4) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start, 1, $bracket_fix);
    }
    $start = 40;

    foreach ($draw->bracket_fixtures($bracket, 5, 6) as $key => $bracket_fix) {
      $start += Brackets::matchup(210, $start + 10, 2, $bracket_fix);
    }

    $start = 80;
    foreach ($draw->bracket_fixtures($bracket, 7, 7) as $key => $bracket_fix) {
      $start += Brackets::matchup(410, $start + 10, 3, $bracket_fix);
      echo Brackets::topLine(610, 810, 200, 200);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=195 x=615 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    $start = 500;

    foreach ($draw->bracket_fixtures($bracket, 11, 11) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start - 10, 1, $bracket_fix);
      echo Brackets::topLine(210, 410, 520, 520);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=515 x=215 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }
    $start = 600;
    foreach ($draw->bracket_fixtures($bracket, 8, 9) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start, 1, $bracket_fix);
    }

    $start = 640;

    foreach ($draw->bracket_fixtures($bracket, 10, 10) as $key => $bracket_fix) {
      $start += Brackets::matchup(210, $start - 10, 2, $bracket_fix);
      echo Brackets::topLine(410, 610, 690, 690);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=685 x=415 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    $start = 840;

    foreach ($draw->bracket_fixtures($bracket, 12, 12) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start - 10, 1, $bracket_fix);
      echo Brackets::topLine(210, 410, 860, 860);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=855 x=215 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    echo '</g>
    </svg>';
  }
  public static function get_bracket_25_32($draw, $bracket)
  {
    echo '<div class="mb-3"><h3>Draw - Position 25-32</h3></div>';
    echo '<svg width="1600" height="1050">
        <g xmlns="http://www.w3.org/2000/svg">';

    $start = 20;

    foreach ($draw->bracket_fixtures($bracket, 1, 4) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start, 1, $bracket_fix);
    }
    $start = 40;

    foreach ($draw->bracket_fixtures($bracket, 5, 6) as $key => $bracket_fix) {
      $start += Brackets::matchup(210, $start + 10, 2, $bracket_fix);
    }

    $start = 80;
    foreach ($draw->bracket_fixtures($bracket, 7, 7) as $key => $bracket_fix) {
      $start += Brackets::matchup(410, $start + 10, 3, $bracket_fix);
      echo Brackets::topLine(610, 810, 200, 200);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=195 x=615 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    $start = 500;

    foreach ($draw->bracket_fixtures($bracket, 11, 11) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start - 10, 1, $bracket_fix);
      echo Brackets::topLine(210, 410, 520, 520);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=515 x=215 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }
    $start = 600;
    foreach ($draw->bracket_fixtures($bracket, 8, 9) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start, 1, $bracket_fix);
    }

    $start = 640;

    foreach ($draw->bracket_fixtures($bracket, 10, 10) as $key => $bracket_fix) {
      $start += Brackets::matchup(210, $start - 10, 2, $bracket_fix);
      echo Brackets::topLine(410, 610, 690, 690);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=685 x=415 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    $start = 840;

    foreach ($draw->bracket_fixtures($bracket, 12, 12) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start - 10, 1, $bracket_fix);
      echo Brackets::topLine(210, 410, 860, 860);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=855 x=215 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    echo '</g>
    </svg>';
  }
  public static function get_bracket_8_playoff($draw, $bracket)
  {
    echo '<div class="mb-3"><h3>Draw - Position 25-32</h3></div>';
    echo '<svg width="1600" height="1050">
        <g xmlns="http://www.w3.org/2000/svg">';

    $start = 20;

    foreach ($draw->bracket_fixtures($bracket, 1, 4) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start, 1, $bracket_fix);
    }
    $start = 40;

    foreach ($draw->bracket_fixtures($bracket, 5, 6) as $key => $bracket_fix) {
      $start += Brackets::matchup(210, $start + 10, 2, $bracket_fix);
    }

    $start = 80;
    foreach ($draw->bracket_fixtures($bracket, 7, 7) as $key => $bracket_fix) {
      $start += Brackets::matchup(410, $start + 10, 3, $bracket_fix);
      echo Brackets::topLine(610, 810, 200, 200);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=195 x=615 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    $start = 500;

    foreach ($draw->bracket_fixtures($bracket, 11, 11) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start - 10, 1, $bracket_fix);
      echo Brackets::topLine(210, 410, 520, 520);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=515 x=215 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }
    $start = 600;
    foreach ($draw->bracket_fixtures($bracket, 8, 9) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start, 1, $bracket_fix);
    }

    $start = 640;

    foreach ($draw->bracket_fixtures($bracket, 10, 10) as $key => $bracket_fix) {
      $start += Brackets::matchup(210, $start - 10, 2, $bracket_fix);
      echo Brackets::topLine(410, 610, 690, 690);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=685 x=415 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    $start = 840;

    foreach ($draw->bracket_fixtures($bracket, 12, 12) as $key => $bracket_fix) {
      $start += Brackets::matchup(10, $start - 10, 1, $bracket_fix);
      echo Brackets::topLine(210, 410, 860, 860);
      echo "<text font-family='Noto Sans JP' font-size='16' id='svg_43' y=855 x=215 font-weight='bold'>" .
        Brackets::get_winner_name($bracket_fix) .
        '</text>';
    }

    echo '</g>
    </svg>';
  }
  public static function result_template($sets)
  {
    if ($sets == 3) {
      $form = '';
      $form .= '<table class="table"><thead style="background-color: darksalmon;"><tr>
               <td></td>
               <td><label for="p1"></label></td>
               <td>vs</td>
               <td><label for="p2"></label></td>

           </tr></thead><tbody>
           <tr>
               <td>Set 1</td>
               <td><input type="text" name="set_1_score1" size="4"></td>
               <td> vs </td>
               <td><input type="text" name="set_1_score2" size="4"></td>

           </tr>
           <tr id="2ndSet">
               <td>Set 2</td>
               <td><input type="text" name="set_2_score1" size="4"></td>
               <td> vs </td>
               <td><input type="text" name="set_2_score2" size="4"></td>

           </tr>
           <tr style="display:none" id="3rdSet">
               <td>Set 3</td>
               <td><input type="text" name="set_3_score1" size="4"></td>
               <td> vs </td>
               <td><input type="text" " name="set_3_score2" size="4"></td>

           </tr>
       </tbody></table>';

      $script = "<script> $(document).on('keyup', '#2ndSet', function() {
                var p1s1 = $('input[name=set_1_score1]').val();
                var p2s1 = $('input[name=set_1_score2]').val();
                var p1s2 = $('input[name=set_2_score1]').val();
                var p2s2 = $('input[name=set_2_score2]').val();

                if (parseInt(p1s1) < parseInt(p2s1)) {
                    if (parseInt(p1s2) > parseInt(p2s2)) {
                        $('#3rdSet').show();
                    }

                } else if (parseInt(p1s1) > parseInt(p2s1)) {
                    if (parseInt(p1s2) < parseInt(p2s2)) {
                        $('#3rdSet').show();

                    }
                } else {
                    $('#3rdSet').hide;
                }
            })


            </script>";
      echo $form;
      echo $script;
    }
  }
  public static function getResult($fixture)
  {
    $sets = $fixture->results;
    $numsets = count($sets);
    $result = '';
    for ($i = 0; $i < $numsets; $i++) {
      $result .= $sets[$i]->registration1_score . ' - ' . $sets[$i]->registration2_score . ' ';
    }
    return $result;
  }
  public static function getResultPlayoffs($fixture)
  {
    $p1 = $fixture->registration1_id;
    $sets = $fixture->results;
    $numsets = count($sets);
    $result = '';
    if (isset($sets[0])) {
      if ($sets[0]->winner_registration == $p1) {
        for ($i = 0; $i < $numsets; $i++) {
          if ($i > 0) {
            $result .= ' ;';
          }
          $result .= $sets[$i]->registration1_score . ' - ' . $sets[$i]->registration2_score . ' ';
        }
      } else {
        for ($i = 0; $i < $numsets; $i++) {
          if ($i > 0) {
            $result .= ' ;';
          }
          $result .= $sets[$i]->registration2_score . ' - ' . $sets[$i]->registration1_score . ' ';
        }
      }
    }

    return $result;
  }
  public static function getResultForDraw($fixture)
  {
    $sets = $fixture->fixtureResults;
    $numsets = count($sets);
    $result = '';
    $winnner = $fixture->winner_registration;

    for ($i = 0; $i < $numsets; $i++) {
      if ($sets[$numsets - 1]->winner_registration == $fixture->registration1_id) {
        $result .= $sets[$i]->registration1_score . ' - ' . $sets[$i]->registration2_score . ' ';
      } else {
        $result .= $sets[$i]->registration2_score . ' - ' . $sets[$i]->registration1_score . ' ';
      }
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
    return $fixture_to_update;
  }

  public static function get_winner_name($fixture)
  {
    if (count($fixture->fixtureResults) > 0) {
      $results = $fixture->fixtureResults;
      return $results[0]->w_registration->players[0]->name . ' ' . $results[0]->w_registration->players[0]->surname;
    } else {
      return '';
    }
  }

  public static function run_update($fixture, $loser_registration, $winner_registration)
  {
    $match_to_update_winner = Brackets::get_update_fixture($fixture, 'winner');

    $match_to_update_loser = Brackets::get_update_fixture($fixture, 'loser');
    //Brackets::update_byes($fixture->draw_id);

    $match_from_update = $fixture;

    Brackets::update_winner_fixture($match_to_update_winner, $winner_registration, $match_from_update);
    Brackets::update_loser_fixture($match_to_update_loser, $loser_registration, $match_from_update);

    return 'success';
  }
  public static function fixtures_for_schedule($draw)
  {
    $fixtures = $draw->fixtures_in_draw_day($draw->id);
    $table = '';
    $table .= '<div class="table-responsive">';
    $table .= '<table id="exampl" class="table table-bordered table-striped">';
    $table .= '<thead>';
    $table .= '<tr>';

    $table .= '<th width="5%">Fixture Id</th>';
    $table .= '<th width="5%">Match nr</th>';
    $table .= '<th width="15%">Bracket</th>';
    $table .= '<th width="10%">choose Fixture</th>';
    $table .= '<th width="15%">Registration 1</th>';
    $table .= '<th width="5%">VS</th>';
    $table .= '<th width="15%">Registration 2</th>';

    $table .= '<th></th>';

    $table .= '</tr>';
    $table .= '</thead>';

    $table .= '<tbody>';
    foreach ($fixtures as $key => $fixture) {
      $table .= '<tr>';

      $table .= '<td>' . $fixture->id . '</td>';
      $table .= '<td>' . $fixture->match_nr . '</td>';
      $table .= '<td>' . $fixture->bracket->name . '</td>';
      $table .=
        '<td>

                    <input id="checkbox-' .
        $fixture->id .
        '" type="checkbox" value="' .
        $fixture->id .
        '" />
                    <label for="checkbox-' .
        $fixture->id .
        '"></label>

                </td>';

      $table .= '<td>';
      if (!$fixture->registration1_id == null && !$fixture->registration2_id == null) {
        $table .= $fixture->registrations1->players[0]->name . ' ' . $fixture->registrations1->players[0]->surname;
      }

      $table .= '</td>';
      $table .= '<td>';
      $table .= 'vs </td>';
      $table .= '<td>';
      if (!$fixture->registration1_id == null && !$fixture->registration2_id == null) {
        $table .= $fixture->registrations2->players[0]->name . ' ' . $fixture->registrations2->players[0]->surname;
      }
      $table .= '</td>';
      $table .= '<td>';
      $table .= '<span class="btn btn-success createFix" data-draw="' . $draw->id . '" >create</span></td>';
      $table .= '</tr>';
    }
    $table .= '</tbody>';
    $table .= '</table>';
    $table .= '</div>';

    return $table;
  }

  public static function scheduled_fixtures($draw)
  {
    $fixtures = $draw->fixtures_in_draw_day($draw->id);
    $data = '';

    $data .= ' ';

    $data .= '<span class="btn btn-success m-2 createFix" data-draw="' . $draw->id . '" >Schedule Order of play</span>';
    $data .= '<span class="btn btn-info m-2 settings" data-draw="' . $draw->id . '" >Settings for order of play</span>';
    $data .= '<ul id="sortable">';
    foreach ($fixtures as $key => $fixture) {
      if (!count($fixture->results) > 0) {
        $data .=
          '<li class="ui-state-default"> ' .
          $fixture->id .
          ' <input id="checkbox-' .
          $fixture->id .
          '" type="checkbox" value="' .
          $fixture->id .
          '" />
            <label for="checkbox-' .
          $fixture->id .
          '"></label>' .
          $fixture->bracket->name .
          ' - Round: ' .
          $fixture->round .
          ' ';

        if (!$fixture->registration1_id == null && !$fixture->registration2_id == null) {
          $data .=
            $fixture->registrations1->players[0]->name . ' ' . $fixture->registrations1->players[0]->surname . ' vs ';
        }
        if (!$fixture->registration1_id == null && !$fixture->registration2_id == null) {
          $data .= $fixture->registrations2->players[0]->name . ' ' . $fixture->registrations2->players[0]->surname;
        }

        $data .= '</li>';
      }
    }
    $data .= '</td>';
    $data .= '</ul>';

    return $data;
  }

  public static function check_if_played($reg1, $reg2)
  {
    $fixture1 = Fixture::whereRegistration1_id($reg1)
      ->whereRegistration2_id($reg2)
      ->get();
    $fixture2 = Fixture::whereRegistration1_id($reg2)
      ->whereRegistration2_id($reg1)
      ->get();
    if (count($fixture1) > 0 || count($fixture2) > 0) {
      return 1;
    } else {
      return 0;
    }
  }

  public static function get_playoff($draw_id, $positions)
  {
    for ($i = 1; $i < 31; $i += 2) {
      if ($positions[$draw_id][$i] > 0 && $positions[$draw_id][$i + 1] > 0) {
        $matches[] =
          'Position: ' .
          ($i + 1) .
          '/' .
          ($i + 2) .
          ' - ' .
          Registration::find($positions[$draw_id][$i])->players[0]->name .
          ' ' .
          Registration::find($positions[$draw_id][$i])->players[0]->surname .
          ' vs ' .
          Registration::find($positions[$draw_id][$i + 1])->players[0]->name .
          ' ' .
          Registration::find($positions[$draw_id][$i + 1])->players[0]->surname;
      } else {
        $matches[] = '';
      }
    }
    return $matches;
  }

  public static function update_byes($draw_id)
  {
    $fixtures = Draw::find($draw_id)->ind_fixtures_byes_reg1;

    $all_byes = $fixtures->merge(Draw::find($draw_id)->ind_fixtures_byes_reg2);

    foreach ($all_byes as $bye) {
      $update_loser_bye = Brackets::get_update_fixture($bye, 'loser');
      $update_winner_bye = Brackets::get_update_fixture($bye, 'winner');
      $bye_winner_id = Brackets::get_bye_winner($bye);
      ////hier improve toest if should update
      if ($update_loser_bye == 0 && is_null($update_winner_bye == null)) {
      } else {
        $response = Brackets::update_loser_fixture($update_loser_bye, 0, $bye);

        $response = Brackets::update_winner_fixture($update_winner_bye, $bye_winner_id, $bye);
      }
    }

    return $response;
  }

  public static function get_bye_winner($fix)
  {
    if ($fix->registration1_id == 0) {
      $winner_id = $fix->registration2_id;
    } else {
      $winner_id = $fix->registration1_id;
    }
    return $winner_id;
  }

  public static function getPosition($key, $draw)
  {
    return Brackets::getPositionTemplate($key, $draw);
  }

  public static function getPositionTemplate($pos, $draw)
  {
    switch ($pos) {
      case 1:
        $res = Fixture::where('bracket_id', 1)
          ->where('match_nr', 31)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 2:
        $res = Fixture::where('bracket_id', 1)
          ->where('match_nr', 31)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 3:
        $res = Fixture::where('bracket_id', 2)
          ->where('match_nr', 1)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 4:
        $res = Fixture::where('bracket_id', 2)
          ->where('match_nr', 1)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 5:
        $res = Fixture::where('bracket_id', 3)
          ->where('match_nr', 27)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 6:
        $res = Fixture::where('bracket_id', 3)
          ->where('match_nr', 27)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 7:
        $res = Fixture::where('bracket_id', 4)
          ->where('match_nr', 1)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 8:
        $res = Fixture::where('bracket_id', 4)
          ->where('match_nr', 1)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 9:
        $res = Fixture::where('bracket_id', 5)
          ->where('match_nr', 3)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 10:
        $res = Fixture::where('bracket_id', 5)
          ->where('match_nr', 3)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 11:
        $res = Fixture::where('bracket_id', 5)
          ->where('match_nr', 4)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 12:
        $res = Fixture::where('bracket_id', 5)
          ->where('match_nr', 4)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 13:
        $res = Fixture::where('bracket_id', 6)
          ->where('match_nr', 3)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 14:
        $res = Fixture::where('bracket_id', 6)
          ->where('match_nr', 3)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 15:
        $res = Fixture::where('bracket_id', 6)
          ->where('match_nr', 4)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 16:
        $res = Fixture::where('bracket_id', 6)
          ->where('match_nr', 4)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 17:
        $res = Fixture::where('bracket_id', 7)
          ->where('match_nr', 7)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 18:
        $res = Fixture::where('bracket_id', 7)
          ->where('match_nr', 7)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 19:
        $res = Fixture::where('bracket_id', 7)
          ->where('match_nr', 11)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 20:
        $res = Fixture::where('bracket_id', 7)
          ->where('match_nr', 11)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 21:
        $res = Fixture::where('bracket_id', 7)
          ->where('match_nr', 10)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 22:
        $res = Fixture::where('bracket_id', 7)
          ->where('match_nr', 10)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 23:
        $res = Fixture::where('bracket_id', 7)
          ->where('match_nr', 12)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 24:
        $res = Fixture::where('bracket_id', 7)
          ->where('match_nr', 12)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 25:
        $res = Fixture::where('bracket_id', 8)
          ->where('match_nr', 7)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 26:
        $res = Fixture::where('bracket_id', 8)
          ->where('match_nr', 7)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 27:
        $res = Fixture::where('bracket_id', 8)
          ->where('match_nr', 11)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 28:
        $res = Fixture::where('bracket_id', 8)
          ->where('match_nr', 11)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 29:
        $res = Fixture::where('bracket_id', 8)
          ->where('match_nr', 10)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 30:
        $res = Fixture::where('bracket_id', 8)
          ->where('match_nr', 10)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      case 31:
        $res = Fixture::where('bracket_id', 8)
          ->where('match_nr', 12)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalWinner($res);

        break;
      case 32:
        $res = Fixture::where('bracket_id', 8)
          ->where('match_nr', 12)
          ->where('draw_id', $draw)
          ->first()->fixtureResults;
        return Brackets::getResultFinalLoser($res);

        break;
      default:
        # code...
        break;
    }
  }

  public static function getResultFinalWinner($res)
  {
    if (count($res) > 0) {
      return $res->last()->w_registration->players[0]->getFullNameAttribute();
    } else {
      return 'not played yet';
    }
  }
  public static function getResultFinalLoser($res)
  {
    if (count($res) > 0) {
      return $res->last()->l_registration->players[0]->getFullNameAttribute();
    } else {
      return 'not played yet';
    }
  }
  public static function createRound1Matches(array $players)
  {
    $matches = [];
    for ($i = 0; $i < count($players); $i += 2) {
      $matches[] = [
        'player1' => $players[$i] ?? 'TBD',
        'player2' => $players[$i + 1] ?? 'TBD',
        'score' => ''
      ];
    }
    return $matches;
  }







  public static function buildBracket(int $playerCount = 32, array $matches = []): string
   {
      $svg = "<svg width='2400' height='3500'><g xmlns='http://www.w3.org/2000/svg'>";

      $boxWidth = 200;
      $baseHeight = 40;
      $gap = 40;

      $rounds = log($playerCount, 2);
      $positions = [];
      $boxHeights = [];
      $matchNumber = 1;

      $svg .= "<text x='10' y='15' font-family='Arial' font-size='16' font-weight='bold'>Platinum Bracket</text>";

      for ($round = 1; $round <= $rounds; $round++) {
          $matchesInRound = pow(2, $rounds - $round);
          $boxHeight = ($round === 1) ? $baseHeight + 10 : $baseHeight * pow(2, $round - 1);
          $boxHeights[$round] = $boxHeight;
          $x = ($round - 1) * $boxWidth;
          $positions[$round] = [];

          for ($i = 0; $i < $matchesInRound; $i++) {
              $y = ($round === 1)
                  ? 40 + $i * ($boxHeight + $gap)
                  : (($positions[$round - 1][$i * 2] + $positions[$round - 1][$i * 2 + 1] + $boxHeights[$round - 1]) / 2) - ($boxHeight / 2);

              $positions[$round][] = $y;

              $x1 = $x + 1;
              $x2 = $x + $boxWidth;

              $svg .= "<path d='M$x1 $y H$x2 V" . ($y + $boxHeight) . " H$x1' fill='white' stroke='black' stroke-width='1' />";

              $label1 = $label2 = '';
              if (isset($matches[$matchNumber - 1])) {
                  $m = $matches[$matchNumber - 1];
                  $label1 = e($m['player1']);
                  $label2 = e($m['player2']);
              }

              $svg .= "<text x='" . ($x1 + 10) . "' y='" . ($y - 5) . "' font-family='Arial' font-size='13'>$label1</text>";
              $svg .= "<text x='" . ($x1 + 10) . "' y='" . ($y + $boxHeight - 5) . "' font-family='Arial' font-size='13'>$label2</text>";
              $svg .= "<text x='" . ($x1 + $boxWidth / 2 - 20) . "' y='" . ($y + $boxHeight / 2 + 5) . "' font-family='Arial' font-size='12' fill='gray'>Match #$matchNumber</text>";

              $matchNumber++;
          }
      }

      $finalRoundX = ($rounds - 1) * $boxWidth;
      $finalBoxY = $positions[$rounds][0];
      $finalBoxHeight = $boxHeights[$rounds];
      $winnerLineStartX = $finalRoundX + $boxWidth;
      $winnerLineEndX = $winnerLineStartX + 100;
      $winnerLineY = $finalBoxY + ($finalBoxHeight / 2);

      $svg .= "<line x1='$winnerLineStartX' y1='$winnerLineY' x2='$winnerLineEndX' y2='$winnerLineY' stroke='black' stroke-width='2' />";
      $svg .= "<text x='" . ($winnerLineEndX + 10) . "' y='" . ($winnerLineY + 5) . "' font-family='Arial' font-size='14' font-weight='bold'>Platinum Winner</text>";

      // === GOLD BRACKET ===
      $goldYStart = max($positions[1]) + $boxHeights[1] + 100;
      $goldPlayers = count($positions[1]);
      $goldRounds = log($goldPlayers, 2);

      $svg .= "<text x='10' y='" . ($goldYStart - 30) . "' font-family='Arial' font-size='16' font-weight='bold'>Gold Bracket</text>";

      $goldMatchNumber = 1;
      $goldBoxHeight = 40;
      $goldBoxHeights = [];
      $goldPositions = [];

      for ($round = 1; $round <= $goldRounds; $round++) {
          $matchesInRound = pow(2, $goldRounds - $round);
          $boxHeight = $goldBoxHeight * pow(2, $round - 1);
          $goldBoxHeights[$round] = $boxHeight;
          $x = ($round - 1) * $boxWidth;
          $goldPositions[$round] = [];

          for ($i = 0; $i < $matchesInRound; $i++) {
              $y = ($round === 1)
                  ? $goldYStart + $i * ($boxHeight + $gap)
                  : (($goldPositions[$round - 1][$i * 2] + $goldPositions[$round - 1][$i * 2 + 1] + $goldBoxHeights[$round - 1]) / 2) - ($boxHeight / 2);

              $goldPositions[$round][] = $y;
              $x1 = $x + 1;
              $x2 = $x + $boxWidth;

              $svg .= "<path d='M$x1 $y H$x2 V" . ($y + $boxHeight) . " H$x1' fill='white' stroke='black' stroke-width='1' />";
              $svg .= "<text x='" . ($x1 + $boxWidth / 2 - 30) . "' y='" . ($y + $boxHeight / 2 + 5) . "' font-family='Arial' font-size='12' fill='gray'>Match #G$goldMatchNumber</text>";

              $goldMatchNumber++;
          }
      }

      $finalGoldRoundX = ($goldRounds - 1) * $boxWidth;
      $finalGoldBoxY = $goldPositions[$goldRounds][0];
      $finalGoldBoxHeight = $goldBoxHeights[$goldRounds];
      $goldWinnerLineStartX = $finalGoldRoundX + $boxWidth;
      $goldWinnerLineEndX = $goldWinnerLineStartX + 100;
      $goldWinnerLineY = $finalGoldBoxY + ($finalGoldBoxHeight / 2);

      $svg .= "<line x1='$goldWinnerLineStartX' y1='$goldWinnerLineY' x2='$goldWinnerLineEndX' y2='$goldWinnerLineY' stroke='black' stroke-width='2' />";
      $svg .= "<text x='" . ($goldWinnerLineEndX + 10) . "' y='" . ($goldWinnerLineY + 5) . "' font-family='Arial' font-size='14' font-weight='bold'>Gold Winner</text>";

      // === SILVER BRACKET ===
      $silverYStart = max($goldPositions[1]) + $goldBoxHeights[1] + 100;
      $silverPlayers = count($goldPositions[1]);
      $silverRounds = log($silverPlayers, 2);

      $svg .= "<text x='10' y='" . ($silverYStart - 30) . "' font-family='Arial' font-size='16' font-weight='bold'>Silver Bracket</text>";

      $silverMatchNumber = 1;
      $silverBoxHeight = 40;
      $silverBoxHeights = [];
      $silverPositions = [];

      for ($round = 1; $round <= $silverRounds; $round++) {
          $matchesInRound = pow(2, $silverRounds - $round);
          $boxHeight = $silverBoxHeight * pow(2, $round - 1);
          $silverBoxHeights[$round] = $boxHeight;
          $x = ($round - 1) * $boxWidth;
          $silverPositions[$round] = [];

          for ($i = 0; $i < $matchesInRound; $i++) {
              $y = ($round === 1)
                  ? $silverYStart + $i * ($boxHeight + $gap)
                  : (($silverPositions[$round - 1][$i * 2] + $silverPositions[$round - 1][$i * 2 + 1] + $silverBoxHeights[$round - 1]) / 2) - ($boxHeight / 2);

              $silverPositions[$round][] = $y;
              $x1 = $x + 1;
              $x2 = $x + $boxWidth;

              $svg .= "<path d='M$x1 $y H$x2 V" . ($y + $boxHeight) . " H$x1' fill='white' stroke='black' stroke-width='1' />";
              $svg .= "<text x='" . ($x1 + $boxWidth / 2 - 30) . "' y='" . ($y + $boxHeight / 2 + 5) . "' font-family='Arial' font-size='12' fill='gray'>Match #S$silverMatchNumber</text>";

              $silverMatchNumber++;
          }
      }

      $finalSilverRoundX = ($silverRounds - 1) * $boxWidth;
      $finalSilverBoxY = $silverPositions[$silverRounds][0];
      $finalSilverBoxHeight = $silverBoxHeights[$silverRounds];
      $silverWinnerLineStartX = $finalSilverRoundX + $boxWidth;
      $silverWinnerLineEndX = $silverWinnerLineStartX + 100;
      $silverWinnerLineY = $finalSilverBoxY + ($finalSilverBoxHeight / 2);

      $svg .= "<line x1='$silverWinnerLineStartX' y1='$silverWinnerLineY' x2='$silverWinnerLineEndX' y2='$silverWinnerLineY' stroke='black' stroke-width='2' />";
      $svg .= "<text x='" . ($silverWinnerLineEndX + 10) . "' y='" . ($silverWinnerLineY + 5) . "' font-family='Arial' font-size='14' font-weight='bold'>Silver Winner</text>";

      // Placement matches (3rd/4th, 5th/6th, 7th/8th)
      $placementLabels = ['3rd Place', '5th Place', '7th Place'];
      for ($i = 1; $i <= 3; $i++) {
          $y = max($silverPositions[$silverRounds]) + $silverBoxHeights[$silverRounds] + ($i * 60);
          $x1 = $finalSilverRoundX + 1;
          $x2 = $x1 + $boxWidth;
          $svg .= "<path d='M$x1 $y H$x2 V" . ($y + $silverBoxHeight) . " H$x1' fill='white' stroke='black' stroke-width='1' />";
          $svg .= "<text x='" . ($x1 + $boxWidth / 2 - 30) . "' y='" . ($y + $silverBoxHeight / 2 + 5) . "' font-family='Arial' font-size='12' fill='gray'>Match #S$silverMatchNumber</text>";

          $lineStart = $x2;
          $lineEnd = $x2 + 100;
          $lineY = $y + ($silverBoxHeight / 2);
          $svg .= "<line x1='$lineStart' y1='$lineY' x2='$lineEnd' y2='$lineY' stroke='black' stroke-width='1' />";
          $svg .= "<text x='" . ($lineEnd + 10) . "' y='" . ($lineY + 5) . "' font-family='Arial' font-size='14' font-weight='bold'>Silver {$placementLabels[$i - 1]}</text>";

          $silverMatchNumber++;
      }

      $svg .= "</g></svg>";
      return $svg;
  }






}
