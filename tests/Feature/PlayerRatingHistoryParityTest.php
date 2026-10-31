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

    public function test_ongoing_event_matches_enter_at_their_dates_without_future_partial_or_private_results_or_early_finishes(): void
    {
        $event = Event::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-03-02', 'published' => true, 'results_published' => true]);
        $category = Category::factory()->create(['name' => 'U13 Boys']);
        $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $category->id]);
        $registrations = [];
        foreach ([1, 2, 3] as $position) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create());
            CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
            CategoryResult::create(['event_id' => $event->id, 'category_id' => $category->id, 'registration_id' => $registration->id, 'position' => $position]);
            $registrations[] = $registration;
        }
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'published' => true]);
        DrawSetting::create(['draw_id' => $draw->id, 'num_sets' => 3]);
        $private = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'published' => false]);
        DrawSetting::create(['draw_id' => $private->id, 'num_sets' => 3]);
        $venue = DB::table('venues')->insertGetId(['name' => 'Ongoing courts']);
        $fixtures = [];
        foreach ([['2026-01-15', 2, $draw], ['2026-02-15', 2, $draw], ['2026-03-01', 2, $draw], ['2026-01-16', 1, $draw], ['2026-01-17', 2, $private]] as [$date, $sets, $fixtureDraw]) {
            $fixture = Fixture::factory()->create(['draw_id' => $fixtureDraw->id, 'registration1_id' => $registrations[0]->id,
                'registration2_id' => $registrations[1]->id, 'match_status' => 0, 'winner_registration' => $registrations[0]->id]);
            foreach (range(1, $sets) as $set) FixtureResult::create(['fixture_id' => $fixture->id, 'set_nr' => $set, 'registration1_score' => 6, 'registration2_score' => 0]);
            OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $fixtureDraw->id, 'venue_id' => $venue, 'court' => 1, 'time' => $date.' 10:00:00']);
            $fixtures[] = $fixture;
        }
        $ability = app(PlayerSharedAbilityService::class);
        $sources = [];
        $builder = new \App\Services\Performance\PlayerRatingEvidenceBuilder;
        foreach (['2026-01-31' => 1, '2026-02-28' => 2, '2026-03-02' => 3] as $date => $count) {
            $asOf = CarbonImmutable::parse($date, 'Africa/Johannesburg');
            $snapshot = $ability->historySnapshot($asOf, $sources);
            $freshSources = [];
            $fresh = $ability->historySnapshot($asOf, $freshSources);
            $this->assertSame($snapshot['cohorts'], $fresh['cohorts']);
            $this->assertSame($snapshot['all_age'], $fresh['all_age']);
            $this->assertCount($count, $snapshot['cohorts']['u13 boys']['history_evidence']['matches']);
            $this->assertCount($count, $snapshot['all_age']['history_evidence']['matches']);
            $this->assertCount($count === 3 ? 1 : 0, $snapshot['cohorts']['u13 boys']['history_evidence']['fields']);
            $this->assertNull($snapshot['cohorts']['u13 boys']['baseline']['metadata']);
            $this->assertSame(array_map(fn ($fixture) => 'match:'.$fixture->id, array_slice($fixtures, 0, $count)), $snapshot['source_matches']);
            $builder->append($snapshot, $sources, $count - 1);
        }
        $this->assertCount(1, $sources);
        $rows = collect($builder->all())->where('cohort', \App\Services\Performance\AllAgeRatingModel::COHORT)->values();
        $this->assertSame([7, 6, 4], $rows->pluck('months')->all());
        $this->assertSame(['2026-03-02'], $rows->pluck('completed_at')->unique()->values()->all());
        $this->assertSame([], $ability->validationSnapshot(CarbonImmutable::parse('2026-02-01'))['source_matches'], 'Holdout validation must continue excluding overlapping events.');
        $event->update(['published' => false, 'results_published' => false]);
        $privateSources = [];
        $this->assertSame([], $ability->historySnapshot(CarbonImmutable::parse('2026-03-02'), $privateSources)['source_matches']);
    }

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
            $core = $reused['cohorts'];
            foreach ($core as &$data) unset($data['history_evidence']);
            unset($data);
            $this->assertSame($canonical['cohorts'], $core);
            $used = $reused['cohorts']['u10 boys']['history_evidence'];
            $this->assertCount(1, $used['matches']);
            $this->assertSame([], $used['fields'], 'Finishing order covered by played connectivity must not be labelled retained likelihood.');
            $this->assertSame($canonical['source_matches'], $reused['source_matches']);
            $this->assertContains('match:'.$fixture->id, $reused['source_matches']);
            $this->assertSame([$event->id], $reused['source_events']);
            if ($date === '2026-01-31') $this->assertLessThan($canonicalQueries, $reusedQueries);
        }
        $this->assertCount(1, $sources);
    }
}
