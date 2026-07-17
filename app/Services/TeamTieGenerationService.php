<?php

namespace App\Services;

use App\Models\TeamEventFormat;
use App\Models\TeamEventFormatRubber;
use App\Models\TeamFixture;
use App\Models\TeamTie;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TeamTieGenerationService
 *
 * Generates team_fixtures (rubbers) for a single TeamTie from the
 * configured TeamEventFormat template.
 *
 * Responsibilities:
 *  - Validate the tie is not already locked.
 *  - Create one TeamFixture row per rubber in format sequence order.
 *  - Idempotent: uses (team_tie_id, rubber_sequence) unique index to
 *    prevent duplicate rubbers.
 *  - Returns the created/existing TeamFixture collection.
 */
class TeamTieGenerationService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate rubbers for the given tie from its draw's format.
     *
     * @param  TeamTie  $tie
     * @param  bool     $allowOverride  If true, skip locked-tie guard.
     * @return Collection<int, TeamFixture>
     *
     * @throws \RuntimeException          if tie is locked and override not set.
     * @throws \InvalidArgumentException  if no format is attached to the draw.
     */
    public function generateForTie(TeamTie $tie, bool $allowOverride = false): Collection
    {
        if (!$allowOverride && $tie->isLocked()) {
            throw new \RuntimeException(
                "Tie #{$tie->id} (status: {$tie->status}) is locked. " .
                "Pass allowOverride=true to force rubber regeneration."
            );
        }

        $draw   = $tie->draw;
        $format = $draw?->teamEventFormat;

        if (!$format) {
            throw new \InvalidArgumentException(
                "Draw #{$draw?->id} has no team event format attached. Attach a format before generating rubbers."
            );
        }

        return $this->generateFromFormat($tie, $format, $allowOverride);
    }

    /**
     * Generate rubbers for the given tie using a specific format.
     *
     * @param  TeamTie          $tie
     * @param  TeamEventFormat  $format
     * @param  bool             $allowOverride
     * @return Collection<int, TeamFixture>
     */
    public function generateFromFormat(
        TeamTie        $tie,
        TeamEventFormat $format,
        bool           $allowOverride = false
    ): Collection {
        if (!$allowOverride && $tie->isLocked()) {
            throw new \RuntimeException(
                "Tie #{$tie->id} is locked and cannot be regenerated without override."
            );
        }

        /** @var Collection<int, TeamEventFormatRubber> $rubberTemplates */
        $rubberTemplates = $format->rubbers;

        if ($rubberTemplates->isEmpty()) {
            throw new \InvalidArgumentException(
                "Format #{$format->id} has no rubber definitions. Add rubber definitions before generating."
            );
        }

        return DB::transaction(function () use ($tie, $rubberTemplates) {
            $created = collect();

            foreach ($rubberTemplates as $template) {
                $rubber = TeamFixture::firstOrCreate(
                    [
                        'team_tie_id'      => $tie->id,
                        'rubber_sequence'  => $template->sequence,
                    ],
                    [
                        'draw_id'               => $tie->draw_id,
                        'round_nr'              => $tie->round_nr,
                        'tie_nr'                => $tie->tie_nr,
                        'fixture_type'          => $template->rubber_code,
                        'rubber_code'           => $template->rubber_code,
                        'rubber_name'           => $template->name,
                        'gender_rule'           => $template->gender_rule,
                        'player_count_per_team' => $template->playerCountPerTeam(),
                        'match_nr'              => $template->sequence,
                        'numSets'               => 3,
                    ]
                );

                $created->push($rubber);
            }

            Log::info('[TeamTieGenerationService] Rubbers generated', [
                'tie_id'  => $tie->id,
                'rubbers' => $created->count(),
            ]);

            return $created;
        });
    }

    /**
     * Generate rubbers for all ties in a draw.
     *
     * @param  \App\Models\Draw  $draw
     * @param  bool              $allowOverride
     * @return Collection<int, TeamFixture>  All created rubbers across all ties.
     */
    public function generateForAllTies(\App\Models\Draw $draw, bool $allowOverride = false): Collection
    {
        $format = $draw->teamEventFormat;

        if (!$format) {
            throw new \InvalidArgumentException(
                "Draw #{$draw->id} has no team event format attached."
            );
        }

        $all = collect();

        foreach ($draw->teamTies as $tie) {
            $rubbers = $this->generateFromFormat($tie, $format, $allowOverride);
            $all = $all->merge($rubbers);
        }

        return $all;
    }
}
