<?php

namespace App\Services\Scheduling;

use App\Models\{Event, TeamFixture};
use Illuminate\Support\Facades\DB;

final class RoundVenueSetup
{
    public function save(Event $event, array $rows, ?int $actor): array
    {
        return DB::transaction(function () use ($event, $rows, $actor) {
            DB::table('venues')->orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $draws = $event->draws()->with('venues')->whereIn('id', array_column($rows, 'draw_id'))->lockForUpdate()->get()->keyBy('id');
            $allowed = $event->venues()->pluck('venues.id')->merge($event->draws()->with('venues')->get()->flatMap(fn ($draw) => $draw->venues->pluck('id')))->unique()->all();
            $normalized = [];
            foreach ($rows as $row) {
                $draw = $draws[$row['draw_id']] ?? null;
                if (! $draw || ! $draw->isTeamDraw() || $draw->locked) throw new \InvalidArgumentException('Choose an unlocked team draw in this event.');
                if (! TeamFixture::where('draw_id', $draw->id)->where('round_nr', $row['round'])->exists()) throw new \InvalidArgumentException('Choose a generated round in this draw.');
                $venues = array_values(array_unique(array_map('intval', $row['venue_ids'])));
                if (! $venues || array_diff($venues, $allowed)) throw new \InvalidArgumentException('Choose venues assigned to this event.');
                $courts = [];
                foreach ($row['court_allocations'] as $allocation) {
                    $id = (int) $allocation['venue_id'];
                    if (! in_array($id, $venues, true) || isset($courts[$id])) throw new \InvalidArgumentException('Choose one court allocation for each selected venue.');
                    $active = DB::table('event_venue_courts')->where('event_id', $event->id)->where('venue_id', $id)->where('active', true)->pluck('label')->map(fn ($label) => (string) $label)->all();
                    if (! $active && DB::table('event_venue_courts')->where('event_id', $event->id)->where('venue_id', $id)->exists()) throw new \InvalidArgumentException('This venue has no active physical courts.');
                    if (! $active) {
                        $count = DB::table('event_venues')->where('event_id', $event->id)->where('venue_id', $id)->value('num_courts')
                            ?? $event->draws()->with('venues')->get()->flatMap(fn ($item) => $item->venues)->where('id', $id)->max(fn ($venue) => $venue->pivot->num_courts);
                        if ((int) $count < 1) throw new \InvalidArgumentException('This venue has no allocated physical courts.');
                        $active = array_map('strval', range(1, (int) $count));
                    }
                    $labels = array_values(array_unique(array_map('strval', $allocation['court_labels'])));
                    $activeKeys = collect($active)->keyBy(fn ($label) => \App\Domain\Draws\Services\ScheduleAvailability::courtKey($label));
                    $labels = array_map(fn ($label) => $activeKeys[\App\Domain\Draws\Services\ScheduleAvailability::courtKey($label)] ?? $label, $labels);
                    if (! $labels || array_diff($labels, $active)) throw new \InvalidArgumentException('Choose active physical courts in this event.');
                    $courts[$id] = $labels;
                }
                if (array_diff($venues, array_keys($courts))) throw new \InvalidArgumentException('Allocate courts to every selected venue.');
                $rules = [];
                foreach ($row['rank_venue_preferences'] ?? [] as $rule) {
                    $min = (int) $rule['min_rank']; $max = (int) $rule['max_rank']; $venue = (int) $rule['venue_id'];
                    if ($min < 1 || $max < $min || $max > 100 || ! in_array($venue, $venues, true)) throw new \InvalidArgumentException('Use valid non-overlapping position bands at this round\'s selected venues.');
                    foreach ($rules as $existing) if ($min <= $existing['max_rank'] && $max >= $existing['min_rank']) throw new \InvalidArgumentException('Position bands cannot overlap.');
                    $rules[] = ['draw_ids' => [(int) $draw->id], 'min_rank' => $min, 'max_rank' => $max, 'venue_id' => $venue];
                }
                $fixtures = TeamFixture::where('draw_id', $draw->id)->where('round_nr', $row['round'])->lockForUpdate()->get();
                $bookings = $fixtures->whereNotNull('scheduled_at')->map(fn ($fixture) => ['venue_id' => $fixture->venue_id, 'court' => $fixture->court_label]);
                $published = DB::table('published_schedule_assignments')->where('event_id', $event->id)->where('draw_id', $draw->id)->where('fixture_kind', 'team')->whereIn('fixture_id', $fixtures->modelKeys())->get();
                foreach ($bookings->concat($published) as $booking) {
                    $venue = (int) data_get($booking, 'venue_id'); $court = (string) data_get($booking, 'court');
                    if (! isset($courts[$venue]) || ! in_array(\App\Domain\Draws\Services\ScheduleAvailability::courtKey($court), array_map(fn ($label) => \App\Domain\Draws\Services\ScheduleAvailability::courtKey($label), $courts[$venue]), true)) throw new \InvalidArgumentException('Move saved matches and update or hide published times before removing their round venue or court.');
                }
                $key = $draw->id.'|'.(int) $row['round'];
                if (isset($normalized[$key])) throw new \InvalidArgumentException('Choose each draw round only once.');
                $normalized[$key] = ['draw_id' => (int) $draw->id, 'round' => (int) $row['round'], 'venue_ids' => $venues,
                    'court_allocations' => collect($courts)->map(fn ($labels, $id) => ['venue_id' => (int) $id, 'court_labels' => $labels])->values()->all(), 'rank_venue_preferences' => $rules];
            }
            $stored = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
            $existing = collect($stored['round_venue_setups'] ?? [])->keyBy(fn ($row) => $row['draw_id'].'|'.$row['round']);
            $stored['round_venue_setups'] = $existing->merge($normalized)->values()->all();
            DB::table('event_venue_schedule_drafts')->updateOrInsert(['event_id' => $event->id], ['options' => json_encode($stored, JSON_THROW_ON_ERROR), 'updated_by' => $actor, 'updated_at' => now(), 'created_at' => now()]);
            return $stored['round_venue_setups'];
        });
    }
}
