<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\{Event, RegistrationOrder, TrialProgramme, TrialPaymentProof, TrialSquadSlot, TrialSquadDraft};
use App\Services\InterprovincialTrials\ManualCollectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TrialRegistrationController extends Controller
{
    public function respond(Request $request, Event $event, TrialSquadSlot $slot)
    {
        $data = $request->validate(['response' => 'required|in:confirmed,declined']);
        abort_unless((int) $slot->draft->event_id === (int) $event->id, 404);
        app(\App\Services\InterprovincialTrials\TrialSelectionReviewService::class)->respond($slot, $request->user(), $data['response']);
        return redirect(route('events.show', ['event' => $event->id, 'slot' => $slot->id]).'#trial-squad-slot-'.$slot->id)->with('success', 'Response recorded with your account and time.');
    }

    public function upload(Request $request, Event $event, RegistrationOrder $order, ManualCollectionService $service)
    {
        abort_unless($event->isInterprovincialTrials() && $order->items()->whereHas('category_event', fn ($query) => $query->where('event_id', $event->id))->exists(), 404);
        abort_unless(filled(TrialProgramme::where('event_id', $event->id)->first()?->bank_details), 422);
        $request->validate(['proof' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);
        $service->uploadProof($order, $request->user(), $request->file('proof'));
        return redirect()->route('registration.checkout', $order)->with('success', 'Proof uploaded. Registration is confirmed after an administrator verifies payment.');
    }

    public function proof(Request $request, TrialPaymentProof $proof, ManualCollectionService $service)
    {
        $service->authorizeProof($proof, $request->user());
        return Storage::disk('local')->download($proof->path, 'payment-proof-'.$proof->id, ['X-Content-Type-Options' => 'nosniff']);
    }
}
