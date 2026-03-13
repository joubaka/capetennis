<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Mail\CategoryMovedMail;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\Registration;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RegistrationCategoryMoveController extends Controller
{
    public function move(Request $request, $entryId)
    {
        $request->validate([
            'new_category_event_id' => ['required', 'exists:category_events,id'],
        ]);

        $entry = CategoryEventRegistration::findOrFail($entryId);

        // Only the user who registered can move
        if ((int) $entry->user_id !== (int) auth()->id()) {
            abort(403, 'You can only edit your own entries.');
        }

        // Cannot move withdrawn entries
        if ($entry->status === 'withdrawn') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot move a withdrawn entry.',
            ], 422);
        }

        $currentCategory = CategoryEvent::with('event', 'category')->findOrFail($entry->category_event_id);
        $event = $currentCategory->event;

        // Enforce withdrawal deadline
        $withdrawalDeadline = $event->withdrawal_deadline
            ? Carbon::parse($event->withdrawal_deadline)
            : ($event->entry_deadline
                ? Carbon::parse($event->entry_deadline)
                : Carbon::parse($event->start_date));

        if (now()->gt($withdrawalDeadline)) {
            return response()->json([
                'success' => false,
                'message' => 'The deadline for category changes has passed.',
            ], 422);
        }

        $newCategory = CategoryEvent::with('category')->findOrFail($request->new_category_event_id);

        // Must be in the same event
        if ($currentCategory->event_id !== $newCategory->event_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot move to a category in a different event.',
            ], 422);
        }

        // Cannot move to same category
        if ((int) $entry->category_event_id === (int) $newCategory->id) {
            return response()->json([
                'success' => false,
                'message' => 'Player is already in this category.',
            ], 422);
        }

        // Target category must not be locked
        if ($newCategory->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Target category is locked.',
            ], 403);
        }

        // Prevent duplicate in target category
        $exists = CategoryEventRegistration::where('category_event_id', $newCategory->id)
            ->where('registration_id', $entry->registration_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Player is already registered in that category.',
            ], 422);
        }

        $oldCategoryName = $currentCategory->category->name ?? 'Unknown';
        $newCategoryName = $newCategory->category->name ?? 'Unknown';

        // Resolve player before update for logging & email
        $registration = Registration::with('players')->find($entry->registration_id);
        $player = $registration?->players->first();

        $entry->update([
            'category_event_id' => $newCategory->id,
        ]);

        Log::info('FRONTEND CATEGORY MOVE', [
            'entry_id' => $entry->id,
            'user_id' => auth()->id(),
            'event' => $event->name ?? '',
            'player' => trim(($player->name ?? '') . ' ' . ($player->surname ?? '')),
            'from_category' => $oldCategoryName,
            'to_category' => $newCategoryName,
        ]);

        // Spatie activity log
        activity('category-move')
            ->performedOn($entry)
            ->causedBy(auth()->user())
            ->withProperties([
                'entry_id' => $entry->id,
                'event_id' => $event->id,
                'event_name' => $event->name ?? '',
                'player' => trim(($player->name ?? '') . ' ' . ($player->surname ?? '')),
                'from_category_id' => $currentCategory->id,
                'from_category' => $oldCategoryName,
                'to_category_id' => $newCategory->id,
                'to_category' => $newCategoryName,
            ])
            ->log("Moved entry from {$oldCategoryName} to {$newCategoryName}");

        // Send email to the player
        if ($player && $player->email) {
            try {
                Mail::to($player->email)->send(new CategoryMovedMail([
                    'player_name' => trim($player->name . ' ' . $player->surname),
                    'event_name' => $event->name ?? 'Event',
                    'old_category' => $oldCategoryName,
                    'new_category' => $newCategoryName,
                    'changed_by' => auth()->user()->userName ?? auth()->user()->name ?? 'User',
                ]));
            } catch (\Throwable $e) {
                Log::warning('CATEGORY MOVE EMAIL FAILED', [
                    'entry_id' => $entry->id,
                    'player_email' => $player->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Moved from ' . $oldCategoryName . ' to ' . $newCategoryName,
        ]);
    }
}
