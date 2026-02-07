<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\DrawFormats;
use Illuminate\Http\Request;

class CategoryEventController extends Controller
{
  public function manage($category_event_id)
  {
      $categoryEvent = CategoryEvent::with([
          'category',
          'draws.settings',
          'draws.groups.registrations.players',
          'draws.registrations.players',
          'draws.drawFormat',
          'registrations.players'
      ])->findOrFail($category_event_id);

      $eligibleRegistrations = $categoryEvent
          ->registrations
          ->filter(fn($reg) => $reg->draws->isEmpty());

      // ✅ Send all registrations
      $allRegistrations = $categoryEvent->registrations;

      $drawFormats = DrawFormats::all();

      return view('backend.categoryEvent.manage', compact(
          'categoryEvent',
          'eligibleRegistrations',
          'allRegistrations',
          'drawFormats'
      ));
  }

}
