<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Event;
use App\Models\CategoryEvent;
use App\Models\CategoryResult;
use App\Models\CategoryEventRegistration;

class EventCategoryResultController extends Controller
{
  

  public function store(
    Request $request,
    Event $event,
    CategoryEvent $categoryEvent
  ) {
    $request->validate([
      'positions' => ['present', 'array'],
      'positions.*.registration_id' => ['required', 'integer'],
      'positions.*.position' => ['required', 'integer', 'min:1'],
    ]);

    // Handle empty positions array (no players in category)
    if (empty($request->positions)) {
      return response()->json([
        'status' => 'ok',
        'message' => 'No positions to save (empty category)',
      ]);
    }

    // Check for duplicate positions in request
    $positions = collect($request->positions)->pluck('position');
    if ($positions->count() !== $positions->unique()->count()) {
      return response()->json([
        'status' => 'error',
        'message' => 'Duplicate positions detected',
      ], 422);
    }

    // Get IDs of withdrawn registrations so they are excluded from results/points
    $withdrawnIds = CategoryEventRegistration::where('category_event_id', $categoryEvent->id)
      ->where('status', 'withdrawn')
      ->pluck('registration_id')
      ->flip();

    $rows = collect($request->positions)
      ->reject(fn($row) => isset($withdrawnIds[$row['registration_id']]))
      ->map(fn($row) => [
        'event_id' => $event->id,
        'category_id' => $categoryEvent->category_id,
        'registration_id' => $row['registration_id'],
        'position' => $row['position'],
        'updated_at' => now(),
        'created_at' => now(),
      ])->values()->all();

    DB::transaction(function () use ($event, $categoryEvent, $rows) {
      // 🧹 Delete old results for this event+category first (clean slate)
      DB::table('category_results')
        ->where('event_id', $event->id)
        ->where('category_id', $categoryEvent->category_id)
        ->delete();

      // 🔒 Insert fresh results
      DB::table('category_results')->insert($rows);
    });

    return response()->json([
      'status' => 'ok',
      'message' => 'Final positions saved',
    ]);
  }

}
