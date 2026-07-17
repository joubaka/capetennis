<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamEventFormat;
use App\Models\TeamTie;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TeamTieValidationService
 *
 * Validates roster constraints, player eligibility, gender/category
 * rules, and duplicate-assignment rules before persisting.
 *
 * All public methods throw \InvalidArgumentException on failure, or
 * return normally on success.
 */
class TeamTieValidationService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Roster Validation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assert that a team's active roster satisfies the format's size constraints.
     *
     * @param  Team              $team
     * @param  TeamEventFormat   $format
     * @throws \InvalidArgumentException
     */
    public function assertRosterSize(Team $team, TeamEventFormat $format): void
    {
        $count = $team->team_players->count() + $team->team_players_no_profile->count();

        if ($count < $format->min_roster_size) {
            throw new \InvalidArgumentException(
                "Team \"{$team->name}\" has only {$count} player(s), but the format requires " .
                "at least {$format->min_roster_size}."
            );
        }

        if ($count > $format->max_roster_size) {
            throw new \InvalidArgumentException(
                "Team \"{$team->name}\" has {$count} player(s), which exceeds the format maximum " .
                "of {$format->max_roster_size}."
            );
        }
    }

    /**
     * Assert that a team's roster does not exceed the hard cap of 12 active players.
     *
     * @param  Team  $team
     * @throws \InvalidArgumentException
     */
    public function assertHardRosterCap(Team $team): void
    {
        $count = $team->team_players->count() + $team->team_players_no_profile->count();

        if ($count > 12) {
            throw new \InvalidArgumentException(
                "Team \"{$team->name}\" has {$count} active players, which exceeds the maximum of 12."
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Player Eligibility
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assert that all given player IDs belong to the specified team in the draw context.
     *
     * @param  array<int>  $playerIds
     * @param  int         $teamId
     * @param  int         $drawId   Used for context in error messages.
     * @throws \InvalidArgumentException
     */
    public function assertPlayersFromTeam(array $playerIds, int $teamId, int $drawId): void
    {
        if (empty($playerIds)) {
            return;
        }

        $team = Team::with('team_players')->findOrFail($teamId);

        $validIds = $team->team_players->pluck('player_id')->toArray();

        $invalid = array_diff($playerIds, $validIds);

        if (!empty($invalid)) {
            throw new \InvalidArgumentException(
                "Player(s) [" . implode(', ', $invalid) . "] do not belong to team \"{$team->name}\" " .
                "(draw #{$drawId})."
            );
        }
    }

    /**
     * Assert no player appears more than once in a tie when player reuse is disabled.
     *
     * @param  array<int>        $playerIds    All player IDs assigned so far in this tie.
     * @param  array<int>        $newPlayerIds Additional player IDs being assigned.
     * @param  TeamEventFormat   $format
     * @throws \InvalidArgumentException
     */
    public function assertNoDuplicateAssignment(
        array          $playerIds,
        array          $newPlayerIds,
        TeamEventFormat $format
    ): void {
        if ($format->allow_player_reuse) {
            return;
        }

        $duplicates = array_intersect($playerIds, $newPlayerIds);

        if (!empty($duplicates)) {
            throw new \InvalidArgumentException(
                "Player(s) [" . implode(', ', $duplicates) . "] are already assigned in this tie " .
                "and the format does not allow player reuse."
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Gender / Category Rules
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assert that the given player IDs satisfy the rubber's gender rule.
     *
     * Gender rule values: 'male' | 'female' | 'mixed'
     *
     * For 'mixed': expects exactly one male and one female among all provided players.
     * For 'male' / 'female': all players must match.
     *
     * Falls back gracefully when player gender is not set (warns, does not reject).
     *
     * @param  array<int>  $playerIds
     * @param  string      $genderRule
     * @param  string      $rubberCode  For error context.
     * @throws \InvalidArgumentException
     */
    public function assertGenderRule(array $playerIds, string $genderRule, string $rubberCode): void
    {
        if (empty($playerIds) || $genderRule === '') {
            return;
        }

        $players = Player::whereIn('id', $playerIds)->get();

        if ($players->isEmpty()) {
            return;
        }

        $genders = $players->pluck('gender')->filter()->map(fn($g) => strtolower($g));

        if ($genders->isEmpty()) {
            // Gender not set on players — warn but allow
            \Log::warning("[TeamTieValidationService] Players have no gender set; skipping gender rule check.", [
                'player_ids'  => $playerIds,
                'rubber_code' => $rubberCode,
            ]);
            return;
        }

        match ($genderRule) {
            'male'   => $this->assertAllGender($genders, 'male', $rubberCode),
            'female' => $this->assertAllGender($genders, 'female', $rubberCode),
            'mixed'  => $this->assertMixed($genders, $rubberCode),
            default  => null, // Unknown rule — allow and log
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tie Completeness
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assert that all rubbers in a tie have at least one player assigned per side.
     *
     * @param  TeamTie  $tie
     * @throws \InvalidArgumentException
     */
    public function assertTieComplete(TeamTie $tie): void
    {
        $rubbers = $tie->rubbers()->with('fixturePlayers')->get();
        $incomplete = [];

        foreach ($rubbers as $rubber) {
            $hasHome = $rubber->fixturePlayers->whereNotNull('team1_id')->isNotEmpty()
                || $rubber->fixturePlayers->whereNotNull('team1_no_profile_id')->isNotEmpty();
            $hasAway = $rubber->fixturePlayers->whereNotNull('team2_id')->isNotEmpty()
                || $rubber->fixturePlayers->whereNotNull('team2_no_profile_id')->isNotEmpty();

            if (!$hasHome || !$hasAway) {
                $incomplete[] = "Rubber #{$rubber->rubber_sequence} ({$rubber->rubber_name})";
            }
        }

        if (!empty($incomplete)) {
            throw new \InvalidArgumentException(
                "Tie #{$tie->id} is incomplete. Missing player assignments for: " .
                implode(', ', $incomplete) . '.'
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function assertAllGender(Collection $genders, string $expected, string $rubberCode): void
    {
        $invalid = $genders->reject(fn($g) => $g === $expected);

        if ($invalid->isNotEmpty()) {
            throw new \InvalidArgumentException(
                "Rubber \"{$rubberCode}\" requires all players to be {$expected}. " .
                "Found: " . $invalid->implode(', ') . '.'
            );
        }
    }

    private function assertMixed(Collection $genders, string $rubberCode): void
    {
        $hasMale   = $genders->contains('male');
        $hasFemale = $genders->contains('female');

        if (!$hasMale || !$hasFemale) {
            throw new \InvalidArgumentException(
                "Rubber \"{$rubberCode}\" is mixed doubles and requires at least one male and one female player. " .
                "Found genders: " . $genders->implode(', ') . '.'
            );
        }
    }
}
