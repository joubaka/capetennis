<?php
// Run: php artisan tinker --execute="require '_debug_tiebreak.php';"

use Illuminate\Support\Facades\DB;
use App\Domain\Ranking\Services\RankingCalculationService;
use App\Models\Series;
use App\Models\RankingList;

$series = Series::create(['name' => 'T', 'best_num_of_scores' => 2, 'auto_award_rule' => true]);
DB::table('points')->insert([
    ['series_id' => $series->id, 'position' => 1, 'score' => 1000],
    ['series_id' => $series->id, 'position' => 2, 'score' => 800],
    ['series_id' => $series->id, 'position' => 3, 'score' => 600],
]);
$list = RankingList::create(['series_id' => $series->id, 'category_id' => 1]);

$e1 = DB::table('events')->insertGetId(['name' => 'Old', 'start_date' => now()->subDays(30)->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
$e2 = DB::table('events')->insertGetId(['name' => 'New', 'start_date' => now()->subDays(10)->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
$ce1 = DB::table('category_events')->insertGetId(['event_id' => $e1, 'category_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
$ce2 = DB::table('category_events')->insertGetId(['event_id' => $e2, 'category_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
DB::table('ranking_list_category_events')->insert([
    ['ranking_list_id' => $list->id, 'category_event_id' => $ce1, 'created_at' => now(), 'updated_at' => now()],
    ['ranking_list_id' => $list->id, 'category_event_id' => $ce2, 'created_at' => now(), 'updated_at' => now()],
]);
DB::table('positions')->insert([
    ['player_id' => 1, 'category_event_id' => $ce1, 'position' => 1, 'created_at' => now(), 'updated_at' => now()],
    ['player_id' => 1, 'category_event_id' => $ce2, 'position' => 3, 'created_at' => now(), 'updated_at' => now()],
    ['player_id' => 2, 'category_event_id' => $ce1, 'position' => 3, 'created_at' => now(), 'updated_at' => now()],
    ['player_id' => 2, 'category_event_id' => $ce2, 'position' => 1, 'created_at' => now(), 'updated_at' => now()],
]);

$result = app(RankingCalculationService::class)->calculate($list);
foreach ($result->rows as $r) {
    echo "Player {$r->playerId}: rank={$r->rankPosition} pts={$r->totalPoints} wins={$r->wins} posSum={$r->positionsSum}\n";
}
echo "ce1=$ce1 e1date=" . now()->subDays(30)->toDateString() . "\n";
echo "ce2=$ce2 e2date=" . now()->subDays(10)->toDateString() . "\n";
