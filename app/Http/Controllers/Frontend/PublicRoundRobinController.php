<?php

namespace App\Http\Controllers\Frontend;

use App\Models\Draw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\InterproDrawBuilder;
use App\Models\CategoryEvent;
use App\Http\Controllers\Controller;

class PublicRoundRobinController extends Controller
{
  protected InterproDrawBuilder $builder;

  public function __construct(InterproDrawBuilder $builder)
  {
    $this->builder = $builder;
  }

  // =============================================================
  // PUBLIC SHOW
  // =============================================================
  public function show(Draw $draw)
  {
    
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

    // Ensure fixtures exist
    if ($draw->drawFixtures->isEmpty()) {
      Log::warning("🌍 [PUBLIC RR] Missing fixtures — regenerating");
      $this->builder->regenerateRoundRobinFixtures($draw);
      $draw->load('drawFixtures');
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
}
