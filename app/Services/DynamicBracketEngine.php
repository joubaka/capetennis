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

    // SVG Layout Constants
    const BOX_WIDTH = 190;
    const BOX_HEIGHT = 60;
    const ROUND_GAP = 200;
    const MATCH_GAP_BASE = 40;
    const START_X = 60;
    const START_Y = 100;
    const BRACKET_GAP = 160;

    public function __construct(Draw $draw)
    {
        $this->draw = $draw;
        $this->fixtures = Fixture::where('draw_id', $draw->id)
            ->whereIn('stage', ['MAIN', 'PLATE', 'CONS', 'BOWL', 'SHIELD', 'SPOON'])
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
        $result = ['brackets' => [], 'totalHeight' => 0, 'totalWidth' => 0];
        $currentY = self::START_Y;

        $playoffConfig = optional($this->draw->settings)->playoff_config ?? [];

        foreach ($playoffConfig as $config) {
            if (!($config['enabled'] ?? false)) continue;

            $slug = $config['slug'] ?? 'unknown';
            $stage = $this->slugToStage($slug);
            $bracketData = $this->buildBracket($stage, $config['size'] ?? 4, $currentY);
            
            if (!empty($bracketData['rounds'])) {
                $result['brackets'][] = array_merge($bracketData, [
                    'name' => $config['name'] ?? 'Bracket',
                    'slug' => $slug,
                    'startY' => $currentY,
                ]);
                $currentY += $bracketData['height'] + self::BRACKET_GAP;
            }
        }

        $result['totalHeight'] = $currentY;
        $result['totalWidth'] = 1400; 
        return $result;
    }

    protected function buildBracket(string $stage, int $size, int $startY): array
    {
        $stageFixtures = $this->fixtures->where('stage', $stage);
        if ($stageFixtures->isEmpty()) {
            return ['rounds' => [], 'height' => 0, 'width' => 0, 'numRounds' => 0, 'positionPlayoffs' => []];
        }

        $numRounds = (int) ceil(log($size, 2));
        $rounds = [];
        $maxHeight = 0;

        // Separate main bracket from position playoffs
        // Position playoffs have playoff_type set (e.g., '3rd/4th', 'cons_sf')
        // Finals may have position=1 but are still part of the main bracket
        $mainFixtures = $stageFixtures->filter(fn($fx) => 
            is_null($fx->playoff_type)
        );

        Log::info("🎯 Main bracket fixtures filtered", [
            'total' => $mainFixtures->count(),
            'by_round' => $mainFixtures->groupBy('round')->map->count()->toArray(),
        ]);

        for ($round = 1; $round <= $numRounds; $round++) {
            $expectedMatches = pow(2, $numRounds - $round);
            $roundFixtures = $mainFixtures->where('round', $round)->sortBy('match_nr')->values();
            
            $matchesInRound = [];
            $roundX = self::START_X + (($round - 1) * self::ROUND_GAP);
            $matchSpacing = (self::MATCH_GAP_BASE + self::BOX_HEIGHT) * pow(2, $round - 1);

            for ($i = 0; $i < $expectedMatches; $i++) {
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
                $height = ($round === 1) ? self::BOX_HEIGHT : (int)($child2MidY - $child1MidY);

                $matchesInRound[] = [
                    'fx' => $fixture,
                    'x' => $roundX,
                    'y' => (int)$y,
                    'width' => self::BOX_WIDTH,
                    'height' => $height,
                    'roundLabel' => $this->getRoundLabel($round, $numRounds),
                ];
                $maxHeight = max($maxHeight, $y + $height);
            }
            $rounds[$round] = $matchesInRound;
        }

        // Build position playoffs
        $playoffMatches = $this->buildPositionPlayoffs($stageFixtures, $maxHeight + 80, $numRounds);
        if (!empty($playoffMatches['matches'])) {
            $maxHeight = max($maxHeight, $playoffMatches['maxY']);
        }

        return [
            'rounds' => $rounds,
            'numRounds' => $numRounds,
            'positionPlayoffs' => $playoffMatches['matches'] ?? [],
            'height' => $maxHeight - $startY + 100,
        ];
    }

    protected function buildPositionPlayoffs(Collection $stageFixtures, int $startY, int $numRounds): array
    {
        $matches = [];
        $maxY = $startY;

        // Filter position playoff fixtures
        $playoffFixtures = $stageFixtures->filter(function($fx) {
            return $fx->position !== null || $fx->playoff_type !== null;
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

        // Separate different playoff types
        $thirdFourth = $playoffFixtures->filter(fn($fx) => 
            $fx->position == 3 || str_contains($fx->playoff_type ?? '', '3rd')
        )->first();
        
        $consSF = $playoffFixtures->filter(fn($fx) => 
            str_contains($fx->playoff_type ?? '', 'cons_sf')
        )->sortBy('match_nr')->values();
        
        $fifthSixth = $playoffFixtures->filter(fn($fx) => 
            $fx->position == 5 || str_contains($fx->playoff_type ?? '', '5th')
        )->first();
        
        $seventhEighth = $playoffFixtures->filter(fn($fx) => 
            $fx->position == 7 || str_contains($fx->playoff_type ?? '', '7th')
        )->first();

        $currentY = $startY;
        $spacing = self::BOX_HEIGHT + 30;

        // 3rd/4th Playoff (align with SF round X position)
        if ($thirdFourth) {
            $xPos = self::START_X + (max(1, $numRounds - 2) * self::ROUND_GAP);
            $matches[] = [
                'fx' => $thirdFourth,
                'x' => $xPos,
                'y' => $currentY,
                'width' => self::BOX_WIDTH,
                'height' => self::BOX_HEIGHT,
                'label' => '3rd/4th Place',
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
                // Calculate midpoints of Cons SF matches
                $consSF1MidY = $consSF1Data['y'] + ($consSF1Data['height'] / 2);
                $consSF2MidY = $consSF2Data['y'] + ($consSF2Data['height'] / 2);
                
                // 5th/6th top line aligns with Cons SF 1 midpoint, bottom with Cons SF 2 midpoint
                $fifthSixthY = $consSF1MidY;
                $fifthSixthHeight = (int)($consSF2MidY - $consSF1MidY);
                
                $matches[] = [
                    'fx' => $fifthSixth,
                    'x' => $r2X,
                    'y' => $fifthSixthY,
                    'width' => self::BOX_WIDTH,
                    'height' => $fifthSixthHeight,
                    'label' => '5th/6th Place',
                    'isFinal' => true,
                ];
            }
            
            // 7th/8th Playoff (losers bracket - positioned below Cons SF, same X column)
            if ($seventhEighth && $consSF2Data) {
                // Position 7th/8th below Cons SF 2 with some spacing, at same X as Cons SF
                $seventhEighthY = $consSF2Data['y'] + self::BOX_HEIGHT + 40;
                
                $matches[] = [
                    'fx' => $seventhEighth,
                    'x' => $r1X,  // Same X as Cons SF matches (left column)
                    'y' => $seventhEighthY,
                    'width' => self::BOX_WIDTH,
                    'height' => self::BOX_HEIGHT,
                    'label' => '7th/8th Place',
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

    protected function slugToStage($slug) {
        return match(strtolower($slug)) { 'main' => 'MAIN', 'plate' => 'PLATE', 'cons' => 'CONS', default => strtoupper($slug) };
    }
}
