<?php

namespace App\Services\Scheduling;

use App\Models\{DrawAuditLog, Event};
use Illuminate\Support\Facades\DB;

/** One age's future venue preferences; never moves saved bookings or publishes. */
final class TeamAgeVenueSetup
{
    public function state(Event $event, int $age): array
    {
        $draws = $event->draws()->with(['categoryEvent.category', 'venues'])->orderBy('id')->get()
            ->filter(fn ($draw) => $draw->isTeamDraw() && app(ScheduleProgramme::class)->age($draw) === $age)->values();
        $rounds = app(EventVenueScheduleService::class)->availableDrawRounds($draws, true);
        $stored = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
        $ids = $draws->pluck('id')->all();
        $graph = [
            'event' => $event->id, 'age' => $age,
            'draws' => $draws->map(fn ($draw) => $draw->only(['id', 'locked', 'updated_at', 'team_draw_selection']))->all(),
            'rounds' => $rounds,
            'fixtures' => DB::table('team_fixtures')->whereIn('draw_id', $ids)->orderBy('id')->get()->all(),
            'venues' => DB::table('event_venues')->where('event_id', $event->id)->orderBy('venue_id')->get()->all(),
            'courts' => DB::table('event_venue_courts')->where('event_id', $event->id)->orderBy('id')->get()->all(),
            'draw_venues' => DB::table('draw_venues')->whereIn('draw_id', $ids)->orderBy('draw_id')->orderBy('venue_id')->get()->all(),
            'overrides' => collect($stored['round_venue_setups'] ?? [])->whereIn('draw_id', $ids)->values()->all(),
            'day_allocations' => collect($stored['day_batch_allocations'] ?? [])->filter(fn ($row, $key) => str_starts_with((string) $key, 'team:') && in_array((int) ($row['draw_id'] ?? 0), $ids, true))->all(),
            'setup' => $stored['team_age_venue_setups'][(string) $age] ?? null,
        ];
        $effective = collect($graph['overrides'])->map(fn ($row) => ['court_allocations' => $row['court_allocations'],
            'rank_venue_preferences' => array_map(fn ($rule) => array_diff_key($rule, ['draw_ids' => true]), $row['rank_venue_preferences'])]);
        $exceptions = $graph['setup'] && $effective->contains(fn ($row) => $row !== $graph['setup']);
        return ['revision' => hash('sha256', json_encode($graph, JSON_THROW_ON_ERROR)),
            'draw_ids' => $ids, 'rounds' => $rounds, 'setup' => $graph['setup'],
            'exceptions' => (bool) $exceptions, 'locked' => $draws->contains('locked', true)];
    }

    /** Expand and validate one complete age setup in memory only. */
    public function previewRows(Event $event, array $data): array
    {
    $state = $this->state($event, (int) $data['age']);
    if (! hash_equals($state['revision'], $data['revision'])) throw new \DomainException('This age group changed. Reload the scheduling page before previewing or saving its setup.');
    if (! $state['draw_ids'] || $state['locked']) throw new \InvalidArgumentException('Choose an age group with unlocked team draws in this event.');
    $rows = [];
    foreach ($state['draw_ids'] as $id) {
        if (empty($state['rounds'][$id])) throw new \InvalidArgumentException('Generate every discipline in this age group before saving its shared setup.');
        foreach ($state['rounds'][$id] as $round) $rows[] = ['draw_id' => $id, 'round' => $round,
            'venue_ids' => array_column($data['court_allocations'], 'venue_id'),
            'court_allocations' => $data['court_allocations'], 'rank_venue_preferences' => $data['rank_venue_preferences']];
    }

        return array_values(app(RoundVenueSetup::class)->normalize($event, $rows));
    }

    public function save(Event $event, array $data, ?int $actor): array
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            DB::table('venues')->orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            $event->draws()->orderBy('id')->lockForUpdate()->get();
            $rows = $this->previewRows($event, $data);
            $state = $this->state($event, (int) $data['age']);
            $rounds = app(RoundVenueSetup::class)->save($event, $rows, $actor);
            $stored = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
            $saved = collect($rounds)->first(fn ($row) => in_array($row['draw_id'], $state['draw_ids'], true));
            $stored['team_age_venue_setups'][(string) $data['age']] = ['court_allocations' => $saved['court_allocations'],
                'rank_venue_preferences' => array_map(fn ($rule) => array_diff_key($rule, ['draw_ids' => true]), $saved['rank_venue_preferences'])];
            $stored['day_batch_allocations'] = collect($stored['day_batch_allocations'] ?? [])->reject(fn ($row, $key) => str_starts_with((string) $key, 'team:') && in_array((int) ($row['draw_id'] ?? 0), $state['draw_ids'], true))->all();
            DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->update(['options' => json_encode($stored, JSON_THROW_ON_ERROR), 'updated_by' => $actor, 'updated_at' => now()]);
            foreach ($state['draw_ids'] as $id) DrawAuditLog::record($id, 'team_age_venue_setup_saved', null, ['age' => (int) $data['age'], 'actor_id' => $actor, 'round_count' => count($state['rounds'][$id])]);
            return ['message' => 'Age-group setup saved across all rounds and disciplines. Saved match times and publication are unchanged.',
                'state' => $this->state($event, (int) $data['age']), 'round_venue_setups' => $rounds];
        });
    }
}
