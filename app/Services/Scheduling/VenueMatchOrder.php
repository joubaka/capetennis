<?php

namespace App\Services\Scheduling;

use App\Models\Fixture;
use App\Models\TeamFixture;
use Carbon\Carbon;

/**
 * One ordering contract for courtside paper and venue score entry.
 */
class VenueMatchOrder
{
    public function compare(Fixture|TeamFixture|array $left, Fixture|TeamFixture|array $right): int
    {
        // Keep team rows on the same contract as draws, schedules and backend pages.
        // Mixed queues use a fixed kind boundary at simultaneous times to remain transitive.
        if ($left instanceof TeamFixture || $right instanceof TeamFixture) {
            $leftTime = $left instanceof TeamFixture ? $left->scheduled_at : (is_array($left) ? ($left['scheduled_at'] ?? null) : $left->orderOfPlay?->time);
            $rightTime = $right instanceof TeamFixture ? $right->scheduled_at : (is_array($right) ? ($right['scheduled_at'] ?? null) : $right->orderOfPlay?->time);
            $timeOrder = strcmp($this->timeKey($leftTime), $this->timeKey($rightTime));
            if ($timeOrder !== 0) return $timeOrder;
            if ($left instanceof TeamFixture && $right instanceof TeamFixture) {
                return app(TeamFixtureOrder::class)->compare($left, $right);
            }
            return ($left instanceof TeamFixture ? 1 : 0) <=> ($right instanceof TeamFixture ? 1 : 0);
        }
        $leftValues = $this->values($left);
        $rightValues = $this->values($right);

        foreach (['time', 'play_order', 'round', 'match', 'kind', 'id'] as $field) {
            if (in_array($field, ['play_order', 'round', 'match', 'kind', 'id'], true)) {
                $comparison = $leftValues[$field] <=> $rightValues[$field];
            } else {
                $comparison = strcmp($leftValues[$field], $rightValues[$field]);
            }

            if ($comparison !== 0) {
                return $comparison;
            }

            // Court and draw are physical/display tie-breakers immediately after time.
            if ($field === 'time') {
                foreach (['venue', 'court', 'draw'] as $textField) {
                    $comparison = strnatcasecmp($leftValues[$textField], $rightValues[$textField]);
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }
            }
        }

        return 0;
    }

    /** @return array{time:string,venue:string,court:string,draw:string,play_order:int,round:int,match:int,kind:int,id:int} */
    private function values(Fixture|TeamFixture|array $match): array
    {
        if (is_array($match)) {
            return [
                'time' => $this->timeKey($match['scheduled_at'] ?? null),
                'venue' => (string) ($match['venue'] ?? ''),
                'court' => $this->textKey($match['court'] ?? null),
                'draw' => (string) ($match['draw_name'] ?? ''),
                'play_order' => $this->numberKey($match['play_order'] ?? null),
                'round' => $this->numberKey($match['round'] ?? null),
                'match' => $this->numberKey($match['match_nr'] ?? null),
                'kind' => 0,
                'id' => (int) ($match['id'] ?? 0),
            ];
        }

        $isTeam = $match instanceof TeamFixture;
        $schedule = $isTeam ? null : $match->orderOfPlay;

        return [
            'time' => $this->timeKey($isTeam ? $match->scheduled_at : $schedule?->time),
            'venue' => (string) ($isTeam ? $match->venue?->name : $schedule?->venue?->name),
            'court' => $this->textKey($isTeam ? $match->court_label : $schedule?->court),
            'draw' => (string) ($match->draw?->drawName ?? ''),
            'play_order' => $this->numberKey($isTeam ? null : $match->play_order),
            'round' => $this->numberKey($isTeam ? $match->round_nr : $match->round),
            'match' => $this->numberKey($match->match_nr),
            'kind' => $isTeam ? 1 : 0,
            'id' => (int) $match->id,
        ];
    }

    private function timeKey(mixed $time): string
    {
        return $time ? Carbon::parse($time)->format('Y-m-d H:i:s.u') : '9999-12-31 23:59:59.999999';
    }

    private function textKey(mixed $value): string
    {
        return $value === null || $value === '' ? "\u{10FFFF}" : (string) $value;
    }

    private function numberKey(mixed $value): int
    {
        return $value === null || $value === '' ? PHP_INT_MAX : (int) $value;
    }
}
