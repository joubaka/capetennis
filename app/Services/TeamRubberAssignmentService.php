<?php

namespace App\Services;

use App\Models\Player;
use App\Models\TeamEventFormatRubber;
use App\Models\TeamFixture;
use App\Models\TeamFixturePlayer;
use App\Models\TeamTie;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TeamRubberAssignmentService
 *
 * Assigns players to a rubber (TeamFixture) from the home and away
 * team rosters, enforcing:
 *  - No cross-team mixing (team1 slot = home team, team2 slot = away team).
 *  - Correct player count per team for the rubber type.
 *  - Gender rule compliance where applicable.
 *  - Slot ordering is deterministic via slot_no.
 */
class TeamRubberAssignmentService
{
    public function __construct(
        private readonly TeamTieValidationService $validator
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assign players to a rubber.
     *
     * @param  TeamFixture  $rubber
     * @param  array<int>   $homePlayerIds   Player IDs from the home team (slot 1 per team).
     * @param  array<int>   $awayPlayerIds   Player IDs from the away team (slot 2 per team).
     * @param  bool         $replace         If true, delete existing assignments before writing.
     * @return Collection<int, TeamFixturePlayer>
     *
     * @throws \InvalidArgumentException  on validation failure.
     */
    public function assign(
        TeamFixture $rubber,
        array       $homePlayerIds,
        array       $awayPlayerIds,
        bool        $replace = true
    ): Collection {
        $tie    = $rubber->teamTie;
        $format = $rubber->draw?->teamEventFormat;

        // Find the rubber template for this rubber_code + sequence
        $template = $format?->rubbers
            ->firstWhere('sequence', $rubber->rubber_sequence);

        $expectedPerTeam = $rubber->player_count_per_team
            ?? $template?->playerCountPerTeam()
            ?? 1;

        // Validate player counts
        if (count($homePlayerIds) !== $expectedPerTeam) {
            throw new \InvalidArgumentException(
                "Home team must provide exactly {$expectedPerTeam} player(s) for this rubber. " .
                "Got " . count($homePlayerIds) . "."
            );
        }
        if (count($awayPlayerIds) !== $expectedPerTeam) {
            throw new \InvalidArgumentException(
                "Away team must provide exactly {$expectedPerTeam} player(s) for this rubber. " .
                "Got " . count($awayPlayerIds) . "."
            );
        }

        // Validate players belong to their respective teams
        if ($tie) {
            $this->validator->assertPlayersFromTeam($homePlayerIds, $tie->home_team_id, $tie->draw_id);
            $this->validator->assertPlayersFromTeam($awayPlayerIds, $tie->away_team_id, $tie->draw_id);
        }

        // Validate gender rules
        if ($template?->gender_rule) {
            $this->validator->assertGenderRule(
                array_merge($homePlayerIds, $awayPlayerIds),
                $template->gender_rule,
                $rubber->rubber_code
            );
        }

        $isReverseDoublesLike = in_array(
            (string) $rubber->rubber_code,
            [\App\Domain\TeamDraw\RubberType::REVERSE_DOUBLES, \App\Domain\TeamDraw\RubberType::REVERSE_MIXED_DOUBLES],
            true
        );

        return DB::transaction(function () use ($rubber, $homePlayerIds, $awayPlayerIds, $expectedPerTeam, $replace, $isReverseDoublesLike) {
            if ($replace) {
                $rubber->fixturePlayers()->delete();
            }

            $created = collect();

            for ($slot = 0; $slot < $expectedPerTeam; $slot++) {
                $awayIndex = $isReverseDoublesLike ? ($expectedPerTeam - 1 - $slot) : $slot;

                $player = TeamFixturePlayer::create([
                    'team_fixture_id' => $rubber->id,
                    'slot_no'         => $slot + 1,
                    'team1_id'        => $homePlayerIds[$slot] ?? null,
                    'team2_id'        => $awayPlayerIds[$awayIndex] ?? null,
                ]);
                $created->push($player);
            }

            Log::info('[TeamRubberAssignmentService] Players assigned', [
                'rubber_id'      => $rubber->id,
                'home_players'   => $homePlayerIds,
                'away_players'   => $awayPlayerIds,
            ]);

            return $created;
        });
    }
}
