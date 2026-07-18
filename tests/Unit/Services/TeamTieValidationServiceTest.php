<?php

namespace Tests\Unit\Services;

use App\Models\Draw;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamEventFormat;
use App\Models\TeamEventFormatRubber;
use App\Models\TeamFixture;
use App\Models\TeamTie;
use App\Services\TeamTieValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for TeamTieValidationService
 *
 * Covers:
 *  1.  assertHardRosterCap passes for 12 players.
 *  2.  assertHardRosterCap fails for 13+ players.
 *  3.  assertRosterSize passes within format bounds.
 *  4.  assertRosterSize fails below min.
 *  5.  assertNoDuplicateAssignment passes when reuse allowed.
 *  6.  assertNoDuplicateAssignment fails when reuse disallowed and duplicate found.
 *  7.  assertGenderRule male: fails when female player present.
 *  8.  assertGenderRule female: passes for female players.
 *  9.  assertGenderRule mixed: fails with all male players.
 *  10. assertGenderRule mixed: passes with male + female.
 *  11. assertTieComplete passes when all rubbers have players assigned.
 *  12. assertTieComplete fails when rubber has no home player.
 */
class TeamTieValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private TeamTieValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TeamTieValidationService();
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    private function makeTeamWithPlayers(int $count, string $gender = 'male'): Team
    {
        $team = Team::factory()->create();

        $factory = $gender === 'female'
            ? Player::factory()->female()
            : Player::factory()->male();

        $players = $factory->count($count)->create();

        // Attach via Eloquent relationship with rank ordering
        foreach ($players as $rank => $player) {
            $team->players()->attach($player->id, [
                'pay_status' => 0, // Default: unpaid
                'rank'       => $rank + 1,
            ]);
        }

        return $team->fresh(['team_players', 'team_players_no_profile']);
    }

    private function makeFormat(int $min = 1, int $max = 12, bool $allowReuse = false): TeamEventFormat
    {
        return TeamEventFormat::factory()->create([
            'min_roster_size'    => $min,
            'max_roster_size'    => $max,
            'allow_player_reuse' => $allowReuse,
        ]);
    }

    // ─── 1. Hard cap passes ────────────────────────────────────────────────

    public function test_hard_roster_cap_passes_for_twelve_players(): void
    {
        $team = $this->makeTeamWithPlayers(12);
        $this->service->assertHardRosterCap($team); // no exception
        $this->assertTrue(true);
    }

    // ─── 2. Hard cap fails ────────────────────────────────────────────────

    public function test_hard_roster_cap_fails_for_thirteen_players(): void
    {
        $team = $this->makeTeamWithPlayers(13);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->assertHardRosterCap($team);
    }

    // ─── 3. Roster size passes within bounds ──────────────────────────────

    public function test_roster_size_passes_within_format_bounds(): void
    {
        $team   = $this->makeTeamWithPlayers(6);
        $format = $this->makeFormat(4, 12);
        $this->service->assertRosterSize($team, $format); // no exception
        $this->assertTrue(true);
    }

    // ─── 4. Roster size fails below min ───────────────────────────────────

    public function test_roster_size_fails_below_minimum(): void
    {
        $team   = $this->makeTeamWithPlayers(2);
        $format = $this->makeFormat(4, 12);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->assertRosterSize($team, $format);
    }

    // ─── 5. No duplicate passes when reuse allowed ────────────────────────

    public function test_no_duplicate_assignment_passes_when_reuse_allowed(): void
    {
        $format = $this->makeFormat(1, 12, allowReuse: true);
        $this->service->assertNoDuplicateAssignment([1], [1], $format); // no exception
        $this->assertTrue(true);
    }

    // ─── 6. No duplicate fails when reuse disallowed ──────────────────────

    public function test_no_duplicate_assignment_fails_when_reuse_disallowed(): void
    {
        $format = $this->makeFormat(1, 12, allowReuse: false);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->assertNoDuplicateAssignment([1, 2], [2, 3], $format);
    }

    // ─── 7. Gender rule male: fails with female player ────────────────────

    public function test_gender_rule_male_fails_when_female_player_present(): void
    {
        $malePlayer   = Player::factory()->male()->create();
        $femalePlayer = Player::factory()->female()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->assertGenderRule(
            [$malePlayer->id, $femalePlayer->id],
            'male',
            'singles'
        );
    }

    // ─── 8. Gender rule female: passes with female players ────────────────

    public function test_gender_rule_female_passes_with_all_female_players(): void
    {
        $players = Player::factory()->female()->count(2)->create();
        $this->service->assertGenderRule($players->pluck('id')->all(), 'female', 'singles');
        $this->assertTrue(true);
    }

    // ─── 9. Gender rule mixed: fails with all male ────────────────────────

    public function test_gender_rule_mixed_fails_with_all_male_players(): void
    {
        $players = Player::factory()->male()->count(2)->create();
        $this->expectException(\InvalidArgumentException::class);
        $this->service->assertGenderRule($players->pluck('id')->all(), 'mixed', 'mixed_doubles');
    }

    // ─── 10. Gender rule mixed: passes with male + female ─────────────────

    public function test_gender_rule_mixed_passes_with_male_and_female(): void
    {
        $male   = Player::factory()->male()->create();
        $female = Player::factory()->female()->create();

        $this->service->assertGenderRule([$male->id, $female->id], 'mixed', 'mixed_doubles');
        $this->assertTrue(true);
    }

    // ─── 11. Tie complete: passes with all rubbers assigned ───────────────

    public function test_assert_tie_complete_passes_when_all_rubbers_have_players(): void
    {
        $event  = Event::factory()->create();
        $draw   = Draw::factory()->create(['event_id' => $event->id]);
        $home   = Team::factory()->create();
        $away   = Team::factory()->create();

        $tie = TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'status'       => TeamTie::STATUS_DRAFT,
        ]);

        $rubber = TeamFixture::create([
            'draw_id'               => $draw->id,
            'team_tie_id'           => $tie->id,
            'match_nr'              => 1,
            'rubber_sequence'       => 1,
            'rubber_code'           => 'singles',
            'rubber_name'           => 'Singles 1',
            'player_count_per_team' => 1,
            'round_nr'              => 1,
            'tie_nr'                => 1,
        ]);

        \App\Models\TeamFixturePlayer::create([
            'team_fixture_id' => $rubber->id,
            'slot_no'         => 1,
            'team1_id'        => Player::factory()->create()->id,
            'team2_id'        => Player::factory()->create()->id,
        ]);

        $this->service->assertTieComplete($tie->fresh());
        $this->assertTrue(true);
    }

    // ─── 12. Tie complete: fails when rubber has no home player ───────────

    public function test_assert_tie_complete_fails_when_rubber_has_no_home_player(): void
    {
        $event = Event::factory()->create();
        $draw  = Draw::factory()->create(['event_id' => $event->id]);
        $home  = Team::factory()->create();
        $away  = Team::factory()->create();

        $tie = TeamTie::create([
            'draw_id'      => $draw->id,
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'status'       => TeamTie::STATUS_DRAFT,
        ]);

        // Rubber with no player assignment
        TeamFixture::create([
            'draw_id'               => $draw->id,
            'team_tie_id'           => $tie->id,
            'match_nr'              => 1,
            'rubber_sequence'       => 1,
            'rubber_code'           => 'singles',
            'rubber_name'           => 'Singles 1',
            'player_count_per_team' => 1,
            'round_nr'              => 1,
            'tie_nr'                => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->assertTieComplete($tie->fresh());
    }
}
