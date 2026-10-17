<?php

namespace App\Services;

use App\Domain\Payments\Services\TeamPaymentService;
use App\Domain\Refunds\Services\{RefundExecutionService, TeamRefundCalculator};
use App\Models\{Event, Team, TeamPlayer, TeamPaymentOrder, TeamSelectionInvitation, TeamFixturePlayer, TeamFixture, TeamTie, User, WalletTransaction, Transaction};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminTeamWithdrawalService
{
    public function review(Event $event, Team $team, TeamPlayer $slot, User $actor): array
    {
        abort_unless($actor->hasRole('super-user'), 403);
        abort_unless((int) $slot->team_id === (int) $team->id && $team->category()->where('event_id', $event->id)->exists(), 404);
        $this->ensure((int) $slot->player_id > 0 && (int) $slot->pay_status === 1, 'Only an active paid player with a linked profile can use this withdrawal.');
        $imported = \App\Models\NoProfileTeamPlayer::where('team_id', $team->id)->where('rank', $slot->rank)->get();
        $importedPlayer = \App\Models\NoProfileTeamPlayer::where('team_id', $team->id)->where('player_profile', $slot->player_id)->get();
        $this->ensure($team->noProfile ? ($imported->count() === 1 && (int) $imported->first()->player_profile === (int) $slot->player_id && (int) $imported->first()->pay_status === 1)
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
        $this->assertSettlement($order);
        $invitations = TeamSelectionInvitation::where('event_id', $event->id)->where('team_id', $team->id)->where('player_id', $slot->player_id)
            ->whereIn('status', [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED])->get();
        $this->ensure($invitations->count() <= 1 && ($invitations->isEmpty() || ((int) $invitations->first()->order_id === (int) $order->id
            && (int) $invitations->first()->roster_rank === (int) $slot->rank)), 'The selection invitation and paid order no longer agree.');
        $amounts = app(TeamRefundCalculator::class)->calculate($order);
        $refundAvailable = now()->lte($event->withdrawalCloseAt());
        $this->ensure(!$refundAvailable || $amounts['net'] > 0, 'No refundable amount is available under the current withdrawal policy.');
        return compact('order', 'amounts', 'refundAvailable', 'imported') + ['fingerprint' => $this->fingerprint($event, $slot, $order, $amounts, $refundAvailable)];
    }

    public function confirm(Event $event, Team $team, TeamPlayer $slot, User $actor, array $data): void
    {
        abort_unless($actor->hasRole('super-user'), 403);
        DB::transaction(function () use ($event, $team, $slot, $actor, $data): void {
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
                && ($order->refund_status === 'completed' || data_get($audit->properties, 'refund_available') === false)) return;
            $review = $this->review($event, $team, $slot, $actor);
            $this->ensure((int) $review['order']->id === (int) $order->id && hash_equals($review['fingerprint'], $data['fingerprint']), 'This player or refund calculation changed. Open a fresh review.');
            $this->ensure(\App\Models\SiteSetting::get('withdrawal_allowed', '1') === '1', 'Withdrawals are currently disabled.');
            $this->ensure(!\App\Models\CategoryEventRegistration::withTrashed()->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))
                ->whereHas('registration.players', fn ($q) => $q->where('players.id', $slot->player_id))->exists(), 'Resolve individual registration participation before this team withdrawal.');
            $playerId = (int) $slot->player_id;
            $fixtures = $this->fixtureChanges($event, $team, $playerId, $review['imported']->pluck('id')->all());
            $payments = app(TeamPaymentService::class);
            $withdrawn = $payments->recordWithdrawal($order, $actor);
            if ($review['refundAvailable']) {
                $payer = User::lockForUpdate()->findOrFail($order->user_id);
                $wallet = $payer->wallet ?? $payer->wallet()->create();
                app(RefundExecutionService::class)->executeWithdrawnTeamWalletRefund($withdrawn, $wallet, $review['amounts']['net'],
                    'admin_team_withdrawal_refund', $order->id, ['event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $playerId, 'actor_id' => $actor->id]);
            }
            foreach ($fixtures as [$fixturePlayer, $changes]) $fixturePlayer->update($changes);
            $before = $slot->getAttributes();
            $payments->updateTeamPlayerSlot($slot, ['player_id' => 0, 'pay_status' => 0]);
            foreach ($review['imported'] as $imported) $imported->delete();
            app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->markWithdrawn($event->id, $team->id, $playerId, $actor, allowAutomaticReplacement: false);
            activity('team-withdrawal')->performedOn($order)->causedBy($actor)->withProperties([
                'event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $playerId, 'slot_id' => $slot->id, 'original_slot' => $before,
                'fingerprint' => $review['fingerprint'], 'refund_available' => $review['refundAvailable'], 'amounts' => $review['amounts'],
                'reason' => $data['reason'], 'automatic_replacement' => false, 'original_imported_slots' => $review['imported']->map->getAttributes()->all(),
            ])->log('super user confirmed team withdrawal');
        });
    }

    private function assertSettlement(TeamPaymentOrder $order): void
    {
        $payments = app(TeamPaymentService::class);
        $this->ensure($order->user && $order->collection_status !== 'paid_privately' && !$payments->hasUnresolvedPayfastHandoff($order), 'A settled payment to the original payer is required.');
        if ($order->payfast_paid) {
            $this->ensure(Transaction::where('custom_str5', 'TeamOrder')->where('custom_int5', $order->id)->where('payment_status', 'COMPLETE')->count() === 1,
                'An exact COMPLETE PayFast receipt is required.');
            $payments->assertVerifiedTeamPayfastSettlement($order);
            return;
        }
        $debits = WalletTransaction::where('source_type', 'team_registration_wallet_payment')->where('source_id', $order->id)->get();
        $this->ensure($order->wallet_debited && (float) $order->total_amount > 0 && (float) $order->payfast_amount_due === 0.0
            && blank($order->payfast_pf_payment_id) && blank($order->payfast_raw_data)
            && !Transaction::where('custom_str5', 'TeamOrder')->where('custom_int5', $order->id)->exists()
            && round((float) $order->wallet_reserved, 2) === round((float) $order->total_amount, 2)
            && $debits->count() === 1 && $debits->first()->type === 'debit'
            && round((float) $debits->first()->amount, 2) === round((float) $order->total_amount, 2)
            && $order->user->wallet()->whereKey($debits->first()->wallet_id)->exists(), 'Exact original payment settlement evidence is required.');
    }

    private function fingerprint(Event $event, TeamPlayer $slot, TeamPaymentOrder $order, array $amounts, bool $available): string
    {
        return hash('sha256', implode('|', [$event->id, $slot->id, $slot->rank, $slot->player_id, $order->id, $order->user_id,
            TeamPaymentService::cashRefundFingerprint($order, $amounts), $available ? 'wallet' : 'no-refund', $event->withdrawalCloseAt()->toIso8601String(),
            \App\Models\NoProfileTeamPlayer::where('team_id', $slot->team_id)->where('rank', $slot->rank)->value('id')]));
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
