<?php

namespace App\Http\Controllers\Frontend;

use App\Services\Draw\CapeTennisDraw;
use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\Player;
use App\Models\TeamFixture;
use App\Models\Fixture;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\DrawService;
use App\Services\TeamFixtureScoreService;
use App\Services\PublicTournamentVisibility;


class FrontFixtureController extends Controller
{
  protected DrawService $builder;

  public function __construct(DrawService $builder)
  {
    $this->builder = $builder;
  }
  public function show($id)
  {
    $draw = Draw::with('event')->findOrFail($id);
    app(PublicTournamentVisibility::class)->ensureDrawIsVisible($draw, auth()->user());

    if ($draw->event?->isInterprovincialTrials()) {
      return $this->showInterproFixtures($draw);
    }

    return $this->showTeamFixtures($draw);
  }

  protected function showTeamFixtures(Draw $draw)
  {
    $fixtures = TeamFixture::with([
      'fixturePlayers.player1',
      'fixturePlayers.player2',
      'fixturePlayers',
      'fixtureResults',
      'venue',
      'region1Name',
      'region2Name',
      'draw'
    ])
      ->where('draw_id', $draw->id)
      ->when(!auth()->user()?->can('view', $draw), fn ($query) => $query->publishedTeamTies())
      ->orderBy('scheduled_at', 'asc')
      ->orderByRaw('CAST(round_nr AS UNSIGNED)')
      ->orderByRaw('CAST(tie_nr AS UNSIGNED)')
      ->orderByRaw('CAST(home_rank_nr AS UNSIGNED)')
      ->orderBy('match_nr')
      ->get();

    $this->hidePrivateSchedule($draw, $fixtures);
    app(\App\Services\TeamFixtureLineupPresenter::class)->prepare($fixtures);

    if (! auth()->user()?->can('view', $draw)) {
      $fixtures = $fixtures->sortBy(fn ($fixture) => $fixture->scheduled_at ?? '9999-12-31')->values();
    }

    if ($fixtures->isEmpty()) {
      return view('frontend.fixture.empty', ['draw' => $draw, 'event' => $draw->event]);
    }

    $data = [
      'fixtures' => $fixtures,
      'draw' => $draw,
      'event' => $draw->event,
    ];

    // Admin / Convenor
    if (auth()->check() && auth()->user()->is_convenor($draw->event_id)) {
      $data['players'] = Player::orderBy('name')->get();
      return view('backend.draw.team.draw-show-team', $data);
    }

    // Frontend
    return view('frontend.fixture.draw-fixtures-show-team', $data);
  }


