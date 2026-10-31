<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, Draw, DrawAuditLog, Event, Fixture, OrderOfPlay, Player, Registration, User};
use App\Services\Draw\{DrawResultOrderService, FlexibleMonradCompiler, FlexibleMonradService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FlexibleMonradChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get('database.connections.'.$connection.'.database');
        if ($app->environment() !== 'testing' || ! (($connection === 'sqlite' && $database === ':memory:')
            || ($connection === 'mysql' && $database === 'ct_testing'))) {
            throw new \RuntimeException('Challenge tests require an isolated testing database.');
        }
        return $app;
    }

    private function draw(int $count = 16, int $size = 16, ?Event $event = null, ?array $challengePairs = null): Draw
    {
        $event ??= Event::factory()->create(['name' => 'Isolated challenge test']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id,
            'drawName' => 'Feed-in Monrad — optional challenges test']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $slots = [];
        foreach (range(0, $size - 1) as $index) {
            $path = strtr(str_pad(decbin($index), (int) log($size, 2), '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            if ($index >= $count) { $slots[$path] = ['type' => 'bye']; continue; }
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create(['name' => 'Test', 'surname' => sprintf('Player %02d', $index + 1)]));
            $registration->categoryEvents()->attach($category->id, ['status' => 'registered', 'payment_status_id' => 1]);
            $slots[$path] = ['type' => 'player', 'id' => $registration->id];
        }
        $pairs = $challengePairs ?? array_values(array_filter(range(2, 62, 2), fn ($upper) => $upper < $count));
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad', 'challenge_playoffs' => true, 'challenge_pairs' => $pairs])->assertRedirect();
        $draw->settings()->update(['num_sets' => 1]);
        $this->putJson(route('flexible-monrad.save', $draw), ['revision' => 1,
            'draft' => ['size' => $size, 'slots' => $slots, 'challenge_playoffs' => false]])->assertOk();
        $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 2])->assertOk();
        return $draw->fresh();
    }

    private function finishBase(Draw $draw, array $winners = []): array
    {
        $service = app(FlexibleMonradService::class);
        $baseCount = count(array_filter($draw->fresh()->flexibleMonrad->graph['nodes'], fn ($node) => ! isset($node['challenge_positions'])));
        for ($i = 0; $i < $baseCount; $i++) {
            $state = $service->state($draw->fresh());
            $ready = collect((array) $state['matches'])->first(fn ($match) => ! isset($match['challenge_positions'])
                && ! $match['sets'] && ! $match['automatic'] && count(array_filter($match['players'])) === 2);
            $this->assertNotNull($ready);
            $service->score($draw, $ready['id'], isset($winners[$ready['id']]) && $winners[$ready['id']] === $ready['players'][1]
                ? [[2, 6]] : [[6, 2]], $state['revision'], false);
        }
        return $service->state($draw->fresh());
    }

    public function test_live_state_reads_new_scores_without_changing_records_and_rechecks_access(): void
    {
        $draw = $this->draw();
        $service = app(FlexibleMonradService::class);
        $initial = $this->getJson(route('flexible-monrad.live', $draw))->assertOk();
        $this->assertTrue($initial->json('state.challenge_playoffs'));
        $initial->assertJsonPath('refreshUrl', route('flexible-monrad.live', $draw));
        $state = $this->finishBase($draw);
        $ready = collect((array) $state['matches'])->first(fn ($match) => ($match['challenge_status'] ?? null) === 'ready');
        $service->score($draw->fresh(), $ready['id'], [[2, 6]], $state['revision'], false);
        $revision = $draw->fresh()->flexibleMonrad->revision;
        $venue = DB::table('venues')->insertGetId(['name' => 'Private refresh venue', 'event_id' => $draw->event_id]);
        OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $ready['id'], 'venue_id' => $venue,
            'court' => 77, 'time' => '2026-10-31 12:00:00']);
        $auditCount = DrawAuditLog::where('draw_id', $draw->id)->count();
        $private = $this->getJson(route('flexible-monrad.live', $draw))->assertOk()
            ->assertJsonPath('state.revision', $revision);
        $this->assertStringContainsString('no-store', $private->headers->get('Cache-Control'));
        $scored = collect($private->json('state.matches'))->firstWhere('id', $ready['id']);
        $this->assertSame($ready['players'][1], $scored['winner']);
        $this->assertSame(77, (int) $scored['schedule']['court']);
        $this->assertSame($auditCount, DrawAuditLog::where('draw_id', $draw->id)->count());
        $this->assertSame($revision, $draw->fresh()->flexibleMonrad->revision);
        $other = User::factory()->create()->assignRole('admin');
        $this->actingAs($other)->getJson(route('flexible-monrad.live', $draw))->assertForbidden();
        $draw->update(['published' => true, 'oop_published' => false]);
        auth()->logout();
        $public = $this->getJson(route('public.flexible-monrad.live', $draw))->assertOk()
            ->assertJsonPath('readOnly', true)->assertJsonPath('canScore', false)->assertJsonPath('urls', []);
        $this->assertArrayNotHasKey('revision', $public->json('state'));
        $this->assertStringNotContainsString('Private refresh venue', $public->getContent());
        foreach ($public->json('state.matches') as $match) $this->assertArrayNotHasKey('schedule', $match);
        $this->assertCount(count($draw->flexibleMonrad->graph['players']), $public->json('state.players'));
        $draw->update(['published' => false]);
        $this->getJson(route('public.flexible-monrad.live', $draw))->assertForbidden();
    }

    private function projectedOrder(Draw $draw): \Illuminate\Support\Collection
    {
        $ids = collect($draw->fresh()->flexibleMonrad->graph['players']);
        return app(DrawResultOrderService::class)->forCategory($draw->categoryEvent, collect([$draw->fresh()]), $ids,
            collect([$draw->category_event_id => $ids]));
    }

    public function test_challenges_wait_for_all_base_results_skip_played_pairs_and_schedule_after_every_base_match(): void
    {
        $draw = $this->draw();
        $service = app(FlexibleMonradService::class);
        $state = $service->state($draw);
        $challenge = $state['matches']->challenge_2 ?? ((array) $state['matches'])['challenge_2'];
        $this->assertSame('pending', $challenge['challenge_status']);
        $this->assertSame([null, null], $challenge['players']);
        $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 3])->assertOk();
        $this->assertSame(43, $draw->drawFixtures()->count());
        $this->putJson(route('flexible-monrad.score', [$draw, $challenge['id']]), ['revision' => 3, 'sets' => [[6, 2]]])->assertUnprocessable();
        $venue = DB::table('venues')->insertGetId(['name' => 'Challenge test venue', 'event_id' => $draw->event_id]);
        $draw->venues()->attach($venue, ['num_courts' => 4]);
        $this->postJson(route('backend.individual-schedule.auto', $draw), ['start' => '2026-10-30 08:00:00', 'duration' => 60])->assertOk();
        $state = $service->state($draw->fresh());
        $scheduled = OrderOfPlay::where('draw_id', $draw->id)->get()->keyBy('fixture_id');
        $baseEnd = collect((array) $state['matches'])->reject(fn ($m) => isset($m['challenge_positions']))
            ->map(fn ($m) => \Carbon\Carbon::parse($scheduled[$m['id']]->time)->addMinutes(60)->timestamp)->max();
        foreach ((array) $state['matches'] as $match) if (isset($match['challenge_positions'])) {
            $this->assertGreaterThanOrEqual($baseEnd, \Carbon\Carbon::parse($scheduled[$match['id']]->time)->timestamp);
        }
        $state = $this->finishBase($draw);
        $this->assertFalse($state['positions_complete']);
        $this->assertEmpty($this->projectedOrder($draw));
        $base = array_column($state['base_positions'], 'player', 'position');
        $expected = $base;
        $playedPairs = [];
        foreach ((array) $state['matches'] as $match) {
            if (isset($match['challenge_positions'])) continue;
            $pair = $match['players']; sort($pair); $playedPairs[] = $pair;
        }
        $readyCount = $skippedCount = 0;
        $readyPair = collect((array) $state['matches'])->first(fn ($match) => ($match['challenge_status'] ?? null) === 'ready')['players'];
        $otherDrawMatch = Fixture::factory()->create(['registration1_id' => $readyPair[0], 'registration2_id' => $readyPair[1],
            'winner_registration' => $readyPair[0], 'match_status' => 1]);
        $otherDrawMatch->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2,
            'winner_registration' => $readyPair[0], 'loser_registration' => $readyPair[1]]);
        $isolated = $service->state($draw->fresh());
        $this->assertSame('ready', collect((array) $isolated['matches'])->first(fn ($match) => $match['players'] === $readyPair)['challenge_status']);
        if (getenv('FEED_IN_WORKSPACE_SNAPSHOT')) {
            @mkdir(storage_path('app/testing'), 0777, true);
            $preview = $this->get(route('public.flexible-monrad.show', $draw))->assertOk()->assertSee('Test Player 01');
            file_put_contents(storage_path('app/testing/feed-in-challenges.html'), $preview->getContent());
        }
        foreach ((array) $state['matches'] as $key => $match) {
            if (! isset($match['challenge_positions'])) continue;
            $pair = $match['players']; sort($pair);
            if ($match['challenge_status'] === 'skipped') {
                $skippedCount++;
                $this->assertContains($pair, $playedPairs);
                $this->assertDatabaseHas('fixtures', ['id' => $match['id'], 'match_status' => 5, 'winner_registration' => null]);
                $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $match['id']]);
                $current = $service->state($draw->fresh());
                $this->putJson(route('flexible-monrad.score', [$draw, $match['id']]), ['revision' => $current['revision'], 'sets' => [[6, 2]]])->assertUnprocessable();
            } else {
                $readyCount++;
                $this->assertNotContains($pair, $playedPairs);
                [$upper, $lower] = $match['challenge_positions'];
                $this->assertSame([$base[$upper], $base[$lower]], $match['players']);
                $current = $service->state($draw->fresh());
                $service->score($draw, $match['id'], [[2, 6]], $current['revision'], false);
                [$expected[$upper], $expected[$lower]] = [$base[$lower], $base[$upper]];
            }
        }
        $this->assertGreaterThan(0, $readyCount);
        $this->assertGreaterThan(0, $skippedCount);
        $final = $service->state($draw->fresh());
        $this->assertTrue($final['positions_complete']);
        $this->assertSame($expected, array_column($final['positions'], 'player', 'position'));
        $this->assertSame($base, array_column($final['base_positions'], 'player', 'position'));
        $this->assertCount(16, $this->projectedOrder($draw));
        $this->assertCount(16, array_unique(array_column($final['positions'], 'player')));
        $printed = app(\App\Services\Draw\FlexibleMonradPdfLayout::class)->build($final);
        $challengeBoard = collect($printed['boards'])->firstWhere('section', 'Challenge play-offs');
        $this->assertContains('Skipped - positions unchanged', array_column($challengeBoard['endpoints'], 'name'));
        $this->assertNotContains('Awaiting result', array_column($challengeBoard['endpoints'], 'name'));
        foreach ($challengeBoard['endpoints'] as $endpoint) $this->assertStringStartsWith('Position ', $endpoint['label']);
        if (getenv('FEED_IN_WORKSPACE_SNAPSHOT')) file_put_contents(storage_path('app/testing/feed-in-challenges-final.html'),
            $this->get(route('public.flexible-monrad.show', $draw))->assertOk()->getContent());
    }

    public function test_base_correction_requires_explicit_challenge_reset_and_foreign_fixtures_are_isolated(): void
    {
        $draw = $this->draw();
        $state = $this->finishBase($draw);
        $service = app(FlexibleMonradService::class);
        $challenge = collect((array) $state['matches'])->first(fn ($match) => ($match['challenge_status'] ?? null) === 'ready');
        $this->assertNotNull($challenge);
        $service->score($draw, $challenge['id'], [[2, 6]], $state['revision'], false);
        $state = $service->state($draw->fresh());
        $base = collect((array) $state['matches'])->first(fn ($match) => ! isset($match['challenge_positions']));
        $before = Fixture::findOrFail($challenge['id'])->fixtureResults->toArray();
        $url = route('flexible-monrad.score', [$draw, $base['id']]);
        $this->putJson($url, ['revision' => $state['revision'], 'sets' => [[2, 6]]])->assertConflict();
        $this->assertEquals($before, Fixture::findOrFail($challenge['id'])->fixtureResults->toArray());
        $foreign = Fixture::factory()->create();
        $this->putJson(route('flexible-monrad.score', [$draw, $foreign]), ['revision' => $state['revision'], 'sets' => [[6, 2]]])->assertNotFound();
        $this->putJson($url, ['revision' => $state['revision'], 'sets' => [[2, 6]], 'reset_dependents' => true])->assertOk();
        $this->assertSame(0, Fixture::findOrFail($challenge['id'])->fixtureResults()->count());
        $this->assertDatabaseHas('draw_audit_logs', ['draw_id' => $draw->id, 'action' => 'monrad_score_changed']);
        $this->assertSame(0, $foreign->fixtureResults()->count());
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $current = $service->state($draw->fresh());
        $this->putJson($url, ['revision' => $current['revision'], 'sets' => [[6, 2]]])->assertForbidden();
    }

    public function test_explicit_byes_advance_correctly_and_selected_challenges_finish_with_unique_positions(): void
    {
        $draw = $this->draw(6, 8);
        $service = app(FlexibleMonradService::class);
        $this->assertCount(2, array_filter($draw->flexibleMonrad->draft['slots'], fn ($slot) => $slot['type'] === 'bye'));
        $state = $this->finishBase($draw);
        $this->assertCount(6, $state['base_positions']);
        foreach ((array) $state['matches'] as $match) {
            if (($match['challenge_status'] ?? null) !== 'ready') continue;
            $current = $service->state($draw->fresh());
            $service->score($draw, $match['id'], [[2, 6]], $current['revision'], false);
        }
        $final = $service->state($draw->fresh());
        $this->assertTrue($final['positions_complete']);
        $this->assertCount(6, array_unique(array_column($final['positions'], 'player')));
        $this->assertNotContains(null, array_column($final['positions'], 'player'));
        $this->assertEqualsCanonicalizing($draw->flexibleMonrad->graph['players'], array_column($final['positions'], 'player'));
    }

    public function test_rolling_challenges_append_idempotently_without_regenerating_results_or_publishing_times(): void
    {
        $draw = $this->draw();
        $service = app(FlexibleMonradService::class);
        $state = $service->state($draw);
        $base = collect((array) $state['matches'])->first(fn ($match) => count(array_filter($match['players'])) === 2);
        $service->score($draw, $base['id'], [[6, 2]], $state['revision'], false);
        $before = $draw->fresh()->flexibleMonrad;
        $results = Fixture::findOrFail($base['id'])->fixtureResults->toArray();
        $venue = DB::table('venues')->insertGetId(['name' => 'Isolated rolling venue', 'event_id' => $draw->event_id]);
        $oop = OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $base['id'], 'time' => '2026-11-01 08:30:00',
            'court' => 1, 'venue_id' => $venue]);
        $oop->refresh();
        $draw->update(['published' => true]);
        $url = route('flexible-monrad.rolling-challenges', $draw);
        $this->postJson($url, ['revision' => $before->revision])->assertOk()->assertJsonPath('rolling_challenges', true);
        $after = $draw->fresh()->flexibleMonrad;
        foreach ($before->fixture_map as $key => $id) $this->assertSame($id, $after->fixture_map[$key]);
        $this->assertSame(count($before->fixture_map) + 2, $draw->drawFixtures()->count());
        $this->assertSame($results, Fixture::findOrFail($base['id'])->fixtureResults->toArray());
        $this->assertSame($oop->toArray(), $oop->fresh()->toArray());
        $this->assertTrue((bool) $draw->fresh()->published);
        $this->assertSame(0, DB::table('published_schedule_assignments')->where('event_id', $draw->event_id)->count());
        $this->assertDatabaseHas('draw_audit_logs', ['draw_id' => $draw->id, 'action' => 'monrad_rolling_challenges_enabled']);
        $this->postJson($url, ['revision' => $after->revision])->assertOk();
        $this->assertSame($after->revision, $draw->fresh()->flexibleMonrad->revision);
        $this->assertSame(count($before->fixture_map) + 2, $draw->drawFixtures()->count());
        $this->postJson($url, ['revision' => $before->revision])->assertConflict();
    }

    public function test_rolling_challenge_activation_is_event_admin_only_and_rejects_locked_draws(): void
    {
        $draw = $this->draw();
        $revision = $draw->flexibleMonrad->revision;
        $url = route('flexible-monrad.rolling-challenges', $draw);
        $manager = auth()->user();
        $foreign = User::factory()->create()->assignRole('admin');
        $this->actingAs($foreign)->postJson($url, ['revision' => $revision])->assertForbidden();
        $this->assertFalse($draw->fresh()->flexibleMonrad->graph['rolling_challenges'] ?? false);
        $this->actingAs($manager);
        $draw->update(['locked' => true]);
        $this->postJson($url, ['revision' => $revision])->assertForbidden();
        $draw->update(['locked' => false]);
        $this->postJson($url, ['revision' => $revision])->assertOk();
    }

    public function test_rolling_correction_requires_explicit_reset_of_scored_later_challenges(): void
    {
        $draw = $this->draw();
        $service = app(FlexibleMonradService::class);
        $service->enableRollingChallenges($draw, $draw->flexibleMonrad->revision);
        // Find a deterministic valid tournament outcome in which all four
        // challenges are needed, then record those base results canonically.
        $record = $draw->fresh()->flexibleMonrad;
        $winners = [];
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $input = $outcomes = $candidate = [];
            $resolveBase = function ($key) use (&$resolveBase, &$input, &$outcomes, &$candidate, $record, $attempt): void {
                if (isset($input[$key])) return;
                $node = $record->graph['nodes'][$key];
                if (isset($node['challenge_positions'])) {
                    $input[$key] = ['players' => [null, null], 'winner' => null, 'played' => false];
                    return;
                }
                foreach ($node['sources'] as $source) if (isset($source['match'])) $resolveBase($source['match']);
                $players = array_map(fn ($source) => $source['type'] === 'player' ? $source['id']
                    : $outcomes[$source['match']][$source['type']], $node['sources']);
                $slot = crc32($attempt.':'.$key) % 2;
                $outcomes[$key] = ['winner' => $players[$slot], 'loser' => $players[1 - $slot]];
                $candidate[$record->fixture_map[$key]] = $players[$slot];
                $input[$key] = ['players' => $players, 'winner' => $players[$slot], 'played' => true];
            };
            foreach (array_keys($record->graph['nodes']) as $key) $resolveBase($key);
            $needed = true;
            foreach (['challenge_2', 'challenge_4', 'challenge_3', 'challenge_2_repeat'] as $key) {
                $projected = (new \App\Services\Draw\FlexibleMonradProgression)->resolve($record->graph, $input, []);
                $match = $projected['matches'][$key];
                if ($match['challenge_status'] !== 'ready') { $needed = false; break; }
                $input[$key] = ['players' => $match['players'], 'winner' => $match['players'][1], 'played' => true];
            }
            if ($needed) { $winners = $candidate; break; }
        }
        $this->assertNotEmpty($winners, 'A four-stage correction scenario must be exercised.');
        $state = $this->finishBase($draw, $winners);
        $baseResults = $draw->drawFixtures()->whereIn('id', collect((array) $state['matches'])
            ->reject(fn ($match) => isset($match['challenge_positions']))->pluck('id'))->with('fixtureResults')->get()->toArray();
        $scored = [];
        foreach (['challenge_2', 'challenge_4', 'challenge_3', 'challenge_2_repeat'] as $key) {
            $state = $service->state($draw->fresh());
            $match = ((array) $state['matches'])[$key];
            $this->assertSame('ready', $match['challenge_status'], $key);
            $service->score($draw, $match['id'], [[2, 6]], $state['revision'], false);
            $scored[] = $key;
        }
        $this->assertContains('challenge_2', $scored);
        $this->assertContains('challenge_3', $scored);
        $state = $service->state($draw->fresh());
        $matches = (array) $state['matches'];
        $url = route('flexible-monrad.score', [$draw, $matches['challenge_2']['id']]);
        $this->putJson($url, ['revision' => $state['revision'], 'sets' => [[6, 2]]])->assertConflict();
        foreach (array_intersect(['challenge_3', 'challenge_2_repeat'], $scored) as $key) $this->assertSame(1, Fixture::findOrFail($matches[$key]['id'])->fixtureResults()->count());
        $this->putJson($url, ['revision' => $state['revision'], 'sets' => [[6, 2]], 'reset_dependents' => true])->assertOk();
        foreach (['challenge_3', 'challenge_2_repeat'] as $key) $this->assertSame(0, Fixture::findOrFail($matches[$key]['id'])->fixtureResults()->count());
        $after = $draw->drawFixtures()->whereIn('id', array_column($baseResults, 'id'))->with('fixtureResults')->get()->toArray();
        $this->assertSame($baseResults, $after);
        $this->assertSame(in_array('challenge_4', $scored, true) ? 1 : 0, Fixture::findOrFail($matches['challenge_4']['id'])->fixtureResults()->count());
    }

    public function test_top_eight_subset_and_rolling_challenges_schedule_without_unplayed_lower_positions(): void
    {
        $event = Event::factory()->create(['start_date' => '2026-10-30', 'end_date' => '2026-11-01']);
        $venue = \App\Models\Venue::forceCreate(['name' => 'Isolated Sunday courts']);
        $event->venues()->attach($venue->id, ['num_courts' => 6]);
        $service = app(FlexibleMonradService::class);
        $draws = collect(['Boys', 'Girls'])->map(function ($name) use ($event) {
            $draw = $this->draw(16, 16, $event);
            $draw->update(['drawName' => $name]);
            return $draw;
        });
        $selectedIds = []; $lowerIds = [];
        foreach ($draws as $draw) {
            $draw->venues()->attach($venue->id, ['num_courts' => 6]);
            $record = $service->enableRollingChallenges($draw, $draw->flexibleMonrad->revision);
            $top = array_map(fn ($rank) => $record->graph['positions'][$rank]['match'], [1, 3, 5, 7]);
            $required = [];
            $visit = function ($key) use (&$visit, &$required, $record): void {
                foreach (\App\Services\Draw\FlexibleMonradCompiler::dependencies($record->graph['nodes'][$key]) as $dependency) {
                    if (isset($required[$dependency])) continue;
                    $required[$dependency] = true; $visit($dependency);
                }
            };
            foreach ($top as $key) $visit($key);
            while ($required) {
                $state = $service->state($draw->fresh());
                $key = collect((array) $state['matches'])->keys()->first(fn ($key) => isset($required[$key])
                    && count(array_filter(((array) $state['matches'])[$key]['players'])) === 2);
                $this->assertNotNull($key);
                $service->score($draw, ((array) $state['matches'])[$key]['id'], [[6, 2]], $state['revision'], false);
                unset($required[$key]);
            }
            $selected = array_merge($top, ['challenge_2', 'challenge_4', 'challenge_3', 'challenge_2_repeat']);
            foreach ($selected as $key) $selectedIds[] = $record->fixture_map[$key];
            foreach ($record->graph['nodes'] as $key => $node) {
                if (! isset($node['challenge_positions']) && ! in_array($key, $top, true)
                    && ! Fixture::findOrFail($record->fixture_map[$key])->fixtureResults()->exists()) $lowerIds[] = $record->fixture_map[$key];
            }
        }
        $draws = $draws->map->fresh();
        $this->assertNotEmpty($lowerIds);
        $scheduler = app(\App\Services\Scheduling\EventVenueScheduleService::class);
        $rounds = [];
        foreach ($draws as $draw) {
            $rounds[$draw->id] = [];
            foreach ($scheduler->dayRoundDetails($draw) as $round => $groups) {
                if (collect($groups)->flatMap(fn ($group) => $group['matches'])->contains(fn ($match) => in_array($match['fixture_id'], $selectedIds, true))) {
                    $rounds[$draw->id][] = (int) $round;
                }
            }
        }
        $options = ['day_batch' => true, 'full_day_scope' => '2026-11-01', 'start' => '2026-11-01 08:30', 'end' => '2026-11-01 18:00',
            'draw_ids' => $draws->pluck('id')->all(), 'draw_order' => $draws->pluck('id')->all(), 'draw_order_mode' => 'mixed',
            'draw_rounds' => $draws->map(fn ($draw) => ['draw_id' => $draw->id, 'rounds' => $rounds[$draw->id]])->all(),
            'selected_fixture_keys' => array_map(fn ($id) => 'individual:'.$id, $selectedIds),
            'venue_ids' => [$venue->id], 'day_court_labels' => [$venue->id => ['1', '2', '3', '4', '5', '6']],
            'duration' => 75, 'player_rest' => 45, 'court_gap' => 0];
        $preview = $scheduler->preview($event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertEqualsCanonicalizing($selectedIds, array_column($preview['matches'], 'fixture_id'));
        if (getenv('SCHEDULE_SUBSET_SNAPSHOT') === '1') {
            $page = $this->get(route('backend.event-venue-schedule.index', ['event' => $event->id, 'date' => '2026-11-01']))->assertOk();
            @mkdir(storage_path('app/testing'), 0777, true);
            file_put_contents(storage_path('app/testing/rolling-subset.html'), $page->getContent());
        }
        $scheduler->apply($event, $options, $preview['revision']);
        $this->assertEqualsCanonicalizing($selectedIds, OrderOfPlay::pluck('fixture_id')->all());
        $this->assertSame(0, Fixture::whereIn('id', $lowerIds)->where('match_status', '!=', 0)->count());
        $this->assertSame(0, DB::table('published_schedule_assignments')->count());
        foreach ($draws as $draw) $this->assertFalse((bool) $draw->fresh()->oop_published);
    }

    public function test_each_draw_keeps_its_own_stage_choices_and_all_off_is_idempotent(): void
    {
        $draw = $this->draw();
        $other = $this->draw();
        $actor = User::findOrFail(DB::table('event_admins')->where('event_id', $draw->event_id)->value('user_id'));
        $this->actingAs($actor);
        $service = app(FlexibleMonradService::class);
        $service->enableRollingChallenges($draw, $draw->flexibleMonrad->revision);
        $before = $draw->fresh()->flexibleMonrad;
        $otherBefore = $other->fresh()->flexibleMonrad->toArray();
        $url = route('flexible-monrad.rolling-challenges.configure', $draw);
        $this->postJson($url, ['revision' => $before->revision, 'stages' => ['challenge_3']])->assertOk()
            ->assertJsonPath('rolling_challenge_stages', ['challenge_3'])->assertJsonPath('matches.challenge_4.challenge_status', 'disabled');
        $this->assertSame($before->fixture_map, $draw->fresh()->flexibleMonrad->fixture_map);
        $this->assertSame($otherBefore, $other->fresh()->flexibleMonrad->toArray());
        $this->postJson($url, ['revision' => $before->revision, 'stages' => []])->assertConflict();
        $revision = $draw->fresh()->flexibleMonrad->revision;
        $this->postJson($url, ['revision' => $revision, 'stages' => []])->assertOk()->assertJsonPath('rolling_challenge_stages', []);
        $revision = $draw->fresh()->flexibleMonrad->revision;
        $this->postJson($url, ['revision' => $revision, 'stages' => []])->assertOk();
        $this->assertSame($revision, $draw->fresh()->flexibleMonrad->revision);
        $this->assertSame([], $draw->fresh()->settings->playoff_config['rolling_challenge_stages']);
        $this->assertSame($before->fixture_map, $draw->fresh()->flexibleMonrad->fixture_map);
    }

    public function test_stage_configuration_validates_choices_and_retains_event_authority_and_lock_boundaries(): void
    {
        $draw = $this->draw();
        $actor = auth()->user();
        $revision = $draw->flexibleMonrad->revision;
        $url = route('flexible-monrad.rolling-challenges.configure', $draw);
        foreach ([['challenge_2_repeat'], ['unknown'], ['challenge_2', 'challenge_2']] as $stages) {
            $this->postJson($url, ['revision' => $revision, 'stages' => $stages])->assertUnprocessable();
        }
        $this->postJson($url, ['revision' => $revision])->assertUnprocessable();
        $this->actingAs(User::factory()->create()->assignRole('admin'))
            ->postJson($url, ['revision' => $revision, 'stages' => []])->assertForbidden();
        $this->actingAs($actor);
        $draw->update(['locked' => true]);
        $this->postJson($url, ['revision' => $revision, 'stages' => []])->assertForbidden();
        $this->assertSame($revision, $draw->fresh()->flexibleMonrad->revision);
    }

    public function test_private_bookings_require_current_explicit_confirmation_and_unaffected_matches_are_preserved(): void
    {
        $draw = $this->draw();
        $service = app(FlexibleMonradService::class);
        $record = $service->enableRollingChallenges($draw, $draw->flexibleMonrad->revision);
        $venue = DB::table('venues')->insertGetId(['name' => 'Fictional private courts', 'event_id' => $draw->event_id]);
        $orders = [];
        foreach (['challenge_4' => '10:30', 'challenge_3' => '12:30', 'challenge_2_repeat' => '14:30', 'challenge_2' => '10:30', 'main_final' => '08:30'] as $key => $time) {
            $orders[$key] = OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $record->fixture_map[$key],
                'time' => '2026-11-01 '.$time.':00', 'venue_id' => $venue, 'court' => $key === 'challenge_2' ? '2' : '1']);
        }
        DB::table('schedules')->insert(['draw_id' => $draw->id, 'fixture_id' => $record->fixture_map['challenge_4'],
            'venue_id' => $venue, 'time' => '2026-11-01 10:30:00']);
        $unchanged = OrderOfPlay::whereIn('id', [$orders['challenge_2']->id, $orders['main_final']->id])->get()->toArray();
        $url = route('flexible-monrad.rolling-challenges.configure', $draw);
        $body = ['revision' => $record->revision, 'stages' => ['challenge_2', 'challenge_3', 'challenge_2_repeat']];
        $response = $this->postJson($url, $body)->assertConflict();
        if (getenv('SCHEDULE_STAGE_SNAPSHOT') === '1') {
            $page = $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk();
            @mkdir(storage_path('app/testing'), 0777, true);
            file_put_contents(storage_path('app/testing/rolling-stage-settings.html'), $page->getContent());
            file_put_contents(storage_path('app/testing/rolling-stage-confirmation.json'), json_encode($response->json(), JSON_PRETTY_PRINT));
        }
        $token = $response->json('schedule_confirmation');
        $this->assertSame(64, strlen($token));
        $this->assertEqualsCanonicalizing(array_map(fn ($key) => $record->fixture_map[$key], ['challenge_4', 'challenge_3', 'challenge_2_repeat']),
            array_column($response->json('schedule_removals'), 'fixture_id'));
        $this->assertDatabaseCount('order_of_plays', 5);
        $this->assertDatabaseCount('schedules', 1);
        $this->assertSame($record->revision, $draw->fresh()->flexibleMonrad->revision);
        $orders['challenge_3']->update(['court' => '3']);
        $response = $this->postJson($url, $body + ['schedule_confirmation' => $token])->assertConflict();
        $this->assertNotSame($token, $response->json('schedule_confirmation'));
        $token = $response->json('schedule_confirmation');
        $otherActor = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $draw->event_id, 'user_id' => $otherActor->id]);
        $originalActor = auth()->user();
        $this->actingAs($otherActor)->postJson($url, $body + ['schedule_confirmation' => $token])->assertConflict();
        $this->actingAs($originalActor);
        $this->postJson($url, array_replace($body, ['stages' => [], 'schedule_confirmation' => $token]))->assertConflict();
        $this->assertDatabaseCount('order_of_plays', 5);
        $this->postJson($url, $body + ['schedule_confirmation' => $token])->assertOk();
        $this->assertSame($unchanged, OrderOfPlay::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('schedules', 0);
        $this->assertSame($record->fixture_map, $draw->fresh()->flexibleMonrad->fixture_map);
        $audit = \App\Models\DrawAuditLog::where('draw_id', $draw->id)->where('action', 'monrad_rolling_challenges_configured')->firstOrFail();
        $this->assertCount(3, $audit->payload['removed_private_times']);
        $this->assertCount(1, $audit->payload['previous_schedules']);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }

    public function test_started_or_published_descendants_cannot_be_changed_even_with_a_confirmation_token(): void
    {
        foreach (['started', 'partial', 'published'] as $protection) {
            $draw = $this->draw();
            $record = app(FlexibleMonradService::class)->enableRollingChallenges($draw, $draw->flexibleMonrad->revision);
            $id = $record->fixture_map['challenge_3'];
            if ($protection === 'started') \App\Models\DrawAuditLog::record($draw->id, 'match_started', $id);
            elseif ($protection === 'partial') Fixture::findOrFail($id)->update(['match_status' => 2]);
            else DB::table('published_schedule_assignments')->insert(['event_id' => $draw->event_id, 'draw_id' => $draw->id,
                'fixture_kind' => 'individual', 'fixture_id' => $id, 'scheduled_at' => '2026-11-01 12:30:00',
                'venue_id' => null, 'court' => '1', 'duration' => 75, 'published_at' => now()]);
            $this->postJson(route('flexible-monrad.rolling-challenges.configure', $draw), ['revision' => $record->revision,
                'stages' => ['challenge_2', 'challenge_3', 'challenge_2_repeat'], 'schedule_confirmation' => str_repeat('0', 64)])->assertConflict();
            $this->assertSame($record->revision, $draw->fresh()->flexibleMonrad->revision);
            $this->assertSame(FlexibleMonradCompiler::selectedRollingStages($record->graph), $draw->fresh()->flexibleMonrad->graph['rolling_challenge_stages']);
        }
    }

    public function test_played_stage_is_protected_but_does_not_block_changing_an_independent_stage(): void
    {
        $draw = $this->draw();
        $service = app(FlexibleMonradService::class);
        $service->enableRollingChallenges($draw, $draw->flexibleMonrad->revision);
        $state = $this->finishBase($draw);
        $match = ((array) $state['matches'])['challenge_2'];
        $this->assertSame('ready', $match['challenge_status']);
        $service->score($draw, $match['id'], [[6, 2]], $state['revision'], false);
        $before = Fixture::findOrFail($match['id'])->fixtureResults->toArray();
        $revision = $draw->fresh()->flexibleMonrad->revision;
        $url = route('flexible-monrad.rolling-challenges.configure', $draw);
        $this->postJson($url, ['revision' => $revision, 'stages' => ['challenge_2', 'challenge_3', 'challenge_2_repeat']])->assertOk();
        $this->assertSame($before, Fixture::findOrFail($match['id'])->fixtureResults->toArray());
        $this->postJson($url, ['revision' => $draw->fresh()->flexibleMonrad->revision,
            'stages' => ['challenge_3', 'challenge_2_repeat']])->assertConflict();
        $this->assertSame($before, Fixture::findOrFail($match['id'])->fixtureResults->toArray());
    }

    public function test_disabled_stage_is_absent_from_match_selection_and_a_disabled_only_round_is_not_offered(): void
    {
        $draw = $this->draw();
        $service = app(FlexibleMonradService::class);
        $record = $service->enableRollingChallenges($draw, $draw->flexibleMonrad->revision);
        $service->configureRollingChallenges($draw, $record->revision, ['challenge_4', 'challenge_2', 'challenge_3']);
        $draw = $draw->fresh();
        $scheduler = app(\App\Services\Scheduling\EventVenueScheduleService::class);
        $id = $record->fixture_map['challenge_2_repeat'];
        $round = $scheduler->programmeFixtureRounds($draw)[$id];
        $metadata = $scheduler->dayRoundDetails($draw);
        $ids = collect($metadata)->flatten(1)->flatMap(fn ($group) => array_column($group['matches'], 'fixture_id'))->all();
        $this->assertNotContains($id, $ids);
        $this->assertArrayNotHasKey($round, $metadata);
        $this->assertNotContains($round, $scheduler->availableDrawRounds(collect([$draw]), true)[$draw->id]);
    }

    public function test_editing_eight_nine_preserves_existing_top_five_and_six_seven_bookings_and_fixture_ids(): void
    {
        $draw = $this->draw(16, 16, null, [2, 4, 6, 8]);
        $service = app(FlexibleMonradService::class);
        $state = $service->state($draw);
        $base = collect((array) $state['matches'])->first(fn ($match) => ! isset($match['challenge_positions']) && count(array_filter($match['players'])) === 2);
        $service->score($draw, $base['id'], [[6, 2]], $state['revision'], false);
        $baseResults = Fixture::findOrFail($base['id'])->fixtureResults->toArray();
        $record = $service->enableRollingChallenges($draw, $draw->fresh()->flexibleMonrad->revision);
        // Emulate the already-deployed four-stage list with untouched legacy6/8 nodes.
        $graph = $record->graph;
        $graph['rolling_challenge_stages'] = FlexibleMonradCompiler::ROLLING_STAGES;
        unset($graph['nodes']['challenge_6']['challenge_enabled'], $graph['nodes']['challenge_8']['challenge_enabled']);
        $record->update(['graph' => $graph]);
        $venue = DB::table('venues')->insertGetId(['name' => 'Fictional current draw courts', 'event_id' => $draw->event_id]);
        foreach (array_merge(FlexibleMonradCompiler::ROLLING_STAGES, ['challenge_6']) as $index => $key) {
            OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $record->fixture_map[$key],
                'time' => '2026-11-01 '.sprintf('%02d', 10 + $index).':30:00', 'venue_id' => $venue, 'court' => '1']);
        }
        $before = OrderOfPlay::orderBy('id')->get()->toArray();
        if (getenv('SCHEDULE_STAGE_SNAPSHOT') === '1') {
            $page = $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk();
            @mkdir(storage_path('app/testing'), 0777, true);
            file_put_contents(storage_path('app/testing/rolling-stage-settings.html'), $page->getContent());
        }
        $state = $service->state($draw->fresh());
        $this->assertContains('challenge_6', $state['rolling_challenge_stages']);
        $this->assertContains('challenge_8', $state['rolling_challenge_stages']);
        $selected = array_values(array_diff($state['rolling_challenge_stages'], ['challenge_8']));
        $url = route('flexible-monrad.rolling-challenges.configure', $draw);
        $this->postJson($url, ['revision' => $record->revision, 'stages' => $selected])->assertOk();
        $after = $draw->fresh()->flexibleMonrad;
        $this->assertSame($before, OrderOfPlay::orderBy('id')->get()->toArray());
        $this->assertSame($record->fixture_map, $after->fixture_map);
        foreach (array_merge(FlexibleMonradCompiler::ROLLING_STAGES, ['challenge_6']) as $key) $this->assertSame($graph['nodes'][$key], $after->graph['nodes'][$key]);
        $this->assertFalse($after->graph['nodes']['challenge_8']['challenge_enabled']);
        $this->postJson($url, ['revision' => $after->revision, 'stages' => $selected])->assertOk();
        $this->assertSame($after->revision, $draw->fresh()->flexibleMonrad->revision);
        // Legacy enable only changes the top five; it must not re-enable8–9.
        $this->postJson(route('flexible-monrad.rolling-challenges', $draw), ['revision' => $after->revision])->assertOk()
            ->assertJsonPath('matches.challenge_8.challenge_status', 'disabled');
        $this->assertSame($before, OrderOfPlay::orderBy('id')->get()->toArray());
        $this->assertSame($baseResults, Fixture::findOrFail($base['id'])->fixtureResults->toArray());
        $all = array_merge($selected, ['challenge_8']);
        $this->postJson($url, ['revision' => $draw->fresh()->flexibleMonrad->revision, 'stages' => $all])->assertOk();
        $revision = $draw->fresh()->flexibleMonrad->revision;
        OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $record->fixture_map['challenge_8'],
            'time' => '2026-11-01 16:30:00', 'venue_id' => $venue, 'court' => '2']);
        $response = $this->postJson($url, ['revision' => $revision, 'stages' => $selected])->assertConflict();
        $this->assertCount(1, $response->json('schedule_removals'));
        $this->assertSame($record->fixture_map['challenge_8'], $response->json('schedule_removals.0.fixture_id'));
        $this->postJson($url, ['revision' => $revision, 'stages' => $selected, 'schedule_confirmation' => $response->json('schedule_confirmation')])->assertOk();
        $this->assertSame($before, OrderOfPlay::orderBy('id')->get()->toArray());
        $this->assertSame($baseResults, Fixture::findOrFail($base['id'])->fixtureResults->toArray());
    }

    public function test_legacy_higher_only_edits_keep_initial_pair_dependency_footprints_and_private_times(): void
    {
        $draw = $this->draw(16, 16, null, [2, 4, 6, 8]);
        $record = $draw->flexibleMonrad;
        $venue = DB::table('venues')->insertGetId(['name' => 'Fictional legacy courts', 'event_id' => $draw->event_id]);
        foreach (['challenge_2', 'challenge_4'] as $index => $key) OrderOfPlay::create([
            'draw_id' => $draw->id, 'fixture_id' => $record->fixture_map[$key], 'time' => '2026-11-01 10:30:00', 'venue_id' => $venue, 'court' => (string) ($index + 1)]);
        $before = OrderOfPlay::orderBy('id')->get()->toArray();
        $this->postJson(route('flexible-monrad.rolling-challenges.configure', $draw), ['revision' => $record->revision,
            'stages' => ['challenge_2', 'challenge_4', 'challenge_8']])->assertOk();
        $graph = $draw->fresh()->flexibleMonrad->graph;
        foreach (['challenge_2', 'challenge_4', 'challenge_8'] as $key) $this->assertSame($record->graph['nodes'][$key], $graph['nodes'][$key]);
        $this->assertSame($before, OrderOfPlay::orderBy('id')->get()->toArray());
        $this->assertFalse($graph['nodes']['challenge_6']['challenge_enabled']);
    }

    public function test_new_valid_higher_pair_is_added_once_and_non_power_of_two_fields_reject_outside_pairs(): void
    {
        $draw = $this->draw(16, 16, null, [2, 4, 6, 8]);
        $service = app(FlexibleMonradService::class);
        $record = $service->enableRollingChallenges($draw, $draw->flexibleMonrad->revision);
        $selected = array_merge(FlexibleMonradCompiler::selectedRollingStages($record->graph), ['challenge_14']);
        $url = route('flexible-monrad.rolling-challenges.configure', $draw);
        $this->postJson($url, ['revision' => $record->revision, 'stages' => $selected])->assertOk()
            ->assertJsonPath('challenge_stage_options.base.challenge_14', '14 vs 15');
        $after = $draw->fresh()->flexibleMonrad;
        $this->assertCount(count($record->fixture_map) + 1, $after->fixture_map);
        foreach ($record->fixture_map as $key => $id) $this->assertSame($id, $after->fixture_map[$key]);
        $this->postJson($url, ['revision' => $after->revision, 'stages' => $selected])->assertOk();
        $this->assertSame($after->fixture_map, $draw->fresh()->flexibleMonrad->fixture_map);
        $this->postJson($url, ['revision' => $after->revision, 'stages' => []])->assertOk()->assertJsonPath('rolling_challenge_stages', []);
        $this->assertSame([], $draw->fresh()->settings->playoff_config['challenge_pairs']);
        foreach ($draw->fresh()->flexibleMonrad->graph['nodes'] as $node) if (isset($node['challenge_positions'])) $this->assertFalse($node['challenge_enabled']);
        $small = $this->draw(12, 16);
        $revision = $small->flexibleMonrad->revision;
        foreach (['challenge_12', 'challenge_14', 'challenge_16'] as $key) $this->postJson(route('flexible-monrad.rolling-challenges.configure', $small),
            ['revision' => $revision, 'stages' => [$key]])->assertUnprocessable();
        $this->postJson(route('flexible-monrad.rolling-challenges.configure', $small), ['revision' => $revision, 'stages' => ['challenge_10']])->assertOk()
            ->assertJsonPath('challenge_stage_options.base.challenge_10', '10 vs 11');
        $this->assertArrayNotHasKey('challenge_12', $service->state($small->fresh())['challenge_stage_options']['base']);
    }

    public function test_higher_base_pairs_retain_started_recorded_result_and_publication_protections(): void
    {
        foreach (['started', 'result', 'published'] as $protection) {
            $draw = $this->draw(16, 16, null, [2, 4, 6, 8]);
            $service = app(FlexibleMonradService::class);
            $record = $service->enableRollingChallenges($draw, $draw->flexibleMonrad->revision);
            $id = $record->fixture_map['challenge_6'];
            if ($protection === 'started') \App\Models\DrawAuditLog::record($draw->id, 'match_started', $id);
            elseif ($protection === 'result') {
                [$first, $second] = array_slice($record->graph['players'], 0, 2);
                $fixture = Fixture::findOrFail($id);
                $fixture->update(['registration1_id' => $first, 'registration2_id' => $second, 'winner_registration' => $first, 'match_status' => 1]);
                $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2,
                    'winner_registration' => $first, 'loser_registration' => $second]);
            } else DB::table('published_schedule_assignments')->insert(['event_id' => $draw->event_id, 'draw_id' => $draw->id,
                'fixture_kind' => 'individual', 'fixture_id' => $id, 'scheduled_at' => '2026-11-01 14:30:00',
                'venue_id' => null, 'court' => '1', 'duration' => 75, 'published_at' => now()]);
            $before = Fixture::with('fixtureResults')->findOrFail($id)->toArray();
            $selected = array_values(array_diff(FlexibleMonradCompiler::selectedRollingStages($record->graph), ['challenge_6']));
            $this->postJson(route('flexible-monrad.rolling-challenges.configure', $draw),
                ['revision' => $record->revision, 'stages' => $selected])->assertConflict();
            $this->assertSame($record->revision, $draw->fresh()->flexibleMonrad->revision);
            $this->assertSame($before, Fixture::with('fixtureResults')->findOrFail($id)->toArray());
        }
    }

    public function test_disabled_branches_retain_fixture_state_and_settings_choices_for_renderers(): void
    {
        $draw = $this->draw(16, 16, null, [2, 4, 6, 8]);
        $service = app(FlexibleMonradService::class);
        $record = $service->enableRollingChallenges($draw, $draw->flexibleMonrad->revision);
        $service->configureRollingChallenges($draw, $record->revision, FlexibleMonradCompiler::ROLLING_STAGES);
        $state = $service->state($draw->fresh());
        foreach (['challenge_6', 'challenge_8'] as $key) {
            $this->assertSame('disabled', ((array) $state['matches'])[$key]['challenge_status']);
            $this->assertNotContains($key, $state['rolling_challenge_stages']);
            $this->assertArrayHasKey($key, $state['challenge_stage_options']['base']);
        }
        $this->assertSame($record->fixture_map, $draw->fresh()->flexibleMonrad->fixture_map);
        if (getenv('DISABLED_CHALLENGE_SNAPSHOT') === '1') {
            $page = $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk();
            @mkdir(storage_path('app/testing'), 0777, true);
            file_put_contents(storage_path('app/testing/disabled-challenge-branches.html'), $page->getContent());
        }
    }
}
