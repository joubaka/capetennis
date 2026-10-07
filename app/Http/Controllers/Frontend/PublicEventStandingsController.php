<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventStandingsService;
use App\Services\PublicTournamentVisibility;
use Illuminate\Http\Request;

final class PublicEventStandingsController extends Controller
{
    public function __invoke(Request $request, Event $event, EventStandingsService $standings)
    {
        app(PublicTournamentVisibility::class)->ensureEventIsVisible($event, $request->user());
        abort_unless($event->isTeam() && $event->standings_published, 404);
        $filters = $request->validate([
            'age' => 'nullable|string|max:50',
        ]);

        return response()->view('frontend.fixtures.event-standings', ['event' => $event]
            + $standings->forEvent($event, $filters, publishedOnly: true))
            ->header('Cache-Control', 'private, no-store');
    }
}
