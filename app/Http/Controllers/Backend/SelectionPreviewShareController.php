<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Event, SelectionPreviewGrant, User};
use App\Services\SelectionPreviewShareService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SelectionPreviewShareController extends Controller
{
    public function index(Request $request, Event $event)
    {
        $this->authorize('event.manage', $event);
        $input = $request->validate(['q' => ['nullable', 'string', 'min:2', 'max:80'], 'age' => ['nullable', 'integer', 'min:1', 'max:99']]);
        $query = User::whereNotNull('email')->where('email', '!=', '')->select(['id', 'name', 'email']);
        if (! empty($input['q'])) $query->where('name', 'like', '%'.$input['q'].'%');
        $users = $query->orderBy('name')->orderBy('id')->paginate(15, ['*'], 'users_page')->withQueryString();
        $grants = SelectionPreviewGrant::where('event_id', $event->id)->whereNull('revoked_at')->with('user:id,name,email')->orderByDesc('id')->paginate(15, ['*'], 'grants_page')->withQueryString();

        return response()->view('backend.adminPage.selection-preview-share', ['event' => $event, 'users' => $users, 'grants' => $grants,
            'age' => $input['age'] ?? null, 'requestKey' => (string) Str::uuid(), 'q' => $input['q'] ?? ''])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Event $event, SelectionPreviewShareService $shares)
    {
        $this->authorize('event.manage', $event);
        $input = $request->validate(['request_key' => ['required', 'uuid'], 'recipient_ids' => ['required', 'array', 'min:1', 'max:30'],
            'recipient_ids.*' => ['required', 'integer', 'distinct'], 'age' => ['nullable', 'integer', 'min:1', 'max:99']]);
        $result = $shares->share($event, $request->user(), $input);
        if ($request->expectsJson()) return response()->json($result)->header('Cache-Control', 'no-store, private');
        return redirect()->route('backend.selection-preview-share.index', ['event' => $event, 'age' => $input['age'] ?? null])
            ->with('share_result', $result)->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request, Event $event, User $user, SelectionPreviewShareService $shares)
    {
        $this->authorize('event.manage', $event);
        $shares->revoke($event, $request->user(), (int) $user->id);
        if ($request->expectsJson()) return response()->json(['revoked' => true])->header('Cache-Control', 'no-store, private');
        return redirect()->route('backend.selection-preview-share.index', $event)->with('success', 'Private preview access removed.')->header('Cache-Control', 'no-store, private');
    }
}
