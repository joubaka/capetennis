<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, DrawAuditLog, Event, EventType, Fixture, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Draw\{InterproRoundRobinFormat, PlayoffLuckyLoserService};
use App\Services\Scheduling\{InterproDailyMatchGraph, InterproParticipantGroups};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterproOneOffPlayoffCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function context(bool $booked = false, bool $conflict = false): array
    {
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Fictional Interpro', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['id' => 253, 'eventType' => $type, 'start_date' => '2026-11-28', 'end_date' => '2026-11-29']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['id' => 1654, 'event_id' => $event->id, 'category_event_id' => $category->id, 'locked' => false, 'published' => false, 'oop_published' => false]);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'preset_key' => InterproRoundRobinFormat::PRESET,
            'playoff_config' => InterproRoundRobinFormat::configuration(), 'boxes' => 4, 'num_sets' => 1, 'score_format' => 'one_set_to_6']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $entries = [];
        $matchNumber = 0;
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $group = $draw->groups()->create(['name' => $name]);
            $registrations = [];
            for ($i = 0; $i < 4; $i++) {
                $registration = Registration::factory()->create();
                $registration->players()->attach(Player::factory()->create()->id);
                $entries[$name][$i] = CategoryEventRegistration::factory()->create(['category_event_id' => $category->id,
                    'registration_id' => $registration->id, 'payment_status_id' => 1, 'status' => 'confirmed', 'withdrawn_at' => null]);
                $group->registrations()->attach($registration->id, ['seed' => $i + 1]);
                $draw->registrations()->attach($registration->id);
                $registrations[] = $registration->id;
            }
            for ($one = 0; $one < 4; $one++) for ($two = $one + 1; $two < 4; $two++) {
                $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'match_nr' => ++$matchNumber, 'draw_group_id' => $group->id,
                    'registration1_id' => $registrations[$one], 'registration2_id' => $registrations[$two],
                    'match_status' => 1, 'winner_registration' => $registrations[$one]]);
                $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2,
                    'winner_registration' => $registrations[$one], 'loser_registration' => $registrations[$two]]);
            }
        }
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        $this->postJson(route('backend.draw.progress', $draw), ['confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(),
            'standings_snapshot' => $review['snapshot']])->assertOk();
        $first = $draw->drawFixtures()->where('match_nr', 3002)->firstOrFail();
        $second = $draw->drawFixtures()->where('match_nr', 3004)->firstOrFail();
        if ($booked) {
            $venue = Venue::forceCreate(['name' => 'Fictional booked courts']);
            $draw->event->venues()->attach($venue->id, ['num_courts' => 8]);
            $draw->venues()->attach($venue->id, ['num_courts' => 8]);
            OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $second->id, 'venue_id' => $venue->id,
                'court' => '1', 'time' => '2026-11-28 10:00', 'duration_minutes' => 60, 'gap_minutes' => 5]);
            DB::table('event_venue_schedule_drafts')->insert(['event_id' => $draw->event_id,
                'options' => json_encode(['limit_daily_matches' => true]), 'created_at' => now(), 'updated_at' => now()]);
            if ($conflict) {
                $other = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
                $match = Fixture::factory()->create(['draw_id' => $other->id, 'registration1_id' => $entries['D'][3]->registration_id]);
                OrderOfPlay::create(['draw_id' => $other->id, 'fixture_id' => $match->id, 'venue_id' => $venue->id,
                    'court' => '2', 'time' => '2026-11-28 10:00', 'duration_minutes' => 60, 'gap_minutes' => 5]);
            }
        }
        $original = $second->registration2_id;
        $registrations = Registration::count();
        $pivots = DB::table('draw_registrations')->count();
        $metadata = $draw->fresh()->team_draw_selection;
        $response = $this->postJson(route('draw.playoff-lucky-loser.store', $draw), ['target' => $second->id.':2:'.$second->registration2_id,
            'source_entry_id' => $entries['D'][3]->id, 'confirm_withdrawal' => 1,
            'revision' => app(PlayoffLuckyLoserService::class)->revision($draw->fresh())]);
        if ($conflict) {
            $response->assertConflict();
            $this->assertSame($original, $second->fresh()->registration2_id);
            $this->assertSame($registrations, Registration::count());
            $this->assertSame($pivots, DB::table('draw_registrations')->count());
            $this->assertEquals($metadata, $draw->fresh()->team_draw_selection);
            $this->assertSame(0, DrawAuditLog::where('action', 'playoff_lucky_loser_replaced')->count());
        } else $response->assertOk();
        return [$draw->fresh(), $first->fresh(), $second->fresh(), $response->json('registration_id')];
    }

    public function test_lucky_loser_with_existing_booking_and_daily_cap_persists_source_before_validation(): void
    {
        [$draw, , $second, $alias] = $this->context(true);
        $this->assertSame($alias, $second->registration2_id);
        $this->assertSame($alias, $draw->team_draw_selection['playoff_lucky_losers'][$second->id.':2']['incoming_registration_id']);
        $this->assertSame('2026-11-28 10:00:00', (string) $second->orderOfPlay->time);
        $graph = InterproDailyMatchGraph::build($draw, $draw->drawFixtures()->with('fixtureResults')->get());
        $this->assertSame(['type' => 'player', 'id' => $alias], $graph[3004]['sources'][1]);
    }

    public function test_scheduled_lucky_loser_conflict_rolls_back_early_metadata_alias_and_audit(): void
    {
        $this->context(true, true);
    }

    private function corrected(): array
    {
        [$draw, $first, $second, $alias] = $this->context();
        $history = $draw->team_draw_selection;
        foreach ([[$first, $second], [$second, $first]] as [$target, $peer]) {
            $history['interpro_one_off_1654_places'][$target->id.':2'] = [
                'counterpart' => $peer->id.':2', 'request_key' => str_repeat('a', 64),
                'original_source_registration_id' => \App\Services\Draw\InterproOneOffPlayoffCorrection::rootSource($draw, $target, 2),
                'base_registration_id' => $target->registration2_id, 'incoming_registration_id' => $peer->registration2_id];
        }
        $firstId = $first->registration2_id;
        $first->update(['registration2_id' => $second->registration2_id]);
        $second->update(['registration2_id' => $firstId]);
        $draw->team_draw_selection = $history;
        $draw->save();
        return [$draw->fresh(), $first->fresh(), $second->fresh(), $alias];
    }

    public function test_reviewed_one_off_state_survives_resolution_and_retains_lucky_loser_identity_and_graph_proof(): void
    {
        [$draw, $first, $second, $alias] = $this->corrected();
        $rr = $draw->drawFixtures()->where('stage', 'RR')->with('fixtureResults')->orderBy('id')->get()->toJson();
        $history = $draw->team_draw_selection;
        app(InterproRoundRobinFormat::class)->resolve($draw);
        $this->assertSame($alias, $first->fresh()->registration2_id);
        $this->assertSame($second->registration2_id, $second->fresh()->registration2_id);
        $this->assertStringContainsString('(LL)', $first->fresh()->registration2->display_name);
        $this->assertEquals($history, $draw->fresh()->team_draw_selection);
        $this->assertSame($rr, $draw->drawFixtures()->where('stage', 'RR')->with('fixtureResults')->orderBy('id')->get()->toJson());
        $fixtures = $draw->drawFixtures()->with('fixtureResults')->get();
        $graph = InterproDailyMatchGraph::build($draw->fresh(), $fixtures);
        $this->assertSame(['type' => 'player', 'id' => $alias], $graph[3002]['sources'][1]);
        $this->assertNotNull(InterproParticipantGroups::forDraw($draw->fresh(), $fixtures));
    }

    public function test_copied_override_is_rejected_outside_exact_draw_and_slots(): void
    {
        [$draw, $first] = $this->corrected();
        $other = Draw::factory()->create(['event_id' => $draw->event_id]);
        $other->team_draw_selection = $draw->team_draw_selection;
        $other->save();
        try { \App\Services\Draw\InterproOneOffPlayoffCorrection::sourceOverride($other, $first, 2, 1); $this->fail('One-off state must not apply to other draws.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        $history = $draw->team_draw_selection;
        $history['interpro_one_off_1654_places'][$first->id.':1'] = $history['interpro_one_off_1654_places'][$first->id.':2'];
        $draw->team_draw_selection = $history;
        $draw->save();
        try { \App\Services\Draw\InterproOneOffPlayoffCorrection::sourceOverride($draw->fresh(), $first, 1, 1); $this->fail('One-off state must not apply to other slots.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) { $this->assertSame(409, $exception->getStatusCode()); }
    }

    public function test_missing_reciprocal_or_changed_original_source_requires_review(): void
    {
        [$draw, $first, $second] = $this->corrected();
        $history = $draw->team_draw_selection;
        foreach (['counterpart', 'original'] as $invalid) {
            $changed = $history;
            if ($invalid === 'counterpart') unset($changed['interpro_one_off_1654_places'][$second->id.':2']);
            else $changed['interpro_one_off_1654_places'][$first->id.':2']['original_source_registration_id'] = 999999;
            $draw->team_draw_selection = $changed;
            $draw->save();
            try { app(InterproRoundRobinFormat::class)->resolve($draw->fresh()); $this->fail('Invalid one-off state must fail closed.'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) { $this->assertSame(409, $exception->getStatusCode()); }
            $this->assertEquals($changed, $draw->fresh()->team_draw_selection);
        }
    }
}
