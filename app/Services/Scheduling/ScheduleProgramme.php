<?php

namespace App\Services\Scheduling;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/** A complete round-to-day programme, evaluated against one shared calendar. */
final class ScheduleProgramme
{
    public function age(\App\Models\Draw $draw): ?int
    {
        $ids = $draw->team_draw_selection['category_ids'] ?? [];
        $names = $ids ? \App\Models\CategoryEvent::with('category')->where('event_id', $draw->event_id)->whereIn('id', $ids)->get()->map(fn ($row) => $row->category?->name) : collect([$draw->drawName]);
        $ages = $names->map(fn ($name) => preg_match('/^(?:u\s*\/?\s*|under\s+)(\d+)\b/i', trim((string) $name), $matches) ? (int) $matches[1] : null)->unique();
        return $ages->count() === 1 ? $ages->first() : null;
    }

    public function normalize(array $programme, Collection $draws, array $availableRounds): array
    {
        if (! $programme) return [];
        $days = [];
        foreach (array_values($programme['days'] ?? []) as $index => $day) {
            $start = Carbon::parse($day['start']);
            $end = Carbon::parse($day['end']);
            if (! $start->isSameDay($end) || ! $end->gt($start)) throw new \InvalidArgumentException('Each programme day must finish after it starts on the same date.');
            if ($index && $start->copy()->startOfDay()->lte(Carbon::parse($days[$index - 1]['start'])->startOfDay())) throw new \InvalidArgumentException('Choose three distinct programme dates in chronological order.');
            $normalized = ['start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s')];
            if (isset($day['gender_waves'])) {
                if (! in_array($day['gender_waves'], ['combined', 'boys_then_girls', 'girls_then_boys'], true)) throw new \InvalidArgumentException('Choose a valid boys/girls order for each programme day.');
                $normalized['gender_waves'] = $day['gender_waves'];
            }
            if (! empty($day['break_start']) || ! empty($day['break_end'])) {
                if (empty($day['break_start']) || empty($day['break_end'])) throw new \InvalidArgumentException('Choose both the start and end of a daily break.');
                $breakStart = Carbon::parse($day['break_start']);
                $breakEnd = Carbon::parse($day['break_end']);
                if ($breakStart->lt($start) || ! $breakEnd->gt($breakStart) || $breakEnd->gt($end)) throw new \InvalidArgumentException('Daily breaks must be within their programme window.');
                $normalized += ['break_start' => $breakStart->format('Y-m-d H:i:s'), 'break_end' => $breakEnd->format('Y-m-d H:i:s')];
            }
            $days[] = $normalized;
        }
        if (count($days) !== 3) throw new \InvalidArgumentException('Choose three programme days.');
        $rounds = [];
        foreach ($programme['rounds'] ?? [] as $row) {
            $drawId = (int) $row['draw_id'];
            $round = (int) $row['round'];
            $day = (int) $row['day'];
            $sequence = (int) $row['sequence'];
            $key = $drawId.'|'.$round;
            if (! $draws->contains('id', $drawId) || ! in_array($round, $availableRounds[$drawId] ?? [], true)) throw new \InvalidArgumentException('Programme rounds must belong to the selected event draws.');
            if (isset($rounds[$key]) || $day < 1 || $day > 3 || $sequence < 1 || $sequence > 100) throw new \InvalidArgumentException('Choose one valid day and order for each programme round.');
            $rounds[$key] = compact('drawId', 'round', 'day', 'sequence');
        }
        foreach ($draws as $draw) {
            if (empty($availableRounds[$draw->id])) throw new \InvalidArgumentException('Every discipline needs generated fixtures before creating a complete programme.');
            $previousPhase = 0;
            foreach ($availableRounds[$draw->id] as $round) {
                if (! isset($rounds[$draw->id.'|'.$round])) throw new \InvalidArgumentException('The programme must include every round of every selected draw.');
                $row = $rounds[$draw->id.'|'.$round];
                $phase = ($row['day'] - 1) * 100 + $row['sequence'];
                if ($phase <= $previousPhase) throw new \InvalidArgumentException($draw->drawName.' round '.$round.' must follow its preceding round. Move earlier rounds before later rounds.');
                $previousPhase = $phase;
            }
        }
        if (! $rounds) throw new \InvalidArgumentException('The selected age group has no rounds to schedule.');
        ksort($rounds);
        return ['days' => $days, 'rounds' => array_values($rounds)];
    }

    public function configureNodes(array &$nodes, array $programme): void
    {
        if (! $programme) return;
        $rounds = collect($programme['rounds'])->keyBy(fn ($row) => $row['drawId'].'|'.$row['round']);
        foreach ($nodes as &$node) {
            $row = $rounds[$node['draw_id'].'|'.$node['round']];
            $node['programme_phase'] = ($row['day'] - 1) * 100 + $row['sequence'];
            $node['draw_start'] = Carbon::parse($programme['days'][$row['day'] - 1]['start']);
            $node['programme_end'] = Carbon::parse($programme['days'][$row['day'] - 1]['end']);
            $node['programme_day'] = $row['day'];
            $node['programme_gender_waves'] = $programme['days'][$row['day'] - 1]['gender_waves'] ?? null;
            $node['programme_sequence'] = $row['sequence'];
            $node['programme_break'] = isset($programme['days'][$row['day'] - 1]['break_start']) ? [Carbon::parse($programme['days'][$row['day'] - 1]['break_start']), Carbon::parse($programme['days'][$row['day'] - 1]['break_end'])] : null;
        }
        unset($node);
        $phases = collect($nodes)->groupBy('programme_phase', preserveKeys: true)->sortKeys();
        $previous = [];
        $prerequisites = [];
        foreach ($phases as $phase => $phaseNodes) {
            $prerequisites[$phase] = $previous;
            $previous = $phaseNodes->keys()->all();
        }
        foreach ($nodes as &$node) {
            // Adjacent stages suffice: their own prerequisites carry the earlier stages.
            $node['dependencies'] = array_merge($node['dependencies'], $prerequisites[$node['programme_phase']]);
            $node['dependencies'] = array_values(array_unique($node['dependencies']));
        }
        unset($node);
    }
}
