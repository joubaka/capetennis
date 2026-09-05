<?php

namespace App\Services\Draw;

/** Resolve absent entrants separately from opponents who have not qualified yet. */
final class FlexibleMonradProgression
{
    public function resolve(array $graph, array $fixtures, array $withdrawn): array
    {
        $matches = [];
        // MySQL JSON objects reorder keys, so persistence cannot preserve compiler order.
        $visiting = [];
        $resolve = function (string $key) use (&$resolve, &$matches, &$visiting, $graph, $fixtures, $withdrawn): void {
            if (isset($matches[$key])) return;
            if (isset($visiting[$key])) throw new \LogicException('Cyclic Monrad source graph.');
            $visiting[$key] = true;
            $node = $graph['nodes'][$key];
            foreach ($node['sources'] as $source) {
                if (isset($source['match'])) $resolve($source['match']);
            }
            $fixture = $fixtures[$key];
            // Completed games are historical facts, even if a player later withdraws.
            if ($fixture['played']) {
                $matches[$key] = $fixture + ['resolved' => true, 'automatic' => null,
                    'vacant' => [false, false], 'withdrawn_players' => [null, null]];
                unset($visiting[$key]);
                return;
            }
            $sources = array_map(fn ($source) => $this->source($source, $matches, $withdrawn), $node['sources']);
            $players = array_column($sources, 'player');
            $ready = $sources[0]['resolved'] && $sources[1]['resolved'];
            $automatic = $ready && in_array(null, $players, true);
            $matches[$key] = [
                'players' => $players,
                'winner' => $automatic ? ($players[0] ?? $players[1]) : null,
                'resolved' => $automatic,
                'automatic' => $automatic ? (array_filter($players) ? 'walkover' : 'void') : null,
                'vacant' => array_map(fn ($s) => $s['resolved'] && $s['player'] === null, $sources),
                'withdrawn_players' => array_column($sources, 'withdrawn_player'),
            ];
            unset($visiting[$key]);
        };
        foreach (array_keys($graph['nodes']) as $key) $resolve($key);
        $positions = [];
        foreach ($graph['positions'] as $position => $source) {
            $value = $this->source($source, $matches, $withdrawn);
            $positions[] = ['position' => $position, 'player' => $value['player'],
                'vacant' => $value['resolved'] && $value['player'] === null];
        }
        return ['matches' => $matches, 'positions' => $positions];
    }

    private function source(array $source, array $matches, array $withdrawn): array
    {
        if ($source['type'] === 'player') {
            $player = (int) $source['id'];
            $withdrawnPlayer = in_array($player, $withdrawn, true) ? $player : null;
        } else {
            $match = $matches[$source['match']];
            if (! $match['resolved']) return ['resolved' => false, 'player' => null, 'withdrawn_player' => null];
            if ($source['type'] === 'winner') {
                $player = $match['winner'];
                $winnerSlot = $player === null ? false : array_search($player, $match['players'], true);
                $withdrawnPlayer = $winnerSlot === false ? null : ($match['withdrawn_players'][$winnerSlot] ?? null);
            } else {
                $loserSlot = $match['winner'] === null ? null : ($match['winner'] === $match['players'][0] ? 1 : 0);
                $player = $loserSlot === null ? null : $match['players'][$loserSlot];
                $withdrawnPlayer = $loserSlot === null ? null : ($match['withdrawn_players'][$loserSlot] ?? null);
            }
        }
        if ($player !== null && in_array($player, $withdrawn, true)) {
            $withdrawnPlayer = $player;
            $player = null;
        }
        return ['resolved' => true, 'player' => $player, 'withdrawn_player' => $withdrawnPlayer];
    }
}
