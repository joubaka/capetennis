<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Position;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResultController extends Controller
{
  public function resetPositions(Request $request)
  {
    $validated = $request->validate([
      'category_event_id' => ['required', 'integer', 'exists:category_events,id'],
    ]);
    $category = CategoryEvent::with('event')->findOrFail($validated['category_event_id']);
    $this->authorize('event.manage', $category->event);

    Position::where('category_event_id', $category->id)->delete();

    return response()->json(['status' => 'deleted']);
  }
  public function saveOrder(Request $request, $id)
  {
    if (is_array($request->input('order.0'))) {
      $request->merge([
        'order' => collect($request->input('order', []))
          ->pluck('id')
          ->all(),
      ]);
    }

    $validated = $request->validate([
      'order' => ['required', 'array', 'min:1'],
      'order.*' => ['required', 'integer', 'distinct', 'exists:players,id'],
      'rrscore' => ['nullable', 'array'],
      'rrscore.*' => ['nullable', 'numeric'],
    ]);
    $categoryEvent = CategoryEvent::with('event')->findOrFail($id);
    $this->authorize('event.manage', $categoryEvent->event);

    $order = array_map('intval', $validated['order']);
    $registeredPlayers = DB::table('category_event_registrations as cer')
      ->join('player_registrations as pr', 'pr.registration_id', '=', 'cer.registration_id')
      ->where('cer.category_event_id', $categoryEvent->id)
      ->whereIn('pr.player_id', $order)
      ->pluck('pr.player_id')
      ->map(fn ($playerId) => (int) $playerId)
      ->unique()
      ->all();
    if (array_diff($order, $registeredPlayers)) {
      throw ValidationException::withMessages([
        'order' => 'Every positioned player must be registered in this event category.',
      ]);
    }

    DB::transaction(function () use ($categoryEvent, $order, $validated): void {
      Position::where('category_event_id', $categoryEvent->id)->delete();
      foreach ($order as $key => $playerId) {
        Position::create([
          'category_event_id' => $categoryEvent->id,
          'player_id' => $playerId,
          'position' => $key + 1,
          'round_robin_score' => $validated['rrscore'][$key] ?? null,
        ]);
      }
    });

    return response()->json(['status' => 'saved']);
  }

  public function show($id)
  {
    $event = Event::find($id);

    return view('frontend.event.results.show_results', compact('event'));
  }

  public function publishResults($id)
  {
    $event = Event::findOrFail($id);
    $this->authorize('event.manage', $event);
    $event->results_published = ! $event->results_published;
    $event->save();

    if (request()->ajax() || request()->wantsJson()) {
      return 'published';
    }

    return back()->with('success', $event->results_published ? 'Results published.' : 'Results unpublished.');
  }
}
