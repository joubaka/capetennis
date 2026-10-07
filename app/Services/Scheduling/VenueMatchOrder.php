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
        if (is_array($left) && ($left['fixture_kind'] ?? null) === 'team') $left = $this->teamRow($left);
        if (is_array($right) && ($right['fixture_kind'] ?? null) === 'team') $right = $this->teamRow($right);
        // Keep team rows on the same contract as draws, schedules and backend pages.
        // Mixed queues use a fixed kind boundary at simultaneous times to remain transitive.
        if ($left instanceof TeamFixture || $right instanceof TeamFixture) {
            $leftTime = $left instanceof TeamFixture ? $left->scheduled_at : (is_array($left) ? ($left['scheduled_at'] ?? null) : $left->orderOfPlay?->time);
            $rightTime = $right instanceof TeamFixture ? $right->scheduled_at : (is_array($right) ? ($right['scheduled_at'] ?? null) : $right->orderOfPlay?->time);
            $timeOrder = strcmp($this->timeKey($leftTime), $this->timeKey($rightTime));
            if ($timeOrder !== 0) return $timeOrder;
            $rankOrder = $this->sortRank($left) <=> $this->sortRank($right);
            if ($rankOrder !== 0) return $rankOrder;
            if ($left instanceof TeamFixture && $right instanceof TeamFixture) {
                return app(TeamFixtureOrder::class)->compare($left, $right);
            }
            return ($left instanceof TeamFixture ? 1 : 0) <=> ($right instanceof TeamFixture ? 1 : 0);
        }
        $leftValues = $this->values($left);
        $rightValues = $this->values($right);

        foreach (['time', 'rank', 'play_order', 'round', 'match', 'kind', 'id'] as $field) {
            if (in_array($field, ['rank', 'play_order', 'round', 'match', 'kind', 'id'], true)) {
                $comparison = $leftValues[$field] <=> $rightValues[$field];
            } else {
                $comparison = strcmp($leftValues[$field], $rightValues[$field]);
            }

            if ($comparison !== 0) {
                return $comparison;
            }

            // Physical tie-breakers follow time and player rank.
            if ($field === 'rank') {
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

    /** @return array{time:string,rank:int,venue:string,court:string,draw:string,play_order:int,round:int,match:int,kind:int,id:int} */
    private function values(Fixture|TeamFixture|array $match): array
    {
        if (is_array($match)) {
            return [
                'time' => $this->timeKey($match['scheduled_at'] ?? null),
                'rank' => $this->rankKey($match),
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
            'rank' => $isTeam ? app(TeamFixtureOrder::class)->rank($match) : 2147483647,
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

    private function sortRank(Fixture|TeamFixture|array $row): int
    {
        return $row instanceof TeamFixture ? app(TeamFixtureOrder::class)->rank($row)
            : (is_array($row) ? $this->rankKey($row) : 2147483647);
    }

    private function teamRow(array $row): TeamFixture
    {
        $fixture = new TeamFixture();
        $fixture->forceFill([
            'id' => $row['fixture_id'] ?? $row['id'] ?? 0,
            'draw_id' => $row['draw_id'] ?? 0,
            'scheduled_at' => $row['scheduled_at'] ?? null,
            'court_label' => $row['court'] ?? null,
            'home_rank_nr' => $this->rankKey($row),
            'round_nr' => $row['round_nr'] ?? null,
            'tie_nr' => $row['tie_nr'] ?? null,
            'rubber_sequence' => $row['rubber_sequence'] ?? null,
            'match_nr' => $row['match_nr'] ?? null,
        ]);
        return $fixture;
    }

    private function rankKey(array $match): int
    {
        foreach (['home_rank_nr', 'rank_nr', 'away_rank_nr', 'rank', 'rubber_sequence'] as $field) {
            if (is_numeric($match[$field] ?? null) && (int) $match[$field] > 0) return (int) $match[$field];
        }
        return 2147483647;
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
