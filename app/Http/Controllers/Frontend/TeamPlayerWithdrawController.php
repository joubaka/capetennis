<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamPaymentOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\Exceptions\DuplicateTransactionException;

class TeamPlayerWithdrawController extends Controller
{
 
  public function withdraw(Request $request, Team $team, Player $player, $eventId)
  {
    $user = Auth::user();
    if (!$user) {
      return redirect()->route('login');
    }

    // allow profile owner OR super-user id 584 OR role 'super-user'
    $isOwner = $player->users->contains('id', $user->id);
    $isSuperUser = ((int) $user->id === 584)
      || (method_exists($user, 'hasRole') && $user->hasRole('super-user'));

    if (!($isOwner || $isSuperUser)) {
      return back()->withErrors('You do not own this player profile.');
    }

    // Find the team slot for this player
    $teamPlayer = TeamPlayer::where('team_id', $team->id)
      ->where('player_id', $player->id)
      ->first();

    if (!$teamPlayer) {
      return back()->withErrors('Team player not found.');
    }

    // Paid slot: mark as unpaid and notify user to request refund / contact admin
    if ((int) $teamPlayer->pay_status === 1) {
      $teamPlayer->pay_status = 0;
      $teamPlayer->save();

      // If super-user withdraws, optionally clear the slot (uncomment if desired)
      // if ($isSuperUser) {
      //     $teamPlayer->player_id = 0;
      //     $teamPlayer->save();
      // }

      // Redirect user to refund choice so they can select wallet or bank refund
      return redirect()
        ->route('team.player.refund.choose', [$team->id, $player->id, $eventId])
        ->with('success', 'Player withdrawn from team (payment marked as unpaid). Please choose refund method.');
    }

    // Unpaid: clear the slot to make it available
    $teamPlayer->player_id = 0;
    $teamPlayer->save();

    return back()->with('success', 'Player withdrawn (no payment). Slot is now available.');
  }

  public function chooseRefund(Team $team, Player $player, $eventId)
  {
    $user = auth()->user();

    if (!$user) {
      return redirect()->route('login');
    }

    // Ownership check: player owner or super-user
    $isOwner = $player->users->contains('id', $user->id);
    $isSuperUser = ((int) $user->id === 584) || (method_exists($user, 'hasRole') && $user->hasRole('super-user'));

    if (!($isOwner || $isSuperUser)) {
      abort(403);
    }

    // Load payment order if exists
    $order = TeamPaymentOrder::where('team_id', $team->id)
      ->where('player_id', $player->id)
      ->where('event_id', $eventId)
      ->first();

    if (!$order) {
      return redirect()->route('events.show', [$eventId])->with('success', 'Player withdrawn (no payment to refund).');
    }

    if (!$order || ((int) $order->pay_status !== 1 && !$order->payfast_paid && !$order->wallet_debited)) {
      // nothing paid
      return redirect()->route('events.show', [$eventId])->with('success', 'Player withdrawn (no payment to refund).');
    }

    $gross = (float) $order->total_amount;
    $fee = round($gross * 0.10, 2);
    $net = round($gross - $fee, 2);

    return view('frontend.team.choose-refund', compact('team', 'player', 'eventId', 'order', 'gross', 'fee', 'net'));
  }

