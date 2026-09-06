<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\StandingsService;
use App\Models\Draw;
use App\Models\DrawGroup;
use App\Models\DrawGroupRegistration;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverbergU10BSingleSetRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_patch_keeps_the_boys_first_set_removes_later_sets_and_configures_both_draws(): void
    {
        $event = Event::factory()->create(['id' => 233]);
        $boys = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'U/10B Boys']);
        $girls = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'u/10B Girls']);
        $boys->settings()->create(['num_sets' => 3, 'require_full_sets' => true]);
        $girls->settings()->create(['num_sets' => 3, 'require_full_sets' => true]);

        $home = Registration::factory()->create();
        $away = Registration::factory()->create();
        $group = DrawGroup::factory()->create(['draw_id' => $boys->id]);
        DrawGroupRegistration::factory()->create(['draw_group_id' => $group->id, 'registration_id' => $home->id]);
        DrawGroupRegistration::factory()->create(['draw_group_id' => $group->id, 'registration_id' => $away->id]);
        $fixture = Fixture::factory()->create([
            'draw_id' => $boys->id,
            'draw_group_id' => $group->id,
            'stage' => 'RR',
            'registration1_id' => $home->id,
            'registration2_id' => $away->id,
            'winner_registration' => $away->id,
        ]);
        FixtureResult::factory()->create([
            'fixture_id' => $fixture->id,
            'set_nr' => 1,
            'registration1_score' => 3,
            'registration2_score' => 1,
            'winner_registration' => $home->id,
            'loser_registration' => $away->id,
        ]);
        FixtureResult::factory()->create([
            'fixture_id' => $fixture->id,
            'set_nr' => 2,
            'registration1_score' => 0,
            'registration2_score' => 3,
            'winner_registration' => $away->id,
            'loser_registration' => $home->id,
        ]);

        $migration = require database_path('migrations/2026_09_06_010000_repair_overberg_u10b_single_set_results.php');
        $migration->up();
        $migration->up();

        $this->assertSame(1, $boys->fresh()->settings->num_sets);
        $this->assertFalse($boys->fresh()->settings->requiresFullSets());
        $this->assertSame(1, $girls->fresh()->settings->num_sets);
        $this->assertFalse($girls->fresh()->settings->requiresFullSets());
        $this->assertSame(1, $fixture->fixtureResults()->count());
        $this->assertSame($home->id, $fixture->fresh()->winner_registration);
        $this->assertDatabaseCount('draw_audit_logs', 1);

        $standings = app(StandingsService::class)->forDraw($boys->fresh());
        $winner = collect($standings[$group->id])->firstWhere('reg_id', $home->id);
        $loser = collect($standings[$group->id])->firstWhere('reg_id', $away->id);
        $this->assertSame(1, $winner['wins']);
        $this->assertSame(3, $winner['games_won']);
        $this->assertSame(1, $winner['games_lost']);
        $this->assertSame(0, $loser['wins']);
        $this->assertSame(1, $loser['games_won']);
        $this->assertSame(3, $loser['games_lost']);
    }
}
