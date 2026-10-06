<?php

namespace Tests\Unit;

use App\Services\Performance\SharedAbilityConfidencePolicy;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SharedAbilityConfidencePolicyTest extends TestCase
{
    private function evidenceMatches(string $date, int $count = 12): array
    {
        return array_map(fn ($i) => ['date' => $date, 'opponent' => $i % 6, 'event' => $i % 3, 'basis' => 'scheduled match date'], range(1, $count));
    }

    private function estimate(array $matches, ?string $finish = null, string $asOf = '2026-10-06', array $component = []): array
    {
        return (new SharedAbilityConfidencePolicy)->evaluate(['confidence_matches' => $matches, 'last_finish' => $finish], $component, CarbonImmutable::parse($asOf));
    }

    public function test_recent_broad_direct_evidence_is_stronger_but_sparse_evidence_is_low(): void
    {
        $this->assertSame(100, $this->estimate($this->evidenceMatches('2026-10-06'))['confidence_index']);
        $this->assertLessThanOrEqual(25, $this->estimate($this->evidenceMatches('2026-10-06', 1))['confidence_index']);
        $this->assertSame(75, $this->estimate($this->evidenceMatches('2026-10-06'), component: ['inferred' => 2])['confidence_index']);
    }

    public function test_many_old_matches_and_one_return_match_do_not_restore_current_confidence(): void
    {
        $old = $this->evidenceMatches('2026-01-06', 120);
        $this->assertLessThanOrEqual(13, $this->estimate($old)['confidence_index']);
        $returned = $this->estimate(array_merge($this->evidenceMatches('2026-01-06'), $this->evidenceMatches('2026-10-06', 1)));
        $this->assertLessThan(40, $returned['confidence_index']);
        $this->assertSame(1, $returned['recent_played']);
        $this->assertLessThan($this->estimate($this->evidenceMatches('2026-10-06', 13))['confidence_index'], $returned['confidence_index']);
    }

    public function test_finish_only_has_low_ceiling_and_recent_finish_does_not_refresh_direct_evidenceMatches(): void
    {
        $finish = $this->estimate([], '2026-10-06');
        $mixed = $this->estimate($this->evidenceMatches('2025-01-01'), '2026-10-06');
        $this->assertSame(15, $finish['confidence_index']);
        $this->assertSame(15, $mixed['confidence_index']);
        $this->assertSame('2025-01-01', $mixed['last_direct_match']);
        $this->assertSame('2026-10-06', $mixed['last_eligible_activity']);
        $this->assertSame(0, $mixed['recent_played']);
    }

    public function test_confidence_decays_daily_and_component_activity_cannot_refresh_own_evidence(): void
    {
        $own = $this->evidenceMatches('2026-09-01');
        $first = $this->estimate($own);
        $next = $this->estimate($own, asOf: '2026-10-07');
        $this->assertLessThan($first['effective_played'], $next['effective_played']);
        $this->assertSame($first, $this->estimate($own, component: ['played' => 5000, 'members' => range(1, 100)]));
        $this->assertSame(0, $this->estimate($this->evidenceMatches('2026-10-07'), '2026-10-07')['confidence_index']);
    }
}