  public function showInterproFixtures(Draw $draw)
  {
    // ---------------------------------------------
    // LOAD FIXTURES
    // ---------------------------------------------
    $fixtures = Fixture::with([
      'registration1.players',
      'registration2.players',
      'fixtureResults',
      'venue',
      'orderOfPlay'
    ])
      ->where('draw_id', $draw->id)
      ->orderByRaw('CAST(match_nr AS UNSIGNED)')
      ->get();

    $this->hidePrivateSchedule($draw, $fixtures);

    if ($fixtures->isEmpty()) {
      return view('frontend.fixture.empty', ['draw' => $draw, 'event' => $draw->event]);
    }

    // Log fixture details for debugging
    foreach ($fixtures as $fx) {
        \Log::debug('Fixture debug', [
            'fixture_id' => $fx->id,
            'team1' => $fx->team1 ? $fx->team1->pluck('full_name')->toArray() : [],
            'team1NoProfile' => $fx->team1NoProfile ? $fx->team1NoProfile->map(fn($np) => $np->name . ' ' . $np->surname)->toArray() : [],
            'team2' => $fx->team2 ? $fx->team2->pluck('full_name')->toArray() : [],
            'team2NoProfile' => $fx->team2NoProfile ? $fx->team2NoProfile->map(fn($np) => $np->name . ' ' . $np->surname)->toArray() : [],
        ]);
    }

    // ---------------------------------------------
    // LOAD HUB (fixtures + OOP + standings)
    // ---------------------------------------------
    $hub = $this->builder->loadRoundRobinHub($draw);

    // ---------------------------------------------
    // GROUPS JSON — MUST MATCH ADMIN
    // ---------------------------------------------
    $groupsJson = $draw->groups
      ->map(function ($g) {
        return [
          'id' => $g->id,
          'name' => $g->name,

          'registrations' => $g->groupRegistrations
            ->map(function ($gr) {
              $reg = $gr->registration;
              $player = $reg?->players?->first();

              return [
                'id' => $reg?->id,
                'display_name' => $player?->full_name ?? 'Unknown',
                'seed' => $gr->seed ?? 9999,
              ];
            })
            ->values(),
        ];
      })
      ->values();

    // ---------------------------------------------
    // VIEW OUTPUT
    // ---------------------------------------------
    $data = [
      'fixtures' => $fixtures,
      'draw' => $draw,
      'event' => $draw->event,

      // ✔ RR Data for JS
      'rrFixtures' => $hub['rrFixtures'],
      'groupsJson' => $groupsJson,   // ✔ FIXED (correct variable)
      'oops' => $hub['oops'],
      'standings' => $hub['standings'],
    ];

    // Convenor sees backend version
    if (auth()->check() && auth()->user()->is_convenor($draw->event_id)) {
      return view('backend.draw.individual.interproDrawConvenor', $data);
    }

    // Public view
    return view('backend.draw.individual.interproDraw', $data);
  }






  public function drawFixtures($id)
  {
    // Load draw + event
    $draw = Draw::with('event')->findOrFail($id);

    app(PublicTournamentVisibility::class)->ensureDrawIsVisible($draw, auth()->user());

    // Resolve the base format semantically so event type database ids can vary.
    $isTeamEvent = $draw->event?->isTeam()
      || (! $draw->event?->isIndividual() && (int) $draw->event?->eventType === 3);

    // ---------------------------------------------------------
    // FIXTURE LOADERS
    // ---------------------------------------------------------
    if ($isTeamEvent) {

      // TEAM FIXTURES
      $fixtures = TeamFixture::with([
        'team1',
        'team2',
        'fixtureResults',
        'venue',
        'region1Name',
        'region2Name'
      ])
        ->where('draw_id', $id)
        ->when(!auth()->user()?->can('view', $draw), fn ($query) => $query->publishedTeamTies())
        ->orderByRaw('CAST(round_nr AS UNSIGNED)')
        ->orderByRaw('CAST(tie_nr AS UNSIGNED)')
        ->orderBy('rubber_sequence')
        ->orderBy('match_nr')
        ->get();

    } else {

      // INDIVIDUAL FIXTURES
      $fixtures = Fixture::with([
        'registration1.players',
        'registration2.players',
        'fixtureResults',
        'venue',
        'orderOfPlay'
      ])
        ->where('draw_id', $id)
        ->orderByRaw('CAST(match_nr AS UNSIGNED)')
        ->get();

    }

    $this->hidePrivateSchedule($draw, $fixtures);
    if ($isTeamEvent) app(\App\Services\TeamFixtureLineupPresenter::class)->prepare($fixtures);
    
    // ---------------------------------------------------------
    // Empty fixtures
    // ---------------------------------------------------------
    if ($fixtures->isEmpty()) {
      return view('frontend.fixture.empty', ['draw' => $draw, 'event' => $draw->event]);
    }

    // ---------------------------------------------------------
    // Data
    // ---------------------------------------------------------
    $data = [
      'fixtures' => $fixtures,
      'draw' => $draw,
      'event' => $draw->event,
    ];

    // ---------------------------------------------------------
    // ADMIN / CONVENOR VIEW
    // ---------------------------------------------------------
    if (Auth::check()) {
      $user = auth()->user();
      $eventId = $draw->event?->id;

      if ($eventId && $user->is_convenor($eventId)) {

        if ($isTeamEvent) {
          $data['players'] = Player::orderBy('name')->get();
          return view('backend.draw.team.draw-show-team', $data);
        }

        // Individual admin view
        return view('backend.draw.individual.draw-show-individual', $data);
      }
    }

    // ---------------------------------------------------------
    // FRONTEND VIEWS
    // ---------------------------------------------------------
    if ($isTeamEvent) {
      return view('frontend.fixture.draw-fixtures-show-team', $data);
    }

    return view('frontend.fixture.draw-fixtures-show', $data);
  }

