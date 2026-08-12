<?php

namespace Tests\Feature\Ranking;

use App\Domain\Ranking\Enums\RankingStatus;
use App\Domain\Ranking\Services\RankingPublicationService;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\RankingList;
use App\Models\User;
use App\Models\Event;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RankingPublicationTest
 *
 * Covers:
 *  1.  Publish fails when no reviewed rows exist
 *  2.  Publish moves reviewed rows to published
 *  3.  Existing published rows are archived on re-publish
 *  4.  Rollback restores archived snapshot
 *  5.  Published rows cannot be overwritten by rebuild (requireNotPublished guard)
 *  6.  Admin can mark rows reviewed
 *  7.  HTTP publish endpoint requires auth
 *  8.  HTTP review endpoint requires auth
 */
class RankingPublicationTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeSeries(): Series
    {
        $series = Series::factory()->create(['best_num_of_scores' => 2, 'auto_award_rule' => false]);
        RankingList::factory()->create(['series_id' => $series->id, 'category_id' => 1]);

        return $series;
    }

    private function seedRows(Series $series, string $status, int $count = 3, string $runId = 'test-run'): void
    {
        $listId = $series->ranking_lists()->value('id');
        for ($i = 1; $i <= $count; $i++) {
            SeriesRanking::create([
                'series_id'     => $series->id,
                'ranking_list_id' => $listId,
                'category_id'   => 1,
                'player_id'     => $i,
                'rank_position' => $i,
                'total_points'  => 1000 - ($i * 100),
                'status'        => $status,
                'run_id'        => $runId,
                'meta_json'     => [],
            ]);
        }
    }

    private function service(): RankingPublicationService
    {
        return app(RankingPublicationService::class);
    }

    // ------------------------------------------------------------------
    // 1. Publish fails when no reviewed rows exist
    // ------------------------------------------------------------------

    public function test_publish_throws_when_no_reviewed_rows(): void
    {
        $series = $this->makeSeries();
        $this->seedRows($series, RankingStatus::Calculated->value);

        $this->expectException(\RuntimeException::class);
        $this->service()->publish($series, $this->admin->id);
    }

    // ------------------------------------------------------------------
    // 2. Publish moves reviewed rows to published
    // ------------------------------------------------------------------

    public function test_publish_promotes_reviewed_to_published(): void
    {
        $series = $this->makeSeries();
        $this->seedRows($series, RankingStatus::Reviewed->value);

        $this->service()->publish($series, $this->admin->id);

        $this->assertTrue($series->fresh()->leaderboard_published);

        $this->assertEquals(
            3,
            SeriesRanking::where('series_id', $series->id)
                ->where('status', RankingStatus::Published->value)
                ->count()
        );
    }

    // ------------------------------------------------------------------
    // 3. Existing published rows are archived on re-publish
    // ------------------------------------------------------------------

    public function test_re_publish_archives_previous_publication(): void
    {
        $series = $this->makeSeries();
        $this->seedRows($series, RankingStatus::Published->value, 3, 'run-old');
        $this->seedRows($series, RankingStatus::Reviewed->value, 3, 'run-new');

        $this->service()->publish($series, $this->admin->id);

        $this->assertEquals(
            3,
            SeriesRanking::where('series_id', $series->id)
                ->where('status', RankingStatus::Archived->value)
                ->where('run_id', 'run-old')
                ->count()
        );
        $this->assertEquals(
            3,
            SeriesRanking::where('series_id', $series->id)
                ->where('status', RankingStatus::Published->value)
                ->count()
        );
    }

    // ------------------------------------------------------------------
    // 4. Rollback restores archived snapshot
    // ------------------------------------------------------------------

    public function test_rollback_restores_archived_snapshot(): void
    {
        $series = $this->makeSeries();
        $this->seedRows($series, RankingStatus::Published->value, 3, 'run-current');
        $this->seedRows($series, RankingStatus::Archived->value, 3, 'run-previous');

        $this->service()->rollback($series, $this->admin->id);

        $this->assertEquals(
            0,
            SeriesRanking::where('series_id', $series->id)
                ->where('status', RankingStatus::Published->value)
                ->where('run_id', 'run-current')
                ->count()
        );
        $this->assertEquals(
            3,
            SeriesRanking::where('series_id', $series->id)
                ->where('status', RankingStatus::Published->value)
                ->where('run_id', 'run-previous')
                ->count()
        );
        $this->assertEquals(
            3,
            SeriesRanking::where('series_id', $series->id)
                ->where('status', RankingStatus::Archived->value)
                ->where('run_id', 'run-current')
                ->count()
        );
    }

    public function test_rollback_without_archive_preserves_current_publication(): void
    {
        $series = $this->makeSeries();
        $this->seedRows($series, RankingStatus::Published->value, 3, 'run-current');

        try {
            $this->service()->rollback($series, $this->admin->id);
            $this->fail('Rollback should fail when no archived snapshot exists.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('No archived ranking snapshot', $exception->getMessage());
        }

        $this->assertSame(3, SeriesRanking::where('series_id', $series->id)
            ->where('status', RankingStatus::Published->value)->count());
    }

    public function test_publish_rejects_mixed_reviewed_runs(): void
    {
        $series = $this->makeSeries();
        $this->seedRows($series, RankingStatus::Reviewed->value, 1, 'run-a');
        $this->seedRows($series, RankingStatus::Reviewed->value, 1, 'run-b');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exactly one reviewed ranking run');

        $this->service()->publish($series, $this->admin->id);
    }

    public function test_rollback_rejects_multiple_active_published_runs_without_changing_them(): void
    {
        $series = $this->makeSeries();
        $this->seedRows($series, RankingStatus::Published->value, 1, 'run-a');
        $this->seedRows($series, RankingStatus::Published->value, 1, 'run-b');
        $this->seedRows($series, RankingStatus::Archived->value, 1, 'run-old');

        try {
            $this->service()->rollback($series, $this->admin->id);
            $this->fail('Rollback should reject multiple active published runs.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('multiple published ranking runs', $exception->getMessage());
        }

        $this->assertSame(2, SeriesRanking::where('series_id', $series->id)
            ->where('status', RankingStatus::Published->value)->count());
    }

    // ------------------------------------------------------------------
    // 5. Published ranking immutability guard
    // ------------------------------------------------------------------

    public function test_require_not_published_throws_when_published_exists(): void
    {
        $series = $this->makeSeries();
        $this->seedRows($series, RankingStatus::Published->value);

        $this->expectException(\RuntimeException::class);
        $this->service()->requireNotPublished($series);
    }

    public function test_require_not_published_passes_when_no_published_rows(): void
    {
        $series = $this->makeSeries();
        $this->seedRows($series, RankingStatus::Calculated->value);

        // Should not throw
        $this->service()->requireNotPublished($series);
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    // 6. Mark reviewed
    // ------------------------------------------------------------------

    public function test_mark_reviewed_transitions_calculated_to_reviewed(): void
    {
        $series = $this->makeSeries();
        $this->seedRows($series, RankingStatus::Calculated->value);

        $this->service()->markReviewed($series, $this->admin->id);

        $this->assertEquals(
            3,
            SeriesRanking::where('series_id', $series->id)
                ->where('status', RankingStatus::Reviewed->value)
                ->count()
        );
    }

    // ------------------------------------------------------------------
    // 7. HTTP publish endpoint requires auth
    // ------------------------------------------------------------------

    public function test_http_publish_requires_auth(): void
    {
        $series = $this->makeSeries();

        $this->postJson("/backend/ranking/series/{$series->id}/publish")
             ->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // 8. HTTP review endpoint requires auth
    // ------------------------------------------------------------------

    public function test_http_review_requires_auth(): void
    {
        $series = $this->makeSeries();

        $this->postJson("/backend/ranking/series/{$series->id}/review")
             ->assertStatus(401);
    }

    public function test_admin_cannot_review_a_series_outside_their_events(): void
    {
        $series = $this->makeSeries();
        Event::factory()->create(['series_id' => $series->id]);

        $this->actingAs($this->admin)
            ->postJson("/backend/ranking/series/{$series->id}/review")
            ->assertForbidden();
    }

    public function test_event_admin_can_review_their_series(): void
    {
        $series = $this->makeSeries();
        $event = Event::factory()->create(['series_id' => $series->id]);
        \Illuminate\Support\Facades\DB::table('event_admins')->insert([
            'event_id' => $event->id,
            'user_id' => $this->admin->id,
        ]);
        $this->seedRows($series, RankingStatus::Calculated->value, runId: 'run-authorized');

        $this->actingAs($this->admin)
            ->postJson("/backend/ranking/series/{$series->id}/review")
            ->assertOk();
    }
}
