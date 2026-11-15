<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, Fixture, Player, Registration};
use App\Services\Draw\DrawWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrawPhaseOneReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function competition(string $format = 'timed', string $workflow = 'round_robin'): array
    {
        $event = Event::factory()->create(['eventType' => 6]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => false, 'oop_published' => false]);
        $draw->settings()->create(['workflow' => $workflow, 'score_format' => $format, 'num_sets' => 3,
            'match_duration_minutes' => 20, 'changeover_minutes' => 5]);
        $registrations = Registration::factory()->count(2)->create();
        foreach ($registrations as $registration) {
            $registration->players()->attach(Player::factory()->create()->id);
            CategoryEventRegistration::factory()->create(['category_event_id' => $category->id,
                'registration_id' => $registration->id, 'payment_status_id' => 1]);
        }
        $group = $draw->groups()->create(['name' => 'A']);
        $group->registrations()->sync($registrations->modelKeys());
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR',
            'registration1_id' => $registrations[0]->id, 'registration2_id' => $registrations[1]->id, 'winner_registration' => null, 'match_status' => 0]);
        return [$draw, $fixture];
    }

    public function test_timed_round_robin_draw_is_complete_without_a_winner_and_refresh_is_read_only(): void
    {
        [$draw, $fixture] = $this->competition();
        $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 4, 'registration2_score' => 4]);
        $before = $fixture->fresh()->getAttributes();
        $state = app(DrawWorkflow::class)->forDraw($draw->fresh());
        $this->assertTrue($state['resultsComplete']);
        $this->assertFalse($state['steps']['schedule']['complete']);
        $this->assertFalse($state['steps']['review']['complete']);
        $this->assertSame($before, $fixture->fresh()->getAttributes());
        $this->assertDatabaseCount('fixture_results', 1);
    }

    public function test_partial_best_of_three_does_not_complete_even_with_stale_winner_metadata(): void
    {
        [$draw, $fixture] = $this->competition('best_of_3_full');
        $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        $fixture->update(['winner_registration' => $fixture->registration1_id]);
        $this->assertFalse(app(DrawWorkflow::class)->forDraw($draw->fresh())['resultsComplete']);
    }

    public function test_playoffs_complete_only_when_configured_final_is_present_and_finished(): void
    {
        [$draw, $fixture] = $this->competition('one_full_set', 'round_robin_playoffs');
        $draw->settings()->update(['playoff_config' => [['slug' => 'main', 'size' => 2, 'enabled' => true, 'positions' => [1, 2]]]]);
        $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        $fixture->update(['winner_registration' => $fixture->registration1_id]);
        $this->assertFalse(app(DrawWorkflow::class)->forDraw($draw->fresh())['resultsComplete']);
        $final = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 1, 'match_nr' => 2,
            'registration1_id' => $fixture->registration1_id, 'registration2_id' => $fixture->registration2_id, 'winner_registration' => null]);
        $this->assertFalse(app(DrawWorkflow::class)->forDraw($draw->fresh())['resultsComplete']);
        $final->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 3]);
        $final->update(['winner_registration' => $fixture->registration1_id]);
        $this->assertTrue(app(DrawWorkflow::class)->forDraw($draw->fresh())['resultsComplete']);
        $draw->settings()->update(['playoff_config' => [['slug' => 'main', 'size' => 2, 'enabled' => true], ['slug' => 'plate', 'size' => 2, 'enabled' => true]]]);
        $this->assertFalse(app(DrawWorkflow::class)->forDraw($draw->fresh())['resultsComplete']);
    }

    public function test_readiness_distinguishes_draw_visibility_from_saved_programme_and_legacy_scoring(): void
    {
        [$draw] = $this->competition();
        $draw->update(['published' => true]);
        $draw->settings()->update(['score_format' => null]);
        $state = app(DrawWorkflow::class)->forDraw($draw->fresh());
        $this->assertFalse($state['steps']['setup']['complete']);
        $this->assertStringContainsString('legacy', $state['steps']['setup']['detail']);
        $this->assertSame('Draw is visible; match times are private.', $state['steps']['review']['detail']);
        $this->assertFalse($state['steps']['review']['complete']);
        $this->assertTrue(app(\App\Domain\Draws\Services\DrawReadinessService::class)->for($draw->fresh())['ready_to_publish']);
    }

    public function test_two_stage_completion_waits_for_confirmed_qualification_and_second_stage_results(): void
    {
        [$draw, $opening] = $this->competition('one_full_set', 'round_robin_round_robin');
        $group = $draw->groups()->create(['name' => 'B']);
        $players = Registration::factory()->count(2)->create();
        foreach ($players as $registration) {
            $registration->players()->attach(Player::factory()->create()->id);
            CategoryEventRegistration::factory()->create(['category_event_id' => $draw->category_event_id,
                'registration_id' => $registration->id, 'payment_status_id' => 1]);
        }
        $group->registrations()->sync($players->modelKeys());
        Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR',
            'registration1_id' => $players[0]->id, 'registration2_id' => $players[1]->id]);
        foreach ($draw->drawFixtures()->get() as $fixture) {
            $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
            $fixture->update(['winner_registration' => $fixture->registration1_id]);
        }
        $this->assertFalse(app(DrawWorkflow::class)->forDraw($draw->fresh())['resultsComplete']);
        $progression = app(\App\Services\Draw\TwoStageRoundRobinService::class);
        $review = $progression->review($draw->fresh());
        $progression->progress($draw->fresh(), ['confirmed_group_ids' => array_column($review['groups'], 'id'), 'standings_snapshot' => $review['snapshot']]);
        $this->assertFalse(app(DrawWorkflow::class)->forDraw($draw->fresh())['resultsComplete']);
        foreach ($draw->drawFixtures()->whereNotIn('id', [$opening->id])->whereHas('drawGroup', fn ($query) => $query->where('round_robin_phase', 2))->get() as $fixture) {
            $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 3]);
            $fixture->update(['winner_registration' => $fixture->registration1_id]);
        }
        $this->assertTrue(app(DrawWorkflow::class)->forDraw($draw->fresh())['resultsComplete']);
    }
}
