<?php

namespace App\Services\Performance;

use App\Models\Player;
use Carbon\CarbonImmutable;

class PlayerRatingHistoryService
{
    public function __construct(private readonly PlayerSharedAbilityService $ability, private readonly PlayerRatingHistoryStore $store) {}

    /** Page reads never calculate, enqueue, or update ratings. */
    public function forPlayer(Player $player): array
    {
        $saved = $this->store->read();
        if ($saved === null) return ['series' => [], 'reason' => 'Rating history is awaiting its first saved calculation. Updates run nightly at 03:00 SAST.'];
        if ($saved['source_fingerprint'] !== $this->ability->fingerprint()) {
            return ['series' => [], 'reason' => 'Rating history is awaiting the next nightly update because its source results or publication changed.'];
        }
        $series = [];
        foreach ($saved['months'] as $index => $snapshot) {
            foreach ($snapshot['cohorts'] as $cohort => $data) {
                $rating = $data[$player->id] ?? null;
                if (!$rating || !is_numeric($rating['score']) || !is_finite((float) $rating['score'])) continue;
                $series[$cohort][] = ['index' => $index, 'date' => $snapshot['date'], 'score' => $rating['score'],
                    'group' => $rating['group'], 'component' => $rating['component']];
            }
        }
        return ['series' => $series, 'reason' => null, 'as_of' => $saved['as_of'], 'built_at' => $saved['built_at'],
            'stale' => $saved['as_of'] < CarbonImmutable::today('Africa/Johannesburg')->toDateString()];
    }

    /** Explicit background calculation; completed-event evidence is validated once for all months. */
    public function calculate(CarbonImmutable $today): array
    {
        $fingerprint = $this->ability->fingerprint();
        $months = []; $sources = [];
        for ($offset = 11; $offset >= 0; $offset--) {
            $date = $offset === 0 ? $today : $today->startOfMonth()->subMonths($offset)->endOfMonth()->startOfDay();
            $snapshot = $this->ability->historySnapshot($date, $sources);
            if ($snapshot['reason']) throw new \RuntimeException('Monthly history calculation withheld.');
            $compact = [];
            foreach ($snapshot['cohorts'] as $cohort => $data) {
                $groups = [];
                foreach ($data['components'] as $id => $component) {
                    if (!$component['converged']) throw new \RuntimeException('Monthly history model did not converge.');
                    $members = $component['members']; sort($members);
                    $groups[$id] = hash('sha256', json_encode([$members, $data['baseline']['metadata']['event_id'] ?? null]));
                }
                foreach ($data['ratings'] as $id => $rating) {
                    $compact[$cohort][$id] = ['score' => $rating['score'], 'component' => $rating['component'], 'group' => $groups[$rating['component']]];
                }
            }
            $months[] = ['date' => $date->toDateString(), 'cohorts' => $compact];
        }
        if ($fingerprint !== $this->ability->fingerprint()) throw new \RuntimeException('History sources changed during calculation.');
        return ['months' => $months, 'as_of' => $today->toDateString(), 'built_at' => CarbonImmutable::now('Africa/Johannesburg')->toIso8601String(),
            'source_fingerprint' => $fingerprint, 'policy_hash' => $this->store->policyHash(), 'version' => PlayerRatingHistoryStore::VERSION];
    }
}
