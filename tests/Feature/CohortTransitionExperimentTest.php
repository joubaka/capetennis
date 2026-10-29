<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Services\Performance\{CohortTransitionEvidenceService, CohortTransitionValidationService, PlayerPerformanceHistoryService, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB};
use Tests\TestCase;

class CohortTransitionExperimentTest extends TestCase
{
    use RefreshDatabase;

    public function test_collector_excludes_holdout_future_overlap_invalid_dates_and_deduplicates_original_sources_without_writes(): void
    {
        $prior = Event::factory()->create(['published' => true, 'start_date' => '2025-10-17', 'end_date' => '2025-10-18']);
        $holdout = Event::factory()->create(['published' => true, 'start_date' => '2026-10-09', 'end_date' => '2026-10-11']);
        foreach ([['2026-10-08', '2026-10-09'], ['2026-10-08', '2026-10-12'], ['2026-10-08', null], ['2026-10-08', '2026-10-07'], ['2026-10-12', '2026-10-13']] as [$start, $end]) {
            Event::factory()->create(['published' => true, 'start_date' => $start, 'end_date' => $end]);
        }
        $row = ['source' => 'Individual match', 'fixture_id' => 55, 'reason' => null, 'discipline' => 'singles', 'cohort' => 'u10 boys',
            'player1_id' => 1, 'player2_id' => 2, 'winner_side' => 1, 'confidence_date' => '2025-10-18',
            'confidence_date_basis' => 'scheduled match date', 'outcome_target' => 0.9];
        $history = $this->mock(PlayerPerformanceHistoryService::class);
        $history->shouldReceive('individualMatches')->once()->withArgs(fn ($event) => $event->id === $prior->id)->andReturnUsing(function () use ($row) { yield $row; yield $row; });
        $history->shouldReceive('teamMatches')->once()->withArgs(fn ($event) => $event->id === $prior->id)->andReturnUsing(function () { yield from []; });
        Cache::spy()->shouldNotReceive('store', 'remember', 'put');
        $writes = [];
        DB::listen(function ($q) use (&$writes) { if (preg_match('/^\s*(insert|update|delete|replace|create|drop)\b/i', $q->sql)) { $writes[] = $q->sql; } });
        $report = app(CohortTransitionEvidenceService::class)->collect(CarbonImmutable::parse('2026-10-09'), $holdout->id);
        $this->assertCount(1, $report['rows']);
        $this->assertSame('Individual match:55', $report['rows'][0]['source_id']);
        $this->assertSame($prior->id, $report['rows'][0]['event_id']);
        $this->assertFalse($report['rows'][0]['context_verified']);
        $this->assertSame([], $writes);
        $this->assertDatabaseCount('player_ability_snapshots', 0);
    }

    public function test_validation_remains_preview_only_and_context_uncertainty_is_not_reported_as_absent_history(): void
    {
        $holdout = Event::factory()->create(['published' => true, 'start_date' => '2026-01-10', 'end_date' => '2026-01-11']);
        $this->mock(PlayerSharedAbilityService::class, function ($mock) {
            $mock->shouldReceive('fingerprint')->twice()->andReturn('same');
            $mock->shouldReceive('validationSnapshot')->once()->andReturn(['reason' => null, 'cohorts' => []]);
        });
        $this->mock(CohortTransitionEvidenceService::class)->shouldReceive('collect')->once()->andReturn(['rows' => [
            ['source_id' => 'match:1', 'event_id' => 1, 'cohort' => 'u10 boys', 'winner' => 1, 'loser' => 2, 'date' => '2026-01-01', 'outcome_target' => 1.0, 'context_verified' => false],
        ], 'excluded' => []]);
        $this->mock(PlayerPerformanceHistoryService::class, function ($mock) {
            $mock->shouldReceive('individualMatches')->once()->andReturnUsing(function () { yield from []; });
            $mock->shouldReceive('teamMatches')->once()->andReturnUsing(function () {
                yield ['source' => 'Team match', 'fixture_id' => 8, 'shared_cohort' => 'u11 boys', 'reason' => null, 'discipline' => 'singles', 'player1_id' => 1, 'player2_id' => 2, 'winner_side' => 1];
            });
        });
        Cache::spy()->shouldNotReceive('store', 'remember', 'put');
        $writes = [];
        DB::listen(function ($q) use (&$writes) { if (preg_match('/^\s*(insert|update|delete|replace|create|drop)\b/i', $q->sql)) { $writes[] = $q->sql; } });
        $report = app(CohortTransitionValidationService::class)->run($holdout);
        $this->assertTrue($report['preview_only']);
        $this->assertSame(2, $report['cohorts']['u11 boys']['transition_reasons_by_missing_player']['context_unverified']);
        $this->assertSame(0, $report['cohorts']['u11 boys']['robust']['new_pairs']['evaluated_matches']);
        $this->assertSame([], $writes);
        $this->assertDatabaseCount('player_ability_snapshots', 0);
    }

