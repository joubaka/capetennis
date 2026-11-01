<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\Performance\{AllAgeRatingModel, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Tests\TestCase;

class PlayerRatingEvidenceSummaryTest extends TestCase
{
    private function rating(array $changes = []): array
    {
        return array_replace(['score' => 62.3, 'component' => 'shared', 'group' => 'same',
            'connection_status' => 'recent', 'historical_connection' => false,
            'connection_age_days' => 20, 'connection_reference_date' => '2026-09-11',
            'confidence_label' => 'Medium', 'last_direct_match' => '2026-09-20',
            'recent_played' => 4, 'direct_opponents' => 8, 'played_events' => 3, 'proxy_dated_matches' => 0], $changes);
    }

    private function history(array $ratings, int $month): array
    {
        $ability = $this->createStub(PlayerSharedAbilityService::class);
        $ability->method('fingerprint')->willReturn('same');
        $store = $this->createStub(PlayerRatingHistoryStore::class);
        $months = [];
        foreach ($ratings as $index => $rating) {
            $months[] = ['date' => ['2026-09-30', '2026-10-31'][$index], 'cohorts' => [AllAgeRatingModel::COHORT => $rating === null ? [] : [1 => $rating]]];
        }
        $store->method('read')->willReturn(['source_fingerprint' => 'same', 'as_of' => '2026-10-31', 'built_at' => '2026-10-31', 'evidence' => [], 'months' => $months]);
        $player = new Player;
        $player->setRawAttributes(['id' => 1, 'name' => 'Synthetic', 'surname' => 'Player']);
        $history = (new PlayerRatingHistoryService($ability, $store))->forPlayer($player, ['month' => $month]);
        $html = view('frontend.my-tennis.rating', ['ratingPlayer' => $player, 'ratingHistory' => $history])->render();
        return [$history, $html];
    }

    public function test_selected_month_uses_its_own_confidence_and_freshness(): void
    {
        [$history, $html] = $this->history([$this->rating(), $this->rating(['confidence_label' => 'High', 'last_direct_match' => '2026-10-30'])], 0);
        $this->assertSame('Medium', $history['point']['confidence_label']);
        $this->assertStringContainsString('Evidence confidence: Medium', $html);
        $this->assertStringContainsString('as of 2026-09-30', $html);
        $this->assertStringContainsString('Last included singles result: 20 Sep 2026', $html);
        $this->assertStringNotContainsString('30 Oct 2026', $html);
        $this->assertStringContainsString('4 matches in the 90 days before this estimate', $html);
        $this->assertStringContainsString('8 distinct opponents across 3 events', $html);
        $this->assertStringContainsString('not an accuracy percentage', $html);
        if ($path = getenv('RATING_SUMMARY_HTML')) {
            file_put_contents($path, '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font:16px Arial;color:#233d50;margin:24px}.text-muted{color:#5e716c}.small{font-size:14px}p{line-height:1.5}.mb-1{margin-bottom:4px}.mb-3{margin-bottom:16px}.mt-2{margin-top:8px}.mt-3{margin-top:16px}.mt-4{margin-top:24px}select{max-width:100%;padding:10px}.card-body{max-width:1100px;margin:auto}svg{display:block}</style></head><body>'.$html.'</body></html>');
        }
    }

    public function test_old_snapshots_do_not_invent_confidence_or_freshness(): void
    {
        $old = array_diff_key($this->rating(), array_flip(['confidence_label', 'last_direct_match', 'recent_played', 'direct_opponents', 'played_events', 'proxy_dated_matches']));
        [, $html] = $this->history([$old], 0);
        $this->assertStringContainsString('Evidence confidence: Not recorded', $html);
        $this->assertStringContainsString('next nightly update', $html);
        $this->assertStringNotContainsString('Last included singles result:', $html);
    }

    public function test_movement_distinguishes_comparable_change_and_changed_group(): void
    {
        [, $same] = $this->history([$this->rating(['score' => 60]), $this->rating()], 1);
        $this->assertStringContainsString('+2.3', $same);
        $this->assertStringContainsString('same comparison group', $same);
        $this->assertStringContainsString('older matches losing weight', $same);
        [, $changed] = $this->history([$this->rating(['score' => 40]), $this->rating(['group' => 'changed'])], 1);
        $this->assertStringContainsString('The difference does not reliably show improvement or decline', $changed);
        $this->assertStringNotContainsString('+22.3', $changed);
        $this->assertStringContainsString('Dashed lines show a changed comparison group or reference', $changed);
        [, $gap] = $this->history([null, $this->rating()], 1);
        $this->assertStringContainsString('No comparable previous-month estimate', $gap);
    }

    public function test_proxy_dates_are_explained_and_unrated_month_has_no_evidence_summary(): void
    {
        [, $html] = $this->history([$this->rating(['proxy_dated_matches' => 2])], 0);
        $this->assertStringContainsString('Some result dates use the event date', $html);
        [, $empty] = $this->history([null], 0);
        $this->assertStringNotContainsString('Selected month evidence summary', $empty);
    }

    public function test_background_calculation_retains_each_snapshots_canonical_evidence_metadata(): void
    {
        $ability = $this->createStub(PlayerSharedAbilityService::class);
        $ability->method('fingerprint')->willReturn(str_repeat('a', 64));
        $ability->method('historySnapshot')->willReturnCallback(function (CarbonImmutable $date, array &$sources): array {
            return ['reason' => null, 'cohorts' => [], 'all_age' => [
                'components' => ['shared' => ['converged' => true, 'members' => [1]]],
                'ratings' => [1 => $this->rating(['confidence_label' => $date->month === 10 ? 'High' : 'Low', 'last_direct_match' => $date->toDateString()])],
                'players' => [1 => ['last_played' => $date->toDateString()]],
            ]];
        });
        $saved = (new PlayerRatingHistoryService($ability, $this->createStub(PlayerRatingHistoryStore::class)))->calculate(CarbonImmutable::parse('2026-10-31'));
        $first = $saved['months'][0]['cohorts'][AllAgeRatingModel::COHORT][1];
        $last = $saved['months'][11]['cohorts'][AllAgeRatingModel::COHORT][1];
        $this->assertSame('Low', $first['confidence_label']);
        $this->assertSame('2025-11-30', $first['last_direct_match']);
        $this->assertSame('High', $last['confidence_label']);
        $this->assertSame('2026-10-31', $last['last_direct_match']);
        $this->assertSame(4, $last['recent_played']);
        $this->assertSame(8, $last['direct_opponents']);
        $this->assertSame(3, $last['played_events']);
    }

    public function test_store_accepts_legacy_metadata_and_rejects_invalid_optional_summary(): void
    {
        $store = new class extends PlayerRatingHistoryStore {
            public function path(): string { return storage_path('framework/testing/evidence-summary-store-'.getmypid().'.json'); }
        };
        $today = CarbonImmutable::today('Africa/Johannesburg');
        $saved = ['version' => PlayerRatingHistoryStore::VERSION, 'policy_hash' => $store->policyHash(),
            'source_fingerprint' => str_repeat('a', 64), 'as_of' => $today->toDateString(), 'built_at' => $today->toIso8601String(), 'evidence' => [], 'months' => []];
        for ($offset = 11; $offset >= 0; $offset--) {
            $date = $offset === 0 ? $today : $today->startOfMonth()->subMonths($offset)->endOfMonth();
            $saved['months'][] = ['date' => $date->toDateString(), 'cohorts' => [AllAgeRatingModel::COHORT => [1 => [
                'score' => 62.3, 'group' => str_repeat('b', 64), 'component' => 'shared',
                'connection_status' => 'recent', 'historical_connection' => false,
                'connection_age_days' => 0, 'connection_reference_date' => $date->toDateString(),
            ]]]];
        }
        try {
            $store->replace($saved, $saved['source_fingerprint']);
            $this->assertNotNull($store->read());
            foreach ([['confidence_label' => 'Medium', 'last_direct_match' => null, 'recent_played' => 0],
                ['confidence_label' => 'High', 'last_direct_match' => $today->toDateString(), 'direct_opponents' => 2, 'played_events' => 1]] as $metadata) {
                $valid = $saved;
                $valid['months'][11]['cohorts'][AllAgeRatingModel::COHORT][1] += $metadata;
                $store->replace($valid, $valid['source_fingerprint']);
                $this->assertNotNull($store->read());
            }
            foreach ([['confidence_label' => '99%'], ['recent_played' => -1], ['played_events' => '3'],
                ['last_direct_match' => '2026-02-30'], ['last_direct_match' => $today->addDay()->toDateString()]] as $metadata) {
                $invalid = $saved;
                $invalid['months'][11]['cohorts'][AllAgeRatingModel::COHORT][1] += $metadata;
                try {
                    $store->replace($invalid, $invalid['source_fingerprint']);
                    $this->fail('Invalid saved summary was accepted.');
                } catch (\RuntimeException $exception) {
                    $this->assertStringContainsString('History sources or policy changed', $exception->getMessage());
                }
            }
        } finally {
            @unlink($store->path());
        }
    }
}
