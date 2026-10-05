<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{Category, CategoryEvent, Draw, Event, Player, Team, TeamFixture, TeamFixturePlayer, TeamPlayer, TeamRegion, TeamTie, User};
use App\Services\TeamSubstitutionService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSubstitutionCurrentReadTest extends TestCase
{
    public function test_mysql_current_reads_protect_play_changed_after_consistent_snapshot(): void
    {
        if (DB::getDriverName() !== 'mysql') $this->markTestSkipped('Requires two isolated MySQL connections.');
        $this->assertSame('ct_testing', DB::selectOne('SELECT DATABASE() AS db')->db);
        // This two-connection test needs committed fixtures. Leave the isolated
        // schema intact instead of invoking unrelated legacy migration down paths.
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $event = Event::factory()->create(['entryFee' => 0, 'start_date' => '2026-12-01']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id,
            'category_id' => Category::factory()->create(['name' => 'u/13 Boys'])->id, 'entry_fee' => 0]);
        $region = TeamRegion::create(['region_name' => 'Synthetic North', 'region_fee' => 0]);
        $team = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id]);
        $away = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id]);
        $old = Player::factory()->create(['gender' => 1, 'dateOfBirth' => '2014-01-01']);
        $new = Player::factory()->create(['gender' => 1, 'dateOfBirth' => '2014-02-01']);
        TeamPlayer::create(['team_id' => $team->id, 'rank' => 1, 'player_id' => $old->id]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => false, 'published' => false]);
        $tie = TeamTie::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => $team->id, 'away_team_id' => $away->id, 'status' => 'draft']);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'round_nr' => 1,
            'tie_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1, 'numSets' => 3, 'match_status' => 0]);
        TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1, 'team1_id' => $old->id]);
        config(['database.connections.substitution_observer' => config('database.connections.mysql')]);
        DB::purge('substitution_observer');
        $observer = DB::connection('substitution_observer');
        DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        DB::beginTransaction();
        try {
            $this->assertSame(0, (int) DB::table('team_fixtures')->where('id', $fixture->id)->value('match_status'));
            $observer->table('team_fixtures')->where('id', $fixture->id)->update(['match_status' => 1]);
            // The ordinary snapshot stays old; the planner must use current locked
            // reads rather than eager-loaded copies after acquiring locks.
            $this->assertSame(0, (int) DB::table('team_fixtures')->where('id', $fixture->id)->value('match_status'));
            $preview = app(TeamSubstitutionService::class)->preview($team->fresh(), ['old_type' => 'profile',
                'old_id' => $old->id, 'new_type' => 'profile', 'new_id' => $new->id,
                'scope' => 'next', 'reason' => 'Synthetic replacement'], $admin);
            $this->assertSame([], $preview['selected_ids']);
            $this->assertTrue($preview['fixtures'][0]['protected']);
            $this->assertDatabaseCount('team_substitutions', 0);
        } finally {
            DB::rollBack();
            DB::disconnect('substitution_observer');
        }
    }
}
