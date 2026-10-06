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
    public function status(Event $event)
    {
        $this->authorize('event.manage', $event);
        return response()->json(['success' => true] + $this->publicationState($event));
    }

    private function publicationState(Event $event): array
    {
        $draws = $event->draws()->withoutEagerLoads()->orderBy('id')->get(['id', 'published', 'locked', 'oop_published']);
        $published = $draws->where('published', true)->count();
        return ['event_id' => (int) $event->id, 'draw_states' => $draws->map(fn ($draw) => [
            'id' => (int) $draw->id, 'published' => (bool) $draw->published, 'locked' => (bool) $draw->locked, 'oop_published' => (bool) $draw->oop_published,
        ])->all(), 'draw_summary' => ['published' => $published, 'unpublished' => $draws->count() - $published,
            'status' => $draws->isEmpty() ? 'No draws' : ($published === $draws->count() ? 'All published' : ($published ? 'Partly published' : 'Unpublished'))]];
    }

    public function __invoke(
        Request $request,
        Event $event,
        DrawPublicationService $drawPublication,
        DrawSchedulePublicationService $schedulePublication,
        FlexibleMonradService $flexibleMonrad,
    ) {
        // Reuse this established endpoint for event-wide display settings. This
        // also keeps the Head Office page compatible with an older cached route
        // table during a rolling/shared-host deployment.
        if ($request->input('operation') === 'schedule_visibility') {
            return app(EventScheduleVisibilityController::class)($request, $event);
        }

        $data = $request->validate([
            'draw_ids' => ['required', 'array', 'min:1', 'max:200'],
            'draw_ids.*' => ['required', 'integer', 'distinct'],
            'operation' => ['required', Rule::in(['draws', 'schedules'])],
            'action' => ['sometimes', Rule::in(['publish', 'unpublish'])],
        ]);
        $action = $data['action'] ?? 'publish';

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

        $changed = [];
        $unchanged = [];
        $failed = [];

        foreach ($ids as $id) {
            $draw = $draws[$id];
            $currentlyPublished = $data['operation'] === 'draws'
                ? (bool) $draw->published
                : (bool) $draw->oop_published;
            $targetPublished = $action === 'publish';
            if ($currentlyPublished === $targetPublished) {
                $unchanged[] = $draw->id;
                continue;
            }

            try {
                if ($data['operation'] === 'schedules') {
                    $targetPublished
                        ? $schedulePublication->publish($draw)
                        : $schedulePublication->unpublish($draw);
                } elseif ($draw->usesFlexibleMonrad()) {
                    $flexibleMonrad->publish($draw, (int) ($draw->flexibleMonrad?->revision ?? 0), $targetPublished);
                } elseif ($targetPublished) {
                    $drawPublication->publish($draw);
                } else {
                    $drawPublication->unpublish($draw);
                }
                $changed[] = $draw->id;
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
            'action' => $action,
            'changed' => $changed,
            'published' => $action === 'publish' ? $changed : [],
            'unpublished' => $action === 'unpublish' ? $changed : [],
            'unchanged' => $unchanged,
            'failed' => $failed,
        ] + $this->publicationState($event));
    }
}
