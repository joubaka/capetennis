<?php

namespace Tests\Feature;

use App\Models\{Draw, Event, Fixture, Registration, TeamFixture, TeamFixtureResult};
use App\Services\EventMatchProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventMatchProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_all_published_draws_without_drafts_foreign_events_byes_or_partial_scores(): void
    {
        $event = Event::factory()->create();
        $first = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $second = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $draft = Draw::factory()->create(['event_id' => $event->id]);
        $foreign = Draw::factory()->create(['event_id' => Event::factory()->create()->id, 'published' => true]);
        $home = Registration::factory()->create();
        $away = Registration::factory()->create();
        $make = fn (Draw $draw, array $extra = []) => Fixture::factory()->create(array_merge([
            'draw_id' => $draw->id, 'registration1_id' => $home->id, 'registration2_id' => $away->id,
        ], $extra));
        $completed = $make($first);
        foreach ([1, 2] as $set) {
            $completed->fixtureResults()->create(['set_nr' => $set, 'registration1_score' => 6, 'registration2_score' => 2]);
        }
        $partial = $make($second);
        $partial->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        $make($draft);
        $make($foreign);
        $make($first, ['match_status' => 3, 'registration2_id' => null]);
        $make($first, ['match_status' => 5, 'registration1_id' => null, 'registration2_id' => null]);
        $make($first, ['registration2_id' => null]);
        $final = Fixture::factory()->create(['draw_id' => $first->id, 'round' => 2]);
        $completed->update(['parent_fixture_id' => $final->id]);
        $walkover = $make($first, ['registration2_id' => null, 'winner_registration' => $home->id]);
        $partial->update(['loser_parent_fixture_id' => $walkover->id]);

        $this->assertSame(['finished' => 1, 'total' => 3], app(EventMatchProgressService::class)->forEvent($event));
        $this->get('/events/'.$event->id)->assertOk()->assertSee('1 / 3')->assertSee('matches finished');
    }

    public function test_team_rubbers_require_a_complete_match_and_pending_rubbers_count_without_a_schedule(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $first = TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 1, 'fixture_type' => 1, 'numSets' => 3]);
        $partial = TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 2, 'fixture_type' => 1, 'numSets' => 3]);
        TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 3, 'fixture_type' => 2, 'numSets' => 1]);
        foreach ([$first, $partial] as $fixture) {
            TeamFixtureResult::create(['team_fixture_id' => $fixture->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
        }
        TeamFixtureResult::create(['team_fixture_id' => $first->id, 'set_nr' => 2, 'team1_score' => 6, 'team2_score' => 3]);

        $this->assertSame(['finished' => 1, 'total' => 3], app(EventMatchProgressService::class)->forEvent($event));
        $draw->update(['published' => false]);
        $this->assertSame(['finished' => 0, 'total' => 0], app(EventMatchProgressService::class)->forEvent($event));
    }
}
