<?php

namespace App\Domain\Payments\Services;

use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPaymentOrder;
use App\Models\TeamPlayer;
use App\Models\TeamSelectionInvitation;
use App\Models\User;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamPaymentService
{
    public function __construct(private PaymentOrchestrator $paymentOrchestrator)
    {
    }

    public function ensureOrder(User $user, Team $team, Player $player, Event $event, float $total): TeamPaymentOrder
    {
        return FinanceMutationScope::run('payment_state_write', function () use ($user, $team, $player, $event, $total) {
            return DB::transaction(function () use ($user, $team, $player, $event, $total) {
                Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
                $excludedHistoricalOrderIds = TeamSelectionInvitation::query()
                    ->where('team_id', $team->id)
                    ->where('player_id', $player->id)
                    ->where('event_id', $event->id)
                    ->get(['snapshot_json'])
                    ->flatMap(function (TeamSelectionInvitation $invitation): array {
                        $legacy = data_get($invitation->snapshot_json, 'restoration.previous_order_id');
                        $history = data_get($invitation->snapshot_json, 'restoration.previous_order_ids', []);

                        return array_merge(is_array($history) ? $history : [], $legacy ? [$legacy] : []);
                    })
                    ->filter()
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->all();
                $orders = TeamPaymentOrder::query()
                    ->where('team_id', $team->id)
                    ->where('player_id', $player->id)
                    ->where('event_id', $event->id)
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->get();
                $existing = $orders->first(fn (TeamPaymentOrder $order): bool => $order->withdrawn_at === null
                    && ! in_array((int) $order->id, $excludedHistoricalOrderIds, true));

                if ($existing) {
                    if ((int) ($existing->pay_status ?? 0) !== 1 && !(bool) ($existing->payfast_paid ?? false)) {
                        $ownershipChanged = (int) $existing->user_id !== (int) $user->id;
                        $totalChanged = round((float) $existing->total_amount, 2) !== round($total, 2);
                        $existing->user_id = $user->id;
                        $existing->total_amount = $total;
                        if ($ownershipChanged || $totalChanged) {
                            $existing->wallet_reserved = 0;
                            $existing->payfast_amount_due = round($total, 2);
                            $existing->wallet_debited = false;
                        }
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
        app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->confirmPaidOrder($finalized);

        return $finalized;
    }

    public function finalizeWalletPayment(TeamPaymentOrder $order, array $context = []): TeamPaymentOrder
    {
        return DB::transaction(function () use ($order, $context) {
            $locked = TeamPaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
            $total = round((float) $locked->total_amount, 2);
            $reserved = round((float) $locked->wallet_reserved, 2);
            $payfastDue = round((float) $locked->payfast_amount_due, 2);

            if ($total <= 0 || $payfastDue !== 0.0 || $reserved !== $total) {
                throw new \RuntimeException('Wallet payment cannot complete while an unpaid balance remains.');
            }

            return $this->finalizePayment($locked, $context + [
                'payment_method' => 'wallet',
                'payfast_amount_due' => 0,
            ]);
        });
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

    public function recordWithdrawal(TeamPaymentOrder $order, User $actor): TeamPaymentOrder
    {
        return FinanceMutationScope::run('team_payment_state_write', function () use ($order, $actor) {
            return DB::transaction(function () use ($order, $actor) {
                $locked = TeamPaymentOrder::query()->lockForUpdate()->findOrFail($order->id);

                if (! $locked->withdrawn_at) {
                    $locked->withdrawn_at = now();
                    $locked->withdrawn_by = $actor->id;
                    $locked->save();
                }

                return $locked;
            });
        });
    }

    public function cancelPayment(TeamPaymentOrder $order): TeamPaymentOrder
    {
        /** @var TeamPaymentOrder $cancelled */
        $cancelled = $this->paymentOrchestrator->cancelPayment($order);

        return $cancelled;
    }

    public function markInvitationPaidPrivately(TeamSelectionInvitation $invitation, User $actor): TeamSelectionInvitation
    {
        return FinanceMutationScope::run('team_payment_state_write', function () use ($invitation, $actor) {
            return DB::transaction(function () use ($invitation, $actor) {
                $locked = TeamSelectionInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
                $import = $locked->selectionImport()->with('source')->lockForUpdate()->firstOrFail();
                $team = Team::query()->with(['category', 'regions'])->lockForUpdate()->findOrFail($locked->team_id);
                $player = Player::query()->with(['user', 'users'])->lockForUpdate()->findOrFail($locked->player_id);
                $slot = TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)
                    ->where('rank', $locked->roster_rank)->lockForUpdate()->first();

                $scopeMatches = (int) $locked->event_id === (int) $import->event_id
                    && (int) $locked->region_id === (int) $import->region_id
                    && (int) $team->region_id === (int) $locked->region_id
                    && (int) $team->category?->event_id === (int) $locked->event_id
                    && (int) $import->source?->event_id === (int) $locked->event_id
                    && (int) $import->source?->region_id === (int) $locked->region_id
                    && $locked->roster_rank !== null
                    && $slot
                    && (int) $slot->player_id === (int) $locked->player_id;

                if (! $scopeMatches) {
                    throw ValidationException::withMessages(['payment' => 'The invitation does not match an active event, region, team and roster place.']);
                }

                if ($locked->status === TeamSelectionInvitation::PAID_CONFIRMED) {
                    $existing = $locked->order_id
                        ? TeamPaymentOrder::query()->lockForUpdate()->find($locked->order_id)
                        : null;
                    if ($existing?->collection_status === 'paid_privately'
                        && (int) $existing->pay_status === 1
                        && (int) $slot->pay_status === 1) {
                        return $locked;
                    }

                    throw ValidationException::withMessages(['payment' => 'This invitation is already paid through another payment path.']);
                }

                if (! in_array($locked->status, [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true)) {
                    throw ValidationException::withMessages(['payment' => 'Only an active invited or payment-pending player can be marked paid privately.']);
                }

                $order = $locked->order_id
                    ? TeamPaymentOrder::query()->lockForUpdate()->findOrFail($locked->order_id)
                    : null;
                $associatedUserIds = collect([$player->userId])->merge($player->users->pluck('id'))
                    ->filter()->map(fn ($id): int => (int) $id)->unique()->values();
                $payer = $order
                    ? User::query()->find($order->user_id)
                    : ($associatedUserIds->count() === 1 ? User::query()->find($associatedUserIds->first()) : null);

                if (! $payer || ($order && (! $associatedUserIds->contains((int) $order->user_id)
                    || (int) $order->event_id !== (int) $locked->event_id
                    || (int) $order->team_id !== (int) $locked->team_id
                    || (int) $order->player_id !== (int) $locked->player_id))) {
                    throw ValidationException::withMessages(['payment' => 'A single canonical payer and matching checkout are required.']);
                }

                if ($order && ($order->withdrawn_at !== null
                    || $order->withdrawn_by !== null
                    || $order->hasRefund()
                    || $order->refunded_at !== null
                    || (float) $order->refund_gross !== 0.0
                    || (float) $order->refund_fee !== 0.0
                    || (float) $order->refund_net !== 0.0
                    || filled($order->refund_method)
                    || $order->refund_waived_at !== null
                    || $order->refund_waived_by !== null
                    || filled($order->refund_waiver_reason))) {
                    throw ValidationException::withMessages(['payment' => 'A withdrawn or refunded checkout is historical evidence and cannot be marked paid privately.']);
                }

                if ($order && ($order->pay_status || $order->payfast_paid || $order->wallet_debited)) {
                    throw ValidationException::withMessages(['payment' => 'This checkout is already recorded as paid and must be reconciled instead.']);
                }

                $event = Event::query()->lockForUpdate()->findOrFail($locked->event_id);
                $total = round((float) $event->entryFee + (float) $team->regions?->region_fee, 2);
                if ($total < 0) {
                    throw ValidationException::withMessages(['payment' => 'The server-calculated registration amount is invalid.']);
                }

                if (! $order) {
                    $order = $this->ensureOrder($payer, $team, $player, $event, $total);
                    $order = TeamPaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
                }

                $this->paymentOrchestrator->cancelPayment($order);
                $order = TeamPaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
                $order->forceFill([
                    'user_id' => $payer->id,
                    'total_amount' => $total,
                    'wallet_reserved' => 0,
                    'payfast_amount_due' => 0,
                    'wallet_debited' => false,
                    'payfast_paid' => false,
                    'pay_status' => true,
                    'collection_status' => 'paid_privately',
                    'paid_privately_at' => now(),
                    'paid_privately_by' => $actor->id,
                ])->save();

                $slot->pay_status = 1;
                $slot->save();
                $locked->forceFill([
                    'order_id' => $order->id,
                    'status' => TeamSelectionInvitation::PAID_CONFIRMED,
                    'paid_at' => now(),
                ])->save();

                activity('team-selection')->performedOn($locked)->causedBy($actor)
                    ->withProperties([
                        'event_id' => $locked->event_id,
                        'region_id' => $locked->region_id,
                        'team_id' => $locked->team_id,
                        'player_id' => $locked->player_id,
                        'order_id' => $order->id,
                        'amount' => $total,
                        'collection_status' => 'paid_privately',
                        'reconciled_payment' => false,
                    ])->log('team selection invitation marked paid privately by manager');

                return $locked->refresh();
            });
        });
    }
}
