<?php

namespace App\Services\Draw;

use App\Models\Draw;
use App\Models\TeamFixture;
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PublicDrawResultsRevision
{
    public function forDraw(Draw $draw, bool $team): string
    {
        $fixtures = $team
            ? TeamFixture::query()->publicDrawFixtures()->toBase()
            : DB::table('fixtures');
        $fixtures->where('draw_id', $draw->id);

        // Compare values so edits in the same second, deletions and bulk writes
        // are visible. Working court and time fields are deliberately omitted.
        $columns = $team
            ? ['id', 'team_tie_id', 'round_nr', 'tie_nr', 'match_nr', 'region1', 'region2', 'match_status', 'fixture_type', 'numSets', 'rubber_sequence', 'rubber_code', 'rubber_name', 'rank_nr', 'home_rank_nr', 'away_rank_nr']
            : ['id', 'round', 'match_nr', 'stage', 'draw_group_id', 'registration1_id', 'registration2_id', 'match_status', 'winner_registration'];
        $rows = $fixtures->orderBy('id')->get($columns);
        $ids = $rows->pluck('id');

        $results = $team
            ? DB::table('team_fixture_results')->whereIn('team_fixture_id', $ids)->orderBy('id')
                ->get(['team_fixture_id', 'set_nr', 'team1_score', 'team2_score'])
            : DB::table('fixture_results')->whereIn('fixture_id', $ids)->orderBy('id')
                ->get(['fixture_id', 'set_nr', 'registration1_score', 'registration2_score']);
        $assignments = $this->assignments($draw, $team, $ids);
        $venues = DB::table('venues')->whereIn('id', $assignments->pluck('venue_id')->filter()->unique())
            ->orderBy('id')->get(['id', 'name']);

        return hash_hmac('sha256', serialize([
            $draw->id, $draw->drawName, $draw->age, $draw->oop_published, $draw->event->name,
            $draw->event->result_auto_refresh_enabled, $draw->event->standings_published,
            $rows, $results, $assignments, $venues,
            $team ? null : $this->individualParticipants($rows),
        ]), (string) config('app.key'));
    }

    private function assignments(Draw $draw, bool $team, Collection $ids): Collection
    {
        if (! $draw->oop_published) {
            return collect();
        }

        if (! $team && $draw->settings?->showsFirstMatchOnly()) {
            return app(SchedulePublicationService::class)->publishedRows($draw->event, includeParticipants: false, drawId: $draw->id)
                ->where('fixture_kind', 'individual')->whereIn('fixture_id', $ids)
                ->map(fn ($row) => (object) array_intersect_key($row, array_flip(['fixture_id', 'scheduled_at', 'venue_id', 'court', 'duration'])))
                ->values();
        }

        return DB::table('published_schedule_assignments')->where('event_id', $draw->event_id)
            ->where('draw_id', $draw->id)->where('fixture_kind', $team ? 'team' : 'individual')
            ->whereIn('fixture_id', $ids)->orderBy('id')->get(['fixture_id', 'scheduled_at', 'venue_id', 'court', 'duration']);
    }

    private function individualParticipants(Collection $fixtures): Collection
    {
        $ids = $fixtures->pluck('registration1_id')->merge($fixtures->pluck('registration2_id'))->filter()->unique();
        return DB::table('player_registrations as members')
            ->join('players', 'players.id', '=', 'members.player_id')
            ->whereIn('members.registration_id', $ids)->orderBy('members.registration_id')->orderBy('members.player_id')
            ->get(['members.registration_id', 'members.player_id', 'players.name', 'players.surname']);
    }
}
