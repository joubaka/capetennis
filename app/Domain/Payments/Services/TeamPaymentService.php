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
    public function transferImportedCoverage(Event $event, Team $team, int $orderId, int $expectedPlayerId, int $targetPlayerId, string $reason, User $actor): void
    {
        $this->transferCoverage($event, $team, $orderId, $expectedPlayerId, $targetPlayerId, $reason, $actor);
    }

    public function transferInvitationCoverage(TeamSelectionInvitation $source, int $orderId, int $expectedPlayerId, int $targetPlayerId, string $reason, User $actor): void
    {
        $this->transferCoverage($source->event, $source->team, $orderId, $expectedPlayerId, $targetPlayerId, $reason, $actor, $source);
    }

    private function transferCoverage(Event $event, Team $team, int $orderId, int $expectedPlayerId, int $targetPlayerId, string $reason, User $actor, ?TeamSelectionInvitation $invitation = null): void
    {
        abort_unless($actor->hasRole('super-user'), 403);
        FinanceMutationScope::run('team_payment_state_write', function () use ($event, $team, $orderId, $expectedPlayerId, $targetPlayerId, $reason, $actor, $invitation): void {
            DB::transaction(function () use ($event, $team, $orderId, $expectedPlayerId, $targetPlayerId, $reason, $actor, $invitation): void {
                $event = Event::query()->lockForUpdate()->findOrFail($event->id);
                $selectionImport = $invitation ? \App\Models\TeamSelectionImport::query()->lockForUpdate()->findOrFail($invitation->import_id) : null;
                $lockedTeam = Team::query()->lockForUpdate()->findOrFail($team->id);
                abort_unless(($invitation || $lockedTeam->noProfile) && $lockedTeam->category()->where('event_id', $event->id)->exists(), 404);
                if (! $invitation) {
                    app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->assertRosterEditable($lockedTeam);
                }
                $fail = fn (string $message) => throw ValidationException::withMessages(['payment_transfer' => $message]);
                if ($expectedPlayerId === $targetPlayerId || trim($reason) === '') {
                    $fail('Choose a different linked player and provide the transfer reason.');
                }
                $ids = [$expectedPlayerId, $targetPlayerId];
                $slots = \App\Models\NoProfileTeamPlayer::query()->where('team_id', $team->id)->whereIn('player_profile', $ids)->orderBy('id')->lockForUpdate()->get();
                $mirrors = TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $team->id)->whereIn('player_id', $ids)->orderBy('id')->lockForUpdate()->get();
                $source = $mirrors->firstWhere('player_id', $expectedPlayerId);
                $target = $mirrors->firstWhere('player_id', $targetPlayerId);
                if ($mirrors->count() !== 2 || ! $source || ! $target
                    || (int) $source->pay_status !== 1 || (int) $target->pay_status !== 0
                    || ($lockedTeam->noProfile && ($slots->count() !== 2
                        || $slots->firstWhere('player_profile', $expectedPlayerId)?->rank != $source->rank
                        || (int) $slots->firstWhere('player_profile', $expectedPlayerId)?->pay_status !== 1
                        || $slots->firstWhere('player_profile', $targetPlayerId)?->rank != $target->rank
                        || $slots->firstWhere('player_profile', $targetPlayerId)?->pay_status))
                    || (! $lockedTeam->noProfile && $slots->isNotEmpty())) {
                    $fail('Both players must occupy consistent linked roster positions, with the source paid and destination unpaid.');
                }
                foreach ($mirrors as $mirror) {
                    if ((int) $mirror->rank < 1 || TeamPlayer::where('team_id', $team->id)->where('rank', $mirror->rank)->count() !== 1) {
                        $fail('The roster positions are inconsistent.');
                    }
                }
                $invitations = TeamSelectionInvitation::query()->where('event_id', $event->id)->whereIn('player_id', $ids)->orderBy('id')->lockForUpdate()->get();
                $sourceInvitation = $invitation ? $invitations->firstWhere('id', $invitation->id) : null;
                $targetInvitation = $invitation ? $invitations->firstWhere('player_id', $targetPlayerId) : null;
                if ($invitation && (! $sourceInvitation || ! $targetInvitation || $invitations->count() !== 2
                    || (int) $selectionImport->event_id !== (int) $event->id
                    || (int) $selectionImport->region_id !== (int) $lockedTeam->region_id || $selectionImport->status !== 'sent'
                    || \App\Models\TeamSelectionImport::where('event_id', $event->id)->where('region_id', $lockedTeam->region_id)->where('id', '>', $selectionImport->id)->exists()
                    || (int) $sourceInvitation->player_id !== $expectedPlayerId || (int) $sourceInvitation->order_id !== $orderId
                    || $sourceInvitation->status !== TeamSelectionInvitation::PAID_CONFIRMED
                    || $targetInvitation->status !== TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT)) {
                    $fail('Choose a selected unpaid player who has started registration in this active invitation campaign. Reserves must be activated and start registration first.');
                }
                if ($invitation) {
                    foreach ([$sourceInvitation, $targetInvitation] as $selected) {
                        $mirror = $mirrors->firstWhere('player_id', $selected->player_id);
                        if ((int) $selected->import_id !== (int) $selectionImport->id || (int) $selected->team_id !== (int) $team->id
                            || (int) $selected->region_id !== (int) $lockedTeam->region_id || ! $selected->roster_rank
                            || (int) $selected->roster_rank !== (int) $mirror->rank) {
                            $fail('The selected players no longer match this campaign and roster. Refresh before transferring.');
                        }
                    }
                }
                foreach ($slots as $slot) {
                    if (\App\Models\NoProfileTeamPlayer::where('team_id', $team->id)->where('rank', $slot->rank)->count() !== 1
                        || TeamPlayer::where('team_id', $team->id)->where('rank', $slot->rank)->count() !== 1) {
                        $fail('The roster positions are inconsistent.');
                    }
                }
                if (TeamPlayer::query()->where('team_id', '!=', $team->id)->whereIn('player_id', $ids)
                    ->whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))->lockForUpdate()->get()->isNotEmpty()
                    || \App\Models\NoProfileTeamPlayer::query()->where('team_id', '!=', $team->id)->whereIn('player_profile', $ids)
                        ->whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))->lockForUpdate()->get()->isNotEmpty()) {
                    $fail('A selected profile is linked to another roster in this event.');
                }
                $players = Player::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                $targetProfile = $players->firstWhere('id', $targetPlayerId);
                if (! $targetProfile) {
                    $fail('The destination profile no longer exists.');
                }
                try {
                    app(\App\Services\PlayerEligibilityService::class)->assertEligible($targetProfile, $event);
                    if ($invitation) {
                        app(\App\Domain\Teams\Services\ExternalTeamRosterService::class)->assertSelectedPlayerCanRegister($event, $lockedTeam, $targetProfile);
                        app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->beginRegistration($event->id, $team->id, $targetPlayerId, $actor);
                    }
                } catch (\RuntimeException $exception) {
                    $fail($exception->getMessage());
                }
                $orders = TeamPaymentOrder::query()->where('event_id', $event->id)->forPlayerHistory($ids)->orderBy('id')->lockForUpdate()->get();
                $order = $orders->firstWhere('id', $orderId);
                if (! $order || (int) $order->team_id !== (int) $team->id || $order->effective_player_id !== $expectedPlayerId
                    || ! $order->pay_status || $order->withdrawn_at || $order->hasRefund()
                    || $order->refunded_at || $order->refund_waived_at
                    || (float) $order->refund_gross !== 0.0 || (float) $order->refund_fee !== 0.0 || (float) $order->refund_net !== 0.0
                    || $order->refund_method) {
                    $fail('The paid order changed or has withdrawal/refund history. Refresh before transferring coverage.');
                }
                $fee = round((float) Event::findOrFail($event->id)->entryFee + (float) \App\Models\TeamRegion::find($team->region_id)?->region_fee, 2);
                if ($fee <= 0 || round((float) $order->total_amount, 2) !== $fee) {
                    $fail('The settled amount must exactly match the current team registration fee.');
                }
                $walletDebit = \App\Models\WalletTransaction::query()->where('source_type', 'team_registration_wallet_payment')
                    ->where('source_id', $order->id)->lockForUpdate()->get();
                $walletPaid = $order->wallet_debited && round((float) $walletDebit->where('type', 'debit')->sum('amount'), 2) === round((float) $order->wallet_reserved, 2)
                    && (float) $order->wallet_reserved > 0 && $walletDebit->count() === 1
                    && $order->user->wallet()->whereKey($walletDebit->first()?->wallet_id)->exists();
                $providerReceipts = $order->payfast_paid && (float) $order->payfast_amount_due > 0 && filled($order->payfast_pf_payment_id)
                    ? \App\Models\Transaction::query()->where('custom_str5', 'TeamOrder')->where('custom_int5', $order->id)
                        ->where('pf_payment_id', $order->payfast_pf_payment_id)->lockForUpdate()->get()
                    : collect();
                $providerReceipt = $providerReceipts->count() === 1 ? $providerReceipts->first() : null;
                // The verified COMPLETE callback finalizes the order and writes its receipt atomically.
                // Legacy receipts omit payment_status; the canonical writer also leaves it null.
                // Reject a contradictory status when one is stored, without querying a legacy column.
                $providerStatus = $providerReceipt?->getAttributes()['payment_status'] ?? null;
                $providerPaid = $order->payfast_paid && filled($order->payfast_pf_payment_id) && $providerReceipt
                    && ($providerStatus === null || $providerStatus === 'COMPLETE')
                    && round((float) $providerReceipt->amount_gross, 2) === round((float) $order->payfast_amount_due, 2)
                    && (int) $providerReceipt->custom_int2 === (int) $order->player_id
                    && (int) $providerReceipt->custom_int3 === (int) $order->event_id
                    && (int) $providerReceipt->custom_int4 === (int) $order->user_id;
                if ($order->collection_status === 'paid_privately' || $this->hasUnresolvedPayfastHandoff($order)
                    || ((float) $order->wallet_reserved > 0 && ! $walletPaid)
                    || ((float) $order->payfast_amount_due > 0 && ! $providerPaid)
                    || round((float) $order->wallet_reserved + (float) $order->payfast_amount_due, 2) !== $fee
                    || ! (($walletPaid || $providerPaid)
                    && round(($walletPaid ? (float) $order->wallet_reserved : 0) + ($providerPaid ? (float) $order->payfast_amount_due : 0), 2) === $fee)) {
                    $fail('Verified settlement evidence is required for payment coverage transfer.');
                }
                if (\App\Models\TrialParticipation::whereIn('order_id', $orders->pluck('id'))->exists()
                    || (! $invitation && $invitations->isNotEmpty())
                    || \App\Models\CategoryEventRegistration::withTrashed()->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))
                        ->whereHas('registration.players', fn ($q) => $q->whereIn('players.id', $ids))->lockForUpdate()->get()->isNotEmpty()
                    || \App\Models\TeamFixturePlayer::whereHas('fixture.draw', fn ($q) => $q->where('event_id', $event->id))
                        ->where(fn ($q) => $q->whereIn('team1_id', $ids)->orWhereIn('team2_id', $ids)
                            ->orWhereIn('team1_no_profile_id', $slots->pluck('id'))->orWhereIn('team2_no_profile_id', $slots->pluck('id')))->lockForUpdate()->get()->isNotEmpty()) {
                    $fail('Selection, registration or fixture participation must be resolved through its existing workflow.');
                }
                foreach ($orders as $targetOrder) {
                    if ((int) $targetOrder->id !== (int) $order->id && $targetOrder->effective_player_id === $expectedPlayerId) {
                        if ($targetOrder->withdrawn_at === null) {
                            $fail('The source has another active checkout in this event.');
                        }
                        $this->assertUnpaidRosterCheckoutMayBeReset($targetOrder);
                    }
                    if ((int) $targetOrder->id === (int) $order->id || $targetOrder->effective_player_id !== $targetPlayerId) {
                        continue;
                    }
                    if ((int) $targetOrder->team_id !== (int) $team->id) {
                        $fail('The destination has checkout history on another team.');
                    }
                    $this->assertUnpaidRosterCheckoutMayBeReset($targetOrder);
                }
                if ($invitation && $targetInvitation->order_id && ! $orders->contains(fn ($candidate) => (int) $candidate->id === (int) $targetInvitation->order_id && $candidate->effective_player_id === $targetPlayerId)) {
                    $fail('The destination invitation has inconsistent checkout history.');
                }
                foreach ($orders as $targetOrder) {
                    if ((int) $targetOrder->id !== (int) $order->id && $targetOrder->effective_player_id === $targetPlayerId) {
                        $this->closeUnpaidLifecycle($targetOrder, $actor);
                    }
                }
                $order->forceFill(['beneficiary_player_id' => $targetPlayerId])->save();
                if ($invitation) {
                    $sourceInvitation->update(['order_id' => null, 'status' => TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, 'paid_at' => null]);
                    $targetInvitation->update(['order_id' => $order->id, 'status' => TeamSelectionInvitation::PAID_CONFIRMED, 'paid_at' => now(), 'accepted_at' => $targetInvitation->accepted_at ?: now()]);
                }
                $this->updateTeamPlayerSlot($source, ['pay_status' => 0]);
                $this->updateTeamPlayerSlot($target, ['pay_status' => 1]);
                foreach ($slots as $slot) {
                    $slot->update(['pay_status' => (int) $slot->player_profile === $targetPlayerId ? 1 : 0]);
                }
                DB::table('team_payment_transfers')->insert([
                    'order_id' => $order->id, 'event_id' => $event->id, 'team_id' => $team->id,
                    'original_player_id' => $order->player_id, 'from_player_id' => $expectedPlayerId,
                    'to_player_id' => $targetPlayerId, 'actor_id' => $actor->id, 'reason' => trim($reason), 'created_at' => now(),
                ]);
                activity('team-payment')->performedOn($order)->causedBy($actor)->withProperties([
                    'from_player_id' => $expectedPlayerId, 'to_player_id' => $targetPlayerId, 'reason' => trim($reason),
                ])->log('transferred settled team payment coverage');
            }, 3);
        });
    }

    public function __construct(private PaymentOrchestrator $paymentOrchestrator)
    {
    }

    public function ensureOrder(User $user, Team $team, Player $player, Event $event, float $total): TeamPaymentOrder
    {
        return FinanceMutationScope::run('payment_state_write', function () use ($user, $team, $player, $event, $total) {
            return DB::transaction(function () use ($user, $team, $player, $event, $total) {
                $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
                if ($lockedTeam->noProfile) {
                    $slot = \App\Models\NoProfileTeamPlayer::query()->where('team_id', $lockedTeam->id)
                        ->where('player_profile', $player->id)->lockForUpdate()->get();
                    $linked = $slot->count() === 1 ? TeamPlayer::query()->where('team_id', $lockedTeam->id)
                        ->where('rank', $slot->first()->rank)->lockForUpdate()->get() : collect();
                    if ($linked->count() !== 1 || (int) $linked->first()->player_id !== (int) $player->id) {
                        throw ValidationException::withMessages(['player' => 'The roster profile link changed. Refresh the team before starting checkout.']);
                    }
                }
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
                    ->forBeneficiary($player->id)
                    ->where('event_id', $event->id)
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->get();
                $existing = $orders->first(fn (TeamPaymentOrder $order): bool => $order->withdrawn_at === null
                    && ! in_array((int) $order->id, $excludedHistoricalOrderIds, true));

                if ($existing) {
                    if ((int) ($existing->pay_status ?? 0) !== 1 && !(bool) ($existing->payfast_paid ?? false)) {
                        if ((int) $existing->user_id !== (int) $user->id) {
                            if ($this->hasUnresolvedPayfastHandoff($existing)) {
                                throw ValidationException::withMessages([
                                    'payment' => 'This checkout is currently being processed by PayFast. Please try again later.',
                                ]);
                            }
                            if (! $this->isWhollyUnpaid($existing)) {
                                throw ValidationException::withMessages([
                                    'payment' => 'This checkout contains payment evidence and cannot be restarted by another payer.',
                                ]);
                            }

                            $previousPayerId = (int) $existing->user_id;
                            $superseded = $this->closeUnpaidLifecycle($existing, $user);
                            $replacement = TeamPaymentOrder::create([
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
                            TeamSelectionInvitation::query()
                                ->where('event_id', $event->id)
                                ->where('team_id', $team->id)
                                ->where('player_id', $player->id)
                                ->where('status', TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT)
                                ->where('order_id', $superseded->id)
                                ->update(['order_id' => $replacement->id]);
                            activity('team-selection')->performedOn($replacement)->causedBy($user)
                                ->withProperties([
                                    'event_id' => $event->id,
                                    'team_id' => $team->id,
                                    'player_id' => $player->id,
                                    'superseded_order_id' => $superseded->id,
                                    'previous_payer_id' => $previousPayerId,
                                    'replacement_order_id' => $replacement->id,
                                    'amount' => round($total, 2),
                                ])->log('restarted abandoned team checkout for a new payer');

                            return $replacement;
                        }
                        if ($this->hasUnresolvedPayfastHandoff($existing)) {
                            return $existing;
                        }
                        $totalChanged = round((float) $existing->total_amount, 2) !== round($total, 2);
                        $existing->total_amount = $total;
                        if ($totalChanged) {
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
        if (\App\Models\TrialParticipation::where('order_id', $order->id)->exists()) {
            throw ValidationException::withMessages(['payment' => 'Use the dedicated regional participation checkout.']);
        }
        return DB::transaction(function () use ($order, $walletApplied, $remainingAmount): TeamPaymentOrder {
            $locked = TeamPaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->withdrawn_at !== null) {
                throw new \RuntimeException('This team checkout has been superseded or withdrawn and cannot be changed.');
            }
            if ($this->hasUnresolvedPayfastHandoff($locked)) {
                throw new \RuntimeException('This team checkout is currently being processed by PayFast and cannot be changed.');
            }

            /** @var TeamPaymentOrder $reserved */
            $reserved = $this->paymentOrchestrator->initiatePayment($locked, $walletApplied, $remainingAmount);

            return $reserved;
        });
    }

    public function ensureTrialOrder(\App\Models\TrialParticipation $participation, User $payer): TeamPaymentOrder
    {
        return FinanceMutationScope::run('payment_state_write', function () use ($participation, $payer) {
            $fee = round((float) \App\Models\TrialProgramme::where('event_id', $participation->event_id)->value('participation_fee'), 2);
            abort_unless($fee > 0, 422);
            if ($participation->order_id) {
                $order = TeamPaymentOrder::lockForUpdate()->findOrFail($participation->order_id);
                abort_unless((int) $order->user_id === (int) $payer->id, 403, 'Only the existing payer may resume or cancel this checkout.');
                if ($participation->isPaid()) return $order;
                abort_unless(! $order->withdrawn_at, 422);
                if (! $order->payfast_handed_off_at && $this->isWhollyUnpaid($order) && (float) $order->wallet_reserved === 0.0) {
                    $order->update(['total_amount' => $fee, 'payfast_amount_due' => $fee]);
                }
                return $order;
            }
            return TeamPaymentOrder::create(['user_id' => $payer->id, 'team_id' => null, 'event_id' => $participation->event_id,
                'player_id' => $participation->player_id, 'total_amount' => $fee, 'payfast_amount_due' => $fee,
                'wallet_reserved' => 0, 'payfast_paid' => false, 'wallet_debited' => false, 'pay_status' => false]);
        });
    }

    public function finalizeTrialManualPayment(\App\Models\TrialParticipation $participation, User $actor, string $method, string $reference, ?int $proofId = null): \App\Models\TrialParticipationReceipt
    {
        $this->assertSafeEvidenceReference($reference);
        abort_unless(in_array($method, ['eft', 'manual'], true), 422);
        return DB::transaction(function () use ($participation, $actor, $method, $reference, $proofId) {
            app(\App\Services\InterprovincialTrials\TrialParticipationService::class)->lockForOrder((int)$participation->order_id);
            $participation = \App\Models\TrialParticipation::lockForUpdate()->findOrFail($participation->id);
            $order = TeamPaymentOrder::lockForUpdate()->findOrFail($participation->order_id);
            app(\App\Services\InterprovincialTrials\TrialProgrammeService::class)->authorize($participation->event, $actor);
            app(\App\Services\InterprovincialTrials\TrialParticipationService::class)->assertOrder($order);
            $existing = \App\Models\TrialParticipationReceipt::where('order_id', $order->id)->first();
            if ($existing) {
                abort_unless($proofId === null || ((int) $existing->proof_id === $proofId && $existing->method === $method), 422, 'A different proof already settled this participation checkout.');
                return $existing;
            }
            if (! $this->isWhollyUnpaid($order) || $order->payfast_handed_off_at || (float) $order->wallet_reserved !== 0.0
                || \App\Models\Transaction::where('custom_int5', $order->id)->where('custom_str5', 'TeamOrder')->exists()
                || \App\Models\WalletTransaction::where('source_id', $order->id)->where('source_type', 'team_registration_wallet_payment')->exists()) {
                throw ValidationException::withMessages(['payment' => 'This participation checkout contains settlement evidence.']);
            }
            $fee = round((float) \App\Models\TrialProgramme::where('event_id', $participation->event_id)->value('participation_fee'), 2);
            if ($fee <= 0 || $fee !== round((float) $order->total_amount, 2) || $fee !== round((float) $order->payfast_amount_due, 2)) {
                throw ValidationException::withMessages(['payment' => 'The regional participation fee changed. Refresh the checkout.']);
            }
            if ($proofId !== null) {
                $proof = \App\Models\TrialParticipationProof::lockForUpdate()->findOrFail($proofId);
                abort_unless((int) $proof->participation_id === (int) $participation->id && (int) $proof->order_id === (int) $order->id && (int) $proof->payer_id === (int) $order->user_id && $proof->status === 'pending', 422);
            }
            $receipt = \App\Models\TrialParticipationReceipt::create(['participation_id' => $participation->id, 'order_id' => $order->id, 'event_id' => $order->event_id,
                'amount' => $fee, 'method' => $method, 'reference' => $reference, 'verified_by' => $actor->id, 'proof_id' => $proofId, 'paid_at' => now()]);
            FinanceMutationScope::run(['payment_state_write', 'team_payment_state_write'], function () use ($order, $actor): void {
                $order->update(['pay_status' => true, 'collection_status' => 'paid_privately', 'paid_privately_at' => now(), 'paid_privately_by' => $actor->id, 'payfast_amount_due' => 0]);
            });
            $participation->update(['paid_at' => now()]);
            if (isset($proof)) $proof->update(['status' => 'verified', 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
            activity('interprovincial-trials')->performedOn($receipt)->causedBy($actor)->withProperties(['order_id' => $order->id, 'method' => $method, 'reference' => $reference, 'amount' => $fee])->log('Regional participation payment received');
            return $receipt;
        });
    }

    public function recordPayfastHandoff(TeamPaymentOrder $order, User $payer, float $amount): TeamPaymentOrder
    {
        return DB::transaction(function () use ($order, $payer, $amount): TeamPaymentOrder {
            app(\App\Services\InterprovincialTrials\TrialParticipationService::class)->lockForOrder((int)$order->id);
            $locked = TeamPaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
            $expected = round((float) $locked->payfast_amount_due, 2);
            if ((int) $locked->user_id !== (int) $payer->id) {
                throw ValidationException::withMessages(['payment' => 'Only the payer may submit this checkout to PayFast.']);
            }
            if ($locked->withdrawn_at !== null || $locked->pay_status || $locked->payfast_paid || $locked->wallet_debited) {
                throw ValidationException::withMessages(['payment' => 'This checkout is no longer available for PayFast submission.']);
            }
            if (! $this->isWhollyUnpaid($locked)) {
                throw ValidationException::withMessages(['payment' => 'This checkout contains settlement evidence and cannot be submitted to PayFast.']);
            }
            if ($expected <= 0 || round($amount, 2) !== $expected) {
                throw ValidationException::withMessages(['payment' => 'The PayFast handoff amount does not match the server-calculated balance.']);
            }
            $participation = \App\Models\TrialParticipation::where('order_id', $locked->id)->first();
            if ($participation) {
                app(\App\Services\InterprovincialTrials\TrialParticipationService::class)->assertOrder($locked);
                $fee = round((float) \App\Models\TrialProgramme::where('event_id', $locked->event_id)->value('participation_fee'), 2);
                if (! $locked->payfast_handed_off_at && ($fee !== round((float) $locked->total_amount, 2) || $expected !== $fee || (float) $locked->wallet_reserved !== 0.0)) {
                    throw ValidationException::withMessages(['payment' => 'The regional participation fee changed. Refresh the checkout before paying.']);
                }
            }
            if ($this->hasUnresolvedPayfastHandoff($locked)) {
                return $locked;
            }
            $locked->forceFill([
                'payfast_handed_off_at' => now(),
            ])->save();

            activity('team-payment')->performedOn($locked)->causedBy($payer)
                ->withProperties([
                    'order_id' => $locked->id,
                    'payer_id' => $payer->id,
                    'amount_due' => $expected,
                    'event_id' => $locked->event_id,
                    'team_id' => $locked->team_id,
                    'player_id' => $locked->player_id,
                    'handed_off_at' => $locked->payfast_handed_off_at?->toIso8601String(),
                ])->log('team checkout handed off to PayFast');

            return $locked->refresh();
        });
    }

    public function finalizePayment(TeamPaymentOrder $order, array $context = []): TeamPaymentOrder
    {
        return DB::transaction(function () use ($order, $context): TeamPaymentOrder {
            app(\App\Services\InterprovincialTrials\TrialParticipationService::class)->lockForOrder((int)$order->id);
            $locked = TeamPaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (\App\Models\TrialParticipation::where('order_id', $locked->id)->exists()
                && (! array_key_exists('payfast_amount_received', $context) || ($context['payment_method'] ?? '') !== 'payfast' || (float) $locked->wallet_reserved !== 0.0 || empty($context['pf_payment_id']))) {
                throw ValidationException::withMessages(['payment' => 'Regional participation requires a verified PayFast result or recorded manual receipt.']);
            }
            if ($locked->withdrawn_at !== null) {
                throw new \RuntimeException('This team checkout has been superseded or withdrawn and cannot be paid.');
            }
            /** @var TeamPaymentOrder $finalized */
            $finalized = $this->paymentOrchestrator->finalizePayment($locked, $context);
            $participation = \App\Models\TrialParticipation::where('order_id', $finalized->id)->first();
            if ($participation) {
                app(\App\Services\InterprovincialTrials\TrialParticipationService::class)->assertOrder($finalized);
                $participation->update(['paid_at' => $participation->paid_at ?? now()]);
            }
            $this->markPlayerPaid($finalized);
            app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->confirmPaidOrder($finalized);

            return $finalized;
        });
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
                $order = TeamPaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
                $teamPlayer = TeamPlayer::query()
                    ->where('team_id', $order->team_id)
                    ->where('player_id', $order->effective_player_id)
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
                $order = TeamPaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
                $teamPlayer = TeamPlayer::query()
                    ->where('team_id', $order->team_id)
                    ->where('player_id', $order->effective_player_id)
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

    public function createUnpaidTeamPlayerSlot(Team $team, Player $player, int $rank): TeamPlayer
    {
        return FinanceMutationScope::run('team_payment_state_write', function () use ($team, $player, $rank) {
            return DB::transaction(function () use ($team, $player, $rank) {
                Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();
                if ($rank < 1 || TeamPlayer::query()->where('team_id', $team->id)->where('rank', $rank)->lockForUpdate()->get()->isNotEmpty()) {
                    throw ValidationException::withMessages(['player_id' => 'This roster position changed. Refresh the team before linking the profile.']);
                }

                return TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => $rank, 'pay_status' => 0]);
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

    public function recordWithdrawal(TeamPaymentOrder $order, ?User $actor): TeamPaymentOrder
    {
        return FinanceMutationScope::run('team_payment_state_write', function () use ($order, $actor) {
            return DB::transaction(function () use ($order, $actor) {
                app(\App\Services\InterprovincialTrials\TrialParticipationService::class)->lockForOrder((int)$order->id);
            $locked = TeamPaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
                if ($locked->effective_player_id !== $order->effective_player_id) {
                    throw ValidationException::withMessages(['payment' => 'Payment coverage changed. Refresh before withdrawing this player.']);
                }

                if ($this->hasUnresolvedPayfastHandoff($locked)) {
                    throw ValidationException::withMessages([
                        'payment' => 'A checkout currently with PayFast cannot be withdrawn locally.',
                    ]);
                }

                if (! $locked->withdrawn_at) {
                    $locked->withdrawn_at = now();
                    $locked->withdrawn_by = $actor?->id;
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

    public function closeUnpaidLifecycle(TeamPaymentOrder $order, ?User $actor): TeamPaymentOrder
    {
        return DB::transaction(function () use ($order, $actor): TeamPaymentOrder {
            app(\App\Services\InterprovincialTrials\TrialParticipationService::class)->lockForOrder((int)$order->id);
            $locked = TeamPaymentOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->payfast_handed_off_at !== null) {
                throw ValidationException::withMessages([
                    'payment' => 'A checkout handed off to PayFast cannot be closed locally.',
                ]);
            }

            if ($locked->withdrawn_at) {
                return $locked;
            }

            if ($locked->pay_status || $locked->payfast_paid || $locked->wallet_debited) {
                throw ValidationException::withMessages([
                    'payment' => 'A paid or debited team checkout cannot be closed as an unpaid lifecycle.',
                ]);
            }

            $cancelled = $this->cancelPayment($locked);

            return $this->recordWithdrawal($cancelled, $actor);
        });
    }

    public function assertUnpaidRosterCheckoutMayBeReset(TeamPaymentOrder $order): void
    {
        $total = round((float) $order->total_amount, 2);
        $reserved = round((float) $order->wallet_reserved, 2);
        $due = round((float) $order->payfast_amount_due, 2);
        if (! $this->isWhollyUnpaid($order, $order->withdrawn_at !== null)
            || $order->payfast_handed_off_at !== null
            || \App\Models\Transaction::query()->where('custom_int5', $order->id)->where('custom_str5', 'TeamOrder')->lockForUpdate()->get()->isNotEmpty()
            || \App\Models\WalletTransaction::query()->where('source_id', $order->id)->where('source_type', 'team_registration_wallet_payment')->lockForUpdate()->get()->isNotEmpty()
            || $total < 0 || $reserved < 0 || $reserved > $total
            || ($order->withdrawn_at !== null && $reserved !== 0.0)
            || ($due !== round($total - $reserved, 2) && ! ($due === 0.0 && $reserved === 0.0))) {
            throw ValidationException::withMessages([
                'player_id' => 'This checkout has payment or settlement evidence and cannot be reset.',
            ]);
        }
    }

    public function assertUnpaidRosterCheckoutMayBeClosed(TeamPaymentOrder $order): void
    {
        $total = round((float) $order->total_amount, 2);
        $due = round((float) $order->payfast_amount_due, 2);
        if (! $this->isWhollyUnpaid($order, $order->withdrawn_at !== null)
            || (float) $order->wallet_reserved !== 0.0 || $order->payfast_handed_off_at !== null
            || $total < 0 || ($due !== 0.0 && $due !== $total)) {
            throw ValidationException::withMessages([
                'player_id' => 'The previous player has checkout or settlement evidence that cannot be closed during roster alignment.',
            ]);
        }
    }

    private function isWhollyUnpaid(TeamPaymentOrder $order, bool $allowWithdrawn = false): bool
    {
        return ! $order->pay_status
            && ! $order->payfast_paid
            && ! $order->wallet_debited
            && ($allowWithdrawn || blank($order->withdrawn_at))
            && ($allowWithdrawn || blank($order->withdrawn_by))
            && blank($order->payfast_pf_payment_id)
            && blank($order->payfast_raw_data)
            && blank($order->paid_privately_at)
            && blank($order->collection_status)
            && ! $order->hasRefund()
            && $order->refunded_at === null
            && round((float) $order->refund_gross, 2) === 0.0
            && round((float) $order->refund_fee, 2) === 0.0
            && round((float) $order->refund_net, 2) === 0.0
            && blank($order->refund_method)
            && blank($order->refund_waived_at)
            && blank($order->refund_waived_by)
            && blank($order->refund_waiver_reason);
    }

    public function assertPayfastHandoffMayBeReleased(TeamPaymentOrder $order): void
    {
        $total = round((float) $order->total_amount, 2);
        $reserved = round((float) $order->wallet_reserved, 2);
        $due = round((float) $order->payfast_amount_due, 2);
        if (! $this->hasUnresolvedPayfastHandoff($order)) {
            throw ValidationException::withMessages(['payment' => 'This team order has no unresolved PayFast handoff.']);
        }
        if ($total <= 0 || $due <= 0 || $reserved < 0 || round($reserved + $due, 2) !== $total
            || ! $this->isWhollyUnpaid($order)) {
            throw ValidationException::withMessages([
                'payment' => 'This team order contains settlement or lifecycle evidence and cannot be released.',
            ]);
        }
    }

    public function releaseUnresolvedPayfastHandoff(TeamPaymentOrder $order, User $operator, string $evidenceReference): TeamPaymentOrder
    {
        $this->assertSafeEvidenceReference($evidenceReference);

        return DB::transaction(function () use ($order, $operator, $evidenceReference): TeamPaymentOrder {
            $locked = TeamPaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
            $authorizedOperator = User::query()->find($operator->id);
            if (! $authorizedOperator?->hasRole('super-user')) {
                throw ValidationException::withMessages(['operator' => 'An existing super-user operator is required.']);
            }
            $this->assertPayfastHandoffMayBeReleased($locked);
            $walletReserved = round((float) $locked->wallet_reserved, 2);
            $payfastDue = round((float) $locked->payfast_amount_due, 2);

            $locked->forceFill(['payfast_handed_off_at' => null])->save();

            activity('team-payment')->performedOn($locked)->causedBy($authorizedOperator)
                ->withProperties([
                    'order_id' => $locked->id,
                    'operator_id' => $authorizedOperator->id,
                    'evidence_reference' => $evidenceReference,
                    'wallet_reserved' => $walletReserved,
                    'payfast_amount_due' => $payfastDue,
                ])->log('supervised unresolved PayFast handoff released');

            return $locked->refresh();
        });
    }

    private function assertSafeEvidenceReference(string $evidenceReference): void
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{2,119}$/', $evidenceReference)) {
            throw ValidationException::withMessages([
                'payment' => 'A safe 3-120 character provider evidence/reference is required.',
            ]);
        }
    }

    public function hasUnresolvedPayfastHandoff(TeamPaymentOrder $order): bool
    {
        return $order->payfast_handed_off_at !== null
            && ! $order->pay_status
            && ! $order->payfast_paid
            && ! $order->wallet_debited;
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
                if ($order?->payfast_handed_off_at !== null) {
                    throw ValidationException::withMessages([
                        'payment' => 'A checkout handed off to PayFast cannot be marked paid privately.',
                    ]);
                }
                $associatedUserIds = collect([$player->userId])->merge($player->users->pluck('id'))
                    ->filter()->map(fn ($id): int => (int) $id)->unique()->values();
                $payer = $order
                    ? User::query()->find($order->user_id)
                    : ($associatedUserIds->count() === 1 ? User::query()->find($associatedUserIds->first()) : null);

                if (! $payer || ($order && (! $associatedUserIds->contains((int) $order->user_id)
                    || (int) $order->event_id !== (int) $locked->event_id
                    || (int) $order->team_id !== (int) $locked->team_id
                    || $order->effective_player_id !== (int) $locked->player_id))) {
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
