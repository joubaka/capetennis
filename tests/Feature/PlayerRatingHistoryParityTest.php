<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, CategoryResult, Draw, DrawSetting, Event, Fixture, FixtureResult, OrderOfPlay, Player, Registration};
use App\Services\Performance\PlayerSharedAbilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlayerRatingHistoryParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reused_completed_event_sources_match_canonical_cutoffs_including_invalid_future_schedule(): void
    {
        $event = Event::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-02', 'published' => true, 'results_published' => true]);
        Event::factory()->create(['start_date' => '2026-02-01', 'end_date' => '2026-02-02', 'published' => true, 'results_published' => true]);
        $category = Category::factory()->create(['name' => 'U10 Boys']);
        $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $category->id]);
        $registrations = [];
        foreach ([1, 2] as $position) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create());
            CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
            CategoryResult::create(['event_id' => $event->id, 'category_id' => $category->id, 'registration_id' => $registration->id, 'position' => $position]);
            $registrations[] = $registration;
        }
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'published' => true]);
        DrawSetting::create(['draw_id' => $draw->id, 'num_sets' => 3]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registrations[0]->id,
            'registration2_id' => $registrations[1]->id, 'match_status' => 1, 'winner_registration' => $registrations[0]->id]);
        foreach ([1, 2] as $set) FixtureResult::create(['fixture_id' => $fixture->id, 'set_nr' => $set, 'registration1_score' => 6, 'registration2_score' => 0]);
        OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => DB::table('venues')->insertGetId(['name' => 'Parity courts']), 'court' => 1, 'time' => '2026-01-15 10:00:00']);
        $ability = app(PlayerSharedAbilityService::class);
        $sources = [];
        foreach (['2026-01-03', '2026-01-31'] as $date) {
            $asOf = CarbonImmutable::parse($date, 'Africa/Johannesburg');
            DB::enableQueryLog(); DB::flushQueryLog();
            $canonical = $ability->validationSnapshot($asOf->addDay());
            $canonicalQueries = count(DB::getQueryLog()); DB::flushQueryLog();
            $reused = $ability->historySnapshot($asOf, $sources);
            $reusedQueries = count(DB::getQueryLog()); DB::disableQueryLog();
            $this->assertSame($canonical['cohorts'], $reused['cohorts']);
            $this->assertSame($canonical['source_matches'], $reused['source_matches']);
            $this->assertContains('match:'.$fixture->id, $reused['source_matches']);
            $this->assertSame([$event->id], $reused['source_events']);
            if ($date === '2026-01-31') $this->assertLessThan($canonicalQueries, $reusedQueries);
        }
        $this->assertCount(1, $sources);
    }
}
