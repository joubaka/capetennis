<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Fixture;
use App\Models\OrderOfPlay;
use App\Models\Venue;
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrialPublicDrawSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('ct_testing', DB::connection()->getDatabaseName());
    }

    public static function families(): array
    {
        return ['individual' => [EventType::INDIVIDUAL], 'team' => [EventType::TEAM]];
    }

    #[DataProvider('families')]
    public function test_trials_share_only_published_draws_and_snapshot_venues_and_times(int $family): void
    {
        DB::table('eventtypes')->updateOrInsert(['id' => 203], ['name' => 'Trial sharing', 'type' => $family, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => 203]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'drawName' => 'Public trial draw']);
        $privateDraw = Draw::factory()->create(['event_id' => $event->id, 'published' => false, 'drawName' => 'Private trial draw']);
        $foreignDraw = Draw::factory()->create(['published' => true, 'drawName' => 'Foreign trial draw']);
        $venue = Venue::forceCreate(['name' => 'Published trial courts']);
        $privateVenue = Venue::forceCreate(['name' => 'Private revised courts']);
        $event->venues()->attach([$venue->id => ['num_courts' => 2], $privateVenue->id => ['num_courts' => 2]]);
        foreach ([$draw, $privateDraw, $foreignDraw] as $item) {
            $fixture = Fixture::factory()->create(['draw_id' => $item->id]);
            $slot = OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $item->id, 'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-10-30 09:15:00']);
            if ($item->is($draw)) {
                $publicSlot = $slot;
            }
        }
        $drawUrl = route('frontend.fixtures.index', $draw);
        $venueUrl = route('fixtures.venue', [$event->id, $venue->id]);
        $orderUrl = route('fixtures.order', [$event->id, $venue->id, '2026-10-30']);
        $this->get(route('events.show', $event))->assertOk()->assertSee($drawUrl, false)
            ->assertDontSee('Draws and match times')
            ->assertDontSee('class="card event-draws-card mb-4"', false)
            ->assertDontSee('Private trial draw')->assertDontSee('Foreign trial draw')
            ->assertDontSee('Published trial courts')->assertDontSee('09:15');
        $this->get(route('frontend.fixtures.index', $privateDraw))->assertForbidden();
        $this->get($drawUrl)->assertOk()->assertViewHas('fixtures', fn ($fixtures) => $fixtures->count() === 1)->assertDontSee('09:15');

        $publication = app(SchedulePublicationService::class);
        $publication->publish($event, ['draw_id' => $draw->id]);
        $publicSlot->update(['venue_id' => $privateVenue->id, 'time' => '2026-10-31 16:45:00']);
        $this->get(route('events.show', $event))->assertOk()->assertSee($venueUrl, false)->assertSee($orderUrl, false)
            ->assertSee('Friday from 09:15')->assertDontSee('Private revised courts')->assertDontSee('16:45');
        foreach ([$drawUrl, $venueUrl, $orderUrl] as $url) {
            $this->get($url)->assertOk()->assertSee('09:15')->assertSee('Published trial courts')
                ->assertDontSee('16:45')->assertDontSee('Private revised courts')
                ->assertViewHas('fixtures', fn ($fixtures) => $fixtures->count() === 1);
        }
        $this->get(route('fixtures.order', [$event->id, $venue->id, 'all', 'draw_id' => $foreignDraw->id]))->assertNotFound();
        $publication->hide($event, ['draw_id' => $draw->id]);
        $this->get(route('events.show', $event))->assertOk()->assertSee($drawUrl, false)->assertDontSee($venueUrl, false)->assertDontSee('09:15');
        foreach ([$venueUrl, $orderUrl] as $url) {
            $this->get($url)->assertOk()->assertViewHas('fixtures', fn ($fixtures) => $fixtures->isEmpty())->assertDontSee('09:15')->assertDontSee('16:45');
        }
        $draw->update(['published' => false]);
        $this->get(route('events.show', $event))->assertOk()->assertDontSee('Public trial draw');
        $this->get($drawUrl)->assertForbidden();
    }
}
