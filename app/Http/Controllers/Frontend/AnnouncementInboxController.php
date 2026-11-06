<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;

class AnnouncementInboxController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $posts = Announcement::query()
            ->whereHas('event', fn ($events) => $events->visibleTo(null))
            ->with(['event' => fn ($events) => $events->visibleTo(null)->select('id', 'name')])
            ->latest()
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->filter(fn (Announcement $post) => $post->event !== null)
            ->values()
            ->map(fn (Announcement $post) => [
                'eventId' => $post->event_id,
                'eventName' => $post->event->name,
                'url' => route('events.show', $post->event_id).'#event-announcement-'.$post->id,
                'title' => filled($post->title) ? $post->title : 'Event update',
                'revision' => $post->readRevision(),
                'postedAt' => $post->created_at?->getTimestampMs(),
            ]);

        return response()->json($posts)->header('Cache-Control', 'private, no-store');
    }
}
