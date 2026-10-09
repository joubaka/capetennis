<?php

namespace App\Services\Scheduling;

use App\Models\{Event, Fixture, TeamFixture};
use App\Services\TeamFixtureLineupPresenter;
use Illuminate\Pagination\LengthAwarePaginator;

/** Private, read-only inspection of saved matches; never changes publication. */
class ScheduleDrawReviewService
{
    public function build(Event $event, array $scope, int $page = 1): array
    {
        $draws = $event->draws()->orderBy('drawName')->orderBy('id')->get();
        $rows = app(SchedulePublicationService::class)->workingRows($event)->filter(fn ($row) =>
            (empty($scope['venue_id']) || (int) $row['venue_id'] === (int) $scope['venue_id'])
            && (($scope['date'] ?? 'all') === 'all' || substr($row['scheduled_at'], 0, 10) === $scope['date']));
        $counts = $rows->countBy('draw_id');
        $rows = $rows->filter(fn ($row) => empty($scope['draw_id']) || (int) $row['draw_id'] === (int) $scope['draw_id']);
        // Keep the canonical time-then-rank order inside each draw.
        $ordered = $draws->flatMap(fn ($draw) => $rows->where('draw_id', $draw->id))->values();
        $pageRows = $ordered->forPage($page, 100)->values();
        $teams = TeamFixture::whereIn('draw_id', $draws->pluck('id'))
            ->whereIn('id', $pageRows->where('fixture_kind', 'team')->pluck('fixture_id'))->get()->keyBy('id');
        if ($teams->isNotEmpty()) app(TeamFixtureLineupPresenter::class)->prepare($teams, publicDraw: true);
        $individuals = Fixture::with(['registration1.categoryEvents', 'registration2.categoryEvents'])->whereIn('draw_id', $draws->pluck('id'))
            ->whereIn('id', $pageRows->where('fixture_kind', 'individual')->pluck('fixture_id'))->get()->keyBy('id');
        $pageRows = $pageRows->map(function ($row) use ($teams, $individuals, $event) {
            $fixture = $row['fixture_kind'] === 'team' ? $teams->get($row['fixture_id']) : $individuals->get($row['fixture_id']);
            if ($fixture instanceof TeamFixture) {
                $template = collect(($fixture->teamTie?->format_snapshot ?? $fixture->draw?->team_format_snapshot)['rubbers'] ?? [])
                    ->first(fn ($rubber) => (int) $rubber['sequence'] === (int) $fixture->rubber_sequence);
                $type = $template['label'] ?? $fixture->rubber_code ?? match ((int) $fixture->fixture_type) {
                    2 => 'Doubles', 3 => 'Mixed doubles', 4 => 'Reverse singles', default => 'Singles',
                };
                return $row + ['lineup' => $fixture->lineup_display, 'teams' => $fixture->tie_display,
                    'match_type' => ucwords(str_replace('_', ' ', $type)), 'stage' => null];
            }
            foreach ([1, 2] as $side) {
                $registration = $fixture?->{'registration'.$side};
                if ($registration && !$registration->categoryEvents->contains(fn ($category) => (int) $category->event_id === (int) $event->id)) {
                    $row['participants'][$side - 1] = 'Assigned registration is outside this event';
                }
            }
            return $row + ['lineup' => [], 'teams' => [], 'match_type' => 'Individual', 'stage' => $fixture?->stage,
                'round_label' => $fixture?->round];
        });
        $matches = new LengthAwarePaginator($pageRows, $ordered->count(), 100, $page,
            ['path' => request()->url(), 'query' => request()->query()]);
        return compact('draws', 'counts', 'matches');
    }
}
