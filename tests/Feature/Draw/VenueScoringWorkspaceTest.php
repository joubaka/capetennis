<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\DrawAuditLog;
use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\Fixture;
use App\Models\OrderOfPlay;
use App\Models\Registration;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VenueScoringWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
