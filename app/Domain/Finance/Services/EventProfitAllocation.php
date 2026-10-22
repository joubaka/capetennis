<?php

namespace App\Domain\Finance\Services;

use Illuminate\Support\Collection;

class EventProfitAllocation
{
    /** Largest remainder allocation; ties use director ID so odd cents remain stable. */
    public static function cents(Collection $directors, float $profit): array
    {
        $profitCents = max(0, (int) round($profit * 100));
        $weights = $directors->mapWithKeys(fn ($director) => [$director->id => EventExpenseAmounts::cents($director->profit_share_pct ?? 0)]);
        $allocations = $weights->map(fn ($weight) => intdiv($profitCents * $weight, 10000))->all();
        $target = intdiv($profitCents * $weights->sum() + 5000, 10000);
        $ids = $weights->keys()->all();
        usort($ids, fn ($a, $b) => (($profitCents * $weights[$b]) % 10000 <=> ($profitCents * $weights[$a]) % 10000) ?: ($a <=> $b));
        $remainder = $target - array_sum($allocations);
        foreach ($ids as $id) {
            if ($remainder-- <= 0) break;
            $allocations[$id]++;
        }

        return $allocations;
    }
}
