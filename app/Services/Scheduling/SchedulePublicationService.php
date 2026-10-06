<?php

namespace App\Services\Scheduling;

use App\Models\{Draw, DrawAuditLog, Event, Fixture, OrderOfPlay, TeamFixture, Venue};
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SchedulePublicationService
{
    public function workingRows(Event $event): Collection
    {
        $draws = $event->draws()->get()->keyBy('id');
        $individual = Fixture::with(['orderOfPlay.venue', 'registration1.players', 'registration2.players'])
            ->whereIn('draw_id', $draws->keys())->whereHas('orderOfPlay', fn ($q) => $q->whereNotNull('time'))->get();
        $team = TeamFixture::with(['venue', 'teamTie', 'team1', 'team2'])->whereIn('draw_id', $draws->keys())
            ->whereNotNull('scheduled_at')->get();
        return $individual->concat($team)->map(function ($fixture) use ($draws, $event) {
            $team = $fixture instanceof TeamFixture;
            $slot = $team ? $fixture : $fixture->orderOfPlay;
            $time = $team ? $slot->scheduled_at : $slot->time;
            return ['event_id' => (int) $event->id, 'fixture_kind' => $team ? 'team' : 'individual',
                'fixture_id' => (int) $fixture->id, 'fixture_key' => ($team ? 'team:' : 'individual:').$fixture->id,
                'draw_id' => (int) $fixture->draw_id, 'draw_name' => $draws[$fixture->draw_id]->drawName,
                'scheduled_at' => Carbon::parse($time)->format('Y-m-d H:i:s'), 'venue_id' => (int) $slot->venue_id,
                'venue_name' => $slot->venue?->name ?? 'Venue unassigned',
                'court' => (string) ($team ? $slot->court_label : $slot->court),
                'duration' => (int) (($team ? $slot->duration_min : $slot->duration_minutes) ?: ($team ? 120 : 75)),
                'participants' => $team ? [$fixture->teamTie?->home_side_name ?: $fixture->team1->map(fn ($p) => trim($p->name.' '.$p->surname))->join(' / '), $fixture->teamTie?->away_side_name ?: $fixture->team2->map(fn ($p) => trim($p->name.' '.$p->surname))->join(' / ')]
                    : [$fixture->registration1?->players?->map(fn ($p) => trim($p->name.' '.$p->surname))->join(' / '), $fixture->registration2?->players?->map(fn ($p) => trim($p->name.' '.$p->surname))->join(' / ')]];
        })->sortBy('scheduled_at')->values();
    }

    /** Timing only: names and participation always come from current event records. */
    public function publishedRows(Event $event): Collection
    {
        if (! app(\App\Services\PublicTournamentVisibility::class)->eventIsVisible($event)) return collect();
        $cacheKey = 'published_schedule_rows_'.$event->id;
        if (request()->attributes->has($cacheKey)) return request()->attributes->get($cacheKey);
        $draws = $event->draws()->where('published', true)->where('oop_published', true)->get()->keyBy('id');
        $snapshots = DB::table('published_schedule_assignments')->where('event_id', $event->id)
            ->whereIn('draw_id', $draws->keys())->get();
        $individualIds = $snapshots->where('fixture_kind', 'individual')->pluck('fixture_id');
        $teamIds = $snapshots->where('fixture_kind', 'team')->pluck('fixture_id');
        $individual = Fixture::with(['fixtureResults', 'registration1.players', 'registration2.players'])
            ->whereIn('draw_id', $draws->keys())->whereIn('id', $individualIds)->get()->keyBy('id');
        $team = TeamFixture::with(['fixtureResults', 'teamTie', 'team1', 'team2'])->publicDrawFixtures()
            ->whereIn('draw_id', $draws->keys())->whereIn('id', $teamIds)->get()->keyBy('id');
        app(\App\Services\TeamFixtureLineupPresenter::class)->prepare($team, publicDraw: true);
        $venues = Venue::whereIn('id', $snapshots->pluck('venue_id'))->get()->keyBy('id');
        $rows = $snapshots->map(function ($row) use ($individual, $team, $draws, $venues) {
            $fixture = ($row->fixture_kind === 'team' ? $team : $individual)->get($row->fixture_id);
            if (! $fixture || (int) $fixture->draw_id !== (int) $row->draw_id) return null;
            return (array) $row + ['fixture_key' => $row->fixture_kind.':'.$row->fixture_id,
                'draw_name' => $draws[$row->draw_id]->drawName, 'venue_name' => $venues->get($row->venue_id)?->name,
                'participants' => $row->fixture_kind === 'team'
                    ? ($fixture->teamTie
                        ? [$fixture->tie_display['home'], $fixture->tie_display['away']]
                        : [$fixture->team1->map(fn ($p) => trim($p->name.' '.$p->surname))->join(' / '), $fixture->team2->map(fn ($p) => trim($p->name.' '.$p->surname))->join(' / ')])
                    : [$fixture->registration1?->players?->map(fn ($p) => trim($p->name.' '.$p->surname))->join(' / '), $fixture->registration2?->players?->map(fn ($p) => trim($p->name.' '.$p->surname))->join(' / ')],
                '_fixture' => $fixture];
        })->filter()->sortBy('scheduled_at')->values();
        $publicRows = $rows->groupBy('draw_id')->flatMap(function ($rows, $drawId) use ($draws) {
            if (! $draws[$drawId]->settings?->showsFirstMatchOnly()) return $rows;
            $seen = []; $visible = [];
            $rr = in_array($draws[$drawId]->settings?->workflow, ['round_robin', 'round_robin_playoffs'], true)
                || $rows->contains(fn ($row) => $row['fixture_kind'] === 'individual' && (strtoupper((string) $row['_fixture']->stage) === 'RR' || $row['_fixture']->draw_group_id !== null));
            foreach ($rows as $row) {
                $fixture = $row['_fixture'];
                if ($row['fixture_kind'] === 'team') { $visible[] = $row; continue; }
                if ($fixture->fixtureResults->isNotEmpty()) continue;
                $ids = array_values(array_filter([(int) $fixture->registration1_id, (int) $fixture->registration2_id]));
                $unseen = array_filter($ids, fn ($id) => ! isset($seen[$id]));
                if ($ids && ($rr ? (bool) $unseen : count($unseen) === count($ids))) $visible[] = $row;
                foreach ($ids as $id) $seen[$id] = true;
            }
            return $visible;
        })->map(fn ($row) => array_diff_key($row, ['_fixture' => true]))->sortBy('scheduled_at')->values();
        request()->attributes->set($cacheKey, $publicRows);
        return $publicRows;
    }

    public function projectFixtures(Collection $fixtures): Collection
    {
        $events = $fixtures->map(fn ($fixture) => $fixture->draw?->event_id ?? $fixture->draws?->event_id)->filter()->unique();
        $rows = $events->flatMap(fn ($id) => $this->publishedRows(Event::findOrFail($id)))->keyBy('fixture_key');
        foreach ($fixtures as $fixture) {
            $team = $fixture instanceof TeamFixture;
            $row = $rows->get(($team ? 'team:' : 'individual:').$fixture->id);
            $venue = $row ? Venue::find($row['venue_id']) : null;
            $fixture->setAttribute('scheduled', $row ? 1 : 0);
            if ($team) $fixture->setAttribute('clash_flag', false);
            if ($team) {
                $fixture->setAttribute('scheduled_at', $row['scheduled_at'] ?? null);
                $fixture->setAttribute('venue_id', $row['venue_id'] ?? null);
                $fixture->setAttribute('court_label', $row['court'] ?? null);
                $fixture->setAttribute('duration_min', $row['duration'] ?? null);
                $fixture->setRelation('venue', $venue);
            } else {
                $slot = $row ? new OrderOfPlay(['fixture_id' => $fixture->id, 'draw_id' => $fixture->draw_id,
                    'time' => $row['scheduled_at'], 'venue_id' => $row['venue_id'], 'court' => $row['court'], 'duration_minutes' => $row['duration']]) : null;
                $slot?->setRelation('venue', $venue);
                foreach (['orderOfPlay', 'schedule', 'oop'] as $relation) $fixture->setRelation($relation, $slot);
                $fixture->setAttribute('scheduled_at', $row['scheduled_at'] ?? null);
                $fixture->setAttribute('venue_id', $row['venue_id'] ?? null);
                $fixture->setRelation('venue', $venue);
            }
        }
        return $fixtures;
    }

    public function publishedAssignments(Event $event): Collection
    {
        return DB::table('published_schedule_assignments')->where('event_id', $event->id)->get()
            ->map(fn ($row) => (array) $row + ['fixture_key' => $row->fixture_kind.':'.$row->fixture_id]);
    }

    public function revision(Event $event): string
    {
        return hash('sha256', json_encode([$this->workingRows($event)->map(fn ($row) => array_diff_key($row, ['participants' => true, 'venue_name' => true, 'draw_name' => true]))->all(),
            DB::table('published_schedule_assignments')->where('event_id', $event->id)->orderBy('fixture_kind')->orderBy('fixture_id')->get()->all()]));
    }

    public function publish(Event $event, array $scope): int
    {
        return $this->mutate($event, $scope, false);
    }

    public function hide(Event $event, array $scope): int
    {
        return $this->mutate($event, $scope, true);
    }

    private function mutate(Event $event, array $scope, bool $hide): int
    {
        return DB::transaction(function () use ($event, $scope, $hide) {
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            if (isset($scope['revision']) && ! hash_equals($this->revision($event), $scope['revision'])) throw new \InvalidArgumentException('The saved or public schedule changed. Refresh the calendar before publishing.');
            $drawId = isset($scope['draw_id']) ? (int) $scope['draw_id'] : null;
            if ($drawId && ! $event->draws()->whereKey($drawId)->exists()) throw new \InvalidArgumentException('The draw does not belong to this event.');
            $working = $this->workingRows($event)->filter(fn ($row) => (! $drawId || $row['draw_id'] === $drawId)
                && (empty($scope['date']) || substr($row['scheduled_at'], 0, 10) === $scope['date'])
                && (empty($scope['venue_id']) || $row['venue_id'] === (int) $scope['venue_id']))->values();
            $old = DB::table('published_schedule_assignments')->where('event_id', $event->id)
                ->when($drawId, fn ($q) => $q->where('draw_id', $drawId))
                ->when(! empty($scope['date']), fn ($q) => $q->whereDate('scheduled_at', $scope['date']))
                ->when(! empty($scope['venue_id']), fn ($q) => $q->where('venue_id', $scope['venue_id']));
            $before = $this->publishedAssignments($event);
            $oldDrawIds = (clone $old)->pluck('draw_id'); $removed = $old->delete();
            if (! $hide) foreach ($working as $row) DB::table('published_schedule_assignments')->updateOrInsert([
                'event_id' => $event->id, 'fixture_kind' => $row['fixture_kind'], 'fixture_id' => $row['fixture_id'],
            ], array_intersect_key($row, array_flip(['draw_id', 'scheduled_at', 'venue_id', 'court', 'duration']))
                + ['published_at' => now(), 'published_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $affected = $working->pluck('draw_id')->concat($oldDrawIds)->when($drawId, fn ($ids) => $ids->push($drawId))->unique();
            foreach ($affected as $id) {
                request()->attributes->remove('draw_schedule_exists_'.$event->id.'_'.$id);
                DB::table('schedule_publication_baselines')->insertOrIgnore(['draw_id' => $id, 'captured_at' => now()]);
                Draw::whereKey($id)->update(['oop_published' => DB::table('published_schedule_assignments')->where('draw_id', $id)->exists()]);
                DrawAuditLog::record((int) $id, $hide ? 'schedule_scope_hidden' : 'schedule_scope_published', null,
                    ['scope' => array_diff_key($scope, ['revision' => true]), 'count' => $hide ? $removed : $working->count(),
                        'before' => $before->where('draw_id', $id)->map(fn ($row) => array_intersect_key($row, array_flip(['fixture_kind','fixture_id','scheduled_at','venue_id','court','duration'])))->values()->all(),
                        'after' => $this->publishedAssignments($event)->where('draw_id', $id)->map(fn ($row) => array_intersect_key($row, array_flip(['fixture_kind','fixture_id','scheduled_at','venue_id','court','duration'])))->values()->all()]);
            }
            request()->attributes->remove('published_schedule_rows_'.$event->id);
            return $hide ? $removed : $working->count();
        });
    }
}
