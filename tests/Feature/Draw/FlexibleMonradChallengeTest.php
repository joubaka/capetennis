<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, Draw, Event, Fixture, OrderOfPlay, Player, Registration, User};
use App\Services\Draw\{DrawResultOrderService, FlexibleMonradService};
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

    private function draw(int $count = 16, int $size = 16, ?Event $event = null): Draw
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
        $pairs = array_values(array_filter(range(2, 62, 2), fn ($upper) => $upper < $count));
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
}
