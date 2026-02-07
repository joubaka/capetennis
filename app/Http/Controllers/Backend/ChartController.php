<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\Exersize;
use App\Models\ExersizeName;
use App\Models\Player;
use App\Models\Practice;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChartController extends Controller
{
    function test($id)
    {
        $player = Player::find($id)->practices;
        $lastSevenDays = CarbonPeriod::create(Carbon::now()->subDays(6), Carbon::now());
        foreach ($lastSevenDays as $date) {
            $dateCount[$date->format("M j")] = 0;
        }
        $practices = Practice::whereYear('created_at','=',2023)
        ->where('player_id',$id)
        ->get()->groupBy(function ($date) {
            return Carbon::parse($date->created_at)->format('W');
        });
       
        $weektime =0;
        $sorted = $practices->sort();
        $data['weeks'] = [];
        $data['data'] = [];
        foreach($sorted->reverse() as $key => $week){
           
            $time = $week->sum(function($item){
                return $item->duration->duration;
            });
            
           array_push($data['weeks'],$key);
           array_push($data['data'],$time);
           
        }
 return $data;

    }


    function physical($id)
    {
        $exersizes = Player::find($id)->exersizes->groupBy('exersize_name_id');

        foreach ($exersizes as $key => $value) {
            if ($value->count() > 1) {
                $total = $value->sum('score') / $value->count();
                $data['x'][] = $total;
            } else {
                $data['x'][] = $value[0]->score;
            }

            $data['y'][] = ExersizeName::find($key)->name;
            $data['player'] = Player::find($id)->full_name;
        }

        return $data;
    }
}
