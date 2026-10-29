<?php

namespace Tests\Unit;

use App\Services\Performance\{AbilityConfidenceDisplay, AllAgeRatingModel, PlayerAbilitySnapshotStore};
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AllAgeHistoricalConnectionTest extends TestCase
{
    private function match(int $a, int $b, string $source, int $days = 1, int $event = 1): array
    {
        return ['winner' => $a, 'loser' => $b, 'source_id' => $source, 'event_id' => $event, 'event_name' => 'Recorded singles',
            'category' => 'U13 Boys', 'cohort' => 'u13 boys', 'ball_type' => 'yellow',
            'confidence_date' => CarbonImmutable::parse('2026-10-02')->subDays($days)->toDateString(), 'confidence_date_basis' => 'scheduled match date'];
    }

    private function network(): array
    {
        $matches = [$this->match(1, 2, 'backbone-a'), $this->match(2, 3, 'backbone-b'), $this->match(3, 10, 'old-bridge', 500)];
        for ($i = 0; $i < 12; $i++) { $matches[] = $this->match(10, 11, 'recent-island-'.$i, 1, $i + 2); }
        return $matches;
    }

    public function test_recent_player_depending_on_old_bridge_has_low_confidence_and_actual_bottleneck_date(): void
    {
        $asOf = CarbonImmutable::parse('2026-10-02');
        $model = new AllAgeRatingModel;
        $fit = $model->fit($this->network(), $asOf);
        $rating = $fit['ratings'][10];
        $this->assertSame([1, 2, 3], $fit['recent_backbone']['members']);
        $this->assertTrue($rating['comparable']);
        $this->assertTrue($rating['historical_connection']);
        $this->assertSame('historical', $rating['connection_status']);
        $this->assertSame(500, $rating['connection_age_days']);
        $this->assertSame($asOf->subDays(500)->toDateString(), $rating['connection_reference_date']);
        $this->assertSame('2026-10-01', $rating['last_direct_match']);
        $this->assertLessThanOrEqual(39, $rating['confidence_index']);
        $this->assertSame('Low', $rating['confidence_label']);
        $this->assertStringContainsString('500 days old', $rating['confidence_explanation']);
        $this->assertStringContainsString('not calibrated rating accuracy', $rating['confidence_explanation']);
        $baseline = $model->fit($this->network(), $asOf, 365);
        $this->assertNull($baseline['ratings'][10]['score']);
        $this->assertSame(365, $baseline['policy']['window_days']);
        $this->assertSame(730, $fit['policy']['window_days']);
    }

    public function test_fresh_alternative_path_removes_historical_flag_and_confidence_cap(): void
    {
        $fit = (new AllAgeRatingModel)->fit([...$this->network(), $this->match(3, 10, 'fresh-bridge')], CarbonImmutable::parse('2026-10-02'));
        $rating = $fit['ratings'][10];
        $this->assertFalse($rating['historical_connection']);
        $this->assertSame('recent', $rating['connection_status']);
        $this->assertSame(0, $rating['connection_age_days']);
        $this->assertGreaterThan(39, $rating['confidence_index']);
        $this->assertNotSame('Low', $rating['confidence_label']);
    }

    public function test_minimax_path_uses_freshest_bottleneck_and_is_input_order_independent(): void
    {
        $matches = [$this->match(1, 2, 'a'), $this->match(2, 3, 'b'), $this->match(3, 10, 'direct-old', 650),
            $this->match(3, 20, 'alternative-first', 450), $this->match(20, 10, 'alternative-second', 500),
            $this->match(3, 10, 'direct-newer', 600), $this->match(10, 11, 'island')];
        $model = new AllAgeRatingModel;
        $fit = $model->fit($matches, CarbonImmutable::parse('2026-10-02'));
        $this->assertSame(500, $fit['ratings'][10]['connection_age_days']);
        $this->assertSame(500, $fit['ratings'][11]['connection_age_days']);
        $this->assertSame(450, $fit['ratings'][20]['connection_age_days']);
        $this->assertSame($fit, $model->fit(array_reverse($matches), CarbonImmutable::parse('2026-10-02')));
    }

    public function test_old_only_player_is_low_confidence_but_inactive_giant_cannot_replace_recent_backbone(): void
    {
        $matches = [$this->match(1, 2, 'recent'), $this->match(2, 3, 'old-only-player', 400)];
        for ($id = 100; $id < 110; $id++) { $matches[] = $this->match($id, $id + 1, 'inactive-'.$id, 400); }
        $fit = (new AllAgeRatingModel)->fit($matches, CarbonImmutable::parse('2026-10-02'));
        $this->assertSame([1, 2], $fit['recent_backbone']['members']);
        $this->assertSame($fit['ratings'][1]['component'], $fit['reference_component']);
        $this->assertTrue($fit['ratings'][3]['historical_connection']);
        $this->assertSame('Low', $fit['ratings'][3]['confidence_label']);
        $this->assertNull($fit['ratings'][100]['score']);
        $this->assertFalse($fit['ratings'][100]['historical_connection']);
        $this->assertNull($fit['ratings'][100]['connection_age_days']);
    }

    public function test_no_recent_matches_means_no_reference_or_shared_scores(): void
    {
        $fit = (new AllAgeRatingModel)->fit([$this->match(1, 2, 'old', 500)], CarbonImmutable::parse('2026-10-02'));
        $this->assertNull($fit['reference_component']);
        $this->assertNull($fit['recent_backbone']);
        foreach ($fit['ratings'] as $rating) {
            $this->assertNull($rating['score']);
            $this->assertSame('disconnected', $rating['connection_status']);
            $this->assertStringContainsString('No recent played-match reference', $rating['reason']);
        }
        $this->assertNull((new PlayerAbilitySnapshotStore)->assertAllAgeGraph($fit));
    }

    public function test_730_day_limit_is_inclusive_and_future_and_older_results_stay_out(): void
    {
        $fit = (new AllAgeRatingModel)->fit([$this->match(1, 2, 'recent'), $this->match(2, 3, 'boundary', 730), $this->match(3, 4, 'too-old', 731), $this->match(2, 5, 'future', -1)], CarbonImmutable::parse('2026-10-02'));
        $this->assertNotNull($fit['ratings'][3]['score']);
        $this->assertSame(730, $fit['ratings'][3]['connection_age_days']);
        $this->assertArrayNotHasKey(4, $fit['ratings']);
        $this->assertArrayNotHasKey(5, $fit['ratings']);
        $this->assertSame(2, $fit['excluded']['Outside the supported played-match window']);
    }

    public function test_recent_result_outweighs_several_very_old_opposite_results_with_existing_decay(): void
    {
        $matches = [$this->match(2, 1, 'recent', 1, 20)];
        for ($i = 0; $i < 4; $i++) { $matches[] = $this->match(1, 2, 'old-'.$i, 700, $i + 1); }
        $fit = (new AllAgeRatingModel)->fit($matches, CarbonImmutable::parse('2026-10-02'));
        $this->assertGreaterThan($fit['ratings'][1]['score'], $fit['ratings'][2]['score']);
        $weights = array_column($fit['history_evidence']['matches'], 'weight', 'source_id');
        $this->assertGreaterThan(10 * $weights['old-0'], $weights['recent']);
    }

    public function test_recent_backbone_ties_use_match_count_then_lowest_player_id(): void
    {
        $model = new AllAgeRatingModel;
        $matches = [$this->match(1, 2, 'first'), $this->match(10, 11, 'second')];
        $this->assertSame([1, 2], $model->fit($matches, CarbonImmutable::parse('2026-10-02'))['recent_backbone']['members']);
        $matches[] = $this->match(10, 11, 'second-again');
        $this->assertSame([10, 11], $model->fit($matches, CarbonImmutable::parse('2026-10-02'))['recent_backbone']['members']);
    }

    public function test_saved_historical_confidence_or_reference_metadata_cannot_be_corrupted(): void
    {
        $fit = (new AllAgeRatingModel)->fit($this->network(), CarbonImmutable::parse('2026-10-02'));
        $store = new PlayerAbilitySnapshotStore;
        $this->assertNull($store->assertAllAgeGraph($fit));
        $mutations = [
            fn (&$g) => $g['ratings'][10]['historical_connection'] = false,
            fn (&$g) => $g['ratings'][10]['connection_age_days'] = 100,
            fn (&$g) => $g['ratings'][10]['connection_reference_date'] = '2026-10-01',
            fn (&$g) => $g['ratings'][10]['confidence_index'] = 80,
            fn (&$g) => $g['ratings'][10]['confidence_label'] = 'High',
            fn (&$g) => $g['reference_component'] = null,
            fn (&$g) => $g['recent_backbone'] = null,
            fn (&$g) => $g['policy']['window_days'] = 365,
        ];
        foreach ($mutations as $mutation) {
            $broken = $fit; $mutation($broken);
            try { $store->assertAllAgeGraph($broken); $this->fail('Corrupt historical connection accepted.'); }
            catch (\UnexpectedValueException) { $this->addToAssertionCount(1); }
        }
        $normalized = AbilityConfidenceDisplay::normalize(array_replace($fit['ratings'][10], ['confidence_index' => 100]));
        $this->assertSame(39, $normalized['confidence_index']);
        $this->assertSame('Low', $normalized['confidence_label']);
    }

    public function test_unsupported_experimental_window_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new AllAgeRatingModel)->fit([], CarbonImmutable::parse('2026-10-02'), 1000);
    }
}
