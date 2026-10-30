<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Fixture, Registration};
use App\Services\Scheduling\PlayoffScheduleReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayoffScheduleReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_and_partially_prepared_stages_remain_visible_without_creating_fixtures(): void
    {
        $draw = Draw::factory()->create();
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'playoff_config' => [
            ['slug' => 'MAIN', 'name' => 'Main', 'positions' => [1], 'enabled' => true],
            ['slug' => 'PLATE', 'name' => 'Plate', 'positions' => [2], 'enabled' => true],
            ['slug' => 'OFF', 'name' => 'Disabled', 'positions' => [3], 'enabled' => false],
        ]]);
        $service = app(PlayoffScheduleReadinessService::class);
        $pending = $service->pending(collect([$draw]));
        $this->assertSame(['Main', 'Plate'], $pending->sole()['missing_stages']);
        $this->assertFalse($pending->sole()['partly_prepared']);
        $this->assertSame(0, $draw->drawFixtures()->count());
        Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN']);
        $pending = $service->pending(collect([$draw]));
        $this->assertSame(['Plate'], $pending->sole()['missing_stages']);
        $this->assertTrue($pending->sole()['partly_prepared']);
        Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'PLATE']);
        $this->assertTrue($service->pending(collect([$draw]))->isEmpty());
        $this->assertSame(2, $draw->drawFixtures()->count());
    }

    public function test_unused_positions_are_dormant_but_existing_stages_are_preserved(): void
    {
        $draw = Draw::factory()->create();
        $group = $draw->groups()->create(['name' => 'A']);
        $group->registrations()->sync(Registration::factory()->count(2)->create()->modelKeys());
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'playoff_config' => [
            ['slug' => 'MAIN', 'name' => 'Main', 'positions' => [1], 'enabled' => true],
            ['slug' => 'FUTURE', 'name' => 'Unused', 'positions' => [3], 'enabled' => true],
        ]]);
        $service = app(PlayoffScheduleReadinessService::class);
        $this->assertSame(['Main'], $service->pending(collect([$draw]))->sole()['missing_stages']);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'FUTURE']);
        $this->assertSame(['Main'], $service->pending(collect([$draw]))->sole()['missing_stages']);
        $this->assertDatabaseHas('fixtures', ['id' => $fixture->id]);
    }
}
