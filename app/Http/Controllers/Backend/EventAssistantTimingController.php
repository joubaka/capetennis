<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Domain\Draws\Enums\FixtureState;
use App\Models\{AssistantTimingObservation, Draw, DrawAuditLog, Event, Fixture, TeamFixture};
use App\Services\Scheduling\MatchTimingService;
use Carbon\Carbon;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{DB, Schema};
use SportStack\Assistant\{DurationEstimator, ProposalFingerprint};

class EventAssistantTimingController extends Controller
{
    public function matches(Request $request, Event $event): JsonResponse
    {
        $this->guard($event);
        $data = $request->validate(['draw_id' => ['required', 'integer', 'min:1']]);
        $draw = $event->draws()->findOrFail($data['draw_id']);
        $kind = $draw->isTeamDraw() ? 'team' : 'individual';
        $class = $kind === 'team' ? TeamFixture::class : Fixture::class;
        $round = $kind === 'team' ? 'round_nr' : 'round';
        $rows = $class::withoutEagerLoads()->where('draw_id', $draw->id)
            ->whereIn('match_status', [FixtureState::STATUS_COMPLETED, FixtureState::STATUS_BYE, FixtureState::STATUS_RETIRED])
            ->orderBy('match_nr')->limit(201)->get(['id', 'match_nr', $round]);
        abort_if($rows->count() > 200, 422, 'Use the draw workspace to review a smaller set of completed matches.');

        return response()->json(['fixture_kind' => $kind, 'matches' => $rows->map(fn ($row) => ['id' => $row->id,
            'label' => $draw->drawName.' · match '.$row->match_nr.' · round '.$row->{$round}])->all()]);
    }

    public function store(Request $request, Event $event): JsonResponse
    {
        $this->guard($event);
        $data = $request->validate([
            'fixture_kind' => ['required', 'in:individual,team'], 'fixture_id' => ['required', 'integer', 'min:1'],
            'actual_start' => ['required', 'date', 'before_or_equal:now'],
            'actual_finish' => ['required', 'date', 'after:actual_start', 'before_or_equal:now'],
            'verified' => ['required', 'accepted'], 'interrupted' => ['required', 'boolean'],
        ]);
        $class = $data['fixture_kind'] === 'team' ? TeamFixture::class : Fixture::class;
        $fixture = $class::withoutEagerLoads()->whereKey($data['fixture_id'])->whereHas('draw', fn ($query) => $query->where('event_id', $event->id))->firstOrFail();
        $fixture->loadMissing('draw.settings');
        $start = Carbon::parse($data['actual_start'], config('app.timezone'));
        $finish = Carbon::parse($data['actual_finish'], config('app.timezone'));
        if ($event->start_date && $event->end_date) {
            abort_if($start->lt(Carbon::parse($event->start_date)->startOfDay()) || $finish->gt(Carbon::parse($event->end_date)->endOfDay()), 422, 'Record actual times within this event’s dates.');
        }
        $minutes = (int) ceil($start->diffInMinutes($finish));
        abort_if($minutes < 5 || $minutes > 360, 422, 'Record an actual playing interval between 5 and 360 minutes.');
        abort_unless(in_array((int) $fixture->match_status, [FixtureState::STATUS_COMPLETED, FixtureState::STATUS_BYE, FixtureState::STATUS_RETIRED], true), 422, 'Record timing after a match has a recorded completion state.');
        $cohort = $this->cohort($fixture->draw, $data['fixture_kind'], $fixture);
        DB::transaction(function () use ($fixture, $request, $event, $data, $start, $finish, $minutes, $cohort) {
            // The explicit fixture kind prevents individual/team identifier collisions.
            AssistantTimingObservation::updateOrCreate([
                'event_id' => $event->id, 'fixture_kind' => $data['fixture_kind'], 'fixture_id' => $fixture->id,
            ], $cohort + [
                'draw_id' => $fixture->draw_id, 'verified_by' => $request->user()->id,
                'actual_start' => $start, 'actual_finish' => $finish, 'actual_minutes' => $minutes,
                'verified' => true, 'interrupted' => $data['interrupted'],
                'walkover' => (int) $fixture->match_status === 3,
                'retired' => (int) $fixture->match_status === 4,
            ]);
            DrawAuditLog::record($fixture->draw_id, 'assistant_timing_verified', $fixture->id, [
                'event_id' => $event->id, 'fixture_kind' => $data['fixture_kind'],
                'actual_minutes' => $minutes, 'interrupted' => $data['interrupted'],
            ]);
        });

        return response()->json(['message' => 'Verified timing recorded. Match scores, bookings and publication are unchanged.']);
    }

    public function suggestion(Request $request, Event $event, DurationEstimator $estimator, MatchTimingService $timing): JsonResponse
    {
        $this->guard($event);
        $data = $request->validate(['draw_id' => ['required', 'integer', 'min:1']]);
        $draw = $event->draws()->with('settings')->findOrFail($data['draw_id']);
        $preferences = \App\Models\EventAssistantPreference::where('event_id', $event->id)->where('user_id', $request->user()->id)->first();
        $fallback = (int) ($preferences?->preferences['duration'] ?? 60);
        if ($fallback < 15 || $fallback > 240) $fallback = 60;
        if ($draw->isTeamDraw()) {
            return response()->json(['source' => 'configured_default', 'minutes' => $fallback, 'samples' => 0, 'message' => 'Team rubber timings need separate scoring cohorts. Use the configured timing in the team scheduling workspace.']);
        }
        if ($draw->settings?->score_format === 'timed') {
            return response()->json(['source' => 'timed_match_rule', 'minutes' => $timing->forDraw($draw)['duration_minutes'], 'samples' => 0, 'message' => 'Timed matches use their configured playing time.']);
        }
        $cohort = $this->cohort($draw, 'individual');
        $observations = AssistantTimingObservation::where('event_id', $event->id)->where($cohort)
            ->where('verified', true)->where('interrupted', false)->where('retired', false)->where('walkover', false)
            ->orderByDesc('id')->limit(2000)->get()->toArray();
        $estimate = $estimator->estimate($observations, $cohort['format'], $cohort['age_group'], $fallback);

        return response()->json($estimate + ['message' => $estimate['source'] === 'configured_default'
            ? 'Not enough verified comparable timings yet. Keep the configured default.'
            : 'Suggestion from the 80th percentile of verified comparable match timings. Review before using; this has not been validated on held-out tournaments.']);
    }

    private function cohort(Draw $draw, string $kind, Fixture|TeamFixture|null $fixture = null): array
    {
        $settings = $draw->settings;

        return ['format' => ProposalFingerprint::make([
            'kind' => $kind, 'discipline' => $draw->drawType_id,
            'score_format' => $settings?->score_format, 'num_sets' => $settings?->num_sets,
            'full_sets' => $settings?->require_full_sets, 'preset' => $settings?->preset_key,
            'rubber' => $fixture instanceof TeamFixture ? $fixture->rubber_code : null,
        ]), 'age_group' => $draw->category_event_id ? 'category:'.$draw->category_event_id : 'draw:'.$draw->id];
    }

    private function guard(Event $event): void
    {
        abort_unless(auth()->user()?->hasRole('super-user'), 403);
        $this->authorize('event.manage', $event);
        abort_unless(config('event-assistant.enabled') && Schema::hasTable('event_assistant_timing_observations'), 503, 'Timing learning needs its reviewed local database setup.');
    }
}
