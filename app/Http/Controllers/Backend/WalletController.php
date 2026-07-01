<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Payments\Services\LedgerService;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamPaymentOrder;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * List all user wallets.
     */
    public function index()
    {
        $wallets = Wallet::with('payable')->get();

        return view('backend.wallet.wallet-index', compact('wallets'));
    }

    /**
     * Show a specific user's wallet and its transactions.
     */
    public function show($id)
    {
        $authUser = auth()->user();
        if (!$authUser || ($authUser->id !== (int) $id && !$authUser->hasAnyRole(['super-user', 'admin']))) {
            abort(403, 'Unauthorized.');
        }

        $user = User::findOrFail($id);

        // Get wallet or create if missing
        $wallet = $user->wallet ?? $user->wallet()->create();

        // Get transactions
        $transactions = $wallet->transactions()->latest()->get();

        return view('backend.wallet.wallet-show', compact('user', 'wallet', 'transactions'));
    }

    /**
     * Refund a team player payment to their wallet
     */
    public function refund(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->hasAnyRole(['super-user', 'admin', 'convenor'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'team_player_id' => 'required|integer|exists:team_players,id'
        ]);

        $teamPlayer = TeamPlayer::with(['player.users', 'team.category'])->findOrFail($request->team_player_id);

        if (!$teamPlayer->player) {
            return response()->json([
                'success' => false,
                'message' => 'Player not found for this team member.'
            ], 422);
        }

        $player = $teamPlayer->player;
        $team = $teamPlayer->team;

        // Find the payment order - try with event_id first, then without
        $eventId = optional($team->category)->event_id;

        $order = TeamPaymentOrder::where('team_id', $team->id)
            ->where('player_id', $player->id)
            ->when($eventId, fn($q) => $q->where('event_id', $eventId))
            ->where('pay_status', true)
            ->first();

        // Fallback: find any paid order for this player+team
        if (!$order) {
            $order = TeamPaymentOrder::where('team_id', $team->id)
                ->where('player_id', $player->id)
                ->where('pay_status', true)
                ->first();
        }

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'No paid order found for this player. Only paid players can be refunded.'
            ], 422);
        }

        // Check if already refunded
        if ($order->hasRefund() && $order->isRefundCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'This payment has already been refunded.'
            ], 400);
        }

        // Get the refund amount
        $refundAmount = $order->maxRefundableAmount();

        if ($refundAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No refundable amount for this player.'
            ], 400);
        }

        // Get the player's primary user
        $playerUser = $player->users->first();

        if (!$playerUser) {
            return response()->json([
                'success' => false,
                'message' => 'No user account found for this player.'
            ], 422);
        }

        // Get or create wallet
        $wallet = $playerUser->wallet ?? $playerUser->wallet()->create();

        // Credit the wallet
        $meta = [
            'admin' => $user->name,
            'team_id' => $team->id,
            'team_name' => $team->name,
            'player_id' => $player->id,
            'player_name' => $player->name . ' ' . $player->surname,
            'order_id' => $order->id,
            'initiated_by' => 'wallet_controller_refund',
        ];

        app(LedgerService::class)->appendWalletCredit(
            $wallet,
            $refundAmount,
            'team_refund',
            $user->id,
            $meta
        );

        // Update order refund status
        $order->update([
            'refund_method' => 'wallet',
            'refund_status' => 'completed',
            'refund_gross' => $refundAmount,
            'refund_net' => $refundAmount,
            'refunded_at' => now(),
        ]);

        // Log activity
        activity('wallet')
            ->performedOn($wallet)
            ->causedBy($user)
            ->withProperties([
                'amount' => $refundAmount,
                'team_player_id' => $teamPlayer->id,
                'order_id' => $order->id,
            ])
            ->log("Refunded R{$refundAmount} to wallet for {$player->name} {$player->surname}");

        return response()->json([
            'success' => true,
            'message' => "Refunded R{$refundAmount} to {$player->name}'s wallet.",
            'amount' => $refundAmount,
        ]);
    }

    /**
     * Bulk refund all players in a team to their wallets
     */
    public function refundBulk(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->hasAnyRole(['super-user', 'admin', 'convenor'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'team_id' => 'required|integer|exists:teams,id'
        ]);

        $team = Team::with(['team_players.player.users'])->findOrFail($request->team_id);
        $refundedCount = 0;
        $errors = [];
        $totalRefunded = 0;

        foreach ($team->team_players as $teamPlayer) {
            if (!$teamPlayer->player) {
                continue;
            }

            $player = $teamPlayer->player;

            // Find the payment order
            $order = TeamPaymentOrder::where('team_id', $team->id)
                ->where('player_id', $player->id)
                ->where('event_id', $team->category->event_id ?? null)
                ->first();

            if (!$order || $order->maxRefundableAmount() <= 0) {
                continue;
            }

            if ($order->hasRefund() && $order->isRefundCompleted()) {
                continue;
            }

            $refundAmount = $order->maxRefundableAmount();
            $playerUser = $player->users->first();

            if (!$playerUser) {
                $errors[] = "No user found for {$player->name}";
                continue;
            }

            $wallet = $playerUser->wallet ?? $playerUser->wallet()->create();

            $meta = [
                'admin' => $user->name,
                'team_id' => $team->id,
                'team_name' => $team->name,
                'player_id' => $player->id,
                'player_name' => $player->name . ' ' . $player->surname,
                'order_id' => $order->id,
                'initiated_by' => 'wallet_controller_bulk_refund',
            ];

            app(LedgerService::class)->appendWalletCredit(
                $wallet,
                $refundAmount,
                'team_refund',
                $user->id,
                $meta
            );

            $order->update([
                'refund_method' => 'wallet',
                'refund_status' => 'completed',
                'refund_gross' => $refundAmount,
                'refund_net' => $refundAmount,
                'refunded_at' => now(),
            ]);

            $refundedCount++;
            $totalRefunded += $refundAmount;
        }

        return response()->json([
            'success' => true,
            'message' => "Refunded {$refundedCount} player(s) for a total of R{$totalRefunded}.",
            'refunded_count' => $refundedCount,
            'total_amount' => $totalRefunded,
            'errors' => $errors,
        ]);
    }

}
