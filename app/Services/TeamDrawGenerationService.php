<?php

namespace App\Services;

use App\Domain\TeamDraw\TeamDrawConflictException;
use App\Models\Draw;
use App\Models\Team;
use App\Models\TeamEventFormat;
use App\Models\TeamTie;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TeamDrawGenerationService
 *
 * Generates team_ties for a draw from a list of teams using a
 * round-robin schedule. Does NOT generate rubbers (TeamFixtures) —
 * that is handled by TeamTieGenerationService.
 *
 * Responsibilities:
 *  - Accept draw, ordered team list, and format.
 *  - Build a round-robin pairing schedule (adding a bye for odd teams).
 *  - Persist team_ties transactionally, idempotent via unique constraints.
 *  - Return the created TeamTie collection.
 */
class TeamDrawGenerationService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate ties for the given draw.
     *
     * @param  Draw                      $draw
     * @param  Collection<int, Team>     $teams   Ordered list of teams to schedule.
     * @param  TeamEventFormat|null      $format  Format to attach to the draw (optional).
     * @return Collection<int, TeamTie>
     *
     * @throws \RuntimeException  if draw has published/completed ties and override is false.
     */
    public function generate(
        Draw           $draw,
        Collection     $teams,
        ?TeamEventFormat $format = null,
        bool           $allowOverride = false
    ): Collection {
        // Guard: do not destructively regenerate if any tie is locked
        if (!$allowOverride) {
            $lockedCount = $draw->teamTies()->locked()->count();
            if ($lockedCount > 0) {
                throw new TeamDrawConflictException(
                    "Cannot regenerate ties: {$lockedCount} tie(s) are published or completed. " .
                    "Pass allowOverride=true to force."
                );
            }
        }

        if ($teams->count() < 2) {
            throw new \InvalidArgumentException('At least 2 teams are required to generate ties.');
        }

        $schedule = $this->buildRoundRobinSchedule($teams);

        return DB::transaction(function () use ($draw, $schedule, $format) {
            // Attach format if provided
            if ($format) {
                $draw->team_event_format_id = $format->id;
                $draw->save();
            }

            $created = collect();

            foreach ($schedule as $roundNr => $matches) {
                $tieNr = 1;
                foreach ($matches as [$homeTeam, $awayTeam]) {
                    $tie = TeamTie::firstOrCreate(
                        [
                            'draw_id'      => $draw->id,
                            'round_nr'     => $roundNr,
                            'home_team_id' => $homeTeam->id,
                            'away_team_id' => $awayTeam->id,
                        ],
                        [
                            'tie_nr' => $tieNr,
                            'status' => TeamTie::STATUS_DRAFT,
                        ]
                    );

                    $created->push($tie);
                    $tieNr++;
                }
            }

            Log::info('[TeamDrawGenerationService] Ties generated', [
                'draw_id' => $draw->id,
                'ties'    => $created->count(),
            ]);

            return $created;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Round-robin schedule builder
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a round-robin schedule using the circle method.
     *
     * Returns: [ roundNr => [ [homeTeam, awayTeam], ... ], ... ]
     *
     * @param  Collection<int, Team> $teams
     * @return array<int, array<int, array{0: Team, 1: Team}>>
     */
    public function buildRoundRobinSchedule(Collection $teams): array
    {
        $list      = $teams->values()->all();
        $teamCount = count($list);

        // Add a bye placeholder for odd number of teams
        if ($teamCount % 2 === 1) {
            $list[]    = null;
            $teamCount++;
        }

        $half     = $teamCount / 2;
        $rounds   = $teamCount - 1;
        $schedule = [];

        // Pin the first team; rotate the rest
        $pivot = array_shift($list);
        $ring  = $list;

        for ($round = 1; $round <= $rounds; $round++) {
            $schedule[$round] = [];

            // Top half vs bottom half (reversed)
            $top    = array_slice($ring, 0, $half - 1);
            $bottom = array_reverse(array_slice($ring, $half - 1));

            // First match: pinned team vs last of ring.
            // Alternate which side the pinned team plays on each round to ensure
            // fair home/away distribution across the full schedule.
            $firstHome = ($round % 2 === 0) ? $ring[$half - 1] : $pivot;
            $firstAway = ($round % 2 === 0) ? $pivot : $ring[$half - 1];

            if ($firstHome !== null && $firstAway !== null) {
                $schedule[$round][] = [$firstHome, $firstAway];
            }

            // Remaining pairs
            for ($i = 0; $i < $half - 1; $i++) {
                $home = $top[$i]    ?? null;
                $away = $bottom[$i] ?? null;

                if ($home !== null && $away !== null) {
                    $schedule[$round][] = [$home, $away];
                }
            }

            // Rotate ring clockwise
            array_unshift($ring, array_pop($ring));
        }

        return $schedule;
    }
}
