<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Event;
use App\Models\CategoryEvent;
use App\Models\CategoryResult;

class EventCategoryResultController extends Controller
{
  

  public function store(
    Request $request,
    Event $event,
    CategoryEvent $categoryEvent
  ) {
    $request->validate([
      'positions' => ['required', 'array'],
      'positions.*.registration_id' => ['required', 'integer'],
      'positions.*.position' => ['required', 'integer', 'min:1'],
    ]);

    $rows = collect($request->positions)->map(fn($row) => [
      'event_id' => $event->id,
      'category_id' => $categoryEvent->category_id,
      'registration_id' => $row['registration_id'],
      'position' => $row['position'],
      'updated_at' => now(),
      'created_at' => now(),
    ])->values()->all();

    // 🔒 Single atomic operation — NO deadlocks
    DB::table('category_results')->upsert(
      $rows,
      ['event_id', 'category_id', 'registration_id'],
      ['position', 'updated_at']
    );

    return response()->json([
      'status' => 'ok',
      'message' => 'Final positions saved',
    ]);
  }

}
