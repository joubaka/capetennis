<?php

namespace App\Helpers;

use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Support\Collection;

class DrawPlayoffGenerator
{
    public static function generate(Draw $draw): array
    {
        [$boxes, $fixtureMap] = self::groupData($draw);
        $finalPositions = self::rankPlayers($boxes, $fixtureMap);
        $codeToReg = self::mapSeedingCodes($boxes, $finalPositions);
        $drawSets = self::buildDrawSets($draw, $codeToReg);

        self::deleteOldFixtures($draw->id);
        self::createFixtures($draw->id, $drawSets);

        $fixtureMap = Fixture::where('draw_id', $draw->id)
            ->where('stage', '!=', 'RR')
            ->get()
            ->mapWithKeys(fn($f) => [$f->draw_group . '-' . $f->match_nr => $f->id]);

        return [
            'message' => 'Fixtures created successfully.',
            'fixture_map' => $fixtureMap,
        ];
    }

    protected static function groupData(Draw $draw): array
    {
        $boxes = $draw->registrations
            ->filter(fn($r) => $r->pivot->box_number)
            ->groupBy(fn($r) => $r->pivot->box_number);

        $fixtureMap = $draw->drawFixtures->groupBy('draw_group_id');

        return [$boxes, $fixtureMap];
    }

    protected static function rankPlayers($boxes, $fixtureMap): array
    {
        $finalPositions = [];

        foreach ($boxes as $boxNumber => $registrations) {
            $boxFixtures = $fixtureMap[$boxNumber] ?? collect();
            $stats = [];

            foreach ($registrations as $reg) {
                $rid = $reg->id;
                $wins = 0;
                $games = 0;

                foreach ($boxFixtures as $f) {
                    foreach ($f->fixtureResults as $r) {
                        if ($r->winner_registration === $rid) $wins++;
                        if ($f->registration1_id === $rid) $games += $r->registration1_score;
                        elseif ($f->registration2_id === $rid) $games += $r->registration2_score;
                    }
                }

                $stats[$rid] = ['wins' => $wins, 'games' => $games];
            }

            $rankings = collect($stats)
                ->map(fn($s, $rid) => ['rid' => $rid] + $s)
                ->sort(function ($a, $b) use ($boxFixtures) {
                    if ($a['wins'] !== $b['wins']) return $b['wins'] <=> $a['wins'];
                    if ($a['games'] !== $b['games']) return $b['games'] <=> $a['games'];
                    $fixture = $boxFixtures->first(fn($f) =>
                        ($f->registration1_id === $a['rid'] && $f->registration2_id === $b['rid']) ||
                        ($f->registration1_id === $b['rid'] && $f->registration2_id === $a['rid'])
                    );
                    return $fixture && $fixture->fixtureResults->first()?->winner_registration === $a['rid'] ? -1 : 1;
                })
                ->values()
                ->pluck('rid')
                ->flip();

            foreach ($registrations as $reg) {
                $position = $rankings[$reg->id] ?? null;
                $finalPositions[$reg->id] = $position !== null ? $position + 1 : null;
            }
        }

        return $finalPositions;
    }

    protected static function mapSeedingCodes($boxes, $finalPositions): array
    {
        $codeToReg = [];
        $boxLetters = range('A', 'Z');

        foreach ($boxes as $boxNumber => $registrations) {
            $sorted = collect($registrations)
                ->sortBy(fn($r) => $finalPositions[$r->id] ?? 999)
                ->values();

            foreach ($sorted as $i => $reg) {
                $code = $boxLetters[$boxNumber - 1] . ($i + 1);
                $codeToReg[$code] = $reg;
            }
        }

        return $codeToReg;
    }

