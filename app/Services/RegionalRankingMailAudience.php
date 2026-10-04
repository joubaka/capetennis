<?php

namespace App\Services;

use App\Models\{Event, EventRegion, NoProfileTeamPlayer, RankingList, SeriesRanking, Team, TeamPlayer, TeamSelectionImport, TeamSelectionInvitation, User};
use App\Services\TeamSelection\TeamSelectionContactService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RegionalRankingMailAudience
{
    public function canManage(Event $event, User $actor): bool
    {
        return $event->isTeam() && ($actor->hasRole('super-user') || $actor->is_event_admin($event->id));
    }

    public function lists(Event $event): Collection
    {
        return RankingList::with(['category', 'series'])->whereIn('series_id', EventRegion::where('event_id', $event->id)->with('rankingSource')->get()->pluck('rankingSource.series_id')->filter())->orderBy('id')->get();
    }

    public function resolve(Event $event, User $actor, array $options): array
    {
        abort_unless($this->canManage($event, $actor), 403);
        $regions = EventRegion::with(['region', 'rankingSource'])->where('event_id', $event->id)->get();
        $selected = collect($options['ranking_region_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        if ($selected->diff($regions->pluck('region_id'))->isNotEmpty()) $this->invalid('Choose regions linked to this event.');
        if ($selected->isNotEmpty()) $regions = $regions->whereIn('region_id', $selected);
        $lists = $this->lists($event);
        $listIds = collect($options['ranking_list_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        if ($listIds->diff($lists->whereIn('series_id', $regions->pluck('rankingSource.series_id'))->pluck('id'))->isNotEmpty()) $this->invalid('Choose ranking lists belonging to the selected regions.');
        $ranges = $this->ranges($options['rank_numbers'] ?? '');
        $teamIds = Team::withoutGlobalScopes()->whereHas('category', fn ($q) => $q->where('event_id', $event->id))->pluck('id');
        $roster = TeamPlayer::withoutGlobalScopes()->whereIn('team_id', $teamIds)->pluck('player_id')->merge(NoProfileTeamPlayer::whereIn('team_id', $teamIds)->whereNotNull('player_profile')->pluck('player_profile'))->unique();
        $imports = TeamSelectionImport::where('event_id', $event->id)->whereIn('status', ['draft', 'sent'])->orderByDesc('id')->get()->unique('region_id')->pluck('id');
        $states = TeamSelectionInvitation::where('event_id', $event->id)->whereIn('import_id', $imports)->whereIn('team_id', $teamIds)
            ->whereHas('selectionImport', fn ($q) => $q->whereColumn('team_selection_imports.region_id', 'team_selection_invitations.region_id'))
            ->whereHas('team', fn ($q) => $q->whereColumn('teams.region_id', 'team_selection_invitations.region_id'))
            ->orderBy('id')->get()->groupBy('player_id');
        $rows = collect();
        $sources = [];
        foreach ($regions as $region) {
            $source = $region->rankingSource;
            if (! $source || (int) $source->event_id !== (int) $event->id || (int) $source->region_id !== (int) $region->region_id) $this->invalid('Every selected region needs a linked ranking source.');
            $runs = SeriesRanking::where('series_id', $source->series_id)->where('status', 'published')->whereNotNull('run_id')->where('run_id', '!=', '')->distinct()->pluck('run_id');
            if ($runs->count() !== 1) $this->invalid('Every selected region needs exactly one current published ranking run.');
            $sources[] = [$region->id, $source->id, $source->series_id, $runs->first()];
            $rankings = SeriesRanking::with(['player.user', 'player.users', 'category'])->where('series_id', $source->series_id)->where('status', 'published')->where('run_id', $runs->first())->whereIn('ranking_list_id', $lists->where('series_id', $source->series_id)->pluck('id'))->when($listIds->isNotEmpty(), fn ($q) => $q->whereIn('ranking_list_id', $listIds))->orderBy('ranking_list_id')->orderBy('rank_position')->orderBy('id')->get();
            foreach ($rankings as $ranking) {
                if ($ranges && ! collect($ranges)->contains(fn ($range) => $ranking->rank_position >= $range[0] && $ranking->rank_position <= $range[1])) continue;
                $statuses = $states->get($ranking->player_id, collect())->pluck('status')->unique()->sort()->values()->all();
                $reasons = [];
                if (! empty($options['exclude_team_listed']) && $roster->contains($ranking->player_id)) $reasons[] = 'team_listed';
                foreach (['declined', 'reserves' => 'reserve', 'withdrawn'] as $key => $status) {
                    $flag = is_int($key) ? $status : $key;
                    if (! empty($options['exclude_'.$flag]) && in_array($status, $statuses, true)) $reasons[] = $flag;
                }
                if (in_array((int) $ranking->player_id, array_map('intval', $options['excluded_player_ids'] ?? []), true)) $reasons[] = 'manual';
                $emails = $ranking->player ? app(TeamSelectionContactService::class)->emails($ranking->player)->sort()->values()->all() : [];
                $rows->push(['id' => $ranking->id, 'player_id' => (int) $ranking->player_id, 'key' => 'player:'.$ranking->player_id, 'name' => $ranking->player?->full_name ?? 'Missing player', 'emails' => $emails, 'region' => $region->region?->region_name, 'region_id' => $region->region_id, 'category' => $ranking->category?->name, 'ranking_list_id' => $ranking->ranking_list_id, 'rank' => (int) $ranking->rank_position, 'statuses' => $statuses, 'team_listed' => $roster->contains($ranking->player_id), 'excluded' => $reasons]);
            }
        }
        $counts = ['matched' => $rows->count(), 'included' => $rows->where('excluded', [])->count(), 'missing_contacts' => $rows->filter(fn ($row) => ! $row['excluded'] && ! $row['emails'])->count()];
        foreach (['team_listed', 'declined', 'reserves', 'withdrawn', 'manual'] as $reason) $counts[$reason] = $rows->filter(fn ($row) => in_array($reason, $row['excluded'], true))->count();

        return ['rows' => $rows, 'sources' => $sources, 'counts' => $counts];
    }

    private function ranges(string $expression): array
    {
        if (trim($expression) === '') return [];
        $ranges = [];
        foreach (explode(',', $expression) as $part) {
            if (! preg_match('/^\s*([1-9]\d{0,5})(?:\s*-\s*([1-9]\d{0,5}))?\s*$/', $part, $matches)) $this->invalid('Use rank numbers such as 9, 11, 14-18.');
            $start = (int) $matches[1];
            $end = (int) ($matches[2] ?? $start);
            if ($end < $start) $this->invalid('Rank ranges must run from lowest to highest.');
            $ranges[] = [$start, $end];
        }
        return $ranges;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['rank_numbers' => $message]);
    }
}
