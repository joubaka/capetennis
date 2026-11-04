<?php

namespace App\Domain\Payments\Services;

use App\Domain\Refunds\Services\RefundExecutionService;
use App\Models\{CategoryAdjustment, ClothingOrder, Event, NoProfileTeamPlayer, Player, PlayerPlusOrder, RegistrationOrder, Team, TeamPaymentOrder, TeamPlayer, User, Wallet, WalletTransaction};
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/** Supervised correction of an audited refund-funded replacement; never called from checkout or an ITN. */
final class TeamReplacementPaymentCorrectionService
{
    public const AUDIT = 'corrected refunded team replacement to original payment coverage';

    public function preview(User $actor, array $ids, string $reason): array
    {
        return DB::transaction(fn () => $this->inspect($actor, $ids, $reason)['preview']);
    }

    public function apply(User $actor, array $ids, string $reason, string $fingerprint): array
    {
        abort_unless($actor->hasRole('super-user'), 403);
        $ids = $this->identifiers($ids);
        $this->ensure(trim($reason) !== '' && strlen($fingerprint) === 64, 'A reviewed reason and exact preview fingerprint are required.');
        return DB::transaction(function () use ($actor, $ids, $reason, $fingerprint): array {
            app(\App\Services\TeamDrawAdaptationService::class)->lockEvent($ids['event_id']);
            $source = TeamPaymentOrder::lockForUpdate()->findOrFail($ids['source_order_id']);
            $prior = Activity::where('subject_type', TeamPaymentOrder::class)->where('subject_id', $source->id)->where('description', self::AUDIT)->lockForUpdate()->get();
            if ($prior->isNotEmpty()) {
                $audit = $prior->count() === 1 ? $prior->first() : null;
                $this->ensure($audit && (int) $audit->causer_id === (int) $actor->id
                    && data_get($audit->properties, 'fingerprint') === $fingerprint
                    && data_get($audit->properties, 'request') === $ids && data_get($audit->properties, 'reason') === trim($reason)
                    && $source->effective_player_id === $ids['target_player_id'] && !$source->withdrawn_at
                    && $source->pay_status && !$source->hasRefund()
                    && WalletTransaction::where('source_type', 'team_replacement_refund_reversal')->where('source_id', $source->id)->count() === 1,
                    'This correction already has different history; do not apply another adjustment.');
                $this->assertAppliedResult($source, $ids, $audit, $actor, $reason, $fingerprint);
                return ['applied' => false, 'already_applied' => true, 'source_order_id' => $source->id];
            }
            $context = $this->inspect($actor, $ids, $reason);
            $this->ensure(hash_equals($context['preview']['fingerprint'], $fingerprint), 'The reviewed payment, wallet or roster state changed. Preview again.');
            FinanceMutationScope::run('team_replacement_correction', function () use ($context, $actor, $reason, $fingerprint): void {
                $source = app(RefundExecutionService::class)->reverseTeamReplacementWalletRefund($context['source'], $context['credit'], $actor, $reason, $fingerprint, $context['replacement']->id);
                app(TeamPaymentService::class)->restoreCorrectedReplacementCoverage($source, $context['replacement'], $context['slot'], $actor, $reason, $fingerprint);
            });
            $source = $context['source']->fresh();
            activity('team-payment')->performedOn($source)->causedBy($actor)->withProperties([
                'request' => $ids, 'reason' => trim($reason), 'fingerprint' => $fingerprint,
                'before' => $context['source_before'], 'after' => $this->orderState($source),
                'refund_credit_id' => $context['credit']->id,
                'compensating_debit_id' => WalletTransaction::where('source_type', 'team_replacement_refund_reversal')->where('source_id', $source->id)->sole()->id,
                'wallet_before' => $context['preview']['wallet_balance'], 'wallet_after' => $context['wallet']->fresh()->balance,
                'replacement_order_remains_closed' => $context['replacement']->id,
            ])->log(self::AUDIT);
            return ['applied' => true, 'already_applied' => false, 'source_order_id' => $source->id,
                'beneficiary_player_id' => $source->effective_player_id, 'wallet_balance' => $context['wallet']->fresh()->balance];
        }, 3);
    }

