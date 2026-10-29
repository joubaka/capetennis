<?php

namespace App\Services;

use App\Domain\Payments\Services\TeamPaymentService;
use App\Domain\Refunds\Services\{RefundExecutionService, TeamRefundCalculator};
use App\Models\{Event, Team, TeamPlayer, TeamPaymentOrder, TeamSelectionInvitation, TeamFixturePlayer, TeamFixture, TeamTie, User, WalletTransaction, Transaction};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminTeamWithdrawalService
{
    public function review(Event $event, Team $team, TeamPlayer $slot, User $actor, string $destination = 'wallet', string $feeMode = 'current', string $refundAction = 'refund', bool $deadlineOverride = false): array
    {
        abort_unless($actor->hasRole('super-user'), 403);
        $this->ensure(in_array($refundAction, ['refund', 'waive'], true) && ($refundAction !== 'waive' || $destination === 'wallet'), 'Choose a valid refund decision.');
        $this->ensure(in_array($destination, ['wallet', 'payfast'], true) && in_array($feeMode, ['current', 'full'], true), 'Choose a valid refund destination and fee policy.');
        abort_unless((int) $slot->team_id === (int) $team->id && $team->category()->where('event_id', $event->id)->exists(), 404);
        $this->ensure((int) $slot->player_id > 0 && (int) $slot->pay_status === 1, 'Only an active paid player with a linked profile can use this withdrawal.');
        $imported = \App\Models\NoProfileTeamPlayer::where('team_id', $team->id)->where('rank', $slot->rank)->get();
        $importedPlayer = \App\Models\NoProfileTeamPlayer::where('team_id', $team->id)->where('player_profile', $slot->player_id)->get();
        // Canonical finalization marks TeamPlayer paid; historical imported mirrors may remain unpaid.
        // Mirror identity stays strict, while the unique order and settlement ledger prove payment.
        $this->ensure($team->noProfile ? ($imported->count() === 1 && (int) $imported->first()->player_profile === (int) $slot->player_id
                && in_array($imported->first()->pay_status, [0, 1, '0', '1'], true))
            : $imported->isEmpty(), 'The imported player and paid roster mirror no longer agree.');
        $this->ensure($team->noProfile ? ($importedPlayer->count() === 1 && (int) $importedPlayer->first()->id === (int) $imported->first()->id)
            : $importedPlayer->isEmpty(), 'This player has duplicate imported roster places requiring administrator review.');
        $samePlayer = TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('player_id', $slot->player_id)->get();
        $sameRank = TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', $slot->rank)->get();
        $this->ensure($samePlayer->count() === 1 && $sameRank->count() === 1 && (int) $samePlayer->first()->id === (int) $slot->id
            && (int) $sameRank->first()->id === (int) $slot->id, 'This roster has duplicate player or rank places requiring administrator review.');
        $orders = TeamPaymentOrder::where('event_id', $event->id)->where('team_id', $team->id)->forBeneficiary($slot->player_id)
            ->where('pay_status', true)->whereNull('withdrawn_at')->get();
        $this->ensure($orders->count() === 1, 'A unique current paid order is required. Review this player’s payment history.');
        $order = $orders->first();
        $this->ensure(!$order->hasRefund() && !$order->refunded_at && !$order->refund_waived_at && !$order->refund_waived_by
            && blank($order->refund_waiver_reason) && blank($order->refund_method)
            && (float) $order->refund_gross === 0.0 && (float) $order->refund_fee === 0.0 && (float) $order->refund_net === 0.0,
            'This order already has refund history. Review the existing refund.');
        $this->ensure(!$event->isInterprovincialTrials() && !\App\Models\TrialParticipation::where('order_id', $order->id)->exists(), 'Use the Trials participation workflow for this entry.');
        $this->ensure(!DB::table('trial_provider_refund_attempts')->where('order_id', $order->id)->exists(), 'A provider refund attempt requires reconciliation before withdrawal.');
        $this->ensure(!\App\Models\TeamProviderRefundAttempt::where('order_id', $order->id)->exists(), 'A provider refund claim already exists. Review the pending refund.');
        $this->assertSettlement($order);
        $invitations = TeamSelectionInvitation::where('event_id', $event->id)->where('team_id', $team->id)->where('player_id', $slot->player_id)
            ->whereIn('status', [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED])->get();
        $this->ensure($invitations->count() <= 1 && ($invitations->isEmpty() || ((int) $invitations->first()->order_id === (int) $order->id
            && (int) $invitations->first()->roster_rank === (int) $slot->rank)), 'The selection invitation and paid order no longer agree.');
        $amounts = app(TeamRefundCalculator::class)->calculate($order, $feeMode === 'full');
        $currentFee = app(TeamRefundCalculator::class)->calculate($order)['fee'];
        $deadlinePassed = now()->gt($event->withdrawalCloseAt());
        $this->ensure(!$deadlineOverride || ($deadlinePassed && $refundAction === 'refund'), 'A deadline override is only available for a late refund.');
        $refundAvailable = !$deadlinePassed || $deadlineOverride;
        $this->ensure($refundAction === 'waive' || !$refundAvailable || $amounts['net'] > 0, 'No refundable amount is available under the current withdrawal policy.');
        $this->ensure(!$refundAvailable || $destination !== 'payfast' || ($order->payfast_paid && $amounts['payfastNet'] > 0), 'This payment has no refundable PayFast component. Choose the original payer wallet.');
        return compact('order', 'amounts', 'refundAvailable', 'imported', 'destination', 'feeMode', 'currentFee', 'deadlinePassed', 'deadlineOverride')
            + ['fingerprint' => $this->fingerprint($event, $slot, $order, $amounts, $refundAvailable, $destination, $feeMode, $currentFee, $refundAction, $deadlineOverride)];
    }

    public function confirm(Event $event, Team $team, TeamPlayer $slot, User $actor, array $data): TeamPaymentOrder
    {
        abort_unless($actor->hasRole('super-user'), 403);
        $destination = $data['destination'] ?? 'wallet';
        $feeMode = $data['fee_mode'] ?? 'current';
        $refundAction = $data['refund_action'] ?? 'refund';
        $deadlineOverride = filter_var($data['deadline_override'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->ensure(!$deadlineOverride || filled($data['reason'] ?? null), 'Give a reason for refunding after the deadline.');
        $this->ensure(in_array($refundAction, ['refund', 'waive'], true) && ($refundAction !== 'waive' || filled($data['reason'] ?? null)), 'Choose a valid refund decision and give a waiver reason.');
        if ($destination === 'payfast' && DB::transactionLevel() !== 0) throw new \LogicException('Provider withdrawal requires a committed request outside an enclosing transaction.');
        $result = DB::transaction(function () use ($event, $team, $slot, $actor, $data, $destination, $feeMode, $refundAction, $deadlineOverride): TeamPaymentOrder {
            $event = app(TeamDrawAdaptationService::class)->lockEvent($event->id);
            $team = Team::lockForUpdate()->findOrFail($team->id);
            $slot = TeamPlayer::withoutGlobalScopes()->lockForUpdate()->findOrFail($slot->id);
            TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->lockForUpdate()->get();
            \App\Models\NoProfileTeamPlayer::where('team_id', $team->id)->lockForUpdate()->get();
            TeamSelectionInvitation::where('event_id', $event->id)->where('team_id', $team->id)->lockForUpdate()->get();
            abort_unless((int) $slot->team_id === (int) $team->id && $team->category()->where('event_id', $event->id)->exists(), 404);
            $order = TeamPaymentOrder::lockForUpdate()->findOrFail($data['order_id']);
            abort_unless((int) $order->event_id === (int) $event->id && (int) $order->team_id === (int) $team->id, 404);
            // A replay is recognized only by this exact confirmed review and its original actor.
            $audit = \Spatie\Activitylog\Models\Activity::where('subject_type', TeamPaymentOrder::class)->where('subject_id', $order->id)
                ->where('description', 'super user confirmed team withdrawal')->latest('id')->first();
            if ($audit && (int) $audit->causer_id === (int) $actor->id && data_get($audit->properties, 'fingerprint') === $data['fingerprint']
                && (int) data_get($audit->properties, 'slot_id') === (int) $slot->id && $order->withdrawn_at
                && data_get($audit->properties, 'destination', 'wallet') === $destination && data_get($audit->properties, 'fee_mode', 'current') === $feeMode
                && data_get($audit->properties, 'refund_action', 'refund') === $refundAction
                && (bool) data_get($audit->properties, 'deadline_override', false) === $deadlineOverride
                && (in_array($order->refund_status, ['pending', 'completed', 'waived'], true) || data_get($audit->properties, 'refund_available') === false)) return $order;
            $review = $this->review($event, $team, $slot, $actor, $destination, $feeMode, $refundAction, $deadlineOverride);
            $this->ensure((int) $review['order']->id === (int) $order->id && hash_equals($review['fingerprint'], $data['fingerprint']), 'This player or refund calculation changed. Open a fresh review.');
            $this->ensure(\App\Models\SiteSetting::get('withdrawal_allowed', '1') === '1', 'Withdrawals are currently disabled.');
            $this->ensure(!\App\Models\CategoryEventRegistration::withTrashed()->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))
                ->whereHas('registration.players', fn ($q) => $q->where('players.id', $slot->player_id))->exists(), 'Resolve individual registration participation before this team withdrawal.');
            $playerId = (int) $slot->player_id;
            $fixtures = $this->fixtureChanges($event, $team, $playerId, $review['imported']->pluck('id')->all());
            $payments = app(TeamPaymentService::class);
            $withdrawn = $payments->recordWithdrawal($order, $actor);
            if ($refundAction === 'waive') {
                app(RefundExecutionService::class)->waiveAdminTeamWithdrawalRefund($withdrawn, $actor, $data['reason'], $feeMode);
            } elseif ($review['refundAvailable']) {
                app(RefundExecutionService::class)->requestAdminTeamWithdrawalRefund($withdrawn, $actor, $destination, $feeMode, $review['fingerprint'], $deadlineOverride, $data['reason']);
            }
            foreach ($fixtures as [$fixturePlayer, $changes]) $fixturePlayer->update($changes);
            $before = $slot->getAttributes();
            $payments->updateTeamPlayerSlot($slot, ['player_id' => 0, 'pay_status' => 0]);
            foreach ($review['imported'] as $imported) $imported->delete();
            app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->markWithdrawn($event->id, $team->id, $playerId, $actor, allowAutomaticReplacement: false);
            activity('team-withdrawal')->performedOn($order)->causedBy($actor)->withProperties([
                'event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $playerId, 'slot_id' => $slot->id, 'original_slot' => $before,
                'fingerprint' => $review['fingerprint'], 'refund_available' => $review['refundAvailable'], 'amounts' => $review['amounts'],
                'destination' => $destination, 'fee_mode' => $feeMode, 'payer_id' => $order->user_id, 'refund_action' => $refundAction,
                'deadline_override' => $deadlineOverride, 'deadline' => $event->withdrawalCloseAt()->toIso8601String(),
                'reason' => $data['reason'], 'automatic_replacement' => false, 'original_imported_slots' => $review['imported']->map->getAttributes()->all(),
            ])->log('super user confirmed team withdrawal');
            return $order->fresh();
        });
        if ($result->refund_status === 'pending' && $result->refund_method === 'payfast') {
            try {
                return app(RefundExecutionService::class)->executeAdminTeamPayfastRefund($result, $actor);
            } catch (ValidationException $exception) {
                // The withdrawal and claim are committed. The pending review provides safe recovery.
                return $result->fresh();
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::error('Team withdrawal refund needs local recovery', ['order_id' => $result->id, 'exception_type' => $exception::class]);
                return $result->fresh();
            }
        }
        return $result;
    }

    private function assertSettlement(TeamPaymentOrder $order): void
    {
        app(TeamPaymentService::class)->assertAdminWithdrawalSettlement($order);
    }

    private function fingerprint(Event $event, TeamPlayer $slot, TeamPaymentOrder $order, array $amounts, bool $available, string $destination, string $feeMode, float $currentFee, string $refundAction, bool $deadlineOverride): string
    {
        return hash_hmac('sha256', implode('|', [$event->id, $slot->id, $slot->rank, $slot->player_id, $order->id, $order->user_id,
            TeamPaymentService::cashRefundFingerprint($order, $amounts), $available ? $destination : 'no-refund', $feeMode, $currentFee, $refundAction, $deadlineOverride ? 'override' : 'standard',
            \App\Models\SiteSetting::get('withdrawal_allowed', '1'), $event->withdrawalCloseAt()->toIso8601String(),
            \App\Models\NoProfileTeamPlayer::where('team_id', $slot->team_id)->where('rank', $slot->rank)->value('id')]), (string) config('app.key'));
    }

    private function fixtureChanges(Event $event, Team $team, int $playerId, array $importedIds): array
    {
        $players = TeamFixturePlayer::whereHas('fixture.draw', fn ($q) => $q->where('event_id', $event->id))
            ->where(fn ($q) => $q->where('team1_id', $playerId)->orWhere('team2_id', $playerId)
                ->orWhereIn('team1_no_profile_id', $importedIds)->orWhereIn('team2_no_profile_id', $importedIds))->lockForUpdate()->get();
        $otherRoster = TeamPlayer::withoutGlobalScopes()->where('team_id', '!=', $team->id)->where('player_id', $playerId)
            ->whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))->lockForUpdate()->exists();
        $result = [];
        foreach ($players as $assignment) {
            $fixture = TeamFixture::without('fixturePlayers')->lockForUpdate()->findOrFail($assignment->team_fixture_id);
            $tie = $fixture->team_tie_id ? TeamTie::lockForUpdate()->findOrFail($fixture->team_tie_id) : null;
            $changes = [];
            foreach ([1 => 'home_team_id', 2 => 'away_team_id'] as $side => $teamColumn) {
                $column = 'team'.$side.'_id';
                $importedColumn = 'team'.$side.'_no_profile_id';
                if (in_array((int) $assignment->$importedColumn, $importedIds, true)) $changes[$importedColumn] = null;
                if ((int) $assignment->$column !== $playerId || ($tie && (int) $tie->$teamColumn !== (int) $team->id)) continue;
                $this->ensure($tie || !$otherRoster, 'A legacy fixture cannot identify this player’s team. Resolve it before withdrawing.');
                $changes[$column] = null;
            }
            $this->ensure(!$changes || (!$fixture->fixtureResults()->lockForUpdate()->exists() && (int) $fixture->match_status !== 2 && $tie?->status !== TeamTie::STATUS_COMPLETED), 'Completed fixture participation must be resolved before withdrawing.');
            if ($changes) $result[] = [$assignment, $changes];
        }
        return $result;
    }

    private function ensure(bool $condition, string $message): void
    {
        if (!$condition) throw ValidationException::withMessages(['withdrawal' => $message]);
    }
}
