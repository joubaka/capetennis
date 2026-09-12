<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Domain\Refunds\Services\RefundExecutionService;
use App\Domain\Refunds\Services\TeamRefundCalculator;
use App\Domain\Finance\Services\RefundRequestService;
use App\Models\Player;
use App\Models\SiteSetting;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamPaymentOrder;
use App\Models\TeamFixturePlayer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\Wallet\Exceptions\DuplicateTransactionException;
use App\Exceptions\RefundAlreadyProcessedException;

class TeamPlayerWithdrawController extends Controller
{
 
  public function withdraw(Request $request, Team $team, Player $player, $eventId)
  {
    $user = Auth::user();
    if (!$user) {
      return redirect()->route('login');
    }

    // Global withdrawal switch
    if (SiteSetting::get('withdrawal_allowed', '1') !== '1') {
      return back()->withErrors('Withdrawals are currently disabled. Please contact support@capetennis.co.za for assistance.');
    }

    $event = \App\Models\Event::findOrFail($eventId);
    if (! app(\App\Domain\Teams\Services\ExternalTeamRosterService::class)->teamBelongsToEvent($team, $event)) {
      abort(404, 'Team does not belong to this event.');
    }

    $withdrawalOrder = $this->paymentOrder($team, $player, (int) $eventId);
    $access = $this->withdrawalAccess($user, $player, $withdrawalOrder);
    if (! $access['allowed']) {
      return back()->withErrors('Only the payer or a user linked to this player may withdraw the team entry.');
    }

    // Find the team slot for this player
    $teamPlayer = TeamPlayer::where('team_id', $team->id)
      ->where('player_id', $player->id)
      ->first();

    if (!$teamPlayer) {
      return back()->withErrors('Team player not found.');
    }

    // Check withdrawal deadline
    $refundAllowed = now()->lte($event->withdrawalCloseAt());

    // A paid withdrawal is not committed until the player chooses a refund
    // method. Opening the choice screen must not alter the roster, fixtures,
    // payment badge or canonical invitation state.
    if ((int) $teamPlayer->pay_status === 1) {
      if ($withdrawalOrder && ! $withdrawalOrder->withdrawn_at) {
        $withdrawalOrder = app(\App\Domain\Payments\Services\TeamPaymentService::class)
          ->recordWithdrawal($withdrawalOrder, $user);
      }

      if ($refundAllowed) {
        return redirect()
          ->route('team.player.refund.choose', [$team->id, $player->id, $eventId])
          ->with('success', 'Choose a refund method to complete the withdrawal. Your team place remains active until you submit this choice.');
      }

      // No refund workflow follows a late withdrawal, so free the roster slot now.
      $this->finalizeTeamWithdrawal($teamPlayer, $player, (int) $eventId, $user, $withdrawalOrder, false);

      return back()->with('success', 'Player withdrawn from team. Refund is not available because the withdrawal deadline has passed. For assistance, contact support@capetennis.co.za.');
    }

    // Unpaid: clear the slot to make it available
    $this->removePlayerFromUnplayedFixtures($player, (int) $eventId);
    app(\App\Domain\Payments\Services\TeamPaymentService::class)
      ->updateTeamPlayerSlot($teamPlayer, ['player_id' => 0, 'pay_status' => 0]);
    app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)
      ->markWithdrawn((int) $eventId, (int) $team->id, (int) $player->id, $user);

