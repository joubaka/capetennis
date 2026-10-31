<?php

namespace App\Services\Scheduling;

use App\Domain\Draws\Services\ScheduleAvailability;
use App\Models\Event;
use Carbon\Carbon;

/** Explain a rejected move without changing any booking or another suggestion. */
final class ScheduleMoveConflictService
{
    public function report(Event $event, array $options, array $preview, array $move, string $message, bool $suggest = true): array
    {
        $draws = $event->draws()->with('settings')->get()->keyBy('id');
        $rows = collect(array_merge($preview['matches'], $preview['existing_matches'], $preview['unscheduled']))->unique('fixture_key');
        $moving = $rows->firstWhere('fixture_key', $move['fixture_key']);
        if (! $moving || ! $draws->has($moving['draw_id'])) return ['message' => $message, 'issues' => [], 'suggestions' => []];
        $venues = collect($preview['venues'])->keyBy('id');
        $describe = fn (array $row) => [
            'fixture_key' => $row['fixture_key'], 'draw_name' => $row['draw_name'], 'match' => $row['match'],
            'scheduled_at' => $row['scheduled_at'], 'venue_name' => $venues[$row['venue_id']]['name'] ?? 'Venue', 'court' => $row['court'],
        ];
        $key = fn ($dependency) => is_numeric($dependency) ? 'individual:'.$dependency : (string) $dependency;
        $at = Carbon::parse($move['scheduled_at']);
        $timing = app(MatchTimingService::class)->forDraw($draws[$moving['draw_id']], (int) ($options['duration'] ?? $moving['duration'] ?? 60), (int) ($options['court_gap'] ?? 5), !empty($options['day_batch']));
        $duration = $timing['duration_minutes'];
        $rest = (int) ($options['player_rest'] ?? 30);
        $gap = $timing['court_gap_minutes'];
        $ready = $at->copy()->addMinutes($duration + $rest);
        $dependencies = array_map($key, $moving['dependencies'] ?? []);
        $release = function (array $target, string $sourceKey, Carbon $sourceStart, int $sourceDuration) use ($key, $rest): Carbon {
            if (in_array($sourceKey, array_map($key, $target['day_finish_dependencies'] ?? []), true)) return $sourceStart->copy()->addMinutes($sourceDuration);
            if (in_array($sourceKey, array_map($key, $target['programme_start_dependencies'] ?? []), true)) return $sourceStart->copy();
            return $sourceStart->copy()->addMinutes($sourceDuration + $rest);
        };
        $issues = [];
        foreach ($rows as $row) {
            if ($row['fixture_key'] === $moving['fixture_key'] || ! $row['scheduled_at']) continue;
            $start = Carbon::parse($row['scheduled_at']);
            $end = $start->copy()->addMinutes((int) $row['duration']);
            $courtEnd = isset($row['ends_at']) ? Carbon::parse($row['ends_at']) : $end->copy()->addMinutes((int) ($row['court_gap'] ?? $gap));
            $own = $draws->has($row['draw_id']);
            $match = $own ? $describe($row) : null;
            $laterRelease = $release($row, $moving['fixture_key'], $at, $duration);
            if ($own && in_array($moving['fixture_key'], array_map($key, $row['dependencies'] ?? []), true) && $laterRelease->gt($start)) {
                $issues[] = ['type' => 'dependent', 'match' => $match, 'required_at' => $laterRelease->toDateTimeString(),
                    'message' => 'This later match depends on the match you are moving. Move it to the required start time or later first, then retry this move.'];
            }
            $qualifierRelease = $release($moving, $row['fixture_key'], $start, (int) $row['duration']);
            if ($own && in_array($row['fixture_key'], $dependencies, true) && $qualifierRelease->gt($at)) {
                $issues[] = ['type' => 'qualifier', 'match' => $match, 'required_at' => $qualifierRelease->toDateTimeString(),
                    'message' => 'This earlier match controls the earliest start through draw progression, programme order or player rest. Choose a slot at or after the required time.'];
            }
            if ((int) $row['venue_id'] === (int) $move['venue_id'] && ScheduleAvailability::courtKey((string) $row['court']) === ScheduleAvailability::courtKey($move['court'])
                && $at->lt($courtEnd) && $at->copy()->addMinutes($duration + $gap)->gt($start)) {
                $issues[] = ['type' => 'court', 'match' => $match, 'required_at' => $courtEnd->toDateTimeString(),
                    'message' => $own ? 'This match already uses the requested court. Choose another court or wait until this booking and its court gap finish.' : 'Another event has a booking on this court. Its match details are private; choose another court or a later slot.'];
            }
            $sameGraph = $own && (int) $row['draw_id'] === (int) $moving['draw_id'] && $draws[$moving['draw_id']]->usesFlexibleMonrad();
            if ($own && ! $sameGraph && array_intersect($row['participant_ids'] ?? [], $moving['participant_ids'] ?? [])
                && $at->lt($end->copy()->addMinutes($rest)) && $ready->gt($start)
                && ! in_array($row['fixture_key'], $dependencies, true) && ! in_array($moving['fixture_key'], array_map($key, $row['dependencies'] ?? []), true)) {
                $issues[] = ['type' => 'player_rest', 'match' => $match, 'required_at' => $end->copy()->addMinutes($rest)->toDateTimeString(),
                    'message' => 'These matches share a player or possible entrant. Their playing times and required rest cannot overlap.'];
            }
        }
        if (! $issues) $issues[] = ['type' => 'availability', 'match' => null, 'required_at' => null, 'message' => $message];
        usort($issues, fn ($left, $right) => ($right['type'] === 'dependent') <=> ($left['type'] === 'dependent'));
        return ['message' => 'This move would conflict with the schedule. No match times were changed.',
            'moving' => $describe($moving),
            'requested' => ['scheduled_at' => $at->toDateTimeString(), 'venue_name' => $venues[$move['venue_id']]['name'] ?? 'Selected venue', 'court' => $move['court']],
            'issues' => array_slice($issues, 0, 30), 'omitted_issues' => max(0, count($issues) - 30),
            'suggestions' => $suggest ? $this->suggestions($event, $options, $preview, $moving, $move) : []];
    }

