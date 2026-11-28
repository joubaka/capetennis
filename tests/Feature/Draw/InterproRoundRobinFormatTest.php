<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, EventType, Fixture, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Draw\{DrawFinalPlacementService, IndividualScoreRevision, InterproRoundRobinFormat, RoundRobinProgressReadiness};
use App\Services\{DrawService, IndividualMatchOutcomeService};
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InterproRoundRobinFormatTest extends TestCase
{
    use RefreshDatabase;

    private function context(array $sizes = [4, 4, 4, 4]): Draw
    {
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Fictional Interpro Trials', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type, 'start_date' => '2026-11-27', 'end_date' => '2026-11-29']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => false, 'oop_published' => false, 'locked' => false]);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'preset_key' => InterproRoundRobinFormat::PRESET,
            'playoff_config' => InterproRoundRobinFormat::configuration(), 'boxes' => 4, 'num_sets' => 1, 'score_format' => 'one_set_to_6']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $nr = 0;
        foreach ($sizes as $index => $size) {
            $group = $draw->groups()->create(['name' => chr(65 + $index)]);
            $registrations = [];
            for ($i = 0; $i < $size; $i++) {
                $registration = Registration::factory()->create();
                $registration->players()->attach(Player::factory()->create()->id);
                CategoryEventRegistration::factory()->create(['category_event_id' => $category->id, 'registration_id' => $registration->id, 'payment_status_id' => 1]);
                $group->registrations()->attach($registration->id, ['seed' => $i + 1]);
                $registrations[] = $registration;
            }
            for ($one = 0; $one < $size; $one++) for ($two = $one + 1; $two < $size; $two++) {
                $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR', 'match_nr' => ++$nr,
                    'registration1_id' => $registrations[$one]->id, 'registration2_id' => $registrations[$two]->id,
                    'match_status' => 1, 'winner_registration' => $registrations[$one]->id]);
                $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2,
                    'winner_registration' => $registrations[$one]->id, 'loser_registration' => $registrations[$two]->id]);
            }
        }
        return $draw->fresh();
    }

    private function progress(Draw $draw): void
    {
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        if (($review['mode'] ?? null) === 'refresh') {
            $this->postJson(route('backend.draw.progress', $draw), ['action' => 'refresh', 'revision' => $review['revision']])->assertOk();
            return;
        }
        $this->postJson(route('backend.draw.progress', $draw), ['confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(),
            'standings_snapshot' => $review['snapshot']])->assertOk();
    }

    private function fixture(Draw $draw, int $number): Fixture
    {
        return $draw->drawFixtures()->where('match_nr', $number)->with(['draw.settings', 'fixtureResults'])->firstOrFail();
    }

    private function score(Draw $draw, int $number, int $slot = 1): void
    {
        app(DrawService::class)->saveBracketScore($this->fixture($draw, $number), $slot === 1 ? [[6, 2]] : [[2, 6]]);
    }

    private function reachFeedIn(Draw $draw): void
    {
        $this->progress($draw);
        foreach ([2001 => 1, 2002 => 2, 3001 => 1, 3002 => 1, 3003 => 1, 3004 => 1, 3005 => 2, 3006 => 2, 3007 => 1, 3008 => 1] as $nr => $slot) $this->score($draw, $nr, $slot);
    }

    public function test_confirmed_playoff_correction_accepts_zero_scores_through_api_and_preserves_progression(): void
    {
        $draw=$this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->progress($draw);
        $service=app(\App\Services\Draw\InterproScoringCorrectionService::class);
        $review=$service->reviewPlayoffs($draw->fresh());
        $this->postJson(route('draw.interpro-scoring-correction.store',$draw),['scope'=>'playoffs','revision'=>$review['revision']])->assertOk();
        $fixture=$this->fixture($draw->fresh(),2001);
        $this->postJson(route('api.draws.fixtures.score.store',[$draw,$fixture]),[
            'score_revision'=>app(IndividualScoreRevision::class)->forFixture($fixture->fresh()),'sets'=>['6-0','0-6','10-0']])->assertOk();
        $this->assertSame(10,(int)$fixture->fixtureResults()->where('set_nr',3)->value('registration1_score'));
        $this->assertSame(0,(int)$fixture->fixtureResults()->where('set_nr',3)->value('registration2_score'));
        $this->assertSame((int)$fixture->registration1_id,(int)$this->fixture($draw->fresh(),2003)->registration1_id);
        $this->assertSame('one_set_to_6',$draw->settings()->first()->score_format);
    }

    public function test_confirmed_playoffs_refresh_available_names_without_reconfirming_groups_and_preserve_results_and_bookings(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->progress($draw);
        $this->score($draw, 2001);
        $this->score($draw, 2002);
        $final = $this->fixture($draw, 2003);
        $expected = $final->registration2_id;
        $final->update(['registration2_id' => null]);
        $venue = Venue::forceCreate(['name' => 'Fictional refresh courts']);
        OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $final->id, 'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-11-29 08:00']);
        DB::table('published_schedule_assignments')->insert(['event_id' => $draw->event_id, 'draw_id' => $draw->id, 'fixture_kind' => 'individual',
            'fixture_id' => $final->id, 'venue_id' => $venue->id, 'court' => '1', 'scheduled_at' => '2026-11-29 08:00', 'published_at' => now()]);
        $snapshot = fn () => [DB::table('fixture_results')->get()->toJson(), DB::table('order_of_plays')->get()->toJson(),
            DB::table('published_schedule_assignments')->get()->toJson(), $draw->drawFixtures()->where('stage', 'RR')->get()->toJson()];
        $before = $snapshot();
        $count = $draw->drawFixtures()->count();
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->assertJsonPath('review.mode', 'refresh')->json('review');
        $this->assertArrayNotHasKey('groups', $review);
        $this->assertNull($final->fresh()->registration2_id);
        $this->postJson(route('backend.draw.progress', $draw), ['action' => 'refresh', 'revision' => $review['revision']])->assertOk();
        $this->assertSame((int) $expected, (int) $final->fresh()->registration2_id);
        $this->assertSame($before, $snapshot());
        $this->assertSame($count, $draw->drawFixtures()->count());
        $this->assertTrue((bool) $draw->settings()->first()->playoff_config[0]['trials_confirmed']);
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        $this->postJson(route('backend.draw.progress', $draw), ['action' => 'refresh', 'revision' => $review['revision']])->assertOk()->assertJsonPath('progress.resolved', 0);
        $this->assertSame(1, DB::table('draw_audit_logs')->where('action', 'interpro_playoff_names_refreshed')->count());
    }

    public function test_playoff_refresh_never_bypasses_initial_confirmation_or_event_authorization(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $initial = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        $this->assertArrayHasKey('groups', $initial);
        $this->postJson(route('backend.draw.progress', $draw), ['action' => 'refresh', 'revision' => str_repeat('0', 64)])->assertConflict();
        $this->assertEmpty($draw->settings()->first()->playoff_config[0]['trials_confirmed'] ?? null);
        $this->progress($draw);
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        $other = User::factory()->create()->assignRole('admin');
        $this->actingAs($other);
        $this->getJson(route('backend.draw.progress-review', $draw))->assertForbidden();
        $this->postJson(route('backend.draw.progress', $draw), ['action' => 'refresh', 'revision' => $review['revision']])->assertForbidden();
        $this->assertSame(0, DB::table('draw_audit_logs')->where('action', 'interpro_playoff_names_refreshed')->count());
    }

    public function test_playoff_refresh_rejects_stale_results_and_changed_confirmed_slots_without_writes(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->progress($draw);
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        $rr = $draw->drawFixtures()->where('stage', 'RR')->firstOrFail();
        $rr->fixtureResults()->update(['registration2_score' => 3]);
        $before = $draw->drawFixtures()->orderBy('id')->get()->toJson();
        $this->postJson(route('backend.draw.progress', $draw), ['action' => 'refresh', 'revision' => $review['revision']])->assertConflict();
        $this->assertSame($before, $draw->drawFixtures()->orderBy('id')->get()->toJson());
        $opening = $this->fixture($draw, 2001);
        $opening->update(['registration1_id' => $opening->registration2_id]);
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        $before = $draw->drawFixtures()->orderBy('id')->get()->toJson();
        $this->postJson(route('backend.draw.progress', $draw), ['action' => 'refresh', 'revision' => $review['revision']])->assertConflict();
        $this->assertSame($before, $draw->drawFixtures()->orderBy('id')->get()->toJson());
        $this->assertSame(0, DB::table('draw_audit_logs')->where('action', 'interpro_playoff_names_refreshed')->count());
    }

    public function test_refresh_rejects_recorded_walkover_with_a_missing_participant(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->progress($draw);
        $this->score($draw, 2001);
        $this->score($draw, 2002);
        $final = $this->fixture($draw, 2003);
        $final->update(['match_status' => 3, 'winner_registration' => $final->registration1_id, 'registration2_id' => null]);
        $before = $draw->drawFixtures()->orderBy('id')->get()->toJson();
        $this->getJson(route('backend.draw.progress-review', $draw))->assertConflict();
        $this->postJson(route('backend.draw.progress', $draw), ['action' => 'refresh', 'revision' => str_repeat('0', 64)])->assertConflict();
        $this->assertSame($before, $draw->drawFixtures()->orderBy('id')->get()->toJson());
        $this->assertSame(0, DB::table('draw_audit_logs')->where('action', 'interpro_playoff_names_refreshed')->count());
    }

    private function playoffCarry(): array
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->progress($draw);
        $source = $this->fixture($draw, 2001);
        $this->score($draw, 2001);
        $target = $this->fixture($draw, 3007);
        $target->update(['registration1_id' => $source->registration1_id, 'registration2_id' => $source->registration2_id]);
        $config = $draw->fresh()->settings->playoff_config;
        $config[0]['trials_carries'][$target->id] = ['source' => $source->id,
            'fingerprint' => (new \ReflectionMethod(InterproRoundRobinFormat::class, 'fingerprint'))->invoke(null, $source->fresh())];
        $draw->settings()->update(['playoff_config' => $config]);
        return [$draw->fresh(), $target->fresh(), $source->fresh()];
    }

    public function test_stored_round_robin_carry_and_skipped_challenge_fail_closed(): void
    {
        [$draw, $target, $source] = $this->playoffCarry();
        $rr = $draw->drawFixtures()->where('stage', 'RR')->where('registration1_id', $source->registration1_id)->firstOrFail();
        $target->update(['registration1_id' => $rr->registration1_id, 'registration2_id' => $rr->registration2_id]);
        $config = $draw->fresh()->settings->playoff_config;
        $config[0]['trials_carries'][$target->id] = ['source' => $rr->id,
            'fingerprint' => (new \ReflectionMethod(InterproRoundRobinFormat::class, 'fingerprint'))->invoke(null, $rr->fresh())];
        $draw->settings()->update(['playoff_config' => $config]);
        $this->assertNull(InterproRoundRobinFormat::source($target->fresh()));
        $this->assertNull(app(IndividualMatchOutcomeService::class)->winner($target->fresh()));
        try { app(InterproRoundRobinFormat::class)->resolve($draw->fresh()); $this->fail('Stored RR carry must require review.'); }
        catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        unset($config[0]['trials_carries'][$target->id]);
        $config['challenge_playoffs'] = true;
        $config['challenge_pairs'] = [2];
        $challenge = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'CHALLENGE', 'match_nr' => 5002,
            'registration1_id' => $rr->registration1_id, 'registration2_id' => $rr->registration2_id, 'winner_registration' => $rr->registration1_id]);
        $config[0]['trials_skipped_challenges'][$challenge->id] = $rr->registration1_id;
        $config[0]['trials_advances'][$challenge->id] = $rr->registration1_id;
        $draw->settings()->update(['playoff_config' => $config]);
        $this->assertNull(InterproRoundRobinFormat::automaticWinner($challenge->fresh()));
        $this->assertStringContainsString('review required', InterproRoundRobinFormat::score($challenge->fresh()));
        $this->assertTrue(InterproRoundRobinFormat::noMatchRequired($challenge->fresh()));
        try { app(InterproRoundRobinFormat::class)->resolve($draw->fresh()); $this->fail('Stored RR skipped challenge must require review.'); }
        catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        $this->assertEquals($config, $draw->fresh()->settings->playoff_config);
    }

    private function renderedPlayerScores(string $html, int $number): array
    {
        $sets = $this->bracketProjection($html)['matches'][$number]['sets'];
        return $sets ? [implode(' ', array_column($sets, 0)), implode(' ', array_column($sets, 1))] : [];
    }

    public function test_optional_challenges_use_completed_feeders_and_fixed_adjacent_positions(): void
    {
        $draw = $this->context();
        $draw->settings()->update(['playoff_config' => InterproRoundRobinFormat::configuration()
            + ['challenge_playoffs' => true, 'challenge_pairs' => [2, 4, 6, 8]]]);
        $draw = $draw->fresh();
        $service = app(InterproRoundRobinFormat::class);
        $this->assertCount(22, $service->prepare($draw));
        $this->assertCount(0, $service->prepare($draw));
        $planning = $draw->fresh()->load(['drawFixtures.fixtureResults', 'groups', 'settings']);
        $nodes = (new \ReflectionMethod(EventVenueScheduleService::class, 'nodesForDraw'))->invoke(
            app(EventVenueScheduleService::class), $planning, \Carbon\Carbon::parse('2026-11-28 08:00'), 30);
        $challengeId = $this->fixture($draw, 5002)->id;
        $feeders = (new \ReflectionMethod(\App\Services\ScheduleEngine::class, 'feederMap'))->invoke(
            app(\App\Services\ScheduleEngine::class), $planning->drawFixtures->keyBy('id'));
        foreach ($draw->drawFixtures()->whereNotIn('stage', ['RR', 'CHALLENGE'])->pluck('id') as $baseId) {
            $this->assertContains($baseId, $nodes[$challengeId]['dependencies']);
            $this->assertContains($baseId, $feeders[$challengeId]);
        }
        $this->reachFeedIn($draw);
        $this->assertNull($this->fixture($draw, 5002)->registration1_id);
        foreach ([2003, 3010, 4001, 4002, 4003, 4004, 3009] as $nr) $this->score($draw, $nr);
        $this->assertNotNull($this->fixture($draw, 5002)->registration1_id);
        $this->score($draw, 3011);
        $base = [2 => ['L', 2003, 3009], 4 => ['L', 3009, 3011], 6 => ['L', 3011, 3010], 8 => ['L', 3010, 4003]];
        $setsBefore = DB::table('fixture_results')->count();
        $played = 0;
        foreach ($base as $upper => [, $higherFinal, $lowerFinal]) {
            $challenge = $this->fixture($draw, 5000 + $upper);
            $higher = $this->fixture($draw, $higherFinal);
            $expectedHigher = (int) $higher->registration1_id === (int) $higher->winner_registration ? $higher->registration2_id : $higher->registration1_id;
            $this->assertSame((int) $expectedHigher, (int) $challenge->registration1_id);
            $this->assertSame((int) $this->fixture($draw, $lowerFinal)->winner_registration, (int) $challenge->registration2_id);
            if (InterproRoundRobinFormat::noMatchRequired($challenge)) {
                $this->assertSame((int) $challenge->registration1_id, app(IndividualMatchOutcomeService::class)->winner($challenge));
                $this->assertSame(0, $challenge->fixtureResults()->count());
                $this->assertStringContainsString('base positions retained', InterproRoundRobinFormat::score($challenge));
            } else {
                $before = app(DrawFinalPlacementService::class)->forDraw($draw->fresh())->keyBy('position');
                $this->assertSame('awaiting', $before[$upper]['status']);
                $category = CategoryEvent::findOrFail($draw->category_event_id);
                $eligible = $draw->groups()->with('groupRegistrations')->get()->flatMap->groupRegistrations->pluck('registration_id');
                $this->assertEmpty(app(\App\Services\Draw\DrawResultOrderService::class)->forCategory(
                    $category, collect([$draw->fresh()->load(['drawFixtures.fixtureResults', 'groups'])]), $eligible, collect()));
                $this->score($draw, 5000 + $upper, 2);
                $positions = app(DrawFinalPlacementService::class)->forDraw($draw->fresh())->keyBy('position');
                $this->assertSame((int) $challenge->registration2_id, (int) $positions[$upper]['registration_id']);
                $this->assertSame((int) $challenge->registration1_id, (int) $positions[$upper + 1]['registration_id']);
                $played++;
            }
        }
        $this->assertGreaterThan(0, $played);
        $this->assertSame($setsBefore + $played, DB::table('fixture_results')->count());
        $positions = app(DrawFinalPlacementService::class)->forDraw($draw->fresh());
        $this->assertCount(12, $positions->pluck('registration_id')->unique());
        $projection = $this->bracketProjection($this->get(route('public.roundrobin.main-bracket', $draw))->assertOk()->getContent());
        $this->assertCount(22, $projection['matches']);
        $this->assertSame('challenges', $projection['boards'][2]['key']);
        $this->assertSame([5002], $projection['boards'][2]['sections'][0]['matches']);
        $skipped = $this->fixture($draw, 5002);
        $this->assertTrue(InterproRoundRobinFormat::noMatchRequired($skipped));
        $venue = Venue::forceCreate(['name' => 'Fictional challenge courts']);
        $draw->event->venues()->attach($venue->id, ['num_courts' => 4]);
        $draw->venues()->attach($venue->id, ['num_courts' => 4]);
        try {
            app(\App\Services\ScheduleEngine::class)->saveFixture($draw, $skipped->id, '2026-11-28 09:00', $venue->id, '1', 30);
            $this->fail('Skipped challenge must not be booked.');
        } catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        $preview = app(EventVenueScheduleService::class)->preview($draw->event, ['draw_ids' => [$draw->id], 'venue_ids' => [$venue->id],
            'start' => '2026-11-28 08:00', 'end' => '2026-11-28 20:00', 'duration' => 30, 'player_rest' => 15]);
        $this->assertNotContains($skipped->id, collect($preview['matches'])->pluck('fixture_id')->all());
        $before = $skipped->getAttributes();
        $entry = CategoryEventRegistration::where('category_event_id', $draw->category_event_id)->where('registration_id', $skipped->registration1_id)->firstOrFail();
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        app(\App\Services\InterprovincialTrials\TrialWithdrawalProgressionService::class)->resolve($entry);
        $this->assertSame($before, $skipped->fresh()->getAttributes());
        $this->assertSame(0, DB::table('draw_audit_logs')->where('fixture_id', $skipped->id)->where('action', 'trials_withdrawal_loss')->count());
    }

    public function test_challenge_uses_own_feeders_and_read_only_catches_up_old_blank_slots(): void
    {
        $draw = $this->context();
        $draw->settings()->update(['playoff_config' => InterproRoundRobinFormat::configuration()
            + ['challenge_playoffs' => true, 'challenge_pairs' => [8]]]);
        $draw = $draw->fresh();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->reachFeedIn($draw);
        $this->score($draw, 3010);
        $this->score($draw, 4001);
        $this->score($draw, 4002);
        $this->score($draw, 4003);
        $challenge = $this->fixture($draw, 5008);
        $this->assertNotNull($challenge->registration1_id);
        $this->assertNotNull($challenge->registration2_id);
        foreach ([2003, 3009, 3011, 4004] as $number) $this->assertNull(app(IndividualMatchOutcomeService::class)->winner($this->fixture($draw, $number)));
        $expected = [$challenge->registration1_id, $challenge->registration2_id];
        // Even a decisive earlier group meeting must not suppress this playoff challenge.
        $rr = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'match_nr' => 999,
            'registration1_id' => $expected[0], 'registration2_id' => $expected[1],
            'match_status' => 1, 'winner_registration' => $expected[0]]);
        $rr->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2,
            'winner_registration' => $expected[0], 'loser_registration' => $expected[1]]);
        $challenge->update(['registration1_id' => null, 'registration2_id' => null]);
        $before = $challenge->fresh()->getAttributes();
        $setCount = DB::table('fixture_results')->count();
        $config = $draw->fresh()->settings->playoff_config;
        $projection = $this->bracketProjection($this->get(route('backend.draw.main-bracket', $draw))->assertOk()->getContent());
        $this->assertSame(array_map('intval', $expected), array_column($projection['matches'][5008]['players'], 'id'));
        $this->assertSame($before, $challenge->fresh()->getAttributes());
        $this->assertSame($config, $draw->fresh()->settings->playoff_config);
        $this->assertSame($setCount, DB::table('fixture_results')->count());
        $this->assertNull($projection['matches'][5008]['winner']);
        $positions = app(DrawFinalPlacementService::class)->forDraw($draw->fresh())->keyBy('position');
        $this->assertSame('awaiting', $positions[8]['status']);
        $this->assertSame('awaiting', $positions[9]['status']);
        app(InterproRoundRobinFormat::class)->resolve($draw->fresh());
        $this->assertSame(array_map('intval', $expected), [(int) $challenge->fresh()->registration1_id, (int) $challenge->fresh()->registration2_id]);
        $this->assertSame(0, app(InterproRoundRobinFormat::class)->resolve($draw->fresh()));
        $this->assertFalse(InterproRoundRobinFormat::noMatchRequired($challenge->fresh()));
    }

    public function test_early_repeated_playoff_challenge_skips_and_legacy_projection_retains_base_positions_without_writes(): void
    {
        $draw = $this->context();
        $draw->settings()->update(['playoff_config' => InterproRoundRobinFormat::configuration()
            + ['challenge_playoffs' => true, 'challenge_pairs' => [2]]]);
        $draw = $draw->fresh();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->reachFeedIn($draw);
        $this->score($draw, 2003);
        $this->score($draw, 3009);
        $challenge = $this->fixture($draw, 5002);
        $this->assertTrue(InterproRoundRobinFormat::noMatchRequired($challenge));
        $this->assertSame((int) $challenge->registration1_id, app(IndividualMatchOutcomeService::class)->winner($challenge));
        $this->assertSame(0, $challenge->fixtureResults()->count());
        $expected = [$challenge->registration1_id, $challenge->registration2_id];
        $config = $draw->fresh()->settings->playoff_config;
        unset($config[0]['trials_skipped_challenges'][$challenge->id], $config[0]['trials_advances'][$challenge->id]);
        $draw->settings()->update(['playoff_config' => $config]);
        $challenge->update(['registration1_id' => null, 'registration2_id' => null, 'winner_registration' => null]);
        $before = $challenge->fresh()->getAttributes();
        $setCount = DB::table('fixture_results')->count();
        $page = $this->get(route('backend.draw.main-bracket', $draw))->assertOk()->assertSee('Challenge skipped');
        $projection = $this->bracketProjection($page->getContent());
        $this->assertSame(array_map('intval', $expected), array_column($projection['matches'][5002]['players'], 'id'));
        $this->assertSame((int) $expected[0], $projection['matches'][5002]['winner']);
        $this->assertSame([], $projection['matches'][5002]['sets']);
        $this->assertNull($projection['matches'][5002]['schedule']);
        $positions = app(DrawFinalPlacementService::class)->forDraw($draw->fresh())->keyBy('position');
        $this->assertSame((int) $expected[0], (int) $positions[2]['registration_id']);
        $this->assertSame((int) $expected[1], (int) $positions[3]['registration_id']);
        $this->assertSame($before, $challenge->fresh()->getAttributes());
        $this->assertSame($config, $draw->fresh()->settings->playoff_config);
        $this->assertSame($setCount, DB::table('fixture_results')->count());
    }

    public function test_interpro_challenge_choices_save_at_setup_and_preserve_result_freeze(): void
    {
        $source = $this->context();
        $draw = Draw::factory()->create(['event_id' => $source->event_id, 'category_event_id' => $source->category_event_id,
            'published' => false, 'oop_published' => false, 'locked' => false]);
        // Setup is permitted only before any tournament result, exactly as other format options.
        DB::table('fixture_results')->delete();
        $source->drawFixtures()->delete();
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => InterproRoundRobinFormat::PRESET,
            'challenge_playoffs' => true])->assertUnprocessable();
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => InterproRoundRobinFormat::PRESET,
            'challenge_playoffs' => true, 'challenge_pairs' => [12]])->assertUnprocessable();
        $this->post(route('draw.setup.store', $draw), ['workflow' => InterproRoundRobinFormat::PRESET,
            'challenge_playoffs' => true, 'challenge_pairs' => [8, 2, 6, 4]])->assertRedirect();
        $this->assertSame([2, 4, 6, 8], InterproRoundRobinFormat::challengePairs($draw->fresh()));
        $this->assertSame(4, $draw->groups()->count());
        $settings = $draw->fresh()->settings->getAttributes();
        $this->post(route('draw.setup.store', $draw), ['workflow' => InterproRoundRobinFormat::PRESET,
            'challenge_playoffs' => true, 'challenge_pairs' => [2, 4, 6, 8]])->assertRedirect();
        $this->assertSame($settings, $draw->fresh()->settings->getAttributes());
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR']);
        $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => InterproRoundRobinFormat::PRESET,
            'challenge_playoffs' => true, 'challenge_pairs' => [2]])->assertConflict();
        $this->assertSame($settings, $draw->fresh()->settings->getAttributes());
    }

    public function test_trial_rankings_wait_for_selected_challenges_and_accept_skipped_pairings(): void
    {
        $draw = $this->context([3, 3, 3, 3]);
        $draw->settings()->update(['playoff_config' => InterproRoundRobinFormat::configuration()
            + ['challenge_playoffs' => true, 'challenge_pairs' => [2, 4, 6, 8]]]);
        $draw = $draw->fresh();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->reachFeedIn($draw);
        foreach ([2003, 3010, 4001, 4002, 4003, 4004, 3009, 3011] as $nr) $this->score($draw, $nr);
        $programme = app(\App\Services\InterprovincialTrials\TrialProgrammeService::class);
        $this->assertNull($programme->refresh($draw->event));
        foreach ([2, 4, 6, 8] as $upper) {
            if (!InterproRoundRobinFormat::noMatchRequired($this->fixture($draw, 5000 + $upper))) $this->score($draw, 5000 + $upper, 2);
        }
        $run = $programme->refresh($draw->event);
        $this->assertNotNull($run);
        $this->assertCount(12, $run->positions);
        $this->assertSame(app(DrawFinalPlacementService::class)->forDraw($draw->fresh())->pluck('registration_id')->all(),
            array_column($run->positions, 'registration_id'));
        $source = $this->fixture($draw, 3007);
        $source->fixtureResults()->delete();
        $source->update(['match_status' => 0, 'winner_registration' => null]);
        $config = $draw->fresh()->settings->playoff_config;
        $config[0]['trials_carries'][$source->id] = ['source' => 0, 'fingerprint' => 'unavailable'];
        $draw->settings()->update(['playoff_config' => $config]);
        $this->assertNull($programme->refresh($draw->event));
    }

    private function bracketProjection(string $html): array
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        return json_decode($xpath->query('//*[@data-interpro-payload]')->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_fixed_topology_prepares_stable_sources_and_accepts_last_years_smaller_groups(): void
    {
        $draw = $this->context([4, 4, 3, 3]);
        $service = app(InterproRoundRobinFormat::class);
        $this->assertCount(18, $service->prepare($draw));
        $ids = $draw->drawFixtures()->pluck('id')->all();
        $this->assertCount(0, $service->prepare($draw));
        $this->assertSame($ids, $draw->drawFixtures()->pluck('id')->all());
        $this->assertSame($this->fixture($draw, 3011)->id, $this->fixture($draw, 3007)->loser_parent_fixture_id);
        $this->assertSame($this->fixture($draw, 3010)->id, $this->fixture($draw, 3005)->loser_parent_fixture_id);
        $this->assertSame(5, $this->fixture($draw, 3011)->position);
        $this->assertNull($this->fixture($draw, 2001)->registration1_id);
        $rr = $draw->drawFixtures()->where('stage', 'RR')->firstOrFail();
        app(\App\Services\Draw\DrawRecoveryImpactService::class)->assertSafeOrdinaryCorrection($rr);
        $draw->settings()->update(['score_format' => 'timed', 'match_duration_minutes' => 20]);
        $rr->fixtureResults()->first()->update(['registration1_score' => 4, 'registration2_score' => 4]);
        $rr->update(['winner_registration' => null]);
        $this->assertFalse(app(RoundRobinProgressReadiness::class)->forDraw($draw->fresh())['ready']);
        $rr->fixtureResults()->first()->update(['registration1_score' => 6, 'registration2_score' => 2]);
        $rr->update(['winner_registration' => $rr->registration1_id]);
        $draw->settings()->update(['score_format' => 'one_set_to_6']);
        $this->progress($draw);
        $this->assertNotNull($this->fixture($draw, 2001)->registration1_id);
        $this->assertSame(36, $draw->drawFixtures()->count());
    }

    public function test_fixture_list_links_to_the_exact_trial_playoff_without_bypassing_publication(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $before = $draw->drawFixtures()->orderBy('id')->get()->toJson();
        $link = route('public.roundrobin.show', $draw).'#playoffs';

        // Event administrators can navigate the same draft preview through either URL.
        $this->get(route('frontend.fixtures.index', $draw))->assertOk()
            ->assertSee('id="main-bracket-tab"', false)->assertSee('Playoff bracket')->assertSee('Round-robin standings')
            ->assertDontSee('data-live-results-reload', false);
        $this->get(route('public.roundrobin.show', $draw))->assertOk()->assertSee('Draft preview');

        $foreignAdmin = User::factory()->create()->assignRole('admin');
        $this->actingAs($foreignAdmin)->get(route('frontend.fixtures.index', $draw))->assertForbidden();
        $this->get(route('public.roundrobin.show', $draw))->assertForbidden();
        auth()->logout();
        $this->get(route('frontend.fixtures.index', $draw))->assertForbidden()->assertDontSee('View playoff bracket');
        $this->get(route('public.roundrobin.show', $draw))->assertForbidden();

        $draw->event->update(['published' => true]);
        $draw->update(['published' => true]);
        $this->get(route('frontend.fixtures.index', $draw))->assertOk()
            ->assertSee('id="main-bracket-tab"', false)->assertSee('Playoff bracket')->assertSee('Round-robin standings')
            ->assertSee('data-live-results-reload', false)->assertSee('data-draw-revision', false);
        $this->get(route('public.roundrobin.show', $draw))->assertOk()->assertSee('id="main-bracket-tab"', false);
        $bracket = $this->get(route('public.roundrobin.main-bracket', $draw))->assertOk();
        $this->assertCount(18, $this->bracketProjection($bracket->getContent())['matches']);
        $this->assertSame($before, $draw->drawFixtures()->orderBy('id')->get()->toJson());

        $draw->settings()->update(['preset_key' => null]);
        $this->get(route('frontend.fixtures.index', $draw))->assertOk()->assertDontSee('data-playoff-bracket-link', false);
    }

    public function test_empty_trial_fixture_list_still_links_to_its_planning_bracket(): void
    {
        $source = $this->context();
        $draw = Draw::factory()->create(['event_id' => $source->event_id, 'category_event_id' => $source->category_event_id,
            'published' => false, 'oop_published' => false]);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'preset_key' => InterproRoundRobinFormat::PRESET,
            'playoff_config' => InterproRoundRobinFormat::configuration()]);
        $this->get(route('frontend.fixtures.index', $draw))->assertOk()->assertSee('id="main-bracket-tab"', false)->assertSee('Fixtures')
            ->assertSee('window.RR_OOP       = []', false);
        $planning = $this->get(route('public.roundrobin.main-bracket', $draw))->assertOk()->assertSee('Group A position 1');
        $this->assertSame(12, substr_count($planning->getContent(), 'class="ct-final-position is-awaiting"'));
        $this->assertSame(0, $draw->drawFixtures()->count());
    }

    public function test_unified_bracket_bounds_and_escapes_long_names_and_preserves_carried_outcomes(): void
    {
        [$draw, $fixture] = $this->playoffCarry();
        $fixture->registration1->players->first()->update(['name' => 'Fictional <script>alert(1)</script>',
            'surname' => 'Exceptionally Long Participant Surname']);
        $fullName = $fixture->registration1->players->first()->fresh()->full_name;
        $before = $draw->drawFixtures()->orderBy('id')->get()->toJson();
        $page = $this->get(route('backend.draw.main-bracket', $draw))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('First result stands');
        $projection = $this->bracketProjection($page->getContent());
        $this->assertSame($fullName, $projection['matches'][3007]['players'][0]['name']);
        $this->assertStringContainsString('\\u003Cscript\\u003E', $page->getContent());
        $this->assertSame($projection['matches'][3007]['players'][0]['id'], $projection['matches'][3007]['winner']);
        $this->assertSame($before, $draw->drawFixtures()->orderBy('id')->get()->toJson());
        $this->assertSame(0, $fixture->fixtureResults()->count());
    }

    public function test_bracket_schedule_metadata_uses_only_its_visible_published_snapshot(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->reachFeedIn($draw);
        $draw->event->update(['published' => true]);
        $draw->update(['published' => true]);
        $venue = Venue::forceCreate(['name' => 'Published bracket venue']);
        $fixture = $this->fixture($draw, 2003);
        $slot = OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
            'time' => '2026-11-27 09:00', 'court' => 'C1']);
        auth()->logout();
        $this->get(route('public.roundrobin.main-bracket', $draw))->assertOk()
            ->assertDontSee('class="interpro-match-schedule"', false)->assertDontSee('Published bracket venue');
        app(\App\Services\Scheduling\SchedulePublicationService::class)->publish($draw->event, ['draw_id' => $draw->id]);
        $slot->update(['time' => '2026-11-28 15:00', 'court' => 'PRIVATE WORKING COURT']);
        $foreign = Fixture::factory()->create();
        DB::table('published_schedule_assignments')->insert(['event_id' => $draw->event_id, 'draw_id' => $draw->id,
            'fixture_kind' => 'individual', 'fixture_id' => $foreign->id, 'scheduled_at' => '2026-11-27 10:00',
            'venue_id' => $venue->id, 'court' => 'FOREIGN COURT', 'duration' => 30, 'published_at' => now()]);
        $published = $this->get(route('public.roundrobin.main-bracket', $draw))->assertOk()
            ->assertSee('Published bracket venue')->assertDontSee('PRIVATE WORKING COURT')->assertDontSee('FOREIGN COURT');
        $this->assertSame(['time' => '2026-11-27 09:00:00', 'venue' => 'Published bracket venue', 'court' => 'C1'],
            $this->bracketProjection($published->getContent())['matches'][2003]['schedule']);
        $this->get(route('public.roundrobin.main-bracket', [$draw, 'empty' => 1]))->assertOk()
            ->assertDontSee('class="interpro-match-schedule"', false)->assertDontSee('Published bracket venue');
        $draw->update(['oop_published' => false]);
        $this->get(route('public.roundrobin.main-bracket', $draw))->assertOk()
            ->assertDontSee('class="interpro-match-schedule"', false)->assertDontSee('Published bracket venue');
    }

    public function test_carried_walkover_still_displays_its_result_type_without_invented_set_scores(): void
    {
        [$draw, $target, $source] = $this->playoffCarry();
        $source->fixtureResults()->delete();
        $source->update(['match_status' => 3]);
        $config = $draw->fresh()->settings->playoff_config;
        $config[0]['trials_carries'][$target->id]['fingerprint'] = (new \ReflectionMethod(InterproRoundRobinFormat::class, 'fingerprint'))->invoke(null, $source->fresh());
        $draw->settings()->update(['playoff_config' => $config]);
        $page = $this->get(route('backend.draw.main-bracket', $draw))->assertOk()->assertSee('W/O')->assertSee('First result stands');
        $this->assertSame([], $this->renderedPlayerScores($page->getContent(), 3007));
        $this->assertSame(0, $target->fixtureResults()->count());
    }

    public function test_first_results_cascade_without_duplicate_evidence_and_determine_all_twelve_positions(): void
    {
        $draw = $this->context();
        $preview = getenv('INTERPRO_PREVIEW_EXPORT') === '1' ? new \Tests\Support\InterproPreviewExporter() : null;
        if ($preview) {
            $draw->update(['drawName' => 'Fictional U13 Interpro trials']);
            $draw->event->update(['name' => 'Fictional Interpro format review']);
            foreach ($draw->groups()->with('groupRegistrations.registration.players')->orderBy('name')->get() as $group) {
                foreach ($group->groupRegistrations->sortBy('seed')->values() as $index => $entry) {
                    $entry->registration->players->first()->update(['name' => 'Group '.$group->name, 'surname' => 'Player '.($index + 1)]);
                }
            }
            $setupEvent = Event::factory()->create(['eventType' => $draw->event->eventType, 'name' => 'Fictional format setup']);
            $setupCategory = CategoryEvent::factory()->create(['event_id' => $setupEvent->id]);
            $setupDraw = Draw::factory()->create(['event_id' => $setupEvent->id, 'category_event_id' => $setupCategory->id,
                'drawName' => 'Fictional U13 Interpro trials', 'published' => false, 'oop_published' => false, 'locked' => false]);
            $setupDraw->settings()->create(['workflow' => 'round_robin_playoffs', 'preset_key' => InterproRoundRobinFormat::PRESET,
                'playoff_config' => InterproRoundRobinFormat::configuration(), 'boxes' => 4, 'num_sets' => 1, 'score_format' => 'one_set_to_6']);
            foreach (['A', 'B', 'C', 'D'] as $name) $setupDraw->groups()->create(['name' => $name]);
            DB::table('event_admins')->insert(['event_id' => $setupEvent->id, 'user_id' => auth()->id()]);
            $preview->setup($this->get(route('draw.setup.show', $setupDraw))->assertOk()->getContent());
        }
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $preview?->roundRobin($draw->fresh());
        $this->reachFeedIn($draw);
        if ($preview) $preview->stage('carry.html', '3 · Feed-in with first results carried',
            $this->get(route('backend.draw.main-bracket', $draw))->assertOk()->getContent());
        foreach ([3007, 3008] as $nr) {
            $fixture = $this->fixture($draw, $nr);
            $source = InterproRoundRobinFormat::source($fixture);
            $this->assertNull($source);
            $this->assertSame(1, $fixture->match_status);
            $this->assertSame(1, $fixture->fixtureResults()->count());
            $this->assertFalse(InterproRoundRobinFormat::noMatchRequired($fixture));
        }
        foreach ([2003, 3010, 4001, 4002, 4003, 4004, 3009, 3011] as $nr) $this->score($draw, $nr);
        $positions = app(DrawFinalPlacementService::class)->forDraw($draw->fresh());
        $this->assertCount(12, $positions);
        $this->assertCount(12, $positions->pluck('registration_id')->unique());
        $this->assertSame(42, DB::table('fixture_results')->count());
        $before = $draw->drawFixtures()->get()->toJson();
        $this->progress($draw);
        $this->assertSame($before, $draw->drawFixtures()->get()->toJson());
        $this->assertSame(42, $draw->drawFixtures()->count());
        $finalPage = $this->get(route('backend.draw.main-bracket', $draw))->assertOk()->assertSee('First result stands')->assertSee('11/12')->assertSee('Final positions');
        $projection = $this->bracketProjection($finalPage->getContent());
        $this->assertCount(18, $projection['matches']);
        $this->assertCount(2, $projection['boards']);
        $dependencies = 0;
        foreach (InterproRoundRobinFormat::template() as $number => $definition) {
            foreach ($definition[2] as $slot => $source) {
                if (preg_match('/^([WL])(\d+)$/', $source, $parts)) {
                    $dependencies++;
                    $this->assertSame($parts[1] === 'W' ? 'winner' : 'loser', $projection['matches'][$number]['sources'][$slot]['type']);
                    $this->assertSame($parts[2], $projection['matches'][$number]['sources'][$slot]['match']);
                }
            }
        }
        $this->assertSame(24, $dependencies);
        foreach ([2003 => 1, 3009 => 3, 3011 => 5, 3010 => 7, 4003 => 9, 4004 => 11] as $number => $position) {
            $this->assertSame($position, $projection['matches'][$number]['position']);
        }
        $this->assertSame([2001, 2002, 2003], $projection['boards'][0]['sections'][0]['matches']);
        $this->assertSame(2003, $projection['boards'][0]['sections'][0]['champion']);
        $this->assertSame([3001, 3002, 3003, 3004, 3005, 3006, 3007, 3008, 3009], $projection['boards'][0]['sections'][1]['matches']);
        $this->assertSame('2002', $projection['matches'][3007]['sources'][0]['match']);
        $this->assertSame('2001', $projection['matches'][3008]['sources'][0]['match']);
        $this->assertSame('Round 3 · crossed feed-in', $projection['boards'][0]['sections'][1]['roundLabels'][2]);
        $blankPage = $this->get(route('backend.draw.main-bracket', [$draw, 'empty' => 1]))->assertOk()->assertSee('Loser match 2002')->assertDontSee('Final positions');
        $this->assertStringNotContainsString('6-2', strip_tags($blankPage->getContent()));
        if ($preview) {
            $preview->stage('final.html', '4 · Completed trial draw and final positions', $finalPage->getContent());
            $preview->stage('blank.html', '6 · Blank planning draw', $blankPage->getContent());
            $preview->printPage($finalPage->getContent(), $draw->fresh());
            $preview->finish($draw->fresh());
        }
        auth()->logout();
        $draw->event->update(['published' => true]);
        $this->get(route('public.roundrobin.main-bracket', $draw))->assertForbidden();
        $draw->update(['published' => true]);
        $this->get(route('public.roundrobin.main-bracket', $draw))->assertOk()->assertSee('First result stands');
        $publicBlank = $this->get(route('public.roundrobin.main-bracket', [$draw, 'empty' => 1]))->assertOk();
        $this->assertStringNotContainsString('6-2', strip_tags($publicBlank->getContent()));
    }

    public function test_playoff_carry_rejects_booking_and_schedule_preview_excludes_it(): void
    {
        [$draw, $target] = $this->playoffCarry();
        $venue = Venue::forceCreate(['name' => 'Fictional trial courts']);
        $draw->event->venues()->attach($venue->id, ['num_courts' => 4]);
        $draw->venues()->attach($venue->id, ['num_courts' => 4]);
        try { app(\App\Services\ScheduleEngine::class)->saveFixture($draw, $target->id, '2026-11-28 09:00', $venue->id, '1', 30); $this->fail('Carried match must not be booked.'); }
        catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        $preview = app(EventVenueScheduleService::class)->preview($draw->event, ['draw_ids' => [$draw->id], 'venue_ids' => [$venue->id],
            'start' => '2026-11-28 08:00', 'end' => '2026-11-28 20:00', 'duration' => 30, 'player_rest' => 15]);
        $this->assertNotContains($target->id, collect($preview['matches'])->pluck('fixture_id')->all());
        $draw->update(['published' => true, 'oop_published' => true]);
        $draw->event->update(['published' => true]);
        DB::table('published_schedule_assignments')->insert(['event_id' => $draw->event_id, 'draw_id' => $draw->id, 'fixture_kind' => 'individual',
            'fixture_id' => $target->id, 'scheduled_at' => '2026-11-28 09:00', 'venue_id' => $venue->id, 'court' => '1', 'duration' => 30, 'published_at' => now()]);
        $rows = app(\App\Services\Scheduling\SchedulePublicationService::class)->publishedRows($draw->event);
        $this->assertNotContains($target->id, $rows->pluck('fixture_id')->all());
        $this->assertDatabaseHas('published_schedule_assignments', ['fixture_id' => $target->id]);
    }

    public function test_carried_target_and_original_results_cannot_be_overwritten_or_deleted(): void
    {
        [$draw, $target] = $this->playoffCarry();
        $source = InterproRoundRobinFormat::source($target);
        $count = DB::table('fixture_results')->count();
        foreach ([$target, $source] as $fixture) {
            try { app(DrawService::class)->saveAnyScore($fixture, [[2, 6]]); $this->fail('Expected carried result protection.'); }
            catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        }
        $this->assertSame($count, DB::table('fixture_results')->count());
        $this->assertNotNull(InterproRoundRobinFormat::source($target->fresh()));
        $revision = app(IndividualScoreRevision::class)->forFixture($target);
        $this->deleteJson(route('backend.roundrobin.score.delete', $target), ['score_revision' => $revision])->assertConflict();
        $this->postJson(route('backend.roundrobin.score.store', $target), ['score_revision' => $revision, 'sets' => [[6, 2]]])->assertConflict();
        foreach (['saveWalkover', 'saveRetirement'] as $method) {
            try {
                if ($method === 'saveWalkover') app(DrawService::class)->saveWalkover($target, (int) $target->registration1_id);
                else app(DrawService::class)->saveRetirement($target, [[2, 1]], (int) $target->registration2_id);
                $this->fail('Carried matches cannot be scored as walkovers or retirements.');
            } catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        }
        try { app(\App\Services\Draw\DrawRecoveryService::class)->preview($draw, $source); $this->fail('Expected original result recovery protection.'); }
        catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        $this->assertSame($count, DB::table('fixture_results')->count());
    }

    public function test_setup_selection_is_event_scoped_and_preserves_existing_draws(): void
    {
        $draw = $this->context();
        $draw->drawFixtures()->delete();
        $draw->groups()->delete();
        $draw->settings()->delete();
        $this->post(route('draw.setup.store', $draw), ['workflow' => InterproRoundRobinFormat::PRESET])->assertRedirect();
        $this->assertTrue(InterproRoundRobinFormat::selected($draw->fresh()));
        $this->assertSame(4, $draw->groups()->count());
        $this->get(route('draw.setup.show', $draw))->assertOk()->assertSee('Interpro trials — round robin');
        $other = Draw::factory()->create();
        $this->postJson(route('draw.setup.store', $other), ['workflow' => InterproRoundRobinFormat::PRESET])->assertForbidden();
        $draw->event->update(['eventType' => 6]);
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => InterproRoundRobinFormat::PRESET])->assertUnprocessable();
    }

    public function test_withdrawal_preserves_a_played_playoff_result_and_resolves_future_unplayed_matches(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->reachFeedIn($draw);
        $carry = $this->fixture($draw, 3007);
        $entry = CategoryEventRegistration::where('category_event_id', $draw->category_event_id)->where('registration_id', $carry->winner_registration)->firstOrFail();
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        app(\App\Services\InterprovincialTrials\TrialWithdrawalProgressionService::class)->resolve($entry);
        $this->assertSame(1, $carry->fresh()->match_status);
        $this->assertNull(InterproRoundRobinFormat::source($carry->fresh()));
        $future = $this->fixture($draw, 3009);
        $this->assertSame(3, $future->match_status);
        $this->assertNotSame((int) $entry->registration_id, (int) $future->winner_registration);
    }

    public function test_two_withdrawn_players_leave_no_blocking_phantom_opponent(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->progress($draw);
        $semi = $this->fixture($draw, 2001);
        $entries = CategoryEventRegistration::where('category_event_id', $draw->category_event_id)
            ->whereIn('registration_id', [$semi->registration1_id, $semi->registration2_id])->get();
        foreach ($entries as $entry) $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        app(\App\Services\InterprovincialTrials\TrialWithdrawalProgressionService::class)->resolve($entries->first());
        $this->assertSame(5, $semi->fresh()->match_status);
        $this->score($draw, 2002);
        $final = $this->fixture($draw, 2003);
        $this->assertNull($final->registration1_id);
        $this->assertNotNull(app(IndividualMatchOutcomeService::class)->winner($final));
        $this->assertSame(0, $final->fixtureResults()->count());
        foreach (array_keys(InterproRoundRobinFormat::template()) as $number) {
            $fixture = $this->fixture($draw, $number);
            if (InterproRoundRobinFormat::noMatchRequired($fixture) || app(IndividualMatchOutcomeService::class)->winner($fixture)) continue;
            $this->score($draw, $number);
        }
        $workflow = app(\App\Services\Draw\DrawWorkflow::class)->forDraw($draw->fresh());
        $this->assertTrue($workflow['resultsComplete']);
    }

    public function test_legacy_plate_and_schedule_writers_cannot_change_selected_format(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->reachFeedIn($draw);
        $fixtures = $draw->drawFixtures()->get()->toJson();
        $this->postJson(route('backend.draw.generate-second-third-bracket', $draw))->assertConflict();
        $this->assertSame($fixtures, $draw->drawFixtures()->get()->toJson());
        $request = \Illuminate\Http\Request::create('/', 'POST', ['items' => []]);
        foreach ([\App\Http\Controllers\Api\DrawApiController::class => 'saveSchedule',
            \App\Http\Controllers\Backend\RoundRobinController::class => 'storeOrderOfPlay'] as $controller => $method) {
            try { app($controller)->$method($request, $draw); $this->fail('Legacy scheduling must be rejected.'); }
            catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        }
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_carried_score_orients_the_original_and_rejects_foreign_or_stale_sources(): void
    {
        [$draw, $target] = $this->playoffCarry();
        $reverse = clone $target;
        $reverse->registration1_id = $target->registration2_id;
        $reverse->registration2_id = $target->registration1_id;
        $this->assertStringStartsWith('2-6', InterproRoundRobinFormat::score($reverse));
        $target->update(['registration1_id' => $reverse->registration1_id, 'registration2_id' => $reverse->registration2_id]);
        $page = $this->get(route('backend.draw.main-bracket', $draw))->assertOk();
        $this->assertSame(['2', '6'], $this->renderedPlayerScores($page->getContent(), 3007));
        $source = InterproRoundRobinFormat::source($target);
        $source->fixtureResults()->first()->update(['registration2_score' => 3]);
        $this->assertNull(app(IndividualMatchOutcomeService::class)->winner($target->fresh()));
        $stale = $this->get(route('backend.draw.main-bracket', $draw))->assertOk()->assertSee('Original result changed');
        $this->assertSame([], $this->renderedPlayerScores($stale->getContent(), 3007));
        $config = $draw->fresh()->settings->playoff_config;
        $other = Fixture::factory()->create(['draw_id' => Draw::factory()->create()->id,
            'registration1_id' => $target->registration1_id, 'registration2_id' => $target->registration2_id]);
        $config[0]['trials_carries'][$target->id]['source'] = $other->id;
        $draw->settings()->update(['playoff_config' => $config]);
        $this->assertNull(InterproRoundRobinFormat::source($target->fresh()));
    }
}
