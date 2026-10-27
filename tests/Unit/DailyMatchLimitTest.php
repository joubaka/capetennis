<?php

namespace Tests\Unit;

use App\Services\Draw\FlexibleMonradCompiler;
use App\Services\Scheduling\DailyMatchLimit;
use PHPUnit\Framework\TestCase;

class DailyMatchLimitTest extends TestCase
{
    private function graph(int $size, bool $challenge = false, bool $bye = false): array
    {
        $slots = [];
        foreach (range(0, $size - 1) as $index) {
            $path = strtr(str_pad(decbin($index), (int) log($size, 2), '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = $bye && $index === $size - 1 ? ['type' => 'bye'] : ['type' => 'player', 'id' => $index + 1];
        }
        $graph = (new FlexibleMonradCompiler)->compile(['size' => $size, 'mode' => 'feed_in_monrad', 'slots' => $slots, 'challenge_playoffs' => $challenge]);
        $matches = []; $depths = [];
        foreach ($graph['nodes'] as $key => $node) {
            $dependencies = FlexibleMonradCompiler::dependencies($node);
            $depths[$key] = $dependencies ? max(array_map(fn ($parent) => $depths[$parent], $dependencies)) + 1 : 1;
            $matches[$key] = $node + ['id' => count($matches) + 1, 'players' => [null, null], 'winner' => null, 'automatic' => false];
        }
        return [$matches, $depths];
    }

    public function test_alternative_winner_and_loser_paths_are_not_added_together(): void
    {
        [$matches] = $this->graph(8);
        $bookings = array_fill_keys(array_map(fn ($match) => 'individual:'.$match['id'], $matches), '2026-10-30');
        $counts = DailyMatchLimit::pathCounts($matches, $bookings, '2026-10-30', []);
        $this->assertCount(8, $counts);
        $this->assertSame([3], array_values(array_unique($counts)));
    }

    public function test_challenges_count_one_appearance_and_not_all_base_prerequisites(): void
    {
        [$matches] = $this->graph(8, true);
        $bookings = array_fill_keys(array_map(fn ($match) => 'individual:'.$match['id'], $matches), '2026-10-30');
        $counts = DailyMatchLimit::pathCounts($matches, $bookings, '2026-10-30', []);
        $this->assertSame(4, max($counts));
        $this->assertLessThan(count($matches), max($counts));
    }

    public function test_eight_sixteen_and_thirty_two_player_splits_preserve_three_per_day_including_challenges(): void
    {
        foreach ([8, 16, 32] as $size) {
            [$matches, $depths] = $this->graph($size, true);
            $bookings = [];
            $dates = ['2026-10-30', '2026-10-31', '2026-11-01'];
            foreach ($matches as $key => $match) $bookings['individual:'.$match['id']] = $dates[intdiv($depths[$key] - 1, 3)];
            foreach ($dates as $date) {
                $counts = DailyMatchLimit::pathCounts($matches, $bookings, $date, []);
                $this->assertLessThanOrEqual(3, $counts ? max($counts) : 0, 'Size '.$size.' on '.$date);
            }
        }
    }

    public function test_byes_and_skipped_challenges_do_not_use_daily_allowance(): void
    {
        [$matches] = $this->graph(8, true, true);
        foreach ($matches as &$match) if (isset($match['challenge_positions'])) $match['automatic'] = 'challenge_skipped';
        unset($match);
        $bookings = array_fill_keys(array_map(fn ($match) => 'individual:'.$match['id'], $matches), '2026-10-30');
        $counts = DailyMatchLimit::pathCounts($matches, $bookings, '2026-10-30', []);
        $this->assertArrayNotHasKey('registration:8', $counts);
        $this->assertLessThanOrEqual(3, max($counts));
    }

    public function test_resolved_winner_cannot_also_take_loser_branch(): void
    {
        [$matches] = $this->graph(8);
        $first = array_key_first($matches);
        $matches[$first]['players'] = [1, 2]; $matches[$first]['winner'] = 1;
        $bookings = [];
        foreach ($matches as $match) foreach ($match['sources'] as $source) {
            if (($source['match'] ?? null) === $first && $source['type'] === 'loser') $bookings['individual:'.$match['id']] = '2026-10-30';
        }
        $counts = DailyMatchLimit::pathCounts($matches, $bookings, '2026-10-30', [1 => ['profile:10'], 2 => ['profile:20']]);
        $this->assertArrayNotHasKey('profile:10', $counts);
        $this->assertSame(1, $counts['profile:20']);
    }
}
