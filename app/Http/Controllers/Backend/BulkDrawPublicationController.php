<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Draws\Services\DrawPublicationService;
use App\Domain\Draws\Services\DrawSchedulePublicationService;
use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\Event;
use App\Services\Draw\FlexibleMonradService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BulkDrawPublicationController extends Controller
{
    public function __invoke(
        Request $request,
        Event $event,
        DrawPublicationService $drawPublication,
        DrawSchedulePublicationService $schedulePublication,
        FlexibleMonradService $flexibleMonrad,
    ) {
        $data = $request->validate([
            'draw_ids' => ['required', 'array', 'min:1', 'max:200'],
            'draw_ids.*' => ['required', 'integer', 'distinct'],
            'operation' => ['required', Rule::in(['draws', 'schedules'])],
        ]);

        $ids = collect($data['draw_ids'])->map(fn ($id) => (int) $id)->values();
        $draws = Draw::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->with(['settings', 'flexibleMonrad'])
            ->get()
            ->keyBy('id');

        if ($draws->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'draw_ids' => 'One or more selected draws do not belong to this event.',
            ]);
        }

        // Authorize the entire selection before changing any draw.
        foreach ($ids as $id) {
            Gate::authorize('publish', $draws[$id]);
        }

        $published = [];
        $unchanged = [];
        $failed = [];

        foreach ($ids as $id) {
            $draw = $draws[$id];
            $alreadyPublished = $data['operation'] === 'draws'
                ? (bool) $draw->published
                : (bool) $draw->oop_published;
            if ($alreadyPublished) {
                $unchanged[] = $draw->id;
                continue;
            }

            try {
                if ($data['operation'] === 'schedules') {
                    $schedulePublication->publish($draw);
                } elseif ($draw->usesFlexibleMonrad()) {
                    $flexibleMonrad->publish($draw, (int) ($draw->flexibleMonrad?->revision ?? 0), true);
                } else {
                    $drawPublication->publish($draw);
                }
                $published[] = $draw->id;
            } catch (\RuntimeException $exception) {
                if ($exception instanceof \Illuminate\Database\QueryException) {
                    throw $exception;
                }
                $failed[] = [
                    'id' => $draw->id,
                    'name' => $draw->drawName,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => count($failed) === 0,
            'operation' => $data['operation'],
            'published' => $published,
            'unchanged' => $unchanged,
            'failed' => $failed,
        ]);
    }
}
