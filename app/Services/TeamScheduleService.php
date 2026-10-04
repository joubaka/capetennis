<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\OrderOfPlay;
use App\Models\TeamFixture;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TeamScheduleService
{
    public function save(Draw $draw, array $data): void
    {
        DB::transaction(function () use ($draw, $data) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            $this->lockScheduler();
            $fixture = TeamFixture::where('draw_id', $draw->id)->whereKey($data['fixture_id'])->lockForUpdate()->firstOrFail();
            $fixture->setRelation('draw', $draw);
            $fields = array_intersect_key($data, array_flip(['scheduled_at', 'venue_id', 'court_label', 'duration_min']));
            $fixture->fill($fields);
            if (!$fixture->isDirty()) {
                return;
            }
            app(TeamDrawMutationGuard::class)->fixture($fixture);
            if ($fixture->venue_id) {
                $venue = $draw->venues()->where('venues.id', $fixture->venue_id)->first();
                if (!$venue) {
                    throw ValidationException::withMessages(['venue_id' => 'Select a venue configured for this draw.']);
                }
                $court = $this->court($fixture->court_label);
                if ($fixture->court_label && ($court === null || $court < 1 || $court > (int) $venue->pivot->num_courts)) {
                    throw ValidationException::withMessages(['court_label' => 'Select a court within the configured venue capacity.']);
                }
                Venue::whereKey($fixture->venue_id)->lockForUpdate()->firstOrFail();
            }
            if ($fixture->scheduled_at) {
                $start = Carbon::parse($fixture->scheduled_at);
                $end = $start->copy()->addMinutes((int) ($fixture->duration_min ?: 120));
                $court = $this->court($fixture->court_label);
                $players = $this->players($fixture);
                foreach (TeamFixture::with('fixturePlayers')->whereKeyNot($fixture->id)
                    ->whereNotNull('scheduled_at')->where('scheduled_at', '<', $end)->lockForUpdate()->get() as $existing) {
                    $booking = $this->booking($existing, Carbon::parse($existing->scheduled_at),
                        Carbon::parse($existing->scheduled_at)->addMinutes((int) ($existing->duration_min ?: 120)));
                    $sameCourt = $fixture->venue_id && $booking['venue'] === (int) $fixture->venue_id
                        && ($court === null || $booking['court'] === null || $court === $booking['court']);
                    if ($start->lt($booking['end']) && ($sameCourt || array_intersect($players, $booking['players']))) {
                        throw ValidationException::withMessages(['scheduled_at' => 'This time overlaps a court or player booking.']);
                    }
                }
                if ($fixture->venue_id) {
                    foreach (OrderOfPlay::where('venue_id', $fixture->venue_id)->whereNotNull('time')->where('time', '<', $end)->lockForUpdate()->get() as $existing) {
                        $existingCourt = $this->court($existing->court);
                        if (($court === null || $existingCourt === null || $court === $existingCourt)
                            && $start->lt(Carbon::parse($existing->time)->addMinutes($existing->occupiedMinutes()))) {
                            throw ValidationException::withMessages(['scheduled_at' => 'This court is booked by an individual fixture.']);
                        }
                    }
                }
            }
            $fixture->forceFill(['scheduled' => $fixture->scheduled_at ? 1 : 0, 'clash_flag' => false])->save();
        });
    }

    public function validate(Request $request): array
    {
        return $request->validate([
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'duration' => 'required|integer|min:20|max:480',
            'gap' => 'nullable|integer|min:0|max:120',
            'round' => 'nullable',
            'venues' => 'nullable|array',
            'venues.*' => 'integer|distinct|exists:venues,id',
            'rank_venue_map' => 'nullable|array',
            'rank_venue_map.*' => 'nullable|integer|exists:venues,id',
            'rank_duration_map' => 'nullable|array',
            'rank_duration_map.*' => 'integer|min:20|max:480',
        ]);
    }

    public function automatic(Collection $draws, array $data, bool $reset = false): array
    {
        return DB::transaction(function () use ($draws, $data, $reset) {
            $guard = app(TeamDrawMutationGuard::class);
            $lockedDraws = Draw::whereIn('id', $draws->pluck('id'))->orderBy('id')->lockForUpdate()->get();
            $this->lockScheduler();
            TeamFixture::whereIn('draw_id', $lockedDraws->pluck('id'))->orderBy('id')->lockForUpdate()->get();
            $venuesByDraw = [];
            foreach ($lockedDraws as $draw) {
                $guard->schedule($draw);
                if ($reset) {
                    $guard->destructive($draw);
                }
                $venues = $draw->venues;
                // An event-wide selection may include another category's venues.
                // Every selected venue must still belong to one of the requested draws.
                $venuesByDraw[$draw->id] = $venues;
            }
            $allVenueIds = collect($venuesByDraw)->flatten(1)->pluck('id')->unique()->all();
            $requested = array_filter(array_merge($data['venues'] ?? [], array_values($data['rank_venue_map'] ?? [])));
            if (array_diff($requested, $allVenueIds)) {
                throw ValidationException::withMessages(['venues' => 'Select venues configured for these draws.']);
            }
            foreach ($venuesByDraw as $drawId => $venues) {
                if (!empty($data['venues'])) {
                    $venuesByDraw[$drawId] = $venues->whereIn('id', $data['venues']);
                }
                if ($venuesByDraw[$drawId]->isEmpty()) {
                    throw ValidationException::withMessages(['venues' => "No venues configured for draw #{$drawId}."]);
                }
            }
            // Serialize schedulers sharing the same venue, even across events.
            Venue::whereIn('id', $allVenueIds)->orderBy('id')->lockForUpdate()->get();
            if ($reset) {
                $this->clearRows($lockedDraws->pluck('id')->all());
            }
            $start = Carbon::parse($data['start']);
            $end = Carbon::parse($data['end']);
            $gap = (int) ($data['gap'] ?? 0);
            $bookings = [];
            // All team draws participate in court/player occupancy, including other events.
            foreach (TeamFixture::with('fixturePlayers')->whereNotNull('scheduled_at')->where('scheduled_at', '<', $end)->lockForUpdate()->get() as $fixture) {
                $finish = Carbon::parse($fixture->scheduled_at)->addMinutes((int) ($fixture->duration_min ?: 120));
                if ($finish->copy()->addMinutes($gap)->lte($start)) {
                    continue;
                }
                $bookings[] = $this->booking($fixture, Carbon::parse($fixture->scheduled_at), $finish);
            }
            // Individual draws can occupy the same physical courts.
            foreach (OrderOfPlay::whereIn('venue_id', $allVenueIds)->whereNotNull('time')->where('time', '<', $end)->lockForUpdate()->get() as $row) {
                $finish = Carbon::parse($row->time)->addMinutes($row->occupiedMinutes());
                if ($finish->lte($start)) {
                    continue;
                }
                $bookings[] = ['venue' => (int) $row->venue_id, 'court' => $this->court($row->court),
                    'start' => Carbon::parse($row->time), 'end' => $finish, 'players' => []];
            }
            $rounds = collect(is_array($data['round'] ?? null) ? $data['round'] : explode(',', (string) ($data['round'] ?? '')))
                ->map(fn ($round) => trim((string) $round))->filter(fn ($round) => $round !== '')->values();
            $assigned = [];
            $skipped = [];
            foreach ($lockedDraws as $draw) {
                $fixtures = TeamFixture::with(['fixturePlayers', 'teamTie'])->where('draw_id', $draw->id)
                    ->whereNull('scheduled_at')->when($rounds->isNotEmpty(), fn ($q) => $q->whereIn('round_nr', $rounds))
                    ->orderByRaw('CAST(round_nr AS UNSIGNED)')->orderBy('tie_nr')->orderBy('rubber_sequence')
                    ->orderBy('home_rank_nr')->orderBy('id')->lockForUpdate()->get();
                foreach ($fixtures as $fixture) {
                    if ($fixture->fixtureResults()->exists() || (int) $fixture->match_status !== 0 || $fixture->teamTie?->isCompleted()) {
                        $skipped[] = ['id' => $fixture->id, 'reason' => 'Fixture has play or results'];
                        continue;
                    }
                    $rank = $fixture->home_rank_nr ?? $fixture->rubber_sequence;
                    $duration = (int) ($data['rank_duration_map'][$rank] ?? $data['duration']);
                    $mapped = $data['rank_venue_map'][$rank] ?? null;
                    $venues = $venuesByDraw[$draw->id]->when($mapped, fn ($v) => $v->where('id', $mapped));
                    $players = $this->players($fixture);
                    $best = null;
                    foreach ($venues as $venue) {
                        $courts = max(1, (int) ($venue->pivot->num_courts ?? 1));
                        for ($court = 1; $court <= $courts; $court++) {
                            $candidate = $start->copy();
                            do {
                                $moved = false;
                                $finish = $candidate->copy()->addMinutes($duration);
                                foreach ($bookings as $booking) {
                                    $sameCourt = $booking['venue'] === (int) $venue->id
                                        && ($booking['court'] === null || $booking['court'] === $court);
                                    if (!$sameCourt && !array_intersect($players, $booking['players'])) {
                                        continue;
                                    }
                                    if ($candidate->lt($booking['end']->copy()->addMinutes($gap))
                                        && $finish->copy()->addMinutes($gap)->gt($booking['start'])) {
                                        $candidate = $booking['end']->copy()->addMinutes($gap);
                                        $moved = true;
                                        break;
                                    }
                                }
                            } while ($moved && $candidate->copy()->addMinutes($duration)->lte($end));
                            if ($candidate->copy()->addMinutes($duration)->lte($end)
                                && ($best === null || $candidate->lt($best['start']))) {
                                $best = ['start' => $candidate, 'venue' => (int) $venue->id, 'court' => $court];
                            }
                        }
                    }
                    if ($best === null) {
                        $skipped[] = ['id' => $fixture->id, 'reason' => 'No conflict-free slot within the time window'];
                        continue;
                    }
                    $fixture->forceFill(['scheduled_at' => $best['start'], 'venue_id' => $best['venue'],
                        'court_label' => "Court {$best['court']}", 'duration_min' => $duration,
                        'scheduled' => 1, 'clash_flag' => false])->save();
                    $bookings[] = $this->booking($fixture, $best['start'], $best['start']->copy()->addMinutes($duration));
                    $assigned[] = ['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $best['venue'],
                        'court' => $fixture->court_label, 'scheduled_at' => $best['start']->format('Y-m-d H:i'), 'duration' => $duration];
                }
            }
            return ['success' => true, 'assigned' => $assigned, 'count' => count($assigned),
                'skipped' => $skipped, 'clashes' => [], 'rounds_processed' => $rounds];
        });
    }

    public function clear(Collection $draws): void
    {
        DB::transaction(function () use ($draws) {
            $locked = Draw::whereIn('id', $draws->pluck('id'))->orderBy('id')->lockForUpdate()->get();
            $this->lockScheduler();
            TeamFixture::whereIn('draw_id', $locked->pluck('id'))->orderBy('id')->lockForUpdate()->get();
            foreach ($locked as $draw) {
                app(TeamDrawMutationGuard::class)->destructive($draw);
            }
            $this->clearRows($locked->pluck('id')->all());
        });
    }

    private function clearRows(array $ids): void
    {
        TeamFixture::whereIn('draw_id', $ids)->update(['scheduled_at' => null, 'venue_id' => null,
            'court_label' => null, 'duration_min' => null, 'clash_flag' => false, 'scheduled' => 0]);
    }

    private function lockScheduler(): void
    {
        // A stable common row serializes team scheduling across different venues
        // and events. Locking booking reads below use the latest committed state.
        Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
    }

    private function booking(TeamFixture $fixture, Carbon $start, Carbon $end): array
    {
        return ['venue' => (int) $fixture->venue_id, 'court' => $this->court($fixture->court_label),
            'start' => $start, 'end' => $end, 'players' => $this->players($fixture)];
    }

    private function court(?string $label): ?int
    {
        return preg_match('/^(?:Court\s*)?(\d+)$/i', trim($label ?? ''), $matches) ? (int) $matches[1] : null;
    }

    private function players(TeamFixture $fixture): array
    {
        $keys = [];
        // Current reads avoid an earlier repeatable-read snapshot of assignments.
        foreach ($fixture->fixturePlayers()->lockForUpdate()->get() as $slot) {
            foreach (['team1_id', 'team2_id'] as $field) {
                if ($slot->$field) {
                    $keys[] = 'player:'.$slot->$field;
                }
            }
            foreach (['team1_no_profile_id', 'team2_no_profile_id'] as $field) {
                if ($slot->$field) {
                    $keys[] = 'imported:'.$slot->$field;
                }
            }
        }
        return array_unique($keys);
    }
}
