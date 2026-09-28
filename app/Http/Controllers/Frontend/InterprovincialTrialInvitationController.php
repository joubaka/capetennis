<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\InterprovincialTrialInvitation;
use App\Services\InterprovincialTrials\InvitationService;
use Illuminate\Http\Request;

class InterprovincialTrialInvitationController extends Controller
{
    public function index(Request $request)
    {
        $invitations = InterprovincialTrialInvitation::with(['event', 'categoryEvent.category', 'player'])
            ->whereIn('player_id', $request->user()->ownedPlayerIds())
            ->whereIn('status', ['queued', 'sent'])
            ->latest()
            ->paginate(25);

        return view('frontend.interprovincial-trials.invitations.index', compact('invitations'));
    }

    public function show(Request $request, InterprovincialTrialInvitation $invitation)
    {
        $user = $request->user();
        $invitation->load(['event', 'categoryEvent.category', 'player', 'nomination', 'order']);

        $tupleIsCurrent = (int) $invitation->categoryEvent?->event_id === (int) $invitation->event_id
            && (int) $invitation->nomination?->event_id === (int) $invitation->event_id
            && (int) $invitation->nomination?->category_event_id === (int) $invitation->category_event_id
            && (int) $invitation->nomination?->player_id === (int) $invitation->player_id;
        $tupleIsCurrent = $tupleIsCurrent && ! InterprovincialTrialInvitation::query()
            ->where('event_id', $invitation->event_id)->where('nomination_id', $invitation->nomination_id)
            ->where('id', '>', $invitation->id)->where('status', '!=', 'prepared')->exists();
        abort_unless($tupleIsCurrent && $invitation->event?->isInterprovincialTrials(), 404);

        $canDecline = in_array((int) $invitation->player_id, $user->ownedPlayerIds(), true);
        $isPayer = $invitation->order && (int) $invitation->order->user_id === (int) $user->id;

        return view('frontend.interprovincial-trials.invitations.show', compact('invitation', 'canDecline', 'isPayer'));
    }

    public function register(Request $request, InterprovincialTrialInvitation $invitation, InvitationService $service)
    {
        $order = $service->accept($invitation, $request->user());

        if ((int) $order->pay_status === 1) {
            return redirect()->route('frontend.registration.success', $order)->with('success', 'Trial registration confirmed.');
        }

        return redirect()->route('registration.checkout', $order);
    }

    public function decline(Request $request, InterprovincialTrialInvitation $invitation, InvitationService $service)
    {
        $service->decline($invitation, $request->user());

        return redirect(route('events.show', [
            'event' => $invitation->event_id,
            'player' => $invitation->player_id,
            'nomination' => $invitation->nomination_id,
        ]).'#trial-nomination-'.$invitation->nomination_id)
            ->with('success', 'The invitation was declined.');
    }
}
