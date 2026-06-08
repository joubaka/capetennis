<?php

namespace App\Domain\Draws\Services;

use App\Models\Draw;

/**
 * DrawStatusService
 *
 * Computes the workflow completion status for a Round Robin draw.
 * Pure read-only — makes no DB mutations.
 *
 * Returned array shape:
 * [
 *   'groups_configured'  => bool,
 *   'fixtures_generated' => bool,
 *   'rr_total'           => int,
 *   'rr_played'          => int,
 *   'rr_complete_pct'    => int   (0–100),
 *   'rr_complete'        => bool,
 *   'standings_ready'    => bool,
 *   'brackets_generated' => bool,
 *   'locked'             => bool,
 *   'published'          => bool,
 *   'warnings'           => string[],
 * ]
 */
final class DrawStatusService
{
    // ------------------------------------------------------------------
    // PUBLIC API
    // ------------------------------------------------------------------

    public function status(Draw $draw): array
    {
        $draw->loadMissing([
            'groups.groupRegistrations',
            'drawFixtures.fixtureResults',
            'settings',
        ]);

        $warnings = [];

        // ── 1. Groups configured? ─────────────────────────────────────
        $groupsConfigured = $draw->groups->isNotEmpty()
            && $draw->groups->every(fn ($g) => $g->groupRegistrations->isNotEmpty());

        if (!$groupsConfigured) {
            $warnings[] = 'Players have not been assigned to all groups yet.';
        }

        // ── 2. RR fixtures generated? ─────────────────────────────────
        $rrFixtures = $draw->drawFixtures->where('stage', 'RR');
        $fixturesGenerated = $rrFixtures->isNotEmpty();

        if (!$fixturesGenerated) {
            $warnings[] = 'Round robin fixtures have not been generated yet.';
        }

        // ── 3. RR completion % ────────────────────────────────────────
        $rrTotal  = $rrFixtures->count();
        $rrPlayed = $rrFixtures->filter(
            fn ($fx) => $fx->fixtureResults->isNotEmpty()
        )->count();

        $rrPct      = $rrTotal > 0 ? (int) round(($rrPlayed / $rrTotal) * 100) : 0;
        $rrComplete = $rrTotal > 0 && $rrPlayed === $rrTotal;

        if ($fixturesGenerated && !$rrComplete) {
            $remaining = $rrTotal - $rrPlayed;
            $warnings[] = "{$remaining} round robin match" . ($remaining === 1 ? '' : 'es') . ' still need scores.';
        }

        // ── 4. Standings ready? (all matches scored) ──────────────────
        $standingsReady = $rrComplete;

        // ── 5. Playoff brackets generated? ───────────────────────────
        $bracketStages = ['MAIN', 'PLATE', 'CONS', 'BOWL', 'SHIELD', 'SPOON'];
        $playoffConfig = optional($draw->settings)->playoff_config ?? [];
        $configStages  = collect($playoffConfig)
            ->where('enabled', true)
            ->pluck('slug')
            ->map(fn ($s) => strtoupper($s))
            ->toArray();

        $checkStages      = array_unique(array_merge($bracketStages, $configStages));
        $bracketsGenerated = $draw->drawFixtures
            ->whereIn('stage', $checkStages)
            ->isNotEmpty();

        if ($standingsReady && !$bracketsGenerated) {
            $warnings[] = 'Standings are complete — playoffs can now be generated.';
        }

        return [
            'groups_configured'  => $groupsConfigured,
            'fixtures_generated' => $fixturesGenerated,
            'rr_total'           => $rrTotal,
            'rr_played'          => $rrPlayed,
            'rr_complete_pct'    => $rrPct,
            'rr_complete'        => $rrComplete,
            'standings_ready'    => $standingsReady,
            'brackets_generated' => $bracketsGenerated,
            'locked'             => (bool) $draw->locked,
            'published'          => (bool) $draw->published,
            'warnings'           => $warnings,
        ];
    }

    /**
     * Quick boolean check used by the bracket generation guard.
     */
    public function isRRComplete(Draw $draw): bool
    {
        $draw->loadMissing(['drawFixtures.fixtureResults']);
        $rr = $draw->drawFixtures->where('stage', 'RR');
        return $rr->isNotEmpty()
            && $rr->every(fn ($fx) => $fx->fixtureResults->isNotEmpty());
    }

    /**
     * Quick boolean: does any RR fixture already have results?
     */
    public function hasAnyResults(Draw $draw): bool
    {
        $draw->loadMissing(['drawFixtures.fixtureResults']);
        return $draw->drawFixtures
            ->where('stage', 'RR')
            ->some(fn ($fx) => $fx->fixtureResults->isNotEmpty());
    }
}
