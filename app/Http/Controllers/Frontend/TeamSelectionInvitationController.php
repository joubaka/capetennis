<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\TeamSelectionInvitation;
use App\Services\TeamSelection\TeamSelectionInvitationService;
use Illuminate\Http\Request;

class TeamSelectionInvitationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $invitations = TeamSelectionInvitation::with(['selectionImport.event', 'region', 'team', 'player'])
            ->whereHas('player', fn ($query) => $query
                ->where('userId', $user->id)
                ->orWhereHas('users', fn ($users) => $users->whereKey($user->id)))
            ->whereIn('status', [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                TeamSelectionInvitation::PAID_CONFIRMED,
            ])->latest('invited_at')->get();

        return view('frontend.team-selection.index', compact('invitations'));
    }

    public function show(Request $request, TeamSelectionInvitation $invitation, TeamSelectionInvitationService $service)
    {
        $invitation->load(['selectionImport.event', 'region', 'team', 'player']);
        $service->authorizePlayer($invitation, $request->user());

        return view('frontend.team-selection.show', compact('invitation'));
    }

    public function accept(Request $request, TeamSelectionInvitation $invitation, TeamSelectionInvitationService $service)
    {
        $accepted = $service->accept($invitation, $request->user());

        return redirect()->route('team.payment.payfast', [
            'team' => $accepted->team_id,
            'player' => $accepted->player_id,
            'event' => $accepted->event_id,
        ])->with('success', 'Invitation accepted. Complete payment to confirm the team place.');
    }

    public function decline(Request $request, TeamSelectionInvitation $invitation, TeamSelectionInvitationService $service)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $replacement = $service->decline($invitation, $request->user(), $data['reason'] ?? null);

        return back()->with('success', $replacement
            ? 'Your unavailability was recorded and the next reserve has been invited.'
            : 'Your unavailability was recorded.');
    }
}
