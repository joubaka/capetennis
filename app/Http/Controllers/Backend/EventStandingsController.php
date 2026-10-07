<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventStandingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Audit\AuditWriter;

class EventStandingsController extends Controller
{
    public function publication(Request $request, Event $event)
    {
        $this->authorize('team-draw.createFormat', $event);
        abort_unless($event->isTeam(), 404);
        $validated = $request->validate(['standings_published' => ['required', 'boolean']]);
        $published = (bool) $validated['standings_published'];
        DB::transaction(function () use ($event, $published): void {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ((bool) $locked->standings_published === $published) return;
            $before = (bool) $locked->standings_published;
            $locked->standings_published = $published;
            $locked->save();
            app(AuditWriter::class)->record([
                'category' => 'publication', 'action' => 'event.standings.publication.changed',
                'subject' => $locked, 'event_id' => $locked->id,
                'before' => ['standings_published' => $before],
                'after' => ['standings_published' => $published],
            ], true);
        });

        return $request->expectsJson()
            ? response()->json(['success' => true, 'standings_published' => $published])
            : redirect()->route('admin.events.standings', $event)->with('success', $published ? 'Standings published.' : 'Standings unpublished.');
    }

    public function show(Request $request, Event $event, EventStandingsService $service)
    {
        $this->authorize('event-draw.view', $event);
        abort_unless($event->isTeam(), 404);
        $filters = $request->validate(['gender' => 'nullable|string|max:50', 'age' => 'nullable|string|max:50', 'category' => 'nullable|string|max:255']);

        return view('backend.event.standings', ['event' => $event] + $service->forEvent($event, $filters));
    }
}
