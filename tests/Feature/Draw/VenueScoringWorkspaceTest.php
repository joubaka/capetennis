<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\DrawAuditLog;
use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\EventType;
use App\Models\Fixture;
use App\Models\OrderOfPlay;
use App\Models\Registration;
use App\Models\TeamFixture;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VenueScoringWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'convenor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'score-keeper', 'guard_name' => 'web']);
    }

    public function test_event_score_keeper_sees_only_the_selected_venues_fixture_queue(): void
    {
        [$event, $draw, $venue, $fixture] = $this->scheduledFixture('Main Venue');
        [, , $otherVenue, $otherFixture] = $this->scheduledFixture('Other Venue', $event, $draw);
        $user = $this->scorerFor($event);

        $response = $this->actingAs($user)->get(route('frontend.scoring.workspace', [
            'event' => $event,
            'venue' => $venue->id,
        ]));

        $response->assertOk()
            ->assertSee('Venue scoring')
            ->assertSee('Main Venue')
            ->assertSee('aria-label="Filter match queue"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('score-filter-empty', false)
            ->assertSee('Match '.$fixture->match_nr)
            ->assertDontSee('Match '.$otherFixture->match_nr);
        $this->assertNotSame($venue->id, $otherVenue->id);
    }

    public function test_unassigned_score_keeper_cannot_open_another_events_workspace(): void
    {
        [$event] = $this->scheduledFixture('Main Venue');
        $otherEvent = Event::factory()->create();
        $user = $this->scorerFor($otherEvent);

        $this->actingAs($user)
            ->get(route('frontend.scoring.workspace', $event))
            ->assertForbidden();
    }

    public function test_operator_is_remembered_and_added_to_the_score_audit(): void
    {
        [$event, $draw, , $fixture] = $this->scheduledFixture('Main Venue');
        $user = $this->scorerFor($event);

        $this->actingAs($user)
            ->post(route('frontend.scoring.operator', $event), ['operator' => 'Court phone A'])
            ->assertSessionHas('venue_scoring.operator', 'Court phone A');

        $this->actingAs($user)
            ->withSession(['venue_scoring.operator' => 'Court phone A'])
            ->post(route('backend.roundrobin.score.store', $fixture), ['sets' => ['6-2', '6-3']])
            ->assertOk();

        $audit = DrawAuditLog::where('draw_id', $draw->id)->where('fixture_id', $fixture->id)->latest()->firstOrFail();
        $this->assertSame('Court phone A', $audit->payload['operator']);
    }

    public function test_event_scoring_ability_is_limited_to_an_assigned_scorer(): void
    {
        [$event] = $this->scheduledFixture('Main Venue');
        $assigned = $this->scorerFor($event);
        $unassigned = User::factory()->create()->assignRole('score-keeper');

        $this->assertTrue(Gate::forUser($assigned)->allows('event.score', $event));
        $this->assertFalse(Gate::forUser($unassigned)->allows('event.score', $event));
        $draw = $event->draws()->firstOrFail();
        $this->assertTrue(Gate::forUser($assigned)->allows('saveScore', $draw));
        $this->assertTrue(Gate::forUser($assigned)->allows('deleteScore', $draw));
        $this->assertFalse(Gate::forUser($assigned)->allows('update', $draw));
        $this->assertFalse(Gate::forUser($assigned)->allows('publish', $draw));
        $this->assertFalse(Gate::forUser($assigned)->allows('lockToggle', $draw));
    }

    public function test_scoped_assignments_authorize_scoring_without_requiring_a_duplicate_global_role(): void
    {
        [$event, $draw] = $this->scheduledFixture('Main Venue');
        $eventAdmin = User::factory()->create();
        DB::table('event_admins')->insert([
            'event_id' => $event->id,
            'user_id' => $eventAdmin->id,
        ]);
        $eventConvenor = User::factory()->create();
        EventConvenor::create([
            'event_id' => $event->id,
            'user_id' => $eventConvenor->id,
        ]);
        $unassignedConvenor = User::factory()->create()->assignRole('convenor');

        foreach ([$eventAdmin, $eventConvenor] as $assigned) {
            $this->assertTrue(Gate::forUser($assigned)->allows('event.score', $event));
            $this->assertTrue(Gate::forUser($assigned)->allows('saveScore', $draw));
            $this->actingAs($assigned)
                ->get(route('frontend.scoring.workspace', ['event' => $event, 'draw' => $draw]))
                ->assertOk();
        }

        $this->assertFalse(Gate::forUser($unassignedConvenor)->allows('event.score', $event));
        $this->assertFalse(Gate::forUser($unassignedConvenor)->allows('saveScore', $draw));
        $this->actingAs($unassignedConvenor)
            ->get(route('frontend.scoring.workspace', $event))
            ->assertForbidden();
    }

    public function test_super_user_can_open_the_scoring_workspace_without_an_event_assignment(): void
    {
        [$event, $draw] = $this->scheduledFixture('Main Venue');
        $superUser = User::factory()->create()->assignRole('super-user');

        $this->assertTrue(Gate::forUser($superUser)->allows('event.score', $event));
        $this->assertTrue(Gate::forUser($superUser)->allows('saveScore', $draw));
        $this->actingAs($superUser)
            ->get(route('frontend.scoring.workspace', ['event' => $event, 'draw' => $draw]))
            ->assertOk();
    }

    public function test_individual_event_score_action_uses_the_scoped_canonical_workspace(): void
    {
        $template = file_get_contents(resource_path('views/frontend/event/eventTypes/individual.blade.php'))
            .file_get_contents(resource_path('views/frontend/event/partials/event-draws.blade.php'));

        $this->assertStringContainsString("can('event.score', \$event)", $template);
        $this->assertStringContainsString("route('frontend.scoring.workspace'", $template);
        $this->assertStringNotContainsString("route('frontend.fixtures.enter-scores'", $template);
    }

    public function test_legacy_individual_score_url_redirects_to_the_canonical_workspace(): void
    {
        [$event, $draw] = $this->scheduledFixture('Main Venue');
        $user = $this->scorerFor($event);

        $this->actingAs($user)
            ->get(route('frontend.fixtures.enter-scores', $draw))
            ->assertRedirect(route('frontend.scoring.workspace', [
                'event' => $event,
                'draw' => $draw,
            ]));
    }

    public function test_published_bracket_match_is_scoreable_in_the_frontend_workspace(): void
    {
        [$event, $draw, , $fixture] = $this->scheduledFixture('Main Venue');
        $draw->update(['published' => true]);
        $fixture->update(['stage' => 'MAIN']);
        $user = $this->scorerFor($event);

        $this->actingAs($user)
            ->get(route('frontend.scoring.workspace', ['event' => $event, 'draw' => $draw]))
            ->assertOk()
            ->assertSee('Enter score')
            ->assertDontSee('Published bracket scores must be managed from the draw workspace.');
    }

    public function test_team_fixture_queue_and_score_writes_use_the_same_workspace_audit(): void
    {
        DB::table('eventtypes')->updateOrInsert(
            ['id' => 990],
            ['name' => 'Venue scoring team event', 'type' => EventType::TEAM]
        );
        $event = Event::factory()->create(['eventType' => 990]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Team Cup', 'published' => false, 'locked' => false]);
        $venue = new Venue();
        $venue->forceFill(['name' => 'Team Venue'])->save();
        $event->venues()->attach($venue->id, ['num_courts' => 2]);
        $fixture = TeamFixture::create([
            'draw_id' => $draw->id,
            'match_nr' => 7,
            'round_nr' => 1,
            'venue_id' => $venue->id,
            'scheduled_at' => '2026-09-10 09:00:00',
            'fixture_type' => 1,
        ]);
        $user = $this->scorerFor($event);

        $this->actingAs($user)->get(route('frontend.scoring.workspace', ['event' => $event, 'venue' => $venue->id]))
            ->assertOk()
            ->assertSee('Team Cup')
            ->assertSee('Match 7');

        $this->actingAs($user)
            ->withSession(['venue_scoring.operator' => 'Team phone'])
            ->postJson(route('frontend.fixtures.score.store', $fixture), [
                'set1_home' => 6,
                'set1_away' => 3,
                'set2_home' => 6,
                'set2_away' => 4,
            ])->assertOk();

        $audit = DrawAuditLog::where('draw_id', $draw->id)->where('fixture_id', $fixture->id)->latest()->firstOrFail();
        $this->assertSame('team', $audit->payload['fixture_type']);
        $this->assertSame('Team phone', $audit->payload['operator']);
        $this->assertSame($venue->id, $audit->payload['venue_id']);
    }

    private function scorerFor(Event $event): User
    {
        $user = User::factory()->create()->assignRole('score-keeper');
        EventConvenor::create(['event_id' => $event->id, 'user_id' => $user->id]);

        return $user;
    }

    private function scheduledFixture(
        string $venueName,
        ?Event $event = null,
        ?Draw $draw = null
    ): array {
        $event ??= Event::factory()->create();
        $draw ??= Draw::factory()->create([
            'event_id' => $event->id,
            'drawName' => 'Boys Under 14',
            'published' => true,
            'locked' => false,
        ]);
        $venue = new Venue();
        $venue->forceFill(['name' => $venueName])->save();
        $event->venues()->attach($venue->id, ['num_courts' => 2]);
        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'RR',
            'round' => 1,
            'match_nr' => $venueName === 'Main Venue' ? 101 : 202,
            'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id,
        ]);
        OrderOfPlay::create([
            'fixture_id' => $fixture->id,
            'draw_id' => $draw->id,
            'venue_id' => $venue->id,
            'court' => '1',
            'time' => '2026-09-10 08:00:00',
        ]);

        return [$event, $draw, $venue, $fixture];
    }
}
