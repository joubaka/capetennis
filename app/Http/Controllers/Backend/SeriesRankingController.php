<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Ranking\Services\RankingAuditService;
use App\Domain\Ranking\Services\RankingListDetailService;
use App\Domain\Ranking\Services\RankingPublicationService;
use App\Domain\Ranking\Services\RankingRebuildService;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryResult;
use App\Models\RankingList;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Services\Ranking\RankingEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeriesRankingController extends Controller
{
  /**
   * Normalize a category name for consistent cross-event merging.
   */
  private function normalizeCategory(string $name): string
  {
    return strtolower(preg_replace('/\s+/', ' ', trim($name)));
  }

  /**
   * Display the ranking list for a series
   */
  public function index(Series $series)
  {
    $this->authorize('view', $series);

    $series->load(['events.categoryEvents']);

    $activeRunId = SeriesRanking::where('series_id', $series->id)
      ->whereIn('status', ['calculated', 'reviewed', 'published'])
      ->whereNotNull('run_id')
      ->orderByDesc('updated_at')
      ->value('run_id');

    $rankings = SeriesRanking::with([
      'registration.players',
      'player',
      'category'
    ])
      ->where('series_id', $series->id)
      ->when($activeRunId, fn($query) => $query->where('run_id', $activeRunId))
      ->orderBy('category_id')
      ->orderBy('rank_position')
      ->get();

    $categories = $rankings->pluck('category')->unique('id');
    $activeStatus = $rankings->first()?->status;
    $hasArchivedSnapshot = SeriesRanking::where('series_id', $series->id)
      ->where('status', 'archived')
      ->whereNotNull('run_id')
      ->exists();
    $detailService = app(RankingListDetailService::class);

    return view('backend.ranking.series.list', [
      'series' => $series,
      'rankings' => $rankings,
      'categories' => $categories,
      'activeRunId' => $activeRunId,
      'activeStatus' => $activeStatus,
      'hasArchivedSnapshot' => $hasArchivedSnapshot,
      'scoreDetails' => $detailService->scoreDetails($series, $rankings),
      'headToHeadAdvisories' => $detailService->headToHeadAdvisories($series, $rankings),
    ]);
  }

  /**
   * Rebuild the ranking list for a series using the canonical pipeline.
   * Pass ?dry_run=1 to preview without writing.
   * Legacy rebuild requests are rejected; all writes use the canonical pipeline.
   */
  public function rebuild(Request $request, Series $series)
  {
    $this->authorize('update', $series);

    abort_if($request->boolean('legacy'), 410, 'The legacy ranking rebuild has been retired.');

    $options = [
      'dryRun'            => $request->boolean('dry_run'),
      'walkoversExcluded' => $request->boolean('exclude_walkovers'),
    ];

    try {
      $report = app(RankingRebuildService::class)->rebuild($series, $options);
    } catch (\InvalidArgumentException|\RuntimeException $exception) {
      Log::warning('[RankingRebuild] validation failed', [
        'series_id' => $series->id,
        'user_id' => auth()->id(),
        'message' => $exception->getMessage(),
      ]);

      return response()->json(['message' => $exception->getMessage()], 422);
    }

    if ($report['total_rows'] === 0 || (!$options['dryRun'] && !$report['persisted'])) {
      return response()->json([
        'message' => collect($report['warnings'])->first()
          ?? 'No ranking rows were created. Check that the series has saved results and ranking categories.',
        'report' => $report,
      ], 422);
    }

    return response()->json([
      'message' => $options['dryRun']
        ? 'Dry-run complete (no rows written).'
        : "Rankings rebuilt successfully ({$report['total_rows']} ranking rows created).",
      'report'  => $report,
    ]);
  }

  /**
   * Mark the current calculated ranking as reviewed (admin approval step).
   */
  public function review(Request $request, Series $series)
  {
    $this->authorize('update', $series);

    app(RankingPublicationService::class)->markReviewed($series, auth()->id());

    return response()->json(['message' => 'Ranking marked as reviewed.']);
  }

  /**
   * Publish the reviewed ranking, archiving the previous published snapshot.
   */
  public function publish(Request $request, Series $series)
  {
    $this->authorize('update', $series);

    app(RankingPublicationService::class)->publish($series, auth()->id());

    return response()->json(['message' => 'Ranking published.']);
  }

  /**
   * Roll back the current publication to the previous archived snapshot.
   */
  public function rollback(Request $request, Series $series)
  {
    $this->authorize('update', $series);

    app(RankingPublicationService::class)->rollback($series, auth()->id());

    return response()->json(['message' => 'Ranking rolled back to previous publication.']);
  }

  /**
   * Return the canonical audit report as JSON for the admin UI.
   */
  public function auditReport(Request $request, Series $series)
  {
    $this->authorize('view', $series);

    $report = app(RankingAuditService::class)->buildReport($series);

    return response()->json($report);
  }

      // ------------------------------------------------------------------
      // Legacy rebuild (kept for parity testing — do not remove yet)
      // ------------------------------------------------------------------

      private function rebuildLegacy(Request $request, Series $series)
      {
        abort(410, 'The legacy ranking rebuild has been retired.');

        DB::transaction(function () use ($series, $request) {
          $runId = 'sr-' . $series->id . '-' . now()->format('YmdHis') . '-' . substr(md5((string) microtime(true)), 0, 6);

      $normalize = fn(string $name) => $this->normalizeCategory($name);

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
       | Per-category best-N overrides
       ------------------------------------------------- */
      $rankingListsByCat = RankingList::where('series_id', $series->id)
        ->get()
        ->keyBy('category_id');

      $seriesDefaultBestN = (int) ($series->best_num_of_scores ?? 9999);

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

        /* Resolve per-category best-N */
        $rankingList = $rankingListsByCat->get($canonicalCategoryId);
        $bestN = (int) ($rankingList?->best_num_of_scores ?? $seriesDefaultBestN);

        Log::debug('Category start', [
          'run_id' => $runId,
          'category_key' => $categoryKey,
          'canonical_category_id' => $canonicalCategoryId,
          'players' => $players->keys()->count(),
          'best_n' => $bestN,
        ]);

        /* Execute strategy */
        $rows = $strategy->rank(
          $players,
          $pointsMap,
          $series,
          $bestN
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
            'meta_json' => $row['meta'],
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
      'message' => '[Legacy] Category-based series rankings rebuilt successfully',
    ]);
  }

  /**
   * Show the Blade audit view (legacy event/category summary table).
   */
  public function audit(Series $series)
  {
    $this->authorize('view', $series);

    $eventIds = $series->events->pluck('id')->values()->toArray();
    $pointsMap = $series->points->pluck('score', 'position')->toArray();
    $categoryNames = Category::pluck('name', 'id')->toArray();

    // Gather raw results grouped by event and category
    $rawResults = CategoryResult::query()
      ->join('registrations', 'registrations.id', '=', 'category_results.registration_id')
      ->join('player_registrations', 'player_registrations.registration_id', '=', 'registrations.id')
      ->whereIn('category_results.event_id', $eventIds)
      ->select(
        'category_results.event_id',
        'category_results.category_id',
        'player_registrations.player_id',
        'category_results.position'
      )
      ->get();

    // Build per-event summary
    $eventSummary = $series->events->map(function ($event) use ($rawResults, $categoryNames) {
      $eventRows = $rawResults->where('event_id', $event->id);
      $categoriesWithResults = $eventRows->pluck('category_id')->unique()->values();

      return [
        'event'              => $event,
        'result_rows'        => $eventRows->count(),
        'categories'         => $categoriesWithResults->map(fn($id) => [
          'id'      => $id,
          'name'    => $categoryNames[$id] ?? 'Unknown',
          'players' => $eventRows->where('category_id', $id)->pluck('player_id')->unique()->count(),
        ])->values(),
        'has_results'        => $eventRows->isNotEmpty(),
      ];
    });

    // Build per-category summary (merged by normalised name, falling back to category ID)
    $merged = $rawResults->groupBy(function ($r) use ($categoryNames) {
      $name = $categoryNames[$r->category_id] ?? null;
      return $name !== null
        ? $this->normalizeCategory($name)
        : 'category-id-' . $r->category_id;
    });

    $categorySummary = $merged->map(function ($rows, $key) use ($categoryNames, $pointsMap) {
      $categoryId = $rows->pluck('category_id')->unique()->first();
      $playerCount = $rows->pluck('player_id')->unique()->count();
      $eventsRepresented = $rows->pluck('event_id')->unique()->count();

      // Positions present in results
      $positionCounts = $rows->groupBy('position')->map->count();

      // Positions that have no points defined
      $missingPoints = $rows->pluck('position')->unique()
        ->filter(fn($pos) => !isset($pointsMap[$pos]))
        ->values();

      return [
        'category_key'       => $key,
        'category_name'      => $categoryNames[$categoryId] ?? 'Unknown',
        'category_id'        => $categoryId,
        'player_count'       => $playerCount,
        'events_represented' => $eventsRepresented,
        'position_counts'    => $positionCounts,
        'missing_points'     => $missingPoints,
      ];
    })->values();

    // Existing ranking rows
    $existingRankings = SeriesRanking::where('series_id', $series->id)
      ->orderBy('category_id')
      ->orderBy('rank_position')
      ->with(['player', 'category'])
      ->get();

    $rankingsByCategory = $existingRankings->groupBy('category_id');

    return view('backend.ranking.series.audit', [
      'series'           => $series,
      'eventSummary'     => $eventSummary,
      'categorySummary'  => $categorySummary,
      'pointsMap'        => $pointsMap,
      'rankingsByCategory' => $rankingsByCategory,
      'totalRankingRows' => $existingRankings->count(),
    ]);
  }
}
