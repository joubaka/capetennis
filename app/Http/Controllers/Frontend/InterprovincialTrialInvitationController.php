<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventNomination;
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

        if ($invitation->player_id === null) {
            abort_unless(in_array($invitation->status, ['queued', 'sent'], true), 404);
            return $this->createNomineeProfile($request, $invitation->event, $invitation->categoryEvent, $invitation->nomination, true);
        }

        $canDecline = in_array((int) $invitation->player_id, $user->ownedPlayerIds(), true);
        $isPayer = $invitation->order && (int) $invitation->order->user_id === (int) $user->id;

        return view('frontend.interprovincial-trials.invitations.show', compact('invitation', 'canDecline', 'isPayer'));
    }

    public function register(Request $request, InterprovincialTrialInvitation $invitation, InvitationService $service)
    {
        abort_unless($invitation->categoryEvent?->nominations_published, 404);
        $order = $service->accept($invitation, $request->user());

        if ((int) $order->pay_status === 1) {
            return redirect()->route('frontend.registration.success', $order)->with('success', 'Trial registration confirmed.');
        }

        return redirect()->route('registration.checkout', $order);
    }

    public function createNomineeProfile(Request $request, Event $event, CategoryEvent $categoryEvent, EventNomination $nomination, bool $fromInvitation = false)
    {
        abort_unless((int) $categoryEvent->event_id === (int) $event->id
            && (int) $nomination->event_id === (int) $event->id
            && (int) $nomination->category_event_id === (int) $categoryEvent->id, 404);
        abort_unless($fromInvitation || $categoryEvent->nominations_published, 404);
        app(\App\Services\InterprovincialTrials\NominationProfileService::class)->authorize($nomination, $request->user());
        $request->session()->put('trial_nomination_profile', ['nomination_id' => $nomination->id, 'user_id' => $request->user()->id, 'from_invitation' => $fromInvitation]);

        return redirect()->route('player.profile.create', ['trial_nomination' => $nomination->id]);
    }

    public function registerNomination(
        Request $request,
        Event $event,
        CategoryEvent $categoryEvent,
        EventNomination $nomination,
        InvitationService $service
    ) {
        $order = $service->acceptPublishedNomination($event, $categoryEvent, $nomination, $request->user());

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
