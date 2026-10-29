<?php

namespace Tests\Feature;

use App\Services\Performance\{AllAgeRatingModel, PlayerRatingHistoryStore};
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AllAgeHistoricalHistoryIntegrityTest extends TestCase
{
    public function test_saved_shared_history_rejects_lost_or_contradictory_historical_warnings(): void
    {
        $path = storage_path('framework/testing/history-integrity-'.uniqid().'.json');
        $store = new class($path) extends PlayerRatingHistoryStore {
            public function __construct(private string $file) {}
            public function path(): string { return $this->file; }
        };
        $today = CarbonImmutable::today('Africa/Johannesburg');
        $months = [];
        for ($offset = 11; $offset >= 0; $offset--) {
            $date = $offset === 0 ? $today : $today->startOfMonth()->subMonths($offset)->endOfMonth();
            $months[] = ['date' => $date->toDateString(), 'cohorts' => [AllAgeRatingModel::COHORT => [1 => [
                'score' => 61.5, 'component' => 'ref', 'group' => str_repeat('a', 64),
                'connection_status' => 'historical', 'historical_connection' => true,
                'connection_age_days' => 500, 'connection_reference_date' => $date->subDays(500)->toDateString(),
            ]]]];
        }
        $saved = ['version' => PlayerRatingHistoryStore::VERSION, 'policy_hash' => $store->policyHash(),
            'source_fingerprint' => str_repeat('b', 64), 'as_of' => $today->toDateString(), 'built_at' => $today->toIso8601String(),
            'months' => $months, 'evidence' => []];
        try {
            $store->replace($saved, $saved['source_fingerprint']);
            $this->assertSame($saved, $store->read());
            $mutations = [
                fn (&$row) => $row['historical_connection'] = false,
                fn (&$row) => $row['connection_status'] = 'recent',
                fn (&$row) => $row['connection_age_days'] = 731,
                fn (&$row) => $row['connection_age_days'] = '500',
                fn (&$row) => $row['connection_reference_date'] = $today->toDateString(),
                fn (&$row) => $row['score'] = null,
                function (&$row) { unset($row['connection_status']); },
            ];
            foreach ($mutations as $mutate) {
                $invalid = $saved;
                $mutate($invalid['months'][11]['cohorts'][AllAgeRatingModel::COHORT][1]);
                file_put_contents($path, json_encode($invalid, JSON_THROW_ON_ERROR));
                $this->assertNull($store->read());
            }
        } finally {
            if (is_file($path)) unlink($path);
        }
    }
}
