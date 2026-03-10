<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\TeamFixture;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamFixtureFrontendController extends Controller
{
  public function index($draw)
  {
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

    $drawModel = \App\Models\Draw::find($draw);

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
    $drawModel = \App\Models\Draw::findOrFail($draw);

    // Round-Robin draws use the Fixture model, not TeamFixture
    $rrCount = \App\Models\Fixture::where('draw_id', $draw)->count();
    if ($rrCount > 0) {
      $fixtures = \App\Models\Fixture::where('draw_id', $draw)
          ->with([
            'registration1.players',
            'registration2.players',
            'fixtureResults',
          ])
          ->orderBy('id')
          ->get();

      return view('frontend.fixtures.enter-score-rr', [
        'draw'     => $drawModel,
        'fixtures' => $fixtures,
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

    return view('frontend.fixtures.enter-score', compact('fixtures'));
  }

  public function storeScore(Request $request, $fixtureId)
  {
      $request->validate([
          'set1_home' => 'required|integer|min:0',
          'set1_away' => 'required|integer|min:0',
      ]);

      $fixture = \App\Models\TeamFixture::findOrFail($fixtureId);

      for ($i = 1; $i <= 3; $i++) {
          if ($request->filled("set{$i}_home") && $request->filled("set{$i}_away")) {
              $fixture->fixtureResults()->updateOrCreate(
                  ['set_nr' => $i],
                  [
                      'team1_score' => $request->input("set{$i}_home"),
                      'team2_score' => $request->input("set{$i}_away"),
                  ]
              );
          }
      }

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
      $fixture->fixtureResults()->delete();

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
      $fixtures = \App\Models\TeamFixture::where('venue_id', $venueId)
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
