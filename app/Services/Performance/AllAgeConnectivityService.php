<?php

namespace App\Services\Performance;

/** Read-only match-evidence plan. Suggested links are never treated as results. */
class AllAgeConnectivityService
{
    public function summarize(array $graph): array
    {
        $groups = [];
        foreach ($graph['components'] ?? [] as $key => $component) {
            $labels = [];
            foreach ($component['members'] as $id) {
                foreach ($graph['players'][$id]['age_groups'] ?? [] as $label) { $labels[$label] = true; }
            }
            $labels = array_keys($labels);
            sort($labels);
            $groups[] = ['id' => (string) $key, 'players' => count($component['members']), 'categories' => $labels,
                'reference' => $key === ($graph['reference_component'] ?? null)];
        }
        usort($groups, fn ($a, $b) => ($b['reference'] <=> $a['reference']) ?: ($b['players'] <=> $a['players']) ?: strcmp($a['id'], $b['id']));
        $links = [];
        // Every new group links to an earlier group, forming a deterministic tree.
        // Only the largest 100 groups are candidate hubs, bounding pair evaluation.
        foreach ($groups as $index => $group) {
            if ($index === 0) { continue; }
            if (count($links) === 25) { break; }
            $best = null;
            foreach (array_slice($groups, 0, min($index, 100)) as $target) {
                $pair = $this->closestCategories($group['categories'], $target['categories']);
                $choice = ['from' => $group['id'], 'to' => $target['id'], 'players_in_group' => $group['players'],
                    'from_category' => $pair['from'], 'to_category' => $pair['to'], 'cross_gender' => $pair['cross_gender'],
                    'age_gap' => $pair['age_gap'], 'cost' => $pair['cost']];
                if ($best === null || [$choice['cost'], $choice['to']] < [$best['cost'], $best['to']]) { $best = $choice; }
            }
            if ($best !== null && count($links) < 25) { unset($best['cost']); $links[] = $best; }
        }
        return ['component_count' => count($groups), 'minimum_links' => max(0, count($groups) - 1),
            'groups' => array_slice($groups, 0, 25), 'links' => $links,
            'truncated' => count($groups) > 25,
            'excluded' => $graph['excluded'] ?? []];
    }

    private function closestCategories(array $from, array $to): array
    {
        $best = ['from' => null, 'to' => null, 'age_gap' => null, 'cross_gender' => null, 'cost' => PHP_INT_MAX];
        foreach ($from as $a) {
            if (!preg_match('/^u(\d{1,2}) (boys|girls)$/', $a, $first)) { continue; }
            foreach ($to as $b) {
                if (!preg_match('/^u(\d{1,2}) (boys|girls)$/', $b, $second)) { continue; }
                $gap = abs((int) $first[1] - (int) $second[1]);
                $cross = $first[2] !== $second[2];
                // Prefer nearby age categories within gender; cross-gender links
                // remain necessary when no actual results connect those networks.
                $cost = ($cross ? 100 : 0) + $gap;
                if ([$cost, $a, $b] < [$best['cost'], $best['from'] ?? '', $best['to'] ?? '']) {
                    $best = ['from' => $a, 'to' => $b, 'age_gap' => $gap, 'cross_gender' => $cross, 'cost' => $cost];
                }
            }
        }
        return $best;
    }
}
