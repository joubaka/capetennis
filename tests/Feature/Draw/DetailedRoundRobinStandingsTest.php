<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\{RoundRobinGenerationService, TennisScoreFormat};
use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, DrawAuditLog, Event, Fixture, Player, Registration, User};
use App\Services\Draw\OneSetRoundRobinCorrectionService;
use App\Services\DrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Queue};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DetailedRoundRobinStandingsTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('fixtures', 'loser_registration')) {
            \Illuminate\Support\Facades\Schema::table('fixtures', fn ($table) => $table->unsignedBigInteger('loser_registration')->nullable());
        }
        \App\Models\EventType::forceCreate(['id' => 6, 'name' => 'Fictional individual tournament', 'type' => \App\Models\EventType::INDIVIDUAL]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create(['eventType' => 6, 'published' => true]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $this->actingAs($actor);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => false,
            'oop_published' => false, 'locked' => false, 'engine_mode' => 'legacy']);
        $draw->settings()->create(['workflow' => 'round_robin', 'boxes' => 1, 'score_format' => null, 'num_sets' => null]);
        $group = $draw->groups()->create(['name' => 'Fictional <Group>']);
        $ids = [];
        foreach (['Alpha', 'Bravo', 'Charlie', 'Delta'] as $name) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create(['name' => $name, 'surname' => 'Example'])->id);
            CategoryEventRegistration::factory()->create(['category_event_id' => $category->id,
                'registration_id' => $registration->id, 'payment_status_id' => 1]);
            $draw->registrations()->attach($registration->id);
            $group->registrations()->attach($registration->id);
            $ids[] = $registration->id;
        }
        app(RoundRobinGenerationService::class)->generate($draw);
        $draw->update(['published' => true]);
        $draw->drawFixtures()->update(['match_status' => 2]); // Inherited legacy status, even on unscored fixtures.
        foreach ([[0, 1, 6, 2], [0, 2, 6, 3], [1, 3, 6, 0], [2, 3, 6, 4]] as [$home, $away, $one, $two]) {
            $fixture = $this->pair($draw, $ids[$home], $ids[$away]);
            $scores = (int) $fixture->registration1_id === $ids[$home] ? [$one, $two] : [$two, $one];
            $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => $scores[0], 'registration2_score' => $scores[1]]);
        }
        return [$draw->fresh(), $actor, $ids, $group->id];
    }

    private function pair(Draw $draw, int $one, int $two): Fixture
    {
        return $draw->drawFixtures()->whereIn('registration1_id', [$one, $two])->whereIn('registration2_id', [$one, $two])->sole();
    }

    public function test_explicit_one_set_correction_preserves_records_and_produces_detailed_standings_without_replaying_scores(): void
    {
        [$draw, $actor, $ids, $groupId] = $this->scenario();
        Mail::fake(); Queue::fake();
        $before = DB::table('fixture_results')->orderBy('id')->get()->toJson();
        $fixtureIds = $draw->drawFixtures()->orderBy('id')->pluck('id')->all();
        $beforeRows = collect(app(DrawService::class)->loadRoundRobinHub($draw)['standings'][$groupId])->keyBy('reg_id');
        $this->assertSame(0, $beforeRows[$ids[0]]['played']);
        $this->assertSame(2, $beforeRows[$ids[0]]['incomplete_results']);
        $this->assertSame(3, $beforeRows[$ids[0]]['remaining']);
        $service = app(OneSetRoundRobinCorrectionService::class);
        $plan = $service->review($draw, $draw->event_id);
        $this->assertCount(4, $plan['fixture_updates']);
        $this->assertSame(4, $service->apply($draw, $draw->event_id, $plan['snapshot'], $actor)['corrected_fixtures']);
        $draw = $draw->fresh();
        $rows = collect(app(DrawService::class)->loadRoundRobinHub($draw)['standings'][$groupId])->keyBy('reg_id');
        $this->assertSame(2, $rows[$ids[0]]['wins']);
        $this->assertSame(2, $rows[$ids[0]]['played']);
        $this->assertSame(1, $rows[$ids[0]]['remaining']);
        $this->assertSame(4, $rows[$ids[0]]['points']);
        $metadata = $this->pair($draw, $ids[0], $ids[1])->fresh();
        $this->assertSame($ids[0], (int) $metadata->winner_registration);
        $this->assertSame($ids[1], (int) $metadata->loser_registration);
        $this->assertSame([12, 5], [$rows[$ids[0]]['games_won'], $rows[$ids[0]]['games_lost']]);
        $this->assertSame([0, 2], [$rows[$ids[3]]['wins'], $rows[$ids[3]]['losses']]);
        $this->assertSame(['won', 'won', 'pending'], array_column($rows[$ids[0]]['match_details'], 'status'));
        $this->assertSame('6–2', $rows[$ids[0]]['match_details'][0]['score']);
        $this->assertSame($before, DB::table('fixture_results')->orderBy('id')->get()->toJson());
        $this->assertSame($fixtureIds, $draw->drawFixtures()->orderBy('id')->pluck('id')->all());
        $this->assertTrue((bool) $draw->published);
        $this->assertFalse((bool) $draw->oop_published);
        $this->assertSame(2, $draw->drawFixtures()->whereDoesntHave('fixtureResults')->where('match_status', 2)->count());
        $this->assertTrue($service->apply($draw, $draw->event_id, $plan['snapshot'], $actor)['already_applied']);
        $audit = DrawAuditLog::where('draw_id', $draw->id)->where('action', OneSetRoundRobinCorrectionService::ACTION)->sole();
        $this->assertSame($actor->id, $audit->user_id);
        $this->assertCount(4, $audit->payload['before']['fixtures']);
        $this->get(route('public.roundrobin.show', $draw))->assertOk()->assertSee('roundrobin-standings.js', false);
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertSee('roundrobin-standings.js', false);
        Mail::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_partial_best_of_three_is_not_a_match_win_and_timed_draw_is_a_completed_match(): void
    {
        [$draw, , $ids, $groupId] = $this->scenario();
        $draw->settings->update(['score_format' => TennisScoreFormat::BEST_OF_3_FULL, 'num_sets' => 3]);
        $rows = collect(app(DrawService::class)->loadRoundRobinHub($draw->fresh())['standings'][$groupId])->keyBy('reg_id');
        $this->assertSame(0, $rows[$ids[0]]['wins']);
        $this->assertSame(0, $rows[$ids[0]]['played']);
        $this->assertSame('incomplete', $rows[$ids[0]]['match_details'][0]['status']);
        $draw->settings->update(['score_format' => TennisScoreFormat::TIMED, 'num_sets' => 1]);
        $fixture = $this->pair($draw, $ids[1], $ids[2]);
        $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 11, 'registration2_score' => 11]);
        $rows = collect(app(DrawService::class)->loadRoundRobinHub($draw->fresh())['standings'][$groupId])->keyBy('reg_id');
        $this->assertSame(1, $rows[$ids[1]]['draws']);
        $this->assertSame(3, $rows[$ids[1]]['played']);
        $this->assertSame(0, $rows[$ids[1]]['remaining']);
        $this->assertSame(3, $rows[$ids[1]]['points']);
        $this->assertContains('drawn', array_column($rows[$ids[1]]['match_details'], 'status'));
    }

    public function test_correction_rejects_wrong_event_actor_stale_schedule_and_a_deliberately_chosen_format(): void
    {
        [$draw, $actor] = $this->scenario();
        $service = app(OneSetRoundRobinCorrectionService::class);
        $plan = $service->review($draw, $draw->event_id);
        $stranger = User::factory()->create()->assignRole('admin');
        $this->artisan('draw:correct-one-set', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $stranger->id, '--apply' => true, '--snapshot' => $plan['snapshot']])->assertFailed();
        foreach (['convenor', 'score-keeper'] as $role) {
            $restricted = User::factory()->create();
            DB::table('event_convenors')->insert(['event_id' => $draw->event_id, 'user_id' => $restricted->id, 'role' => $role]);
            $this->artisan('draw:correct-one-set', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $restricted->id])->assertFailed();
        }
        $this->artisan('draw:correct-one-set', ['draw' => $draw->id, '--event' => $draw->event_id + 1, '--actor' => $actor->id])->assertFailed();
        $venue = \App\Models\Venue::forceCreate(['name' => 'Fictional courts']);
        \App\Models\OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $draw->drawFixtures()->first()->id,
            'venue_id' => $venue->id, 'court' => 1, 'time' => '2026-11-14 11:00:00']);
        $this->artisan('draw:correct-one-set', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $actor->id, '--apply' => true, '--snapshot' => $plan['snapshot']])->assertFailed();
        $this->assertNull($draw->settings->fresh()->score_format);
        $draw->settings->update(['score_format' => TennisScoreFormat::BEST_OF_3_FULL, 'num_sets' => 3]);
        $this->artisan('draw:correct-one-set', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $actor->id])->assertFailed();
        $this->assertSame(4, DB::table('fixture_results')->count());
        $this->assertSame(6, $draw->drawFixtures()->count());
    }

    public function test_missing_score_and_frozen_or_downstream_state_are_rejected_without_writes(): void
    {
        [$draw, $actor] = $this->scenario();
        $result = $draw->drawFixtures()->whereHas('fixtureResults')->first()->fixtureResults->first();
        $original = $result->registration2_score;
        $result->update(['registration2_score' => null]);
        $this->artisan('draw:correct-one-set', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $actor->id])->assertFailed();
        $this->assertNull($draw->settings->fresh()->score_format);
        $result->update(['registration2_score' => $original]);
        $draw->settings->update(['workflow' => 'round_robin_round_robin']);
        $this->artisan('draw:correct-one-set', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $actor->id])->assertFailed();
        $draw->settings->update(['workflow' => 'round_robin']);
        $fixture = $draw->drawFixtures()->first();
        $fixture->update(['parent_fixture_id' => $draw->drawFixtures()->orderByDesc('id')->first()->id]);
        $this->artisan('draw:correct-one-set', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $actor->id])->assertFailed();
        $this->assertSame(4, DB::table('fixture_results')->count());
        $this->assertNull($draw->settings->fresh()->score_format);
    }

    public function test_optional_cached_loser_column_absence_is_supported_without_touching_score_rows(): void
    {
        [$draw, $actor] = $this->scenario();
        \Illuminate\Support\Facades\Schema::table('fixtures', fn ($table) => $table->dropColumn('loser_registration'));
        $before = DB::table('fixture_results')->orderBy('id')->get()->toJson();
        $service = app(OneSetRoundRobinCorrectionService::class);
        $plan = $service->review($draw, $draw->event_id);
        $this->assertFalse($plan['persisted_loser_supported']);
        $this->assertArrayNotHasKey('loser_registration', $plan['fixture_updates'][0]);
        $this->assertSame(4, $service->apply($draw, $draw->event_id, $plan['snapshot'], $actor)['corrected_fixtures']);
        $this->assertSame($before, DB::table('fixture_results')->orderBy('id')->get()->toJson());
    }

    public function test_conflicting_immutable_set_outcome_and_qualifier_origin_are_not_silently_overwritten(): void
    {
        [$draw, $actor, $ids] = $this->scenario();
        $fixture = $this->pair($draw, $ids[0], $ids[1]);
        $result = $fixture->fixtureResults->first();
        $result->update(['winner_registration' => $ids[1], 'loser_registration' => $ids[0]]);
        $this->artisan('draw:correct-one-set', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $actor->id])->assertFailed();
        $this->assertNull($draw->settings->fresh()->score_format);
        $this->assertSame($ids[1], (int) $result->fresh()->winner_registration);
        $result->update(['winner_registration' => null, 'loser_registration' => null]);
        $fixture->update(['registration1_source_position' => 1, 'registration1_source_group_id' => $fixture->draw_group_id]);
        $this->artisan('draw:correct-one-set', ['draw' => $draw->id, '--event' => $draw->event_id, '--actor' => $actor->id])->assertFailed();
        $this->assertNull($draw->settings->fresh()->score_format);
        $this->assertSame(4, DB::table('fixture_results')->count());
    }
}
