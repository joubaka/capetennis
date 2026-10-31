<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, DrawGroup, Event, Fixture, OrderOfPlay, Registration, Venue};
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoundRobinGroupRotationSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public static function unequalGroups(): array
    {
        return [
            'five versus four' => [['A' => 4, 'B' => 5], false, false],
            'four unequal groups' => [['A' => 4, 'B' => 5, 'C' => 4, 'D' => 5], false, false],
            'day batch' => [['A' => 4, 'B' => 5], true, false],
            'selected fixtures retain full group priority' => [['A' => 4, 'B' => 5], true, true],
        ];
    }

    #[DataProvider('unequalGroups')]
    public function test_larger_groups_go_first_in_each_round(array $sizes, bool $dayBatch, bool $partial): void
    {
        $event = Event::factory()->create(['eventType' => 6, 'start_date' => '2026-09-10', 'end_date' => '2026-09-10']);
        $venue = new Venue();
        $venue->forceFill(['name' => 'Unequal group courts'])->save();
        $event->venues()->attach($venue->id, ['num_courts' => 4]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => false, 'published' => false]);
        $draw->venues()->attach($venue->id, ['num_courts' => 4]);
        $draw->settings()->create(['workflow' => 'round_robin', 'score_format' => 'timed',
            'match_duration_minutes' => 15, 'changeover_minutes' => 0]);
        $groups = [];
        foreach ($sizes as $name => $size) {
            $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => $name, 'round_robin_phase' => 1]);
            $group->registrations()->attach(Registration::factory()->count($size)->create()->pluck('id'));
            $groups[$group->id] = $name;
        }
        app(\App\Domain\Draws\Services\RoundRobinGenerationService::class)->generate($draw);
        $fixtures = $draw->drawFixtures()->orderBy('id')->get();
        $selected = $partial ? $fixtures->filter(fn ($fixture) => (int) $fixture->round === 1)->values() : $fixtures;
        if ($partial) {
            // B has fewer selected matches than A but still has more group members.
            $remove = $selected->filter(fn ($fixture) => $groups[$fixture->draw_group_id] === 'B')->last()->id;
            $selected = $selected->reject(fn ($fixture) => $fixture->id === $remove)->values();
        }
        $options = ['start' => '2026-09-10 08:00:00', 'end' => '2026-09-10 18:00:00',
            'duration' => 15, 'court_gap' => 0, 'player_rest' => 15, 'wave_minutes' => 0];
        if ($dayBatch) $options += ['day_batch' => true, 'full_day_scope' => '2026-09-10',
            'draw_ids' => [$draw->id], 'draw_order' => [$draw->id], 'draw_order_mode' => 'sequential',
            'selected_fixture_keys' => $selected->map(fn ($fixture) => 'individual:'.$fixture->id)->all(),
            'draw_rounds' => [['draw_id' => $draw->id, 'rounds' => $partial ? [1] : [1, 2, 3, 4, 5]]],
            'venue_ids' => [$venue->id], 'day_court_labels' => [$venue->id => ['1', '2', '3', '4']]];
        $scheduler = app(EventVenueScheduleService::class);
        $preview = $scheduler->preview($event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertCount($selected->count(), $preview['matches']);
        $actual = collect($preview['matches'])->sortBy('scheduled_at')->map(function ($row) use ($fixtures, $groups) {
            $fixture = $fixtures->firstWhere('id', $row['fixture_id']);
            return $fixture->round.':'.$groups[$fixture->draw_group_id];
        })->unique()->values()->all();
        $expected = $partial ? ['1:B', '1:A'] : (count($sizes) === 2
            ? ['1:B', '1:A', '2:B', '2:A', '3:B', '3:A', '4:B', '5:B']
            : ['1:B', '1:D', '1:A', '1:C', '2:B', '2:D', '2:A', '2:C',
                '3:B', '3:D', '3:A', '3:C', '4:B', '4:D', '5:B', '5:D']);
        $this->assertSame($expected, $actual);
        $this->assertSame($selected->count(), $scheduler->apply($event, $options, $preview['revision'])['count']);
        $this->assertDatabaseCount('order_of_plays', $selected->count());
        $this->assertDatabaseCount('fixtures', $fixtures->count());
    }

    public static function rotations(): array
    {
        return ['two groups' => [2, false, false], 'four groups' => [4, false, false],
            'day batch' => [2, true, false], 'unequal groups with rest' => [2, false, true]];
    }

    #[DataProvider('rotations')]
    public function test_group_rounds_take_alternating_turns_even_when_all_groups_could_fit_on_courts(int $count, bool $dayBatch, bool $unequal): void
    {
        $event = Event::factory()->create(['eventType' => 6, 'start_date' => '2026-09-10', 'end_date' => '2026-09-10']);
        $venue = new Venue();
        $venue->forceFill(['name' => 'Rotation courts'])->save();
        $event->venues()->attach($venue->id, ['num_courts' => 4]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => false, 'published' => false]);
        $draw->venues()->attach($venue->id, ['num_courts' => 4]);
        $draw->settings()->create(['workflow' => 'round_robin', 'score_format' => 'timed',
            'match_duration_minutes' => 15, 'changeover_minutes' => 0]);
        $expected = [];
        $number = 1;
        // Insert groups backwards to prove rotation follows group names, not fixture IDs.
        foreach (array_reverse(range('A', chr(64 + $count))) as $name) {
            $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => $name, 'round_robin_phase' => 1]);
            $players = Registration::factory()->count(4)->create()->pluck('id')->all();
            foreach ([[[0, 3], [1, 2]], [[0, 2], [3, 1]], [[0, 1], [2, 3]]] as $round => $pairs) {
                if ($unequal && $name === 'B' && $round === 2) continue;
                foreach ($pairs as [$home, $away]) {
                    $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id,
                        'stage' => 'RR', 'round' => $round + 1, 'match_nr' => $number++, 'match_status' => 0,
                        'registration1_id' => $players[$home], 'registration2_id' => $players[$away]]);
                    $expected[$fixture->id] = 15 * ($round * $count + ord($name) - 65);
                }
            }
        }
        $played = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id,
            'stage' => 'RR', 'round' => 4, 'match_nr' => 99, 'match_status' => 2,
            'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id]);
        OrderOfPlay::create(['fixture_id' => $played->id, 'draw_id' => $draw->id,
            'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-09-10 12:00:00',
            'duration_minutes' => 15, 'gap_minutes' => 0]);
        $options = ['start' => '2026-09-10 08:00:00', 'end' => '2026-09-10 18:00:00',
            'duration' => 15, 'court_gap' => 0, 'player_rest' => $unequal ? 15 : 0, 'wave_minutes' => 0];
        if ($dayBatch) $options += ['day_batch' => true, 'full_day_scope' => '2026-09-10',
            'draw_ids' => [$draw->id], 'draw_order' => [$draw->id], 'draw_order_mode' => 'sequential',
            'selected_fixture_keys' => array_map(fn ($id) => 'individual:'.$id, array_keys($expected)),
            'draw_rounds' => [['draw_id' => $draw->id, 'rounds' => [1, 2, 3]]],
            'venue_ids' => [$venue->id], 'day_court_labels' => [$venue->id => ['1', '2', '3', '4']]];
        $scheduler = app(EventVenueScheduleService::class);
        $preview = $scheduler->preview($event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertCount(count($expected), $preview['matches']);
        foreach ($preview['matches'] as $row) {
            $this->assertSame(\Carbon\Carbon::parse('2026-09-10 08:00:00')->addMinutes($expected[$row['fixture_id']])->toDateTimeString(), $row['scheduled_at']);
        }
        $this->assertSame(count($expected), $scheduler->apply($event, $options, $preview['revision'])['count']);
        $this->assertDatabaseCount('order_of_plays', count($expected) + 1);
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $played->id, 'time' => '2026-09-10 12:00:00']);
        $this->assertDatabaseHas('fixtures', ['id' => $played->id, 'match_status' => 2]);
    }
}
