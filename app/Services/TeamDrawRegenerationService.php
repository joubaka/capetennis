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
 * TeamDrawRegenerationService
 *
 * Orchestrates idempotent, transactional regeneration of team ties
 * and their rubbers for a draw.
 *
 * Lifecycle safety:
 *  - Blocks destructive regeneration of published/completed ties unless
 *    explicit override confirmation is provided.
 *  - When regenerating ties, only draft ties are deleted; locked ties are
 *    left untouched (unless overridden).
 *  - When regenerating rubbers, only non-locked tie rubbers are rebuilt.
 */
class TeamDrawRegenerationService
{
    public function __construct(
        private readonly TeamDrawGenerationService $drawGenerator,
        private readonly TeamTieGenerationService  $tieGenerator
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Regenerate ties (and optionally rubbers) for the given draw.
     *
     * By default only draft ties are replaced. Pass $allowOverride = true
     * to also force-rebuild published/completed ties (requires intentional
     * admin confirmation on the UI before calling this method).
     *
     * @param  Draw                  $draw
     * @param  Collection<int, Team> $teams
     * @param  TeamEventFormat|null  $format
     * @param  bool                  $regenerateRubbers  Whether to rebuild rubbers after ties.
     * @param  bool                  $allowOverride      Unlock guard for locked ties.
     * @return array{ ties: Collection, rubbers: Collection }
     *
     * @throws TeamDrawConflictException if locked ties exist and override is false.
     */
    public function regenerate(
        Draw           $draw,
        Collection     $teams,
        ?TeamEventFormat $format = null,
        bool           $regenerateRubbers = false,
        bool           $allowOverride = false
    ): array {
        $lockedCount = $draw->teamTies()->locked()->count();

        if ($lockedCount > 0 && !$allowOverride) {
            throw new TeamDrawConflictException(
                "Draw #{$draw->id} has {$lockedCount} published/completed tie(s). " .
                "Confirm override to force regeneration."
            );
        }

        return DB::transaction(function () use ($draw, $teams, $format, $regenerateRubbers, $allowOverride) {
            Log::info('[TeamDrawRegenerationService] Starting regeneration', [
                'draw_id'       => $draw->id,
                'team_count'    => $teams->count(),
                'allow_override'=> $allowOverride,
            ]);

            // Remove draft ties (and cascade their rubbers via DB)
            $this->purgeDraftTies($draw, $allowOverride);

            // Rebuild ties
            $ties = $this->drawGenerator->generate($draw, $teams, $format, $allowOverride);

            // Optionally rebuild rubbers
            $rubbers = collect();
            if ($regenerateRubbers) {
                $rubbers = $this->tieGenerator->generateForAllTies($draw, $allowOverride);
            }

            Log::info('[TeamDrawRegenerationService] Regeneration complete', [
                'draw_id' => $draw->id,
                'ties'    => $ties->count(),
                'rubbers' => $rubbers->count(),
            ]);

            return ['ties' => $ties, 'rubbers' => $rubbers];
        });
    }

    /**
     * Regenerate only the rubbers for existing ties in a draw.
     *
     * Skips locked ties unless $allowOverride is true.
     *
     * @param  Draw   $draw
     * @param  bool   $allowOverride
     * @return Collection<int, \App\Models\TeamFixture>
     */
    public function regenerateRubbersOnly(Draw $draw, bool $allowOverride = false): Collection
    {
        $format = $draw->teamEventFormat;

        if (!$format) {
            throw new \InvalidArgumentException(
                "Draw #{$draw->id} has no team event format. Attach one before regenerating rubbers."
            );
        }

        return DB::transaction(function () use ($draw, $format, $allowOverride) {
            $all = collect();

            foreach ($draw->teamTies as $tie) {
                if ($tie->isLocked() && !$allowOverride) {
                    Log::info('[TeamDrawRegenerationService] Skipping locked tie', ['tie_id' => $tie->id]);
                    continue;
                }

                // Delete existing rubbers for this tie
                $tie->rubbers()->delete();

                $rubbers = $this->tieGenerator->generateFromFormat($tie, $format, $allowOverride);
                $all     = $all->merge($rubbers);
            }

            Log::info('[TeamDrawRegenerationService] Rubbers regenerated', [
                'draw_id' => $draw->id,
                'rubbers' => $all->count(),
            ]);

            return $all;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function purgeDraftTies(Draw $draw, bool $allowOverride): void
    {
        $query = $draw->teamTies();

        if (!$allowOverride) {
            // Only purge draft ties; leave locked ties alone
            $query->whereNotIn('status', [TeamTie::STATUS_PUBLISHED, TeamTie::STATUS_COMPLETED]);
        }

        // CASCADE DELETE on team_ties.id → team_fixtures.team_tie_id handles rubber cleanup.
        $query->delete();
    }
}
