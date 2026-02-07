<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\CategoryEvent;
use App\Models\DrawFormats;
use App\Models\DrawSetting;
use App\Models\Player;
use App\Models\Registration;
use Illuminate\Http\Request;
use App\Services\FeedInDrawService;

class ManageDrawController extends Controller
{
  public function index($id)
  {
    $draw = Draw::with([
      'settings',
      'players',
      'groups.registrations.players',
      'categoryEvent.category',
      'registrations.players'
    ])->findOrFail($id);

    $drawFormats = DrawFormats::all();

    // Get all draws within the same category_event (for drag/drop preview)
    $allDraws = Draw::with(['registrations.players', 'drawFormat'])
      ->where('category_event_id', $draw->category_event_id)
      ->orderBy('drawName')
      ->get();

    // Get eligible registrations NOT assigned to any draw in this category_event
    $eligibleRegistrations = Registration::whereHas('categoryEvents', function ($query) use ($draw) {
      $query->where('category_event_id', $draw->category_event_id);
    })->with(['players:id,name,surname'])
      ->get()
      ->filter(function ($registration) use ($allDraws) {
        // Exclude if already in any draw
        foreach ($allDraws as $d) {
          if ($d->registrations->contains($registration)) {
            return false;
          }
        }
        return true;
      });

    return view('backend.draw.manage', compact(
      'draw',
      'drawFormats',
      'eligibleRegistrations',
      'allDraws'
    ));
  }


  public function updateSettings(Request $request, Draw $draw)
  {
      $data = $request->validate([
          'draw_format_id' => 'required|exists:draw_formats,id',
          'draw_type_id' => 'required|exists:draw_types,id',
          'boxes' => 'required|integer',
          'playoff_size' => 'required|integer',
          'num_sets' => 'required|integer',
      ]);

      if (!$draw->settings) {
          $draw->settings()->create(array_merge($data, [
              'draw_id' => $draw->id
          ]));
      } else {
          $draw->settings()->update($data);
      }

      $settings = $draw->settings()->with(['drawFormat', 'drawType'])->first();

      return response()->json([
          'success' => true,
          'settings' => $settings
      ]);
  }




  // Add more functions as needed for tabs like:
  // assignPlayers(Request $request, Draw $draw)
  // createGroups(Request $request, Draw $draw)



  public function showFeedInDraw()
  {
      $svg = (new FeedInDrawService())->testMatchBox(); // Can be 16 or 32
      return view('draw.feedin', compact('svg'));
  }

}
