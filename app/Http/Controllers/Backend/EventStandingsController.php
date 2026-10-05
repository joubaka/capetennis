<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventStandingsService;
use Illuminate\Http\Request;

class EventStandingsController extends Controller
{
    public function show(Request $request, Event $event, EventStandingsService $service)
    {
        $this->authorize('event-draw.view', $event);
        abort_unless($event->isTeam(), 404);
        $filters = $request->validate(['gender' => 'nullable|string|max:50', 'age' => 'nullable|string|max:50', 'category' => 'nullable|string|max:255']);

        return view('backend.event.standings', ['event' => $event] + $service->forEvent($event, $filters));
    }
}
