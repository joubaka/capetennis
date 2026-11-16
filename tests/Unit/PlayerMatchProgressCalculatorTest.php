<?php

namespace Tests\Unit;

use App\Services\Performance\{AllAgeRatingModel, PlayerMatchProgressCalculator};
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PlayerMatchProgressCalculatorTest extends TestCase
{
    private function evidence(): array
    {
        $matches = []; $order = [];
        foreach (['2026-06-14', '2026-07-12', '2026-08-09', '2026-09-13', '2026-10-11', '2026-11-08'] as $month => $date) {
            foreach ([[1, 2, in_array($month, [2, 3], true) ? 1 : 0], [3, 1, in_array($month, [1, 4, 5], true) ? 1 : 0]] as $index => [$winner, $loser, $games]) {
                $id = 'match:'.(100 + $month * 2 + $index);
                $matches[] = ['winner' => $winner, 'loser' => $loser, 'source_id' => $id, 'event_id' => $month + 1, 'event_name' => 'Fictional event',
                    'cohort' => 'u10 boys', 'category' => 'u10 boys', 'ball_type' => 'green', 'confidence_date' => $date,
                    'confidence_date_basis' => 'scheduled match date', 'outcome_target' => .75 + .25 * ((6 - $games) / (6 + $games))];
                $order[$id] = ['time' => $date.' '.($index ? '10:00:00' : '09:00:00'), 'earliest_date' => $date, 'latest_date' => $date];
            }
        }
        return [$matches, $order];
    }

    public function test_latest_five_follow_scheduled_time_not_source_id_order_and_pairs_use_distinct_chronological_prefixes(): void
    {
        [$matches, $order] = $this->evidence(); $beforeInput = serialize([$matches, $order]);
        $model = new AllAgeRatingModel; $calculator = new PlayerMatchProgressCalculator($model);
        $latest = $calculator->latest(1, $matches, $order);
        $this->assertSame(['match:111', 'match:110', 'match:109', 'match:108', 'match:107'], array_column($latest, 'source_id'));
        $afters = [];
        foreach ($latest as $target) {
            $date = CarbonImmutable::parse($target['confidence_date']); $time = $order[$target['source_id']]['time'];
            $pair = $calculator->pair(1, $target['source_id'], $matches, $order, $date);
            $pre = array_values(array_filter($matches, fn ($m) => $order[$m['source_id']]['time'] < $time));
            $expectedBefore = $model->fit($pre, $date)['ratings'][1]['score'];
            $expectedAfter = $model->fit([...$pre, $target], $date)['ratings'][1]['score'];
            $this->assertNull($pair['reason']); $this->assertSame($expectedBefore, $pair['before_score']); $this->assertSame($expectedAfter, $pair['after_score']);
            $this->assertSame(round($expectedAfter - $expectedBefore, 1), $pair['delta']); $afters[] = $pair['after_score'];
            $this->assertSame(count($pre), $pair['explanation']['network_before']);
            $this->assertSame(count($pre) + 1, $pair['explanation']['network_after']);
            $this->assertSame(count($pre), $pair['explanation']['own_before']);
            $this->assertSame(2, $pair['explanation']['opponents_before']);
            $this->assertSame(count($pre) + 1, count($pair['own_sources']));
            $added = array_values(array_filter($pair['own_sources'], fn ($m) => !$m['before']));
            $this->assertSame([$target['source_id']], array_column($added, 'source_id'));
            $this->assertSame([true], array_unique(array_column($pair['own_sources'], 'after')));
            $preGraph = $model->fit($pre, $date); $postGraph = $model->fit([...$pre, $target], $date);
            foreach ($pair['own_sources'] as $member) {
                $input = array_values(array_filter($matches, fn ($m) => $m['source_id'] === $member['source_id']))[0];
                $opponent = $input['winner'] === 1 ? $input['loser'] : $input['winner'];
                $this->assertSame($preGraph['ratings'][$opponent]['score'], $member['opponent_before']);
                $this->assertSame($postGraph['ratings'][$opponent]['score'], $member['opponent_after']);
            }
        }
        $this->assertGreaterThan(3, count(array_unique($afters)), 'Each row must not reuse today’s constant baseline');
        $this->assertSame($beforeInput, serialize([$matches, $order]));
    }

    public function test_later_matches_of_any_player_and_future_events_never_enter_either_prefix(): void
    {
        [$matches, $order] = $this->evidence(); $calculator = new PlayerMatchProgressCalculator(new AllAgeRatingModel);
        $day = CarbonImmutable::parse('2026-10-11'); $expected = $calculator->pair(1, 'match:108', $matches, $order, $day);
        foreach ([['later-other', '2026-10-11 15:00:00'], ['future-event', '2026-12-01 09:00:00']] as [$id, $time]) {
            $extra = $matches[0]; $extra['source_id'] = $id; $extra['winner'] = 2; $extra['loser'] = 3; $extra['confidence_date'] = substr($time, 0, 10); $matches[] = $extra;
            $order[$id] = ['time' => $time, 'earliest_date' => substr($time, 0, 10), 'latest_date' => substr($time, 0, 10)];
        }
        $this->assertSame($expected, $calculator->pair(1, 'match:108', $matches, $order, $day));
    }

    public function test_unrelated_simultaneous_matches_are_excluded_from_both_fits_and_after_adds_only_the_target(): void
    {
        [$matches, $order] = $this->evidence(); $day = CarbonImmutable::parse('2026-10-11');
        $calculator = new PlayerMatchProgressCalculator(new AllAgeRatingModel);
        $expected = $calculator->pair(1, 'match:108', $matches, $order, $day);
        $this->assertNull($expected['reason']);
        foreach ([[3, 4], [998, 999]] as $index => [$winner, $loser]) {
            $extra = $matches[0]; $extra['source_id'] = 'simultaneous:'.$index;
            $extra['winner'] = $winner; $extra['loser'] = $loser; $extra['confidence_date'] = '2026-10-11'; $matches[] = $extra;
            $order[$extra['source_id']] = ['time' => '2026-10-11 09:00:00', 'earliest_date' => '2026-10-11', 'latest_date' => '2026-10-11'];
        }
        foreach ([$matches, array_reverse($matches)] as $input) {
            $this->assertSame($expected, $calculator->pair(1, 'match:108', $input, $order, $day));
        }
        $pre = array_values(array_filter($matches, fn ($m) => $order[$m['source_id']]['time'] < '2026-10-11 09:00:00'));
        $target = array_values(array_filter($matches, fn ($m) => $m['source_id'] === 'match:108'))[0];
        $model = $this->createMock(AllAgeRatingModel::class); $calls = [];
        $model->expects($this->exactly(2))->method('fit')->willReturnCallback(function ($pool, $asOf) use (&$calls) {
            $calls[] = array_column($pool, 'source_id');
            return (new AllAgeRatingModel)->fit($pool, $asOf);
        });
        $this->assertSame($expected, (new PlayerMatchProgressCalculator($model))->pair(1, 'match:108', $matches, $order, $day));
        $this->assertSame([array_column($pre, 'source_id'), array_column([...$pre, $target], 'source_id')], $calls);
    }
    public function test_opponent_pair_is_unavailable_when_its_comparability_or_context_changes_even_if_selected_pair_is_valid(): void
    {
        [$matches, $order] = $this->evidence(); $day = CarbonImmutable::parse('2026-11-08'); $model = new AllAgeRatingModel;
        $pre = $model->fit(array_slice($matches, 0, 11), $day); $post = $model->fit($matches, $day);
        foreach (['comparable', 'connection'] as $case) {
            $changed = $post;
            if ($case === 'comparable') $changed['ratings'][3]['comparable'] = false;
            else $changed['ratings'][3]['connection_status'] = 'different';
            $mock = $this->createMock(AllAgeRatingModel::class); $mock->expects($this->exactly(2))->method('fit')->willReturnOnConsecutiveCalls($pre, $changed);
            $pair = (new PlayerMatchProgressCalculator($mock))->pair(1, 'match:111', $matches, $order, $day);
            $this->assertNull($pair['reason']); $this->assertNotNull($pair['after_score']);
            foreach ($pair['own_sources'] as $ref) {
                if ($ref['opponent_id'] === 3) { $this->assertNull($ref['opponent_before']); $this->assertNull($ref['opponent_after']); }
                else $this->assertNotNull($ref['opponent_after']);
            }
        }
    }

    public function test_unknown_tied_or_overlapping_proxy_order_withholds_the_whole_pair_without_fitting(): void
    {
        foreach (['target_unknown', 'target_date_mismatch', 'same_day_unknown', 'overlapping_proxy', 'evidence_date_mismatch', 'target_winner_as_winner', 'target_winner_as_loser', 'target_loser_as_winner', 'target_loser_as_loser'] as $case) {
            [$matches, $order] = $this->evidence(); $model = $this->createMock(AllAgeRatingModel::class); $model->expects($this->never())->method('fit');
            if ($case === 'target_unknown') $order['match:108']['time'] = null;
            elseif ($case === 'target_date_mismatch') $order['match:108']['time'] = '2026-10-10 09:00:00';
            else {
                $extra = $matches[0]; $extra['source_id'] = 'ambiguous-other'; $extra['winner'] = 999; $extra['loser'] = 998;
                if (str_starts_with($case, 'target_')) {
                    $participant = str_starts_with($case, 'target_winner_') ? 1 : 2;
                    $extra[str_ends_with($case, '_as_winner') ? 'winner' : 'loser'] = $participant;
                }
                $extra['confidence_date'] = $case === 'overlapping_proxy' ? '2026-10-01' : '2026-10-11'; $matches[] = $extra;
                $order['ambiguous-other'] = ['time' => str_starts_with($case, 'target_') ? '2026-10-11 09:00:00' : ($case === 'evidence_date_mismatch' ? '2026-10-10 09:00:00' : null),
                    'earliest_date' => $extra['confidence_date'], 'latest_date' => $case === 'overlapping_proxy' ? '2026-10-15' : '2026-10-11'];
            }
            $pair = (new PlayerMatchProgressCalculator($model))->pair(1, 'match:108', $matches, $order, CarbonImmutable::parse('2026-10-11'));
            $this->assertNull($pair['before_score']); $this->assertNull($pair['after_score']); $this->assertNull($pair['delta']); $this->assertNotEmpty($pair['reason']);
        }
    }

    public function test_known_and_unknown_same_day_rows_have_deterministic_display_without_inventing_an_order(): void
    {
        [$matches, $order] = $this->evidence(); $unknown = $matches[0]; $unknown['source_id'] = 'unknown-order'; $unknown['confidence_date'] = '2026-11-08'; $matches[] = $unknown;
        $order['unknown-order'] = ['time' => null, 'earliest_date' => '2026-11-08', 'latest_date' => '2026-11-08'];
        $calculator = new PlayerMatchProgressCalculator(new AllAgeRatingModel);
        foreach ([$matches, array_reverse($matches), [...array_slice($matches, 5), ...array_slice($matches, 0, 5)]] as $input) {
            $latest = $calculator->latest(1, $input, $order);
            $this->assertSame(['match:111', 'match:110', 'unknown-order', 'match:109', 'match:108'], array_column($latest, 'source_id'));
            foreach (array_slice($latest, 0, 3) as $match) $this->assertNull($calculator->pair(1, $match['source_id'], $input, $order, CarbonImmutable::parse('2026-11-08'))['delta']);
        }
    }

    public function test_changed_comparison_context_or_nonconvergence_does_not_expose_a_numeric_pair(): void
    {
        [$matches, $order] = $this->evidence(); $day = CarbonImmutable::parse('2026-11-08');
        $baseline = (new AllAgeRatingModel)->fit(array_slice($matches, 0, 10), $day);
        foreach (['members', 'reference', 'backbone', 'connection', 'nonconverged'] as $case) {
            $changed = $baseline; $component = $changed['ratings'][1]['component'];
            match ($case) {
                'members' => $changed['components'][$component]['members'] = [1, 2], 'reference' => $changed['reference_component'] = 'different',
                'backbone' => $changed['recent_backbone']['members'] = [1, 2], 'connection' => $changed['ratings'][1]['connection_status'] = 'historical',
                'nonconverged' => $changed['components'][$component]['converged'] = false,
            };
            $model = $this->createMock(AllAgeRatingModel::class); $model->expects($this->exactly(2))->method('fit')->willReturnOnConsecutiveCalls($baseline, $changed);
            $pair = (new PlayerMatchProgressCalculator($model))->pair(1, 'match:110', $matches, $order, $day);
            $this->assertNull($pair['before_score']); $this->assertNull($pair['after_score']); $this->assertNull($pair['delta']);
        }
    }
}
