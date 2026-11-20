<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\PlusMorningDigestService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AnnouncementReadController extends Controller
{
    public function __invoke(Request $request, PlusMorningDigestService $digest): Response
    {
        $data = $request->validate([
            'entries' => ['required', 'array', 'min:1', 'max:100'],
            'entries.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'entries.*.revision' => ['required', 'string', 'max:100'],
        ]);

        $posts = Announcement::query()
            ->whereIn('id', array_column($data['entries'], 'id'))
            ->whereHas('event', fn ($events) => $events->visibleTo(null))
            ->get()->keyBy('id');

        // Validate the complete batch before recording any read. A supplied user
        // identifier is never used: reads always belong to the current actor.
        foreach ($data['entries'] as $entry) {
            $post = $posts->get($entry['id']);
            abort_unless($post, 404);
            abort_unless(hash_equals($post->readRevision(), $entry['revision']), 409);
        }
        foreach ($data['entries'] as $entry) {
            $digest->markSeen($request->user(), 'announcement:'.$entry['id'], $entry['revision']);
        }

        return response()->noContent();
    }
}
