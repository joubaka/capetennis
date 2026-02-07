<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\CategoryResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Ranking\RankingEngine;

class SeriesRankingController extends Controller
{
  /**
   * Display the ranking list for a series
   */
  public function index(Series $series)
  {
    $rankings = SeriesRanking::with([
      'registration.players',
      'category'
    ])
      ->where('series_id', $series->id)
      ->orderBy('category_id')
      ->orderBy('rank_position')
      ->get();

    $categories = $rankings->pluck('category')->unique('id');

    return view('backend.ranking.series.list', [
      'series' => $series,
      'rankings' => $rankings,
      'categories' => $categories,
    ]);
  }

  /**
   * Rebuild the ranking list for a series
   */
  public function rebuild(Request $request, Series $series)
  {
    DB::transaction(function () use ($series, $request) {

      $runId = 'sr-' . $series->id . '-' . now()->format('YmdHis') . '-' . substr(md5((string) microtime(true)), 0, 6);

      $normalize = fn(string $name) =>
        strtolower(preg_replace('/\s+/', ' ', trim($name)));

      Log::info('=== SERIES RANKING REBUILD START ===', [
        'run_id' => $runId,
        'series_id' => $series->id,
        'series_name' => $series->name,
        'rank_type' => optional($series->rankType)->type,
        'user_id' => auth()->id(),
        'ip' => $request->ip(),
      ]);

      /* -------------------------------------------------
       | Clear old rankings
       ------------------------------------------------- */
      $deleted = SeriesRanking::where('series_id', $series->id)->delete();

      Log::info('Old rankings deleted', [
        'run_id' => $runId,
        'deleted_rows' => $deleted,
      ]);

      /* -------------------------------------------------
       | Points map
       ------------------------------------------------- */
      $pointsMap = $series->points
        ->pluck('score', 'position')
        ->toArray();

      Log::debug('Points map loaded', [
        'run_id' => $runId,
        'map' => $pointsMap,
      ]);

      /* -------------------------------------------------
       | Events in series
       ------------------------------------------------- */
      $eventIds = $series->events->pluck('id')->values()->toArray();

      Log::debug('Series event IDs', [
        'run_id' => $runId,
        'event_ids' => $eventIds,
        'event_count' => count($eventIds),
      ]);

      if (empty($eventIds)) {
        Log::warning('No events found, aborting rebuild', [
          'run_id' => $runId
        ]);
        return;
      }

      /* -------------------------------------------------
       | Category names (for merge keys)
       ------------------------------------------------- */
      $categoryNames = \App\Models\Category::pluck('name', 'id')->toArray();

      /* -------------------------------------------------
       | Raw result rows
       ------------------------------------------------- */
      $raw = CategoryResult::query()
        ->join('registrations', 'registrations.id', '=', 'category_results.registration_id')
        ->join('player_registrations', 'player_registrations.registration_id', '=', 'registrations.id')
        ->whereIn('category_results.event_id', $eventIds)
        ->select(
          'category_results.event_id',
          'category_results.category_id',
          'player_registrations.player_id',
          'category_results.position'
        )
        ->get()
        ->map(function ($r) use ($categoryNames, $normalize) {
          $name = $categoryNames[$r->category_id] ?? 'unknown';

          return (object) [
            'event_id' => (int) $r->event_id,
            'category_id' => (int) $r->category_id,
            'category_key' => $normalize($name),
            'player_id' => (int) $r->player_id,
            'position' => (int) $r->position,
          ];
        });

      Log::debug('Raw rows loaded', [
        'run_id' => $runId,
        'rows' => $raw->count(),
        'events' => $raw->pluck('event_id')->unique()->count(),
        'categories_raw' => $raw->pluck('category_id')->unique()->count(),
        'categories_merged' => $raw->pluck('category_key')->unique()->count(),
        'players' => $raw->pluck('player_id')->unique()->count(),
      ]);

      /* -------------------------------------------------
       | Group by merged category → player
       ------------------------------------------------- */
      $grouped = $raw->groupBy(['category_key', 'player_id']);

      Log::info('Merged categories found for ranking', [
        'run_id' => $runId,
        'merged_category_count' => $grouped->keys()->count(),
        'category_keys' => $grouped->keys()->values()->toArray(),
      ]);

      $created = 0;

      /* -------------------------------------------------
       | Resolve ranking strategy
       ------------------------------------------------- */
      $engine = app(RankingEngine::class);
      $strategy = $engine->resolve($series->rankType->type);

      Log::info('Ranking strategy resolved', [
        'run_id' => $runId,
        'rank_type' => $series->rankType->type,
        'strategy' => class_basename($strategy),
      ]);

      /* ===================== PROCESS ===================== */
      foreach ($grouped as $categoryKey => $players) {

        $canonicalCategoryId = $players
          ->flatten(1)
          ->pluck('category_id')
          ->unique()
          ->first();

        Log::debug('Category start', [
          'run_id' => $runId,
          'category_key' => $categoryKey,
          'canonical_category_id' => $canonicalCategoryId,
          'players' => $players->keys()->count(),
        ]);

        /* Execute strategy */
        $rows = $strategy->rank(
          $players,
          $pointsMap,
          $series
        );

        Log::debug('Strategy result', [
          'run_id' => $runId,
          'category_key' => $categoryKey,
          'rows' => count($rows),
        ]);

        foreach ($rows as $i => $row) {

          SeriesRanking::create([
            'series_id' => $series->id,
            'category_id' => (int) $canonicalCategoryId,
            'player_id' => (int) $row['player_id'],
            'rank_position' => $i + 1,
            'total_points' => (int) $row['total'],
            'meta_json' => json_encode($row['meta']),
          ]);

          $created++;

          Log::debug('Ranking row created', [
            'run_id' => $runId,
            'category_key' => $categoryKey,
            'player_id' => $row['player_id'],
            'rank' => $i + 1,
            'total' => $row['total'],
            'third' => $row['third'] ?? null,
          ]);
        }

        Log::info('Category complete', [
          'run_id' => $runId,
          'category_key' => $categoryKey,
          'canonical_category_id' => $canonicalCategoryId,
          'ranked_players' => count($rows),
        ]);
      }

      Log::info('=== SERIES RANKING REBUILD COMPLETE ===', [
        'run_id' => $runId,
        'series_id' => $series->id,
        'events' => count($eventIds),
        'merged_categories' => $grouped->keys()->count(),
        'created_rows' => $created,
        'deleted_rows' => $deleted,
      ]);
    });

    return response()->json([
      'message' => 'Category-based series rankings rebuilt successfully'
    ]);
  }
}
