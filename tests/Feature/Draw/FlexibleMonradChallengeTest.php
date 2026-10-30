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

    private function draw(int $count = 16, int $size = 16): Draw
    {
        $event = Event::factory()->create(['name' => 'Isolated challenge test']);
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

    private function finishBase(Draw $draw): array
    {
        $service = app(FlexibleMonradService::class);
        $baseCount = count(array_filter($draw->fresh()->flexibleMonrad->graph['nodes'], fn ($node) => ! isset($node['challenge_positions'])));
        for ($i = 0; $i < $baseCount; $i++) {
            $state = $service->state($draw->fresh());
            $ready = collect((array) $state['matches'])->first(fn ($match) => ! isset($match['challenge_positions'])
                && ! $match['sets'] && ! $match['automatic'] && count(array_filter($match['players'])) === 2);
            $this->assertNotNull($ready);
            $service->score($draw, $ready['id'], [[6, 2]], $state['revision'], false);
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
}
