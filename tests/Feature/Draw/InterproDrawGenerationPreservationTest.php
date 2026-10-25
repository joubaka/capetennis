<?php

namespace Tests\Feature\Draw;

use App\Models\{Category, CategoryEvent, Draw, Event, EventType, Player, Team, TeamFixture, TeamFixturePlayer, TeamFixtureResult, TeamRegion, User, Venue};
use App\Services\FixtureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterproDrawGenerationPreservationTest extends TestCase
{
    use RefreshDatabase;

    private function eventWithAdmin(int $baseType = EventType::TEAM, ?string $code = null): Event
    {
        $typeId = DB::table('eventtypes')->insertGetId(['name' => $code ? 'Interprovincial Trials' : 'Team competition',
            'type' => $baseType, 'code' => $code]);
        $event = Event::factory()->create(['eventType' => $typeId]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        return $event;
    }

    public function test_bulk_generation_preserves_existing_draws_results_rosters_and_schedule(): void
    {
        $event = $this->eventWithAdmin();
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => true, 'published' => true, 'oop_published' => true]);
        $otherDraw = Draw::factory()->create();
        $venue = Venue::forceCreate(['name' => 'Synthetic venue']);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'match_nr' => 1, 'match_status' => 1, 'scheduled' => 1, 'scheduled_at' => '2026-10-25 08:00:00',
            'venue_id' => $venue->id, 'court_label' => '1', 'duration_min' => 60]);
        $result = TeamFixtureResult::create(['team_fixture_id' => $fixture->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 3]);
        $slot = TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'team1_id' => Player::factory()->create()->id]);
        $before = $fixture->fresh()->getAttributes();
        foreach ([[], ['onlyCategories' => ['U/13 Boys']]] as $payload) {
            $this->postJson(route('headoffice.createFixtures', $event), $payload)
                ->assertConflict()->assertJsonPath('message', 'This event already has draws. Use the draw workspace to review or regenerate one draw at a time.');
            $this->assertSame($before, $fixture->fresh()->getAttributes());
            $this->assertDatabaseHas('team_fixture_results', ['id' => $result->id]);
            $this->assertDatabaseHas('team_fixture_players', ['id' => $slot->id]);
            $this->assertDatabaseHas('draws', ['id' => $draw->id, 'published' => 1, 'locked' => 1, 'oop_published' => 1]);
            $this->assertDatabaseHas('draws', ['id' => $otherDraw->id]);
            $this->assertDatabaseCount('draws', 2);
            $this->assertDatabaseCount('team_fixtures', 1);
        }
    }

    public function test_empty_team_event_can_generate_once_without_touching_other_events(): void
    {
        $event = $this->eventWithAdmin();
        CategoryEvent::factory()->create(['event_id' => $event->id,
            'category_id' => Category::factory()->create(['name' => 'U/13 Boys'])->id]);
        foreach (['Synthetic North', 'Synthetic South'] as $name) {
            $region = TeamRegion::create(['region_name' => $name]);
            $event->regions()->attach($region->id, ['ordering' => $region->id]);
            $team = Team::factory()->create(['region_id' => $region->id, 'name' => 'U/13 Boys']);
            foreach (range(1, 4) as $rank) $team->players()->attach(Player::factory()->create()->id, ['rank' => $rank]);
        }
        $foreign = Draw::factory()->create();
        app(FixtureService::class)->createDrawsAndFixtures($event, 'perType', ['U/13 Boys']);
        $drawIds = $event->draws()->pluck('id')->all();
        $this->assertNotEmpty($drawIds);
        $fixtureIds = TeamFixture::whereIn('draw_id', $drawIds)->pluck('id')->all();
        $this->assertNotEmpty($fixtureIds);
        $this->assertDatabaseHas('draws', ['id' => $foreign->id]);
        $this->postJson(route('headoffice.createFixtures', $event))->assertConflict();
        $this->assertSame($drawIds, $event->draws()->pluck('id')->all());
        $this->assertSame($fixtureIds, TeamFixture::whereIn('draw_id', $drawIds)->pluck('id')->all());
    }

    public function test_individual_and_trials_events_cannot_use_team_bulk_generation(): void
    {
        foreach ([null, EventType::INTERPROVINCIAL_TRIALS_CODE] as $code) {
            $event = $this->eventWithAdmin(EventType::INDIVIDUAL, $code);
            $this->postJson(route('headoffice.createFixtures', $event))->assertUnprocessable();
        }
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('team_fixtures', 0);
    }

    public function test_foreign_event_and_invalid_generation_options_cannot_write_draws(): void
    {
        $this->eventWithAdmin();
        $foreign = Event::factory()->create();
        $this->postJson(route('headoffice.createFixtures', $foreign))->assertForbidden();
        $event = $this->eventWithAdmin();
        $this->postJson(route('headoffice.createFixtures', $event), ['mode' => 'unsupported'])->assertUnprocessable();
        $this->postJson(route('headoffice.createFixtures', $event), ['onlyCategories' => 'U/13 Boys'])->assertUnprocessable();
        $this->assertDatabaseCount('draws', 0);
    }
}