    /** Deliberately conservative: any later wallet debit or unresolved hold needs separate reconciliation. */
    public function assertUnusedRefund(User $payer, Wallet $wallet, WalletTransaction $credit): array
    {
        $ledger = WalletTransaction::where('wallet_id', $wallet->id)->where('id', '>=', $credit->id)->orderBy('id')->limit(1001)->lockForUpdate()->get();
        $this->ensure($ledger->count() <= 1000 && !$ledger->contains(fn ($row) => $row->type !== 'credit'), 'The refund has subsequent wallet spending or too much history for this correction.');
        foreach ([TeamPaymentOrder::class, RegistrationOrder::class, ClothingOrder::class, CategoryAdjustment::class, PlayerPlusOrder::class] as $model) {
            $holds = $model::where('user_id', $payer->id)->where('wallet_reserved', '>', 0)
                ->where(fn ($q) => $q->where('wallet_debited', false)->orWhereNull('wallet_debited'))->lockForUpdate()->get(['id', 'wallet_reserved']);
            $this->ensure($holds->isEmpty(), 'The original payer has unresolved wallet reservations. Resolve those before reversing the refund.');
        }
        $this->ensure(round($wallet->balance, 2) >= round((float) $credit->amount, 2), 'The original refund is no longer fully available in its payer wallet.');
        return ['balance' => round($wallet->balance, 2), 'ledger_since_refund' => $ledger->map->only(['id', 'type', 'amount', 'source_type', 'source_id'])->all()];
    }

