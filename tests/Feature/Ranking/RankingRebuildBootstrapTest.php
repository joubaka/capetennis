<?php

namespace Tests\Feature\Ranking;

use App\Domain\Ranking\Services\RankingRebuildService;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Series;
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
}
