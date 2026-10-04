<?php

namespace Tests\Feature\TeamDraw;

use App\Domain\TeamDraw\TeamEventFormatDefinitionValidator;
use App\Models\{Draw, Event, EventType, TeamEventFormat, User};
use App\Services\TeamEventFormatPresets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamFormatPresetWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_presets_define_exact_six_and_eight_player_pairings(): void
    {
        foreach (app(TeamEventFormatPresets::class)->all() as $preset) {
            app(TeamEventFormatDefinitionValidator::class)->validate($preset);
            $size = $preset['max_roster_size'];
            $this->assertCount($size * 2 + $size / 2, $preset['rubbers']);
            foreach (range(1, $size) as $rank) {
                $this->assertSame([$rank], $preset['rubbers'][$rank - 1]['away_positions']);
                $this->assertSame([$rank % 2 ? $rank + 1 : $rank - 1], $preset['rubbers'][$size + $rank - 1]['away_positions']);
            }
            foreach (range(1, $size, 2) as $index => $rank) {
                $rubber = $preset['rubbers'][$size * 2 + $index];
                $this->assertSame([$rank, $rank + 1], $rubber['home_positions']);
                $this->assertSame($rubber['home_positions'], $rubber['away_positions']);
            }
        }
    }

    public function test_saving_customized_preset_is_event_scoped_and_preserves_existing_snapshots(): void
    {
        DB::table('eventtypes')->insert(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create(['eventType' => 3]);
        $other = Event::factory()->create(['eventType' => 3]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $oldFormat = TeamEventFormat::factory()->create(['event_id' => $event->id, 'is_default' => true]);
        $snapshot = ['id' => $oldFormat->id, 'rubbers' => [['sequence' => 1, 'home_positions' => [1], 'away_positions' => [1]]]];
        $draw = Draw::factory()->create(['event_id' => $event->id, 'team_event_format_id' => $oldFormat->id, 'team_format_snapshot' => $snapshot]);
        $preset = app(TeamEventFormatPresets::class)->all()['six_player'];
        $preset['name'] = 'Event customized six-player format';
        $preset['rubbers'][0]['away_positions'] = [3];
        $this->actingAs($admin)->get(route('backend.team-rules.edit', $event))->assertOk()->assertSee('six_player');
        $this->postJson(route('team-draw.formats.store', $other), $preset)->assertForbidden();
        $id = $this->postJson(route('team-draw.formats.store', $event), $preset)->assertCreated()->json('format.id');
        $saved = TeamEventFormat::findOrFail($id);
        $this->assertSame($event->id, $saved->event_id);
        $this->assertSame([3], $saved->rubbers->first()->away_positions);
        $this->assertTrue((bool) $oldFormat->fresh()->is_default);
        $this->assertFalse((bool) $saved->is_default);
        $this->assertSame($snapshot, $draw->fresh()->team_format_snapshot);
    }
}
