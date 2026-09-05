<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\DrawSetting;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class EventScheduleVisibilityController extends Controller
{
    public function __invoke(Request $request, Event $event)
    {
        Gate::authorize('event.manage', $event);

        $data = $request->validate([
            'schedule_visibility' => ['required', Rule::in([
                DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH,
                DrawSetting::SCHEDULE_VISIBILITY_FULL,
            ])],
        ]);

        $draws = $event->draws()->with('settings')->get();

        // Authorize the complete event selection before changing any setting.
        foreach ($draws as $draw) {
            Gate::authorize('editNotes', $draw);
        }

        DB::transaction(function () use ($draws, $data): void {
            foreach ($draws as $draw) {
                $draw->settings()->updateOrCreate(
                    ['draw_id' => $draw->id],
                    ['schedule_visibility' => $data['schedule_visibility']],
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Match time display updated for every draw.',
            'schedule_visibility' => $data['schedule_visibility'],
            'updated_draws' => $draws->count(),
        ]);
    }
}
