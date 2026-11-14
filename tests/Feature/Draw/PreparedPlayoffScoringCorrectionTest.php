<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, DrawAuditLog, Event, Fixture, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Draw\OneSetRoundRobinCorrectionService;
use App\Services\Scheduling\RoundRobinPlayoffScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Queue};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PreparedPlayoffScoringCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        \App\Models\EventType::forceCreate(['id' => 6, 'name' => 'Fictional individual competition', 'type' => \App\Models\EventType::INDIVIDUAL]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create(['eventType' => 6, 'published' => true]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $this->actingAs($actor);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => false, 'locked' => false]);
        $config = [];
        foreach (['MAIN', 'PLATE', 'CONS', 'BOWL'] as $index => $stage) {
            $config[] = ['name' => 'Fictional positions '.($index * 2 + 1).'–'.($index * 2 + 2), 'slug' => $stage,
                'size' => 2, 'positions' => [$index + 1], 'enabled' => true];
        }
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'boxes' => 2, 'score_format' => null, 'num_sets' => null, 'playoff_config' => $config]);
        $ids = [];
        foreach (['A', 'B'] as $name) {
            $group = $draw->groups()->create(['name' => $name]);
            foreach (range(1, 4) as $seed) {
                $registration = Registration::factory()->create();
                $registration->players()->attach(Player::factory()->create(['name' => $name.$seed, 'surname' => 'Example'])->id);
                CategoryEventRegistration::factory()->create(['category_event_id' => $category->id, 'registration_id' => $registration->id, 'payment_status_id' => 1]);
                $draw->registrations()->attach($registration->id);
                $group->registrations()->attach($registration->id, ['seed' => $seed]);
                $ids[$group->id][$seed] = $registration->id;
            }
        }
        app(RoundRobinGenerationService::class)->generate($draw);
        app(RoundRobinPlayoffScheduleService::class)->prepare($draw->fresh());
        foreach ($draw->drawFixtures()->where('stage', 'RR')->get() as $fixture) {
            $homeWins = (int) $fixture->registration1_id < (int) $fixture->registration2_id;
            $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => $homeWins ? 6 : 1,
                'registration2_score' => $homeWins ? 1 : 6]);
            $fixture->update(['match_status' => 2]);
        }
        $venue = Venue::forceCreate(['name' => 'Fictional placement courts']);
        foreach ($draw->drawFixtures()->where('stage', '!=', 'RR')->get() as $index => $fixture) {
            $fixture->update(['registration1_id' => $ids[$fixture->registration1_source_group_id][$fixture->registration1_source_position],
                'registration2_id' => $ids[$fixture->registration2_source_group_id][$fixture->registration2_source_position]]);
            OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
                'court' => $index + 1, 'time' => '2026-11-14 12:00:00']);
            DB::table('schedules')->insert(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id, 'time' => '2026-11-14 12:00:00']);
            DB::table('published_schedule_assignments')->insert(['event_id' => $event->id, 'draw_id' => $draw->id,
                'fixture_kind' => 'individual', 'fixture_id' => $fixture->id, 'scheduled_at' => '2026-11-14 12:00:00',
                'venue_id' => $venue->id, 'court' => (string) ($index + 1), 'published_at' => now(), 'published_by' => $actor->id]);
        }
        $draw->update(['published' => true, 'oop_published' => true]);
        return [$draw->fresh(), $actor, $ids];
    }

    private function args(Draw $draw, User $actor, array $extra = []): array
    {
        return ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $actor->id, '--preserve-playoffs' => true] + $extra;
    }

    public function test_explicit_preservation_corrects_only_round_robin_outcomes_and_keeps_all_playoff_records_and_bookings(): void
    {
        [$draw, $actor] = $this->scenario();
        Mail::fake(); Queue::fake();
        $beforeFixtures = $draw->drawFixtures()->where('stage', '!=', 'RR')->orderBy('id')->get()->toJson();
        $beforeResults = DB::table('fixture_results')->orderBy('id')->get()->toJson();
        $beforePrivate = OrderOfPlay::where('draw_id', $draw->id)->orderBy('id')->get()->toJson();
        $beforeLegacy = DB::table('schedules')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson();
        $beforePublished = DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson();
        $this->artisan('draw:correct-one-set', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $actor->id])->assertFailed();
        $service = app(OneSetRoundRobinCorrectionService::class);
        $plan = $service->review($draw, $draw->event_id, true);
        $this->assertCount(12, $plan['fixture_updates']);
        $this->assertCount(4, $plan['preserved_playoff_fixture_ids']);
        $this->assertCount(4, $plan['preserved_playoff_bookings']['private']);
        $result = $service->apply($draw, $draw->event_id, $plan['snapshot'], $actor, true);
        $this->assertSame(12, $result['corrected_fixtures']);
        $this->assertSame($beforeFixtures, $draw->drawFixtures()->where('stage', '!=', 'RR')->orderBy('id')->get()->toJson());
        $this->assertSame($beforeResults, DB::table('fixture_results')->orderBy('id')->get()->toJson());
        $this->assertSame($beforePrivate, OrderOfPlay::where('draw_id', $draw->id)->orderBy('id')->get()->toJson());
        $this->assertSame($beforeLegacy, DB::table('schedules')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson());
        $this->assertSame($beforePublished, DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson());
        $this->assertSame(12, $draw->drawFixtures()->where('stage', 'RR')->whereNotNull('winner_registration')->where('match_status', 1)->count());
        $this->assertSame(16, $draw->drawFixtures()->count());
        $this->assertTrue((bool) $draw->fresh()->published);
        $this->assertTrue((bool) $draw->fresh()->oop_published);
        $this->assertTrue($service->apply($draw->fresh(), $draw->event_id, $plan['snapshot'], $actor, true)['already_applied']);
        $audit = DrawAuditLog::where('draw_id', $draw->id)->where('action', OneSetRoundRobinCorrectionService::ACTION)->sole();
        $this->assertCount(4, $audit->payload['plan']['preserved_playoffs']);
        Mail::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_playoff_attribute_or_booking_changes_make_a_preview_stale(): void
    {
        [$draw, $actor] = $this->scenario();
        $service = app(OneSetRoundRobinCorrectionService::class);
        $plan = $service->review($draw, $draw->event_id, true);
        $playoff = $draw->drawFixtures()->where('stage', 'MAIN')->sole();
        $playoff->update(['match_nr' => $playoff->match_nr + 20]);
        $this->artisan('draw:correct-one-set', $this->args($draw, $actor, ['--apply' => true, '--snapshot' => $plan['snapshot']]))->assertFailed();
        $plan = $service->review($draw->fresh(), $draw->event_id, true);
        $playoff->orderOfPlay->update(['time' => '2026-11-14 12:30:00']);
        $this->artisan('draw:correct-one-set', $this->args($draw, $actor, ['--apply' => true, '--snapshot' => $plan['snapshot']]))->assertFailed();
        $this->assertNull($draw->settings->fresh()->score_format);
        $this->assertSame(0, $draw->drawFixtures()->where('stage', 'RR')->whereNotNull('winner_registration')->count());
    }

    public function test_scored_live_or_conflicting_playoff_states_are_not_accepted(): void
    {
        [$draw, $actor, $ids] = $this->scenario();
        $playoff = $draw->drawFixtures()->where('stage', 'MAIN')->sole();
        $playoff->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 0]);
        $this->artisan('draw:correct-one-set', $this->args($draw, $actor))->assertFailed();
        $playoff->fixtureResults()->delete();
        $playoff->update(['match_status' => 2]);
        $this->artisan('draw:correct-one-set', $this->args($draw, $actor))->assertFailed();
        $playoff->update(['match_status' => 0, 'winner_registration' => $playoff->registration1_id]);
        $this->artisan('draw:correct-one-set', $this->args($draw, $actor))->assertFailed();
        $playoff->update(['winner_registration' => null, 'registration1_id' => $ids[$playoff->registration1_source_group_id][2]]);
        $this->artisan('draw:correct-one-set', $this->args($draw, $actor))->assertFailed();
        $playoff->update(['registration1_id' => $ids[$playoff->registration1_source_group_id][1], 'registration1_source_position' => 2]);
        $this->artisan('draw:correct-one-set', $this->args($draw, $actor))->assertFailed();
        $this->assertNull($draw->settings->fresh()->score_format);
        $this->assertSame(12, DB::table('fixture_results')->count());
    }

    public function test_occupied_final_requires_complete_resolved_source_and_cross_draw_sources_remain_forbidden(): void
    {
        [$draw, $actor] = $this->scenario();
        $source = $draw->drawFixtures()->where('stage', 'RR')->first();
        $scores = $source->fixtureResults->first()->getAttributes();
        $source->fixtureResults()->delete();
        $this->artisan('draw:correct-one-set', $this->args($draw, $actor))->assertFailed();
        DB::table('fixture_results')->insert($scores);
        $other = Draw::factory()->create(['event_id' => $draw->event_id]);
        Fixture::create(['draw_id' => $other->id, 'stage' => 'MAIN', 'registration1_source_group_id' => $source->draw_group_id, 'registration1_source_position' => 1]);
        $this->artisan('draw:correct-one-set', $this->args($draw, $actor))->assertFailed();
        $this->assertNull($draw->settings->fresh()->score_format);
        $this->assertSame(16, $draw->drawFixtures()->count());
        $this->assertSame(12, DB::table('fixture_results')->count());
    }
}
