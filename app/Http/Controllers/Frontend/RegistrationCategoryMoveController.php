<?php

namespace App\Http\Controllers\Frontend;

use App\Domain\Entries\Services\EntryService;
use App\Http\Controllers\Controller;
use App\Mail\CategoryMovedMail;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Registration;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RegistrationCategoryMoveController extends Controller
{
    public function __construct(private EntryService $entryService) {}

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

        $newCategory = CategoryEvent::with('category')->findOrFail($request->new_category_event_id);

        // Capture display data before transfer for logging and email
        $currentCategory = CategoryEvent::with('event', 'category')->findOrFail($entry->category_event_id);
        $event           = $currentCategory->event;
        $oldCategoryName = $currentCategory->category->name ?? 'Unknown';
        $newCategoryName = $newCategory->category->name ?? 'Unknown';
        $registration    = Registration::with('players')->find($entry->registration_id);
        $player          = $registration?->players->first();

        try {
            $entry = $this->entryService->transferEntry(
                $entry,
                $newCategory,
                auth()->user(),
                adminOverride: false
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        Log::info('FRONTEND CATEGORY MOVE', [
            'entry_id'      => $entry->id,
            'user_id'       => auth()->id(),
            'event'         => $event->name ?? '',
            'player'        => trim(($player->name ?? '') . ' ' . ($player->surname ?? '')),
            'from_category' => $oldCategoryName,
            'to_category'   => $newCategoryName,
        ]);

        activity('category-move')
            ->performedOn($entry)
            ->causedBy(auth()->user())
            ->withProperties([
                'entry_id'         => $entry->id,
                'event_id'         => $event->id,
                'event_name'       => $event->name ?? '',
                'player'           => trim(($player->name ?? '') . ' ' . ($player->surname ?? '')),
                'from_category_id' => $currentCategory->id,
                'from_category'    => $oldCategoryName,
                'to_category_id'   => $newCategory->id,
                'to_category'      => $newCategoryName,
            ])
            ->log("Moved entry from {$oldCategoryName} to {$newCategoryName}");

        if ($player && $player->email && SiteSetting::get('player_email_on_move', '1') === '1') {
            try {
                Mail::to($player->email)->send(new CategoryMovedMail([
                    'player_name'  => trim($player->name . ' ' . $player->surname),
                    'event_name'   => $event->name ?? 'Event',
                    'old_category' => $oldCategoryName,
                    'new_category' => $newCategoryName,
                    'changed_by'   => auth()->user()->userName ?? auth()->user()->name ?? 'User',
                ]));
            } catch (\Throwable $e) {
                Log::warning('CATEGORY MOVE EMAIL FAILED', [
                    'entry_id'     => $entry->id,
                    'player_email' => $player->email,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Moved from ' . $oldCategoryName . ' to ' . $newCategoryName,
        ]);
    }
}