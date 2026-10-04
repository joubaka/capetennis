<?php

namespace App\Services;

/** Editable starting definitions; never updates stored formats or draw snapshots. */
final class TeamEventFormatPresets
{
    public function all(): array
    {
        return ['six_player' => $this->forSize(6), 'eight_player' => $this->forSize(8)];
    }

    private function forSize(int $size): array
    {
        $rubbers = [];
        foreach (['singles', 'reverse_singles'] as $type) {
            foreach (range(1, $size) as $rank) {
                $away = $type === 'singles' ? $rank : ($rank % 2 ? $rank + 1 : $rank - 1);
                $rubbers[] = ['sequence' => count($rubbers) + 1,
                    'name' => ($type === 'singles' ? 'Singles ' : 'Reverse singles ').$rank,
                    'rubber_code' => $type, 'player_count_per_team' => 1,
                    'home_positions' => [$rank], 'away_positions' => [$away],
                    'gender_rule' => null, 'is_required' => true];
            }
        }
        foreach (range(1, $size, 2) as $rank) {
            $rubbers[] = ['sequence' => count($rubbers) + 1, 'name' => 'Doubles '.(($rank + 1) / 2),
                'rubber_code' => 'doubles', 'player_count_per_team' => 2,
                'home_positions' => [$rank, $rank + 1], 'away_positions' => [$rank, $rank + 1],
                'gender_rule' => null, 'is_required' => true];
        }
        return ['name' => $size.'-player starting format', 'min_roster_size' => $size,
            'max_roster_size' => $size, 'allow_player_reuse' => true, 'rubbers' => $rubbers];
    }
}
