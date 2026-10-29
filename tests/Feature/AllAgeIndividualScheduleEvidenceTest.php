<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, Draw, DrawSetting, Event, Fixture, FixtureResult, OrderOfPlay, Player, Registration, Venue};
use App\Services\Performance\PlayerSharedAbilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AllAgeIndividualScheduleEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $event = Event::factory()->create(['start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'published' => true, 'results_published' => false]);
        $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'U13 Boys A'])->id]);
        $registrations = [];
        foreach ([1, 2] as $side) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create());
            CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
            $registrations[] = $registration;
        }
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'published' => true]);
        DrawSetting::create(['draw_id' => $draw->id, 'workflow' => 'round_robin', 'score_format' => 'one_full_set', 'num_sets' => 1]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registrations[0]->id, 'registration2_id' => $registrations[1]->id,
            'match_status' => 1, 'winner_registration' => $registrations[0]->id]);
        FixtureResult::create(['fixture_id' => $fixture->id, 'set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        $venue = Venue::forceCreate(['name' => 'Synthetic schedule courts']);
        foreach (['1' => 'yellow', '2' => 'green'] as $label => $ball) { DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => $venue->id, 'label' => (string) $label, 'ball_type' => $ball, 'active' => true]); }
        $attributes = ['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-10-01 09:00:00'];
        $schedule = OrderOfPlay::create($attributes);
        return compact('fixture', 'draw', 'schedule', 'attributes');
    }

    public function test_conflicting_court_or_date_schedules_never_establish_shared_standard_evidence(): void
    {
        $data = $this->fixture();
        OrderOfPlay::create(array_replace($data['attributes'], ['court' => '2', 'time' => '2026-10-02 09:00:00']));
        $snapshot = app(PlayerSharedAbilityService::class)->calculateSnapshot(CarbonImmutable::parse('2026-10-03'));
        $this->assertSame([], $snapshot['all_age']['ratings']);
        $this->assertSame(1, $snapshot['all_age']['excluded']['Individual match has conflicting schedule evidence']);
        $this->assertCount(1, $snapshot['cohorts']);
    }

    public function test_foreign_draw_schedule_never_establishes_shared_ball_or_date_evidence(): void
    {
        $data = $this->fixture();
        $foreign = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $data['schedule']->update(['draw_id' => $foreign->id]);
        $snapshot = app(PlayerSharedAbilityService::class)->calculateSnapshot(CarbonImmutable::parse('2026-10-03'));
        $this->assertSame([], $snapshot['all_age']['ratings']);
        $this->assertSame(1, $snapshot['all_age']['excluded']['Individual match schedule belongs to another draw']);
    }

    public function test_identical_duplicate_schedule_context_is_not_double_counted(): void
    {
        $data = $this->fixture();
        OrderOfPlay::create($data['attributes']);
        $snapshot = app(PlayerSharedAbilityService::class)->calculateSnapshot(CarbonImmutable::parse('2026-10-03'));
        $this->assertCount(2, $snapshot['all_age']['ratings']);
        $this->assertSame(1, array_values($snapshot['all_age']['components'])[0]['played']);
    }
}
