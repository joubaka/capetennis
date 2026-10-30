<?php

namespace Tests\Feature\Draw;

use App\Http\Controllers\Backend\RoundRobinController;
use App\Models\Draw;
use App\Models\DrawSetting;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllPlayersPlayoffGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_assigned_player_gets_one_opening_playoff_slot_for_large_and_uneven_fields(): void
    {
        foreach ([[4, 4, 4, 4], [11, 11, 11, 11], [5, 4, 4, 4], [5], [5, 4]] as $counts) {
            $preset = DrawSetting::allPlayersPreset(count($counts), max($counts));
            $draw = Draw::factory()->create(['event_id' => Event::factory()->create()->id, 'locked' => false]);
            $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'boxes' => count($counts), 'playoff_config' => $preset['config']]);
            $seeds = [];
            $assigned = [];
            foreach ($counts as $index => $count) {
                $group = $draw->groups()->create(['name' => chr(65 + $index)]);
                foreach (Registration::factory()->count($count)->create() as $rank => $registration) {
                    $draw->registrations()->attach($registration->id);
                    $group->registrations()->attach($registration->id, ['seed' => $rank + 1]);
                    $seeds[$group->name.($rank + 1)] = $registration->id;
                    $assigned[] = $registration->id;
                }
            }
            $draw->load('groups.groupRegistrations', 'settings');
            // Exercise the ordinary workspace's generator, which permits missing qualifiers/byes.
            $generator = new \ReflectionMethod(RoundRobinController::class, 'generateDynamicPlayoffFixtures');
            $generator->invoke(app(RoundRobinController::class), $draw, $preset['config'], $seeds);
            $opening = $draw->drawFixtures()->where('round', 1)->get();
            $actual = $opening->flatMap(fn ($fixture) => [$fixture->registration1_id, $fixture->registration2_id])->filter()->all();
            sort($assigned);
            sort($actual);
            $this->assertSame($assigned, $actual);
            $this->assertCount(count($assigned), array_unique($actual));
            $this->assertSame(count($preset['config']), $opening->pluck('stage')->unique()->count());
            if (count(array_unique($counts)) > 1 || count($counts) === 1) {
                $this->assertTrue($opening->contains(fn ($fixture) => (bool) $fixture->registration1_id !== (bool) $fixture->registration2_id));
                $this->assertTrue($opening->contains(fn ($fixture) => $fixture->winner_registration !== null));
            }
        }
    }
}