    private function suggestions(Event $event, array $options, array $preview, array $moving, array $move): array
    {
        $scheduler = app(EventVenueScheduleService::class);
        $start = Carbon::parse($move['scheduled_at']);
        $candidates = [];
        // Nearby alternatives are bounded. Every offered destination is checked by
        // the canonical scheduler with all other matches frozen in their places.
        foreach ([0, 15, 30, 60, 90, 120] as $minutes) {
            foreach (array_slice($moving['venue_courts'][$move['venue_id']] ?? [], 0, 3) as $court) {
                $candidates[] = array_replace($move, ['scheduled_at' => $start->copy()->addMinutes($minutes)->toDateTimeString(), 'court' => (string) $court]);
            }
        }
        if ($moving['scheduled_at']) $candidates[] = ['fixture_key' => $move['fixture_key'], 'scheduled_at' => $moving['scheduled_at'], 'venue_id' => (int) $moving['venue_id'], 'court' => (string) $moving['court']];
        foreach ($candidates as $candidate) {
            if (Carbon::parse($candidate['scheduled_at'])->equalTo($start) && $candidate['court'] === $move['court'] && $candidate['venue_id'] === $move['venue_id']) continue;
            try {
                $validated = $scheduler->preview($event, $scheduler->preservePreviewOptions($options, $preview, $candidate));
                $row = collect($validated['matches'])->firstWhere('fixture_key', $move['fixture_key']);
                if (! $row) continue;
                return [['fixture_key' => $move['fixture_key'], 'scheduled_at' => $row['scheduled_at'], 'venue_id' => (int) $row['venue_id'], 'court' => (string) $row['court'],
                    'venue_name' => collect($validated['venues'])->firstWhere('id', $row['venue_id'])['name'] ?? 'Venue',
                    'current_slot' => $candidate['scheduled_at'] === $moving['scheduled_at'] && $candidate['venue_id'] === $moving['venue_id'] && $candidate['court'] === $moving['court']]];
            } catch (\InvalidArgumentException) {
                // Rejected candidates are never presented as available slots.
            }
        }
        return [];
    }
}
