<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Draw\BulkDrawDeletionService;
use Illuminate\Http\Request;

final class BulkDrawDeletionController extends Controller
{
    public function __invoke(Request $request, Event $event, BulkDrawDeletionService $deletion)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate(['draw_ids' => ['required', 'array', 'min:1', 'max:200'],
            'draw_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'revision' => [$request->isMethod('delete') ? 'required' : 'prohibited', 'string', 'size:64']]);
        return response()->json($deletion->review($event, $request->user(), $data['draw_ids'], $data['revision'] ?? null))->header('Cache-Control', 'no-store');
    }
}
