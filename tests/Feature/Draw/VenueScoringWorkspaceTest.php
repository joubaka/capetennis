<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Enums\FixtureState;
use App\Models\Draw;
use App\Models\DrawAuditLog;
use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\EventType;
use App\Models\Fixture;
use App\Models\FixtureResult;
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
            ->assertSee('id="venue-filter"', false)
            ->assertSee('id="draw-filter"', false)
            ->assertSee('class="match-card-main"', false)
            ->assertSee('Mark as on court')
            ->assertSee('aria-label="Filter match queue"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('score-filter-empty', false)
            ->assertSee('Match '.$fixture->match_nr)
            ->assertDontSee('Match '.$otherFixture->match_nr);
        $this->assertNotSame($venue->id, $otherVenue->id);
    }

    public function test_venue_limited_score_keeper_cannot_view_or_score_another_venue(): void
    {
        [$event, $draw, $venue, $fixture] = $this->scheduledFixture('Assigned Venue');
        [, , $otherVenue, $otherFixture] = $this->scheduledFixture('Blocked Venue', $event, $draw);
        $user = $this->scorerFor($event);
        EventConvenor::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->update(['venue_id' => $venue->id]);

        $response = $this->actingAs($user)->get(route('frontend.scoring.workspace', [
            'event' => $event,
            'all_venues' => 1,
        ]));

        $response->assertOk()->assertSee('Assigned Venue');
        $this->assertTrue($response->viewData('venueRestricted'));
        $this->assertSame($venue->id, $response->viewData('selectedVenue')->id);
        $this->assertSame([$fixture->id], $response->viewData('matches')->pluck('id')->all());

        $this->actingAs($user)
            ->get(route('frontend.scoring.workspace', ['event' => $event, 'venue' => $otherVenue->id]))
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('backend.roundrobin.score.store', $otherFixture), ['sets' => ['6-2', '6-3']])
            ->assertForbidden();
        $this->actingAs($user)
            ->postJson(route('frontend.scoring.fixtures.playing', [$event, $otherFixture]))
            ->assertForbidden();
        $this->assertDatabaseMissing('fixture_results', ['fixture_id' => $otherFixture->id]);
        $this->assertSame(FixtureState::STATUS_PENDING, (int) $otherFixture->fresh()->match_status);
    }

    public function test_queue_defaults_to_time_then_age_group_then_natural_court_number_and_scores_do_not_change_that_order(): void
    {
        [$event, $under14, , $under14Court2] = $this->scheduledFixture('Under 14 Venue');
        $under14->update(['drawName' => 'Under 14']);
        $under14Court2->orderOfPlay()->update(['court' => '2']);
        $under14Court2->update(['match_nr' => 142]);

        $under12 = Draw::factory()->create([
            'event_id' => $event->id,
            'drawName' => 'Under 12',
            'published' => true,
            'locked' => false,
        ]);
        [, , , $under12Court10] = $this->scheduledFixture('Under 12 Court 10 Venue', $event, $under12);
        $under12Court10->orderOfPlay()->update(['court' => '10']);
        $under12Court10->update(['match_nr' => 1210]);
        [, , , $under12Court1] = $this->scheduledFixture('Under 12 Court 1 Venue', $event, $under12);
        $under12Court1->orderOfPlay()->update(['court' => '1']);
        $under12Court1->update(['match_nr' => 121]);

        $under18 = Draw::factory()->create([
            'event_id' => $event->id,
            'drawName' => 'Under 18',
            'published' => true,
            'locked' => false,
        ]);
        [, , , $earlierUnder18] = $this->scheduledFixture('Earlier Venue', $event, $under18);
        $earlierUnder18->orderOfPlay()->update(['court' => '9', 'time' => '2026-09-10 07:30:00']);
        $earlierUnder18->update(['match_nr' => 189]);

        $user = $this->scorerFor($event);
        $expected = [$earlierUnder18->id, $under12Court1->id, $under12Court10->id, $under14Court2->id];

        $before = $this->actingAs($user)->get(route('frontend.scoring.workspace', ['event' => $event, 'all_venues' => 1]));
        $before->assertOk();
        $this->assertSame($expected, $before->viewData('matches')->pluck('id')->all());

        FixtureResult::create([
            'fixture_id' => $under12Court10->id,
            'registration1_score' => 6,
            'registration2_score' => 3,
            'set_nr' => 1,
        ]);

        $after = $this->actingAs($user)->get(route('frontend.scoring.workspace', ['event' => $event, 'all_venues' => 1]));
        $after->assertOk()->assertSee('data-score-state="completed"', false);
        $this->assertSame($expected, $after->viewData('matches')->pluck('id')->all());
    }

    public function test_workspace_uses_ajax_for_filters_operator_and_score_lifecycle_actions(): void
    {
        $template = file_get_contents(resource_path('views/frontend/scoring/workspace.blade.php'));

        $this->assertStringContainsString('refreshWorkspace(select.value', $template);
        $this->assertStringContainsString('new FormData(operatorForm)', $template);
        $this->assertStringContainsString("request(startButton.dataset.playingUrl, 'POST', {})", $template);
        $this->assertStringContainsString('await refreshWorkspace(window.location.href)', $template);
        $this->assertStringContainsString("history.pushState({}, '', options.historyUrl || url)", $template);
        $this->assertStringContainsString("window.addEventListener('popstate'", $template);
        $this->assertStringNotContainsString('window.location.reload()', $template);
        $this->assertStringNotContainsString('window.location.assign(', $template);
    }

    public function test_venue_scoring_control_offers_assigned_convenor_a_link_for_each_scheduled_venue(): void
    {
        [$event, , $venue] = $this->scheduledFixture('Main Venue');
        $venue->fixture_count = 2;
        $user = $this->scorerFor($event);

        $this->actingAs($user);
        $html = view('frontend.event.partials._venue-scoring', [
            'event' => $event,
            'scoringVenues' => collect([$venue]),
        ])->render();

        $this->assertStringContainsString('Score fixtures by venue', $html);
        $this->assertStringContainsString(
            route('frontend.scoring.workspace', ['event' => $event, 'venue' => $venue->id]),
            $html
        );
        $this->assertStringContainsString('>2</span>', $html);
    }

    public function test_venue_scoring_control_is_hidden_from_an_unassigned_user(): void
    {
        [$event, , $venue] = $this->scheduledFixture('Main Venue');
        $venue->fixture_count = 1;
        $user = User::factory()->create();

        $this->actingAs($user);
        $html = view('frontend.event.partials._venue-scoring', [
            'event' => $event,
            'scoringVenues' => collect([$venue]),
        ])->render();

        $this->assertStringNotContainsString('Score fixtures by venue', $html);
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

    public function test_individual_match_can_be_marked_playing_and_score_completion_turns_it_green(): void
    {
        [$event, $draw, $venue, $fixture] = $this->scheduledFixture('Main Venue');
        $user = $this->scorerFor($event);

        $this->actingAs($user)
            ->withSession(['venue_scoring.operator' => 'Court phone A'])
            ->postJson(route('frontend.scoring.fixtures.playing', [$event, $fixture]))
            ->assertOk()
            ->assertJsonPath('status', 'playing');

        $this->assertSame(FixtureState::STATUS_PARTIAL, (int) $fixture->fresh()->match_status);
        $this->assertDatabaseHas('draw_audit_logs', [
            'draw_id' => $draw->id,
            'fixture_id' => $fixture->id,
            'action' => 'match_started',
        ]);
        $this->actingAs($user)
            ->postJson(route('frontend.scoring.fixtures.playing', [$event, $fixture]))
            ->assertOk();
        $this->assertSame(1, DrawAuditLog::where('draw_id', $draw->id)
            ->where('fixture_id', $fixture->id)
            ->where('action', 'match_started')
            ->count());

        $playing = $this->actingAs($user)->get(route('frontend.scoring.workspace', ['event' => $event, 'venue' => $venue->id]));
        $playing->assertOk()
            ->assertSee('data-score-state="playing"', false)
            ->assertSee('is-playing', false)
            ->assertSee('Playing now');

        $this->actingAs($user)
            ->postJson(route('api.draws.fixtures.score.store', [$draw, $fixture]), ['sets' => ['6-2', '6-3']])
            ->assertOk();

        $this->assertSame(FixtureState::STATUS_COMPLETED, (int) $fixture->fresh()->match_status);
        $completed = $this->actingAs($user)->get(route('frontend.scoring.workspace', ['event' => $event, 'venue' => $venue->id]));
        $completed->assertOk()
            ->assertSee('data-score-state="completed"', false)
            ->assertSee('is-completed', false)
            ->assertSee('Completed');
    }

    public function test_score_keeper_cannot_mark_another_events_match_playing(): void
    {
        [$event] = $this->scheduledFixture('Main Venue');
        [$otherEvent, , , $otherFixture] = $this->scheduledFixture('Other Venue');
        $user = $this->scorerFor($event);

        $this->actingAs($user)
            ->postJson(route('frontend.scoring.fixtures.playing', [$otherEvent, $otherFixture]))
            ->assertForbidden();

        $this->assertSame(FixtureState::STATUS_PENDING, (int) $otherFixture->fresh()->match_status);
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

        $this->actingAs($user)
            ->withSession(['venue_scoring.operator' => 'Team phone'])
            ->postJson(route('frontend.scoring.team-fixtures.playing', [$event, $fixture]))
            ->assertOk()
            ->assertJsonPath('status', 'playing');
        $this->assertSame(FixtureState::STATUS_PARTIAL, (int) $fixture->fresh()->match_status);

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

        $this->assertSame(FixtureState::STATUS_COMPLETED, (int) $fixture->fresh()->match_status);

        $audit = DrawAuditLog::where('draw_id', $draw->id)->where('fixture_id', $fixture->id)->latest()->firstOrFail();
        $this->assertSame('team', $audit->payload['fixture_type']);
        $this->assertSame('Team phone', $audit->payload['operator']);
        $this->assertSame($venue->id, $audit->payload['venue_id']);
    }

    private function scorerFor(Event $event): User
    {
        $user = User::factory()->create()->assignRole('score-keeper');
        EventConvenor::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => 'score-keeper',
        ]);

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
