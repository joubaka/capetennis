<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Exersize;
use App\Models\ExersizeName;
use App\Models\ExersizeType;
use App\Models\Goal;
use App\Models\GoalTheme;
use App\Models\GoalType;
use App\Models\Player;
use App\Models\PracticeDuration;
use App\Models\PracticeFixtures;
use App\Models\PracticeResults;
use App\Models\PracticeType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlayerController extends Controller
{
    function player_profile($id)
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

            ->withCount('results')
            ->whereHas('practice', function ($q) use ($id) {
                $q->where('player_id', '=', $id);
            })
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
        return view('frontend.player.player_profile', compact('u','goal_themes', 'goal_types', 'setslost', 'setswon', 'totsets', 'players', 'physical_exersizes', 'practice_types', 'durations', 'player', 'general_goal_types', 'career_goal_types', 'exersize_types'));
    }

    
}
