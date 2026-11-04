<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Draw, Event, EventType, NoProfileTeamPlayer, Player, Team, TeamFixture, TeamPaymentOrder, TeamPlayer, TeamRegion, User};
use App\Services\{EventOperationsService, TeamEventOverviewStats};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamEventOverviewStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_counts_only_owned_teams_and_occupied_authoritative_slots(): void
    {
        $type = EventType::forceCreate(['name' => 'Team', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => $type->id]);
        $other = Event::factory()->create(['eventType' => $type->id]);
        $region = TeamRegion::create(['region_name' => 'Shared']);
        $event->regions()->attach($region);
        $other->regions()->attach($region);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $other->id]);
        $team = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $category->id]);
        $imported = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $category->id, 'noProfile' => true]);
        $otherTeam = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $otherCategory->id]);
        Team::factory()->create(['region_id' => $region->id, 'category_event_id' => null]);
        $exclusive = TeamRegion::create(['region_name' => 'Exclusive legacy']);
        $event->regions()->attach($exclusive);
        Team::factory()->create(['region_id' => $exclusive->id, 'category_event_id' => null]);
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $otherTeam->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 1]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 1]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => 0, 'rank' => 2, 'pay_status' => 0]);
        NoProfileTeamPlayer::create(['team_id' => $imported->id, 'name' => 'Imported', 'surname' => 'Player', 'rank' => 1, 'player_profile' => $player->id, 'pay_status' => 0]);
        TeamPlayer::create(['team_id' => $imported->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 1]);
        NoProfileTeamPlayer::create(['team_id' => $imported->id, 'name' => 'Unlinked', 'rank' => 2, 'pay_status' => 0]);
        TeamPlayer::create(['team_id' => $imported->id, 'player_id' => $player->id, 'rank' => 2, 'pay_status' => 1]);
        NoProfileTeamPlayer::create(['team_id' => $imported->id, 'name' => ' ', 'rank' => 3, 'pay_status' => 0]);

        foreach ([[], ['pay_status' => true], ['withdrawn_at' => now()], ['withdrawn_at' => now(), 'pay_status' => true, 'refund_status' => 'pending'], ['collection_status' => 'reconciliation_required']] as $attributes) {
            TeamPaymentOrder::create($attributes + ['event_id' => $event->id, 'team_id' => $team->id, 'player_id' => Player::factory()->create()->id, 'pay_status' => false]);
        }
        TeamPaymentOrder::create(['event_id' => $other->id, 'pay_status' => false, 'refund_status' => 'pending']);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => true]);
        $complete = TeamFixture::create(['draw_id' => $draw->id, 'numSets' => 3, 'fixture_type' => 1, 'match_nr' => 1]);
        $partial = TeamFixture::create(['draw_id' => $draw->id, 'numSets' => 3, 'fixture_type' => 1, 'match_nr' => 1]);
        foreach ([$complete->id => 2, $partial->id => 1] as $id => $sets) {
            for ($set = 1; $set <= $sets; $set++) {
                DB::table('team_fixture_results')->insert(['team_fixture_id' => $id, 'set_nr' => $set, 'team1_score' => 6, 'team2_score' => 2]);
            }
        }
        TeamFixture::create(['draw_id' => Draw::factory()->create(['event_id' => $other->id])->id, 'numSets' => 1, 'match_nr' => 1]);

        $stats = app(TeamEventOverviewStats::class)->for($event);
        foreach (['teams' => 3, 'regions' => 2, 'players' => 3, 'entries' => 3, 'paid' => 2, 'pending_checkouts' => 1, 'withdrawals' => 1, 'pending_refunds' => 1, 'matchesTotal' => 2, 'matchesPlayed' => 1, 'drawsLocked' => 1] as $key => $expected) {
            $this->assertSame($expected, $stats[$key], $key);
        }
        $mirror = TeamPlayer::where('team_id', $imported->id)->where('rank', 1)->firstOrFail();
        $mirror->update(['rank' => 4]);
        $this->assertSame(1, app(TeamEventOverviewStats::class)->for($event)['paid']);
        $mirror->update(['rank' => 1, 'player_id' => Player::factory()->create()->id]);
        $this->assertSame(1, app(TeamEventOverviewStats::class)->for($event)['paid']);
        $mirror->update(['player_id' => $player->id]);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $operations = app(EventOperationsService::class)->for($event);
        $this->assertSame(3, $operations['counts']['entries']);
        $this->assertSame(2, $operations['counts']['paid']);
        $this->assertSame(1, $operations['counts']['pending_checkouts']);
        $this->assertSame(route('admin.events.transactions', $event), $operations['warnings']->firstWhere('key', 'pending_checkouts')['action']);
        $this->get(route('admin.events.overview', $event))->assertOk()->assertViewHas('stats', fn ($value) => $value === $stats);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $operations = app(EventOperationsService::class)->for($event);
        $this->assertArrayNotHasKey('pending_checkouts', $operations['counts']);
        $this->assertNull($operations['warnings']->firstWhere('key', 'pending_checkouts'));
        $this->get(route('admin.events.overview', $event))->assertOk()->assertDontSee('data-metric="pending_checkouts"', false);
        $this->get(route('admin.events.overview', $other))->assertForbidden();
    }

    public function test_draw_total_is_exact_even_when_readiness_is_bounded(): void
    {
        $type = EventType::forceCreate(['name' => 'Team', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => $type->id]);
        Draw::factory()->count(101)->create(['event_id' => $event->id]);
        $operations = app(EventOperationsService::class)->for($event);
        $this->assertSame(101, $operations['counts']['draws']);
        $this->assertCount(100, $operations['readiness']);
    }
}
