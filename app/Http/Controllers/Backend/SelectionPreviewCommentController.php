<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Event, SelectionPreviewComment, TeamResultSelectionDraft};
use App\Services\SelectionPreviewShareService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SelectionPreviewCommentController extends Controller
{
    private function target(Event $event, array $input): array
    {
        $draft = app(\App\Services\SelectionPreviewSnapshotService::class)->drafts($event)->get($input['group_key']);
        abort_unless($draft, 404);
        $player = collect($draft->snapshot)->first(fn ($row) => (string) $row['id'] === $input['player_key']);
        abort_unless($player, 404);
        return [$draft, $player];
    }

    public function show(Request $request, Event $event, SelectionPreviewShareService $shares)
    {
        abort_unless($shares->canView($event, $request->user()), 403);
        $input = $request->validate(['group_key' => ['required', 'string', 'regex:/^[1-9][0-9]?-(boys|girls)$/'], 'player_key' => ['required', 'string', 'max:100']]);
        [$draft, $player] = $this->target($event, $input);
        $comments = SelectionPreviewComment::where('event_id', $event->id)->where('group_key', $input['group_key'])->where('player_key', $input['player_key'])
            ->with('author:id,name')->orderByDesc('id')->paginate(20)->withQueryString();
        return response()->json(['player_name' => $player['name'], 'draft_version' => $draft->version, 'original_note' => $draft->reasons[$input['player_key']] ?? '',
            'comments' => $comments->map(fn ($comment) => ['author' => $comment->author->name, 'body' => $comment->body, 'draft_version' => $comment->draft_version, 'created_at' => $comment->created_at->toIso8601String()]),
            'next_page_url' => $comments->nextPageUrl()])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Event $event, SelectionPreviewShareService $shares)
    {
        abort_unless($shares->canView($event, $request->user()), 403);
        $input = $request->validate(['group_key' => ['required', 'string', 'regex:/^[1-9][0-9]?-(boys|girls)$/'], 'player_key' => ['required', 'string', 'max:100'],
            'draft_version' => ['required', 'integer', 'min:1'], 'body' => ['required', 'string', 'max:2000']]);
        $body = trim($input['body']);
        if ($body === '') throw \Illuminate\Validation\ValidationException::withMessages(['body' => 'Enter a comment.']);
        DB::transaction(function () use ($event, $request, $shares, $input, $body) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_unless($shares->canView($event, $request->user()), 403);
            [$draft] = $this->target($event, $input);
            abort_unless((int) $draft->version === (int) $input['draft_version'], 409, 'The saved selection changed. Reopen this discussion before adding your comment.');
            SelectionPreviewComment::create(['event_id' => $event->id, 'group_key' => $input['group_key'], 'player_key' => $input['player_key'],
                'author_id' => $request->user()->id, 'draft_version' => $draft->version, 'body' => $body, 'created_at' => now()]);
        });
        return response()->json(['saved' => true], 201)->header('Cache-Control', 'no-store, private');
    }
}
