<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;

/** Played-result topology only: recent reference selection and minimax historical-link age. */
class AllAgeConnectionPolicy
{
    public function analyze(array $edges, CarbonImmutable $asOf): array
    {
        $adjacent = []; $recent = []; $recentDegrees = [];
        foreach ($edges as $edge) {
            $days = (int) CarbonImmutable::parse($edge['confidence_date'])->startOfDay()->diffInDays($asOf->startOfDay(), false);
            $a = (int) $edge['winner']; $b = (int) $edge['loser'];
            if ($a < 1 || $b < 1 || $a === $b || $days < 0 || $days > AllAgeRatingModel::WINDOW_DAYS) { continue; }
            $adjacent[$a][$b] = min($adjacent[$a][$b] ?? PHP_INT_MAX, $days);
            $adjacent[$b][$a] = $adjacent[$a][$b];
            if ($days <= AllAgeRatingModel::RECENT_WINDOW_DAYS) {
                $recent[$a][$b] = true; $recent[$b][$a] = true;
                $recentDegrees[$a] = ($recentDegrees[$a] ?? 0) + 1; $recentDegrees[$b] = ($recentDegrees[$b] ?? 0) + 1;
            }
        }
        ksort($recent, SORT_NUMERIC);
        $seen = []; $components = [];
        foreach (array_keys($recent) as $root) {
            if (isset($seen[$root])) { continue; }
            $queue = [$root]; $members = [];
            while ($queue) {
                $id = array_pop($queue);
                if (isset($seen[$id])) { continue; }
                $seen[$id] = true; $members[] = $id;
                foreach (array_keys($recent[$id]) as $other) { if (!isset($seen[$other])) { $queue[] = $other; } }
            }
            sort($members, SORT_NUMERIC);
            $membership = array_fill_keys($members, true);
            $played = (int) (array_sum(array_intersect_key($recentDegrees, $membership)) / 2);
            $components[] = ['members' => $members, 'played' => $played,
                'component' => substr(hash('sha256', AllAgeRatingModel::COHORT.'|'.implode(',', $members)), 0, 12),
                'as_of' => $asOf->toDateString(), 'window_days' => AllAgeRatingModel::RECENT_WINDOW_DAYS];
        }
        usort($components, fn ($a, $b) => count($b['members']) <=> count($a['members']) ?: $b['played'] <=> $a['played'] ?: $a['members'][0] <=> $b['members'][0]);
        $backbone = $components[0] ?? null;
        $ages = [];
        $queue = new \SplPriorityQueue;
        $queue->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
        foreach ($backbone['members'] ?? [] as $id) { $ages[$id] = 0; $queue->insert($id, 0); }
        while (!$queue->isEmpty()) {
            $item = $queue->extract(); $id = $item['data']; $age = -$item['priority'];
            if ($age !== $ages[$id]) { continue; }
            foreach ($adjacent[$id] ?? [] as $other => $edgeAge) {
                $candidate = max($age, $edgeAge);
                if ($candidate < ($ages[$other] ?? PHP_INT_MAX)) { $ages[$other] = $candidate; $queue->insert($other, -$candidate); }
            }
        }
        ksort($ages, SORT_NUMERIC);
        return ['recent_backbone' => $backbone, 'connection_ages' => $ages];
    }
}
