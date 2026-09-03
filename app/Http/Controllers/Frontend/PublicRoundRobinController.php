<?php

namespace App\Http\Controllers\Frontend;

use App\Models\Draw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\DrawService;
use App\Models\CategoryEvent;
use App\Http\Controllers\Controller;

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
    if ($draw->usesFlexibleMonrad()) {
      if (! $draw->published) {
        $this->authorize('view', $draw);
        return redirect()->route('flexible-monrad.show', $draw);
      }
      return redirect()->route('public.flexible-monrad.show', $draw);
    }
    // Block unpublished draws — only admin, super-user, or convenor for this event may view
    if (!$draw->published) {
      $user = auth()->user();
      $isPrivileged = $user && $user->can('view', $draw);

      if (!$isPrivileged) {
        abort(403, 'This draw has not been published yet.');
      }
    }

    Log::info("🌍 [PUBLIC RR] Loading draw {$draw->id}", [
      'event_id' => $draw->event_id,
      'type' => $draw->event->eventType ?? null
    ]);

    // Minimal load
    $draw->load([
      'event',
     
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

    // Bracket Engine (Main + Plate + Consolation)
    $engine = new \App\Services\BracketEngine($draw);
    $svgData = $engine->build();

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
    // Block unpublished draws — only admin, super-user, or convenor for this event may view
    if (!$draw->published) {
      $user = auth()->user();
      $isPrivileged = $user && $user->can('view', $draw);
      if (!$isPrivileged) {
        abort(403, 'This draw has not been published yet.');
      }
    }

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
