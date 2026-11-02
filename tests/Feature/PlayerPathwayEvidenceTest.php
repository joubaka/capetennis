<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerPathwayEvidence, PlayerRatingEvidenceBuilder};
use Carbon\CarbonImmutable;
use Tests\TestCase;

class PlayerPathwayEvidenceTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function fixture(array $rows): array
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01', 'Africa/Johannesburg'));
        $player = new Player; $player->id = 1;
        $saved = ['as_of' => '2026-11-01', 'publication_manifest' => ['events' => [], 'draws' => [], 'fixtures' => [], 'team_fixtures' => []], 'evidence' => []];
        foreach ($rows as $index => $changes) {
            $id = $index + 1;
            $row = array_replace(['source_id' => 'match:'.$id, 'event_id' => $id, 'kind' => 'match', 'cohort' => AllAgeRatingModel::COHORT,
                'players' => [1, 2], 'winner_side' => 1, 'score' => '6-4 6-3', 'date' => '2026-10-01', 'months' => 2048, 'ball_type' => 'red'], $changes);
            $event = $row['event_id'];
            $saved['evidence'][] = $row;
            if ($event > 0) {
                $saved['publication_manifest']['events'][$event] = ['id' => $event, 'published' => $changes['event_published'] ?? true];
                $saved['publication_manifest']['draws'][$event] = ['id' => $event, 'event_id' => $event, 'published' => true];
                $saved['publication_manifest']['fixtures'][] = ['id' => $id, 'draw_id' => $event];
            }
        }
        $points = [['index' => 11, 'date' => '2026-11-01', 'score' => 60, 'connection_status' => 'recent', 'historical_connection' => false]];
        return [$saved, $player, $points];
    }

    public function test_distinct_native_personal_latest_window_events_and_ball_stages_are_exact(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('published')->once()->andReturn(true);
        [$saved, $player, $points] = $this->fixture([
            ['event_id' => 1], ['event_id' => 1, 'ball_type' => 'orange'], ['event_id' => 2, 'ball_type' => 'orange'],
            ['event_id' => 3, 'ball_type' => 'orange'], ['event_id' => 4, 'ball_type' => 'green'], ['event_id' => 5, 'ball_type' => 'yellow'],
            ['event_id' => 6, 'ball_type' => null], ['event_id' => 7, 'players' => [3, 4]],
            ['event_id' => 8, 'kind' => 'finish'], ['event_id' => 9, 'score' => 'W/O'], ['event_id' => 10, 'date' => '2026-12-01'],
            ['event_id' => 11, 'months' => 1024], ['event_id' => 12, 'date' => '2023-01-01'],
            ['event_id' => -9, 'source_id' => 'teamsheet:private'], ['event_id' => 0],
            ['event_id' => 13, 'event_published' => false], ['event_id' => 14, 'source_id' => 'registration:14'],
        ]);
        $metrics = app(PlayerPathwayEvidence::class)->fromHistory($saved, $player, $points, false);
        $this->assertSame(6, $metrics['events']);
        $this->assertSame(['red' => 1, 'orange' => 3, 'standard' => 3], $metrics['stage_events']);
        $this->assertSame(0, $metrics['unknown_stage_events']);
        $this->assertTrue($metrics['comparable']);
        $this->assertFalse($metrics['stale']);
    }

    public function test_revoked_publication_or_missing_context_withholds_metrics(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('published')->once()->andReturn(false);
        [$saved, $player, $points] = $this->fixture([[]]);
        $metrics = app(PlayerPathwayEvidence::class)->fromHistory($saved, $player, $points, false);
        $this->assertFalse($metrics['available']); $this->assertNull($metrics['events']);
        unset($saved['publication_manifest']);
        $this->assertFalse(app(PlayerPathwayEvidence::class)->fromHistory($saved, $player, $points, false)['available']);
    }

    public function test_unstated_ball_defaults_to_normal_and_explicit_red_event_label_is_respected(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('published')->once()->andReturn(true);
        [$saved, $player, $points] = $this->fixture([['ball_type' => null], ['ball_type' => null], ['ball_type' => null]]);
        foreach ($saved['evidence'] as &$row) { unset($row['ball_type']); $row['event'] = 'Fictional event'; }
        unset($row);
        $metrics = app(PlayerPathwayEvidence::class)->fromHistory($saved, $player, $points, false);
        $this->assertSame(3, $metrics['events']);
        $this->assertSame(['red' => 0, 'orange' => 0, 'standard' => 3], $metrics['stage_events']);
        $this->assertSame(0, $metrics['unknown_stage_events']);
    }

    public function test_three_private_external_events_never_count_as_cape_tennis_stage_events(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('published')->once()->andReturn(true);
        [$saved, $player, $points] = $this->fixture([
            ['event_id' => -1, 'source_id' => 'teamsheet:1'], ['event_id' => -2, 'source_id' => 'teamsheet:2'], ['event_id' => -3, 'source_id' => 'teamsheet:3'],
        ]);
        $metrics = app(PlayerPathwayEvidence::class)->fromHistory($saved, $player, $points, false);
        $this->assertSame(0, $metrics['events']);
        $this->assertSame(['red' => 0, 'orange' => 0, 'standard' => 0], $metrics['stage_events']);
        unset($saved['publication_manifest']['fixtures']);
        $this->assertNull(app(PlayerPathwayEvidence::class)->fromHistory($saved, $player, $points, false)['events']);
    }

    public function test_stale_historical_disconnected_and_future_ratings_never_qualify(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('published')->andReturn(true);
        [$saved, $player, $points] = $this->fixture([[]]);
        $this->assertTrue(app(PlayerPathwayEvidence::class)->fromHistory($saved, $player, $points, true)['stale']);
        foreach (['historical', 'disconnected'] as $status) {
            $points[0]['connection_status'] = $status;
            $this->assertFalse(app(PlayerPathwayEvidence::class)->fromHistory($saved, $player, $points, false)['comparable']);
        }
        $points[0]['date'] = '2026-12-01';
        $this->assertFalse(app(PlayerPathwayEvidence::class)->fromHistory($saved, $player, $points, false)['available']);
    }

    public function test_builder_preserves_only_authoritative_edge_ball_metadata(): void
    {
        $match = ['source_id' => 'match:1', 'event_id' => 1, 'event' => 'Fictional event', 'confidence_date' => '2026-10-01',
            'confidence_date_basis' => 'actual match date', 'date' => '2026-10-01', 'player1_id' => 1, 'player2_id' => 2, 'winner_side' => 1, 'score' => '6-4 6-3'];
        $builder = new PlayerRatingEvidenceBuilder;
        $builder->append(['names' => [1 => 'One', 2 => 'Two'], 'cohorts' => [], 'all_age' => ['history_evidence' => ['matches' => [['source_id' => 'match:1', 'ball_type' => 'orange']]]]],
            [1 => ['matches' => [$match]]], 11);
        $this->assertSame('orange', $builder->all()[0]['ball_type']);
    }

    public function test_yesterdays_unchanged_build_can_be_assessed_but_changed_or_older_builds_cannot(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('published')->andReturn(true);
        [$saved, $player, $points] = $this->fixture([[]]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02', 'Africa/Johannesburg'));
        $metrics = app(PlayerPathwayEvidence::class)->fromHistory($saved, $player, $points, false);
        $this->assertTrue($metrics['stale']);
        $this->assertTrue($metrics['assessment_available']);
        $this->assertFalse(app(PlayerPathwayEvidence::class)->fromHistory($saved, $player, $points, true)['assessment_available']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-03', 'Africa/Johannesburg'));
        $this->assertFalse(app(PlayerPathwayEvidence::class)->fromHistory($saved, $player, $points, false)['assessment_available']);
    }
}
