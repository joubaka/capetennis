<?php

namespace Tests\Feature\Cleanup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DrawDataCleanupSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_orphan_draw_children_are_previewed_then_removed_with_confirmation(): void
    {
        $fixtureId = DB::table('team_fixtures')->insertGetId([
            'draw_id' => 999999, 'match_nr' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('team_fixture_players')->insert([
            'team_fixture_id' => $fixtureId, 'team1_id' => null, 'team2_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('data:cleanup-orphan-draw-children --dry-run')->assertSuccessful();
        $this->assertDatabaseHas('team_fixtures', ['id' => $fixtureId]);

        $this->artisan('data:cleanup-orphan-draw-children --confirm')->assertSuccessful();
        $this->assertDatabaseMissing('team_fixtures', ['id' => $fixtureId]);
        $this->assertDatabaseMissing('team_fixture_players', ['team_fixture_id' => $fixtureId]);
    }

    public function test_duplicate_team_results_keep_latest_row(): void
    {
        $fixtureId = DB::table('team_fixtures')->insertGetId([
            'draw_id' => 1, 'match_nr' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $oldId = DB::table('team_fixture_results')->insertGetId([
            'team_fixture_id' => $fixtureId, 'set_nr' => 1,
            'team1_score' => 6, 'team2_score' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $keepId = DB::table('team_fixture_results')->insertGetId([
            'team_fixture_id' => $fixtureId, 'set_nr' => 1,
            'team1_score' => 6, 'team2_score' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('data:cleanup-duplicate-team-fixture-results --confirm')->assertSuccessful();

        $this->assertDatabaseMissing('team_fixture_results', ['id' => $oldId]);
        $this->assertDatabaseHas('team_fixture_results', ['id' => $keepId]);
    }
}
