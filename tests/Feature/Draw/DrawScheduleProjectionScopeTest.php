<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, Venue};
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DrawScheduleProjectionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_draw_loads_only_its_own_schedule_snapshot(): void
    {
        $event = Event::factory()->create();
        $venue = Venue::forceCreate(['name' => 'Publication court']);
        $draws = Draw::factory()->count(3)->create(['event_id' => $event->id, 'published' => true]);
        $fixtures = $draws->map(function (Draw $draw, int $index) use ($venue) {
            $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
            OrderOfPlay::create([
                'fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id,
                'court' => (string) ($index + 1), 'time' => '2026-10-09 09:00:00',
            ]);

            return $fixture;
        });

        $schedule = app(SchedulePublicationService::class);
        $schedule->publish($event, []);
        $fixtures->first()->orderOfPlay()->update(['time' => '2026-10-09 15:00:00']);
        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            $this->get(route('frontend.fixtures.index', $draws->first()))
                ->assertOk()
                ->assertViewHas('fixtures', fn ($rows) => $rows->count() === 1
                    && $rows->first()->id === $fixtures->first()->id
                    && $rows->first()->orderOfPlay?->time === '2026-10-09 09:00:00');

            $snapshotReads = collect(DB::getQueryLog())->filter(fn ($query) =>
                str_starts_with($query['query'], 'select * from ')
                && str_contains($query['query'], 'published_schedule_assignments'));
            $this->assertCount(1, $snapshotReads);
            $this->assertSame([$event->id, $draws->first()->id], $snapshotReads->first()['bindings']);
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame($fixtures->pluck('id')->sort()->values()->all(),
            $schedule->publishedRows($event, includeParticipants: false)->pluck('fixture_id')->sort()->values()->all());
    }
}
