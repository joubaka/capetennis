<?php

namespace App\Services\Scheduling;

use App\Models\{Event, Fixture, OrderOfPlay, TeamFixture, Venue};
use Illuminate\Support\Facades\DB;

final class VenueCourtCorrectionService
{
    // Called under the venue/event locks held by the court mutation transaction.
    public function prepare(Event $event, Venue $venue, array $before, array $after, array $confirmation): ?array
    {
        if (! array_diff($before, $after)) return null;
        $removedKeys = array_map(\App\Domain\Draws\Services\ScheduleAvailability::courtKey(...), array_values(array_diff($before, $after)));
        $drawIds = $event->draws()->pluck('id');
        $bookings = OrderOfPlay::whereHas('fixture', fn ($query) => $query->whereIn('draw_id', $drawIds))
            ->where('venue_id', $venue->id)->orderBy('id')->lockForUpdate()->get();
        $historical = $bookings->filter(fn ($booking) => in_array(\App\Domain\Draws\Services\ScheduleAvailability::courtKey((string) $booking->court), $removedKeys, true));
        $bookings = $bookings->filter(fn ($booking) => $booking->time !== null);
        $public = DB::table('published_schedule_assignments')->where('event_id', $event->id)
            ->whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)->orderBy('id')->lockForUpdate()->get();
        $individual = Fixture::with('fixtureResults')->whereIn('draw_id', $drawIds)
            ->whereIn('id', $bookings->pluck('fixture_id')->merge($historical->pluck('fixture_id'))->merge($public->where('fixture_kind', 'individual')->pluck('fixture_id')))
            ->orderBy('id')->lockForUpdate()->get();
        $team = TeamFixture::with(['fixtureResults', 'teamTie'])->whereIn('draw_id', $drawIds)
            ->where(fn ($query) => $query->where('venue_id', $venue->id)
                ->orWhereIn('id', $public->where('fixture_kind', 'team')->pluck('fixture_id')))
            ->orderBy('id')->lockForUpdate()->get();
        if ($individual->contains(fn ($fixture) => $fixture->fixtureResults->isNotEmpty() || (int) $fixture->match_status !== 0)
            || $team->contains(fn ($fixture) => app(UnifiedTeamScheduleService::class)->protected($fixture))) {
            return ['status' => 422, 'message' => 'This venue has played matches or results in the affected schedule. Its courts cannot be corrected automatically.'];
        }
        $scheduledTeam = $team->filter(fn ($fixture) => (int) $fixture->venue_id === (int) $venue->id && $fixture->scheduled_at !== null);
        $protectedDraws = $event->draws()->whereHas('venues', fn ($query) => $query->where('venues.id', $venue->id))
            ->where(fn ($query) => $query->where('locked', true)->orWhere('published', true))->orderBy('id')->get(['draws.id', 'locked', 'published']);
        $counts = ['scheduled_matches' => $bookings->pluck('fixture_id')->unique()->count() + $scheduledTeam->count(),
            'published_matches' => $public->count(), 'protected_draws' => $protectedDraws->count()];
        if (! array_sum($counts)) return null;
        $revision = hash('sha256', json_encode([$event->id, $venue->id, $before, $after,
            $bookings->toArray(), $public->all(), $team->map(fn ($row) => $row->only(['id', 'venue_id', 'court_label', 'scheduled_at', 'updated_at']))->all(),
            $protectedDraws->toArray()]));
        if (! ($confirmation['confirm_reset'] ?? false) || ! hash_equals($revision, $confirmation['correction_revision'] ?? '')) {
            return ['status' => 409, 'requires_confirmation' => true, 'correction_revision' => $revision, 'impact' => $counts,
                'message' => "Correcting these courts will clear all {$counts['scheduled_matches']} scheduled matches for this event at {$venue->name}, across all age groups, and withdraw {$counts['published_matches']} published match times at this venue. Draws, players and results will be preserved. Continue and reschedule afterward?"];
        }
        // Canonical services preserve fixtures, audit scheduling changes, and check dependencies.
        if ($counts['scheduled_matches']) app(EventVenueScheduleService::class)->unapplyForCourtCorrection($event, $venue->id);
        if ($counts['published_matches']) app(SchedulePublicationService::class)->hide($event, ['venue_id' => $venue->id]);
        return null;
    }
}
