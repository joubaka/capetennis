<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\MastersInvitation;
use App\Services\Masters\MastersInvitationService;
use Illuminate\Http\Request;

class MastersInvitationController extends Controller
{
    public function show(MastersInvitation $invitation, Request $request)
    {
        $invitation->load(['batch.event', 'categoryEvent.category', 'player']);
        return view('frontend.masters.show', compact('invitation'));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $invitations = MastersInvitation::with(['batch.event', 'categoryEvent.category', 'player'])
            ->whereHas('player', fn ($q) => $q->where('userId', $user->id)->orWhereHas('users', fn ($uq) => $uq->whereKey($user->id)))
            ->whereIn('status', [MastersInvitation::INVITED, MastersInvitation::ACCEPTED_PENDING_PAYMENT, MastersInvitation::PAID_CONFIRMED])
            ->latest('invited_at')->get();

        return view('frontend.masters.index', compact('invitations'));
    }

    public function accept(MastersInvitation $invitation, Request $request, MastersInvitationService $service)
    {
        $order = $service->accept($invitation, $request->user());
        return redirect()->route('registration.checkout', $order)
            ->with('success', 'Invitation accepted. Complete payment to confirm your Masters place.');
    }

    public function decline(MastersInvitation $invitation, Request $request, MastersInvitationService $service)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $service->decline($invitation, $request->user(), $request->input('reason'));
        return back()->with('masters_decline_success', 'Your unavailability was recorded. Please confirm it using the link sent to your email. The next player will only be invited after you confirm.');
    }

    public function confirmDecline(MastersInvitation $invitation, MastersInvitationService $service)
    {
        $replacement = $service->confirmDecline($invitation);
        return redirect()->route('masters.invitations.show', $invitation)
            ->with('success', $replacement
                ? 'Your cancellation is confirmed. The next reserve player has been invited.'
                : 'Your cancellation is confirmed. The administrator will manage the next replacement.');
    }
}
