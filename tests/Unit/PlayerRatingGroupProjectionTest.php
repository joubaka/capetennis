<?php

namespace Tests\Unit;

use App\Services\Performance\{AllAgeRatingModel, PlayerRatingGroupProjection};
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PlayerRatingGroupProjectionTest extends TestCase
{
    private function match(int $winner, int $loser, string $date = '2026-10-01'): array
    {
        return ['winner' => $winner, 'loser' => $loser, 'source_id' => $winner.':'.$loser, 'event_id' => 1,
            'event_name' => 'Synthetic played singles', 'category' => 'U13 girls', 'cohort' => 'u13 girls',
            'confidence_date' => $date, 'confidence_date_basis' => 'scheduled match date', 'ball_type' => 'yellow'];
    }

    private function snapshot(bool $historical = false): array
    {
        $matches = [$this->match(1, 2), $this->match(2, 3), $this->match(3, 4), $this->match(4, 5),
            $this->match(10, 11), $this->match(12, 13)];
        if ($historical) { $matches[] = $this->match(11, 12, '2025-06-01'); }
        return ['reason' => null, 'all_age' => (new AllAgeRatingModel)->fit($matches, CarbonImmutable::parse('2026-10-29'))];
    }

    public function test_disconnected_projection_preserves_shared_score_and_exposes_only_explicit_group_values(): void
    {
        $snapshot = $this->snapshot();
        $before = $snapshot;
        $group = $snapshot['all_age']['ratings'][10]['component'];
        $projection = (new PlayerRatingGroupProjection)->project($snapshot, $group);
        $this->assertSame([10, 11], array_keys($projection));
        $this->assertGreaterThan($projection[11]['within_group_score'], $projection[10]['within_group_score']);
        $this->assertNull($projection[10]['score']);
        $this->assertFalse($projection[10]['comparable']);
        $this->assertSame('group_local', $projection[10]['rating_scope']);
        $this->assertArrayNotHasKey('strength', $projection[10]);
        $this->assertArrayNotHasKey('local_score', $projection[10]);
        $this->assertFalse($projection[10]['historical_connection']);
        $this->assertSame($before, $snapshot);
        $this->assertSame([], (new PlayerRatingGroupProjection)->project($snapshot, ''));
        $this->assertSame([], (new PlayerRatingGroupProjection)->project($snapshot, $snapshot['all_age']['reference_component']));
    }

    public function test_group_own_historical_connections_are_marked_low_even_when_global_disconnected_metadata_is_false(): void
    {
        $snapshot = $this->snapshot(true);
        $group = $snapshot['all_age']['ratings'][10]['component'];
        $this->assertFalse($snapshot['all_age']['ratings'][12]['historical_connection']);
        $projection = (new PlayerRatingGroupProjection)->project($snapshot, $group);
        $this->assertFalse($projection[10]['historical_connection']);
        $this->assertTrue($projection[12]['historical_connection']);
        $this->assertSame('Low', $projection[12]['confidence_label']);
        $this->assertSame('2025-06-01', $projection[12]['connection_reference_date']);
    }

    public function test_withheld_nonconverged_or_corrupt_evidence_cannot_be_projected(): void
    {
        $snapshot = $this->snapshot();
        $group = $snapshot['all_age']['ratings'][10]['component'];
        $mutations = [
            fn (&$s) => $s['reason'] = 'Sources no longer published',
            fn (&$s) => $s['all_age']['components'][$group]['converged'] = false,
            fn (&$s) => $s['all_age']['ratings'][10]['local_score'] = INF,
            fn (&$s) => $s['all_age']['ratings'][10]['score'] = 66,
            fn (&$s) => $s['all_age']['ratings'][10]['comparable'] = true,
            fn (&$s) => $s['all_age']['ratings'][10]['component'] = 'foreign',
            fn (&$s) => $s['all_age']['policy']['as_of'] = '2026-02-31',
            fn (&$s) => $s['all_age']['history_evidence']['matches'] = [],
            function (&$s) { foreach ($s['all_age']['history_evidence']['matches'] as &$edge) { $edge['confidence_date'] = null; } },
        ];
        foreach ($mutations as $mutation) {
            $broken = $snapshot; $mutation($broken);
            $this->assertSame([], (new PlayerRatingGroupProjection)->project($broken, $group));
        }
    }
}
