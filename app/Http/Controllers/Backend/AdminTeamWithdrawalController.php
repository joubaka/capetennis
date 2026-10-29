<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Event, Team, TeamPlayer, TeamPaymentOrder, TeamProviderRefundAttempt};
use App\Services\AdminTeamWithdrawalService;
use Illuminate\Http\Request;

class AdminTeamWithdrawalController extends Controller
{
    public function show(Request $request, Event $event, Team $team, TeamPlayer $slot, AdminTeamWithdrawalService $service)
    {
        $request->validate(['destination' => 'sometimes|in:wallet,payfast', 'fee_mode' => 'sometimes|in:current,full', 'deadline_override' => 'sometimes|boolean']);
        $review = $service->review($event, $team, $slot, $request->user(), $request->input('destination', 'wallet'), $request->input('fee_mode', 'current'), 'refund', $request->boolean('deadline_override'));
        return view('backend.adminPage.admin_show.team-withdrawal', compact('event', 'team', 'slot', 'review'));
    }

    public function store(Request $request, Event $event, Team $team, TeamPlayer $slot, AdminTeamWithdrawalService $service)
    {
        $data = $request->validate([
            'order_id' => 'required|integer', 'fingerprint' => 'required|string|size:64',
            'reason' => 'required|string|min:3|max:1000', 'confirm' => 'accepted',
            'destination' => 'sometimes|in:wallet,payfast', 'fee_mode' => 'sometimes|in:current,full',
            'deadline_override' => 'sometimes|boolean',
        ]);
        $order = $service->confirm($event, $team, $slot, $request->user(), $data);
        if ($order->refund_status === 'pending' && $order->refund_method === 'payfast') {
            return redirect()->route('backend.team.withdrawal.refund.show', [$event, $order])
                ->with('warning', 'The player is withdrawn and the roster place is free. The original-source refund is pending review; do not issue another refund.');
        }
        return redirect()->route('admin.events.teams', ['event' => $event->id, 'roster_tab' => 'players', 'selected_region' => $team->region_id])
            ->with('success', 'Player withdrawn. Any eligible refund was completed to the confirmed original payer destination.');
    }

    public function refundShow(Request $request, Event $event, TeamPaymentOrder $order)
    {
        abort_unless($request->user()->hasRole('super-user'), 403);
        abort_unless((int) $order->event_id === (int) $event->id, 404);
        $attempt = TeamProviderRefundAttempt::where('order_id', $order->id)->firstOrFail();
        return response()->view('backend.adminPage.admin_show.team-withdrawal-refund', compact('event', 'order', 'attempt'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function refundRecover(Request $request, Event $event, TeamPaymentOrder $order, \App\Domain\Refunds\Services\RefundExecutionService $refunds)
    {
        abort_unless($request->user()->hasRole('super-user'), 403);
        abort_unless((int) $order->event_id === (int) $event->id, 404);
        $data = $request->validate(['action' => 'required|in:resume,reconcile', 'confirm' => 'accepted']);
        if ($data['action'] === 'resume') {
            $attempt = TeamProviderRefundAttempt::where('order_id', $order->id)->firstOrFail();
            if ($attempt->status === 'ready') {
                $refunds->executeAdminTeamPayfastRefund($order, $request->user());
            } else {
                $refunds->recoverAdminTeamPayfastRefund($order, $request->user());
            }
        } else {
            $refunds->recoverAdminTeamPayfastRefund($order, $request->user(), $request->only(['pf_payment_id', 'amount', 'reference', 'reason', 'confirmed_at', 'externally_confirmed']));
        }
        return redirect()->route('backend.team.withdrawal.refund.show', [$event, $order])->with('success', 'The original-source refund is completed. No duplicate refund was dispatched.');
    }
}
