<?php

namespace Tests\Unit;

use App\Services\Performance\{PlayerRatingEvidenceBuilder, PlayerRatingHistoryDisplay, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use App\Models\Player;
use Carbon\CarbonImmutable;
use Mockery;
use Tests\TestCase;

class PlayerRatingEvidenceTest extends TestCase
{
    private function row(int $id, array $players = [7, 8], int $months = 3072): array
    {
        return ['source_id' => 'match:'.$id, 'kind' => 'match', 'cohort' => 'u10 boys', 'event_id' => 1, 'event' => 'Published event',
            'date' => '2026-09-15', 'date_basis' => 'scheduled match date', 'completed_at' => '2026-09-16',
            'players' => $players, 'names' => ['First Player', 'Second Player'], 'winner_side' => 1, 'score' => '6-1 6-4', 'months' => $months, 'baseline_months' => 0];
    }

    public function test_registry_uses_final_fit_edges_and_retained_fields_once_with_baseline_month_roles(): void
    {
        $builder = new PlayerRatingEvidenceBuilder;
        $raw = fn ($id) => ['source' => 'Individual match', 'fixture_id' => $id, 'event_id' => 1, 'event' => 'Published event',
            'confidence_date' => '2026-09-15', 'confidence_date_basis' => 'scheduled match date', 'date' => '2026-09-16',
            'player1_id' => 7, 'player2_id' => 8, 'winner_side' => 1, 'score' => '6-1 6-4'];
        $field = ['source_id' => 'ordinal:1:u10 boys:0', 'players' => [7, 8], 'weight' => 0.2,
            'event_id' => 1, 'event_name' => 'Published event', 'event_date' => '2026-09-14', 'completed_at' => '2026-09-16'];
        $sources = [1 => ['matches' => [$raw(1), $raw(2), $raw(99)], 'ordinal' => ['Covered field, not retained']]];
        $snapshot = ['names' => [7 => 'First Player', 8 => 'Second Player'], 'cohorts' => ['u10 boys' => [
            'ratings' => [7 => ['component' => 'a'], 8 => ['component' => 'a']], 'components' => ['a' => ['members' => [7, 8]]],
            'history_evidence' => ['matches' => [['source_id' => 'match:1'], ['source_id' => 'match:2', 'topology_only' => true]],
                'fields' => [$field, array_replace($field, ['source_id' => 'invalid', 'players' => [7, 7]])]],
        ]]];
        $builder->append($snapshot, $sources, 10);
        unset($snapshot['cohorts']['u10 boys']['history_evidence']['matches'][1]['topology_only']);
        $builder->append($snapshot, $sources, 11);
        $rows = collect($builder->all())->keyBy('source_id');
        $this->assertCount(3, $rows);
        $this->assertArrayNotHasKey('match:99', $rows->all());
        $this->assertArrayNotHasKey('invalid', $rows->all());
        $this->assertSame(3072, $rows['match:1']['months']);
        $this->assertSame(1024, $rows['match:2']['baseline_months']);
        $this->assertSame(0, $rows['match:1']['baseline_months']);
        $this->assertSame('finish', $rows[$field['source_id']]['kind']);
    }

    public function test_saved_evidence_filters_player_cohort_month_new_scope_and_orients_score_before_pagination(): void
    {
        $ability = Mockery::mock(PlayerSharedAbilityService::class);
        $ability->shouldReceive('fingerprint')->times(4)->andReturn('same');
        $ability->shouldNotReceive('historySnapshot', 'validationSnapshot', 'calculateSnapshot');
        $points = ['score' => 55.0, 'group' => 'same', 'component' => 'a', 'last_activity' => '2026-10-20'];
        $saved = ['as_of' => '2026-10-29', 'built_at' => 'saved', 'source_fingerprint' => 'same', 'months' => [], 'evidence' => []];
        for ($index = 0; $index < 12; $index++) $saved['months'][] = ['date' => '2026-10-29', 'cohorts' => ['u10 boys' => [8 => $points]]];
        for ($id = 1; $id <= 25; $id++) $saved['evidence'][] = $this->row($id);
        $new = $this->row(26, [7, 8], 2048); $new['baseline_months'] = 2048;
        $saved['evidence'][] = $new;
        $saved['evidence'][] = $this->row(27, [7, 99]);
        $saved['evidence'][] = array_replace($this->row(28), ['cohort' => 'green ball']);
        $store = Mockery::mock(PlayerRatingHistoryStore::class);
        $store->shouldReceive('read')->times(4)->andReturn($saved);
        $service = new PlayerRatingHistoryService($ability, $store);
        $player = new Player; $player->id = 8;
        $data = $service->forPlayer($player, ['month' => 11]);
        $this->assertSame(26, $data['evidence']['included']);
        $this->assertSame(26, $data['evidence']['matches']);
        $this->assertSame(0, $data['evidence']['wins']);
        $this->assertSame(26, $data['evidence']['losses']);
        $this->assertSame(1, $data['evidence']['new']);
        $this->assertCount(1, $data['evidence']['rows']);
        $this->assertSame('1-6 4-6', $data['evidence']['rows'][0]['score']);
        $this->assertSame('First Player', $data['evidence']['rows'][0]['opponent']);
        $this->assertSame('Main-trial baseline result', $data['evidence']['rows'][0]['role']);
        $all = $service->forPlayer($player, ['month' => 11, 'scope' => 'all']);
        $this->assertCount(20, $all['evidence']['rows']);
        $this->assertSame(2, $all['evidence']['pages']);
        $page = $service->forPlayer($player, ['month' => 11, 'scope' => 'all', 'page' => 2]);
        $this->assertCount(6, $page['evidence']['rows']);
        $old = $service->forPlayer($player, ['month' => 9, 'scope' => 'all']);
        $this->assertSame(0, $old['evidence']['included']);
    }

    public function test_focused_axis_has_five_point_steps_explicit_padding_and_full_scale_option(): void
    {
        $axis = PlayerRatingHistoryDisplay::axis([['score' => 53.5], ['score' => 56.3]], 'focused');
        $this->assertSame(['min' => 45, 'max' => 65, 'step' => 5, 'ticks' => [45, 50, 55, 60, 65]], $axis);
        $this->assertSame(['min' => 0, 'max' => 100, 'step' => 25, 'ticks' => [0, 25, 50, 75, 100]], PlayerRatingHistoryDisplay::axis([], 'full'));
        foreach ([0, 100] as $score) {
            $edge = PlayerRatingHistoryDisplay::axis([['score' => $score]], 'focused');
            $this->assertGreaterThanOrEqual(0, $edge['min']);
            $this->assertLessThanOrEqual(100, $edge['max']);
            $this->assertGreaterThanOrEqual(20, $edge['max'] - $edge['min']);
        }
    }
}
