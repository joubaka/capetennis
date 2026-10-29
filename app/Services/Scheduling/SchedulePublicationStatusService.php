<?php

namespace App\Services\Scheduling;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Timing-only status; published snapshots do not imply public draw visibility. */
final class SchedulePublicationStatusService
{
    public function days(Event $event, ?int $drawId = null): Collection
    {
      [$savedTimes, $publicTimes] = $this->assignments($event, $drawId);
      return $this->compareDays($savedTimes, $publicTimes);
    }

    /** Compare every draw in three event-scoped queries, rather than one query set per row. */
    public function draws(Event $event): Collection
    {
      [$saved, $public] = $this->assignments($event);
      return $saved->pluck('draw_id')->merge($public->pluck('draw_id'))->unique()->mapWithKeys(function ($id) use ($saved, $public) {
        $days = $this->compareDays($saved->where('draw_id', $id), $public->where('draw_id', $id));
        $pending = $days->sum('pending');
        $published = $days->sum('published');
        return [$id => ['saved' => $days->sum('saved'), 'published' => $published, 'pending' => $pending,
          'status' => $days->contains('status', 'Updates not published') ? 'Updates not published'
            : ($pending && $published ? 'Partly published' : ($published ? 'Published' : 'Unpublished'))]];
      });
    }

    private function assignments(Event $event, ?int $drawId = null): array
    {
      $teamTimes = DB::table('team_fixtures')->join('draws', 'draws.id', '=', 'team_fixtures.draw_id')
        ->where('draws.event_id', $event->id)->whereNotNull('team_fixtures.scheduled_at')
        ->when($drawId !== null, fn ($query) => $query->where('draws.id', $drawId))
        ->selectRaw("draws.id as draw_id, 'team' as fixture_kind, team_fixtures.id as fixture_id, team_fixtures.scheduled_at, team_fixtures.venue_id, team_fixtures.court_label as court, team_fixtures.duration_min as duration")->get();
      $individualTimes = DB::table('order_of_plays')->join('fixtures', 'fixtures.id', '=', 'order_of_plays.fixture_id')
        ->join('draws', 'draws.id', '=', 'fixtures.draw_id')->where('draws.event_id', $event->id)->whereNotNull('order_of_plays.time')
        ->when($drawId !== null, fn ($query) => $query->where('draws.id', $drawId))
        ->selectRaw("draws.id as draw_id, 'individual' as fixture_kind, fixtures.id as fixture_id, order_of_plays.time as scheduled_at, order_of_plays.venue_id, order_of_plays.court, order_of_plays.duration_minutes as duration")->get();
      $publishedTimes = DB::table('published_schedule_assignments')->join('draws', 'draws.id', '=', 'published_schedule_assignments.draw_id')
        ->where('published_schedule_assignments.event_id', $event->id)->where('draws.event_id', $event->id)
        ->when($drawId !== null, fn ($query) => $query->where('draws.id', $drawId))
        ->get(['published_schedule_assignments.draw_id', 'fixture_kind', 'fixture_id', 'scheduled_at', 'published_schedule_assignments.venue_id', 'court', 'duration']);
      $normalize = fn ($row) => ['draw_id' => (int) $row->draw_id, 'key' => $row->fixture_kind.':'.$row->fixture_id, 'scheduled_at' => Carbon::parse($row->scheduled_at)->format('Y-m-d H:i:s'),
        'venue_id' => (string) (int) $row->venue_id, 'court' => (string) $row->court, 'duration' => (string) (int) ($row->duration ?: ($row->fixture_kind === 'team' ? 120 : 75))];
      $savedTimes = $teamTimes->concat($individualTimes)->map($normalize)->keyBy('key');
      $publicTimes = $publishedTimes->map($normalize)->keyBy('key');
      return [$savedTimes, $publicTimes];
    }

    private function compareDays(Collection $savedTimes, Collection $publicTimes): Collection
    {
      $matchedKeys = $savedTimes->filter(fn ($row, $key) => $publicTimes->get($key) === $row)->keys();
      $matchedSet = array_fill_keys($matchedKeys->all(), true);
      $savedByDay = $savedTimes->groupBy(fn ($row) => substr($row['scheduled_at'], 0, 10));
      $publicByDay = $publicTimes->groupBy(fn ($row) => substr($row['scheduled_at'], 0, 10));
      return $savedByDay->keys()->merge($publicByDay->keys())->unique()->sort()->mapWithKeys(function ($day) use ($savedByDay, $publicByDay, $matchedSet, $publicTimes) {
        $saved = $savedByDay->get($day, collect()); $public = $publicByDay->get($day, collect());
        $matched = $saved->filter(fn ($row) => isset($matchedSet[$row['key']]))->count();
        $pendingSaved = $saved->reject(fn ($row) => isset($matchedSet[$row['key']]));
        $pendingPublic = $public->reject(fn ($row) => isset($matchedSet[$row['key']]));
        $pending = $pendingSaved->concat($pendingPublic)->pluck('key')->unique()->count();
        $changedPublicTimes = $pendingPublic->isNotEmpty() || $pendingSaved->contains(fn ($row) => $publicTimes->has($row['key']));
        $status = $changedPublicTimes ? 'Updates not published' : ($public->isEmpty() ? ($saved->isEmpty() ? 'Not scheduled' : 'Unpublished')
          : ($matched === $saved->count() ? 'Published' : 'Partly published'));
        return [$day => ['saved' => $saved->count(), 'published' => $public->count(), 'matched' => $matched, 'pending' => $pending, 'status' => $status]];
      });
    }

    public function snapshot(Event $event, ?int $drawId = null): array
    {
        $publication = app(SchedulePublicationService::class);
        // Also protects READ COMMITTED connections without adding write locks.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $snapshot = DB::transaction(function () use ($event, $publication, $drawId) {
                $before = $publication->revision($event);
                $days = $this->days($event, $drawId)->map(fn ($counts, $day) => $counts + ['date' => $day, 'label' => Carbon::parse($day)->format('l j M Y')])->values()->all();
                $after = $publication->revision($event);
                return hash_equals($before, $after) ? ['event_id' => (int) $event->id, 'days' => $days, 'revision' => $after]
                    + ($drawId !== null ? ['draw_id' => $drawId] : []) : null;
            });
            if ($snapshot !== null) return $snapshot;
        }
        throw new \InvalidArgumentException('The schedule changed while checking publication status. Check the current status before another action.');
    }
}
