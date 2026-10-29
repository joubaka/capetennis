<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;

/** Conditional temporal likelihood. Target references stay fixed; old ratings never enter. */
class CohortTransitionAbilityModel
{
    public const VERSION = 'conditional-temporal-v1';
    private const MAX_SENSITIVITY_REMOVALS = 200;
    protected const ITERATIONS = 500;

    public function estimate(int $player, string $target, array $rows, array $native, CarbonImmutable $asOf, bool $assumeContext = false): array
    {
        $hasUnverifiedHistory = count(array_filter($rows, fn ($r) => CohortTransitionEvidenceService::eligible($r, $target, $asOf, true)
            && ($r['winner'] === $player || $r['loser'] === $player) && !($r['context_verified'] ?? false))) > 0;
        $rows = $this->deduplicate(array_values(array_filter($rows, fn ($r) => CohortTransitionEvidenceService::eligible($r, $target, $asOf, $assumeContext))));
        $network = $this->network($player, $rows);
        if (!$network) { return $this->withheld(!$assumeContext && $hasUnverifiedHistory ? 'context_unverified' : 'no_eligible_history'); }
        $ids = array_fill_keys($network, true);
        $rows = array_values(array_filter($rows, fn ($r) => isset($ids[$r['winner']], $ids[$r['loser']])));
        if (count($rows) > 2000 || count($network) > 500) { return $this->withheld('processing_limit'); }
        $references = [];
        $components = [];
        foreach ($network as $id) {
            if ($id === $player || !isset($native['ratings'][$id]) || ($native['players'][$id]['played'] ?? 0) < 1) { continue; }
            $rating = $native['ratings'][$id];
            if ($rating['score'] === null || !is_finite($rating['strength'])) { continue; }
            $references[$id] = (float) $rating['strength'];
            $components[$rating['component']] = true;
        }
        $own = array_values(array_filter($rows, fn ($r) => $r['winner'] === $player || $r['loser'] === $player));
        $ownEvents = array_unique(array_column($own, 'event_id'));
        $last = max(array_column($own, 'date'));
        $support = ['historical_matches' => count($own), 'historical_events' => count($ownEvents), 'reference_peers' => count($references),
            'target_components' => count($components), 'gap_days' => (int) CarbonImmutable::parse($last)->diffInDays($asOf),
            'current_played_added' => 0, 'context_assumed' => $assumeContext];
        if (count($components) !== 1) { return $this->withheld(count($components) ? 'multiple_target_components' : 'no_target_reference', $support); }
        if (count($references) < 2) { return $this->withheld('insufficient_reference_peers', $support); }
        $supportingPlayers = array_values(array_filter($network, fn ($id) => $id !== $player));
        sort($supportingPlayers, SORT_NUMERIC);
        $networkEvents = array_unique(array_column($rows, 'event_id'));
        if (count($supportingPlayers) + count($networkEvents) > self::MAX_SENSITIVITY_REMOVALS) {
            return $this->withheld('sensitivity_processing_limit', $support);
        }
        $fits = [];
        foreach ([0.5, 1.0, 2.0] as $scale) {
            $fit = $this->fit($player, $rows, $references, $asOf, $scale);
            if ($fit === null) { return $this->withheld('nonconverged', $support); }
            $fits[(string) $scale] = $fit;
        }
        $peerRemoved = 0;
        $playerRemoved = 0;
        $nonreferenceRemoved = 0;
        $eventRemoved = 0;
        $sensitivity = [];
        // Unrated intermediaries can be the only path to every calibration peer.
        // Remove every supporting identity, not only identities with an anchor.
        foreach ($supportingPlayers as $id) {
            $remainingRows = array_values(array_filter($rows, fn ($r) => $r['winner'] !== $id && $r['loser'] !== $id));
            [$remainingRows, $remaining] = $this->connected($player, $remainingRows, $references);
            $fit = count($remaining) >= 2 ? $this->fit($player, $remainingRows, $remaining, $asOf, 1.0) : null;
            if ($fit === null) {
                $playerRemoved++;
                if (isset($references[$id])) { $peerRemoved++; } else { $nonreferenceRemoved++; }
            } else { $sensitivity[] = $fit; }
        }
        foreach ($networkEvents as $event) {
            $remainingRows = array_values(array_filter($rows, fn ($r) => $r['event_id'] !== $event));
            [$remainingRows, $remainingRefs] = $this->connected($player, $remainingRows, $references);
            if (count($remainingRefs) < 2) { $eventRemoved++; continue; }
            $fit = $this->fit($player, $remainingRows, $remainingRefs, $asOf, 1.0);
            if ($fit === null) { $eventRemoved++; } else { $sensitivity[] = $fit; }
        }
        $range = max(array_merge(array_values($fits), $sensitivity)) - min(array_merge(array_values($fits), $sensitivity));
        $robust = count($ownEvents) >= 2 && !$playerRemoved && !$eventRemoved && $range <= 0.5;
        return ['preview_only' => true, 'available' => true, 'robust' => $robust, 'reason' => $robust ? null : 'fragile_historical_support',
            'strength' => $fits['1'], 'component' => array_key_first($components), 'support' => $support,
            'sensitivity' => ['continuity_strengths' => $fits, 'strength_range' => $range,
                'supporting_players_tested' => count($supportingPlayers), 'reference_peers_tested' => count($references),
                'leave_player_out_abstentions' => $playerRemoved, 'leave_peer_out_abstentions' => $peerRemoved,
                'leave_nonreference_player_out_abstentions' => $nonreferenceRemoved, 'leave_event_out_abstentions' => $eventRemoved]];
    }

