<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DynamicBracketEngine
{
    protected Draw $draw;
    protected Collection $fixtures;
    protected array $seedOriginMap = [];

    // SVG Layout Constants
    const BOX_WIDTH = 190;
    const BOX_HEIGHT = 60;
    const ROUND_GAP = 200;
    const MATCH_GAP_BASE = 40;
    const START_X = 60;
    const START_Y = 70;
    const BRACKET_GAP = 80;

    public function __construct(Draw $draw)
    {
        $this->draw = $draw;

        // Determine all stages from playoff config (covers custom slugs like P6, P7, etc.)
        $playoffConfig = optional($draw->settings)->playoff_config ?? [];
        $stages = ['MAIN', 'PLATE', 'CONS', 'BOWL', 'SHIELD', 'SPOON'];
        foreach ($playoffConfig as $cfg) {
            $s = strtoupper($cfg['slug'] ?? '');
            if ($s && !in_array($s, $stages)) {
                $stages[] = $s;
            }
        }

        $this->fixtures = Fixture::where('draw_id', $draw->id)
            ->whereIn('stage', $stages)
            ->with(['registration1.players', 'registration2.players', 'fixtureResults'])
            ->orderBy('stage')
            ->orderBy('round')
            ->orderBy('match_nr')
            ->get();
        
        Log::info('🎾 DynamicBracketEngine initialized', [
            'draw_id' => $draw->id,
            'total_fixtures' => $this->fixtures->count(),
        ]);
    }

    public function build(): array
    {
        $this->seedOriginMap = $this->buildSeedOriginMap();
        $byeSlots = $this->buildByeSlots();

        $result = [
            'brackets' => [],
            'totalHeight' => 0,
            'totalWidth' => 0,
            'seedOriginMap' => $this->seedOriginMap,
            'byeSlots' => $byeSlots,
        ];
        $currentY = self::START_Y;
        $overallPosition = 1; // Running counter for tournament positions

        $playoffConfig = optional($this->draw->settings)->playoff_config ?? [];

        foreach ($playoffConfig as $config) {
            if (!($config['enabled'] ?? false)) continue;

            $slug = $config['slug'] ?? 'unknown';
            $stage = $this->slugToStage($slug);
            $size = $config['size'] ?? 4;
            $positions = $config['positions'] ?? [];
            $bracketData = $this->buildBracket($stage, $size, $currentY, $positions);
            
            if (!empty($bracketData['rounds'])) {
                $posFrom = $overallPosition;
                $posTo   = $overallPosition + $size - 1;

                $result['brackets'][] = array_merge($bracketData, [
                    'name' => $config['name'] ?? 'Bracket',
                    'slug' => $slug,
                    'positions' => $positions,
                    'posFrom' => $posFrom,
                    'posTo' => $posTo,
                    'startY' => $currentY,
                ]);
                $overallPosition += $size;
                $currentY += $bracketData['height'] + self::BRACKET_GAP;
            }
        }

        $result['totalHeight'] = $currentY;
        $result['totalWidth'] = 1400; 
        return $result;
    }

    protected function buildBracket(string $stage, int $size, int $startY, array $positions = []): array
    {
        $stageFixtures = $this->fixtures->where('stage', $stage);
        $hasFixtures = $stageFixtures->isNotEmpty();

        $numRounds = (int) ceil(log($size, 2));
        $rounds = [];
        $maxHeight = 0;

        // Separate main bracket from position playoffs
        $mainFixtures = $hasFixtures 
            ? $stageFixtures->filter(fn($fx) => is_null($fx->playoff_type))
            : collect();

        // Virtual seed labels for empty bracket
        $groupNames = $this->draw->groups->sortBy('name')->pluck('name')->values()->all();
        $seedLabels = [];
        foreach ($positions as $pos) {
            foreach ($groupNames as $gn) {
                $seedLabels[] = $gn . $pos;
            }
        }
        $matchups = $this->getStandardBracketMatchups($size);
        $virtualMatchNr = 0;

        if ($hasFixtures) {
            Log::info("🎯 Main bracket fixtures filtered", [
                'total' => $mainFixtures->count(),
                'by_round' => $mainFixtures->groupBy('round')->map->count()->toArray(),
            ]);
        } else {
            Log::info("🎯 Building empty bracket structure for stage {$stage}, size {$size}");
        }

        for ($round = 1; $round <= $numRounds; $round++) {
            $expectedMatches = pow(2, $numRounds - $round);
            $roundFixtures = $mainFixtures->where('round', $round)->sortBy('match_nr')->values();
            
            $matchesInRound = [];
            $roundX = self::START_X + (($round - 1) * self::ROUND_GAP);
            $matchSpacing = (self::MATCH_GAP_BASE + self::BOX_HEIGHT) * pow(2, $round - 1);

            for ($i = 0; $i < $expectedMatches; $i++) {
                $child1MidY = null;
                $child2MidY = null;

                if ($round === 1) {
                    $y = $startY + ($i * $matchSpacing);
                } else {
                    $prevRoundMatches = $rounds[$round - 1];
                    $child1 = $prevRoundMatches[$i * 2] ?? null;
                    $child2 = $prevRoundMatches[$i * 2 + 1] ?? null;
                    
                    if ($child1 && $child2) {
                        $child1MidY = $child1['y'] + ($child1['height'] / 2);
                        $child2MidY = $child2['y'] + ($child2['height'] / 2);
                        $y = $child1MidY;
                    } else {
                        $y = $startY + ($i * $matchSpacing);
                    }
                }

                $fixture = $roundFixtures->get($i);
                $height = ($round === 1 || !$child2MidY) ? self::BOX_HEIGHT : (int)($child2MidY - $child1MidY);
                $virtualMatchNr++;

                // Compute virtual labels for empty bracket display
                $sl1 = null; $sl2 = null; $fl1 = null; $fl2 = null;
                if ($round === 1 && isset($matchups[$i])) {
                    $sl1 = $seedLabels[$matchups[$i][0] - 1] ?? null;
                    $sl2 = $seedLabels[$matchups[$i][1] - 1] ?? null;
                }
                if ($round > 1) {
                    $prevMatches = $rounds[$round - 1];
                    $c1Nr = $prevMatches[$i * 2]['virtualMatchNr'] ?? null;
                    $c2Nr = $prevMatches[$i * 2 + 1]['virtualMatchNr'] ?? null;
                    if ($c1Nr) $fl1 = 'W' . $c1Nr;
                    if ($c2Nr) $fl2 = 'W' . $c2Nr;
                }

                $matchesInRound[] = [
                    'fx' => $fixture,
                    'x' => $roundX,
                    'y' => (int)$y,
                    'width' => self::BOX_WIDTH,
                    'height' => $height,
                    'roundLabel' => $this->getRoundLabel($round, $numRounds),
                    'virtualMatchNr' => $virtualMatchNr,
                    'seedLabel1' => $sl1,
                    'seedLabel2' => $sl2,
                    'feederLabel1' => $fl1,
                    'feederLabel2' => $fl2,
                ];
                $maxHeight = max($maxHeight, $y + $height);
            }
            $rounds[$round] = $matchesInRound;
        }

        // Build position playoffs
        if ($hasFixtures) {
            $playoffData = $this->buildPositionPlayoffs($stageFixtures, $maxHeight + 80, $numRounds);
        } else {
            $playoffData = $this->buildVirtualPositionPlayoffs($rounds, $numRounds, $maxHeight + 80, $virtualMatchNr);
        }
        if (!empty($playoffData['matches'])) {
            $maxHeight = max($maxHeight, $playoffData['maxY']);
        }

        return [
            'rounds' => $rounds,
            'numRounds' => $numRounds,
            'positionPlayoffs' => $playoffData['matches'] ?? [],
            'height' => $maxHeight - $startY + 40,
        ];
    }

    protected function buildPositionPlayoffs(Collection $stageFixtures, int $startY, int $numRounds): array
    {
        $matches = [];
        $maxY = $startY;

        // Filter position playoff fixtures — must have explicit playoff_type
        // (excludes the Final which has position=1 but playoff_type=null)
        $playoffFixtures = $stageFixtures->filter(function($fx) {
            return $fx->playoff_type !== null;
        })->sortBy('match_nr');

        Log::info("🎖️ Position playoff fixtures", [
            'count' => $playoffFixtures->count(),
            'fixtures' => $playoffFixtures->map(fn($fx) => [
                'id' => $fx->id,
                'position' => $fx->position,
                'playoff_type' => $fx->playoff_type,
            ])->toArray(),
        ]);

        if ($playoffFixtures->isEmpty()) {
            Log::info("ℹ️ No position playoff fixtures found");
            return ['matches' => [], 'maxY' => $startY];
        }

        // Consolation SFs
        $consSF = $playoffFixtures->filter(fn($fx) => 
            str_contains($fx->playoff_type ?? '', 'cons_sf')
        )->sortBy('match_nr')->values();
        
        // Position-bearing playoffs (have a position value, sorted ascending)
        $positionPlayoffs = $playoffFixtures->filter(fn($fx) => 
            !is_null($fx->position) && !str_contains($fx->playoff_type ?? '', 'cons_sf')
        )->sortBy('position')->values();

        // First = "3rd/4th" equivalent, second = "5th/6th", third = "7th/8th"
        $thirdFourth   = $positionPlayoffs->get(0);
        $fifthSixth    = $positionPlayoffs->get(1);
        $seventhEighth = $positionPlayoffs->get(2);

        $currentY = $startY;
        $spacing = self::BOX_HEIGHT + 30;

        // 3rd/4th Playoff (align with SF round X position)
        if ($thirdFourth) {
            $xPos = self::START_X + (max(1, $numRounds - 2) * self::ROUND_GAP);
            $pos = $thirdFourth->position;
            $matches[] = [
                'fx' => $thirdFourth,
                'x' => $xPos,
                'y' => $currentY,
                'width' => self::BOX_WIDTH,
                'height' => self::BOX_HEIGHT,
                'label' => $this->ordinalRange($pos) . ' Place',
                'isFinal' => true,
            ];
            $currentY += $spacing + 40;
            $maxY = max($maxY, $currentY);
        }

        // 5th-8th Consolation Bracket
        if ($consSF->count() >= 2 || $fifthSixth || $seventhEighth) {
            $r1X = self::START_X;
            $r2X = self::START_X + self::ROUND_GAP;
            
            // Store Cons SF match data for alignment
            $consSF1Data = null;
            $consSF2Data = null;
            
            // Cons SF matches
            if ($consSF->count() >= 2) {
                $consSF1Y = $currentY;
                $consSF1Data = [
                    'fx' => $consSF[0],
                    'x' => $r1X,
                    'y' => $consSF1Y,
                    'width' => self::BOX_WIDTH,
                    'height' => self::BOX_HEIGHT,
                    'label' => 'Cons SF 1',
                    'isFinal' => false,
                ];
                $matches[] = $consSF1Data;
                
                $consSF2Y = $currentY + ($spacing * 2);
                $consSF2Data = [
                    'fx' => $consSF[1],
                    'x' => $r1X,
                    'y' => $consSF2Y,
                    'width' => self::BOX_WIDTH,
                    'height' => self::BOX_HEIGHT,
                    'label' => 'Cons SF 2',
                    'isFinal' => false,
                ];
                $matches[] = $consSF2Data;
                
                $maxY = max($maxY, $consSF2Y + self::BOX_HEIGHT);
            }
            
            // 5th/6th Playoff (align with Cons SF midpoints)
            if ($fifthSixth && $consSF1Data && $consSF2Data) {
                $consSF1MidY = $consSF1Data['y'] + ($consSF1Data['height'] / 2);
                $consSF2MidY = $consSF2Data['y'] + ($consSF2Data['height'] / 2);
                
                $fifthSixthY = $consSF1MidY;
                $fifthSixthHeight = (int)($consSF2MidY - $consSF1MidY);
                $pos56 = $fifthSixth->position;
                
                $matches[] = [
                    'fx' => $fifthSixth,
                    'x' => $r2X,
                    'y' => $fifthSixthY,
                    'width' => self::BOX_WIDTH,
                    'height' => $fifthSixthHeight,
                    'label' => $this->ordinalRange($pos56) . ' Place',
                    'isFinal' => true,
                ];
            }
            
            // 7th/8th Playoff (losers bracket - positioned below Cons SF, same X column)
            if ($seventhEighth && $consSF2Data) {
                $seventhEighthY = $consSF2Data['y'] + self::BOX_HEIGHT + 40;
                $pos78 = $seventhEighth->position;
                
                $matches[] = [
                    'fx' => $seventhEighth,
                    'x' => $r1X,
                    'y' => $seventhEighthY,
                    'width' => self::BOX_WIDTH,
                    'height' => self::BOX_HEIGHT,
                    'label' => $this->ordinalRange($pos78) . ' Place',
                    'isFinal' => true,
                ];
                $maxY = max($maxY, $seventhEighthY + self::BOX_HEIGHT);
            }
        }

        return ['matches' => $matches, 'maxY' => $maxY];
    }

    protected function getRoundLabel($round, $total) {
        $diff = $total - $round;
        return match($diff) { 0 => 'Final', 1 => 'Semi-Finals', 2 => 'Quarter-Finals', default => "Round $round" };
    }

    protected function ordinalRange(int $pos): string
    {
        return $this->ordinal($pos) . '/' . $this->ordinal($pos + 1);
    }

    protected function ordinal(int $n): string
    {
        $s = ['th','st','nd','rd'];
        $v = $n % 100;
        return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
    }

    protected function slugToStage($slug) {
        return match(strtolower($slug)) { 'main' => 'MAIN', 'plate' => 'PLATE', 'cons' => 'CONS', default => strtoupper($slug) };
    }

    /**
     * Standard bracket seeding matchups (seed1 vs seed2 per match).
     */
    protected function getStandardBracketMatchups(int $size): array
    {
        return match($size) {
            2  => [[1, 2]],
            4  => [[1, 4], [2, 3]],
            8  => [[1, 8], [4, 5], [2, 7], [3, 6]],
            16 => [[1, 16], [8, 9], [4, 13], [5, 12], [2, 15], [7, 10], [3, 14], [6, 11]],
            32 => [
                [1, 32], [16, 17], [8, 25], [9, 24],
                [4, 29], [13, 20], [5, 28], [12, 21],
                [2, 31], [15, 18], [7, 26], [10, 23],
                [3, 30], [14, 19], [6, 27], [11, 22],
            ],
            default => collect(range(1, (int)($size / 2)))->map(fn($i) => [$i * 2 - 1, $i * 2])->toArray(),
        };
    }

    /**
     * Build virtual position playoff entries when no real fixtures exist.
     * Uses virtual match numbers from the main bracket rounds.
     */
    protected function buildVirtualPositionPlayoffs(array $rounds, int $numRounds, int $startY, int $nextNr): array
    {
        $matches = [];
        $maxY = $startY;
        $spacing = self::BOX_HEIGHT + 30;
        $currentY = $startY;

        // 3rd/4th: losers of SF round (requires at least 2 rounds = size >= 4)
        if ($numRounds >= 2) {
            $sfMatches = $rounds[$numRounds - 1] ?? [];
            if (count($sfMatches) >= 2) {
                $sfNr1 = $sfMatches[0]['virtualMatchNr'];
                $sfNr2 = $sfMatches[1]['virtualMatchNr'];
                $nextNr++;

                $xPos = self::START_X + (max(1, $numRounds - 2) * self::ROUND_GAP);
                $matches[] = [
                    'fx' => null,
                    'x' => $xPos,
                    'y' => $currentY,
                    'width' => self::BOX_WIDTH,
                    'height' => self::BOX_HEIGHT,
                    'label' => '3rd/4th Place',
                    'isFinal' => true,
                    'virtualMatchNr' => $nextNr,
                    'seedLabel1' => null,
                    'seedLabel2' => null,
                    'feederLabel1' => 'L' . $sfNr1,
                    'feederLabel2' => 'L' . $sfNr2,
                ];
                $currentY += $spacing + 40;
                $maxY = max($maxY, $currentY);
            }
        }

        // 5th–8th: losers of QF round (requires at least 3 rounds = size >= 8)
        if ($numRounds >= 3) {
            $qfMatches = $rounds[$numRounds - 2] ?? [];
            if (count($qfMatches) >= 4) {
                $qfNr1 = $qfMatches[0]['virtualMatchNr'];
                $qfNr2 = $qfMatches[1]['virtualMatchNr'];
                $qfNr3 = $qfMatches[2]['virtualMatchNr'];
                $qfNr4 = $qfMatches[3]['virtualMatchNr'];

                $r1X = self::START_X;
                $r2X = self::START_X + self::ROUND_GAP;

                // Cons SF 1
                $consSF1Nr = ++$nextNr;
                $consSF1Y = $currentY;
                $matches[] = [
                    'fx' => null, 'x' => $r1X, 'y' => $consSF1Y,
                    'width' => self::BOX_WIDTH, 'height' => self::BOX_HEIGHT,
                    'label' => 'Cons SF 1', 'isFinal' => false,
                    'virtualMatchNr' => $consSF1Nr,
                    'seedLabel1' => null, 'seedLabel2' => null,
                    'feederLabel1' => 'L' . $qfNr1, 'feederLabel2' => 'L' . $qfNr2,
                ];

                // Cons SF 2
                $consSF2Nr = ++$nextNr;
                $consSF2Y = $currentY + ($spacing * 2);
                $matches[] = [
                    'fx' => null, 'x' => $r1X, 'y' => $consSF2Y,
                    'width' => self::BOX_WIDTH, 'height' => self::BOX_HEIGHT,
                    'label' => 'Cons SF 2', 'isFinal' => false,
                    'virtualMatchNr' => $consSF2Nr,
                    'seedLabel1' => null, 'seedLabel2' => null,
                    'feederLabel1' => 'L' . $qfNr3, 'feederLabel2' => 'L' . $qfNr4,
                ];

                // 5th/6th
                $consSF1MidY = $consSF1Y + (self::BOX_HEIGHT / 2);
                $consSF2MidY = $consSF2Y + (self::BOX_HEIGHT / 2);
                $matches[] = [
                    'fx' => null, 'x' => $r2X, 'y' => (int)$consSF1MidY,
                    'width' => self::BOX_WIDTH, 'height' => (int)($consSF2MidY - $consSF1MidY),
                    'label' => '5th/6th Place', 'isFinal' => true,
                    'virtualMatchNr' => ++$nextNr,
                    'seedLabel1' => null, 'seedLabel2' => null,
                    'feederLabel1' => 'W' . $consSF1Nr, 'feederLabel2' => 'W' . $consSF2Nr,
                ];

                // 7th/8th
                $seventhEighthY = $consSF2Y + self::BOX_HEIGHT + 40;
                $matches[] = [
                    'fx' => null, 'x' => $r1X, 'y' => $seventhEighthY,
                    'width' => self::BOX_WIDTH, 'height' => self::BOX_HEIGHT,
                    'label' => '7th/8th Place', 'isFinal' => true,
                    'virtualMatchNr' => ++$nextNr,
                    'seedLabel1' => null, 'seedLabel2' => null,
                    'feederLabel1' => 'L' . $consSF1Nr, 'feederLabel2' => 'L' . $consSF2Nr,
                ];

                $maxY = max($maxY, $seventhEighthY + self::BOX_HEIGHT);
            }
        }

        return ['matches' => $matches, 'maxY' => $maxY, 'nextNr' => $nextNr];
    }

    /**
     * Build a map of fixture_id => ['slot1' => bool, 'slot2' => bool]
     * indicating which slots in consolation/position playoff fixtures
     * are permanent byes (feeder was a bye match with no real loser).
     */
    protected function buildByeSlots(): array
    {
        $byeSlots = [];

        // Find all fixtures that feed losers into another fixture
        $feeders = $this->fixtures->whereNotNull('loser_parent_fixture_id');

        // Group by loser target
        $grouped = $feeders->groupBy('loser_parent_fixture_id');

        foreach ($grouped as $targetId => $feederGroup) {
            $slotIndex = 0;
            foreach ($feederGroup->sortBy('match_nr') as $feeder) {
                $slotIndex++;
                $slotKey = 'slot' . $slotIndex;

                // A feeder is a "bye" if it has a winner but one registration is null
                $isByeFeeder = !is_null($feeder->winner_registration)
                    && (is_null($feeder->registration1_id) || is_null($feeder->registration2_id));

                if ($isByeFeeder) {
                    if (!isset($byeSlots[$targetId])) {
                        $byeSlots[$targetId] = [];
                    }
                    $byeSlots[$targetId][$slotKey] = true;
                }
            }
        }

        return $byeSlots;
    }

    /**
     * Build a map of registration_id => "A1", "B2", etc.
     * based on RR standings (wins, then set diff).
     */
    protected function buildSeedOriginMap(): array
    {
        $map = [];

        $this->draw->loadMissing([
            'groups.groupRegistrations.registration.players',
            'drawFixtures.fixtureResults',
        ]);

        foreach ($this->draw->groups as $group) {
            $standings = [];

            foreach ($group->groupRegistrations as $gr) {
                $regId = $gr->registration_id;
                $standings[$regId] = [
                    'reg_id'    => $regId,
                    'wins'      => 0,
                    'losses'    => 0,
                    'sets_won'  => 0,
                    'sets_lost' => 0,
                ];
            }

            foreach ($this->draw->drawFixtures->where('draw_group_id', $group->id)->where('stage', 'RR') as $fx) {
                if ($fx->fixtureResults->isEmpty()) continue;

                $home = $fx->registration1_id;
                $away = $fx->registration2_id;
                $homeSets = 0;
                $awaySets = 0;

                foreach ($fx->fixtureResults as $set) {
                    if ($set->registration1_score > $set->registration2_score) $homeSets++;
                    else $awaySets++;
                }

                if (isset($standings[$home])) {
                    $standings[$home]['sets_won']  += $homeSets;
                    $standings[$home]['sets_lost'] += $awaySets;
                    $standings[$home][$homeSets > $awaySets ? 'wins' : 'losses']++;
                }
                if (isset($standings[$away])) {
                    $standings[$away]['sets_won']  += $awaySets;
                    $standings[$away]['sets_lost'] += $homeSets;
                    $standings[$away][$awaySets > $homeSets ? 'wins' : 'losses']++;
                }
            }

            // Sort: wins desc, then set diff desc (explicit comparator)
            $sorted = collect($standings)->sort(function ($a, $b) {
                if ($a['wins'] !== $b['wins']) {
                    return $b['wins'] - $a['wins'];
                }
                return ($b['sets_won'] - $b['sets_lost']) - ($a['sets_won'] - $a['sets_lost']);
            })->values();

            foreach ($sorted as $pos => $entry) {
                $map[$entry['reg_id']] = $group->name . ($pos + 1);
            }
        }

        return $map;
    }
}
