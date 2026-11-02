<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{CapesterSupportRequest, Event};
use App\Services\Assistant\CapesterSupportQueue;
use Illuminate\Http\Request;

class CapesterSupportController extends Controller
{
    public function index(Request $request, Event $event)
    {
        $this->guard($event);
        $items = CapesterSupportRequest::where('event_id', $event->id)->where('user_id', $request->user()->id)->latest('id')->paginate(20);
        return response()->view('backend.event.capester', compact('event', 'items'))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Event $event, CapesterSupportQueue $queue)
    {
        $this->guard($event);
        $data = $request->validate(['submission_uuid' => ['required', 'uuid'],
            'capester_description' => ['required', 'string', 'max:2000'], 'kind' => ['required', 'in:support,hello'], 'reviewed' => ['required', 'accepted']]);
        abort_if($data['kind'] === 'hello' && ! app()->environment(['local', 'testing']), 403);
        $data['submission_uuid'] = strtolower($data['submission_uuid']);
        $queue->submit($request->user(), $event, $data);
        return redirect()->route('backend.capester.index', $event)->with('status', 'Request queued for Capester.');
    }

    private function guard(Event $event): void
    {
        abort_unless(config('capester.enabled'), 404);
        abort_unless(auth()->user()?->hasRole('super-user'), 403);
        $this->authorize('event.manage', $event);
    }
}
