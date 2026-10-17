<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Event, Team, TeamPlayer};
use App\Services\AdminTeamWithdrawalService;
use Illuminate\Http\Request;

class AdminTeamWithdrawalController extends Controller
{
    public function show(Request $request, Event $event, Team $team, TeamPlayer $slot, AdminTeamWithdrawalService $service)
    {
        $review = $service->review($event, $team, $slot, $request->user());
        return view('backend.adminPage.admin_show.team-withdrawal', compact('event', 'team', 'slot', 'review'));
    }

    public function store(Request $request, Event $event, Team $team, TeamPlayer $slot, AdminTeamWithdrawalService $service)
    {
        $data = $request->validate([
            'order_id' => 'required|integer', 'fingerprint' => 'required|string|size:64',
            'reason' => 'required|string|min:3|max:1000', 'confirm' => 'accepted',
        ]);
        $service->confirm($event, $team, $slot, $request->user(), $data);
        return redirect()->route('admin.events.teams', ['event' => $event->id, 'roster_tab' => 'players', 'selected_region' => $team->region_id])
            ->with('success', 'Player withdrawn. Any eligible refund was credited to the original payer’s wallet.');
    }
}
