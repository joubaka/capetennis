<?php

namespace Tests\Unit;

use App\Services\Performance\BallRatingModel;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BallRatingModelTest extends TestCase
{
    private function finish(array $players = [1, 2, 3], string $stage = 'standard'): array
    {
        return ['players' => $players, 'source_id' => 'ball-finish:20:1', 'event_id' => 20, 'event_name' => 'Fictional final',
            'event_date' => '2026-10-20', 'completed_at' => '2026-10-20', 'rating_ball_type' => $stage];
    }

    public function test_finishes_create_low_confidence_local_estimates_without_inventing_matches(): void
    {
        $fit = (new BallRatingModel)->fit([], CarbonImmutable::parse('2026-11-01'), [$this->finish()]);
        $this->assertCount(3, $fit['standard']['ratings']);
        $this->assertGreaterThan($fit['standard']['ratings'][2]['score'], $fit['standard']['ratings'][1]['score']);
        $this->assertSame([], $fit['standard']['history_evidence']['matches']);
        $this->assertCount(1, $fit['standard']['history_evidence']['fields']);
        foreach ($fit['standard']['ratings'] as $rating) {
            $this->assertSame(0, $rating['played']); $this->assertFalse($rating['comparable']);
            $this->assertSame('finish_only', $rating['evidence_basis']); $this->assertLessThanOrEqual(15, $rating['confidence_index']);
        }
        $this->assertSame([], $fit['orange']['ratings']); $this->assertSame([], $fit['red']['ratings']);
    }

    public function test_played_field_is_not_double_counted_and_scored_evidence_outweighs_a_finish(): void
    {
        $model = new BallRatingModel; $date = CarbonImmutable::parse('2026-11-01');
        $match = array_replace($this->match('match:20', null, 2, 1), ['event_id' => 20]);
        $baseline = $model->fit([$match], $date);
        $covered = $model->fit([$match], $date, [$this->finish([1, 2])]);
        $this->assertSame($baseline, $covered);
        $mixed = $model->fit([$match], $date, [$this->finish()]);
        $this->assertGreaterThan($mixed['standard']['ratings'][1]['score'], $mixed['standard']['ratings'][2]['score']);
        $this->assertFalse($mixed['standard']['ratings'][3]['comparable']);
        $this->assertSame(0, $mixed['standard']['ratings'][3]['played']);
    }

    public function test_future_old_duplicate_and_other_ball_finishes_do_not_leak(): void
    {
        $model = new BallRatingModel; $date = CarbonImmutable::parse('2026-11-01');
        $field = $this->finish(); $normal = $model->fit([], $date, [$field]);
        $this->assertSame($normal, $model->fit([], $date, [$field, $field]));
        $changed = $model->fit([], $date, [$field, array_replace($this->finish([4, 5], 'orange'), ['source_id' => 'orange-finish'])]);
        $this->assertSame($normal['standard'], $changed['standard']);
        foreach (['2027-01-01', '2020-01-01'] as $excluded) $this->assertSame([], $model->fit([], $date, [array_replace($field, ['completed_at' => $excluded])])['standard']['ratings']);
    }

    public function test_ordinal_fit_converges_when_finishes_join_many_played_components(): void
    {
        $matches = []; $fields = [];
        for ($id = 1; $id < 120; $id += 2) $matches[] = $this->match('match:'.$id, null, $id, $id + 1);
        for ($id = 1; $id <= 100; $id += 10) $fields[] = array_replace($this->finish(range($id, $id + 20)), ['source_id' => 'finish:'.$id]);
        $fit = (new BallRatingModel)->fit($matches, CarbonImmutable::parse('2026-11-01'), $fields)['standard'];
        $this->assertCount(120, $fit['ratings']);
        foreach ($fit['components'] as $component) $this->assertTrue($component['converged']);
        $this->assertCount(60, $fit['history_evidence']['matches']);
        $this->assertCount(10, $fit['history_evidence']['fields']);
    }

    private function match(string $id, ?string $ball, int $winner = 1, int $loser = 2): array
    {
        return ['source_id' => $id, 'event_id' => (int) substr($id, 6), 'winner' => $winner, 'loser' => $loser,
            'confidence_date' => '2026-10-20', 'confidence_date_basis' => 'actual match date',
            'category' => 'U10 Boys', 'cohort' => 'u10 boys', 'event_name' => 'Fictional tennis event',
            'outcome_target' => 0.9, 'ball_type' => $ball];
    }

    public function test_other_ball_results_cannot_move_a_level_rating_or_confidence(): void
    {
        $model = new BallRatingModel; $asOf = CarbonImmutable::parse('2026-11-01');
        $red = [$this->match('match:1', 'red'), $this->match('match:2', 'red', 2, 3)];
        $baseline = $model->fit($red, $asOf);
        $changed = $model->fit([...$red, $this->match('match:3', 'orange', 2, 1), $this->match('match:4', null, 3, 1)], $asOf);
        $this->assertSame($baseline['red'], $changed['red']);
        $orange = $model->fit([$this->match('match:3', 'orange', 2, 1)], $asOf);
        $this->assertSame($orange['orange'], $changed['orange']);
        $this->assertCount(1, $changed['standard']['history_evidence']['matches']);
        $this->assertArrayNotHasKey(3, $changed['orange']['ratings']);
    }

    public function test_green_yellow_and_unstated_results_share_normal_but_never_red_or_orange(): void
    {
        $model = new BallRatingModel; $asOf = CarbonImmutable::parse('2026-11-01');
        $fit = $model->fit([$this->match('match:1', 'green'), $this->match('match:2', 'yellow'), $this->match('match:3', null)], $asOf);
        $this->assertSame([], $fit['red']['ratings']); $this->assertSame([], $fit['orange']['ratings']);
        $this->assertSame(3, $fit['standard']['ratings'][1]['played']);
        $this->assertSame('standard', BallRatingModel::bucket(null));
        $this->assertSame('orange', BallRatingModel::bucket(null, 'Orange River Open'));
        $this->assertSame('red', BallRatingModel::bucket(null, 'Wilson Red 2026'));
        $this->assertSame('orange', BallRatingModel::bucket(null, 'Orange ball festival'));
        $this->assertNull(BallRatingModel::bucket(null, 'Wilson Red and Orange ball festival'));
        $this->assertSame('red', BallRatingModel::bucket('red', 'Orange ball festival'));
        $this->assertSame('orange', BallRatingModel::forMatch(['category' => 'Orange ball', 'event_name' => 'Wilson Red and Orange']));
        $this->assertSame('standard', BallRatingModel::forMatch(['category' => 'Green/Yellow ball', 'event_name' => 'Wilson Red']));
        $this->assertSame('standard', BallRatingModel::forMatch(['category' => 'Normal', 'event_name' => 'Wilson Orange']));
        $this->assertSame('red', BallRatingModel::forMatch(['category' => 'Red Boys u/9', 'event_name' => 'Junior tournament']));
        $this->assertSame('red', BallRatingModel::forMatch(['category' => 'JTA u/9 Red Boys + Girls', 'event_name' => 'Junior tournament']));
        $this->assertSame('orange', BallRatingModel::forMatch(['category' => 'Orange Ball Boys', 'event_name' => 'JTA Red and Orange ball tournament']));
        $this->assertNull(BallRatingModel::bucket(null, 'Red, Orange and Green Ball Tournament'));
        $this->assertSame('red', BallRatingModel::bucket(null, 'Junior Red Series'));
        $this->assertSame('red', BallRatingModel::bucket(null, 'RedBoys2023'));
        $this->assertSame('orange', BallRatingModel::bucket(null, 'SchoolORANGESeries'));
        $this->assertSame('red', BallRatingModel::forMatch(['category' => 'RedBoys', 'event_name' => 'Orange Series']));
        $this->assertSame('standard', BallRatingModel::forMatch(['category' => 'GreenBoys', 'event_name' => 'Red Series']));
        $this->assertSame('orange', BallRatingModel::bucket(null, 'Wilson U/9B'));
        $this->assertSame('orange', BallRatingModel::bucket(null, 'WilsonU9B'));
        $this->assertSame('orange', BallRatingModel::forMatch(['category' => 'U/9 Boys B - Division', 'event_name' => 'Wilson Junior Series']));
        $this->assertSame('orange', BallRatingModel::forMatch(['category' => 'u9b Girls', 'event_name' => 'Wilson Series']));
        $this->assertSame('standard', BallRatingModel::forMatch(['category' => 'U9B', 'event_name' => 'Junior Open']));
        $this->assertSame('standard', BallRatingModel::forMatch(['ball_type' => 'green', 'category' => 'U9B', 'event_name' => 'Wilson Series']));
        $this->assertSame('red', BallRatingModel::forMatch(['category' => 'Red Ball U9B', 'event_name' => 'Wilson Series']));
        $this->assertSame('standard', BallRatingModel::forMatch(['category' => 'Green Ball U9B', 'event_name' => 'Wilson Series']));
        $this->assertNull(BallRatingModel::forMatch(['category' => 'Red and Orange U9B', 'event_name' => 'Wilson Series']));
        $this->assertSame('orange', BallRatingModel::forMatch(['ball_type' => 'orange', 'category' => 'Red Ball U9B', 'event_name' => 'Wilson Series']));
    }

    public function test_a_nonconvergent_ball_bucket_is_unrated_without_blocking_healthy_levels(): void
    {
        $model = new BallRatingModel; $date = CarbonImmutable::parse('2026-10-31');
        $red = $this->match('match:1', 'red', 1, 3);
        $normal = $this->match('match:2', null, 1, 4);
        $healthy = $model->fit([$red, $normal], $date);
        $orange = [];
        foreach (['2026-09-10', '2026-10-10', '2026-10-20'] as $index => $played) $orange[] = array_replace($this->match('match:'.($index + 10), 'orange'), ['confidence_date' => $played, 'outcome_target' => 0.875]);
        $fit = $model->fit([$red, $normal, ...$orange], $date);
        $this->assertSame($healthy['red'], $fit['red']); $this->assertSame($healthy['standard'], $fit['standard']);
        $this->assertSame([], $fit['orange']['ratings']); $this->assertSame([], $fit['orange']['components']);
        $this->assertStringContainsString('did not converge', $fit['orange']['unrated_reason']);
        $this->assertSame(3, $fit['orange']['excluded']['Ball-level model did not converge']);
    }

    public function test_regional_trials_u10_b_defaults_to_orange_without_overriding_explicit_conditions(): void
    {
        foreach (['Overberg Tennis Trials - Leg 2 2026', 'Westcoast Trials 2026', 'West Coast Tennis Trials', 'West-Coast Trials'] as $event) {
            foreach (['u/10 Girls - B division', 'U10 Boys B', 'u10b Girls'] as $category) {
                $match = ['category' => $category, 'event_name' => $event];
                $this->assertSame('orange', BallRatingModel::forMatch($match));
                $this->assertSame('standard', BallRatingModel::forMatch(['ball_type' => 'green'] + $match));
                $this->assertSame('red', BallRatingModel::forMatch(['category' => 'Red Ball '.$category] + $match));
                $this->assertSame('standard', BallRatingModel::forMatch(['category' => 'Green Ball '.$category] + $match));
            }
            foreach (['U10 Girls A', 'U12 Girls B', 'U10 Girls', 'U9B', 'U10 Girls A and B', 'U10 Girls A / U12 Girls B'] as $category) {
                $this->assertSame('standard', BallRatingModel::forMatch(['category' => $category, 'event_name' => $event]));
            }
        }
        foreach (['Overberg Junior Open', 'West Coast Festival', 'Other District Trials'] as $event) {
            $this->assertSame('standard', BallRatingModel::forMatch(['category' => 'U10 Girls B', 'event_name' => $event]));
        }
        foreach (['u/9 Girls B - Division', 'U9 Boys B', 'U9B Girls'] as $category) {
            $match = ['category' => $category, 'event_name' => 'Cavaliers Junior Ceres Tournament 2026'];
            $this->assertSame('orange', BallRatingModel::forMatch($match));
            $this->assertSame('standard', BallRatingModel::forMatch(['ball_type' => 'yellow'] + $match));
            $this->assertSame('standard', BallRatingModel::forMatch(['category' => 'Green Ball '.$category] + $match));
        }
        foreach (['U9 Girls A', 'U10 Boys B', 'U9 Girls'] as $category) {
            $this->assertSame('standard', BallRatingModel::forMatch(['category' => $category, 'event_name' => 'Cavaliers Junior Ceres Tournament 2026']));
        }
        $this->assertSame('standard', BallRatingModel::forMatch(['category' => 'U9 Girls B', 'event_name' => 'Ceres School Trials']));
    }
}
