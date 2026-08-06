<?php

namespace App\Services;

use App\Domain\TeamDraw\RubberType;
use App\Models\Team;
use App\Models\TeamEventFormatRubber;
use App\Models\TeamFixture;
use App\Models\TeamFixturePlayer;
use App\Models\TeamTie;
use Illuminate\Support\Facades\Log;

/**
 * TeamPlayerAutoAssignService
 *
 * Automatically inserts TeamFixturePlayer records for a rubber immediately
 * after the rubber (TeamFixture) is created.
 *
 * Rules:
 *  - Singles / Reverse Singles  → 1 slot per side.
 *    Uses rubber_template->singles_position (1-based rank) to pick the player.
 *    For reverse_singles the position is read from reverse_from_position if set.
 *  - Doubles                → 2 slots per side (ranks 1 & 2 from the team).
 *  - Mixed / Reverse Mixed  → 2 slots per side, preferring one male and one female per team.
 *  - If a team is null (bye) or has no player at the required rank → null placeholder.
 *  - Profile players   → stored in team1_id / team2_id.
 *  - No-profile players → stored in team1_no_profile_id / team2_no_profile_id.
 */
class TeamPlayerAutoAssignService
{
    /**
     * Assign players to a rubber based on the format template and the two teams of the tie.
     *
     * @param  TeamFixture            $rubber
     * @param  TeamTie                $tie
     * @param  TeamEventFormatRubber  $template
     * @return void
     */
    public function assignForRubber(
        TeamFixture           $rubber,
        TeamTie               $tie,
        TeamEventFormatRubber $template
    ): void {
        // Delete any existing assignments (idempotent re-run)
        $rubber->fixturePlayers()->delete();

        $homeTeam = $tie->homeTeam;   // may be null (bye)
        $awayTeam = $tie->awayTeam;   // may be null (bye)

        $slots = $this->resolveSlots($template, $homeTeam, $awayTeam);

        foreach ($slots as $slotData) {
            TeamFixturePlayer::create(array_merge(
                ['team_fixture_id' => $rubber->id],
                $slotData
            ));
        }

        Log::debug('[TeamPlayerAutoAssignService] Players assigned', [
            'rubber_id' => $rubber->id,
            'tie_id'    => $tie->id,
            'slots'     => count($slots),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve slot data arrays for a rubber.
     *
     * Returns an array of arrays, each with keys accepted by TeamFixturePlayer::$fillable.
     *
     * @param  TeamEventFormatRubber  $template
     * @param  Team|null              $homeTeam
     * @param  Team|null              $awayTeam
     * @return array<int, array<string, mixed>>
     */
    private function resolveSlots(
        TeamEventFormatRubber $template,
        ?Team                 $homeTeam,
        ?Team                 $awayTeam
    ): array {
        $code = (string) $template->rubber_code;

        if (in_array($code, [RubberType::MIXED_DOUBLES, RubberType::REVERSE_MIXED_DOUBLES], true)) {
            return $this->mixedSlots($homeTeam, $awayTeam, $code === RubberType::REVERSE_MIXED_DOUBLES);
        }

        if (in_array($code, [RubberType::DOUBLES, RubberType::REVERSE_DOUBLES], true)) {
            return $this->doublesSlots($homeTeam, $awayTeam, $code === RubberType::REVERSE_DOUBLES);
        }

        // Singles or Reverse Singles
        $position = $this->singlesPosition($template);
        return $this->singlesSlots($homeTeam, $awayTeam, $position);
    }

    /**
     * Build 1 slot for singles (both sides use the same position/rank).
     */
    private function singlesSlots(?Team $homeTeam, ?Team $awayTeam, int $position): array
    {
        [$homeProfileId, $homeNoProfileId] = $this->playerAtRank($homeTeam, $position);
        [$awayProfileId, $awayNoProfileId] = $this->playerAtRank($awayTeam, $position);

        return [
            [
                'slot_no'             => 1,
                'team1_id'            => $homeProfileId,
                'team2_id'            => $awayProfileId,
                'team1_no_profile_id' => $homeNoProfileId,
                'team2_no_profile_id' => $awayNoProfileId,
            ],
        ];
    }

    /**
     * Build 2 slots for doubles (ranks 1 & 2 from each team into a single slot row).
     *
     * TeamFixturePlayer holds both doubles partners for a team in a single row:
     *   slot 1 → team1_id = home player A, team2_id = away player A (rank 1)
     *   slot 2 → team1_id = home player B, team2_id = away player B (rank 2)
     */
    private function doublesSlots(?Team $homeTeam, ?Team $awayTeam, bool $reverse = false): array
    {
        [$homeP1, $homeNP1] = $this->playerAtRank($homeTeam, 1);
        [$homeP2, $homeNP2] = $this->playerAtRank($homeTeam, 2);
        [$awayP1, $awayNP1] = $this->playerAtRank($awayTeam, 1);
        [$awayP2, $awayNP2] = $this->playerAtRank($awayTeam, 2);

        if ($reverse) {
            return [
                [
                    'slot_no'             => 1,
                    'team1_id'            => $homeP1,
                    'team2_id'            => $awayP2,
                    'team1_no_profile_id' => $homeNP1,
                    'team2_no_profile_id' => $awayNP2,
                ],
                [
                    'slot_no'             => 2,
                    'team1_id'            => $homeP2,
                    'team2_id'            => $awayP1,
                    'team1_no_profile_id' => $homeNP2,
                    'team2_no_profile_id' => $awayNP1,
                ],
            ];
        }

        return [
            [
                'slot_no'             => 1,
                'team1_id'            => $homeP1,
                'team2_id'            => $awayP1,
                'team1_no_profile_id' => $homeNP1,
                'team2_no_profile_id' => $awayNP1,
            ],
            [
                'slot_no'             => 2,
                'team1_id'            => $homeP2,
                'team2_id'            => $awayP2,
                'team1_no_profile_id' => $homeNP2,
                'team2_no_profile_id' => $awayNP2,
            ],
        ];
    }

    /**
     * Build 2 slots for mixed doubles using one male and one female player per team.
     * Falls back to the standard rank-based doubles ordering when gendered players
     * are not available.
     */
    private function mixedSlots(?Team $homeTeam, ?Team $awayTeam, bool $reverse = false): array
    {
        $homePair = $this->mixedPairSlots($homeTeam);
        $awayPair = $this->mixedPairSlots($awayTeam);

        if (!$homePair || !$awayPair) {
            return $this->doublesSlots($homeTeam, $awayTeam, $reverse);
        }

        [$homeMale, $homeFemale] = $homePair;
        [$awayMale, $awayFemale] = $awayPair;

        if ($reverse) {
            return [
                [
                    'slot_no'             => 1,
                    'team1_id'            => $homeMale[0],
                    'team2_id'            => $awayFemale[0],
                    'team1_no_profile_id' => $homeMale[1],
                    'team2_no_profile_id' => $awayFemale[1],
                ],
                [
                    'slot_no'             => 2,
                    'team1_id'            => $homeFemale[0],
                    'team2_id'            => $awayMale[0],
                    'team1_no_profile_id' => $homeFemale[1],
                    'team2_no_profile_id' => $awayMale[1],
                ],
            ];
        }

        return [
            [
                'slot_no'             => 1,
                'team1_id'            => $homeMale[0],
                'team2_id'            => $awayMale[0],
                'team1_no_profile_id' => $homeMale[1],
                'team2_no_profile_id' => $awayMale[1],
            ],
            [
                'slot_no'             => 2,
                'team1_id'            => $homeFemale[0],
                'team2_id'            => $awayFemale[0],
                'team1_no_profile_id' => $homeFemale[1],
                'team2_no_profile_id' => $awayFemale[1],
            ],
        ];
    }

    /**
     * Get the player id (profile or no-profile) at a given 1-based rank from a team.
     *
     * Returns [profileId|null, noProfileId|null].
     * If the team is null or has no player at that rank, both values are null (placeholder).
     */
    private function playerAtRank(?Team $team, int $rank): array
    {
        if (!$team) {
            return [null, null];
        }

        // Profile players ordered by rank
        $profilePlayer = $team->team_players->firstWhere('rank', $rank);
        if ($profilePlayer) {
            return [$profilePlayer->player_id, null];
        }

        // No-profile players ordered by rank
        $noProfilePlayer = $team->team_players_no_profile->firstWhere('rank', $rank);
        if ($noProfilePlayer) {
            return [null, $noProfilePlayer->id];
        }

        // No player at this rank → placeholder
        return [null, null];
    }

    /**
     * Pick one male and one female profile player from a team.
     * Returns [[profileId|null, noProfileId|null], [profileId|null, noProfileId|null]]
     * or null when the team cannot satisfy gendered selection.
     */
    private function mixedPairSlots(?Team $team): ?array
    {
        if (!$team) {
            return null;
        }

        $players = $team->team_players->sortBy('rank')->values();
        $male = $this->firstProfilePlayerByGender($players, 'male');
        $female = $this->firstProfilePlayerByGender($players, 'female');

        if (!$male || !$female) {
            return null;
        }

        return [
            [$male->player_id, null],
            [$female->player_id, null],
        ];
    }

    /**
     * Find the first profile-backed team player with a matching gender.
     */
    private function firstProfilePlayerByGender($players, string $gender)
    {
        return $players->first(function ($teamPlayer) use ($gender) {
            $playerGender = $teamPlayer->player?->gender;

            if ($playerGender === null) {
                return false;
            }

            if ($playerGender === 1 || $playerGender === '1') {
                return $gender === 'male';
            }

            if ($playerGender === 2 || $playerGender === '2') {
                return $gender === 'female';
            }

            return strtolower((string) $playerGender) === $gender;
        });
    }

    /**
     * Determine the 1-based rank/position to use for a singles rubber.
     *
     * - For reverse_singles: prefer reverse_from_position, fall back to singles_position.
     * - For regular singles: use singles_position.
     * - Default to 1 if neither is set.
     */
    private function singlesPosition(TeamEventFormatRubber $template): int
    {
        $code = (string) $template->rubber_code;

        if ($code === RubberType::REVERSE_SINGLES && !empty($template->reverse_from_position)) {
            return (int) $template->reverse_from_position;
        }

        return (int) ($template->singles_position ?? 1);
    }
}
