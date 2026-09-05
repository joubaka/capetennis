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
        ->orderBy('scheduled_at')
        ->orderBy('home_rank_nr')
        ->get();

    // Group fixtures by day
    $fixturesByDay = $fixtures->groupBy(function($fx) {
        return Carbon::parse($fx->scheduled_at)->toDateString();
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
    $this->authorize('team-fixture.saveScore', $drawModel);
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

      // Prepare updated result HTML
      $resultHtml = view('frontend.fixtures.partials.result', ['fixture' => $fixture])->render();

      // Determine winner/loser for classes
      $winner = null;
      $lastSet = $fixture->fixtureResults->last();
      if ($lastSet) {
          if ($lastSet->team1_score > $lastSet->team2_score) $winner = 'home';
          elseif ($lastSet->team2_score > $lastSet->team1_score) $winner = 'away';
          else $winner = 'draw';
      }

      $homeNames = [];
$awayNames = [];
$homeRegionShort = $fixture->region1Name?->short_name ?? null;
$awayRegionShort = $fixture->region2Name?->short_name ?? null;

// Populate $homeNames and $awayNames as in your Blade
if ($fixture->fixturePlayers) {
    foreach ($fixture->fixturePlayers as $player) {
        if ($player->player1) {
            $homeNames[] = $player->player1->name;
        }
        if ($player->player2) {
            $awayNames[] = $player->player2->name;
        }
    }
}

$homeLabel = count($homeNames) ? collect($homeNames)->implode(' + ') : 'TBD';
$awayLabel = count($awayNames) ? collect($awayNames)->implode(' + ') : 'TBD';

$actionsHtml = view('frontend.fixtures.partials.actions', [
    'fixture' => $fixture,
    'homeLabel' => $homeLabel,
    'awayLabel' => $awayLabel
])->render();

      return response()->json([
          'success' => true,
          'html' => $resultHtml,
          'winner' => $winner,
          'actionsHtml' => $actionsHtml,
      ]);
  }

  public function deleteScore($fixtureId)
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

      // Prepare updated result HTML
      $resultHtml = '<span class="text-muted">No result</span>';

      $homeNames = [];
$awayNames = [];
$homeRegionShort = $fixture->region1Name?->short_name ?? null;
$awayRegionShort = $fixture->region2Name?->short_name ?? null;

// Populate $homeNames and $awayNames as in your Blade
if ($fixture->fixturePlayers) {
    foreach ($fixture->fixturePlayers as $player) {
        if ($player->player1) {
            $homeNames[] = $player->player1->name;
        }
        if ($player->player2) {
            $awayNames[] = $player->player2->name;
        }
    }
}

$homeLabel = count($homeNames) ? collect($homeNames)->implode(' + ') : 'TBD';
$awayLabel = count($awayNames) ? collect($awayNames)->implode(' + ') : 'TBD';

$actionsHtml = view('frontend.fixtures.partials.actions', [
    'fixture' => $fixture,
    'homeLabel' => $homeLabel,
    'awayLabel' => $awayLabel
])->render();

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
      $eventIds = $user->hasRole('super-user')
          ? Event::query()->pluck('id')
          : collect(DB::table('event_admins')->where('user_id', $user->id)->pluck('event_id'))
              ->merge(DB::table('event_convenors')->where('user_id', $user->id)->pluck('event_id'))
              ->unique();

      $fixtures = \App\Models\TeamFixture::where('venue_id', $venueId)
          ->whereHas('draw', fn ($query) => $query->whereIn('event_id', $eventIds))
          ->with(['fixtureResults', 'homeTeam', 'awayTeam'])
          ->orderBy('scheduled_at')
          ->get();

      return view('frontend.fixtures.venue-fixtures', compact('venue', 'fixtures'));
  }

    /**
     * Convenor: Enter scores for all fixtures at a given event and venue.
     * Shows the same enter-score view but filters fixtures to the provided event and venue.
     */
    public function enterScoresByEventVenue($eventId, $venueId)
    {
        $event = Event::findOrFail($eventId);
        $this->authorize('event-draw.view', $event);
        $fixtures = \App\Models\TeamFixture::with(['fixtureResults', 'homeTeam', 'awayTeam', 'draw'])
            ->where('venue_id', $venueId)
            ->whereHas('draw', function($q) use ($eventId) {
                $q->where('event_id', $eventId);
            })
            ->orderBy('scheduled_at')
            ->orderBy('round_nr')
            ->orderBy('home_rank_nr')
            ->get();
   
        return view('frontend.fixtures.enter-score', compact('fixtures'));
    }
}
