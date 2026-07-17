<?php

namespace Tests\Unit\Services;

use App\Models\Draw;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamEventFormat;
use App\Models\TeamTie;
use App\Services\TeamDrawGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for TeamDrawGenerationService
 *
 * Covers:
 *  1.  buildRoundRobinSchedule: even number of teams produces correct pairs.
 *  2.  buildRoundRobinSchedule: odd number of teams (bye) excludes null pairs.
 *  3.  buildRoundRobinSchedule: each team appears exactly once per round.
 *  4.  buildRoundRobinSchedule: each pairing is unique across all rounds.
 *  5.  generate: persists team_ties to DB.
 *  6.  generate: throws on fewer than 2 teams.
 *  7.  generate: throws when locked ties exist and override is false.
 *  8.  generate: succeeds with override when locked ties exist.
 *  9.  generate: attaches format to draw.
 */
class TeamDrawGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    private TeamDrawGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TeamDrawGenerationService();
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    private function fakeTeams(int $count): \Illuminate\Support\Collection
    {
        return Team::factory()->count($count)->create()->collect();
    }

    private function makeDraw(): Draw
    {
        return Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
    }

    // ─── 1. Even teams: correct pair count ────────────────────────────────

    public function test_round_robin_schedule_with_even_teams(): void
    {
        $teams    = $this->fakeTeams(4);
        $schedule = $this->service->buildRoundRobinSchedule($teams);

        // 4 teams → 3 rounds, each round has 2 matches
        $this->assertCount(3, $schedule);
        foreach ($schedule as $round => $matches) {
            $this->assertCount(2, $matches, "Round {$round} should have 2 matches.");
        }
    }

    // ─── 2. Odd teams: no null entries ────────────────────────────────────

    public function test_round_robin_schedule_with_odd_teams_has_no_null_pairs(): void
    {
        $teams    = $this->fakeTeams(3);
        $schedule = $this->service->buildRoundRobinSchedule($teams);

        foreach ($schedule as $round => $matches) {
            foreach ($matches as [$home, $away]) {
                $this->assertNotNull($home, "Round {$round}: home team is null.");
                $this->assertNotNull($away, "Round {$round}: away team is null.");
            }
        }
    }

    // ─── 3. Each team appears once per round ──────────────────────────────

    public function test_each_team_appears_exactly_once_per_round(): void
    {
        $teams    = $this->fakeTeams(6);
        $schedule = $this->service->buildRoundRobinSchedule($teams);

        foreach ($schedule as $round => $matches) {
            $ids = collect($matches)->flatMap(fn($pair) => [$pair[0]->id, $pair[1]->id]);

            $this->assertEquals(
                $ids->count(),
                $ids->unique()->count(),
                "Round {$round}: a team appears more than once."
            );
        }
    }

    // ─── 4. Unique pairings across all rounds ─────────────────────────────

    public function test_each_pairing_is_unique_across_all_rounds(): void
    {
        $teams    = $this->fakeTeams(6);
        $schedule = $this->service->buildRoundRobinSchedule($teams);

        $seen = [];

        foreach ($schedule as $matches) {
            foreach ($matches as [$home, $away]) {
                // Normalize pairing (unordered)
                $key = implode('-', collect([$home->id, $away->id])->sort()->all());
                $this->assertNotContains($key, $seen,
                    "Duplicate pairing {$home->id} vs {$away->id} found.");
                $seen[] = $key;
            }
        }
    }

    // ─── 5. generate: persists team_ties ──────────────────────────────────

    public function test_generate_persists_ties_to_database(): void
    {
        $draw  = $this->makeDraw();
        $teams = $this->fakeTeams(4);

        $ties = $this->service->generate($draw, $teams);

        $this->assertGreaterThan(0, $ties->count());
        $this->assertDatabaseCount('team_ties', $ties->count());

        foreach ($ties as $tie) {
            $this->assertEquals($draw->id, $tie->draw_id);
        }
    }

    // ─── 6. generate: throws on < 2 teams ─────────────────────────────────

    public function test_generate_throws_when_fewer_than_two_teams(): void
    {
        $draw  = $this->makeDraw();
        $teams = $this->fakeTeams(1);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->generate($draw, $teams);
    }

    // ─── 7. generate: blocked by locked ties ──────────────────────────────

    public function test_generate_throws_when_locked_ties_exist_without_override(): void
    {
        $draw  = $this->makeDraw();
        $teams = $this->fakeTeams(2);

        // Create a published tie
        TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'status'       => TeamTie::STATUS_PUBLISHED,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->generate($draw, $teams);
    }

    // ─── 8. generate: override bypasses lock ──────────────────────────────

    public function test_generate_succeeds_with_override_despite_locked_ties(): void
    {
        $draw  = $this->makeDraw();
        $teams = $this->fakeTeams(2);

        TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'status'       => TeamTie::STATUS_PUBLISHED,
        ]);

        $ties = $this->service->generate($draw, $teams, null, true);

        $this->assertGreaterThan(0, $ties->count());
    }

    // ─── 9. generate: attaches format ─────────────────────────────────────

    public function test_generate_attaches_format_to_draw(): void
    {
        $draw   = $this->makeDraw();
        $teams  = $this->fakeTeams(2);
        $format = TeamEventFormat::factory()->create();

        $this->service->generate($draw, $teams, $format);

        $this->assertDatabaseHas('draws', [
            'id'                   => $draw->id,
            'team_event_format_id' => $format->id,
        ]);
    }
}
