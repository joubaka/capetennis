<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{CategoryEvent, Draw, Event, NoProfileTeamPlayer, Team, TeamFixture, TeamPlayer, TeamTie, User, Player};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerOrderFixtureSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function roster(): array
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['category_event_id' => $category->id]);
        $profile = TeamPlayer::create(['team_id' => $team->id, 'player_id' => Player::factory()->create()->id, 'rank' => 1, 'pay_status' => 1]);
        $imported = NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Imported', 'surname' => 'Player', 'rank' => 2, 'pay_status' => 0]);
        return [$event, $team, $profile, $imported];
    }

    private function reorder(Team $team, TeamPlayer $profile, NoProfileTeamPlayer $imported)
    {
        return $this->postJson(route('team.order.player.list'), ['team_id' => $team->id, 'order' => [
            ['type' => 'noprofile', 'id' => $imported->id, 'position' => 1],
            ['type' => 'profile', 'id' => $profile->id, 'position' => 2],
        ]]);
    }

    public function test_existing_legacy_fixtures_block_reorder_without_changing_ranks_or_match_history(): void
    {
        [$event, $team, $profile, $imported] = $this->roster();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'match_nr' => 1, 'round_nr' => 1, 'numSets' => 3, 'match_status' => 1]);
        $before = $fixture->fresh()->getAttributes();
        $this->reorder($team, $profile, $imported)->assertConflict()->assertJsonFragment(['message' => 'This event already has generated matches. Use the regional team selection playing order to review the affected matches and confirm a safe move. Existing bookings and played results have been retained.']);
        $this->assertSame(1, (int) $profile->fresh()->rank);
        $this->assertSame(2, (int) $imported->fresh()->rank);
        $this->assertSame(1, (int) $profile->fresh()->pay_status);
        $this->assertSame($before, $fixture->fresh()->getAttributes());
        $this->assertDatabaseCount('team_fixtures', 1);
        $this->assertDatabaseCount('draw_audit_logs', 0);
    }

    public function test_generated_ties_also_block_reorder_before_rubbers_exist(): void
    {
        [$event, $team, $profile, $imported] = $this->roster();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        TeamTie::create(['draw_id' => $draw->id, 'home_team_id' => $team->id, 'away_team_id' => $team->id, 'round_nr' => 1, 'tie_nr' => 1, 'status' => 'draft']);
        $this->reorder($team, $profile, $imported)->assertConflict();
        $this->assertSame(1, (int) $profile->fresh()->rank);
        $this->assertDatabaseCount('team_ties', 1);
    }

    public function test_fixtures_in_another_event_do_not_block_an_empty_events_roster(): void
    {
        [$event, $team, $profile, $imported] = $this->roster();
        $draw = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'match_nr' => 1, 'round_nr' => 1, 'numSets' => 3]);
        $this->reorder($team, $profile, $imported)->assertOk();
        $this->assertSame(2, (int) $profile->fresh()->rank);
        $this->assertSame(1, (int) $imported->fresh()->rank);
        $this->assertDatabaseCount('team_fixtures', 1);
    }

    public function test_unprivileged_user_cannot_change_player_order(): void
    {
        [$event, $team, $profile, $imported] = $this->roster();
        $this->actingAs(User::factory()->create());
        $this->reorder($team, $profile, $imported)->assertForbidden();
        $this->assertSame(1, (int) $profile->fresh()->rank);
    }
}