  public function drawFixturesRound($event, $var, $type)
  {
    $eventModel = Event::findOrFail($event);
    app(PublicTournamentVisibility::class)->ensureEventIsVisible($eventModel, auth()->user());
    $eventDraws = app(PublicTournamentVisibility::class)
      ->publishedDrawsFor($eventModel, scheduleRequired: true)
      ->pluck('id');

    $fixtures = TeamFixture::with([
      'draw', 'fixturePlayers.player1', 'fixturePlayers.player2',
      'fixtureResults', 'venue', 'region1Name', 'region2Name',
    ])
      ->whereIn('draw_id', $eventDraws)
      ->where(fn ($query) => $query->publishedTeamTies())
      ->when($type === 'tie', fn ($query) => $query->where('tie_nr', $var))
      ->when($type !== 'tie', fn ($query) => $query->where('round_nr', $var))
      ->orderBy('scheduled_at')
      ->get();

    abort_if($fixtures->isEmpty(), 404, 'No published matches found.');

    app(\App\Services\Scheduling\SchedulePublicationService::class)->projectFixtures($fixtures);
    $fixtures = $fixtures->sortBy(fn ($fixture) => $fixture->scheduled_at ?? '9999-12-31')->values();

    app(\App\Services\TeamFixtureLineupPresenter::class)->prepare($fixtures);

    return view('frontend.fixture.draw-fixtures-show-team', [
      'fixtures' => $fixtures,
      'draw' => $fixtures->first()->draw,
      'event' => $eventModel,
    ]);
  }

    public function bracketFixtures($id){
        $draw = Draw::with('event')->findOrFail($id);
        app(PublicTournamentVisibility::class)->ensureDrawIsVisible($draw, auth()->user());

        $data['draw'] = $draw;
        $data['event'] = $draw->event;
        $data['bracket'] = new CapeTennisDraw($id);
        return view('frontend.draw.fixtures.showFixtures',$data);

    }

  private function hidePrivateSchedule(Draw $draw, $fixtures): void
  {
    if (auth()->user()?->can('view', $draw)) {
      return;
    }

    app(\App\Services\Scheduling\SchedulePublicationService::class)->projectFixtures($fixtures);
  }

  public function saveScore(Request $request, TeamFixture $fixture)
  {
    $this->authorize('team-fixture.saveScore', $fixture);

    $rules = [];
    for ($i = 1; $i <= 3; $i++) {
      $rules["set{$i}_home"] = "nullable|required_with:set{$i}_away|integer|min:0";
      $rules["set{$i}_away"] = "nullable|required_with:set{$i}_home|integer|min:0";
    }

    $rules['participant_revision'] = 'nullable|string|size:64';
    $validated = $request->validate($rules);

    app(TeamFixtureScoreService::class)->save($fixture, $validated);

    if ($request->ajax()) {
      $fixture->load('fixtureResults', 'teamResults');
      $winner = $fixture->winnerSide();

      return response()->json([
        'success' => true,
        // this partial should render the scores in <td>
        'html' => view('frontend.fixture.partials.result-col', compact('fixture'))->render(),
        'winner' => $winner,
        'scores' => $fixture->fixtureResults->mapWithKeys(function ($r) {
          return [
            "set{$r->set_nr}_home" => $r->team1_score,
            "set{$r->set_nr}_away" => $r->team2_score,
          ];
        }),
      ]);
    }

    return redirect()
      ->back()
      ->with('success', 'Scores updated successfully.');
  }




}
