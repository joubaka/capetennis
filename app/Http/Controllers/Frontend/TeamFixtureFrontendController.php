<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\TeamFixture;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Draw;
use App\Models\Event;
use App\Models\DrawAuditLog;
use App\Services\TeamFixtureScoreService;

class TeamFixtureFrontendController extends Controller
{
  public function index($draw)
  {
    $drawModel = \App\Models\Draw::findOrFail($draw);
    app(\App\Services\PublicTournamentVisibility::class)->ensureEventIsVisible($drawModel->event, auth()->user());
    app(\App\Services\PublicTournamentVisibility::class)->ensureDrawIsVisible($drawModel, auth()->user());

    $isPrivileged = auth()->user()?->can('view', $drawModel) ?? false;

    // Block unpublished draws — only admin/super-user/convenor may view
    if (!$drawModel->published) {
      $user = auth()->user();
      $isPrivileged = $user && $user->can('view', $drawModel);
      if (!$isPrivileged) {
        abort(403, 'This draw has not been published yet.');
      }
    }

    if ($drawModel->usesFlexibleMonrad()) {
      return redirect()->route($drawModel->published ? 'public.flexible-monrad.show' : 'flexible-monrad.show', $drawModel);
    }

    $fixtures = \App\Models\TeamFixture::with([
            'draw',
            'venue',
            'fixtureResults',
            'fixturePlayers.player1',
            'fixturePlayers.player2',
            'region1Name',
            'region2Name'
        ])
        ->where('draw_id', $draw)
        ->when(!$isPrivileged, fn ($query) => $query->publishedTeamTies())
        ->orderBy('scheduled_at')
        ->orderBy('home_rank_nr')
        ->get();

    if (! $isPrivileged) {
      app(\App\Services\Scheduling\SchedulePublicationService::class)->projectFixtures($fixtures);
      $fixtures = $fixtures->sortBy(fn ($fixture) => $fixture->scheduled_at ?? '9999-12-31')->values();
    }

    app(\App\Services\TeamFixtureLineupPresenter::class)->prepare($fixtures);

    // Group fixtures by day
    $fixturesByDay = $fixtures->groupBy(function($fx) {
        return $fx->scheduled_at ? Carbon::parse($fx->scheduled_at)->toDateString() : 'Schedule to be announced';
    });

    return view('frontend.fixtures.team-fixtures', [
        'fixtures' => $fixtures,
        'fixturesByDay' => $fixturesByDay,
        'draw' => $drawModel,
    ]);
  }

  public function enterScores($draw)
  {
    $drawModel = Draw::findOrFail($draw);

    // Round-Robin draws use the Fixture model, not TeamFixture
    $rrCount = \App\Models\Fixture::where('draw_id', $draw)->count();
    if ($rrCount > 0) {
      $this->authorize('event.score', $drawModel->event);

      return redirect()->route('frontend.scoring.workspace', [
        'event' => $drawModel->event_id,
        'draw' => $drawModel->id,
      ]);
    }

    // Team-based draws use TeamFixture
    $fixtures = \App\Models\TeamFixture::with([
            'draw',
            'venue',
            'fixtureResults',
            'fixturePlayers.player1',
            'fixturePlayers.player2',
            'region1Name',
            'region2Name',
        ])
        ->where('draw_id', $draw)
        ->orderBy('scheduled_at')
        ->orderBy('home_rank_nr')
        ->get();

    $fixtures = $this->authorizedScoringFixtures($fixtures, $drawModel);

    return view('frontend.fixtures.enter-score', compact('fixtures'));
  }

  public function storeScore(Request $request, $fixtureId)
  {
      $fixture = \App\Models\TeamFixture::findOrFail($fixtureId);
      $this->authorize('team-fixture.saveScore', $fixture);
      $previousSets = $fixture->fixtureResults()->orderBy('set_nr')->get()
          ->map(fn ($result) => [(int) $result->team1_score, (int) $result->team2_score])->values()->all();

      $rules = [];
      for ($i = 1; $i <= 3; $i++) {
          $required = $i === 1 ? 'required' : 'nullable';
          $rules["set{$i}_home"] = "{$required}|required_with:set{$i}_away|integer|min:0";
          $rules["set{$i}_away"] = "{$required}|required_with:set{$i}_home|integer|min:0";
      }
      $rules['participant_revision'] = 'nullable|string|size:64';
    $validated = $request->validate($rules);

      app(TeamFixtureScoreService::class)->save($fixture, $validated);
      DrawAuditLog::record($fixture->draw_id, $previousSets ? 'score_corrected' : 'score_saved', $fixture->id, [
          'fixture_type' => 'team',
          'previous_sets' => $previousSets,
          'sets' => collect(range(1, 3))->map(fn ($set) => [
              $validated["set{$set}_home"] ?? null,
              $validated["set{$set}_away"] ?? null,
          ])->filter(fn ($set) => $set[0] !== null && $set[1] !== null)->values()->all(),
          'venue_id' => $fixture->venue_id,
      ]);

      $fixture->refresh();
      if (!$request->expectsJson()) { return back()->with('success', 'Scores saved.'); }

      // Prepare updated result HTML
      $resultHtml = view('frontend.fixtures.partials.result', ['fixture' => $fixture])->render();

      // Determine winner/loser for classes
      $fixture->load('fixtureResults', 'teamResults');
      $winner = $fixture->winnerSide();

      $actionsHtml = $this->scoreActions($fixture);

      return response()->json([
          'success' => true,
          'html' => $resultHtml,
          'winner' => $winner,
          'actionsHtml' => $actionsHtml,
      ]);
  }

