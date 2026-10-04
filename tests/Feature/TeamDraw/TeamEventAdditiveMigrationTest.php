<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{Draw, TeamFixture};
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamEventAdditiveMigrationTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        // RefreshDatabase retains its in-memory PDO between tests, but this
        // truncation test uses a new connection and needs its own schema.
        if (config('database.default') === 'sqlite'
            && config('database.connections.sqlite.database') === ':memory:') {
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        // MySQL DDL commits transactions. Clean test rows without rolling back
        // unrelated historical migrations, then force the next suite to refresh.
        $this->beforeApplicationDestroyed(function () {
            $this->truncateTablesForAllConnections();
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_new_columns_preserve_populated_legacy_draws_and_results(): void
    {
        $pairings = require database_path('migrations/2026_10_04_010000_add_team_rubber_pairing_positions.php');
        $snapshots = require database_path('migrations/2026_10_04_020000_add_team_event_rules_and_snapshots.php');
        $snapshots->down();
        $pairings->down();
        try {
            $draw = Draw::factory()->create(['locked' => true, 'published' => true]);
            $fixture = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'match_nr' => 1, 'round_nr' => 1]);
            DB::table('team_fixture_results')->insert(['team_fixture_id' => $fixture->id, 'set_nr' => 1,
                'team1_score' => 6, 'team2_score' => 2]);
            $beforeDraw = (array) DB::table('draws')->where('id', $draw->id)->first();
            $beforeFixture = (array) DB::table('team_fixtures')->where('id', $fixture->id)->first();
            $beforeScores = DB::table('team_fixture_results')->where('team_fixture_id', $fixture->id)->get()->toArray();
        } finally {
            $pairings->up();
            $snapshots->up();
        }
        $afterDraw = (array) DB::table('draws')->where('id', $draw->id)->first();
        $this->assertNull($afterDraw['team_scoring_rules']);
        $this->assertNull($afterDraw['team_format_snapshot']);
        unset($afterDraw['team_scoring_rules'], $afterDraw['team_format_snapshot']);
        $this->assertSame($beforeDraw, $afterDraw);
        $this->assertSame($beforeFixture, (array) DB::table('team_fixtures')->where('id', $fixture->id)->first());
        $this->assertEquals($beforeScores, DB::table('team_fixture_results')->where('team_fixture_id', $fixture->id)->get()->toArray());
        $this->assertDatabaseCount('team_event_rules', 0);
    }
}
