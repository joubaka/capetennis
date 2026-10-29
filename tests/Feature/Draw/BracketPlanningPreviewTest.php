<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, EventType, Fixture, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BracketPlanningPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function planningDraw(): Draw
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Interprovincial Trials', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => false, 'oop_published' => false]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'playoff_config' => [
            ['slug' => 'main', 'name' => 'Main', 'enabled' => true, 'size' => 4, 'positions' => [1]],
            ['slug' => 'plate', 'name' => 'Plate', 'enabled' => true, 'size' => 4, 'positions' => [2]],
        ]]);
        return $draw;
    }

    public function test_trials_preview_uses_saved_group_qualifiers_without_creating_records_or_publication(): void
    {
        $draw = $this->planningDraw();
        foreach (['A', 'B', 'C', 'D'] as $name) $draw->groups()->create(['name' => $name]);
        $before = $draw->fresh()->getAttributes();
        $settings = $draw->settings->getAttributes();
        $url = route('backend.draw.main-bracket', $draw);
        $page = $this->get($url)->assertOk()->assertSee('Provisional playoff preview')->assertSee('Winner Group A')->assertSee('Runner-up Group B');
        $page->assertSee('Winner match 1')->assertSee('Loser match 1');
        preg_match('/viewBox="0 0 (\d+) (\d+)"/', $page->getContent(), $bounds);
        preg_match_all('/<line[^>]+x2="([\d.]+)"/', $page->getContent(), $ends);
        $this->assertLessThanOrEqual((int) $bounds[1] - 40, max(array_map('floatval', $ends[1])), 'Every bracket exit line needs a printable right gutter.');
        $page->assertDontSee('width="1600"', false)->assertDontSee('bracket-score-btn')->assertDontSee('data-fixture-id=', false);
        if (getenv('DRAW_GUIDE_SNAPSHOT_DIR')) file_put_contents(getenv('DRAW_GUIDE_SNAPSHOT_DIR').'/playoff-preview.html', $page->getContent());
        $this->get($url)->assertOk();
        $this->assertSame(0, Fixture::count());
        $this->assertSame(4, $draw->groups()->count());
        $this->assertSame($before, $draw->fresh()->getAttributes());
        $this->assertSame($settings, $draw->settings->fresh()->getAttributes());
        $this->assertSame(0, DB::table('published_schedule_assignments')->count());
        $this->assertSame(0, DB::table('order_of_plays')->count());
    }

    public function test_eight_assigned_players_use_two_brackets_and_keep_the_future_template(): void
    {
        $draw = $this->planningDraw();
        $config = [
            ['slug' => 'main', 'name' => 'Main', 'enabled' => true, 'size' => 4, 'positions' => [1, 2]],
            ['slug' => 'plate', 'name' => 'Plate', 'enabled' => true, 'size' => 4, 'positions' => [3, 4]],
            ['slug' => 'cons', 'name' => 'Future third bracket', 'enabled' => true, 'size' => 4, 'positions' => [5, 6]],
        ];
        $draw->settings->update(['playoff_config' => $config]);
        $seeds = [];
        foreach (['A', 'B'] as $name) {
            $group = $draw->groups()->create(['name' => $name]);
            foreach (\App\Models\Registration::factory()->count(4)->create() as $index => $registration) {
                $group->registrations()->attach($registration->id, ['seed' => $index + 1]);
                $seeds[$name.($index + 1)] = $registration->id;
            }
        }
        $this->get(route('backend.draw.main-bracket', $draw))->assertOk()
            ->assertSee('Main')->assertSee('Plate')->assertDontSee('Future third bracket');
        $this->assertSame(0, Fixture::count());
        $this->assertEquals($config, $draw->settings->fresh()->playoff_config);
        $method = new \ReflectionMethod(\App\Http\Controllers\Backend\RoundRobinController::class, 'generateDynamicPlayoffFixtures');
        $method->invoke(app(\App\Http\Controllers\Backend\RoundRobinController::class), $draw->fresh(), $config, $seeds);
        $this->assertSame(['MAIN', 'PLATE'], Fixture::where('draw_id', $draw->id)->distinct()->orderBy('stage')->pluck('stage')->all());
        $this->assertSame(8, Fixture::where('draw_id', $draw->id)->count());
        // An existing future-stage fixture must remain visible, never be discarded by a preview.
        $existing = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'CONS', 'round' => 1]);
        $shown = (new \App\Services\DynamicBracketEngine($draw->fresh()))->build();
        $this->assertContains('cons', array_column($shown['brackets'], 'slug'));
        $this->assertDatabaseHas('fixtures', ['id' => $existing->id]);
        // A position present in only one group is still active; retain its missing source as a bye.
        $draw->groups()->where('name', 'B')->first()->registrations()->detach($seeds['B4']);
        $this->assertFalse(app(\App\Services\Draw\PlayoffParticipationService::class)->isDormant($draw->fresh(), ['slug' => 'partial', 'positions' => [4]]));
        $this->assertEquals($config, $draw->settings->fresh()->playoff_config);
    }

    public function test_full_qualifier_wording_and_progression_labels_have_wider_preview_slots(): void
    {
        $draw = $this->planningDraw();
        foreach (['A', 'B'] as $name) $draw->groups()->create(['name' => $name]);
        $draw->settings->update(['playoff_config' => [
            ['slug' => 'main', 'name' => 'Runner-up qualifiers', 'enabled' => true, 'size' => 4, 'positions' => [2]],
            ['slug' => 'plate', 'name' => 'Fourth-position qualifiers', 'enabled' => true, 'size' => 4, 'positions' => [4]],
        ]]);
        $page = $this->get(route('backend.draw.main-bracket', $draw))->assertOk();
        $page->assertSee('>Runner-up Group A', false)->assertSee('>Runner-up Group B', false)
            ->assertSee('>Group A position 4', false)->assertSee('>Group B position 4', false)
            ->assertSee('>Winner match 1', false)->assertSee('>Loser match 1', false)
            ->assertDontSee('Runner-up Gro...')->assertDontSee('Group A posit...');
        $this->assertEquals(266, $page->viewData('svgData')['brackets'][0]['rounds'][1][0]['width']);
        if (getenv('DRAW_GUIDE_SNAPSHOT_DIR')) file_put_contents(getenv('DRAW_GUIDE_SNAPSHOT_DIR').'/playoff-label-preview.html', $page->getContent());
    }

    public function test_single_group_repeated_order_preset_remains_previewable(): void
    {
        $draw = $this->planningDraw();
        $draw->groups()->create(['name' => 'A']);
        $preset = \App\Models\DrawSetting::getPresetTemplates()['1_group_4s']['config'];
        $preset[0]['group_order'] = ['A', 'A'];
        $draw->settings->update(['playoff_config' => $preset]);
        $this->get(route('backend.draw.main-bracket', $draw))->assertOk()->assertSee('<svg', false)
            ->assertDontSee('Choose and save the playoff format');
        $this->assertEquals($preset, $draw->settings->fresh()->playoff_config);
    }

    public function test_no_groups_undefined_settings_and_round_robin_only_get_clear_next_actions(): void
    {
        $draw = $this->planningDraw();
        $url = route('backend.draw.main-bracket', $draw);
        $this->get($url)->assertOk()->assertSee('Create groups before previewing')->assertDontSee('<svg', false);
        $draw->groups()->create(['name' => 'A']);
        foreach ([[], [['enabled' => true, 'size' => 'bad', 'positions' => [1]]], [['enabled' => true, 'size' => 4, 'positions' => 'bad']], [['enabled' => true, 'size' => 4, 'positions' => [1], 'group_order' => 'bad']], [['enabled' => true, 'size' => 4, 'positions' => ['first' => 1]]], [['enabled' => true, 'size' => 4, 'positions' => [1], 'slug' => []]]] as $config) {
            $draw->settings->update(['playoff_config' => $config]);
            $this->get($url)->assertOk()->assertSee('Choose and save the playoff format')->assertDontSee('<svg', false);
        }
        $draw->settings->update(['workflow' => 'round_robin']);
        $this->get($url)->assertOk()->assertSee('No playoff bracket is needed')->assertSee('Open standings');
        $this->assertSame(0, Fixture::count());
    }

    public function test_virtual_odd_group_seeding_and_custom_order_match_generation_without_resolving_players(): void
    {
        $draw = $this->planningDraw();
        foreach (['A', 'B', 'C'] as $name) $draw->groups()->create(['name' => $name]);
        $draw->settings->update(['playoff_config' => [
            ['slug' => 'main', 'name' => 'Main', 'enabled' => true, 'size' => 8, 'positions' => [1, 2], 'group_order' => ['C', 'A', 'B']],
            ['enabled' => false, 'slug' => []],
        ]]);
        $data = (new \App\Services\DynamicBracketEngine($draw->fresh()))->build();
        $pairs = collect($data['brackets'][0]['rounds'][1])->map(fn ($match) => [$match['seedLabel1'], $match['seedLabel2']])->all();
        // Canonical generation rotates the second qualifying position by one
        // group for three groups, before applying its standard eight-slot tree.
        $this->assertSame([['C1', null], ['B2', 'A2'], ['A1', null], ['C2', 'B1']], $pairs);
        $this->get(route('backend.draw.main-bracket', $draw))->assertOk()->assertSee('Winner Group C')->assertSee('Runner-up Group A');
        $this->assertSame(0, Fixture::count());
    }

    public function test_existing_trials_fixtures_keep_legacy_renderer_and_unrelated_admin_cannot_preview(): void
    {
        $draw = $this->planningDraw();
        Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 1]);
        $this->get(route('backend.draw.main-bracket', $draw))->assertOk()->assertViewIs('backend.draw.roundrobin.draw-svg');
        $other = User::factory()->create()->assignRole('admin');
        $this->actingAs($other)->get(route('backend.draw.main-bracket', $draw))->assertForbidden();
        $this->assertSame(1, Fixture::count());
    }
}
