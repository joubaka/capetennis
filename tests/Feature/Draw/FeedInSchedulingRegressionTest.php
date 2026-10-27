<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\ScheduleAvailability;
use App\Models\{Category, CategoryEvent, Draw, Event, OrderOfPlay, Registration, Venue};
use App\Services\Draw\{FlexibleMonradCompiler, FlexibleMonradService};
use App\Services\Scheduling\EventVenueScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedInSchedulingRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        // Anonymized topology of U/19 Interpro event 255 / draw 1519: sixteen
        // opening entrants and all seven base-position challenge pairs.
        $event = Event::factory()->create(['name' => 'U/19 Interpro scheduling regression', 'start_date' => '2026-10-30', 'end_date' => '2026-11-01']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'Under 19 Boys'])->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'drawName' => 'U/19 Boys feed-in', 'gender' => null]);
        $draw->settings()->create(['workflow' => 'feed_in_monrad', 'playoff_config' => ['challenge_playoffs' => true, 'challenge_pairs' => [2, 4, 6, 8, 10, 12, 14]]]);
        $venue = Venue::forceCreate(['name' => 'Isolated Interpro test courts']);
        $draw->venues()->attach($venue->id, ['num_courts' => 8]);
        $registrations = Registration::factory()->count(16)->create();
        $slots = [];
        foreach ($registrations as $i => $registration) {
            $registration->categoryEvents()->attach($category->id, ['status' => 'registered', 'payment_status_id' => 1]);
            $path = strtr(str_pad(decbin($i), 4, '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = ['type' => 'player', 'id' => $registration->id];
        }
        $compiler = app(FlexibleMonradService::class);
        $compiler->save($draw, ['size' => 16, 'slots' => $slots], 0);
        $record = $compiler->generate($draw, 1);
        $options = ['start' => '2026-10-30 08:00:00', 'end' => '2026-10-30 18:00:00', 'duration' => 30,
            'player_rest' => 20, 'court_gap' => 0, 'wave_minutes' => 30, 'draw_ids' => [$draw->id]];
        return [$event, $draw->fresh(), $venue, $registrations, $record, $options];
    }

    public function test_preserved_unresolved_challenges_do_not_delay_their_independent_sibling(): void
    {
        [$event, $draw, , , $record, $options] = $this->scenario();
        $service = app(EventVenueScheduleService::class);
        $initial = $service->preview($event, $options);
        $this->assertCount(43, $initial['matches']);
        $this->assertSame([], $initial['unscheduled']);
        $targetId = $record->fixture_map['challenge_2'];
        $target = collect($initial['matches'])->firstWhere('fixture_id', $targetId);
        foreach ($initial['matches'] as $row) {
            if ($row['fixture_id'] === $targetId) continue;
            OrderOfPlay::create(['fixture_id' => $row['fixture_id'], 'draw_id' => $draw->id, 'venue_id' => $row['venue_id'],
                'court' => $row['court'], 'time' => $row['scheduled_at'], 'duration_minutes' => 30, 'gap_minutes' => 0]);
        }
        $before = OrderOfPlay::orderBy('id')->get()->map->getAttributes()->all();
        $incremental = $service->preview($event, $options);
        $this->assertSame([], $incremental['unscheduled']);
        $this->assertCount(1, $incremental['matches']);
        $this->assertSame($targetId, $incremental['matches'][0]['fixture_id']);
        $this->assertSame($target['scheduled_at'], $incremental['matches'][0]['scheduled_at']);
        $this->assertSame($before, OrderOfPlay::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertFalse((bool) $draw->fresh()->oop_published);
    }

    public function test_loaded_flexible_bookings_still_protect_other_draws_and_the_same_court(): void
    {
        [, $draw, $venue, $registrations, $record] = $this->scenario();
        OrderOfPlay::create(['fixture_id' => $record->fixture_map['main_final'], 'draw_id' => $draw->id,
            'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-10-30 10:00:00', 'duration_minutes' => 30]);
        $calendar = ScheduleAvailability::load([$venue->id], $registrations->pluck('id')->all(), [], null, 20);
        $start = Carbon::parse('2026-10-30 10:00:00');
        $players = $registrations->pluck('id')->all();
        $this->assertSame('10:00', $calendar->nextAvailableForMatch($start, 30, 50, $venue->id, '2', $players, 'flexible-draw-'.$draw->id)->format('H:i'));
        $this->assertSame('10:30', $calendar->nextAvailableForMatch($start, 30, 50, $venue->id, '1', $players, 'flexible-draw-'.$draw->id)->format('H:i'));
        $this->assertSame('10:50', $calendar->nextAvailableForMatch($start, 30, 50, $venue->id, '2', $players, 'flexible-draw-'.($draw->id + 1))->format('H:i'));
        $this->assertSame('10:50', $calendar->nextAvailableForMatch($start, 30, 50, $venue->id, '2', $players)->format('H:i'));
    }

    public function test_interpro_feed_in_preview_honours_every_feeder_and_challenge_prerequisite(): void
    {
        [$event, $draw, , , $record, $options] = $this->scenario();
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $this->assertCount(43, $preview['matches']);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertSame([1, 2, 3, 4, 5, 6], $service->availableDrawRounds(collect([$draw]), true)[$draw->id]);
        $planned = collect($preview['matches'])->keyBy('fixture_id');
        foreach (app(FlexibleMonradService::class)->state($draw)['matches'] as $match) {
            foreach (FlexibleMonradCompiler::dependencies($match) as $key) {
                $feeder = $planned[$record->fixture_map[$key]];
                $this->assertGreaterThanOrEqual(strtotime($feeder['scheduled_at']) + 50 * 60, strtotime($planned[$match['id']]['scheduled_at']));
            }
        }
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->assertFalse((bool) $draw->fresh()->oop_published);
    }
}