  public function storeRefund(Request $request, Team $team, Player $player, $eventId)
  {
    $user = auth()->user();

    Log::info('TEAM REFUND REQUEST START', [
      'team_id' => $team->id,
      'player_id' => $player->id,
      'event_id' => $eventId,
      'user_id' => $user?->id,
      'method' => $request->input('method'),
    ]);

    if (!$user) {
      return redirect()->route('login');
    }

    // ownership
    $isOwner = $player->users->contains('id', $user->id);
    $isSuperUser = ((int) $user->id === 584) || (method_exists($user, 'hasRole') && $user->hasRole('super-user'));

    if (!($isOwner || $isSuperUser)) {
      abort(403);
    }

    $teamPlayer = TeamPlayer::where('team_id', $team->id)
      ->where('player_id', $player->id)
      ->first();

    if (!$teamPlayer) {
      return back()->withErrors('Team player not found.');
    }

    $order = TeamPaymentOrder::where('team_id', $team->id)
      ->where('player_id', $player->id)
      ->where('event_id', $eventId)
      ->first();

    if (!$order) {
      return back()->withErrors('Payment order not found.');
    }

    // Duplicate / already processed protection (best-effort)
    if ($order->pay_status !== 1 && !$order->payfast_paid && !$order->wallet_debited) {
      return back()->withErrors('No paid amount found to refund.');
    }

    $request->validate([
      'method' => 'required|in:wallet,bank',
      'account_name' => 'required_if:method,bank|string|max:255',
      'bank_name' => 'required_if:method,bank|string|max:255',
      'account_number' => 'required_if:method,bank|string|max:50',
      'branch_code' => 'required_if:method,bank|string|max:20',
      'account_type' => 'required_if:method,bank|in:cheque,savings,business',
    ]);

    $gross = (float) $order->total_amount;
    $fee = round($gross * 0.10, 2);
    $net = round($gross - $fee, 2);

    // WALLET
    if ($request->input('method') === 'wallet') {
      try {
        DB::transaction(function () use ($order, $user, $teamPlayer, $gross, $fee, $net, $team, $player) {
          app(WalletService::class)->credit(
            $order->user->wallet,
            (float) $net,
            'team_player_refund',
            $order->id,
            [
              'team_id' => $team->id,
              'player_id' => $player->id,
              'gross' => $gross,
              'fee' => $fee,
              'method' => 'wallet',
              'reference' => optional($order->event)->name ?? 'Team Refund',
            ]
          );

          // clear slot and mark order unpaid
          $teamPlayer->player_id = 0;
          $teamPlayer->pay_status = 0;
          $teamPlayer->save();

          $order->pay_status = 0;
          $order->payfast_paid = false;
          $order->wallet_debited = false;
          $order->save();
        });

        $teamRefEventName = optional($order->event)->name ?? 'Team Refund';

        activity('wallet')
          ->performedOn($order)
          ->causedBy($user)
          ->withProperties([
            'type' => 'credit',
            'amount' => $net,
            'gross' => $gross,
            'fee' => $fee,
            'reference' => $teamRefEventName,
            'team_id' => $team->id,
            'player_id' => $player->id,
          ])
          ->log("Wallet credited R{$net} for team refund – {$teamRefEventName}");

        Log::info('TEAM WALLET REFUND COMPLETED', [
          'team_id' => $team->id,
          'player_id' => $player->id,
          'order_id' => $order->id,
          'amount' => $net,
        ]);

        activity('refund')
          ->performedOn($order)
          ->causedBy($user)
          ->withProperties([
            'method' => 'wallet',
            'team_id' => $team->id,
            'player' => trim($player->name . ' ' . $player->surname),
            'event' => optional($order->event)->name ?? '',
            'gross' => $gross,
            'fee' => $fee,
            'net' => $net,
          ])
          ->log("Team wallet refund R{$net}");

        return redirect()->route('events.show', [$eventId])->with('success', 'Refund credited to your wallet.');

      } catch (DuplicateTransactionException $e) {
        // Sync state
        $teamPlayer->player_id = 0;
        $teamPlayer->pay_status = 0;
        $teamPlayer->save();

        $order->pay_status = 0;
        $order->save();

        return redirect()->route('events.show', [$eventId])->with('success', 'Refund already processed.');
      } catch (\Throwable $e) {
        Log::error('TEAM WALLET REFUND FAILED', [
          'order_id' => $order->id,
          'error' => $e->getMessage(),
        ]);

        return redirect()->route('events.show', [$eventId])->withErrors('Refund failed. Please contact support.');
      }
    }

    // BANK: persist bank refund details and mark refund pending
    DB::transaction(function () use ($order, $teamPlayer, $request, $gross, $fee, $net) {
      $teamPlayer->player_id = 0;
      $teamPlayer->pay_status = 0;
      $teamPlayer->save();

      $order->update([
        'pay_status' => 0,
        'refund_method' => 'bank',
        'refund_status' => 'pending',
        'refund_gross' => $gross,
        'refund_fee' => $fee,
        'refund_net' => $net,
        'refund_account_name' => $request->account_name ?? null,
        'refund_bank_name' => $request->bank_name ?? null,
        'refund_account_number' => $request->account_number ?? null,
        'refund_branch_code' => $request->branch_code ?? null,
        'refund_account_type' => $request->account_type ?? null,
      ]);
    });

    // ── Auto-refund via PayFast if original payment was PayFast ──
    $pfPaymentId = $order->payfast_pf_payment_id ?? null;

    if (!empty($pfPaymentId)) {
      try {
        $payfast = new \App\Services\Payfast();
        $result = $payfast->refund($pfPaymentId, $net, 'Team withdrawal refund');

        Log::info('TEAM PAYFAST AUTO REFUND ATTEMPT', [
          'order_id' => $order->id,
          'pf_payment_id' => $pfPaymentId,
          'amount' => $net,
          'result' => $result,
        ]);

        if ($result['success']) {
          $order->update([
            'refund_status' => 'completed',
            'refunded_at' => now(),
          ]);

          activity('refund')
            ->performedOn($order)
            ->causedBy($user)
            ->withProperties([
              'method' => 'payfast',
              'pf_payment_id' => $pfPaymentId,
              'team_id' => $team->id,
              'player' => trim($player->name . ' ' . $player->surname),
              'event' => optional($order->event)->name ?? '',
              'gross' => $gross,
              'fee' => $fee,
              'net' => $net,
            ])
            ->log("Team PayFast auto refund R{$net} processed");

          return redirect()->route('events.show', [$eventId])
            ->with('success', 'Refund of R' . number_format($net, 2) . ' processed via PayFast. It may take 3–5 business days to reflect.');
        }

        Log::warning('TEAM PAYFAST AUTO REFUND FAILED — falling back to manual', [
          'order_id' => $order->id,
          'error' => $result['error'],
        ]);

      } catch (\Throwable $e) {
        Log::error('TEAM PAYFAST AUTO REFUND EXCEPTION — falling back to manual', [
          'order_id' => $order->id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    Log::info('TEAM BANK REFUND REQUEST CREATED', [
      'order_id' => $order->id,
      'amount' => $net,
      'bank_name' => $request->bank_name ?? null,
    ]);

    activity('refund')
      ->performedOn($order)
      ->causedBy($user)
      ->withProperties([
        'method' => 'bank',
        'team_id' => $team->id,
        'player' => trim($player->name . ' ' . $player->surname),
        'event' => optional($order->event)->name ?? '',
        'gross' => $gross,
        'fee' => $fee,
        'net' => $net,
        'bank' => $request->bank_name ?? '',
      ])
      ->log("Team bank refund R{$net} requested");

    // Notify admin via existing EmailController helper
    try {
      $details = [
        'subject' => "Bank refund requested: Team #{$order->team_id} - Player #{$order->player_id}",
        'body' => "A bank refund has been requested for Team ID: {$order->team_id}, Player ID: {$order->player_id}.\nAmount: R" . number_format($net, 2) . "\nBank: " . ($request->bank_name ?? 'N/A') . "\nAccount: " . ($request->account_number ?? 'N/A'),
        'replyTo' => $order->user?->email ?? null,
      ];

      app(\App\Http\Controllers\Backend\EmailController::class)->sendToOwner($details, 'smtp');
    } catch (\Throwable $e) {
      Log::error('Failed to send bank refund notification', ['error' => $e->getMessage()]);
    }

    return redirect()->route('events.show', [$eventId])->with('success', 'Bank refund request submitted. It will be processed manually.');
  }
}