    private function fit(int $player, array $rows, array $references, CarbonImmutable $asOf, float $scale): ?float
    {
        $strengths = []; $playerStates = []; $links = []; $anchors = []; $degrees = [];
        foreach ($rows as $r) { foreach ([$r['winner'], $r['loser']] as $id) { $degrees[$r['event_id']][$id] = ($degrees[$r['event_id']][$id] ?? 0) + 1; } }
        foreach ($rows as $r) {
            $states = [];
            foreach ([$r['winner'], $r['loser']] as $id) {
                $key = $id.'|'.substr($r['date'], 0, 7);
                $states[] = $key; $strengths[$key] = 0.0;
                $playerStates[$id][$key] = max($playerStates[$id][$key] ?? '', $r['date']);
            }
            $decay = pow(0.5, CarbonImmutable::parse($r['date'])->diffInDays($asOf) / 180);
            $weight = $decay / max($degrees[$r['event_id']][$r['winner']], $degrees[$r['event_id']][$r['loser']]);
            $outcome = (float) $r['outcome_target'];
            if (!is_finite($outcome) || $outcome < 0.75 || $outcome > 1) { return null; }
            $links[$states[0]][] = [$states[1], $outcome, $weight];
            $links[$states[1]][] = [$states[0], 1 - $outcome, $weight];
        }
        $continuity = [];
        foreach ($playerStates as $id => $states) {
            asort($states); $keys = array_keys($states);
            for ($i = 1; $i < count($keys); $i++) {
                $gap = CarbonImmutable::parse($states[$keys[$i - 1]])->diffInDays(CarbonImmutable::parse($states[$keys[$i]]));
                $weight = $scale / (1 + $gap / 90) ** 2;
                $continuity[$keys[$i - 1]][] = [$keys[$i], $weight]; $continuity[$keys[$i]][] = [$keys[$i - 1], $weight];
            }
            $last = $keys[array_key_last($keys)];
            if (isset($references[$id])) {
                $gap = CarbonImmutable::parse($states[$last])->diffInDays($asOf);
                $anchors[$last] = [$references[$id], $scale / (1 + $gap / 90) ** 2];
            }
        }
        if (!isset($playerStates[$player])) { return null; }
        // The unobserved target-time state is not the last historical state.
        // Its weak neutral prior competes with a gap-dependent continuity link.
        $states = $playerStates[$player]; asort($states);
        $lastHistorical = array_key_last($states);
        $gap = CarbonImmutable::parse($states[$lastHistorical])->diffInDays($asOf);
        $targetState = $player.'|target';
        $strengths[$targetState] = 0.0;
        $weight = $scale / (1 + $gap / 90) ** 2;
        $continuity[$targetState][] = [$lastHistorical, $weight];
        $continuity[$lastHistorical][] = [$targetState, $weight];
        ksort($strengths);
        $converged = false;
        for ($iteration = 0; $iteration < static::ITERATIONS; $iteration++) {
            $movement = 0.0;
            foreach ($strengths as $key => $value) {
                $gradient = -0.02 * $value; $curvature = 0.02;
                foreach ($links[$key] ?? [] as [$other, $outcome, $weight]) {
                    $p = 1 / (1 + exp(-max(-40, min(40, $value - $strengths[$other]))));
                    $gradient += $weight * ($outcome - $p); $curvature += $weight * $p * (1 - $p);
                }
                foreach ($continuity[$key] ?? [] as [$other, $weight]) { $gradient += $weight * ($strengths[$other] - $value); $curvature += $weight; }
                if (isset($anchors[$key])) { [$target, $weight] = $anchors[$key]; $gradient += $weight * ($target - $value); $curvature += $weight; }
                $step = max(-0.5, min(0.5, $gradient / $curvature)); $strengths[$key] += $step; $movement = max($movement, abs($step));
            }
            if ($movement < 0.000001) { $converged = true; break; }
        }
        $result = $strengths[$targetState];
        return $converged && is_finite($result) ? $result : null;
    }

    private function deduplicate(array $rows): array
    {
        $unique = [];
        foreach ($rows as $r) {
            if (isset($unique[$r['source_id']]) && $unique[$r['source_id']] !== $r) { throw new \InvalidArgumentException('Conflicting historical source identity.'); }
            $unique[$r['source_id']] ??= $r;
        }
        ksort($unique);
        return array_values($unique);
    }

    private function connected(int $player, array $rows, array $references): array
    {
        $reachable = array_fill_keys($this->network($player, $rows), true);
        return [array_values(array_filter($rows, fn ($r) => isset($reachable[$r['winner']], $reachable[$r['loser']]))),
            array_intersect_key($references, $reachable)];
    }

    private function network(int $root, array $rows): array
    {
        $adj = [];
        foreach ($rows as $r) { $adj[$r['winner']][$r['loser']] = true; $adj[$r['loser']][$r['winner']] = true; }
        if (!isset($adj[$root])) { return []; }
        $seen = []; $queue = [$root];
        while ($queue) { $id = array_pop($queue); if (isset($seen[$id])) { continue; } $seen[$id] = true; foreach (array_keys($adj[$id]) as $other) { if (!isset($seen[$other])) { $queue[] = $other; } } }
        return array_keys($seen);
    }

    private function withheld(string $reason, array $support = []): array
    {
        return ['preview_only' => true, 'available' => false, 'robust' => false, 'reason' => $reason, 'support' => $support];
    }
}
