<?php

namespace App\Http\Controllers\backend;

use App\Classes\Rank;
use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\Player;
use App\Models\Point;
use App\Models\Position;
use App\Models\RankingList;
use App\Models\RankingListCategoryEvent;
use App\Models\RankingScores;
use App\Models\Series;
use App\Services\SeriesRanker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RankingController extends Controller
{
  public function index()
  {
    dd('index');
  }

  public function create()
  {
    dd('hallo');
  }

  public function store(Request $request)
  {
    //
  }

  public function show($id)
  {
   
    $series = Series::find($id);

    $series->load([
      'events' => fn($q) => $q->withCount('registrations')->orderBy('start_date'),
      'ranking_lists.category',
      'ranking_lists.rank_cats.eventCategory.event',
      'ranking_lists.rank_cats.eventCategory.category',
    ]);

    $series_categories = $series->events()
      ->with(['categories' => fn($q) => $q->orderBy('name')])
      ->get();

    $categories = \App\Models\Category::orderBy('name')->get();
    $points = \App\Models\Point::where('series_id', $series->id)->orderBy('position')->get();
    $report = app(SeriesRanker::class)->compute($series, [
      'debug' => true,  // include verbose debug trail
      'dryRun' => true,  // don’t write to DB while testing
      // 'bestN' => 3,   // override if needed
    ]);

    // $report['lists'][..]['feedback'] = short UI messages
// $report['debug'] = full trace you can stream to logs or render for admins

    return view('backend.ranking.admin', compact('series', 'series_categories', 'categories', 'points','report'));
  }

  public function edit($id)
  {
    //
  }

  public function update(Request $request, $id)
  {
    //
  }

  public function destroy($id)
  {
    //
  }

  public function ranking_frontend_show($id)
  {

    $series = Series::find($id);
    return view('frontend.ranking.show_ranking', compact('series'));
  }

  public function seriesAllAjax()
  {
    $data = Series::all();
    return ['data' => $data];
  }

 

 

  // RankingController@calculate
  public function calculate(Request $request, $seriesId)
  {
    
    $series = Series::with(['ranking_lists.rank_cats'])->findOrFail($seriesId);

    $rank = new Rank($seriesId);
    $report = $rank->test($series);

    // If the request is AJAX / expects JSON -> keep your current JSON response
    if ($request->expectsJson() || $request->ajax()) {
      return response()->json(['report' => $report]);
    }

    // Otherwise, go back to the Rankings Admin screen and show the banner
    return back()->with('calc_report', $report);
    // or, if you prefer an explicit route:
    // return redirect()->route('ranking.lists.index', $seriesId)->with('calc_report', $report);
  }



  public function details($id)
  {
    $player = Player::find($id);
    $series = Series::find(request('series'));

    $events = $series->events->pluck('id');
    $eventCats = CategoryEvent::whereIn('event_id', $events)->get()->groupBy('category_id');
    $wheres = $eventCats->flatten()->pluck('id');

    $results = Position::whereHas('player', function ($q) use ($player) {
      return $q->where('id', '=', $player->id);
    })
      ->whereIn('category_event_id', $wheres)
      ->get();

    return view('backend.ranking.details', [
      'series' => $series,
      'results' => $results,
      'player' => $player,
    ]);
  }

  public function add_ranking_list($series_id)
  {
    $rank = new Rank($series_id);
    $rank->create_Ranking_List_Normal();

    return 'Ranking lists created';
  }

  public function storeList(Request $request, Series $series)
  {
    $data = $request->validate([
      'name' => ['required', 'string', 'max:100'],
      'category_id' => ['required', 'integer', 'exists:categories,id'],
    ]);

    $list = RankingList::create([
      'series_id' => $series->id,
      'category_id' => $data['category_id'],
      'name' => $data['name'],
    ]);

    return response()->json(['status' => 'ok', 'list' => $list]);
  }

  public function renameList(Request $request, RankingList $rankingList)
  {
    $data = $request->validate([
      'name' => ['required', 'string', 'max:100'],
    ]);

    $rankingList->update(['name' => $data['name']]);

    return response()->json(['status' => 'ok']);
  }

  public function destroyList(RankingList $rankingList)
  {
    DB::transaction(function () use ($rankingList) {
      $rankingList->rank_cats()->delete();
      $rankingList->ranking_scores()->delete();
      $rankingList->delete();
    });

    return response()->json(['status' => 'deleted']);
  }

  public function add_category_to_ranklist(Request $request, RankingList $rankingList)
  {
    
    $data = $request->validate([
      'category_event_id' => ['required', 'integer', 'exists:category_events,id'],
    ]);

   

    $rankingList->rank_cats()->firstOrCreate(
      ['category_event_id' => $data['category_event_id']]
    
    );

    return response()->json(['status' => 'ok']);
  }

  public function delete_category_from_ranklist(Request $request, RankingList $rankingList)
  {
    $data = $request->validate([
      'category_event_id' => ['required', 'integer', 'exists:category_events,id'],
    ]);

    $rankingList->rank_cats()->where('category_event_id', $data['category_event_id'])->delete();

    return response()->json(['status' => 'deleted']);
  }

  public function updateListOrder(Request $request, RankingList $rankingList)
  {
    $data = $request->validate([
      'order' => ['required', 'array', 'min:1'],
      'order.*' => ['integer', 'exists:category_events,id'],
    ]);

    DB::transaction(function () use ($rankingList, $data) {
      foreach ($data['order'] as $i => $catEventId) {
        $rankingList->rank_cats()
          ->where('category_event_id', $catEventId)
          ->update(['order' => $i + 1]);
      }
    });

    return response()->json(['status' => 'ok']);
  }
  // RankingController
  public function results(Series $series)
  {

    // Load series with events, categories, results and players
    $series->load([
      'events:id,name,start_date,series_id',
      'events.eventCategories:id,event_id,category_id',
      'events.eventCategories.results.player:id,name,surname',
      'ranking_lists:id,series_id,category_id',
      'ranking_lists.category:id,name',
      'ranking_lists.ranking_scores' => function ($q) {
        $q->with('player:id,name,surname')
          ->orderByDesc('total_points')
          ->orderBy('player_id');
      },
    ]);

    // Attach the relevant events per list
    $series->ranking_lists->each(function ($list) use ($series) {
      $events = $series->events
        ->filter(fn($e) => $e->eventCategories->contains('category_id', $list->category_id))
        ->sortBy('start_date')
        ->values();

      $list->setRelation('events', $events);
    });

    // Load the points table for this series
    $posToPoints = Point::where('series_id', $series->id)
      ->pluck('score', 'position')
      ->toArray();

    // For each score, build legs_by_event dynamically
    $series->ranking_lists->each(function ($list) use ($posToPoints) {
      $list->ranking_scores->transform(function ($score) use ($list, $posToPoints) {
        $byEvent = collect();

        foreach ($list->events as $event) {
 
          foreach ($event->eventCategories as $ce) {
  
            foreach ($ce->results as $result) {
             
              if ($result->player_id == $score->player_id) {

                $pts = $posToPoints[$result->position] ?? 0;
                $byEvent->put($event->id, [
                  'event' => $event->name,
                  'points' => $pts,
                  'pos' => $result->position,
                ]);
              }
            }
          }
        }
     
        $score->setRelation('legs_by_event', $byEvent);
        return $score;
      });
    });


    return view('backend.ranking.results', compact('series'));
  }

  public function removeCategory(\App\Models\RankingList $list, \Illuminate\Http\Request $request)
  {
    
    $series = $list->series;
    $categoryId = CategoryEvent::find($request->input('category_event_id'))->category->id; // or 'category_id' if that's the field name
  
    RankingList::where('series_id', $series->id)
      ->where('category_id', $categoryId)
      ->get();

    return response()->json([
      'ok' => true,
      'message' => "Category {$categoryId} removed from list {$list->id}"
    ]);
  }
  public function setSchool(Request $request, $id)
  {
    $score = RankingScores::findOrFail($id);

    $group = $request->input('group'); // 'primary', 'high', 'clear'

    $score->primarySchool = ($group === 'primary') ? 1 : 0;
    $score->highSchool = ($group === 'high') ? 1 : 0;
    $score->save();

    return response()->json([
      'ok' => true,
      'primary' => $score->primarySchool,
      'high' => $score->highSchool,
    ]);
  }
  public function points(Series $series)
  {
    $points = Point::where('series_id', $series->id)
      ->orderBy('position')
      ->get()
      ->keyBy('position');

    // Build positions 1–50 with fallback
    $rows = collect(range(1, 50))->map(function ($pos) use ($points) {
      return [
        'position' => $pos,
        'score' => $points[$pos]->score ?? 0,
      ];
    });

    return view('backend.ranking.points', compact('series', 'rows'));
  }


  public function updatePoints(Request $request, Series $series)
  {
    
    $data = $request->validate([
      'points' => ['required', 'array'],
      'points.*.position' => ['required', 'integer', 'min:1', 'max:50'],
      'points.*.score' => ['required', 'integer', 'min:0'],
    ]);

    DB::transaction(function () use ($series, $data) {

      Point::where('series_id', $series->id)->delete();

      $insert = collect($data['points'])->map(fn($row) => [
        'series_id' => $series->id,
        'position' => $row['position'],
        'score' => $row['score'],
        'created_at' => now(),
        'updated_at' => now(),
      ])->toArray();

      Point::insert($insert);

      $series->update([
        'points_template_created' => 1,
      ]);
    });

    return response()->json([
      'status' => 'ok',
      'message' => 'Points template saved',
    ]);
  }



}