    private function inspect(User $actor, array $ids, string $reason): array
    {
        abort_unless($actor->hasRole('super-user'), 403);
        $ids = $this->identifiers($ids);
        $this->ensure(trim($reason) !== '' && mb_strlen($reason) <= 1000, 'Give a correction reason of at most 1000 characters.');
        $event = app(\App\Services\TeamDrawAdaptationService::class)->lockEvent($ids['event_id']);
        $team = Team::lockForUpdate()->findOrFail($ids['team_id']);
        abort_unless($team->category()->where('event_id', $event->id)->exists(), 404);
        $orders = TeamPaymentOrder::where('event_id', $event->id)->forPlayerHistory([$ids['source_player_id'], $ids['target_player_id']])->orderBy('id')->lockForUpdate()->get();
        $source = $orders->firstWhere('id', $ids['source_order_id']);
        $replacement = $orders->firstWhere('id', $ids['replacement_order_id']);
        $this->ensure($source && $replacement && $orders->count() === 2, 'Exactly the original paid order and its closed unpaid replacement are required.');
        $this->ensure((int) $source->team_id === (int) $team->id && (int) $replacement->team_id === (int) $team->id
            && (int) $source->player_id === $ids['source_player_id'] && $source->beneficiary_player_id === null
            && $replacement->effective_player_id === $ids['target_player_id'] && (int) $source->user_id === (int) $replacement->user_id
            && $ids['source_player_id'] !== $ids['target_player_id'], 'The original payer, player, team and replacement identity must match.');
        $this->ensure($source->pay_status && $source->withdrawn_at && $source->refunded_at && $source->refund_status === 'completed'
            && $source->refund_method === 'wallet' && !$source->refund_waived_at && !$source->refund_waived_by && blank($source->refund_waiver_reason)
            && $source->refund_gross > 0 && $source->refund_fee >= 0 && $source->refund_net > 0
            && round((float) $source->refund_gross, 2) === round((float) $source->total_amount, 2)
            && round((float) $source->refund_net + (float) $source->refund_fee, 2) === round((float) $source->total_amount, 2), 'An exact completed original-payer wallet refund is required.');
        $payments = app(TeamPaymentService::class);
        $payments->assertAdminWithdrawalSettlement($source);
        $this->ensure($replacement->withdrawn_at !== null && (float) $replacement->wallet_reserved === 0.0, 'The unpaid replacement checkout must already be safely closed with its reservation released.');
        $payments->assertUnpaidRosterCheckoutMayBeReset($replacement);
        $fee = round((float) $event->entryFee + (float) $team->regions?->region_fee, 2);
        $this->ensure($fee > 0 && $fee === round((float) $source->total_amount, 2) && $fee === round((float) $replacement->total_amount, 2), 'Original, replacement and current entry amounts must match exactly.');
        $slots = TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->orderBy('id')->lockForUpdate()->get();
        $matches = $slots->where('player_id', $ids['target_player_id']);
        $slot = $matches->count() === 1 ? $matches->first() : null;
        $this->ensure($slot && !$slot->pay_status && $slot->rank > 0 && $slots->where('rank', $slot->rank)->count() === 1
            && $slots->where('player_id', $ids['source_player_id'])->isEmpty(), 'A unique unpaid replacement place without the original player is required.');
        $imports = NoProfileTeamPlayer::where('team_id', $team->id)->where(fn ($q) => $q->where('rank', $slot->rank)->orWhereIn('player_profile', [$ids['source_player_id'], $ids['target_player_id']]))->lockForUpdate()->get();
        $this->ensure($team->noProfile ? ($imports->count() === 1 && (int) $imports->first()->rank === (int) $slot->rank
            && (int) $imports->first()->player_profile === $ids['target_player_id'] && !$imports->first()->pay_status) : $imports->isEmpty(), 'The imported replacement and profile mirror must agree.');
        $this->ensure(!TeamPlayer::withoutGlobalScopes()->where('team_id', '!=', $team->id)->whereIn('player_id', [$ids['source_player_id'], $ids['target_player_id']])
            ->whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))->exists()
            && !NoProfileTeamPlayer::where('team_id', '!=', $team->id)->whereIn('player_profile', [$ids['source_player_id'], $ids['target_player_id']])
                ->whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))->exists(), 'Another team participation needs separate review.');
        $this->assertNoProtectedHistory($event, $team, $orders->pluck('id')->all(), [$ids['source_player_id'], $ids['target_player_id']], $imports->pluck('id')->all());
        app(\App\Services\PlayerEligibilityService::class)->assertEligible(Player::lockForUpdate()->findOrFail($ids['target_player_id']), $event);
        $audits = Activity::where('subject_type', TeamPaymentOrder::class)->where('subject_id', $source->id)->orderBy('id')->lockForUpdate()->get();
        $withdrawals = $audits->where('description', 'super user confirmed team withdrawal');
        $funding = $audits->where('description', 'applied withdrawal refund to replacement checkout');
        $this->ensure($withdrawals->count() === 1 && $funding->count() === 1
            && $audits->where('description', self::AUDIT)->isEmpty()
            && (int) data_get($withdrawals->first()->properties, 'player_id') === $ids['source_player_id']
            && (int) data_get($withdrawals->first()->properties, 'payer_id') === (int) $source->user_id
            && (int) data_get($withdrawals->first()->properties, 'original_slot.rank') === (int) $slot->rank
            && data_get($withdrawals->first()->properties, 'refund_action') === 'refund'
            && (int) data_get($funding->first()->properties, 'replacement_order_id') === (int) $replacement->id
            && (int) data_get($funding->first()->properties, 'replacement_player_id') === $ids['target_player_id']
            && round((float) data_get($funding->first()->properties, 'amount_applied'), 2) === round((float) $source->refund_net, 2), 'The exact canonical withdrawal and replacement-funding audit chain is required.');
        $rosterAudit = Activity::where('subject_type', Team::class)->where('subject_id', $team->id)->where('description', 'changed team roster player')
            ->where('properties->replacement_order_id', $replacement->id)->lockForUpdate()->get();
        $this->ensure($rosterAudit->count() === 1
            && (int) data_get($rosterAudit->first()->properties, $team->noProfile ? 'before.player_profile' : 'before.player_id') === $ids['source_player_id']
            && (int) data_get($rosterAudit->first()->properties, $team->noProfile ? 'after.player_profile' : 'after.player_id') === $ids['target_player_id'], 'The original roster replacement audit must match both players.');
        $originalImportIds = collect(data_get($withdrawals->first()->properties, 'original_imported_slots', []))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $fixtureState = $this->upcomingFixtureState($event, $team, $ids['source_player_id'], $ids['target_player_id'], $imports->pluck('id')->all(), $originalImportIds);
        $payer = User::lockForUpdate()->findOrFail($source->user_id);
        $wallet = $payer->wallet()->lockForUpdate()->firstOrFail();
        $credits = WalletTransaction::where('source_type', 'admin_team_withdrawal_refund')->where('source_id', $source->id)->lockForUpdate()->get();
        $credit = $credits->count() === 1 ? $credits->first() : null;
        $this->ensure($credit && $credit->type === 'credit' && (int) $credit->wallet_id === (int) $wallet->id
            && round((float) $credit->amount, 2) === round((float) $source->refund_net, 2)
            && (int) data_get($funding->first()->properties, 'refund_credit_id') === (int) $credit->id
            && !WalletTransaction::where('source_type', 'team_replacement_refund_reversal')->where('source_id', $source->id)->exists(), 'A unique unreversed original-payer refund credit is required.');
        $walletState = $this->assertUnusedRefund($payer, $wallet, $credit);
        $source_before = $this->orderState($source);
        $state = ['request' => $ids, 'actor_id' => (int) $actor->id, 'reason' => trim($reason), 'source' => $source_before,
            'replacement' => $this->orderState($replacement), 'slot' => $slot->only(['id', 'team_id', 'player_id', 'rank', 'pay_status']),
            'imports' => $imports->map->only(['id', 'team_id', 'player_profile', 'rank', 'pay_status'])->all(), 'wallet' => $walletState,
            'audit_ids' => [$withdrawals->first()->id, $funding->first()->id, $rosterAudit->first()->id], 'current_fee' => $fee, 'fixtures' => $fixtureState];
        $preview = ['request' => $ids, 'payer_id' => (int) $payer->id, 'original_paid_amount' => $fee, 'refund_to_reverse' => (float) $credit->amount,
            'refund_credit_id' => $credit->id, 'wallet_balance' => $walletState['balance'], 'wallet_after' => round($walletState['balance'] - $credit->amount, 2),
            'restored_beneficiary_player_id' => $ids['target_player_id'], 'closed_replacement_order_id' => $replacement->id,
            'unchanged_upcoming_fixture_ids' => array_column($fixtureState, 'fixture_id'),
            'fingerprint' => hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR), (string) config('app.key'))];
        return compact('source', 'replacement', 'slot', 'wallet', 'credit', 'source_before', 'preview');
    }

    private function assertNoProtectedHistory(Event $event, Team $team, array $orderIds, array $playerIds, array $importIds): void
    {
        $protected = [
            'Trials event' => $event->isInterprovincialTrials(),
            'Trials participation' => DB::table('trial_participations')->whereIn('order_id', $orderIds)->lockForUpdate()->exists(),
            'Trials provider refund claim' => DB::table('trial_provider_refund_attempts')->whereIn('order_id', $orderIds)->lockForUpdate()->exists(),
            'Trials payment proof' => \App\Models\TrialParticipationProof::whereIn('order_id', $orderIds)->lockForUpdate()->exists(),
            'team provider refund claim' => DB::table('team_provider_refund_attempts')->whereIn('order_id', $orderIds)->lockForUpdate()->exists(),
            'payment transfer history' => DB::table('team_payment_transfers')->whereIn('order_id', $orderIds)->lockForUpdate()->exists(),
            'retained expired PayFast handoff' => Activity::where('subject_type', TeamPaymentOrder::class)->whereIn('subject_id', $orderIds)
                ->where('description', 'Expired unresolved PayFast handoff retained for reconciliation')->lockForUpdate()->exists(),
            'selection invitation history' => \App\Models\TeamSelectionInvitation::where('event_id', $event->id)->where(fn ($q) => $q->where('team_id', $team->id)->orWhereIn('player_id', $playerIds))->lockForUpdate()->exists(),
            'individual registration history' => \App\Models\CategoryEventRegistration::withTrashed()->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))
                ->whereHas('registration.players', fn ($q) => $q->whereIn('players.id', $playerIds))->lockForUpdate()->exists(),
        ];
        foreach ($protected as $boundary => $exists) {
            $this->ensure(!$exists, 'Correction blocked by '.$boundary.'. Separate reconciliation is required.');
        }
    }

    /** Payment-only correction may retain proven, unstarted target fixtures; it never changes their identities or schedule. */
    private function upcomingFixtureState(Event $event, Team $team, int $sourcePlayerId, int $targetPlayerId, array $targetImportIds, array $sourceImportIds): array
    {
        $importIds = array_values(array_unique(array_merge($targetImportIds, $sourceImportIds)));
        $fixtureIds = \App\Models\TeamFixturePlayer::whereHas('fixture.draw', fn ($q) => $q->where('event_id', $event->id))
            ->where(fn ($q) => $q->whereIn('team1_id', [$sourcePlayerId, $targetPlayerId])->orWhereIn('team2_id', [$sourcePlayerId, $targetPlayerId])
                ->orWhereIn('team1_no_profile_id', $importIds)->orWhereIn('team2_no_profile_id', $importIds))
            ->orderBy('team_fixture_id')->lockForUpdate()->pluck('team_fixture_id')->unique()->all();
        $state = [];
        foreach ($fixtureIds as $fixtureId) {
            $fixture = \App\Models\TeamFixture::without(['teamResults', 'fixturePlayers', 'draw'])->lockForUpdate()->findOrFail($fixtureId);
            $draw = \App\Models\Draw::lockForUpdate()->findOrFail($fixture->draw_id);
            $tie = $fixture->team_tie_id ? \App\Models\TeamTie::lockForUpdate()->findOrFail($fixture->team_tie_id) : null;
            $results = $fixture->fixtureResults()->lockForUpdate()->get();
            $assignments = \App\Models\TeamFixturePlayer::where('team_fixture_id', $fixture->id)->orderBy('id')->lockForUpdate()->get();
            $this->ensure((int) $draw->event_id === (int) $event->id && $tie && (int) $tie->draw_id === (int) $draw->id
                && (int) $fixture->match_status === 0 && $results->isEmpty() && !$tie->isCompleted() && !$tie->winner_team_id,
                'Only unstarted, unscored replacement fixtures with a verified team tie may retain this payment correction.');
            $targetSides = 0;
            foreach ($assignments as $assignment) {
                foreach ([1 => 'home_team_id', 2 => 'away_team_id'] as $side => $teamColumn) {
                    $profileId = (int) $assignment->{'team'.$side.'_id'};
                    $importedId = (int) $assignment->{'team'.$side.'_no_profile_id'};
                    $this->ensure($profileId !== $sourcePlayerId && !in_array($importedId, $sourceImportIds, true),
                        'Original player fixture history requires separate reconciliation.');
                    if ($profileId !== $targetPlayerId && !in_array($importedId, $targetImportIds, true)) continue;
                    $targetSides++;
                    $representative = (int) $tie->$teamColumn;
                    $mixedSides = $draw->team_draw_selection['mixed_sides'] ?? [];
                    $mixed = $mixedSides[$representative] ?? null;
                    $sameSide = !$mixedSides ? $representative === (int) $team->id : ($mixed
                        && in_array((int) $team->id, [(int) ($mixed['boys'] ?? 0), (int) ($mixed['girls'] ?? 0)], true)
                        && (int) ($mixed['region_id'] ?? 0) === (int) $team->region_id
                        && in_array((int) $team->category_event_id, array_map('intval', $draw->team_draw_selection['category_ids'] ?? []), true));
                    if ($sameSide) {
                        try {
                            $anchor = Team::lockForUpdate()->findOrFail($representative);
                            $resolved = app(\App\Services\TeamDrawSideResolver::class)->side($draw, $anchor, (int) $fixture->round_nr, (int) $fixture->id);
                            $sameSide = $resolved && $resolved->team_players->where('player_id', $targetPlayerId)->count() === 1;
                        } catch (\InvalidArgumentException $exception) {
                            $sameSide = false;
                        }
                    }
                    $this->ensure($sameSide && (($profileId === $targetPlayerId && $importedId === 0)
                            || ($profileId === 0 && in_array($importedId, $targetImportIds, true)
                                && (int) data_get($assignment->participant_snapshot, $side.'.linked_profile_id') === $targetPlayerId))
                        && app(\App\Services\TeamParticipantHistoryService::class)->matches($assignment, $side, $team, (int) $event->id),
                        'A replacement fixture has ambiguous or mismatched team participation.');
                }
            }
            $this->ensure($targetSides === 1, 'A replacement fixture must have one exact target participant.');
            $state[] = ['fixture_id' => (int) $fixture->id, 'fixture' => $fixture->getAttributes(), 'draw' => $draw->getAttributes(),
                'tie' => $tie->getAttributes(), 'assignments' => $assignments->map->getAttributes()->all(), 'results' => []];
        }
        return $state;
    }

    private function orderState(TeamPaymentOrder $order): array
    {
        return $order->only(['id', 'event_id', 'team_id', 'user_id', 'player_id', 'beneficiary_player_id', 'total_amount', 'pay_status', 'wallet_reserved', 'wallet_debited',
            'payfast_amount_due', 'payfast_paid', 'payfast_pf_payment_id', 'payfast_handed_off_at', 'withdrawn_at', 'withdrawn_by', 'refund_status', 'refund_method', 'refund_gross', 'refund_fee', 'refund_net', 'refunded_at', 'updated_at']);
    }

    private function assertAppliedResult(TeamPaymentOrder $source, array $ids, Activity $audit, User $actor, string $reason, string $fingerprint): void
    {
        $replacement = TeamPaymentOrder::lockForUpdate()->findOrFail($ids['replacement_order_id']);
        $slots = TeamPlayer::withoutGlobalScopes()->where('team_id', $ids['team_id'])->where('player_id', $ids['target_player_id'])->lockForUpdate()->get();
        $credit = WalletTransaction::lockForUpdate()->findOrFail(data_get($audit->properties, 'refund_credit_id'));
        $debits = WalletTransaction::where('source_type', 'team_replacement_refund_reversal')->where('source_id', $source->id)->lockForUpdate()->get();
        $debit = $debits->count() === 1 ? $debits->first() : null;
        $transfers = DB::table('team_payment_transfers')->where('order_id', $source->id)->lockForUpdate()->get();
        $transfer = $transfers->count() === 1 ? $transfers->first() : null;
        $this->ensure((int) $source->event_id === $ids['event_id'] && (int) $source->team_id === $ids['team_id']
            && (int) $source->player_id === $ids['source_player_id'] && $source->refund_gross == 0 && $source->refund_fee == 0 && $source->refund_net == 0
            && !$source->refunded_at && !$source->refund_method && (int) $source->user_id === (int) data_get($audit->properties, 'before.user_id')
            && $replacement->withdrawn_at && (int) $replacement->user_id === (int) $source->user_id && (int) $replacement->team_id === $ids['team_id']
            && (int) $replacement->event_id === $ids['event_id'] && $replacement->effective_player_id === $ids['target_player_id']
            && $slots->count() === 1 && (int) $slots->first()->pay_status === 1
            && $credit->type === 'credit' && $credit->source_type === 'admin_team_withdrawal_refund' && (int) $credit->source_id === (int) $source->id
            && $debit && $debit->type === 'debit' && (int) $debit->id === (int) data_get($audit->properties, 'compensating_debit_id')
            && (int) $debit->wallet_id === (int) $credit->wallet_id && round((float) $debit->amount, 2) === round((float) $credit->amount, 2)
            && round((float) $credit->amount, 2) === round((float) data_get($audit->properties, 'before.refund_net'), 2)
            && data_get($debit->meta, 'fingerprint') === $fingerprint && data_get($debit->meta, 'reason') === trim($reason)
            && (int) data_get($debit->meta, 'actor_id') === (int) $actor->id
            && $transfer && (int) $transfer->from_player_id === $ids['source_player_id'] && (int) $transfer->to_player_id === $ids['target_player_id']
            && (int) $transfer->actor_id === (int) $actor->id && $transfer->reason === trim($reason),
            'The prior correction result has changed. Separate reconciliation is required.');
        $wallet = $source->user->wallet()->lockForUpdate()->firstOrFail();
        $this->ensure((int) $wallet->id === (int) $credit->wallet_id, 'The prior correction payer wallet changed.');
        $imports = NoProfileTeamPlayer::where('team_id', $ids['team_id'])->where(fn ($q) => $q->where('rank', $slots->first()->rank)->orWhere('player_profile', $ids['target_player_id']))->lockForUpdate()->get();
        $this->ensure($source->team->noProfile ? ($imports->count() === 1 && (int) $imports->first()->player_profile === $ids['target_player_id'] && (int) $imports->first()->pay_status === 1) : $imports->isEmpty(),
            'The prior correction roster mirror changed.');
        app(TeamPaymentService::class)->assertAdminWithdrawalSettlement($source);
        app(TeamPaymentService::class)->assertUnpaidRosterCheckoutMayBeReset($replacement);
    }

    private function identifiers(array $ids): array
    {
        $result = [];
        foreach (['event_id', 'team_id', 'source_order_id', 'replacement_order_id', 'source_player_id', 'target_player_id'] as $key) {
            $this->ensure(isset($ids[$key]) && filter_var($ids[$key], FILTER_VALIDATE_INT) !== false && (int) $ids[$key] > 0, 'Exact positive event, team, order and player identifiers are required.');
            $result[$key] = (int) $ids[$key];
        }
        return $result;
    }

    private function ensure(bool $valid, string $message): void
    {
        if (!$valid) throw ValidationException::withMessages(['correction' => $message]);
    }
}
