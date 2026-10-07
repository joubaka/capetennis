<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\MatchReminderService;
use Illuminate\Http\{JsonResponse, Request};

final class MatchReminderController extends Controller
{
    public function __invoke(Request $request, MatchReminderService $reminders): JsonResponse
    {
        return response()->json($reminders->for($request->user()))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Vary', 'Cookie');
    }
}
