<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Current verified source schedules, not actual completion times or archived chronology. */
class PlayerMatchChronologySources
{
    public function resolve(array $matches): array
    {
        if (count($matches) > PlayerMatchProgressCalculator::MAX_MATCHES) throw new \OverflowException('Too many chronology sources.');
        $events = [];
        foreach (array_chunk(array_values(array_unique(array_filter(array_column($matches, 'event_id'), fn ($id) => is_numeric($id) && $id > 0))), 1000) as $chunk) {
            foreach (DB::table('events')->whereIntegerInRaw('id', $chunk)->get(['id', 'start_date', 'end_date']) as $event) $events[(int) $event->id] = $event;
        }
        $native = []; $team = [];
        foreach ($matches as $match) {
            if (preg_match('/^match:([1-9][0-9]*)$/', $match['source_id'], $id)) $native[] = (int) $id[1];
            if (preg_match('/^team-match:([1-9][0-9]*)$/', $match['source_id'], $id)) $team[] = (int) $id[1];
        }
        $schedules = []; $scanned = 0;
        foreach (array_chunk(array_unique($native), 1000) as $chunk) {
            $rows = DB::table('fixtures as f')->join('draws as d', 'd.id', '=', 'f.draw_id')
                ->leftJoin('order_of_plays as o', 'o.fixture_id', '=', 'f.id')->whereIntegerInRaw('f.id', $chunk)
                ->limit(PlayerMatchProgressCalculator::MAX_MATCHES + 1)->get(['f.id', 'f.draw_id', 'd.event_id', 'o.draw_id as schedule_draw_id', 'o.time']);
            foreach ($rows as $row) { if (++$scanned > PlayerMatchProgressCalculator::MAX_MATCHES) throw new \OverflowException('Too many schedule rows.'); $schedules['match:'.$row->id][] = $row; }
        }
        foreach (array_chunk(array_unique($team), 1000) as $chunk) {
            foreach (DB::table('team_fixtures as f')->join('draws as d', 'd.id', '=', 'f.draw_id')->whereIntegerInRaw('f.id', $chunk)
                ->get(['f.id', 'f.draw_id', 'd.event_id', 'f.draw_id as schedule_draw_id', 'f.scheduled_at as time']) as $row) $schedules['team-match:'.$row->id][] = $row;
        }
        $result = [];
        foreach ($matches as $match) {
            $event = $events[(int) $match['event_id']] ?? null;
            // Actual-date external sources have a reliable day but no supported intraday order here.
            $actualDay = str_contains($match['confidence_date_basis'] ?? '', 'actual');
            $earliest = $event ? substr((string) $event->start_date, 0, 10) : $match['confidence_date'];
            $latest = $event ? substr((string) $event->end_date, 0, 10) : ($actualDay ? $match['confidence_date'] : '9999-12-31');
            $result[$match['source_id']] = ['time' => null, 'earliest_date' => $earliest, 'latest_date' => $latest];
            $rows = $schedules[$match['source_id']] ?? [];
            if (count($rows) !== 1 || !$event) continue;
            $row = $rows[0];
            if ((int) $row->event_id !== (int) $match['event_id'] || (int) $row->schedule_draw_id !== (int) $row->draw_id) continue;
            $time = $this->time($row->time);
            if (!$time || $time->format('H:i:s') === '00:00:00' || $time->toDateString() !== $match['confidence_date']
                || $time->toDateString() < $earliest || $time->toDateString() > $latest) continue;
            $result[$match['source_id']]['time'] = $time->format('Y-m-d H:i:s');
        }
        return $result;
    }

    private function time(mixed $raw): ?CarbonImmutable
    {
        if (!is_string($raw) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw)) return null;
        try {
            $time = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $raw, 'Africa/Johannesburg');
            return $time->format('Y-m-d H:i:s') === $raw ? $time : null;
        } catch (\Throwable) { return null; }
    }
}
