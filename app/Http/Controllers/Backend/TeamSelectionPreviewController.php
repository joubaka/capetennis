<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\TeamSelectionPreviewService;
use Illuminate\Http\Request;

class TeamSelectionPreviewController extends Controller
{
    public function show(Request $request, Event $event, TeamSelectionPreviewService $preview)
    {
        abort_unless(app(\App\Services\SelectionPreviewShareService::class)->canView($event, $request->user()), 403);
        $input = $request->validate(['age' => ['sometimes', 'integer', 'min:1', 'max:99'], 'gender' => ['sometimes', 'string', 'in:boys,girls']]);

        return response()->view('backend.adminPage.selection-preview', ['event' => $event] + $preview->preview($event, isset($input['age']) ? (int) $input['age'] : null, $input['gender'] ?? null))
            ->header('Cache-Control', 'no-store, private');
    }
}
