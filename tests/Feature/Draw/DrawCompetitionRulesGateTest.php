<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\TennisScoreFormat;
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
        $this->postJson(route('backend.draw.update-settings', $this->draw), [
            'score_format' => TennisScoreFormat::BEST_OF_3_TO_4,
        ])
            ->assertOk();
        $this->postJson(route('backend.draw.update-notes', $this->draw), [
            'notes' => ['round_robin' => 'Sudden death at deuce'],
            'notes_print' => ['round_robin' => false],
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
        $this->assertSame(TennisScoreFormat::BEST_OF_3_TO_4, $settings->score_format);
        $this->assertSame('Sudden death at deuce', $settings->notes['round_robin']);
        $this->assertFalse($settings->notes_print['round_robin']);
        $this->assertSame('main', $settings->playoff_config[0]['slug']);
    }

    private function playoffDraft(): array
    {
        return [
            ['name' => 'Saved main', 'slug' => 'main', 'size' => 4, 'positions' => [1, 2], 'enabled' => true, 'group_order' => ['B', 'A']],
            ['name' => 'Unused plate', 'slug' => 'plate', 'size' => 4, 'positions' => [], 'enabled' => false],
        ];
    }

    public function test_single_save_updates_scoring_and_full_playoff_draft_together(): void
    {
        $this->postJson(route('backend.draw.update-settings', $this->draw), [
            'score_format' => TennisScoreFormat::BEST_OF_3_TO_4,
            'playoff_config' => $this->playoffDraft(),
        ])->assertOk();

        $settings = $this->draw->fresh()->settings;
        $this->assertSame(TennisScoreFormat::BEST_OF_3_TO_4, $settings->score_format);
        $this->assertEquals($this->playoffDraft(), $settings->playoff_config);
        $this->assertDatabaseCount('draw_settings', 1);
    }

    public function test_existing_templates_keep_high_positions_and_repeated_group_order(): void
    {
        foreach (['1_group_4s', '4_groups_1to4'] as $key) {
            $templates = DrawSetting::getPresetTemplates();
            $this->postJson(route('backend.draw.update-settings', $this->draw), [
                'playoff_config' => $templates[$key]['config'],
                'preset_key' => $key,
            ])->assertOk();
            $this->assertEquals($templates[$key]['config'], $this->draw->fresh()->settings->playoff_config);
        }
    }

    public function test_invalid_playoff_draft_cannot_partially_save_scoring(): void
    {
        $draft = $this->playoffDraft();
        $draft[0]['positions'] = [];
        $this->postJson(route('backend.draw.update-settings', $this->draw), [
            'score_format' => TennisScoreFormat::BEST_OF_3_TO_4,
            'playoff_config' => $draft,
        ])->assertUnprocessable()->assertJsonValidationErrors('playoff_config.0.positions');
        $this->assertNull($this->draw->fresh()->settings->score_format);
        $this->assertDatabaseHas('draw_settings', ['draw_id' => $this->draw->id, 'playoff_config' => null]);
    }

    public function test_playoff_only_save_preserves_legacy_scoring_and_other_settings(): void
    {
        $this->postJson(route('backend.draw.update-settings', $this->draw), [
            'playoff_config' => $this->playoffDraft(),
        ])->assertOk();
        $settings = $this->draw->fresh()->settings;
        $this->assertNull($settings->score_format);
        $this->assertSame(1, $settings->num_sets);
        $this->assertSame(['round_robin' => 'Original rules'], $settings->notes);
    }

    public function test_saved_settings_populate_one_editor_and_read_only_preview(): void
    {
        $this->draw->settings->update(['playoff_config' => $this->playoffDraft(), 'boxes' => 2]);
        foreach (['A', 'B'] as $name) {
            $group = $this->draw->groups()->create(['name' => $name]);
            foreach (\App\Models\Registration::factory()->count(2)->create() as $index => $registration) {
                $this->draw->registrations()->attach($registration->id);
                $group->registrations()->attach($registration->id, ['seed' => $index + 1]);
            }
        }
        $response = $this->get(route('backend.draw.roundrobin.show', $this->draw))->assertOk()
            ->assertSee('Current setup')->assertSee('Change settings')->assertSee('Changes preview')
            ->assertSee('No changes.')->assertSee('Saved main')->assertSee('Unused plate')
            ->assertSee('View full setup')->assertSee('Save changes')
            ->assertSee('Show unused playoffs')->assertSee('Show future positions')
            ->assertDontSee('Save Settings')->assertDontSee('Save Playoff Config');
        $this->assertSame(1, substr_count($response->getContent(), 'id="btn-save-settings"'));
        // Empty disabled rows must not overwrite the draw-wide assignment total.
        $this->assertMatchesRegularExpression('/id="rr-overview-player-count">\s*4\s*<\/h3>/', $response->getContent());
        $response->assertDontSee('Choose only group positions that currently contain players before saving playoff changes.');
        if (getenv('DRAW_SETTINGS_SNAPSHOT')) {
            file_put_contents(storage_path('framework/testing/draw-settings-qa.html'), $response->getContent());
        }
    }

    public function test_combined_playoffs_reject_round_robin_only_draws_without_saving_scoring(): void
    {
        $this->draw->settings->update(['workflow' => 'round_robin']);
        $this->postJson(route('backend.draw.update-settings', $this->draw), [
            'score_format' => TennisScoreFormat::BEST_OF_3_TO_4,
            'playoff_config' => $this->playoffDraft(),
        ])->assertUnprocessable();
        $this->assertNull($this->draw->fresh()->settings->score_format);
    }

    public function test_combined_save_preserves_lock_and_event_authorization_gates(): void
    {
        $payload = ['score_format' => TennisScoreFormat::BEST_OF_3_TO_4, 'playoff_config' => $this->playoffDraft()];
        $this->draw->update(['locked' => true]);
        $this->postJson(route('backend.draw.update-settings', $this->draw), $payload)->assertForbidden();
        $this->draw->update(['locked' => false]);
        $otherAdmin = User::factory()->create()->assignRole('admin');
        $otherEvent = Event::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $otherEvent->id, 'user_id' => $otherAdmin->id]);
        $this->actingAs($otherAdmin)->postJson(route('backend.draw.update-settings', $this->draw), $payload)->assertForbidden();
        $this->assertNull($this->draw->fresh()->settings->score_format);
        $this->assertDatabaseHas('draw_settings', ['draw_id' => $this->draw->id, 'playoff_config' => null]);
    }

    public function test_first_result_locks_match_format_rules_and_playoff_configuration(): void
    {
        $otherDraw = Draw::factory()->create(['event_id' => $this->draw->event_id]);
        $fixture = Fixture::factory()->create(['draw_id' => $otherDraw->id]);
        FixtureResult::factory()->create(['fixture_id' => $fixture->id]);

        $this->postJson(route('backend.draw.update-settings', $this->draw), [
            'score_format' => TennisScoreFormat::BEST_OF_3_TO_4,
            'playoff_config' => $this->playoffDraft(),
        ])
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
        $this->assertNull($settings->score_format);
        $this->assertSame('Original rules', $settings->notes['round_robin']);
        $this->assertDatabaseHas('draw_settings', [
            'draw_id' => $this->draw->id,
            'playoff_config' => null,
        ]);
    }
}
