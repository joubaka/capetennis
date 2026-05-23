<?php

namespace App\Domain\Draws\Services;

use App\Domain\Draws\Guards\DrawGuard;
use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PlayoffGenerationService
 *
 * Canonical service for creating playoff (MAIN) and plate (PLATE) bracket fixtures.
 *
 * Responsibilities:
 *   - Fixture tree creation only
 *   - Parent/loser-parent linkage
 *   - Seed assignment into first-round slots
 *
 * Does NOT:
 *   - calculate standings
 *   - advance scores
 *   - schedule matches
 *   - render anything
 */
final class PlayoffGenerationService
{
    // Match-number constants for the main bracket (matches 2001–2004)
    public const MAIN_SF1    = 2001;
    public const MAIN_SF2    = 2002;
    public const MAIN_FINAL  = 2003;
    public const MAIN_THIRD  = 2004;

    // Match-number constants for the plate bracket (matches 3001–3011)
    public const PLATE_QF1   = 3001;
    public const PLATE_QF2   = 3002;
    public const PLATE_QF3   = 3003;
    public const PLATE_QF4   = 3004;
    public const PLATE_SF1   = 3005;
    public const PLATE_SF2   = 3006;
    public const PLATE_FEED1 = 3007;
    public const PLATE_FEED2 = 3008;
    public const PLATE_FINAL = 3009;
    public const PLATE_THIRD = 3010;
    public const PLATE_7TH   = 3011;

    // ------------------------------------------------------------------
    // PUBLIC API — MAIN BRACKET
    // ------------------------------------------------------------------

    /**
     * Create MAIN bracket fixtures (SF + Final + 3rd/4th).
     *
     * Accepted seed keys:
     *   4-group: A1, B1, C1, D1
     *   2-group: A1, A2, B1, B2
     *
     * @throws \RuntimeException on locked draw or unrecognised seed layout.
     */
    public function createMainBracket(Draw $draw, array $seeds): array
    {
        DrawGuard::requireMutable($draw, 'create main bracket for');

        return DB::transaction(function () use ($draw, $seeds) {
            // Clear old MAIN fixtures.
            Fixture::where('draw_id', $draw->id)->where('stage', 'MAIN')->delete();

            // Finals / 3rd-place are always the same regardless of group count.
            $final = $this->make($draw, 'MAIN', 2, self::MAIN_FINAL, position: 3);
            $third = $this->make($draw, 'MAIN', 2, self::MAIN_THIRD, position: 4);

            // 4-group mode: A1 vs D1 and B1 vs C1
            if (isset($seeds['A1'], $seeds['B1'], $seeds['C1'], $seeds['D1'])) {
                return $this->createMain4Group($draw, $seeds, $final, $third);
            }

            // 2-group mode: A1 vs B2 and B1 vs A2
            if (isset($seeds['A1'], $seeds['A2'], $seeds['B1'], $seeds['B2'])) {
                return $this->createMain2Group($draw, $seeds, $final, $third);
            }

            throw new \RuntimeException(
                'PlayoffGenerationService: unrecognised seed layout. '
                . 'Expected keys A1/B1/C1/D1 (4-group) or A1/A2/B1/B2 (2-group).'
            );
        });
    }

    // ------------------------------------------------------------------
    // PUBLIC API — PLATE BRACKET (2nd/3rd seeded)
    // ------------------------------------------------------------------

    /**
     * Create PLATE bracket fixtures.
     *
     * Required seed keys: A2, A3, B2, B3, C2, C3, D2, D3
     *
     * @throws \RuntimeException on locked draw or missing seeds.
     */
    public function createPlateBracket(Draw $draw, array $seeds): array
    {
        DrawGuard::requireMutable($draw, 'create plate bracket for');

        $required = ['A2', 'A3', 'B2', 'B3', 'C2', 'C3', 'D2', 'D3'];
        foreach ($required as $key) {
            if (! isset($seeds[$key])) {
                throw new \RuntimeException(
                    "PlayoffGenerationService: missing seed key '{$key}' for plate bracket."
                );
            }
        }

        return DB::transaction(function () use ($draw, $seeds) {
            Fixture::where('draw_id', $draw->id)->where('stage', 'PLATE')->delete();
            return $this->buildPlateBracket($draw, $seeds);
        });
    }

    // ------------------------------------------------------------------
    // MAIN BRACKET BUILDERS
    // ------------------------------------------------------------------

    private function createMain4Group(Draw $draw, array $s, Fixture $final, Fixture $third): array
    {
        $sf1 = $this->make($draw, 'MAIN', 1, self::MAIN_SF1,
            position: 1, reg1: $s['A1'], reg2: $s['D1']);
        $sf2 = $this->make($draw, 'MAIN', 1, self::MAIN_SF2,
            position: 2, reg1: $s['B1'], reg2: $s['C1']);

        $this->linkParents($sf1, $sf2, $final, $third);

        Log::info('[Playoff] MAIN 4-group bracket created', ['draw_id' => $draw->id]);
        return compact('sf1', 'sf2', 'final', 'third');
    }

    private function createMain2Group(Draw $draw, array $s, Fixture $final, Fixture $third): array
    {
        $sf1 = $this->make($draw, 'MAIN', 1, self::MAIN_SF1,
            position: 1, reg1: $s['A1'], reg2: $s['B2']);
        $sf2 = $this->make($draw, 'MAIN', 1, self::MAIN_SF2,
            position: 2, reg1: $s['B1'], reg2: $s['A2']);

        $this->linkParents($sf1, $sf2, $final, $third);

        Log::info('[Playoff] MAIN 2-group bracket created', ['draw_id' => $draw->id]);
        return compact('sf1', 'sf2', 'final', 'third');
    }

