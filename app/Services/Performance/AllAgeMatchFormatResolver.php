<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\{DB, Schema};

/** Resolve the actual event court where available; never infer modified-ball equivalence. */
class AllAgeMatchFormatResolver
{
    private array $events = [];

    public function resolve(int $eventId, array $match): array
    {
        if (!isset($this->events[$eventId])) {
            $courts = Schema::hasTable('event_venue_courts') ? DB::table('event_venue_courts')->where('event_id', $eventId)->limit(50001)->get()->all() : [];
            $allocations = Schema::hasTable('draw_venue_court_allocations') ? DB::table('draw_venue_court_allocations as a')->join('draws as d', 'd.id', '=', 'a.draw_id')->where('d.event_id', $eventId)->limit(50001)->get(['a.*'])->all() : [];
            $schedules = DB::table('order_of_plays as o')->join('fixtures as f', 'f.id', '=', 'o.fixture_id')->join('draws as d', 'd.id', '=', 'f.draw_id')
                ->where('d.event_id', $eventId)->limit(50001)->get(['o.fixture_id', 'o.draw_id', 'o.venue_id', 'o.court', 'o.time'])->groupBy('fixture_id')->all();
            if (count($courts) > 50000 || count($allocations) > 50000 || array_sum(array_map('count', $schedules)) > 50000) { throw new \OverflowException('All-age court context exceeds the validation limit.'); }
            $this->events[$eventId] = compact('courts', 'allocations', 'schedules');
        }
        ['courts' => $courts, 'allocations' => $allocations, 'schedules' => $schedules] = $this->events[$eventId];
        if (($match['source'] ?? null) === 'Individual match') {
            $ownSchedules = $schedules[$match['fixture_id']] ?? collect();
            $contexts = [];
            foreach ($ownSchedules as $schedule) {
                if ((int) $schedule->draw_id !== (int) ($match['draw_id'] ?? 0)) { return ['ball_type' => null, 'format_reason' => 'Individual match schedule belongs to another draw']; }
                $contexts[json_encode([$schedule->draw_id, $schedule->venue_id, $schedule->court, $schedule->time], JSON_THROW_ON_ERROR)] = true;
            }
            if (count($contexts) > 1) { return ['ball_type' => null, 'format_reason' => 'Individual match has conflicting schedule evidence']; }
        }
        $byKey = [];
        foreach ($courts as $court) { $byKey[$court->venue_id.'|'.$court->label] = $court->ball_type; }
        $key = ($match['venue_id'] ?? '').'|'.($match['court_label'] ?? '');
        if (($match['venue_id'] ?? null) && trim((string) ($match['court_label'] ?? '')) !== '' && array_key_exists($key, $byKey) && $byKey[$key] !== null) {
            return $this->decision(['ball_type' => $byKey[$key], 'format_reason' => null], $match);
        }
        $assigned = [];
        foreach ($allocations as $allocation) {
            if ((int) $allocation->draw_id === (int) ($match['draw_id'] ?? 0)) { $assigned[] = $byKey[$allocation->venue_id.'|'.$allocation->court_label] ?? null; }
        }
        if ($assigned) {
            $unique = array_values(array_unique($assigned, SORT_REGULAR));
            if (count($unique) === 1 && $unique[0] !== null) { return $this->decision(['ball_type' => $unique[0], 'format_reason' => null], $match); }
            return $this->decision(['ball_type' => null, 'format_reason' => 'Draw court format is mixed or unresolved'], $match);
        }
        foreach ($courts as $court) {
            if ($court->ball_type !== null && !in_array($court->ball_type, ['standard', 'yellow'], true)) {
                return $this->decision(['ball_type' => null, 'format_reason' => 'Match court is unresolved in an event with modified-ball courts'], $match);
            }
        }
        return $this->decision(['ball_type' => null, 'format_reason' => null], $match);
    }

    private function decision(array $resolved, array $match): array
    {
        if ($match['all_age_source_format_reason'] ?? null) { $resolved['format_reason'] = $match['all_age_source_format_reason']; }
        if (!empty($match['all_age_requires_explicit_format']) && $resolved['ball_type'] === null
            && !preg_match('/\b(yellow[\s-]*ball|standard[\s-]*ball)\b/iu', $match['category'] ?? '')) {
            $resolved['format_reason'] ??= 'Cross-context or unclassified team singles require explicit standard playing conditions';
        }
        return $resolved;
    }
}
