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
        abort_unless(in_array((int) $invitation->player_id, $request->user()->ownedPlayerIds(), true), 403);
        abort_unless(in_array($invitation->status, ['queued', 'sent'], true), 404);
        $invitation->load(['event', 'categoryEvent.category', 'player']);

        return view('frontend.interprovincial-trials.invitations.show', compact('invitation'));
    }

    public function register(Request $request, InterprovincialTrialInvitation $invitation, InvitationService $service)
    {
        $order = $service->accept($invitation, $request->user());

        if ((int) $order->pay_status === 1) {
            return redirect()->route('frontend.registration.success', $order)->with('success', 'Trial registration confirmed.');
        }

        return redirect()->route('registration.checkout', $order);
    }
}