    /** Link SF1/SF2 winners → Final, losers → 3rd/4th. */
    private function linkParents(Fixture $sf1, Fixture $sf2, Fixture $final, Fixture $third): void
    {
        $sf1->parent_fixture_id       = $final->id;
        $sf2->parent_fixture_id       = $final->id;
        $sf1->loser_parent_fixture_id = $third->id;
        $sf2->loser_parent_fixture_id = $third->id;
        $sf1->save();
        $sf2->save();
    }

    // ------------------------------------------------------------------
    // PLATE BRACKET BUILDER
    // ------------------------------------------------------------------

    private function buildPlateBracket(Draw $draw, array $s): array
    {
        // Round 1 – QFs
        $qf1 = $this->make($draw, 'PLATE', 1, self::PLATE_QF1, position: 1, reg1: $s['A2'], reg2: $s['D3']);
        $qf2 = $this->make($draw, 'PLATE', 1, self::PLATE_QF2, position: 2, reg1: $s['B2'], reg2: $s['C3']);
        $qf3 = $this->make($draw, 'PLATE', 1, self::PLATE_QF3, position: 3, reg1: $s['C2'], reg2: $s['B3']);
        $qf4 = $this->make($draw, 'PLATE', 1, self::PLATE_QF4, position: 4, reg1: $s['D2'], reg2: $s['A3']);

        // Round 2 – SFs
        $sf1 = $this->make($draw, 'PLATE', 2, self::PLATE_SF1, position: 5);
        $sf2 = $this->make($draw, 'PLATE', 2, self::PLATE_SF2, position: 6);

        // QF winners → SF
        $qf1->parent_fixture_id = $sf1->id;
        $qf2->parent_fixture_id = $sf1->id;
        $qf3->parent_fixture_id = $sf2->id;
        $qf4->parent_fixture_id = $sf2->id;
        $qf1->save(); $qf2->save(); $qf3->save(); $qf4->save();

        // Round 3 – Feed matches (MAIN SF losers feed in here)
        $feed1 = $this->make($draw, 'PLATE', 3, self::PLATE_FEED1, position: 7);
        $feed2 = $this->make($draw, 'PLATE', 3, self::PLATE_FEED2, position: 8);

        // PLATE SF winners → feed matches
        $sf1->parent_fixture_id = $feed1->id;
        $sf2->parent_fixture_id = $feed2->id;
        $sf1->save(); $sf2->save();

        // Round 4 – Plate final + 3rd/4th + 7th/8th
        $plateFinal = $this->make($draw, 'PLATE', 4, self::PLATE_FINAL, position: 9);
        $plateThird = $this->make($draw, 'PLATE', 4, self::PLATE_THIRD, position: 10);
        $plate7th   = $this->make($draw, 'PLATE', 4, self::PLATE_7TH,   position: 11);

        // Feed match winners → plate final
        $feed1->parent_fixture_id       = $plateFinal->id;
        $feed2->parent_fixture_id       = $plateFinal->id;
        // Feed match losers → 7th/8th (QF SF losers → 3rd/4th handled below)
        $feed1->loser_parent_fixture_id = $plate7th->id;
        $feed2->loser_parent_fixture_id = $plate7th->id;
        $feed1->save(); $feed2->save();

        // PLATE SF losers → 3rd/4th
        $sf1->loser_parent_fixture_id = $plateThird->id;
        $sf2->loser_parent_fixture_id = $plateThird->id;
        $sf1->save(); $sf2->save();

        // QF losers → consolation SFs (routing through sf1/sf2's loser paths already handled)
        $qf1->loser_parent_fixture_id = $sf2->id;
        $qf2->loser_parent_fixture_id = $sf1->id;
        $qf3->loser_parent_fixture_id = $sf1->id;
        $qf4->loser_parent_fixture_id = $sf2->id;
        $qf1->save(); $qf2->save(); $qf3->save(); $qf4->save();

        Log::info('[Playoff] PLATE bracket created', ['draw_id' => $draw->id]);

        return [
            'qf1'   => $qf1,   'qf2'   => $qf2,
            'qf3'   => $qf3,   'qf4'   => $qf4,
            'sf1'   => $sf1,   'sf2'   => $sf2,
            'feed1' => $feed1, 'feed2' => $feed2,
            'final' => $plateFinal,
            'third' => $plateThird,
            'seventh' => $plate7th,
        ];
    }

    // ------------------------------------------------------------------
    // FACTORY HELPER
    // ------------------------------------------------------------------

    private function make(
        Draw   $draw,
        string $stage,
        int    $round,
        int    $matchNr,
        ?int   $position = null,
        ?int   $reg1     = null,
        ?int   $reg2     = null,
        ?int   $parent   = null,
        ?int   $losersTo = null,
    ): Fixture {
        return Fixture::create(array_filter([
            'draw_id'                  => $draw->id,
            'stage'                    => $stage,
            'round'                    => $round,
            'match_nr'                 => $matchNr,
            'position'                 => $position,
            'registration1_id'         => $reg1,
            'registration2_id'         => $reg2,
            'parent_fixture_id'        => $parent,
            'loser_parent_fixture_id'  => $losersTo,
            'match_status'             => 0,
        ], fn($v) => ! is_null($v)));
    }
}
