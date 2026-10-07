<?php

namespace App\Services\Scheduling;

use App\Models\TeamFixture;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Display ordering only: never changes fixture numbers, participants or scheduling priority. */
class TeamFixtureOrder
{
    public function sort(Collection $fixtures): Collection
    {
        return $fixtures->sort(fn ($left, $right) => $this->compare($left, $right))->values();
    }

    public function compare(TeamFixture $left, TeamFixture $right): int
    {
        return $this->key($left) <=> $this->key($right);
    }

    public function applyQuery(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        $rankParts = [];
        foreach (['home_rank_nr', 'rank_nr', 'away_rank_nr', 'rubber_sequence'] as $column) {
            $rankParts[] = "CASE WHEN {$table}.{$column} > 0 THEN {$table}.{$column} END";
        }
        $query->reorder()->orderByRaw("CASE WHEN {$table}.scheduled_at IS NULL THEN 1 ELSE 0 END")
            ->orderBy("{$table}.scheduled_at")
            ->orderByRaw('COALESCE('.implode(', ', $rankParts).', 2147483647)')
            ->orderByRaw("CASE WHEN {$table}.court_label IS NULL OR {$table}.court_label = '' THEN 1 ELSE 0 END")
            ->orderByRaw("LOWER({$table}.court_label)")
            ->orderBy("{$table}.draw_id");
        foreach (['round_nr', 'tie_nr'] as $column) {
            $query->orderByRaw("COALESCE(CAST(NULLIF({$table}.{$column}, '') AS SIGNED), 2147483647)");
        }
        return $query->orderByRaw("COALESCE(NULLIF({$table}.rubber_sequence, 0), CAST(NULLIF({$table}.match_nr, '') AS SIGNED), CAST(NULLIF({$table}.home_rank_nr, '') AS SIGNED), 2147483647)")
            ->orderByRaw("COALESCE(CAST(NULLIF({$table}.match_nr, '') AS SIGNED), 2147483647)")
            ->orderBy("{$table}.id");
    }

    private function key(TeamFixture $fixture): array
    {
        return [
            $fixture->scheduled_at ? Carbon::parse($fixture->scheduled_at)->format('Y-m-d H:i:s.u') : '9999-12-31 23:59:59.999999',
            $this->rank($fixture),
            $fixture->court_label === null || $fixture->court_label === '' ? 1 : 0,
            'court:'.mb_strtolower((string) $fixture->court_label),
            (int) $fixture->draw_id,
            $this->number($fixture->round_nr),
            $this->number($fixture->tie_nr),
            $fixture->rubber_sequence ?: $this->number($fixture->match_nr !== null && $fixture->match_nr !== '' ? $fixture->match_nr : $fixture->home_rank_nr),
            $this->number($fixture->match_nr),
            (int) $fixture->id,
        ];
    }

    public function rank(TeamFixture $fixture): int
    {
        foreach (['home_rank_nr', 'rank_nr', 'away_rank_nr', 'rubber_sequence'] as $column) {
            if (is_numeric($fixture->{$column}) && (int) $fixture->{$column} > 0) return (int) $fixture->{$column};
        }
        return 2147483647;
    }

    private function number(mixed $value): int
    {
        return $value === null || $value === '' ? 2147483647 : (int) $value;
    }
}
