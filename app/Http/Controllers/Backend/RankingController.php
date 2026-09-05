<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\Event;
use App\Models\Player;
use App\Models\Point;
use App\Models\Position;
use App\Models\RankingList;
use App\Models\RankingListCategoryEvent;
use App\Models\Series;
use App\Domain\Ranking\Enums\RankingStatus;
use App\Domain\Ranking\Services\RankingRebuildService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RankingController extends Controller
{
  public function index()
  {
    abort(404);
  }

  public function rankingsIndex()
  {
    $series = Series::where('leaderboard_published', 1)
      ->orderByDesc('year')
      ->orderBy('name')
      ->get();

    return view('frontend.ranking.index', compact('series'));
  }

  public function create()
  {
    abort(404);
  }

  public function store(Request $request)
  {
    //
  }

  public function show($id)
  {
    $series = Series::findOrFail($id);
    $this->authorize('view', $series);

    return redirect()->route('ranking.series.list', $series);

    /*

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

    return view('backend.ranking.admin', compact('series', 'series_categories', 'categories', 'points','report')); */
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
    $series = Series::whereKey($id)
      ->where('leaderboard_published', true)
      ->firstOrFail();

    $runId = $this->publishedRunId($series);

    // Load series rankings similar to SeriesRankingController@index
    $rankings = \App\Models\SeriesRanking::with([
      'registration.players',
      'player',
      'category'
    ])
      ->where('series_id', $series->id)
      ->when(
        $runId,
        fn($query) => $query
          ->where('status', RankingStatus::Published->value)
          ->where('run_id', $runId),
        fn($query) => $query->whereNull('run_id')
      )
      ->orderBy('category_id')
      ->orderBy('rank_position')
      ->get();

    $categories = $rankings->pluck('category')->filter()->unique('id')->sortBy(function ($category) {
      $name = (string) $category->name;
      preg_match('/u\s*\/\s*(\d+)/i', $name, $ageMatch);
      $age = (int) ($ageMatch[1] ?? 999);
      $gender = preg_match('/boys?/i', $name) ? 2 : (preg_match('/girls?/i', $name) ? 1 : 3);
      preg_match('/\b([AB])\b/i', $name, $divisionMatch);
      $division = strtoupper($divisionMatch[1] ?? 'Z');

      return sprintf('%d-%03d-%s-%s', $gender, $age, $division, strtolower($name));
    })->values();

    $categoryEventIds = $rankings->flatMap(function ($ranking) {
      $meta = $ranking->meta_json ?? [];
      if (is_string($meta)) {
        $meta = json_decode($meta, true) ?: [];
      }

      return collect($meta['counting_legs'] ?? [])
        ->merge($meta['dropped_legs'] ?? [])
        ->pluck('category_event_id');
    })->filter()->unique()->values();

    $eventIdsByCategoryEvent = CategoryEvent::query()
      ->whereIn('id', $categoryEventIds)
      ->pluck('event_id', 'id');

    $displayLegsByRanking = $rankings->mapWithKeys(function ($ranking) use ($eventIdsByCategoryEvent) {
      $meta = $ranking->meta_json ?? [];
      if (is_string($meta)) {
        $meta = json_decode($meta, true) ?: [];
      }

      $normalise = function (array $leg, string $status) use ($eventIdsByCategoryEvent) {
        $categoryEventId = $leg['category_event_id'] ?? null;

        return [
          'event_id' => $leg['event_id'] ?? $eventIdsByCategoryEvent->get($categoryEventId) ?? $categoryEventId,
          'points' => (float) ($leg['points'] ?? 0),
          'position' => $leg['position'] ?? null,
          'status' => $status,
          'synthetic' => (bool) ($leg['synthetic'] ?? false),
        ];
      };

      if (array_key_exists('counting_legs', $meta) || array_key_exists('dropped_legs', $meta)) {
        $legs = collect($meta['counting_legs'] ?? [])
          ->map(fn ($leg) => $normalise($leg, 'counted'))
          ->concat(collect($meta['dropped_legs'] ?? [])->map(fn ($leg) => $normalise($leg, 'dropped')))
          ->values();
      } else {
        $legs = collect($meta['legs'] ?? [])->map(function ($leg) use ($normalise) {
          $status = ($leg['status'] ?? null) === 'dropped' || ($leg['colour'] ?? null) === 'red'
            ? 'dropped'
            : 'counted';

          return $normalise($leg, $status);
        })->values();
      }

      return [$ranking->id => $legs];
    });

    return view('frontend.ranking.show_ranking', compact(
      'series',
      'rankings',
      'categories',
      'displayLegsByRanking'
    ));
  }

  public function playerDetail(Series $series, Player $player)
  {
    abort_unless($series->leaderboard_published, 404);

    $runId = $this->publishedRunId($series);

    $rankingRecord = \App\Models\SeriesRanking::with(['category'])
      ->where('series_id', $series->id)
      ->where('player_id', $player->id)
      ->when(
        $runId,
        fn($query) => $query
          ->where('status', RankingStatus::Published->value)
          ->where('run_id', $runId),
        fn($query) => $query->whereNull('run_id')
      )
      ->orderBy('rank_position')
      ->first();

    abort_unless($rankingRecord, 404);

    $eventsById = $series->events()
      ->select('id', 'name', 'start_date', 'published', 'results_published')
      ->get()
      ->keyBy('id');

    $legs = collect();
    $rankingCategoryName = $rankingRecord?->category?->name ?? '—';

    // Normalise meta_json: handle double-encoded JSON (stored as json_encode'd string in an array-cast column)
    $metaJson = $rankingRecord?->meta_json ?? null;
    if (is_string($metaJson)) {
      $metaJson = json_decode($metaJson, true) ?? [];
    }

    if ($rankingRecord && (!empty($metaJson['counting_legs']) || !empty($metaJson['dropped_legs']))) {
      // New format from RankingRebuildService: counting_legs / dropped_legs keyed by category_event_id
      $meta = $metaJson;
      $allCeIds = collect($meta['counting_legs'] ?? [])->pluck('category_event_id')
        ->merge(collect($meta['dropped_legs'] ?? [])->pluck('category_event_id'))
        ->unique()->filter()->values();

      $catEventsById = \App\Models\CategoryEvent::with(['event:id,name,start_date'])
        ->whereIn('id', $allCeIds)
        ->get()
        ->keyBy('id');

      $countingLegs = collect($meta['counting_legs'] ?? [])->map(function ($leg) use ($catEventsById, $rankingCategoryName) {
        $ce = $catEventsById->get($leg['category_event_id'] ?? null);
        return array_merge($leg, [
          'event_name'    => $ce?->event?->name ?? 'Event #' . ($leg['category_event_id'] ?? '?'),
          'event_date'    => $ce?->event?->start_date ?? null,
          'category_name' => $rankingCategoryName,
          'status'        => 'counted',
          'colour'        => !empty($leg['synthetic']) ? 'yellow' : 'green',
          'is_auto'       => !empty($leg['synthetic']),
        ]);
      });

      $droppedLegs = collect($meta['dropped_legs'] ?? [])->map(function ($leg) use ($catEventsById, $rankingCategoryName) {
        $ce = $catEventsById->get($leg['category_event_id'] ?? null);
        return array_merge($leg, [
          'event_name'    => $ce?->event?->name ?? 'Event #' . ($leg['category_event_id'] ?? '?'),
          'event_date'    => $ce?->event?->start_date ?? null,
          'category_name' => $rankingCategoryName,
          'status'        => 'dropped',
          'colour'        => 'red',
          'is_auto'       => false,
        ]);
      });

      $legs = $countingLegs->concat($droppedLegs)->sortByDesc('points')->values();
    } elseif ($rankingRecord && !empty($metaJson['legs'])) {
      $legs = collect($metaJson['legs'])->map(function ($leg) use ($eventsById, $rankingCategoryName) {
        $event = $eventsById->get($leg['event_id'] ?? null);
        return array_merge($leg, [
          'event_name'    => $event?->name ?? 'Event #' . ($leg['event_id'] ?? '?'),
          'event_date'    => $event?->start_date ?? null,
          'category_name' => $rankingCategoryName,
        ]);
      });
    } elseif ($rankingRecord) {
      // Fallback: build legs from CategoryResult when meta_json has no legs
      // (e.g. rankings created via direct data entry, not through the rebuild process)
      $eventIds = $eventsById->keys()->toArray();
      $pointsMap = $series->points->pluck('score', 'position')->toArray();
      // Use per-category override if set, otherwise fall back to series-level value
      $rankingList = \App\Models\RankingList::where('series_id', $series->id)
        ->where('category_id', $rankingRecord->category_id)
        ->first();
      $bestN = (int) ($rankingList?->best_num_of_scores ?? $series->best_num_of_scores ?? 0);

      $rawResults = \App\Models\CategoryResult::query()
        ->join('registrations', 'registrations.id', '=', 'category_results.registration_id')
        ->join('player_registrations', 'player_registrations.registration_id', '=', 'registrations.id')
        ->whereIn('category_results.event_id', $eventIds)
        ->where('player_registrations.player_id', $player->id)
        ->where('category_results.category_id', $rankingRecord->category_id)
        ->select(
          'category_results.event_id',
          'category_results.category_id',
          'category_results.position'
        )
        ->get();

      if ($rawResults->isNotEmpty()) {
        $categoryIds = $rawResults->pluck('category_id')->unique()->filter();
        $categoriesById = \App\Models\Category::whereIn('id', $categoryIds)->pluck('name', 'id');

        // Sort by points descending (best first) so Best-N counting matches rebuild logic
        $sorted = $rawResults->map(function ($result) use ($eventsById, $pointsMap, $categoriesById) {
          return [
            'event_id'      => (int) $result->event_id,
            'event_name'    => $eventsById->get($result->event_id)?->name ?? 'Event #' . $result->event_id,
            'event_date'    => $eventsById->get($result->event_id)?->start_date ?? null,
            'category_name' => $categoriesById->get($result->category_id) ?? '—',
            'position'      => (int) $result->position,
            'points'        => (int) ($pointsMap[$result->position] ?? 0),
          ];
        })->sortByDesc('points')->values();

        $legs = $sorted->map(function ($leg, $i) use ($bestN) {
          $counted = $bestN <= 0 || $i < $bestN;
          return array_merge($leg, [
            'status' => $counted ? 'counted' : 'dropped',
            'colour' => $counted ? 'green' : 'red',
          ]);
        });
      }
    }

    $legs = $this->addPublicEventDestinations($legs, $eventsById, (int) $rankingRecord->category_id);

    return view('frontend.ranking.player_detail', compact(
      'series',
      'player',
      'rankingRecord',
      'legs'
    ));
  }

  private function addPublicEventDestinations(Collection $legs, Collection $eventsById, int $categoryId): Collection
  {
    if ($legs->isEmpty()) {
      return $legs;
    }

    $categoryEventIds = $legs->pluck('category_event_id')->filter()->map(fn ($id) => (int) $id)->unique();
    $eventIds = $legs->pluck('event_id')->filter()->map(fn ($id) => (int) $id)->unique();

    $categoryEvents = CategoryEvent::query()
      ->where(function ($query) use ($categoryEventIds, $eventIds, $categoryId) {
        if ($categoryEventIds->isNotEmpty()) {
          $query->whereIn('id', $categoryEventIds);
        }

        if ($eventIds->isNotEmpty()) {
          $method = $categoryEventIds->isNotEmpty() ? 'orWhere' : 'where';
          $query->{$method}(function ($eventQuery) use ($eventIds, $categoryId) {
            $eventQuery->whereIn('event_id', $eventIds)->where('category_id', $categoryId);
          });
        }
      })
      ->get(['id', 'event_id', 'category_id']);

    $categoryEventsById = $categoryEvents->keyBy('id');
    $categoryEventByEvent = $categoryEvents
      ->where('category_id', $categoryId)
      ->keyBy('event_id');
    $allEventIds = $eventIds
      ->merge($categoryEvents->pluck('event_id')->map(fn ($id) => (int) $id))
      ->unique();

    $publishedDrawsByEvent = Draw::query()
      ->with(['flexibleMonrad', 'settings'])
      ->whereIn('event_id', $allEventIds)
      ->where('published', true)
      ->orderBy('id')
      ->get()
      ->groupBy('event_id');

    return $legs->map(function (array $leg) use ($eventsById, $categoryEventsById, $categoryEventByEvent, $publishedDrawsByEvent) {
      $categoryEvent = isset($leg['category_event_id'])
        ? $categoryEventsById->get((int) $leg['category_event_id'])
        : null;
      $eventId = (int) ($leg['event_id'] ?? $categoryEvent?->event_id ?? 0);
      $event = $eventsById->get($eventId);

      if (! $categoryEvent && $eventId) {
        $categoryEvent = $categoryEventByEvent->get($eventId);
      }

      $draw = $event?->published && $categoryEvent
        ? $publishedDrawsByEvent->get($eventId, collect())
          ->firstWhere('category_event_id', $categoryEvent->id)
        : null;

      if ($draw) {
        $leg['event_url'] = $draw->usesFlexibleMonrad()
          ? route('public.flexible-monrad.show', $draw)
          : route('public.roundrobin.show', $draw);
        $leg['event_destination'] = 'draw';
      } elseif ($event?->published && $event->results_published) {
        $leg['event_url'] = route('events.results', $event);
        $leg['event_destination'] = 'results';
      } else {
        $leg['event_url'] = null;
        $leg['event_destination'] = null;
      }

      return $leg;
    });
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
    $this->authorize('update', $series);

    try {
      $report = app(RankingRebuildService::class)->rebuild($series, [
        'dryRun' => $request->boolean('dry_run'),
        'walkoversExcluded' => $request->boolean('exclude_walkovers'),
      ]);
    } catch (\InvalidArgumentException|\RuntimeException $exception) {
      if ($request->expectsJson() || $request->ajax()) {
        return response()->json(['message' => $exception->getMessage()], 422);
      }

      return back()->withErrors(['ranking' => $exception->getMessage()]);
    }

    $failed = $report['total_rows'] === 0
      || (!$request->boolean('dry_run') && !$report['persisted']);

    // If the request is AJAX / expects JSON -> keep your current JSON response
    if ($request->expectsJson() || $request->ajax()) {
      return response()->json(['report' => $report], $failed ? 422 : 200);
    }

    // Otherwise, go back to the Rankings Admin screen and show the banner
    if ($failed) {
      return back()->withErrors([
        'ranking' => collect($report['warnings'])->first()
          ?? 'No replacement ranking rows were created. Existing rankings were preserved.',
      ]);
    }

    return back()->with('calc_report', $report);
    // or, if you prefer an explicit route:
    // return redirect()->route('ranking.lists.index', $seriesId)->with('calc_report', $report);
  }



  public function details($id)
  {
    $player = Player::findOrFail($id);
    $series = Series::findOrFail(request('series'));
    $this->authorize('view', $series);

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
    $series = Series::findOrFail($series_id);
    $this->authorize('update', $series);

    abort(410, 'Legacy ranking-list generation has been retired. Create canonical ranking lists explicitly.');
  }

  public function storeList(Request $request, Series $series)
  {
    $this->authorize('update', $series);
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
    $this->authorize('update', $rankingList->series);
    $data = $request->validate([
      'name' => ['required', 'string', 'max:100'],
    ]);

    $rankingList->update(['name' => $data['name']]);

    return response()->json(['status' => 'ok']);
  }

  public function destroyList(RankingList $rankingList)
  {
    $this->authorize('update', $rankingList->series);
    DB::transaction(function () use ($rankingList) {
      $rankingList->rank_cats()->delete();
      $rankingList->ranking_scores()->delete();
      $rankingList->delete();
    });

    return response()->json(['status' => 'deleted']);
  }

  public function add_category_to_ranklist(Request $request, RankingList $rankingList)
  {
    $this->authorize('update', $rankingList->series);
    $data = $request->validate([
      'category_event_id' => ['required', 'integer', 'exists:category_events,id'],
    ]);

   

    $categoryEvent = CategoryEvent::with(['event', 'category'])->findOrFail($data['category_event_id']);
    $listCategoryName = $this->normalizeCategoryName((string) $rankingList->category?->name);
    $eventCategoryName = $this->normalizeCategoryName((string) $categoryEvent->category?->name);
    abort_unless(
      (int) $categoryEvent->event?->series_id === (int) $rankingList->series_id
        && $listCategoryName !== ''
        && $listCategoryName === $eventCategoryName,
      422,
      'The category event must belong to this series and ranking-list category.'
    );

    $rankingList->rank_cats()->firstOrCreate(
      ['category_event_id' => $data['category_event_id']]
    
    );

    return response()->json(['status' => 'ok']);
  }

  public function delete_category_from_ranklist(Request $request, RankingList $rankingList)
  {
    $this->authorize('update', $rankingList->series);
    $data = $request->validate([
      'category_event_id' => ['required', 'integer', 'exists:category_events,id'],
    ]);

    $rankingList->rank_cats()->where('category_event_id', $data['category_event_id'])->delete();

    return response()->json(['status' => 'deleted']);
  }

  public function updateListOrder(Request $request, RankingList $rankingList)
  {
    $this->authorize('update', $rankingList->series);
    $data = $request->validate([
      'order' => ['required', 'array', 'min:1'],
      'order.*' => ['integer', 'exists:category_events,id'],
    ]);

    $linkedIds = $rankingList->rank_cats()->pluck('category_event_id')->map(fn($id) => (int) $id)->sort()->values();
    $requestedIds = collect($data['order'])->map(fn($id) => (int) $id)->unique()->sort()->values();
    abort_unless($requestedIds->count() === count($data['order']) && $requestedIds->all() === $linkedIds->all(), 422,
      'The order must contain every linked category event exactly once.');

    DB::transaction(function () use ($rankingList, $data) {
      foreach ($data['order'] as $i => $catEventId) {
        $rankingList->rank_cats()
          ->where('category_event_id', $catEventId)
          ->update(['sort_order' => $i + 1]);
      }
    });

    return response()->json(['status' => 'ok']);
  }
  // RankingController
  public function results(Series $series)
  {
    $this->authorize('view', $series);

    return redirect()->route('ranking.series.list', $series);

    /* Legacy ranking_scores implementation retained temporarily for reference.

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


    return view('backend.ranking.results', compact('series')); */
  }

  public function removeCategory(\App\Models\RankingList $list, \Illuminate\Http\Request $request)
  {
    $this->authorize('update', $list->series);

    $data = $request->validate([
      'category_event_id' => ['required', 'integer', 'exists:category_events,id'],
    ]);

    $deleted = $list->rank_cats()
      ->where('category_event_id', $data['category_event_id'])
      ->delete();

    return response()->json([
      'ok' => true,
      'deleted' => $deleted,
      'message' => $deleted ? 'Category event removed from ranking list.' : 'Category event was not linked.'
    ]);
  }
  public function setSchool(Request $request, $id)
  {
    abort_unless(auth()->user()->hasAnyRole(['super-user', 'admin']), 403);

    abort(410, 'Legacy ranking-score grouping has been retired.');
  }
  public function points(Series $series)
  {
    $this->authorize('view', $series);
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
    $this->authorize('update', $series);
    $data = $request->validate([
      'points' => ['required', 'array'],
      'points.*.position' => ['required', 'integer', 'min:1', 'max:50'],
      'points.*.score' => ['required', 'integer', 'min:0'],
    ]);

    abort_if(collect($data['points'])->pluck('position')->duplicates()->isNotEmpty(), 422,
      'Each finishing position may only appear once.');

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

  private function publishedRunId(Series $series): ?string
  {
    return \App\Models\SeriesRanking::where('series_id', $series->id)
      ->where('status', RankingStatus::Published->value)
      ->whereNotNull('run_id')
      ->orderByDesc('published_at')
      ->orderByDesc('updated_at')
      ->value('run_id');
  }

  private function normalizeCategoryName(string $name): string
  {
    return mb_strtolower(preg_replace('/\s+/', ' ', trim($name)) ?? '');
  }



}