    public function test_identity_routing_is_report_equivalent_and_independent_of_heldout_winners_scores_and_rejection_reasons(): void
    {
        $nativeCohort = ['ratings' => [], 'players' => []];
        foreach ([2, 3, 4] as $id) {
            $nativeCohort['ratings'][$id] = ['strength' => 0.1 * $id, 'score' => 50.0, 'component' => 'target'];
            $nativeCohort['players'][$id] = ['played' => 5];
        }
        $native = ['cohorts' => ['u11 boys' => $nativeCohort]];
        $history = [];
        foreach ([1, 6, 99] as $focal) {
            foreach ([2, 3, 4] as $peer) {
                $history[] = ['source_id' => $focal.':'.$peer, 'event_id' => $peer, 'cohort' => 'u10 boys',
                    'winner' => $focal, 'loser' => $peer, 'date' => '2026-01-0'.$peer,
                    'date_basis' => 'scheduled match date', 'outcome_target' => 1.0, 'context_verified' => true];
            }
        }
        $heldout = [
            ['source' => 'Team match', 'fixture_id' => 1, 'reason' => null, 'discipline' => 'singles', 'player1_id' => 1, 'player2_id' => 2, 'winner_side' => 1, 'score' => '6-3'],
            // Rejected rows still route their participant identities, including
            // numeric string IDs returned by some database configurations.
            ['source' => 'Team match', 'fixture_id' => 2, 'reason' => 'unfinished', 'discipline' => 'singles', 'player1_id' => '6', 'player2_id' => 2, 'winner_side' => 0, 'score' => '1-0'],
        ];
        $service = app(CohortTransitionValidationService::class);
        $date = CarbonImmutable::parse('2026-04-01');
        $full = $service->predictions($native, $history, $date, true);
        $routes = $service->participantRoutes(['u11 boys' => $heldout]);
        $routed = $service->predictions($native, $history, $date, true, $routes);
        $this->assertArrayHasKey(99, $full['u11 boys']);
        $this->assertArrayNotHasKey(99, $routed['u11 boys']);
        $this->assertArrayHasKey(6, $routed['u11 boys']);
        $this->assertSame($full['u11 boys'][1], $routed['u11 boys'][1]);
        $this->assertSame($full['u11 boys'][6], $routed['u11 boys'][6]);
        $this->assertSame($service->evaluate($nativeCohort, $heldout, $full['u11 boys']), $service->evaluate($nativeCohort, $heldout, $routed['u11 boys']));
        $swapped = $heldout;
        $swapped[0]['winner_side'] = 2; $swapped[0]['score'] = '3-6';
        $swapped[1]['reason'] = 'different rejection'; $swapped[1]['score'] = '0-5';
        $swappedRoutes = $service->participantRoutes(['u11 boys' => $swapped]);
        $this->assertSame($routes, $swappedRoutes);
        $swappedPredictions = $service->predictions($native, $history, $date, true, $swappedRoutes);
        $this->assertSame($routed, $swappedPredictions);
        $this->assertSame($service->evaluate($nativeCohort, $swapped, $full['u11 boys']), $service->evaluate($nativeCohort, $swapped, $swappedPredictions['u11 boys']));
        $this->assertNotSame($service->evaluate($nativeCohort, $heldout, $routed['u11 boys'])['exploratory_only']['new_pairs']['brier'],
            $service->evaluate($nativeCohort, $swapped, $swappedPredictions['u11 boys'])['exploratory_only']['new_pairs']['brier']);
    }
}
