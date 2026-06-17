<?php

namespace App\Domain\Payments\Services;

use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPaymentOrder;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;

class TeamPaymentService
{
    public function __construct(private PaymentOrchestrator $paymentOrchestrator)
    {
    }

    public function ensureOrder(User $user, Team $team, Player $player, Event $event, float $total): TeamPaymentOrder
    {
        return FinanceMutationScope::run('payment_state_write', function () use ($user, $team, $player, $event, $total) {
            return DB::transaction(function () use ($user, $team, $player, $event, $total) {
                $existing = TeamPaymentOrder::query()
                    ->where('team_id', $team->id)
                    ->where('player_id', $player->id)
                    ->where('event_id', $event->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ((int) ($existing->pay_status ?? 0) !== 1 && !(bool) ($existing->payfast_paid ?? false)) {
                        $existing->user_id = $user->id;
                        $existing->total_amount = $total;
                        $existing->save();
                    }

                    return $existing;
                }

                return TeamPaymentOrder::create([
                    'user_id' => $user->id,
                    'team_id' => $team->id,
                    'player_id' => $player->id,
                    'event_id' => $event->id,
                    'total_amount' => $total,
                    'wallet_reserved' => 0,
                    'payfast_amount_due' => $total,
                    'wallet_debited' => false,
                    'payfast_paid' => false,
                    'pay_status' => false,
                ]);
            });
        });
    }

    public function reservePayment(TeamPaymentOrder $order, float $walletApplied, float $remainingAmount): TeamPaymentOrder
    {
        /** @var TeamPaymentOrder $reserved */
        $reserved = $this->paymentOrchestrator->initiatePayment($order, $walletApplied, $remainingAmount);

        return $reserved;
    }

    public function finalizePayment(TeamPaymentOrder $order, array $context = []): TeamPaymentOrder
    {
        /** @var TeamPaymentOrder $finalized */
        $finalized = $this->paymentOrchestrator->finalizePayment($order, $context);
        $this->markPlayerPaid($finalized);

        return $finalized;
    }

    public function markPlayerPaid(TeamPaymentOrder $order): ?TeamPlayer
    {
        return FinanceMutationScope::run('team_payment_state_write', function () use ($order) {
            return DB::transaction(function () use ($order) {
                $teamPlayer = TeamPlayer::query()
                    ->where('team_id', $order->team_id)
                    ->where('player_id', $order->player_id)
                    ->lockForUpdate()
                    ->first();

                if (!$teamPlayer) {
                    return null;
                }

                $teamPlayer->pay_status = 1;
                $teamPlayer->save();

                return $teamPlayer;
            });
        });
    }

    public function clearPlayerPayment(TeamPaymentOrder $order, bool $clearSlot = false): ?TeamPlayer
    {
        return FinanceMutationScope::run('team_payment_state_write', function () use ($order, $clearSlot) {
            return DB::transaction(function () use ($order, $clearSlot) {
                $teamPlayer = TeamPlayer::query()
                    ->where('team_id', $order->team_id)
                    ->where('player_id', $order->player_id)
                    ->lockForUpdate()
                    ->first();

                if (!$teamPlayer) {
                    return null;
                }

                if ($clearSlot) {
                    $teamPlayer->player_id = 0;
                }

                $teamPlayer->pay_status = 0;
                $teamPlayer->save();

                return $teamPlayer;
            });
        });
    }

    public function updateTeamPlayerSlot(TeamPlayer $teamPlayer, array $attributes): TeamPlayer
    {
        return FinanceMutationScope::run('team_payment_state_write', function () use ($teamPlayer, $attributes) {
            return DB::transaction(function () use ($teamPlayer, $attributes) {
                $locked = TeamPlayer::query()->lockForUpdate()->findOrFail($teamPlayer->id);
                $locked->fill($attributes);
                $locked->save();

                return $locked;
            });
        });
    }
}
