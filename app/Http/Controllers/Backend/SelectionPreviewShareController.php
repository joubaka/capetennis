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
        $input = $request->validate(['q' => ['nullable', 'string', 'max:80'], 'age' => ['nullable', 'integer', 'min:1', 'max:99'], 'section' => ['sometimes', 'in:users,grants']]);
        if ($request->expectsJson() && ($input['section'] ?? null) === 'grants') {
            $grants = SelectionPreviewGrant::where('event_id', $event->id)->whereNull('revoked_at')->with('user:id,name,userName,userSurname,email')
                ->orderByDesc('id')->paginate(15, ['*'], 'grants_page')->withQueryString();
            return response()->json(['grants' => $grants->map(fn ($grant) => $this->choice($grant->user) + ['revoke_url' => route('backend.selection-preview-share.destroy', ['event' => $event, 'user' => $grant->user_id])]),
                'pagination' => $this->pagination($grants)])->header('Cache-Control', 'no-store, private');
        }
        $q = preg_replace('/\s+/u', ' ', trim($input['q'] ?? ''));
        $query = User::whereNotNull('email')->where('email', '!=', '')->select(['id', 'name', 'userName', 'userSurname', 'email']);
        foreach (preg_split('/\s+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY) as $token) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $token).'%';
            $query->where(function ($match) use ($pattern) {
                foreach (['name', 'email', 'userName', 'userSurname'] as $field) $match->orWhereRaw("LOWER(users.{$field}) LIKE ? ESCAPE '!'", [$pattern]);
                $match->orWhereHas('players', fn ($players) => $players->where(fn ($name) => $name->whereRaw("LOWER(players.name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(players.surname) LIKE ? ESCAPE '!'", [$pattern])));
                $match->orWhereExists(fn ($direct) => $direct->selectRaw('1')->from('players')->whereColumn('players.userId', 'users.id')
                    ->where(fn ($name) => $name->whereRaw("LOWER(players.name) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(players.surname) LIKE ? ESCAPE '!'", [$pattern])));
            });
        }
        $users = $query->orderBy('name')->orderBy('id')->paginate(15, ['*'], 'users_page')->withQueryString();
        $activeIds = SelectionPreviewGrant::where('event_id', $event->id)->whereNull('revoked_at')->whereIn('user_id', $users->pluck('id'))->pluck('user_id')->all();
        $choices = $users->map(fn ($user) => $this->choice($user) + ['shared' => in_array($user->id, $activeIds)]);
        if ($request->expectsJson()) return response()->json(['users' => $choices, 'pagination' => $this->pagination($users)])->header('Cache-Control', 'no-store, private');
        $grants = SelectionPreviewGrant::where('event_id', $event->id)->whereNull('revoked_at')->with('user:id,name,userName,userSurname,email')->orderByDesc('id')->paginate(15, ['*'], 'grants_page')->withQueryString();
        $oldIds = array_slice(array_map('intval', (array) $request->old('recipient_ids', [])), 0, 30);
        $selected = User::whereIn('id', $oldIds)->get(['id', 'name', 'userName', 'userSurname', 'email'])->map(fn ($user) => $this->choice($user))->filter(fn ($user) => $user['selectable'])->values();

        return response()->view('backend.adminPage.selection-preview-share', ['event' => $event, 'users' => $users, 'grants' => $grants,
            'choices' => $choices, 'selected' => $selected, 'age' => $input['age'] ?? null, 'requestKey' => (string) Str::uuid(), 'q' => $q])->header('Cache-Control', 'no-store, private');
    }

    private function choice(User $user): array
    {
        $name = trim($user->name ?: $user->userName);
        $surname = trim($user->userSurname ?? '');
        if ($surname !== '' && ! str_ends_with(mb_strtolower($name), mb_strtolower($surname))) $name .= ' '.$surname;
        return ['id' => (int) $user->id, 'name' => $name, 'email' => (string) $user->email,
            'selectable' => (bool) filter_var($user->email, FILTER_VALIDATE_EMAIL)];
    }

    private function pagination($paginator): array
    {
        return ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total(), 'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(), 'previous_page_url' => $paginator->previousPageUrl(), 'next_page_url' => $paginator->nextPageUrl()];
    }

    public function store(Request $request, Event $event, SelectionPreviewShareService $shares)
    {
        $this->authorize('event.manage', $event);
        $input = $request->validate(['request_key' => ['required', 'uuid'], 'recipient_ids' => ['required', 'array', 'min:1', 'max:30'],
            'channel' => ['sometimes', 'string', 'in:email,whatsapp'],
            'recipient_ids.*' => ['required', 'integer', 'distinct'], 'age' => ['nullable', 'integer', 'min:1', 'max:99']]);
        $result = $shares->share($event, $request->user(), $input);
        if ($request->expectsJson()) return response()->json($result + ['next_request_key' => (string) Str::uuid()])->header('Cache-Control', 'no-store, private');
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
