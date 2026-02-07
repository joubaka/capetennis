<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamFixturePlayer;
use App\Models\TeamPlayer;
use Doctrine\DBAL\Schema\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamSelectionController extends Controller
{

    public function selection_index($id)
    {


        $data['event'] = Event::find($id);
        $data['categories'] = CategoryEvent::where('event_id', $id)
            ->with('teams.players')

            ->get();

        $event = Event::find($id);
        $regions = $event->regions->pluck('id');
        $categoriesTeams = Team::whereIn('region_id', $regions)->get()->groupBy('category_event_id');

        foreach ($categoriesTeams as $key => $cat) {
            $cats[] = $key;
            $draws[] = Draw::whereHas('categoryEvent', function ($q) use ($key) {
                $q->where('category_event_id', $key);
            })
                ->whereIn('drawType_id', [1, 4])
                ->with('fixtures')->get();
        }

        $singles = new Collection();
        $m = array();
        foreach ($draws as $key => $draw) {

            foreach ($draw as $format) {

                $singles = $singles->merge($format->fixtures);
            }

            array_push($m, $singles);



            //all draws in event

        }

        //$ordered = $singles->sortBy('rank_nr');

        $data['perAge'] = $singles->groupBy('age');

       
        //dd($perage['u/10 Boys'][0]->player);

        //dd($draws);

        //dd($data['categories'][0]['teams'][0]['players'][0]['teamResultsTeam2'][0]);
dd($data);
        return View('backend.teamSelection.selection-index', $data);
    }
}