    return back()->with('success', 'Player withdrawn (no payment). Slot is now available.');
  }

  /**
   * Remove a withdrawn player from future rubbers in this event without
   * rewriting completed fixture history or the opposing side's assignment.
   */
  private function removePlayerFromUnplayedFixtures(Player $player, int $eventId): void
  {
    $base = TeamFixturePlayer::query()
      ->whereHas('fixture.draw', fn($query) => $query->where('event_id', $eventId))
      ->whereDoesntHave('fixture.fixtureResults');

    (clone $base)->where('team1_id', $player->id)->update(['team1_id' => null]);
    (clone $base)->where('team2_id', $player->id)->update(['team2_id' => null]);
  }

  public function chooseRefund(Team $team, Player $player, $eventId)
  {
    $user = auth()->user();

    if (!$user) {
      return redirect()->route('login');
    }

    $event = \App\Models\Event::findOrFail($eventId);
    if (! app(\App\Domain\Teams\Services\ExternalTeamRosterService::class)->teamBelongsToEvent($team, $event)) {
      abort(404, 'Team does not belong to this event.');
    }

    // Load payment order if exists
    $order = $this->paymentOrder($team, $player, (int) $eventId);

    if (!$order) {
      return back()->withErrors('The paid team place has no payment order. No withdrawal changes were made; please contact support.');
    }

    $access = $this->withdrawalAccess($user, $player, $order);
    abort_unless($access['allowed'], 403);

    if (! $access['payer'] && ! $access['super_user']) {
      abort(403, 'Only the payer may choose a refund method.');
    }

    $teamPlayer = TeamPlayer::where('team_id', $team->id)
      ->where('player_id', $player->id)
      ->first();

    if (!$teamPlayer || (int) $teamPlayer->pay_status !== 1) {
      return back()->withErrors('This paid team place is no longer available for withdrawal.');
    }

    if (! $order->withdrawn_at && now()->lte($event->withdrawalCloseAt())) {
      $order = app(\App\Domain\Payments\Services\TeamPaymentService::class)
        ->recordWithdrawal($order, $user);
    }

    if (! $order->withdrawn_at) {
      return back()->withErrors('Player withdrawal time was not recorded. Please withdraw the player again or contact support.');
    }

    if ($order->withdrawn_at->gt($event->withdrawalCloseAt())) {
      return back()->withErrors('The withdrawal deadline passed before this player was withdrawn.');
    }

    if ((int) $order->pay_status !== 1 && !$order->payfast_paid && !$order->wallet_debited) {
      return back()->withErrors('No verified paid amount was found. No withdrawal changes were made.');
    }

    ['gross' => $gross, 'fee' => $fee, 'net' => $net, 'payfastGross' => $payfastGross, 'payfastNet' => $payfastNet] = app(TeamRefundCalculator::class)->calculate($order);

    $bankNames = BankDetailsController::BANK_NAMES;

    return view('frontend.team.choose-refund', compact('team', 'player', 'eventId', 'order', 'gross', 'fee', 'net', 'payfastGross', 'payfastNet', 'bankNames'));
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

    $event = \App\Models\Event::findOrFail($eventId);
    if (! app(\App\Domain\Teams\Services\ExternalTeamRosterService::class)->teamBelongsToEvent($team, $event)) {
      abort(404, 'Team does not belong to this event.');
    }

    $order = $this->paymentOrder($team, $player, (int) $eventId);

    if (!$order) {
      return back()->withErrors('Payment order not found.');
    }

    $access = $this->withdrawalAccess($user, $player, $order);
    abort_unless($access['allowed'], 403);

    if (! $access['payer'] && ! $access['super_user']) {
      abort(403, 'Only the payer may request this refund.');
    }

    $teamPlayer = TeamPlayer::where('team_id', $team->id)
      ->where('player_id', $player->id)
      ->first();

    if (!$teamPlayer) {
      return back()->withErrors('Team player not found.');
    }

    if ((int) $teamPlayer->pay_status !== 1) {
      return back()->withErrors('This paid team place is no longer available for withdrawal.');
    }

    if (! $order->withdrawn_at && now()->lte($event->withdrawalCloseAt())) {
      $order = app(\App\Domain\Payments\Services\TeamPaymentService::class)
        ->recordWithdrawal($order, $user);
    }

    if (! $order->withdrawn_at) {
      return back()->withErrors('Player withdrawal time was not recorded. Please withdraw the player again or contact support.');
    }

    if ($order->withdrawn_at->gt($event->withdrawalCloseAt())) {
      return back()->withErrors('The withdrawal deadline passed before this player was withdrawn.');
    }

    if ($order->isRefundCompleted() || $order->isRefundPending()) {
      return back()->with('success', 'Refund already processed.');
    }

    if ($order->pay_status !== 1 && !$order->payfast_paid && !$order->wallet_debited) {
      return back()->withErrors('No paid amount found to refund.');
    }

    $request->validate([
      'method' => 'required|in:wallet,bank',
      'account_name' => 'required_if:method,bank|string|max:255',
      'bank_name' => 'required_if:method,bank|string|max:255',
      'account_number' => 'required_if:method,bank|digits_between:5,12',
      'branch_code' => 'required_if:method,bank|digits_between:4,6',
      'account_type' => 'required_if:method,bank|in:current,savings',
    ]);

    [
      'gross' => $gross,
      'fee' => $fee,
      'net' => $net,
      'payfastGross' => $payfastGross,
      'payfastNet' => $payfastNet,
      'walletNet' => $walletNet,
    ] = app(TeamRefundCalculator::class)->calculate($order);

    if ($request->input('method') === 'bank' && $payfastNet <= 0) {
      return back()->withErrors('Wallet-funded team payments can only be refunded to the wallet.');
    }

    // Over-refund guard
    if ($order->maxRefundableAmount() <= 0) {
      return back()->withErrors('No refundable amount remaining for this order.');
    }

    // WALLET
    if ($request->input('method') === 'wallet') {
      try {
        $wallet = $order->user->wallet;

        if (!$wallet) {
          return redirect()->route('events.show', [$eventId])->withErrors('Wallet not found for this user.');
        }

        app(RefundExecutionService::class)->executeWalletRefund(
          $order,
          $wallet,
          (float) $net,
          'team_player_refund',
          $order->id,
          [
            'team_id'   => $team->id,
            'player_id' => $player->id,
            'gross'     => $gross,
            'fee'       => $fee,
            'method'    => 'wallet',
            'reference' => optional($order->event)->name ?? 'Team Refund',
          ],
          [
            'refund_method' => 'wallet',
            'refund_gross'  => $gross,
            'refund_fee'    => $fee,
            'refund_net'    => $net,
          ]
        );

        // Only free the slot after the canonical refund transaction succeeds.
        // Keep the order's paid flags intact as an audit record of the original payment.
        $this->finalizeTeamWithdrawal($teamPlayer, $player, (int) $eventId, $user, $order, true);

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

        app(\App\Services\TeamCommunicationService::class)->player($order->fresh(), 'refund_completed', [
          'gross' => $gross, 'fee' => $fee, 'net' => $net, 'wallet_net' => $net,
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

      } catch (DuplicateTransactionException | RefundAlreadyProcessedException $e) {
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
    try {
      app(RefundRequestService::class)->requestTeamRefund($order, [
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
      ], $user);
      $this->finalizeTeamWithdrawal($teamPlayer, $player, (int) $eventId, $user, $order, true);
    } catch (RefundAlreadyProcessedException $e) {
      return back()->with('success', 'Refund already processed.');
    }

    // ── Auto-refund via PayFast if original payment was PayFast ──
    $pfPaymentId = $order->payfast_pf_payment_id ?? null;

    if (!empty($pfPaymentId)) {
      try {
        $payfast = new \App\Services\Payfast();
        $result = $payfast->refundUsingAvailableMethod(
          $pfPaymentId,
          $payfastNet,
          'Team withdrawal refund',
          [
            'account_holder' => $request->account_name,
            'bank_name' => $request->bank_name,
            'branch_code' => $request->branch_code,
            'account_number' => $request->account_number,
            'account_type' => $request->account_type,
          ]
        );

        Log::info('TEAM PAYFAST AUTO REFUND ATTEMPT', [
          'order_id' => $order->id,
          'pf_payment_id' => $pfPaymentId,
          'amount' => $payfastNet,
          'result' => $result,
        ]);

        if ($result['success']) {
          app(RefundExecutionService::class)->executeSplitRefund(
            $order,
            $order->user?->wallet,
            $walletNet,
            'team_player_hybrid_refund',
            $order->id,
            ['team_id' => $team->id, 'player_id' => $player->id, 'gross' => $gross, 'fee' => $fee],
            [
            'refund_method' => 'payfast',
            'refund_gross'  => $gross,
            'refund_fee'    => $fee,
            'refund_net'    => $net,
            ]
          );

          app(\App\Services\TeamCommunicationService::class)->player($order->fresh(), 'refund_completed', [
            'gross' => $gross, 'fee' => $fee, 'net' => $net,
            'payfast_net' => $payfastNet, 'wallet_net' => $walletNet,
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

    app(\App\Services\TeamCommunicationService::class)->player($order->fresh(), 'refund_requested', [
      'gross' => $gross, 'fee' => $fee, 'net' => $net,
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

      app(\App\Http\Controllers\Backend\EmailController::class)->sendToOwner($details, 'smtp', 'email_on_bank_refund_request');
    } catch (\Throwable $e) {
      Log::error('Failed to send bank refund notification', ['error' => $e->getMessage()]);
    }

    return redirect()->route('events.show', [$eventId])->with('success', 'Bank refund request submitted. It will be processed manually.');
  }

  private function paymentOrder(Team $team, Player $player, int $eventId): ?TeamPaymentOrder
  {
    return TeamPaymentOrder::query()
      ->where('team_id', $team->id)
      ->where('player_id', $player->id)
      ->where('event_id', $eventId)
      ->first();
  }

  /**
   * A payer or any account linked to the player may initiate withdrawal.
   * Financial refund choices remain restricted to the payer or a super-user.
   *
   * @return array{allowed: bool, payer: bool, linked: bool, super_user: bool}
   */
  private function withdrawalAccess(User $user, Player $player, ?TeamPaymentOrder $order): array
  {
    $payer = $order && (int) $order->user_id === (int) $user->id;
    $linked = in_array((int) $player->id, $user->ownedPlayerIds(), true);
    $superUser = (int) $user->id === 584
      || (method_exists($user, 'hasRole') && $user->hasRole('super-user'));

    return [
      'allowed' => $payer || $linked || $superUser,
      'payer' => $payer,
      'linked' => $linked,
      'super_user' => $superUser,
    ];
  }

  private function finalizeTeamWithdrawal(
    TeamPlayer $teamPlayer,
    Player $player,
    int $eventId,
    $actor,
    ?TeamPaymentOrder $order,
    bool $refundAvailable
  ): void {
    $teamId = (int) $teamPlayer->team_id;
    $playerId = (int) $player->id;

    DB::transaction(function () use ($teamPlayer, $player, $eventId, $teamId, $playerId, $actor): void {
      $this->removePlayerFromUnplayedFixtures($player, $eventId);
      $teamPlayer->forceFill(['player_id' => 0, 'pay_status' => 0])->save();
      app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)
        ->markWithdrawn($eventId, $teamId, $playerId, $actor);
    });

    if ($order) {
      try {
        app(\App\Services\TeamCommunicationService::class)->withdrawal($order->fresh(), [
          'refund_available' => $refundAvailable,
        ]);
      } catch (\Throwable $exception) {
        Log::error('TEAM WITHDRAWAL COMMUNICATION FAILED', [
          'order_id' => $order->id,
          'error' => $exception->getMessage(),
        ]);
      }
    }
  }
}
