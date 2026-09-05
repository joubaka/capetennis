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
      if (! $draw->published) {
        $this->authorize('view', $draw);
        return redirect()->route('flexible-monrad.show', $draw);
      }
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
  
    return view('frontend.roundrobin.show', [
      'draw' => $draw,
      'svg' => $svgData,
      'groupsJson' => $groupsJson,
      'rrFixtures' => $hub['rrFixtures'],
      'oops' => $hub['oops'],
      'standings' => $hub['standings'],
    ]);
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

      return view('backend.draw.roundrobin.draw-svg', [
        'draw' => $draw,
        'svg' => $svgData,
      ]);
    }

    $engine = new \App\Services\DynamicBracketEngine($draw);
    $svgData = $engine->build();

    return view('backend.draw.roundrobin.dynamic-bracket-svg', [
      'draw' => $draw,
      'svgData' => $svgData,
      'emptyBracket' => $isEmpty,
    ]);
  }
}
