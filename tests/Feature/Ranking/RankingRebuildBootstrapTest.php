<?php

namespace Tests\Feature\Ranking;

use App\Domain\Ranking\Services\RankingRebuildService;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Player;
use App\Models\RankingList;
use App\Models\Registration;
use App\Models\Series;
use App\Models\SeriesRanking;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RankingRebuildBootstrapTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rebuild_bootstraps_canonical_lists_for_a_series_with_saved_results(): void
    {
        $series = Series::factory()->create([
            'best_num_of_scores' => 2,
            'auto_award_rule' => false,
        ]);
        $category = Category::factory()->create();
        $player = Player::factory()->create();

        DB::table('points')->insert([
            ['series_id' => $series->id, 'position' => 1, 'score' => 1000],
        ]);

        foreach ([now()->subMonth()->toDateString(), now()->toDateString()] as $eventDate) {
            $event = Event::factory()->create([
                'series_id' => $series->id,
                'start_date' => $eventDate,
            ]);
            $categoryEvent = CategoryEvent::factory()->create([
                'event_id' => $event->id,
                'category_id' => $category->id,
            ]);
            $registration = Registration::factory()->create();

            DB::table('player_registrations')->insert([
                'registration_id' => $registration->id,
                'player_id' => $player->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('category_event_registrations')->insert([
                'category_event_id' => $categoryEvent->id,
                'registration_id' => $registration->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('category_results')->insert([
                'event_id' => $event->id,
                'category_id' => $category->id,
                'registration_id' => $registration->id,
                'position' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(0, $series->ranking_lists()->count());

        $report = app(RankingRebuildService::class)->rebuild($series);
        $list = $series->ranking_lists()->firstOrFail();

        $this->assertSame(1, $report['total_rows']);
        $this->assertSame(1, $report['topology']['created_lists']);
        $this->assertSame(2, $report['topology']['linked_category_events']);
        $this->assertSame(2, DB::table('ranking_list_category_events')
            ->where('ranking_list_id', $list->id)->count());
        $this->assertDatabaseHas('series_rankings', [
            'series_id' => $series->id,
            'ranking_list_id' => $list->id,
            'player_id' => $player->id,
            'total_points' => 2000,
            'status' => 'calculated',
        ]);
    }

    public function test_zero_row_rebuild_preserves_existing_legacy_rankings(): void
    {
        $series = Series::factory()->create(['auto_award_rule' => false]);
        $category = Category::factory()->create();
        $list = RankingList::factory()->create([
            'series_id' => $series->id,
            'category_id' => $category->id,
        ]);
        $player = Player::factory()->create();

        $legacy = SeriesRanking::create([
            'series_id' => $series->id,
            'ranking_list_id' => null,
            'category_id' => $category->id,
            'player_id' => $player->id,
            'rank_position' => 1,
            'total_points' => 1000,
            'status' => 'calculated',
            'run_id' => null,
            'meta_json' => [],
        ]);

        $report = app(RankingRebuildService::class)->rebuild($series);

        $this->assertSame(0, $report['total_rows']);
        $this->assertFalse($report['persisted']);
        $this->assertStringContainsString('preserved', implode(' ', $report['warnings']));
        $this->assertDatabaseHas('series_rankings', ['id' => $legacy->id]);
        $this->assertSame(0, DB::table('ranking_list_category_events')
            ->where('ranking_list_id', $list->id)->count());
    }

    public function test_existing_empty_list_is_linked_by_category_name_before_rebuild(): void
    {
        $series = Series::factory()->create([
            'best_num_of_scores' => 2,
            'auto_award_rule' => false,
        ]);
        $listCategory = Category::factory()->create(['name' => 'U/12 Boys']);
        $eventCategory = Category::factory()->create(['name' => '  u/12   BOYS  ']);
        $list = RankingList::factory()->create([
            'series_id' => $series->id,
            'category_id' => $listCategory->id,
        ]);
        $player = Player::factory()->create();

        DB::table('points')->insert([
            ['series_id' => $series->id, 'position' => 1, 'score' => 1000],
        ]);
        $categoryEvent = $this->seedResult($series, $eventCategory, $player, now()->toDateString());
        $staleCategory = Category::factory()->create();
        $staleRanking = SeriesRanking::create([
            'series_id' => $series->id,
            'ranking_list_id' => null,
            'category_id' => $staleCategory->id,
            'player_id' => Player::factory()->create()->id,
            'rank_position' => 1,
            'total_points' => 500,
            'status' => 'calculated',
            'run_id' => null,
            'meta_json' => [],
        ]);

        $report = app(RankingRebuildService::class)->rebuild($series);

        $this->assertTrue($report['persisted']);
        $this->assertSame(1, $report['total_rows']);
        $this->assertSame(1, $report['topology']['linked_category_events']);
        $this->assertDatabaseHas('ranking_list_category_events', [
            'ranking_list_id' => $list->id,
            'category_event_id' => $categoryEvent->id,
        ]);
        $this->assertDatabaseMissing('series_rankings', ['id' => $staleRanking->id]);
    }

    public function test_bootstrap_merges_same_named_categories_with_different_ids(): void
    {
        $series = Series::factory()->create([
            'best_num_of_scores' => 2,
            'auto_award_rule' => false,
        ]);
        $firstCategory = Category::factory()->create(['name' => 'U/15 Girls']);
        $secondCategory = Category::factory()->create(['name' => ' u/15   girls ']);
        $player = Player::factory()->create();

        DB::table('points')->insert([
            ['series_id' => $series->id, 'position' => 1, 'score' => 1000],
        ]);
        $this->seedResult($series, $firstCategory, $player, now()->subMonth()->toDateString());
        $this->seedResult($series, $secondCategory, $player, now()->toDateString());

        $report = app(RankingRebuildService::class)->rebuild($series);
        $list = $series->ranking_lists()->firstOrFail();

        $this->assertTrue($report['persisted']);
        $this->assertSame(1, $series->ranking_lists()->count());
        $this->assertSame(2, DB::table('ranking_list_category_events')
            ->where('ranking_list_id', $list->id)->count());
        $this->assertDatabaseHas('series_rankings', [
            'series_id' => $series->id,
            'ranking_list_id' => $list->id,
            'player_id' => $player->id,
            'total_points' => 2000,
        ]);
    }

    private function seedResult(Series $series, Category $category, Player $player, string $eventDate): CategoryEvent
    {
        $event = Event::factory()->create([
            'series_id' => $series->id,
            'start_date' => $eventDate,
        ]);
        $categoryEvent = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'category_id' => $category->id,
        ]);
        $registration = Registration::factory()->create();

        DB::table('player_registrations')->insert([
            'registration_id' => $registration->id,
            'player_id' => $player->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('category_event_registrations')->insert([
            'category_event_id' => $categoryEvent->id,
            'registration_id' => $registration->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('category_results')->insert([
            'event_id' => $event->id,
            'category_id' => $category->id,
            'registration_id' => $registration->id,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $categoryEvent;
    }
}
