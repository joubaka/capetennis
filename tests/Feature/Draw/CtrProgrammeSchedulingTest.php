<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Models\{CategoryEvent, CtrDrawPlan, Draw, DrawGroup, Event, OrderOfPlay, Registration, User, Venue};
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CtrProgrammeSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public static function modes(): array
    {
        return ['programme' => [null, false, null], 'mixed day' => ['mixed', false, null],
            'sequential day' => ['sequential', false, null], 'partial selection' => ['mixed', true, null],
            'fixed programme' => [null, false, 'fixed'], 'fixed mixed day' => ['mixed', false, 'fixed'],
            'played mixed day' => ['mixed', false, 'played'], 'conflicting fixed turns' => ['mixed', false, 'conflict']];
    }

    #[DataProvider('modes')]
    public function test_independent_ctr_draws_rotate_by_round_with_largest_group_first(?string $mode, bool $partial, ?string $saved): void
    {
        $event = Event::factory()->create(['eventType' => 1, 'start_date' => '2026-09-10', 'end_date' => '2026-09-10']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $venue = new Venue();
        $venue->forceFill(['name' => 'Fictional CTR courts'])->save();
        $event->venues()->attach($venue->id, ['num_courts' => 4]);
        $draws = collect();
        foreach ([4, 4, 8] as $index => $size) {
            $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id,
                'drawName' => 'CTR group '.($index + 1), 'locked' => false, 'published' => false]);
            $draw->venues()->attach($venue->id, ['num_courts' => 4]);
            $draw->settings()->create(['workflow' => 'round_robin', 'score_format' => 'timed',
                'match_duration_minutes' => 15, 'changeover_minutes' => 0]);
            $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => 'A', 'round_robin_phase' => 1]);
            $group->registrations()->attach(Registration::factory()->count($size)->create()->pluck('id'));
            app(RoundRobinGenerationService::class)->generate($draw);
            $draws->push($draw->load('drawFixtures'));
        }
        $plan = CtrDrawPlan::create(['token' => (string) Str::uuid(), 'event_id' => $event->id,
            'actor_id' => User::factory()->create()->id, 'fingerprint' => str_repeat('0', 64), 'proposal' => [],
            'draw_ids' => $draws->pluck('id')->all(), 'applied_at' => now(), 'expires_at' => now()->addMinutes(30)]);
        $fixtures = $draws->flatMap->drawFixtures;
        $savedFixture = $saved ? $fixtures->where('draw_id', $draws[2]->id)->where('round', 1)->last() : null;
        if ($savedFixture) {
            if ($saved === 'played') $savedFixture->update(['match_status' => 2]);
            OrderOfPlay::create(['fixture_id' => $savedFixture->id, 'draw_id' => $savedFixture->draw_id,
                'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-09-10 09:00:00',
                'duration_minutes' => 15, 'gap_minutes' => 0]);
        }
        if ($saved === 'conflict') {
            $earlierSmall = $fixtures->where('draw_id', $draws[0]->id)->where('round', 1)->first();
            OrderOfPlay::create(['fixture_id' => $earlierSmall->id, 'draw_id' => $earlierSmall->draw_id,
                'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-09-10 08:00:00',
                'duration_minutes' => 15, 'gap_minutes' => 0]);
        }
        $selected = $partial ? $fixtures->where('round', 1)->values() : $fixtures;
        if ($partial) $selected = $selected->reject(fn ($fixture) => $fixture->id === $selected->where('draw_id', $draws[2]->id)->last()->id);
        $options = ['start' => '2026-09-10 08:00:00', 'end' => '2026-09-10 18:00:00',
            'duration' => 15, 'court_gap' => 0, 'player_rest' => 0, 'wave_minutes' => 0,
            'draw_ids' => $draws->pluck('id')->all()];
        if ($mode) $options += ['day_batch' => true, 'full_day_scope' => '2026-09-10',
            'draw_order' => $draws->pluck('id')->all(), 'draw_order_mode' => $mode,
            'selected_fixture_keys' => $selected->map(fn ($fixture) => 'individual:'.$fixture->id)->all(),
            'draw_rounds' => $draws->map(fn ($draw, $index) => ['draw_id' => $draw->id,
                'rounds' => $partial ? [1] : range(1, $index === 2 ? 7 : 3)])->all(),
            'venue_ids' => [$venue->id], 'day_court_labels' => [$venue->id => ['1', '2', '3', '4']]];
        $scheduler = app(EventVenueScheduleService::class);
        if ($saved === 'conflict') {
            $before = OrderOfPlay::orderBy('id')->get()->map->getAttributes()->all();
            try {
                $scheduler->preview($event, $options);
                $this->fail('Conflicting fixed group turns must require review.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('Saved matches conflict with the programme order', $exception->getMessage());
            }
            $this->assertSame($before, OrderOfPlay::orderBy('id')->get()->map->getAttributes()->all());
            return;
        }
        $preview = $scheduler->preview($event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $plannedCount = $selected->count() - ($savedFixture ? 1 : 0);
        $this->assertCount($plannedCount, $preview['matches']);
        if ($savedFixture) {
            foreach ($preview['matches'] as $row) {
                $fixture = $fixtures->firstWhere('id', $row['fixture_id']);
                if ((int) $fixture->round === 1 && $fixture->draw_id !== $draws[2]->id) {
                    $this->assertGreaterThanOrEqual('2026-09-10 09:15:00', $row['scheduled_at']);
                }
            }
        }
        $actual = collect($preview['matches'])->sortBy('scheduled_at')->map(function ($row) use ($fixtures) {
            $fixture = $fixtures->firstWhere('id', $row['fixture_id']);
            return $fixture->round.':'.$fixture->draw_id;
        })->unique()->values()->all();
        $expected = [];
        foreach (range(1, $partial ? 1 : 7) as $round) {
            $expected[] = $round.':'.$draws[2]->id;
            if ($round <= 3) foreach ([$draws[0], $draws[1]] as $draw) $expected[] = $round.':'.$draw->id;
        }
        $this->assertSame($expected, $actual);
        // Programme lineage changes the ordering contract and must invalidate a saved preview.
        $plan->update(['applied_at' => null]);
        $this->assertNotSame($preview['revision'], $scheduler->preview($event, $options)['revision']);
        $plan->update(['applied_at' => now()]);
        $this->assertSame($plannedCount, $scheduler->apply($event, $options, $preview['revision'])['count']);
        $this->assertDatabaseCount('order_of_plays', $selected->count());
        $this->assertDatabaseCount('fixtures', 40);
        if ($savedFixture) $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $savedFixture->id, 'time' => '2026-09-10 09:00:00']);
    }

    public function test_cohorts_are_scoped_to_applied_event_plan_and_category(): void
    {
        $event = Event::factory()->create();
        $foreign = Event::factory()->create();
        $first = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $second = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draws = collect([$first, $first, $second, $second])->map(fn ($category) => Draw::factory()->create([
            'event_id' => $event->id, 'category_event_id' => $category->id]));
        $attributes = ['actor_id' => User::factory()->create()->id, 'fingerprint' => str_repeat('0', 64),
            'proposal' => [], 'expires_at' => now()->addMinutes(30)];
        CtrDrawPlan::create($attributes + ['token' => (string) Str::uuid(), 'event_id' => $foreign->id,
            'draw_ids' => $draws->pluck('id')->all(), 'applied_at' => now()]);
        CtrDrawPlan::create($attributes + ['token' => (string) Str::uuid(), 'event_id' => $event->id,
            'draw_ids' => [$draws[3]->id], 'applied_at' => null]);
        $plan = CtrDrawPlan::create($attributes + ['token' => (string) Str::uuid(), 'event_id' => $event->id,
            'draw_ids' => $draws->take(3)->pluck('id')->all(), 'applied_at' => now()]);
        $method = new \ReflectionMethod(EventVenueScheduleService::class, 'ctrProgrammeCohorts');
        $map = $method->invoke(app(EventVenueScheduleService::class), $event, $draws);
        $this->assertSame($map[$draws[0]->id], $map[$draws[1]->id]);
        $this->assertNotSame($map[$draws[0]->id], $map[$draws[2]->id]);
        $this->assertArrayNotHasKey($draws[3]->id, $map);
        $this->assertStringContainsString('ctr:'.$plan->id.':', $map[$draws[0]->id]);
    }
}
