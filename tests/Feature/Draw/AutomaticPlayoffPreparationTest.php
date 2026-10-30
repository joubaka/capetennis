<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, Registration, User, Venue};
use App\Services\Draw\AutomaticPlayoffPreparationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutomaticPlayoffPreparationTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(bool $configured = true): Draw
    {
        $event = Event::factory()->create(['eventType' => 6]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => false, 'published' => false, 'oop_published' => false]);
        foreach (['A', 'B', 'C', 'D'] as $index => $name) {
            $group = $draw->groups()->create(['name' => $name]);
            $registrations = Registration::factory()->count(2)->create();
            $group->registrations()->sync($registrations->modelKeys());
            foreach ($registrations as $registration) $draw->registrations()->attach($registration->id, ['box_number' => $index + 1]);
        }
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'playoff_config' => $configured ? $this->config() : []]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $user->id]);
        $this->actingAs($user);
        return $draw;
    }

    private function config(): array
    {
        return [
            ['slug' => 'MAIN', 'name' => 'Main', 'size' => 4, 'positions' => [1], 'enabled' => true],
            ['slug' => 'PLATE', 'name' => 'Plate', 'size' => 4, 'positions' => [2], 'enabled' => true],
        ];
    }

    public function test_direct_fixture_creation_also_prepares_configured_placeholders(): void
    {
        $draw = $this->scenario();
        $this->postJson(route('admin.draws.generateRoundRobin', $draw))->assertOk()->assertJsonPath('prepared_playoffs', 8);
        $this->assertSame(4, $draw->drawFixtures()->where('stage', 'RR')->count());
        $this->assertSame(8, $draw->drawFixtures()->where('stage', '!=', 'RR')->count());
    }

    public function test_rebuild_creates_all_configured_brackets_with_unresolved_qualifiers_and_no_bookings(): void
    {
        $draw = $this->scenario();
        $response = $this->postJson(route('backend.draw.regenerate-rr', $draw))->assertOk();
        $this->assertSame(4, $draw->drawFixtures()->where('stage', 'RR')->count());
        $playoffs = $draw->drawFixtures()->where('stage', '!=', 'RR')->get();
        $this->assertSame(8, $playoffs->count());
        $response->assertJsonPath('fixture_count', 12);
        foreach (['MAIN', 'PLATE'] as $stage) {
            $opening = $playoffs->where('stage', $stage)->where('round', 1);
            $this->assertCount(2, $opening);
            foreach ($opening as $fixture) {
                $this->assertNull($fixture->registration1_id);
                $this->assertNull($fixture->registration2_id);
                $this->assertNotNull($fixture->registration1_source_group_id);
                $this->assertNotNull($fixture->registration2_source_group_id);
            }
        }
        $before = $playoffs->modelKeys();
        $this->assertSame(0, app(AutomaticPlayoffPreparationService::class)->prepare($draw));
        $this->assertSame($before, $draw->drawFixtures()->where('stage', '!=', 'RR')->pluck('id')->all());
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }

    public function test_configuration_saved_after_rr_generation_prepares_placeholders(): void
    {
        $draw = $this->scenario(false);
        $this->postJson(route('backend.draw.regenerate-rr', $draw))->assertOk();
        $this->assertSame(4, $draw->drawFixtures()->count());
        $this->postJson(route('backend.draw.update-playoff-config', $draw), ['playoff_config' => $this->config()])->assertOk();
        $this->assertSame(8, $draw->drawFixtures()->where('stage', '!=', 'RR')->count());
    }

    public function test_incomplete_configuration_keeps_rr_and_manual_fallback_without_partial_brackets(): void
    {
        $draw = $this->scenario();
        $config = $this->config();
        $config[1]['size'] = 8;
        $draw->settings()->update(['playoff_config' => $config]);
        $this->postJson(route('backend.draw.regenerate-rr', $draw))->assertOk();
        $this->assertSame(4, $draw->drawFixtures()->count());
        $this->assertSame(0, $draw->drawFixtures()->where('stage', '!=', 'RR')->count());
    }

    public function test_auto_preparation_preserves_locked_published_and_recorded_draws(): void
    {
        $draw = $this->scenario();
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'match_status' => 0]);
        foreach (['locked', 'published', 'oop_published'] as $flag) {
            $draw->update([$flag => true]);
            $this->assertSame(0, app(AutomaticPlayoffPreparationService::class)->prepare($draw));
            $draw->update([$flag => false]);
        }
        $fixture->update(['match_status' => 4]);
        // A retirement is protected even when legacy data has no set rows.
        $this->assertSame(0, app(AutomaticPlayoffPreparationService::class)->prepare($draw));
        $this->assertSame(1, $draw->drawFixtures()->count());
    }

    public function test_config_edits_replace_only_unbooked_placeholder_graph_and_keep_rr(): void
    {
        $draw = $this->scenario();
        $this->postJson(route('backend.draw.regenerate-rr', $draw))->assertOk();
        $rr = $draw->drawFixtures()->where('stage', 'RR')->pluck('id')->all();
        $old = $draw->drawFixtures()->where('stage', '!=', 'RR')->pluck('id')->all();
        $config = $this->config();
        $config[0]['positions'] = [2];
        $config[1]['positions'] = [1];
        $this->postJson(route('backend.draw.update-playoff-config', $draw), ['playoff_config' => $config])->assertOk();
        $this->assertSame($rr, $draw->drawFixtures()->where('stage', 'RR')->pluck('id')->all());
        $this->assertSame(0, $draw->drawFixtures()->whereIn('id', $old)->count());
        $main = $draw->drawFixtures()->where('stage', 'MAIN')->where('round', 1)->firstOrFail();
        $this->assertSame(2, (int) $main->registration1_source_position);
        $before = $draw->drawFixtures()->pluck('id')->all();
        $config[0]['size'] = 8;
        $this->postJson(route('backend.draw.update-playoff-config', $draw), ['playoff_config' => $config])->assertUnprocessable();
        $this->assertSame($before, $draw->drawFixtures()->pluck('id')->all());
        $this->assertSame(4, $draw->settings()->first()->playoff_config[0]['size']);
    }

    public function test_config_edit_with_saved_playoff_time_is_rejected_without_changing_graph_or_settings(): void
    {
        $draw = $this->scenario();
        $this->postJson(route('backend.draw.regenerate-rr', $draw))->assertOk();
        $playoff = $draw->drawFixtures()->where('stage', 'MAIN')->firstOrFail();
        $venue = Venue::forceCreate(['name' => 'Placeholder guard court']);
        $booking = OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $playoff->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-10-31 10:00:00']);
        $config = $this->config();
        $config[0]['positions'] = [2];
        $config[1]['positions'] = [1];
        $this->postJson(route('backend.draw.update-playoff-config', $draw), ['playoff_config' => $config])->assertUnprocessable();
        $this->assertDatabaseHas('fixtures', ['id' => $playoff->id]);
        $this->assertDatabaseHas('order_of_plays', ['id' => $booking->id]);
        $this->assertSame([1], $draw->settings()->first()->playoff_config[0]['positions']);
    }
}
