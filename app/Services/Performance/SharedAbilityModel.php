<?php

namespace App\Services\Performance;

/** Regularized Bradley-Terry fit. Edges are wins, not artificial matches. */
class SharedAbilityModel
{
    public const ORDINAL_WEIGHT = 0.2;
    public const ORDINAL_TARGET_SPAN = 1.0;

    public function fit(array $edges, string $cohort = '', array $ordinalFields = [], array $anchors = []): array
    {
        $edges = array_values(array_filter($edges, fn ($edge) => $edge['winner'] !== $edge['loser']
            && is_finite((float) $edge['weight']) && $edge['weight'] > 0));
        $adjacent = [];
        foreach ($edges as $edge) {
            $adjacent[$edge['winner']][$edge['loser']] = true;
            $adjacent[$edge['loser']][$edge['winner']] = true;
        }
        ksort($adjacent, SORT_NUMERIC);
        $components = []; $seen = [];
        foreach (array_keys($adjacent) as $root) {
            if (isset($seen[$root])) { continue; }
            $queue = [$root]; $members = [];
            while ($queue) {
                $id = array_pop($queue);
                if (isset($seen[$id])) { continue; }
                $seen[$id] = true; $members[] = $id;
                foreach (array_keys($adjacent[$id]) as $other) { if (!isset($seen[$other])) { $queue[] = $other; } }
            }
            sort($members, SORT_NUMERIC);
            $components[] = $members;
        }
        $ratings = []; $componentInfo = [];
        foreach ($components as $members) {
            $ownAnchors = array_intersect_key($anchors, array_fill_keys($members, true));
            $ownAnchors = array_filter($ownAnchors, fn ($anchor) => is_finite($anchor['target']) && is_finite($anchor['weight']) && $anchor['weight'] > 0);
            $membership = array_fill_keys($members, true);
            $componentEdges = array_values(array_filter($edges, fn ($edge) => isset($membership[$edge['winner']])));
            usort($componentEdges, fn ($a, $b) => $a['winner'] <=> $b['winner'] ?: $a['loser'] <=> $b['loser'] ?: strcmp($a['source_id'], $b['source_id']));
            $strength = array_fill_keys($members, 0.0);
            $links = array_fill_keys($members, []);
            foreach ($componentEdges as $edge) {
                if ($edge['topology_only'] ?? false) { continue; }
                $outcome = $edge['kind'] === 'played' ? ($edge['outcome_target'] ?? 1.0) : 1.0;
                if (!is_numeric($outcome) || !is_finite((float) $outcome) || $outcome < 0.75 || $outcome > 1) { $outcome = 1.0; }
                $links[$edge['winner']][] = [$edge['loser'], $outcome, $edge['weight']];
                $links[$edge['loser']][] = [$edge['winner'], 1 - $outcome, $edge['weight']];
            }
            $fieldLinks = array_fill_keys($members, []); $fieldSums = []; $fields = [];
            usort($ordinalFields, fn ($a, $b) => strcmp($a['source_id'], $b['source_id']));
            foreach ($ordinalFields as $field) {
                $ids = $field['players']; $count = count($ids);
                if ($count < 2 || $count > 256 || count(array_unique($ids)) !== $count || !is_finite($field['weight']) || $field['weight'] <= 0
                    || count(array_intersect($ids, $members)) !== $count) { continue; }
                $key = count($fields); $fields[$key] = $field; $fieldSums[$key] = 0.0;
                foreach ($ids as $rank => $id) {
                    $fieldLinks[$id][] = ['key' => $key, 'count' => $count,
                        'target' => self::ORDINAL_TARGET_SPAN * ($count - 1 - 2 * $rank) / ($count - 1), 'weight' => $field['weight']];
                }
            }
            $converged = false;
            for ($iteration = 0; $iteration < 100; $iteration++) {
                $movement = 0.0;
                foreach ($members as $id) {
                    $anchor = $ownAnchors[$id] ?? null;
                    $curvature = $anchor ? $anchor['weight'] : 0.5;
                    $gradient = $anchor ? -$anchor['weight'] * ($strength[$id] - $anchor['target']) : -0.5 * $strength[$id];
                    foreach ($links[$id] as [$other, $outcome, $weight]) {
                        $p = $this->sigmoid($strength[$id] - $strength[$other]);
                        $gradient += $weight * ($outcome - $p);
                        $curvature += $weight * $p * (1 - $p);
                    }
                    // Explicit ordinal field likelihood; these are not synthetic played matches.
                    foreach ($fieldLinks[$id] as $field) {
                        $gradient += $field['weight'] * ($field['target'] - $strength[$id] + $fieldSums[$field['key']] / $field['count']);
                        $curvature += $field['weight'] * (1 - 1 / $field['count']);
                    }
                    $step = max(-1, min(1, $gradient / $curvature));
                    $strength[$id] += $step;
                    foreach ($fieldLinks[$id] as $field) { $fieldSums[$field['key']] += $step; }
                    $movement = max($movement, abs($step));
                }
                if ($movement < 0.000001) { $converged = true; break; }
            }
            $mean = $ownAnchors ? 0.0 : array_sum($strength) / count($strength);
            $componentId = substr(hash('sha256', $cohort.'|'.implode(',', $members)), 0, 12);
            $played = count(array_filter($componentEdges, fn ($edge) => $edge['kind'] === 'played'));
            $inferred = count($componentEdges) - $played;
            $divisionLinks = count(array_filter($componentEdges, fn ($edge) => $edge['kind'] === 'division_order'));
            $bridges = $this->bridges($adjacent, $members);
            $componentInfo[$componentId] = ['members' => $members, 'played' => $played, 'inferred' => $inferred,
                'bridge_count' => count($bridges), 'bridges' => $bridges, 'division_links' => $divisionLinks, 'converged' => $converged, 'trial_anchored' => (bool) $ownAnchors];
            foreach ($members as $id) {
                $beta = $strength[$id] - $mean;
                $ratings[$id] = ['score' => $converged && is_finite($beta) ? round(100 * $this->sigmoid($beta), 1) : null,
                    'strength' => $beta, 'component' => $componentId];
            }
        }
        return ['ratings' => $ratings, 'components' => $componentInfo];
    }

    private function sigmoid(float $value): float
    {
        return 1 / (1 + exp(-max(-40, min(40, $value))));
    }

    /** Graph bridges describe limited connections, not a probability of rating accuracy. */
    private function bridges(array $adjacent, array $members): array
    {
        $time = 0; $discovered = []; $low = []; $bridges = [];
        $visit = function ($id, $parent = null) use (&$visit, &$time, &$discovered, &$low, &$bridges, $adjacent): void {
            $discovered[$id] = $low[$id] = ++$time;
            foreach (array_keys($adjacent[$id]) as $other) {
                if ($other === $parent) { continue; }
                if (!isset($discovered[$other])) {
                    $visit($other, $id); $low[$id] = min($low[$id], $low[$other]);
                    if ($low[$other] > $discovered[$id]) { $bridges[] = [$id, $other]; }
                } else { $low[$id] = min($low[$id], $discovered[$other]); }
            }
        };
        foreach ($members as $id) { if (!isset($discovered[$id])) { $visit($id); } }
        return $bridges;
    }
}
