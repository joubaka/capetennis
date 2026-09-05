<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\DrawSetting;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawCompetitionRulesGateTest extends TestCase
{
    use RefreshDatabase;

    private Draw $draw;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create(['eventType' => 6]);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $this->admin->id]);
        $this->draw = Draw::factory()->create([
            'event_id' => $event->id,
            'published' => true,
            'locked' => false,
        ]);
        $this->draw->settings()->create([
            'workflow' => 'round_robin_playoffs',
            'num_sets' => 1,
            'notes' => ['round_robin' => 'Original rules'],
        ]);

        $this->actingAs($this->admin);
    }

    public function test_published_draw_allows_match_format_and_rules_before_any_result(): void
    {
        $this->postJson(route('backend.draw.update-settings', $this->draw), ['num_sets' => 3])
            ->assertOk();
        $this->postJson(route('backend.draw.update-notes', $this->draw), [
            'notes' => ['round_robin' => 'Sudden death at deuce'],
            'schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_FULL,
        ])->assertOk();
        $this->postJson(route('backend.draw.update-playoff-config', $this->draw), [
            'playoff_config' => [[
                'name' => 'Main playoff',
                'slug' => 'main',
                'size' => 4,
                'positions' => [1, 2],
                'enabled' => true,
            ]],
        ])->assertOk();

        $settings = $this->draw->fresh()->settings;
        $this->assertSame(3, $settings->num_sets);
        $this->assertSame('Sudden death at deuce', $settings->notes['round_robin']);
        $this->assertSame('main', $settings->playoff_config[0]['slug']);
    }

    public function test_first_result_locks_match_format_rules_and_playoff_configuration(): void
    {
        $otherDraw = Draw::factory()->create(['event_id' => $this->draw->event_id]);
        $fixture = Fixture::factory()->create(['draw_id' => $otherDraw->id]);
        FixtureResult::factory()->create(['fixture_id' => $fixture->id]);

        $this->postJson(route('backend.draw.update-settings', $this->draw), ['num_sets' => 3])
            ->assertForbidden();
        $this->postJson(route('backend.draw.update-notes', $this->draw), [
            'notes' => ['round_robin' => 'Changed rules'],
            'schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_FULL,
        ])->assertForbidden();
        $this->postJson(route('backend.draw.update-playoff-config', $this->draw), [
            'playoff_config' => [[
                'name' => 'Changed playoff',
                'slug' => 'changed',
                'size' => 4,
                'positions' => [1, 2],
                'enabled' => true,
            ]],
        ])->assertForbidden();

        $settings = $this->draw->fresh()->settings;
        $this->assertSame(1, $settings->num_sets);
        $this->assertSame('Original rules', $settings->notes['round_robin']);
        $this->assertNull($settings->playoff_config);
    }
}
