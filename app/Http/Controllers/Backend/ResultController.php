<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Position;
use App\Models\Registration;
use Illuminate\Http\Request;

class ResultController extends Controller
{
  public function resetPositions(Request $request)
  {
    $categoryId = $request->input('category_event_id');

    Position::where('category_event_id', $categoryId)->delete();

    return response()->json(['status' => 'deleted']);
  }
  public function saveOrder(Request $request, $id)
  {

    $categoryEvent = CategoryEvent::find($id);
    Position::where('category_event_id', $categoryEvent->id)->delete();

    $order = $request->order;

    foreach ($order as $key => $value) {
      $position = new Position();
      $position->category_event_id = $categoryEvent->id;
      $position->player_id = $value;
      $position->position = ($key + 1);
      if (is_null($request->rrscore)) {
      } else {
        $position->round_robin_score = $request->rrscore[$key];
      }


      $position->save();
    }
  }

  public function show($id)
  {
    $event = Event::find($id);

    return view('frontend.event.results.show_results', compact('event'));
  }

  public function publishResults($id)
  {
    $event = Event::find($id);
    if ($event->results_published == 1) {
      $event->results_published = 2;
      $event->save();
    } else {
      $event->results_published = 1;
      $event->save();
    }
    return 'published';
  }
}
