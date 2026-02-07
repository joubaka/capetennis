<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\CategoryEvent;
use App\Models\Registration;

class EventResultsController extends Controller
{
  public function individual(Event $event)
  {
    // Load category events for this event
    $categories = CategoryEvent::where('event_id', $event->id)
      ->get()
      ->map(function ($category) use ($event) {

        // Load registrations for this category
        $registrations = Registration::whereHas(
          'categoryEvents',
          fn($q) => $q->where('category_events.id', $category->id)
        )
          ->leftJoin('category_results as cr', function ($join) use ($event, $category) {
          $join->on('registrations.id', '=', 'cr.registration_id')
            ->where('cr.event_id', $event->id)
            ->where('cr.category_id', $category->id);
        })
          ->select('registrations.*', 'cr.position')
          ->orderByRaw('cr.position IS NULL') // saved results first
          ->orderBy('cr.position')            // then by position
          ->orderBy('registrations.id')       // stable fallback
          ->get();

        // Inject registrations relation manually
        $category->setRelation('registrations', $registrations);

        return $category;
      });

    return view(
      'backend.event.results.individual',
      compact('event', 'categories')
    );
  }
}
