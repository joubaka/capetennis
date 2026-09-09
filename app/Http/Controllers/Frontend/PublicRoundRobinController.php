<?php

namespace App\Http\Controllers\Frontend;

use App\Models\Draw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\DrawService;
use App\Models\CategoryEvent;
use App\Http\Controllers\Controller;
use App\Services\PublicDrawScheduleVisibility;
use App\Services\PublicTournamentVisibility;

class PublicRoundRobinController extends Controller
{
  protected DrawService $builder;

  public function __construct(DrawService $builder)
  {
    $this->builder = $builder;
  }

  // =============================================================
  // PUBLIC SHOW
  // =============================================================
  public function show(Draw $draw)
  {
    app(PublicTournamentVisibility::class)->ensureDrawIsVisible($draw, auth()->user());
    if ($draw->usesFlexibleMonrad()) {
      return redirect()->route('public.flexible-monrad.show', $draw);
    }
    Log::info("🌍 [PUBLIC RR] Loading draw {$draw->id}", [
      'event_id' => $draw->event_id,
      'type' => $draw->event->eventType ?? null
    ]);

    // Minimal load
    $draw->load([
      'event',
      'categoryEvent.category',
     
      'groups.groupRegistrations.registration.players',
      'drawFixtures.registration1.players',
      'drawFixtures.registration2.players',
      'drawFixtures.fixtureResults',
      'drawFixtures.schedule',
    ]);

    // Public reads must never create or regenerate tournament state.
    if ($draw->drawFixtures->isEmpty()) {
      Log::warning("🌍 [PUBLIC RR] Published draw has no fixtures", [
        'draw_id' => $draw->id,
      ]);
    }

    // Hub (RR fixtures, OOP, standings)
    $hub = $this->builder->loadRoundRobinHub($draw);
    $hub = app(PublicDrawScheduleVisibility::class)->restrictRoundRobinHub($draw, $hub);

    // Round-robin-only draws do not have a playoff bracket.
    $svgData = null;
    if (! $draw->isRoundRobinOnly()) {
      $engine = new \App\Services\BracketEngine($draw);
      $svgData = $engine->build();
    }

    // Prepare JSON data for JS
    $groupsJson = $draw->groups->map(function ($g) {
      return [
        'id' => $g->id,
        'name' => $g->name,
        'registrations' => $g->groupRegistrations->map(function ($gr) {
          $reg = $gr->registration;
          $player = $reg?->players?->first();

          return [
            'id' => $reg->id ?? null,
            'display_name' => $player?->full_name ?? 'Unknown',
            'seed' => $gr->seed ?? 9999
          ];
        })->values()
      ];
    });
  
    return response()->view('frontend.roundrobin.show', [
      'draw' => $draw,
      'svg' => $svgData,
      'groupsJson' => $groupsJson,
      'rrFixtures' => $hub['rrFixtures'],
      'oops' => $hub['oops'],
      'standings' => $hub['standings'],
    ])->withHeaders($this->freshDrawHeaders());
  }

  // =============================================================
  // PUBLIC BRACKET (AJAX - no auth required)
  // =============================================================
  public function mainBracket(Draw $draw)
  {
    abort_if($draw->isRoundRobinOnly(), 404);
    app(PublicTournamentVisibility::class)->ensureDrawIsVisible($draw, auth()->user());

    $eventType = $draw->event->eventType ?? null;
    $isEmpty = request()->boolean('empty');

    if ($eventType == 13) {
      $engine = new \App\Services\BracketEngine($draw);
      $svgData = $engine->build();

      return response()->view('backend.draw.roundrobin.draw-svg', [
        'draw' => $draw,
        'svg' => $svgData,
      ])->withHeaders($this->freshDrawHeaders());
    }

    $engine = new \App\Services\DynamicBracketEngine($draw);
    $svgData = $engine->build();

    return response()->view('backend.draw.roundrobin.dynamic-bracket-svg', [
      'draw' => $draw,
      'svgData' => $svgData,
      'emptyBracket' => $isEmpty,
    ])->withHeaders($this->freshDrawHeaders());
  }

  /**
   * Public tournament state changes during the event, so browsers and proxies
   * must revalidate the page and its asynchronously loaded playoff bracket.
   */
  private function freshDrawHeaders(): array
  {
    return [
      'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
      'Pragma' => 'no-cache',
      'Expires' => '0',
    ];
  }
}
