<?php

namespace Tests\Feature\Ranking;

use App\Models\Category;
use App\Models\Event;
use App\Models\Player;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RankingAuditPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_shows_only_the_active_snapshot_and_excludes_archived_rows(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $series = Series::factory()->create();
        $event = Event::factory()->create(['series_id' => $series->id]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        $category = Category::factory()->create(['name' => 'U/15 Girls']);
        $player = Player::factory()->create();

        SeriesRanking::create([
            'series_id' => $series->id,
            'category_id' => $category->id,
            'player_id' => $player->id,
            'rank_position' => 1,
            'total_points' => 1000,
            'status' => 'archived',
            'run_id' => 'old-run',
            'meta_json' => [],
        ]);
        SeriesRanking::create([
            'series_id' => $series->id,
            'category_id' => $category->id,
            'player_id' => $player->id,
            'rank_position' => 1,
            'total_points' => 2000,
            'status' => 'published',
            'run_id' => 'active-run',
            'meta_json' => [],
        ]);

        $this->actingAs($admin)
            ->get(route('ranking.series.audit', $series))
            ->assertOk()
            ->assertViewHas('activeRunId', 'active-run')
            ->assertViewHas('activeStatus', 'published')
            ->assertViewHas('totalRankingRows', 1)
            ->assertViewHas('archivedRankingRows', 1)
            ->assertViewHas('rankingsByCategory', function ($groups) use ($category): bool {
                return $groups->get($category->id)?->pluck('total_points')->all() === [2000];
            })
            ->assertSee('Active Rankings Snapshot')
            ->assertSee('1 archived row excluded from the totals below.')
            ->assertDontSee('1000');
    }

    public function test_audit_does_not_present_archived_rows_as_current_when_no_active_run_exists(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $series = Series::factory()->create();
        $event = Event::factory()->create(['series_id' => $series->id]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        SeriesRanking::create([
            'series_id' => $series->id,
            'category_id' => Category::factory()->create()->id,
            'player_id' => Player::factory()->create()->id,
            'rank_position' => 1,
            'total_points' => 1000,
            'status' => 'archived',
            'run_id' => 'old-run',
            'meta_json' => [],
        ]);

        $this->actingAs($admin)
            ->get(route('ranking.series.audit', $series))
            ->assertOk()
            ->assertViewHas('activeRunId', null)
            ->assertViewHas('totalRankingRows', 0)
            ->assertSee('No calculated, reviewed or published ranking is currently available.')
            ->assertDontSee('Active Rankings Snapshot');
    }
}