    protected static function buildDrawSets(Draw $draw, $codeToReg): array
    {
        $sets = [];

        if ($draw->settings->boxes == 2) {
dd('2');
            $basePattern = ['A', 'B', 'A', 'B', 'A', 'B', 'A', 'B'];
            $positionPattern = [1, 4, 3, 2, 2, 3, 4, 1];
            $boxCodes = array_values(array_unique(array_map(fn($code) => substr($code, 0, 1), array_keys($codeToReg))));

            for ($i = 0; $i < count($boxCodes); $i += 2) {
                $boxA = $boxCodes[$i];
                $boxB = $boxCodes[$i + 1] ?? null;
                if (!$boxB) break;

                $playersA = array_filter($codeToReg, fn($_, $code) => str_starts_with($code, $boxA), ARRAY_FILTER_USE_BOTH);
                $playersB = array_filter($codeToReg, fn($_, $code) => str_starts_with($code, $boxB), ARRAY_FILTER_USE_BOTH);
                $maxSeeds = max(count($playersA), count($playersB));
                $numDraws = ceil($maxSeeds / 4);

                for ($d = 0; $d < $numDraws; $d++) {
                    $offset = $d * 4;
                    $sets[] = collect($basePattern)->map(function ($letter, $j) use ($boxA, $boxB, $positionPattern, $offset, $codeToReg) {
                        $box = $letter === 'A' ? $boxA : $boxB;
                        $code = $box . ($positionPattern[$j] + $offset);
                        return $codeToReg[$code]->id ?? 'Bye';
                    })->toArray();
                }
            }
        } elseif ($draw->settings->boxes == 4) {
           dd('4');
            $basePattern = ['A', 'B', 'C', 'D', 'C', 'D', 'A', 'B'];
            $positionPattern = [1, 2, 2, 1, 1, 2, 2, 1];

            $maxPosition = collect(array_keys($codeToReg))->map(fn($code) => (int) substr($code, 1))->max();
            $numDraws = ceil($maxPosition / 2);

            for ($d = 0; $d < $numDraws; $d++) {
                $offset = $d * 2;
                $sets[] = collect($basePattern)->map(function ($letter, $i) use ($positionPattern, $offset, $codeToReg) {
                    $pos = $positionPattern[$i] + $offset;
                    $code = $letter . $pos;
                    return $codeToReg[$code]->id ?? 'Bye';
                })->toArray();
            }
        }

        return $sets;
    }

    protected static function deleteOldFixtures($drawId): void
    {
        Fixture::where('draw_id', $drawId)
            ->where('stage', '!=', 'RR')
            ->delete();
    }

    protected static function createFixtures($drawId, $drawSets): void
    {
        foreach ($drawSets as $groupIndex => $codeSet) {
            $qfFixtures = [];

            // QFs
            for ($i = 0; $i < 4; $i++) {
                $r1 = $codeSet[$i * 2];
                $r2 = $codeSet[$i * 2 + 1];
                if (!is_numeric($r1) && !is_numeric($r2)) continue;

                $fixture = Fixture::create([
                    'draw_id' => $drawId,
                    'registration1_id' => is_numeric($r1) ? $r1 : null,
                    'registration2_id' => is_numeric($r2) ? $r2 : null,
                    'stage' => 'QF',
                    'match_nr' => $i + 1,
                    'draw_group' => $groupIndex + 1,
                ]);

                $qfFixtures[] = $fixture;
            }

            // SFs
            $sf1 = Fixture::create(['draw_id' => $drawId, 'stage' => 'SF', 'match_nr' => 5, 'draw_group' => $groupIndex + 1]);
            $sf2 = Fixture::create(['draw_id' => $drawId, 'stage' => 'SF', 'match_nr' => 6, 'draw_group' => $groupIndex + 1]);
            if (isset($qfFixtures[0])) $qfFixtures[0]->update(['parent_fixture_id' => $sf1->id]);
            if (isset($qfFixtures[1])) $qfFixtures[1]->update(['parent_fixture_id' => $sf1->id]);
            if (isset($qfFixtures[2])) $qfFixtures[2]->update(['parent_fixture_id' => $sf2->id]);
            if (isset($qfFixtures[3])) $qfFixtures[3]->update(['parent_fixture_id' => $sf2->id]);

            // Final
            $final = Fixture::create(['draw_id' => $drawId, 'stage' => 'F', 'match_nr' => 7, 'draw_group' => $groupIndex + 1]);
            $sf1->update(['parent_fixture_id' => $final->id]);
            $sf2->update(['parent_fixture_id' => $final->id]);

            // Consolation
            $csf1 = Fixture::create(['draw_id' => $drawId, 'stage' => 'C-SF1', 'match_nr' => 8, 'draw_group' => $groupIndex + 1]);
            $csf2 = Fixture::create(['draw_id' => $drawId, 'stage' => 'C-SF2', 'match_nr' => 9, 'draw_group' => $groupIndex + 1]);
            $cf = Fixture::create(['draw_id' => $drawId, 'stage' => 'C-F', 'match_nr' => 10, 'draw_group' => $groupIndex + 1]);
            $csf1->update(['parent_fixture_id' => $cf->id]);
            $csf2->update(['parent_fixture_id' => $cf->id]);

            // 7/8 Playoff
            $seventh = Fixture::create(['draw_id' => $drawId, 'stage' => '7/8', 'match_nr' => 11, 'draw_group' => $groupIndex + 1]);
            $csf1->update(['loser_parent_fixture_id' => $seventh->id]);
            $csf2->update(['loser_parent_fixture_id' => $seventh->id]);
        }
    }
}