  public function deleteScore(Request $request, $fixtureId)
  {
      $fixture = \App\Models\TeamFixture::findOrFail($fixtureId);
      $this->authorize('team-fixture.saveScore', $fixture);
      $previousSets = $fixture->fixtureResults()->orderBy('set_nr')->get()
          ->map(fn ($result) => [(int) $result->team1_score, (int) $result->team2_score])->values()->all();
      app(TeamFixtureScoreService::class)->delete($fixture);
      DrawAuditLog::record($fixture->draw_id, 'score_deleted', $fixture->id, [
          'fixture_type' => 'team',
          'previous_sets' => $previousSets,
          'venue_id' => $fixture->venue_id,
      ]);

      if (!$request->expectsJson()) { return back()->with('success', 'Scores deleted.'); }

      // Prepare updated result HTML
      $resultHtml = '<span class="text-muted">No result</span>';

      $fixture->refresh();
      $actionsHtml = $this->scoreActions($fixture);

      return response()->json([
          'success' => true,
          'html' => $resultHtml,
          'winner' => null,
          'actionsHtml' => $actionsHtml,
      ]);
  }

  public function venueFixtures($venueId)
  {
      $venue = \App\Models\Venue::findOrFail($venueId);
      $user = auth()->user();
      $superUser = $user->hasRole('super-user');
      $eventIds = collect();
      if (!$superUser) {
          $eventIds = collect(DB::table('event_admins')->where('user_id', $user->id)->pluck('event_id'))
              ->merge(\App\Models\EventConvenor::where('user_id', $user->id)->active()->pluck('event_id'))
              ->unique()->filter(function ($eventId) use ($user, $venueId) {
                  if ($user->is_event_score_keeper($eventId)) {
                      return $user->canScoreVenue((int) $eventId, (int) $venueId);
                  }
                  return $user->is_event_admin($eventId) || $user->is_convenor($eventId);
              })->values();
          abort_if($eventIds->isEmpty(), 403);
      }

      $fixtures = \App\Models\TeamFixture::where('venue_id', $venueId)
          ->when(!$superUser, fn ($query) => $query->whereHas('draw', fn ($draw) => $draw->whereIn('event_id', $eventIds)))
          ->with(['fixtureResults', 'homeTeam', 'awayTeam'])
          ->orderBy('scheduled_at')
          ->get();
      if (!$superUser) {
          $fixtures = $fixtures->filter(fn ($fixture) => !$user->is_event_score_keeper($fixture->draw->event_id)
              || $user->can('team-fixture.saveScore', $fixture))->values();
      }

      return view('frontend.fixtures.venue-fixtures', compact('venue', 'fixtures'));
  }

    /**
     * Convenor: Enter scores for all fixtures at a given event and venue.
     * Shows the same enter-score view but filters fixtures to the provided event and venue.
     */
    public function enterScoresByEventVenue($eventId, $venueId)
    {
        $event = Event::findOrFail($eventId);
        abort_unless(auth()->user()->hasRole('super-user') || auth()->user()->is_event_admin($event->id)
            || auth()->user()->is_convenor($event->id), 403);
        if (auth()->user()->is_event_score_keeper($event->id) && !auth()->user()->hasRole('super-user')) {
            abort_unless(auth()->user()->canScoreVenue($event->id, (int) $venueId), 403);
        }
        $fixtures = \App\Models\TeamFixture::with(['fixtureResults', 'homeTeam', 'awayTeam', 'draw'])
            ->where('venue_id', $venueId)
            ->whereHas('draw', function($q) use ($eventId) {
                $q->where('event_id', $eventId);
            })
            ->orderBy('scheduled_at')
            ->orderBy('round_nr')
            ->orderBy('home_rank_nr')
            ->get();
        if (auth()->user()->is_event_score_keeper($event->id) && !auth()->user()->hasRole('super-user')) {
            $fixtures = $fixtures->filter(fn ($fixture) => auth()->user()->can('team-fixture.saveScore', $fixture))->values();
        }

        return view('frontend.fixtures.enter-score', compact('fixtures'));
    }

    private function authorizedScoringFixtures($fixtures, Draw $draw)
    {
        $user = auth()->user();
        if ($user->is_event_score_keeper($draw->event_id) && !$user->hasRole('super-user')) {
            $fixtures = $fixtures->filter(fn ($fixture) => $user->can('team-fixture.saveScore', $fixture))->values();
            abort_if($fixtures->isEmpty(), 403);
        } else {
            $this->authorize('team-fixture.saveScore', $draw);
        }

        return $fixtures;
    }

    private function scoreActions(TeamFixture $fixture): string
    {
        $fixture->loadMissing('fixturePlayers.player1', 'fixturePlayers.player2', 'fixturePlayers.noProfile1',
            'fixturePlayers.noProfile2', 'region1Name', 'region2Name');
        $labels = [];
        foreach (['home' => ['player1', 'noProfile1', 'region1Name'], 'away' => ['player2', 'noProfile2', 'region2Name']] as $side => [$profile, $imported, $region]) {
            $names = [];
            foreach ($fixture->fixturePlayers as $slot) {
                $player = $slot->$profile ?? $slot->$imported;
                if ($player) {
                    $name = trim($player->name.' '.$player->surname);
                    if ($fixture->$region?->short_name) {
                        $name .= ' ('.$fixture->$region->short_name.')';
                    }
                    $names[] = $name;
                }
            }
            $labels[$side.'Label'] = $names ? implode(' + ', $names) : 'TBD';
        }

        return view('frontend.fixtures.partials.actions', ['fixture' => $fixture] + $labels)->render();
    }

}
