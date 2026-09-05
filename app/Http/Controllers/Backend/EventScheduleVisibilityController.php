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

        $applyNumSets = $request->filled('num_sets');
        $data = $request->validate([
            'schedule_visibility' => ['required', Rule::in([
                DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH,
                DrawSetting::SCHEDULE_VISIBILITY_FULL,
            ])],
            'num_sets' => ['sometimes', 'nullable', 'integer', Rule::in([1, 2, 3, 5])],
        ]);

        $draws = $event->draws()->with('settings')->get();

        // Authorize the complete event selection before changing any setting.
        foreach ($draws as $draw) {
            Gate::authorize('editNotes', $draw);

            if ($applyNumSets) {
                Gate::authorize('update', $draw);
            }
        }

        DB::transaction(function () use ($draws, $data, $applyNumSets): void {
            foreach ($draws as $draw) {
                $settings = [
                    'schedule_visibility' => $data['schedule_visibility'],
                ];

                if ($applyNumSets) {
                    $settings['num_sets'] = $data['num_sets'];
                }

                $draw->settings()->updateOrCreate(
                    ['draw_id' => $draw->id],
                    $settings,
                );
            }
        });

        $message = $applyNumSets
            ? 'Draw settings updated for every draw.'
            : 'Match time display updated for every draw.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'schedule_visibility' => $data['schedule_visibility'],
            'num_sets' => $applyNumSets ? $data['num_sets'] : null,
            'updated_draws' => $draws->count(),
        ]);
    }
}
