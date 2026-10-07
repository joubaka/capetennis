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
