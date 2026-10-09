<?php

namespace App\Services\Scoring;

use App\Domain\Draws\Enums\FixtureState;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\TeamFixture;
use App\Models\Venue;
use Illuminate\Support\Collection;

final class VenueScoringProgress
{
    public function venues(Event $event, ?int $venueId = null): Collection
    {
        $date = now(config('app.timezone'))->toDateString();
        $drawIds = $event->draws()->select('id');
        $individual = Fixture::query()->join('order_of_plays', 'order_of_plays.fixture_id', '=', 'fixtures.id')
            ->whereIn('fixtures.draw_id', $drawIds)->whereNotNull('order_of_plays.time')->whereNotNull('order_of_plays.venue_id')
            ->when($venueId !== null, fn ($query) => $query->where('order_of_plays.venue_id', $venueId))
            ->selectRaw('order_of_plays.venue_id, COUNT(DISTINCT fixtures.id) AS total,
                COUNT(DISTINCT CASE WHEN DATE(order_of_plays.time) = ? THEN fixtures.id END) AS today_total,
                COUNT(DISTINCT CASE WHEN DATE(order_of_plays.time) = ? AND fixtures.match_status = ? THEN fixtures.id END) AS today_scored',
                [$date, $date, FixtureState::STATUS_COMPLETED])
            ->groupBy('order_of_plays.venue_id')->get()->keyBy('venue_id');
        $team = TeamFixture::query()->whereIn('draw_id', $drawIds)->whereNotNull('scheduled_at')->whereNotNull('venue_id')
            ->when($venueId !== null, fn ($query) => $query->where('venue_id', $venueId))
            ->selectRaw('venue_id, COUNT(*) AS total,
                SUM(CASE WHEN DATE(scheduled_at) = ? THEN 1 ELSE 0 END) AS today_total,
                SUM(CASE WHEN DATE(scheduled_at) = ? AND match_status = ? THEN 1 ELSE 0 END) AS today_scored',
                [$date, $date, FixtureState::STATUS_COMPLETED])
            ->groupBy('venue_id')->get()->keyBy('venue_id');

        return Venue::query()->whereIn('id', $individual->keys()->merge($team->keys())->unique())->orderBy('name')->get()
            ->map(function ($venue) use ($individual, $team, $date) {
                $venue->fixture_count = (int) ($individual->get($venue->id)?->total ?? 0) + (int) ($team->get($venue->id)?->total ?? 0);
                $venue->today_fixture_count = (int) ($individual->get($venue->id)?->today_total ?? 0) + (int) ($team->get($venue->id)?->today_total ?? 0);
                $venue->today_scored_count = (int) ($individual->get($venue->id)?->today_scored ?? 0) + (int) ($team->get($venue->id)?->today_scored ?? 0);
                $venue->scoring_date = $date;

                return $venue;
            });
    }
}
