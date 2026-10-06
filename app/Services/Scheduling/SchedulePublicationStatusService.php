<?php

namespace App\Services\Scheduling;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Timing-only status; published snapshots do not imply public draw visibility. */
final class SchedulePublicationStatusService
{
    public function days(Event $event): Collection
    {
      $teamTimes = DB::table('team_fixtures')->join('draws', 'draws.id', '=', 'team_fixtures.draw_id')
        ->where('draws.event_id', $event->id)->whereNotNull('team_fixtures.scheduled_at')
        ->selectRaw("'team' as fixture_kind, team_fixtures.id as fixture_id, team_fixtures.scheduled_at, team_fixtures.venue_id, team_fixtures.court_label as court, team_fixtures.duration_min as duration")->get();
      $individualTimes = DB::table('order_of_plays')->join('fixtures', 'fixtures.id', '=', 'order_of_plays.fixture_id')
        ->join('draws', 'draws.id', '=', 'fixtures.draw_id')->where('draws.event_id', $event->id)->whereNotNull('order_of_plays.time')
        ->selectRaw("'individual' as fixture_kind, fixtures.id as fixture_id, order_of_plays.time as scheduled_at, order_of_plays.venue_id, order_of_plays.court, order_of_plays.duration_minutes as duration")->get();
      $publishedTimes = DB::table('published_schedule_assignments')->join('draws', 'draws.id', '=', 'published_schedule_assignments.draw_id')
        ->where('published_schedule_assignments.event_id', $event->id)->where('draws.event_id', $event->id)
        ->get(['fixture_kind', 'fixture_id', 'scheduled_at', 'published_schedule_assignments.venue_id', 'court', 'duration']);
      $normalize = fn ($row) => ['key' => $row->fixture_kind.':'.$row->fixture_id, 'scheduled_at' => Carbon::parse($row->scheduled_at)->format('Y-m-d H:i:s'),
        'venue_id' => (string) (int) $row->venue_id, 'court' => (string) $row->court, 'duration' => (string) (int) ($row->duration ?: ($row->fixture_kind === 'team' ? 120 : 75))];
      $savedTimes = $teamTimes->concat($individualTimes)->map($normalize)->keyBy('key');
      $publicTimes = $publishedTimes->map($normalize)->keyBy('key');
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

    public function snapshot(Event $event): array
    {
        $publication = app(SchedulePublicationService::class);
        // Also protects READ COMMITTED connections without adding write locks.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $snapshot = DB::transaction(function () use ($event, $publication) {
                $before = $publication->revision($event);
                $days = $this->days($event)->map(fn ($counts, $day) => $counts + ['date' => $day, 'label' => Carbon::parse($day)->format('l j M Y')])->values()->all();
                $after = $publication->revision($event);
                return hash_equals($before, $after) ? ['event_id' => (int) $event->id, 'days' => $days, 'revision' => $after] : null;
            });
            if ($snapshot !== null) return $snapshot;
        }
        throw new \InvalidArgumentException('The schedule changed while checking publication status. Check the current status before another action.');
    }
}
