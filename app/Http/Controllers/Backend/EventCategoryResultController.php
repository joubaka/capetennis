<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Event;
use App\Models\CategoryEvent;
use App\Models\CategoryResult;
use App\Models\CategoryEventRegistration;
use Illuminate\Validation\ValidationException;

class EventCategoryResultController extends Controller
{
  

  public function store(
    Request $request,
    Event $event,
    CategoryEvent $categoryEvent
  ) {
    $this->authorize('event.manage', $event);

    if ((int) $categoryEvent->event_id !== (int) $event->id) {
      abort(404);
    }

    $request->validate([
      'positions' => ['present', 'array'],
      'positions.*.registration_id' => ['required', 'integer', 'distinct'],
      'positions.*.position' => ['required', 'integer', 'min:1', 'distinct'],
    ]);

    $submittedIds = collect($request->positions)->pluck('registration_id')->map(fn ($id) => (int) $id);
    $submittedPositions = collect($request->positions)->pluck('position')->map(fn ($position) => (int) $position)->sort()->values();
    $expectedPositions = collect(range(1, $submittedPositions->count()));

    if ($submittedPositions->isNotEmpty() && $submittedPositions->all() !== $expectedPositions->all()) {
      throw ValidationException::withMessages([
        'positions' => 'Final positions must be consecutive and start at 1.',
      ]);
    }

    $activeRegistrationIds = CategoryEventRegistration::where('category_event_id', $categoryEvent->id)
      ->where('status', '!=', 'withdrawn')
      ->whereIn('registration_id', $submittedIds)
      ->pluck('registration_id')
      ->map(fn ($id) => (int) $id);

    if ($submittedIds->diff($activeRegistrationIds)->isNotEmpty()) {
      throw ValidationException::withMessages([
        'positions' => 'Every positioned registration must be active in this event category.',
      ]);
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
      if ($rows !== []) {
        DB::table('category_results')->insert($rows);
      }
    });

    return response()->json([
      'status' => 'ok',
      'message' => 'Final positions saved',
    ]);
  }

}
