<?php

namespace App\Console\Commands;

use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\RankingList;
use App\Services\Ranking\RankingEngine;
use App\Models\CategoryResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RebuildSeriesRankingLegacy extends Command
{
    protected $signature = 'ranking:rebuild-legacy {series_id}';
    protected $description = 'Rebuild series rankings using legacy category_results path';

    public function handle()
    {
        $seriesId = $this->argument('series_id');
        $series = Series::with(['points', 'events', 'ranking_lists', 'rankType'])->findOrFail($seriesId);

        $pointsMap = $series->points->pluck('score', 'position')->toArray();
        $eventIds = $series->events->pluck('id')->values()->toArray();
        $categoryNames = \App\Models\Category::pluck('name', 'id')->toArray();

        $normalize = fn(string $name) => strtolower(trim(preg_replace('/\s+/', ' ', $name)));

        $raw = CategoryResult::query()
            ->join('registrations', 'registrations.id', '=', 'category_results.registration_id')
            ->join('player_registrations', 'player_registrations.registration_id', '=', 'registrations.id')
            ->whereIn('category_results.event_id', $eventIds)
            ->select('category_results.event_id', 'category_results.category_id', 'player_registrations.player_id', 'category_results.position')
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

        $grouped = $raw->groupBy(['category_key', 'player_id']);
        $rankingListsByCat = RankingList::where('series_id', $series->id)->get()->keyBy('category_id');
        $seriesDefaultBestN = (int) ($series->best_num_of_scores ?? 9999);

        $engine = app(RankingEngine::class);
        $rankType = optional($series->rankType)->type ?? 'platteland_series';
        $strategy = $engine->resolve($rankType);

        $created = 0;

        DB::transaction(function () use ($series, $grouped, $rankingListsByCat, $seriesDefaultBestN, $strategy, $pointsMap, $categoryNames, $normalize, &$created) {
            SeriesRanking::where('series_id', $series->id)->delete();

            foreach ($grouped as $categoryKey => $players) {
                $canonicalCategoryId = $players->flatten(1)->pluck('category_id')->unique()->first();
                $rankingList = $rankingListsByCat->get($canonicalCategoryId);
                $bestN = (int) ($rankingList?->best_num_of_scores ?? $seriesDefaultBestN);

                $rows = $strategy->rank($players, $pointsMap, $series, $bestN);

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
                }
            }
        });

        $this->info("Rebuilt $created ranking rows for series $seriesId");
    }
}
