<?php
namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, CategoryResult, Event, Player, Registration, User};
use App\Services\Performance\{PlayerAbilityValidationService, PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerAbilityValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_experimental_model_information_and_anchor_weights_are_opt_in(): void
    {
        $model = app(\App\Services\Performance\SharedAbilityModel::class);
        $edges = [['winner' => 1, 'loser' => 2, 'weight' => 1.0, 'kind' => 'played', 'source_id' => 'one']];
        $anchors = [1 => ['target' => -0.5, 'weight' => 2.0], 2 => ['target' => 0.5, 'weight' => 2.0]];
        $current = $model->fit($edges, 'test', [], $anchors);
        $diagnostic = $model->fit($edges, 'test', [], $anchors, ['diagnostics' => true]);
        $half = $model->fit($edges, 'test', [], $anchors, ['anchor_weight_scale' => 0.5]);
        $this->assertArrayNotHasKey('conditional_information', $current['ratings'][1]);
        $this->assertSame($current['ratings'][1]['strength'], $diagnostic['ratings'][1]['strength']);
        $p = 1 / (1 + exp(-($current['ratings'][1]['strength'] - $current['ratings'][2]['strength'])));
        $this->assertEqualsWithDelta(2 + $p * (1 - $p), $diagnostic['ratings'][1]['conditional_information'], 1e-12);
        $this->assertGreaterThan($current['ratings'][1]['strength'], $half['ratings'][1]['strength']);
        $this->expectException(\InvalidArgumentException::class);
        $model->fit($edges, 'test', [], $anchors, ['anchor_weight_scale' => 0.0]);
    }

    public function test_single_decay_experiment_removes_only_extra_freshness_multiplier(): void
    {
        $date = CarbonImmutable::parse('2026-04-01');
        $own = ['confidence_matches' => [['date' => '2026-01-01', 'opponent' => 2, 'event' => 1, 'basis' => 'scheduled match date']]];
        $component = ['inferred' => 0, 'bridge_count' => 0];
        $current = app(\App\Services\Performance\SharedAbilityConfidencePolicy::class)->evaluate($own, $component, $date);
        $alternative = app(\App\Services\Performance\ExperimentalConfidencePolicy::class)->evaluate($own, $component, $date);
        $this->assertGreaterThan($current['confidence_index'], $alternative['confidence_index']);
        $this->assertSame($current['effective_played'], $alternative['effective_played']);
        $this->assertSame($current['last_direct_match'], $alternative['last_direct_match']);
    }

    public function test_compare_builds_only_pre_event_snapshots_without_writes_or_cache(): void
    {
        $this->finishEvent('2026-01-01', '2026-01-02');
        $holdout = $this->finishEvent('2026-01-10', '2026-01-11');
        Cache::spy()->shouldNotReceive('store', 'remember', 'put');
        $writes = [];
        DB::listen(function ($query) use (&$writes) { if (preg_match('/^\s*(insert|update|delete|replace|create|drop)\b/i', $query->sql)) { $writes[] = $query->sql; } });
        $report = app(PlayerAbilityValidationService::class)->run($holdout, true);
        $this->assertSame('no_evidence', $report['status']);
        $this->assertSame([], $report['experiments']['paired_by_cohort']);
        $this->assertSame(0, $report['experiments']['conditional_information']['evaluated_pairs']);
        $this->assertNull($report['experiments']['conditional_information']['mean_conditional_difference_se']);
        $this->assertSame([], $writes);
        $this->assertDatabaseCount('player_ability_snapshots', 0);
    }

    public function test_experiments_compare_only_common_pairs_and_do_not_change_current_predictions(): void
    {
        $component = ['inferred' => 0, 'bridge_count' => 0, 'trial_anchored' => false];
        $data = ['ratings' => [1 => ['score' => 73.0, 'strength' => 1.0, 'component' => 'same'], 2 => ['score' => 50.0, 'strength' => 0.0, 'component' => 'same']],
            'components' => ['same' => $component], 'players' => [1 => ['played' => 0], 2 => ['played' => 0]], 'baseline' => ['anchors' => []]];
        $training = ['cohorts' => ['u10 boys' => $data]];
        $alternative = $training;
        $alternative['cohorts']['u10 boys']['ratings'][1]['strength'] = 0.5;
        $information = $training;
        foreach ([1, 2] as $id) { $information['cohorts']['u10 boys']['ratings'][$id]['conditional_strength_se'] = 0.5; }
        $match = ['source' => 'Team match', 'fixture_id' => 1, 'reason' => null, 'discipline' => 'singles', 'shared_cohort' => 'u10 boys', 'winner_side' => 1, 'player1_id' => 1, 'player2_id' => 2];
        $service = app(PlayerAbilityValidationService::class);
        $date = CarbonImmutable::parse('2026-01-09');
        $normal = $service->evaluate($training, [$match], $date);
        $report = $service->evaluate($training, [$match, array_replace($match, ['fixture_id' => 2, 'player2_id' => 99])], $date, ['trial_weight_half' => $alternative, 'current_information' => $information]);
        $this->assertSame($normal['current'], $report['current']);
        $pair = $report['experiments']['paired_by_cohort']['trial_weight_half']['u10 boys'];
        $this->assertSame(1, $pair['current_same_pairs']['evaluated_matches']);
        $this->assertSame(1, $pair['alternative_same_pairs']['evaluated_matches']);
        $this->assertGreaterThan(0, $pair['brier_change']);
        $this->assertSame('small_sample', $pair['current_same_pairs']['sample_status']);
        $uncertainty = $report['experiments']['conditional_information'];
        $this->assertSame(1, $uncertainty['evaluated_pairs']);
        $this->assertEqualsWithDelta(sqrt(0.5), $uncertainty['mean_conditional_difference_se'], 1e-12);
        $this->assertSame(1, $uncertainty['sensitivity_groups']['conditional_se_at_most_1']['evaluated_matches']);
        $this->assertSame($normal['current'], $report['experiments']['confidence_single_decay']['alternative_groups']['Low']);
        $this->assertDatabaseCount('player_ability_snapshots', 0);
    }

    public function test_empty_and_unrated_holdouts_explain_coverage_without_fabricating_accuracy(): void
    {
        $service = app(PlayerAbilityValidationService::class);
        $date = CarbonImmutable::parse('2026-01-09');
        $empty = $service->evaluate([], [], $date);
        $this->assertSame('no_evidence', $empty['status']);
        $this->assertNull($empty['coverage']);
        $this->assertNull($empty['chance_reference']['brier']);
        $match = ['source' => 'Team match', 'fixture_id' => 1, 'reason' => null, 'discipline' => 'singles', 'shared_cohort' => 'u10 boys', 'winner_side' => 1, 'player1_id' => 1, 'player2_id' => 2];
        $report = $service->evaluate([], [$match, $match, array_replace($match, ['fixture_id' => 2, 'reason' => 'Team match is incomplete'])], $date);
        $this->assertSame('insufficient_coverage', $report['status']);
        $this->assertSame(0, $report['current']['evaluated_matches']);
        $this->assertSame(3, $report['source_diagnostics']['Team match']['yielded']);
        $this->assertSame(1, $report['source_diagnostics']['Team match']['duplicates']);
        $this->assertSame(1, $report['source_diagnostics']['Team match']['exclusion_reasons']['missing_pre_event_rating']);
        $this->assertSame(1, $report['source_diagnostics']['Team match']['exclusion_reasons']['Team match is incomplete']);
    }

    public function test_operator_event_lists_are_bounded_and_preserve_order_without_duplicate_holdouts(): void
    {
        $this->assertSame([3], PlayerAbilityValidationService::eventIds(['event' => '3']));
        $this->assertSame([3, 2], PlayerAbilityValidationService::eventIds(['events' => '3,2,3']));
        foreach ([[], ['event' => '1', 'events' => '2'], ['events' => ''], ['event' => '1,2'], ['events' => '1,-2'], ['events' => implode(',', range(1, 11))], ['events' => (string) PHP_INT_MAX.'0']] as $invalid) {
            try { PlayerAbilityValidationService::eventIds($invalid); $this->fail('Invalid list accepted'); }
            catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
        }
    }

    public function test_run_explains_prefiltered_unpublished_and_unscored_sources_with_count_only_funnels(): void
    {
        $event = Event::factory()->create(['start_date' => '2026-01-10', 'end_date' => '2026-01-11', 'published' => true]);
        $hidden = \App\Models\Draw::factory()->create(['event_id' => $event->id, 'published' => false]);
        $visible = \App\Models\Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        \App\Models\Fixture::factory()->create(['draw_id' => $hidden->id]);
        \App\Models\Fixture::factory()->create(['draw_id' => $visible->id]);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('fingerprint')->twice()->andReturn('unchanged')
            ->shouldReceive('validationSnapshot')->once()->andReturn(['reason' => null, 'cohorts' => []]);
        $report = app(PlayerAbilityValidationService::class)->run($event);
        $this->assertSame('no_evidence', $report['status']);
        $this->assertSame(['all_event_fixtures' => 2, 'published_draw' => 1, 'with_recorded_scores' => 0, 'canonical_reader_candidates' => 0], $report['source_candidates']['Individual match']);
        $this->assertSame(0, $report['source_candidates']['Team match']['all_event_fixtures']);
        $this->assertSame([], $report['source_diagnostics']);
        $this->assertDatabaseCount('player_ability_snapshots', 0);
    }

    private function finishEvent(string $start, ?string $end): Event
    {
        $event = Event::factory()->create(['start_date' => $start, 'end_date' => $end, 'published' => true, 'results_published' => true]);
        $category = Category::factory()->create(['name' => 'U10 Boys']);
        $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $category->id]);
        foreach ([1, 2] as $rank) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create());
            CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
            CategoryResult::create(['event_id' => $event->id, 'category_id' => $category->id, 'registration_id' => $registration->id, 'position' => $rank]);
        }
        return $event;
    }
    public function test_completed_event_cutoff_excludes_holdout_overlap_missing_and_invalid_end_dates_without_cache_or_writes(): void
    {
        $prior = $this->finishEvent('2026-01-01', '2026-01-02');
        $holdout = $this->finishEvent('2026-01-10', '2026-01-11');
        $this->finishEvent('2026-01-05', '2026-01-15');
        $this->finishEvent('2026-01-05', null);
        $this->finishEvent('2026-01-04', '2026-01-10');
        $this->finishEvent('2026-01-08', '2026-01-07');
        Cache::spy()->shouldNotReceive('store', 'remember', 'put');
        $writes = [];
        DB::listen(function ($query) use (&$writes) { if (preg_match('/^\s*(insert|update|delete|replace|create|drop)\b/i', $query->sql)) { $writes[] = $query->sql; } });
        $snapshot = app(PlayerSharedAbilityService::class)->validationSnapshot(CarbonImmutable::parse('2026-01-10'), $holdout->id);
        $this->assertSame([$prior->id], $snapshot['source_events']);
        $this->assertSame([], $snapshot['source_matches']);
        $this->assertSame([], $writes);
        $this->assertDatabaseCount('player_ability_snapshots', 0);
    }
    public function test_metrics_count_only_compatible_pairs_and_report_ties_deduplication_and_paired_trial_reference(): void
    {
        $component = ['inferred' => 0, 'bridge_count' => 0, 'trial_anchored' => true];
        $own = ['played' => 0, 'last_finish' => '2026-01-01'];
        $data = ['ratings' => [1 => ['score' => 73., 'strength' => 1., 'component' => 'same'], 2 => ['score' => 50., 'strength' => 0., 'component' => 'same'], 3 => ['score' => 50., 'strength' => 0., 'component' => 'other'], 4 => ['score' => 50., 'strength' => 0., 'component' => 'same']],
            'components' => ['same' => $component, 'other' => $component], 'players' => [1 => $own, 2 => $own, 3 => $own, 4 => $own],
            'baseline' => ['anchors' => [1 => ['target' => 1.], 2 => ['target' => 0.], 4 => ['target' => 0.]]]];
        $match = ['source' => 'Individual match', 'fixture_id' => 1, 'reason' => null, 'discipline' => 'singles', 'cohort' => 'U10 Boys', 'winner_side' => 1, 'player1_id' => 1, 'player2_id' => 2];
        $matches = [$match, $match, array_replace($match, ['fixture_id' => 2, 'player1_id' => 2, 'player2_id' => 4]), array_replace($match, ['fixture_id' => 3, 'player2_id' => 3]), array_replace($match, ['fixture_id' => 4, 'player2_id' => 99])];
        $report = app(PlayerAbilityValidationService::class)->evaluate(['cohorts' => ['u10 boys' => $data]], $matches, CarbonImmutable::parse('2026-01-09'));
        $this->assertSame(4, $report['eligible_matches']);
        $this->assertSame(2, $report['current']['evaluated_matches']);
        $this->assertSame(1, $report['current']['equal_rating_pairs']);
        $this->assertSame(1, $report['excluded']['different_comparison_group']);
        $this->assertSame(0.5, $report['coverage']);
        $this->assertEquals(1., $report['current']['higher_rated_win_rate']);
        $this->assertEqualsWithDelta(((1 - 1 / (1 + exp(-1))) ** 2 + .25) / 2, $report['current']['brier'], 1e-12);
        $this->assertSame(2, $report['confidence_groups']['Low']['evaluated_matches']);
        $this->assertSame(2, $report['direct_trial_reference']['evaluated_matches']);
        $this->assertSame('partial_coverage', $report['status']);
        $this->assertSame(2, $report['chance_reference']['evaluated_matches']);
        $this->assertEquals(.25, $report['chance_reference']['brier']);
        $this->assertEqualsWithDelta(log(2), $report['chance_reference']['log_loss'], 1e-12);
    }
    public function test_existing_saved_badges_get_structural_limits_without_refitting_or_policy_changes(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $raw = ['confidence_index' => 90, 'confidence_band' => 'Stronger', 'cohort' => 'u10 boys', 'component' => 'group', 'baseline_status' => 'Direct main-trial baseline'];
        $snapshot = ['reason' => null, 'badge_players' => [1 => [$raw]], 'cohorts' => ['u10 boys' => ['components' => ['group' => ['inferred' => 1, 'bridge_count' => 0]], 'players' => [1 => ['played' => 15]]]]];
        $this->partialMock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->once()->andReturn($snapshot);
        $this->partialMock(PlayerSharedAbilityService::class)->shouldNotReceive('fingerprint', 'calculateSnapshot');
        $rating = app(PlayerSharedAbilityService::class)->badgeSnapshot()[1][0];
        $this->assertSame('Medium', $rating['confidence_band']);
        $this->assertSame(90, $rating['confidence_index']);
        $this->assertStringContainsString('inferred', $rating['confidence_explanation']);
    }
}
