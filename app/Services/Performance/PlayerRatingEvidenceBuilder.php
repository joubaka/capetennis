<?php

namespace App\Services\Performance;

/** Shared saved provenance of the final fit, not per-match point attribution. */
class PlayerRatingEvidenceBuilder
{
    private array $matches = [];
    private array $indexedEvents = [];
    private array $registry = [];

    public function append(array $snapshot, array $sources, int $month): void
    {
        foreach ($sources as $eventId => $source) {
            if (isset($this->indexedEvents[$eventId])) continue;
            foreach ($source['matches'] as $match) {
                $id = ($match['source'] === 'Team match' ? 'team-match:' : 'match:').$match['fixture_id'];
                $this->matches[$id] = $match;
            }
            $this->indexedEvents[$eventId] = true;
        }
        $bit = 1 << $month;
        foreach ($snapshot['cohorts'] as $cohort => $data) {
            foreach ($data['history_evidence']['matches'] ?? [] as $edge) {
                $id = $edge['source_id'];
                $match = $this->matches[$id] ?? throw new \RuntimeException('Fit provenance has no validated source.');
                $this->registry[$id] ??= [
                    'source_id' => $id, 'kind' => 'match', 'cohort' => $cohort,
                    'event_id' => (int) $match['event_id'], 'event' => $match['event'], 'date' => $match['confidence_date'],
                    'date_basis' => $match['confidence_date_basis'], 'completed_at' => $match['date'],
                    'players' => [(int) $match['player1_id'], (int) $match['player2_id']],
                    'names' => [$snapshot['names'][$match['player1_id']] ?? 'Player #'.$match['player1_id'], $snapshot['names'][$match['player2_id']] ?? 'Player #'.$match['player2_id']],
                    'winner_side' => (int) $match['winner_side'], 'score' => $match['score'], 'months' => 0, 'baseline_months' => 0,
                ];
                if ($this->registry[$id]['cohort'] !== $cohort) throw new \RuntimeException('Match appears in conflicting fit cohorts.');
                $this->registry[$id]['months'] |= $bit;
                if ($edge['topology_only'] ?? false) $this->registry[$id]['baseline_months'] |= $bit;
            }
            foreach ($data['history_evidence']['fields'] ?? [] as $field) {
                $players = $field['players']; $count = count($players);
                $component = $data['ratings'][$players[0] ?? 0]['component'] ?? null;
                $members = $data['components'][$component]['members'] ?? [];
                if ($count < 2 || $count > 256 || count(array_unique($players)) !== $count || !is_finite($field['weight']) || $field['weight'] <= 0
                    || count(array_intersect($players, $members)) !== $count) continue;
                $id = $field['source_id'];
                $this->registry[$id] ??= [
                    'source_id' => $id, 'kind' => 'finish', 'cohort' => $cohort, 'event_id' => (int) $field['event_id'],
                    'event' => $field['event_name'], 'date' => $field['event_date'], 'date_basis' => 'event start; finishing-order evidence', 'completed_at' => $field['completed_at'],
                    'players' => array_map('intval', $field['players']),
                    'names' => array_map(fn ($id) => $snapshot['names'][$id] ?? 'Player #'.$id, $field['players']),
                    'months' => 0, 'baseline_months' => 0,
                ];
                $this->registry[$id]['months'] |= $bit;
            }
        }
        if (count($this->registry) > 50000) throw new \RuntimeException('History provenance exceeds its safe limit.');
    }

    public function all(): array { return array_values($this->registry); }
}
