<?php

namespace App\Services\Scheduling;

use App\Domain\Draws\Services\ScheduleAvailability;
use App\Models\{CategoryEvent, Event, Fixture, OrderOfPlay, TeamFixture, Venue};
use Illuminate\Support\Facades\DB;

final class VenueCourtCorrectionService
{
    // Called under the venue/event locks held by the court mutation transaction.
    public function prepare(Event $event, Venue $venue, array $before, array $after, array $confirmation): ?array
    {
        $options = $this->ageOptions($event, $venue);
        $ageKeys = array_values(array_unique($confirmation['reset_age_keys'] ?? [])); sort($ageKeys);
        if (array_diff($ageKeys, array_column($options, 'key'))) throw new \InvalidArgumentException('Choose age groups offered for this event and venue.');
        if (! array_diff($before, $after)) {
            if (! ($confirmation['review_only'] ?? false)) return null;
            return ['status' => 409, 'requires_confirmation' => true, 'correction_revision' => hash('sha256', json_encode([$event->id, $venue->id, $before, $after])),
                'impact' => ['scheduled_matches' => 0, 'additional_matches' => 0, 'dependent_matches' => 0, 'published_matches' => 0, 'protected_draws' => 0, 'affected_venues' => 0],
                'age_group_options' => $this->ageOptions($event, $venue), 'reset_age_keys' => [], 'affected_venue_names' => [],
                'message' => 'The courts already match this setup. No scheduled matches will be returned to planning.'];
        }
        $removedKeys = array_map(ScheduleAvailability::courtKey(...), array_values(array_diff($before, $after)));
        $drawIds = $event->draws()->pluck('id');
        $allBookings = OrderOfPlay::whereHas('fixture', fn ($query) => $query->whereIn('draw_id', $drawIds))
            ->where('venue_id', $venue->id)->orderBy('id')->lockForUpdate()->get();
        $historicalIds = $allBookings->filter(fn ($booking) => in_array(ScheduleAvailability::courtKey((string) $booking->court), $removedKeys, true))->pluck('fixture_id');
        $venueBookings = $allBookings->filter(fn ($booking) => $booking->time !== null);
        $venuePublic = DB::table('published_schedule_assignments')->where('event_id', $event->id)
            ->whereIn('draw_id', $drawIds)->where('venue_id', $venue->id)->orderBy('id')->lockForUpdate()->get();
        $venueTeam = TeamFixture::with(['fixtureResults', 'teamTie'])->whereIn('draw_id', $drawIds)
            ->where('venue_id', $venue->id)->orderBy('id')->lockForUpdate()->get();
        $rootIndividualIds = $venueBookings->pluck('fixture_id')->merge($venuePublic->where('fixture_kind', 'individual')->pluck('fixture_id'))->unique();
        $rootTeamIds = $venueTeam->whereNotNull('scheduled_at')->pluck('id')->merge($venuePublic->where('fixture_kind', 'team')->pluck('fixture_id'))->unique();
        $ageDrawIds = collect($options)->whereIn('key', $ageKeys)->pluck('draw_ids')->flatten()->unique();
        if ($ageDrawIds->isNotEmpty()) {
            $rootIndividualIds = $rootIndividualIds->merge(Fixture::whereIn('draw_id', $ageDrawIds)->whereHas('orderOfPlay', fn ($query) => $query->whereNotNull('time'))->pluck('id'));
            $rootTeamIds = $rootTeamIds->merge(TeamFixture::whereIn('draw_id', $ageDrawIds)->whereNotNull('scheduled_at')->pluck('id'));
            $agePublic = DB::table('published_schedule_assignments')->where('event_id', $event->id)->whereIn('draw_id', $ageDrawIds)->get();
            $rootIndividualIds = $rootIndividualIds->merge($agePublic->where('fixture_kind', 'individual')->pluck('fixture_id'));
            $rootTeamIds = $rootTeamIds->merge($agePublic->where('fixture_kind', 'team')->pluck('fixture_id'));
        }
        $closure = app(EventVenueScheduleService::class)->courtCorrectionClosure($event, $rootIndividualIds->unique()->all(), $rootTeamIds->unique()->all());
        $individualIds = array_map(fn ($key) => (int) substr($key, 11), array_values(array_filter($closure['keys'], fn ($key) => str_starts_with($key, 'individual:'))));
        $teamIds = array_map(fn ($key) => (int) substr($key, 5), array_values(array_filter($closure['keys'], fn ($key) => str_starts_with($key, 'team:'))));
        $individual = Fixture::with('fixtureResults')->whereIn('draw_id', $drawIds)->whereIn('id', array_merge($individualIds, $historicalIds->all()))
            ->orderBy('id')->lockForUpdate()->get();
        $team = TeamFixture::with(['fixtureResults', 'teamTie'])->whereIn('draw_id', $drawIds)->whereIn('id', array_merge($teamIds, $venueTeam->pluck('id')->all()))
            ->orderBy('id')->lockForUpdate()->get();
        if ($individual->contains(fn ($fixture) => $fixture->fixtureResults->isNotEmpty() || (int) $fixture->match_status !== 0)
            || $team->contains(fn ($fixture) => app(UnifiedTeamScheduleService::class)->protected($fixture))) {
            return ['status' => 422, 'message' => 'An affected match or dependent match has play or results. Courts and schedules were preserved; those matches cannot be returned to planning automatically.'];
        }
        $bookings = OrderOfPlay::whereIn('fixture_id', $individualIds)->whereNotNull('time')->orderBy('id')->lockForUpdate()->get();
        $scheduledTeam = $team->whereIn('id', $teamIds)->whereNotNull('scheduled_at');
        $public = DB::table('published_schedule_assignments')->where('event_id', $event->id)->whereIn('draw_id', $drawIds)
            ->where(fn ($query) => $query->where(fn ($row) => $row->where('fixture_kind', 'individual')->whereIn('fixture_id', $individualIds))
                ->orWhere(fn ($row) => $row->where('fixture_kind', 'team')->whereIn('fixture_id', $teamIds)))
            ->orderBy('id')->lockForUpdate()->get();
        $protectedDraws = $event->draws()->whereHas('venues', fn ($query) => $query->where('venues.id', $venue->id))
            ->where(fn ($query) => $query->where('locked', true)->orWhere('published', true))->orderBy('id')->get(['draws.id', 'locked', 'published']);
        $baseKeys = array_merge($venueBookings->map(fn ($row) => 'individual:'.$row->fixture_id)->all(), $venueTeam->whereNotNull('scheduled_at')->map(fn ($row) => 'team:'.$row->id)->all());
        $scheduledKeys = array_merge($bookings->map(fn ($row) => 'individual:'.$row->fixture_id)->all(), $scheduledTeam->map(fn ($row) => 'team:'.$row->id)->all());
        $venueIds = $bookings->pluck('venue_id')->merge($scheduledTeam->pluck('venue_id'))->merge($public->pluck('venue_id'))->filter()->unique()->sort()->values();
        $counts = ['scheduled_matches' => count(array_unique($scheduledKeys)), 'additional_matches' => count(array_diff(array_unique($scheduledKeys), $baseKeys)),
            'dependent_matches' => count(array_intersect($scheduledKeys, array_diff($closure['keys'], array_merge($rootIndividualIds->map(fn ($id) => 'individual:'.$id)->all(), $rootTeamIds->map(fn ($id) => 'team:'.$id)->all())))),
            'published_matches' => $public->count(), 'protected_draws' => $protectedDraws->count(), 'affected_venues' => $venueIds->count()];
        if (! array_sum($counts) && ! ($confirmation['review_only'] ?? false)) return null;
        $venueNames = Venue::whereIn('id', $venueIds)->orderBy('name')->pluck('name')->all();
        $revision = hash('sha256', json_encode([$event->id, $venue->id, $before, $after, $ageKeys, $closure,
            $bookings->toArray(), $public->all(), $team->map(fn ($row) => $row->only(['id', 'venue_id', 'court_label', 'scheduled_at', 'updated_at']))->all(), $protectedDraws->toArray(), $options]));
        if (($confirmation['review_only'] ?? false) || ! ($confirmation['confirm_reset'] ?? false) || ! hash_equals($revision, $confirmation['correction_revision'] ?? '')) {
            return ['status' => 409, 'requires_confirmation' => true, 'correction_revision' => $revision, 'impact' => $counts,
                'age_group_options' => $options, 'reset_age_keys' => $ageKeys, 'affected_venue_names' => $venueNames,
                'message' => "Correcting these courts will return {$counts['scheduled_matches']} scheduled matches to planning, including {$counts['additional_matches']} additional matches outside the venue reset ({$counts['dependent_matches']} dependent matches), across {$counts['affected_venues']} venues, and withdraw {$counts['published_matches']} affected published match times. All scheduled matches for this event at {$venue->name} are included, across all age groups. Draws, players and results will be preserved. Continue and reschedule afterward?"];
        }
        app(EventVenueScheduleService::class)->unapplyCorrectionSelection($event, $individualIds, $teamIds);
        if ($counts['published_matches']) app(SchedulePublicationService::class)->hideFixturesForCourtCorrection($event, $public->map(fn ($row) => $row->fixture_kind.':'.$row->fixture_id)->all());
        return null;
    }

    private function ageOptions(Event $event, Venue $venue): array
    {
        $categories = CategoryEvent::with('category')->where('event_id', $event->id)->get()->keyBy('id');
        $groups = []; $venueKeys = [];
        foreach ($event->draws()->with('venues')->orderBy('id')->get() as $draw) {
            $ids = array_unique(array_filter(array_merge([$draw->category_event_id], $draw->team_draw_selection['category_ids'] ?? [])));
            $ages = collect($ids)->map(fn ($id) => $categories->get($id)?->category?->name)
                ->map(fn ($name) => preg_match('/^(?:u\s*\/?\s*|under\s+)(\d+)\b/i', trim((string) $name), $match) ? (int) $match[1] : null)->unique();
            if ($ages->count() !== 1 || ! $ages->first()) continue;
            $age = $ages->first(); $key = 'under:'.$age;
            $groups[$key] ??= ['key' => $key, 'label' => 'Under '.$age, 'draw_ids' => []];
            $groups[$key]['draw_ids'][] = (int) $draw->id;
            if ($draw->venues->contains('id', $venue->id)) $venueKeys[$key] = true;
        }
        ksort($groups);
        return array_values(array_intersect_key($groups, $venueKeys));
    }
}
