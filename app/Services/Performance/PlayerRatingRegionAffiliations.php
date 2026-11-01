<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\DB;

/** Recorded team affiliations, never a claim of current residence or representation. */
class PlayerRatingRegionAffiliations
{
    public function forPlayers(array $playerIds): array
    {
        $playerIds = array_values(array_unique(array_filter(array_map('intval', $playerIds), fn ($id) => $id > 0)));
        if (!$playerIds) return [];
        $direct = DB::table('team_players as roster')->join('teams', 'teams.id', '=', 'roster.team_id')
            ->join('team_regions as regions', 'regions.id', '=', 'teams.region_id')
            ->whereIn('roster.player_id', $playerIds)
            ->selectRaw('roster.player_id AS player_id, TRIM(regions.region_name) AS region_name');
        $imported = DB::table('no_profile_team_players as roster')->join('teams', 'teams.id', '=', 'roster.team_id')
            ->join('team_regions as regions', 'regions.id', '=', 'teams.region_id')
            ->whereIn('roster.player_profile', $playerIds)
            ->selectRaw('roster.player_profile AS player_id, TRIM(regions.region_name) AS region_name');
        // Deduplicate region labels across seasons/rosters and both linkage paths.
        $distinct = DB::query()->fromSub($direct->union($imported), 'affiliations')
            ->whereNotNull('region_name')->where('region_name', '<>', '')
            ->groupByRaw('player_id, LOWER(region_name)')
            ->selectRaw('player_id, MIN(region_name) AS region_name');
        $ranked = DB::query()->fromSub($distinct, 'distinct_affiliations')->select('*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY player_id ORDER BY region_name) AS region_position, COUNT(*) OVER (PARTITION BY player_id) AS region_count');
        // Only three labels per visible player leave SQL, even with a long roster history.
        $rows = DB::query()->fromSub($ranked, 'ranked_affiliations')->where('region_position', '<=', 3)
            ->orderBy('player_id')->orderBy('region_position')->get();
        $result = [];
        foreach ($rows as $row) {
            $id = (int) $row->player_id;
            $result[$id] ??= ['names' => [], 'additional' => max(0, (int) $row->region_count - 3)];
            $result[$id]['names'][] = mb_strimwidth($row->region_name, 0, 100, '…');
        }
        return $result;
    }
}
