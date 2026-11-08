<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\TeamFixture;
use App\Models\Venue;
use App\Services\Scheduling\EventVenueScheduleService;
use App\Services\TeamFixtureScoreService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as Events;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VenueScheduleScoreConcurrencyTest extends TestCase
{
    public static function scoringWindows(): array
    {
        return ['during planning' => [false], 'immediately before write locks' => [true]];
    }

    #[DataProvider('scoringWindows')]
    public function test_score_commits_during_apply_and_stale_schedule_is_rejected(bool $afterPlanning): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires two MySQL connections.');
        }
        $this->assertSame('ct_testing', DB::selectOne('SELECT DATABASE() AS db')->db);
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);

        $event = Event::factory()->create(['start_date' => '2026-10-09', 'end_date' => '2026-10-11']);
        $type = DB::table('draw_types')->insertGetId(['type' => 'team', 'drawTypeName' => 'Team', 'btn_color' => 'primary']);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'drawType_id' => $type, 'published' => true, 'locked' => false]);
        $venue = Venue::forceCreate(['name' => 'Concurrency test courts']);
        $draw->venues()->attach($venue->id, ['num_courts' => 2]);
        $fixtures = collect([1, 2])->map(fn (int $match) => TeamFixture::create([
            'draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'match_nr' => $match, 'fixture_type' => 1, 'match_status' => 0,
        ]));
        [$scored, $unplayed] = $fixtures->all();
        $options = ['start' => '2026-10-10 08:00:00', 'end' => '2026-10-10 18:00:00'];
        $scheduler = app(EventVenueScheduleService::class);
        $preview = $scheduler->preview($event, $options);
        $this->assertCount(2, $preview['matches']);

        $primary = DB::getDefaultConnection();
        config(['database.connections.concurrent_scorer' => config("database.connections.$primary")]);
        DB::connection('concurrent_scorer')->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $submitted = false;
        $submit = function () use ($primary, $scored, &$submitted): void {
            if ($submitted) {
                return;
            }
            $submitted = true;
            DB::setDefaultConnection('concurrent_scorer');
            try {
                app(TeamFixtureScoreService::class)->save($scored->fresh(), ['set1_home' => 6, 'set1_away' => 3]);
            } finally {
                DB::setDefaultConnection($primary);
            }
        };
        if ($afterPlanning) {
            DB::connection($primary)->beforeStartingTransaction($submit);
        } else {
            Events::listen(QueryExecuted::class, function (QueryExecuted $query) use ($primary, $submit): void {
                if ($query->connectionName === $primary && str_contains($query->sql, 'event_venue_schedule_drafts')) {
                    $submit();
                }
            });
        }

        try {
            $scheduler->apply($event, $options, $preview['revision']);
            $this->fail('Apply must reject the preview after the score commits.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('fresh preview', $exception->getMessage());
        } finally {
            DB::disconnect('concurrent_scorer');
        }

        $this->assertTrue($submitted);
        $this->assertDatabaseHas('team_fixture_results', [
            'team_fixture_id' => $scored->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 3,
        ]);
        $this->assertNull($scored->fresh()->scheduled_at);
        $this->assertNull($unplayed->fresh()->scheduled_at);

        $fresh = $scheduler->preview($event, $options);
        $this->assertSame(1, $scheduler->apply($event, $options, $fresh['revision'])['count']);
        $this->assertNotNull($unplayed->fresh()->scheduled_at);
        $this->assertNull($scored->fresh()->scheduled_at);
    }
}
