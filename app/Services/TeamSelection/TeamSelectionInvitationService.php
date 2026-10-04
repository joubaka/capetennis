<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Domain\Payments\Services\TeamPaymentService;
use App\Domain\Teams\Services\ExternalTeamRosterService;
use App\Services\Clothing\ClothingPriceService;
use App\Jobs\SendTeamSelectionInvitationEmailJob;
use App\Models\BulkEmailLog;
use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\TeamPaymentOrder;
use App\Models\Player;
use App\Models\TeamPlayer;
use App\Models\TeamSelectionImport;
use App\Models\TeamSelectionInvitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TeamSelectionInvitationService
{
    public function __construct(
        private ClothingPriceService $clothingPrices,
        private TeamSelectionContactService $contacts,
        private TeamSelectionInvitationDecisionAccessService $decisionAccess,
    ) {}

    /**
     * @param array{name: string, num_team_members: int, published: bool} $attributes
     * @return array{moved_to_reserves: int}
     */
    public function updateTeamSettings(Team $team, Event $event, int $regionId, array $attributes, User $actor): array
    {
        return DB::transaction(function () use ($team, $event, $regionId, $attributes, $actor): array {
            Event::query()->lockForUpdate()->findOrFail($event->id);
            $requestedPlaces = (int) $attributes['num_team_members'];
            $activeImport = TeamSelectionImport::query()
                ->where('event_id', $event->id)
                ->where('region_id', $regionId)
                ->whereIn('status', ['draft', 'sent'])
                ->latest('id')
                ->lockForUpdate()
                ->first();
            $overflow = $activeImport
                ? TeamSelectionInvitation::query()
                    ->where('import_id', $activeImport->id)
                    ->where('team_id', $team->id)
                    ->whereIn('status', [
                        TeamSelectionInvitation::INVITED,
                        TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                        TeamSelectionInvitation::PAID_CONFIRMED,
                    ])
                    ->where('roster_rank', '>', $requestedPlaces)
                    ->orderBy('roster_rank')
                    ->lockForUpdate()
                    ->get()
                : collect();
            $lockedTeam = Team::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($team->id);
            $previousPlaces = (int) $lockedTeam->num_team_members;

            $protected = $overflow->whereIn('status', [
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                TeamSelectionInvitation::PAID_CONFIRMED,
            ]);
            if ($protected->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'num_team_members' => 'The team size cannot move accepted or paid players to reserves. Withdraw those players through the normal payment and refund workflow first.',
                ]);
            }

            $managedSlotKeys = $overflow->mapWithKeys(fn (TeamSelectionInvitation $invitation) => [
                ((int) $invitation->roster_rank).':'.((int) $invitation->player_id) => true,
            ]);
            $occupiedOverflowSlots = TeamPlayer::query()->withoutGlobalScopes()
                ->where('team_id', $lockedTeam->id)
                ->where('rank', '>', $requestedPlaces)
                ->where('player_id', '>', 0)
                ->lockForUpdate()
                ->get();
            $unmanagedRosterRank = (int) $occupiedOverflowSlots
                ->reject(fn (TeamPlayer $slot) => $managedSlotKeys->has(((int) $slot->rank).':'.((int) $slot->player_id)))
                ->max('rank');
            $importedRosterRank = (int) $lockedTeam->team_players_no_profile()
                ->where('rank', '>', $requestedPlaces)
                ->max('rank');
            $unmanagedRank = max($unmanagedRosterRank, $importedRosterRank);
            if ($unmanagedRank > 0) {
                throw ValidationException::withMessages([
                    'num_team_members' => "Player place {$unmanagedRank} is not part of the active reserve queue and cannot be moved automatically. Move or remove that roster player first.",
                ]);
            }

            foreach ($overflow as $invitation) {
                $previousRank = (int) $invitation->roster_rank;
                $slot = $occupiedOverflowSlots->first(fn (TeamPlayer $candidate) =>
                    (int) $candidate->rank === $previousRank
                    && (int) $candidate->player_id === (int) $invitation->player_id
                );
                if ($slot) {
                    app(TeamPaymentService::class)->updateTeamPlayerSlot($slot, [
                        'player_id' => 0,
                        'pay_status' => 0,
                    ]);
                }
                $invitation->update([
                    'status' => TeamSelectionInvitation::RESERVE,
                    'roster_rank' => null,
                ]);
                activity('team-selection')->performedOn($invitation)->causedBy($actor)
                    ->withProperties([
                        'team_id' => $lockedTeam->id,
                        'previous_roster_rank' => $previousRank,
                        'new_team_capacity' => $requestedPlaces,
                    ])->log('moved selected player to reserve after team capacity reduction');
            }

            TeamPlayer::query()->withoutGlobalScopes()
                ->where('team_id', $lockedTeam->id)
                ->where('rank', '>', $requestedPlaces)
                ->where('player_id', 0)
                ->delete();
            $lockedTeam->update($attributes);
            activity('team-selection')->performedOn($lockedTeam)->causedBy($actor)
                ->withProperties([
                    'region_id' => $regionId,
                    'published' => (bool) $attributes['published'],
                    'previous_player_places' => $previousPlaces,
                    'player_places' => $requestedPlaces,
                    'moved_to_reserves' => $overflow->count(),
                    'moved_invitation_ids' => $overflow->pluck('id')->all(),
                ])->log('regional manager updated team details');

            return ['moved_to_reserves' => $overflow->count()];
        });
    }

    public function send(TeamSelectionImport $import, array $deadlines, User $actor, bool $activateRegistration = false): array
    {
        return DB::transaction(function () use ($import, $deadlines, $actor, $activateRegistration) {
            $event = Event::query()->lockForUpdate()->findOrFail($import->event_id);
            $locked = TeamSelectionImport::query()->lockForUpdate()
                ->with(['event.venues', 'region.clothingItems.sizes', 'invitations.team', 'invitations.player.user', 'invitations.player.users'])
                ->findOrFail($import->id);
            abort_unless((int) $locked->event_id === (int) $event->id, 404);
            if ($locked->status === 'sent') {
                throw ValidationException::withMessages(['import' => 'These invitations have already been sent.']);
            }

            $response = now()->parse($deadlines['response_deadline']);
            $payment = now()->parse($deadlines['payment_deadline']);
            $replacement = now()->parse($deadlines['replacement_payment_deadline'] ?? $deadlines['payment_deadline']);
            if ($response->isPast() || $payment->isPast() || $replacement->isPast()
                || $payment->lt($response) || $replacement->lt($payment)) {
                throw ValidationException::withMessages(['deadlines' => 'Use future deadlines ordered as response, payment, then replacement payment.']);
            }
            $selected = $locked->invitations->where('status', TeamSelectionInvitation::INVITED);
            if ($selected->isEmpty()) {
                throw ValidationException::withMessages(['invitations' => 'There are no selected players to invite.']);
            }

            if ($activateRegistration) {
                if (! app(RegionManagerAccessService::class)->isEventManager($actor, $event)) {
                    throw new AuthorizationException('Only an event manager may open registration for the whole event.');
                }
                $eventUpdates = ['published' => true, 'signUp' => true];
                if (in_array($event->status, ['draft', 'closed'], true)) {
                    $eventUpdates['status'] = 'open';
                }
                $event->update($eventUpdates);
                $event->refresh();
                $locked->setRelation('event', $event);
            } elseif (! app(ExternalTeamRosterService::class)->registrationIsOpen($event)) {
                throw ValidationException::withMessages([
                    'event' => 'The event manager must open event registration before a regional invitation can be sent.',
                ]);
            }
            Team::query()->whereIn('id', $selected->pluck('team_id')->filter()->unique())
                ->update(['published' => true]);

            $campaign = $this->campaignSnapshot($locked, $deadlines, $response, $payment, $replacement);
            if (isset($deadlines['expected_campaign_hash'])
                && ! hash_equals((string) $deadlines['expected_campaign_hash'], (string) $campaign['hash'])) {
                throw ValidationException::withMessages([
                    'email_preview' => 'The recipient list or invitation content changed. Preview the exact send again.',
                ]);
            }

            $stats = ['selected' => $selected->count(), 'queued' => 0, 'missing_email' => 0];
            foreach ($selected as $invitation) {
                $email = $this->contactEmail($invitation);
                if (! $email) {
                    $stats['missing_email']++;
                    continue;
                }
                $invitation->update(['invited_at' => now()]);
                $this->queueMail($invitation, $email, 'invitation', $campaign);
                $stats['queued']++;
            }
            if ($stats['queued'] === 0) {
                throw ValidationException::withMessages([
                    'email' => 'None of the selected players has a valid contact email. Add at least one contact before sending.',
                ]);
            }

            $locked->update([
                'response_deadline' => $response,
                'payment_deadline' => $payment,
                'replacement_payment_deadline' => $replacement,
                'email_subject' => $campaign['subject'],
                'email_message' => $campaign['message'],
                'event_information' => $campaign['event_information'],
                'reply_to' => $campaign['reply_to'],
                'include_clothing' => $campaign['include_clothing'],
                'communication_hash' => $campaign['hash'],
                'communication_snapshot' => $campaign,
                'prepared_by' => $actor->id,
                'prepared_at' => now(),
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties($stats + [
                    'response_deadline' => $response->toIso8601String(),
                    'payment_deadline' => $payment->toIso8601String(),
                    'replacement_payment_deadline' => $replacement->toIso8601String(),
                    'communication_hash' => $campaign['hash'],
                    'include_clothing' => $campaign['include_clothing'],
                    'registration_opened' => $activateRegistration,
                    'teams_published' => $selected->pluck('team_id')->filter()->unique()->count(),
                ])
                ->log('sent regional team selection invitations');

            return $stats;
        });
    }

    public function accept(TeamSelectionInvitation $invitation, User $user): TeamSelectionInvitation
    {
        return DB::transaction(function () use ($invitation, $user) {
            $locked = TeamSelectionInvitation::query()->lockForUpdate()
                ->with(['selectionImport.event', 'team', 'player'])->findOrFail($invitation->id);
            if ($locked->status === TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT) {
                if ($this->deadlineBlocksRegistration($locked, $this->paymentDeadline($locked))) {
                    throw ValidationException::withMessages(['payment' => 'The payment deadline for this invitation has passed.']);
                }

                return $locked->fresh();
            }
            if ($locked->status !== TeamSelectionInvitation::INVITED) {
                throw ValidationException::withMessages(['invitation' => 'Registration is no longer available for this selected player.']);
            }
            if ($this->deadlineBlocksRegistration($locked, $this->responseDeadline($locked))) {
                throw ValidationException::withMessages(['invitation' => 'The response deadline has passed.']);
            }
            app(ExternalTeamRosterService::class)->assertSelectedPlayerCanRegister(
                $locked->selectionImport->event, $locked->team, $locked->player
            );
            $locked->update([
                'status' => TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                'accepted_at' => null,
                'payment_started_at' => now(),
            ]);
            activity('team-selection')->performedOn($locked)->causedBy($user)
                ->log('started payment for regional team invitation');

            return $locked->fresh();
        });
    }

    public function attachOrder(TeamPaymentOrder $order): ?TeamSelectionInvitation
    {
        return DB::transaction(function () use ($order): ?TeamSelectionInvitation {
            $order = TeamPaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
            $invitation = TeamSelectionInvitation::query()->lockForUpdate()
                ->where('event_id', $order->event_id)
                ->where('team_id', $order->team_id)
                ->where('player_id', $order->effective_player_id)
                ->where('status', TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT)
                ->latest('id')
                ->first();
            if ($invitation && ! $invitation->order_id) {
                $invitation->update(['order_id' => $order->id]);
            }

            return $invitation?->fresh();
        }, 3);
    }

    public function assertEligibleForPaymentCoverage(TeamSelectionInvitation $invitation): void
    {
        $invitation->loadMissing(['selectionImport.event', 'team', 'player']);
        if (! in_array($invitation->status, [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true)) {
            throw ValidationException::withMessages(['payment_transfer' => 'Only a selected unpaid player can receive payment coverage.']);
        }
        $deadline = $invitation->status === TeamSelectionInvitation::INVITED
            ? $this->responseDeadline($invitation) : $this->paymentDeadline($invitation);
        if ($this->deadlineBlocksRegistration($invitation, $deadline)) {
            throw ValidationException::withMessages(['payment_transfer' => 'The registration or payment deadline for this selected player has passed.']);
        }
        app(ExternalTeamRosterService::class)->assertSelectedPlayerCanRegister(
            $invitation->selectionImport->event, $invitation->team, $invitation->player
        );
    }

    public function beginRegistration(int $eventId, int $teamId, int $playerId, User $user): ?TeamSelectionInvitation
    {
        $invitation = TeamSelectionInvitation::query()->with('selectionImport')
            ->where('event_id', $eventId)->where('team_id', $teamId)->where('player_id', $playerId)
            ->whereIn('status', [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT])
            ->latest('id')->first();
        if (! $invitation) return null;
        if ($invitation->status === TeamSelectionInvitation::INVITED) {
            return $this->accept($invitation, $user);
        }
        if ($this->deadlineBlocksRegistration($invitation, $this->paymentDeadline($invitation))) {
            throw ValidationException::withMessages(['payment' => 'The payment deadline for this team invitation has passed.']);
        }

        return $invitation;
    }

    public function defaultEventInformation(Event $event): string
    {
        $html = (string) $event->information;
        if (trim($html) === '') {
            return '';
        }

        $withStructure = preg_replace(
            [
                '/<\s*br\s*\/?>/i',
                '/<\s*li\b[^>]*>/i',
                '/<\s*\/\s*li\s*>/i',
                '/<\s*\/\s*(?:p|div|section|article|h[1-6]|ul|ol|table|tr)\s*>/i',
                '/<\s*\/\s*(?:td|th)\s*>/i',
            ],
            ["\n", '• ', "\n", "\n", "\t"],
            $html,
        );
        $text = html_entity_decode(strip_tags((string) $withStructure), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $lines = collect(preg_split('/\R/u', $text) ?: [])
            ->map(fn (string $line) => trim((string) preg_replace('/[\t ]+/u', ' ', $line)));

        return trim((string) preg_replace('/\n{3,}/u', "\n\n", $lines->implode("\n")));
    }

    public function confirmPaidOrder(TeamPaymentOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $order = TeamPaymentOrder::query()->lockForUpdate()->findOrFail($order->id);
            $invitation = TeamSelectionInvitation::query()->lockForUpdate()
                ->where('event_id', $order->event_id)
                ->where('team_id', $order->team_id)
                ->where('player_id', $order->effective_player_id)
                ->where(fn ($query) => $query->where('order_id', $order->id)->orWhereNull('order_id'))
                ->where('status', TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT)
                ->first();
            if (! $invitation) return;
            $invitation->update([
                'order_id' => $order->id,
                'status' => TeamSelectionInvitation::PAID_CONFIRMED,
                'accepted_at' => $invitation->accepted_at ?: now(),
                'paid_at' => now(),
            ]);
        });
    }

    public function markUnavailable(TeamSelectionInvitation $invitation, User $actor, string $reason, array $expected): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }
        DB::transaction(function () use ($invitation, $actor, $reason, $expected): void {
            $selectionImport = $this->lockInvitationContext($invitation);
            $locked = TeamSelectionInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            $region = \App\Models\EventRegion::query()->where('event_id', $locked->event_id)
                ->where('region_id', $selectionImport->region_id)->firstOrFail();
            abort_unless(app(RegionManagerAccessService::class)->canManage($actor, $region), 403);
            abort_unless((int) $locked->event_id === (int) $selectionImport->event_id
                && (int) $locked->region_id === (int) $selectionImport->region_id
                && $locked->team()->where('region_id', $selectionImport->region_id)
                    ->whereHas('category', fn ($query) => $query->where('event_id', $locked->event_id))->exists(), 404);
            if (! in_array($selectionImport->status, ['draft', 'sent'], true)) {
                throw ValidationException::withMessages(['invitation' => 'This invitation campaign is no longer active.']);
            }
            if ($locked->team->noProfile || $locked->team->team_players_no_profile()->where('player_profile', $locked->player_id)->exists()) {
                throw ValidationException::withMessages(['invitation' => 'This player has an imported roster place. Use its existing roster workflow.']);
            }
            $expectedRank = $locked->status === TeamSelectionInvitation::DECLINED && $locked->decline_method === 'regional_manager_unavailable'
                ? $locked->vacated_roster_rank : $locked->roster_rank;
            if ((int) $expected['expected_team_id'] !== (int) $locked->team_id
                || (int) $expected['expected_player_id'] !== (int) $locked->player_id
                || (int) $expected['expected_roster_rank'] !== (int) $expectedRank) {
                throw ValidationException::withMessages(['invitation' => 'The selected player or roster place changed. Refresh the invitations.']);
            }
            if ($locked->status === TeamSelectionInvitation::DECLINED && $locked->decline_method === 'regional_manager_unavailable') {
                return;
            }
            if (! in_array($locked->status, [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true) || ! $locked->roster_rank) {
                throw ValidationException::withMessages(['invitation' => 'Only an unpaid selected player can be marked unavailable. Paid players must use the withdrawal process.']);
            }
            $payments = app(TeamPaymentService::class);
            $orders = TeamPaymentOrder::query()->where('event_id', $locked->event_id)->where('team_id', $locked->team_id)
                ->forBeneficiary((int) $locked->player_id)->lockForUpdate()->get();
            if ($locked->order_id && ! $orders->contains('id', $locked->order_id)) {
                throw ValidationException::withMessages(['payment' => 'The checkout does not match this player and team. Refresh the invitations.']);
            }
            if ($locked->payment_started_at && $orders->isEmpty()) {
                throw ValidationException::withMessages(['payment' => 'The checkout is in progress. Refresh and resolve payment before releasing this place.']);
            }
            foreach ($orders as $order) {
                $payments->assertUnpaidRosterCheckoutMayBeReset($order);
            }
            $slots = TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $locked->team_id)
                ->where('player_id', $locked->player_id)->lockForUpdate()->get();
            if ($slots->count() !== 1 || (int) $slots->first()->rank !== (int) $locked->roster_rank) {
                throw ValidationException::withMessages(['invitation' => 'The roster place changed. Refresh the invitations.']);
            }
            if ($slots->contains(fn ($slot) => (bool) $slot->pay_status)) {
                throw ValidationException::withMessages(['payment' => 'This roster place has payment evidence. Use the withdrawal process.']);
            }
            if (\App\Models\TeamFixturePlayer::query()->where(fn ($query) => $query->where('team1_id', $locked->player_id)->orWhere('team2_id', $locked->player_id))
                ->whereHas('fixture.draw', fn ($query) => $query->where('event_id', $locked->event_id))->lockForUpdate()->get()->isNotEmpty()) {
                throw ValidationException::withMessages(['invitation' => 'This player has fixture participation. Resolve it through the withdrawal process first.']);
            }
            foreach ($orders as $order) {
                $payments->closeUnpaidLifecycle($order, $actor);
            }
            foreach ($slots as $slot) {
                $payments->updateTeamPlayerSlot($slot, ['player_id' => 0, 'pay_status' => 0]);
            }
            $rank = $locked->roster_rank;
            $locked->update([
                'status' => TeamSelectionInvitation::DECLINED, 'declined_at' => now(),
                'decline_reason' => trim($reason), 'declined_by_user_id' => $actor->id,
                'decline_method' => 'regional_manager_unavailable', 'payment_started_at' => null,
                'vacated_roster_rank' => $rank, 'roster_rank' => null,
            ]);
            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties(['reason' => trim($reason), 'vacated_rank' => $rank, 'replacement_id' => null])
                ->log('regional manager marked selected player unavailable');
        });
    }

    public function decline(TeamSelectionInvitation $invitation, User $user, ?string $reason): ?TeamSelectionInvitation
    {
        return DB::transaction(function () use ($invitation, $user, $reason) {
            $selectionImport = $this->lockInvitationContext($invitation);
            $locked = TeamSelectionInvitation::query()->lockForUpdate()->with('selectionImport')->findOrFail($invitation->id);
            $locked->setRelation('selectionImport', $selectionImport);
            $this->decisionAccess->authorizePlayerDecision($user, $locked);
            if (! in_array($locked->status, [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
            ], true)) {
                throw ValidationException::withMessages([
                    'invitation' => 'This invitation is no longer awaiting payment. A paid registration must use the withdrawal process.',
                ]);
            }
            $customInvitation = (bool) data_get($locked->snapshot_json, 'activation.custom_campaign_hash');
            if ($customInvitation) {
                $event = $locked->selectionImport?->event;
                if (! $event || ! app(ExternalTeamRosterService::class)->registrationIsOpen($event)) {
                    throw ValidationException::withMessages(['invitation' => 'Team registration is no longer open for this event.']);
                }
            } else {
                $deadline = $locked->status === TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT
                    ? $this->paymentDeadline($locked)
                    : $this->responseDeadline($locked);
                if ($deadline && now()->gt($deadline)) {
                    throw ValidationException::withMessages(['invitation' => 'The invitation deadline has passed.']);
                }
            }
            if ($locked->order_id) {
                $order = TeamPaymentOrder::query()->lockForUpdate()->find($locked->order_id);
                if ($order) {
                    if ($order->pay_status || $order->payfast_paid || $order->wallet_debited) {
                        throw ValidationException::withMessages([
                            'payment' => 'Payment has already been received or is being finalized. The invitation cannot be declined.',
                        ]);
                    }
                    app(TeamPaymentService::class)->closeUnpaidLifecycle($order, $user);
                }
            }
            $rank = $locked->roster_rank;
            $locked->update([
                'status' => TeamSelectionInvitation::DECLINED,
                'declined_at' => now(),
                'decline_reason' => $reason,
                'declined_by_user_id' => $user->id,
                'decline_method' => 'authenticated_invitation',
                'payment_started_at' => null,
                'vacated_roster_rank' => $rank,
                'roster_rank' => null,
            ]);
            $slot = TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $locked->team_id)
                ->where('rank', $rank)->where('player_id', $locked->player_id)->lockForUpdate()->first();
            if ($slot) app(TeamPaymentService::class)->updateTeamPlayerSlot($slot, ['player_id' => 0, 'pay_status' => 0]);

            $reserve = $locked->selectionImport?->auto_replacement_enabled
                ? $this->promoteNextReserve($locked, $selectionImport, $rank, true)
                : null;
            activity('team-selection')->performedOn($locked)->causedBy($user)
                ->withProperties([
                    'replacement_id' => $reserve?->id,
                    'reason' => $reason,
                    'method' => 'authenticated_invitation',
                ])
                ->log('declined regional team invitation');

            return $reserve?->fresh();
        });
    }

    public function recordClothingDecision(
        TeamSelectionInvitation $invitation,
        User $user,
        string $decision,
    ): string {
        if ($decision !== 'not_required') {
            throw ValidationException::withMessages([
                'decision' => 'The clothing decision is invalid.',
            ]);
        }

        return DB::transaction(function () use ($invitation, $user, $decision): string {
            $locked = TeamSelectionInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            $this->decisionAccess->authorizePlayerDecision($user, $locked);
            if ($locked->status !== TeamSelectionInvitation::PAID_CONFIRMED) {
                throw new AuthorizationException('Complete event payment before recording a clothing decision.');
            }

            $orders = ClothingOrder::query()
                ->where('event_id', $locked->event_id)
                ->where('team_id', $locked->team_id)
                ->where('player_id', $locked->player_id)
                ->lockForUpdate()
                ->get(['id', 'pay_status', 'payfast_paid']);
            if ($orders->contains(fn (ClothingOrder $order): bool => (int) $order->pay_status === 1 || (bool) $order->payfast_paid)) {
                return 'paid_order';
            }
            if ($orders->isNotEmpty()) {
                return 'order_in_progress';
            }

            if ($locked->clothing_decision !== $decision || $locked->clothing_decided_at === null) {
                $locked->update([
                    'clothing_decision' => $decision,
                    'clothing_decided_at' => now(),
                ]);
            }

            return 'recorded';
        });
    }

    public function markWithdrawn(int $eventId, int $teamId, int $playerId, ?User $actor = null): ?TeamSelectionInvitation
    {
        return DB::transaction(function () use ($eventId, $teamId, $playerId, $actor) {
            $event = Event::query()->lockForUpdate()->findOrFail($eventId);
            $selectionImport = TeamSelectionImport::query()->where('event_id', $event->id)
                ->whereHas('invitations', fn ($query) => $query->where('team_id', $teamId)->where('player_id', $playerId)
                    ->whereIn('status', [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED]))
                ->latest('id')->lockForUpdate()->first();
            $invitation = TeamSelectionInvitation::query()->lockForUpdate()->with('selectionImport')
                ->where('event_id', $eventId)
                ->where('team_id', $teamId)
                ->where('player_id', $playerId)
                ->whereIn('status', [
                    TeamSelectionInvitation::INVITED,
                    TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                    TeamSelectionInvitation::PAID_CONFIRMED,
                ])
                ->latest('id')
                ->first();
            if (! $invitation) {
                return null;
            }
            abort_unless($selectionImport && (int) $invitation->import_id === (int) $selectionImport->id, 404);
            $invitation->setRelation('selectionImport', $selectionImport);

            if ($invitation->order_id) {
                $order = TeamPaymentOrder::query()->lockForUpdate()->find($invitation->order_id);
                if ($order && ! $order->pay_status && ! $order->payfast_paid && ! $order->wallet_debited) {
                    app(TeamPaymentService::class)->closeUnpaidLifecycle($order, $actor);
                } elseif ($order) {
                    app(TeamPaymentService::class)->recordWithdrawal($order, $actor);
                }
            }

            $rank = $invitation->roster_rank;
            $invitation->update([
                'status' => TeamSelectionInvitation::WITHDRAWN,
                'declined_at' => now(),
                'decline_reason' => 'Player withdrew from the team event.',
                'vacated_roster_rank' => $rank,
                'roster_rank' => null,
            ]);
            $reserve = $invitation->selectionImport?->auto_replacement_enabled
                ? $this->promoteNextReserve($invitation, $selectionImport, $rank)
                : null;

            $activity = activity('team-selection')->performedOn($invitation)
                ->withProperties(['replacement_id' => $reserve?->id, 'event_id' => $eventId, 'team_id' => $teamId]);
            if ($actor) {
                $activity->causedBy($actor);
            }
            $activity->log('withdrew from regional team selection');

            return $reserve?->fresh();
        });
    }

    public function replaceWithNextReserve(TeamSelectionInvitation $invitation, User $actor, string $reason): TeamSelectionInvitation
    {
        return DB::transaction(function () use ($invitation, $actor, $reason) {
            $selectionImport = $this->lockInvitationContext($invitation);
            $locked = TeamSelectionInvitation::query()->lockForUpdate()
                ->with(['selectionImport', 'team'])->findOrFail($invitation->id);
            $locked->setRelation('selectionImport', $selectionImport);
            if (! in_array($locked->status, [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true)) {
                throw ValidationException::withMessages([
                    'replacement' => 'Only an unpaid selected player can be replaced. Withdraw and process any paid player through the normal refund workflow.',
                ]);
            }
            if ($locked->order_id) {
                $order = TeamPaymentOrder::query()->lockForUpdate()->find($locked->order_id);
                if ($order && ($order->pay_status || $order->payfast_paid || $order->wallet_debited)) {
                    throw ValidationException::withMessages(['replacement' => 'Payment has already been received; use the withdrawal and refund workflow.']);
                }
                if ($order) app(TeamPaymentService::class)->closeUnpaidLifecycle($order, $actor);
            }

            $rank = $locked->roster_rank;
            $slot = TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $locked->team_id)
                ->where('rank', $rank)->where('player_id', $locked->player_id)->lockForUpdate()->first();
            if ($slot) app(TeamPaymentService::class)->updateTeamPlayerSlot($slot, ['player_id' => 0, 'pay_status' => 0]);

            $locked->update([
                'status' => TeamSelectionInvitation::DECLINED,
                'declined_at' => now(),
                'decline_reason' => $reason,
                'declined_by_user_id' => $actor->id,
                'decline_method' => 'regional_manager_replacement',
                'payment_started_at' => null,
                'vacated_roster_rank' => $rank,
                'roster_rank' => null,
            ]);
            $replacement = $this->promoteNextReserve($locked, $selectionImport, $rank, true);
            if (! $replacement) {
                throw ValidationException::withMessages([
                    'replacement' => 'No eligible reserve with a linked email account is available for this team.',
                ]);
            }
            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties([
                    'replacement_id' => $replacement->id,
                    'replacement_strategy' => 'next_reserve',
                    'reason' => $reason,
                ])
                ->log('regional manager replaced selected player with next reserve');

            return $replacement->fresh();
        });
    }

    public function moveRosterRank(TeamSelectionInvitation $invitation, User $actor, string $direction): void
    {
        DB::transaction(function () use ($invitation, $actor, $direction): void {
            $selectionImport = $this->lockInvitationContext($invitation);
            $current = TeamSelectionInvitation::query()->findOrFail($invitation->id);
            if ((int) $current->import_id !== (int) $selectionImport->id || ! in_array($current->status, [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                TeamSelectionInvitation::PAID_CONFIRMED,
            ], true) || ! $current->roster_rank) {
                throw ValidationException::withMessages(['order' => 'Only an active selected player can be reordered.']);
            }

            $targetRank = (int) $current->roster_rank + ($direction === 'up' ? -1 : 1);
            $swapId = TeamSelectionInvitation::query()
                ->where('import_id', $current->import_id)
                ->where('team_id', $current->team_id)
                ->where('roster_rank', $targetRank)
                ->whereIn('status', [
                    TeamSelectionInvitation::INVITED,
                    TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                    TeamSelectionInvitation::PAID_CONFIRMED,
                ])->value('id');
            if (! $swapId) {
                throw ValidationException::withMessages(['order' => 'That player is already at the end of the active roster.']);
            }

            $lockedPair = TeamSelectionInvitation::query()
                ->whereIn('id', collect([$current->id, $swapId])->map(fn ($id) => (int) $id)->sort()->values())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $locked = $lockedPair->get($current->id);
            $swap = $lockedPair->get((int) $swapId);
            if (! $locked || ! $swap
                || (int) $locked->import_id !== (int) $selectionImport->id
                || (int) $swap->import_id !== (int) $selectionImport->id
                || (int) $locked->team_id !== (int) $swap->team_id
                || (int) $locked->roster_rank !== (int) $current->roster_rank
                || (int) $swap->roster_rank !== $targetRank
                || ! in_array($locked->status, [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED], true)
                || ! in_array($swap->status, [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED], true)) {
                throw ValidationException::withMessages(['order' => 'The roster order changed while it was being updated. Refresh and try again.']);
            }

            $currentRank = (int) $locked->roster_rank;
            $slots = TeamPlayer::query()->withoutGlobalScopes()->lockForUpdate()
                ->where('team_id', $locked->team_id)
                ->whereIn('rank', [$currentRank, $targetRank])
                ->get()->keyBy('rank');
            if ($slots->count() !== 2) {
                throw ValidationException::withMessages(['order' => 'The roster positions no longer match the selection. Refresh and try again.']);
            }

            $currentSlot = $slots->get($currentRank);
            $targetSlot = $slots->get($targetRank);
            abort_unless((int) $currentSlot->player_id === (int) $locked->player_id
                && (int) $targetSlot->player_id === (int) $swap->player_id, 409);

            $currentSlot->update(['rank' => 0]);
            $targetSlot->update(['rank' => $currentRank]);
            $currentSlot->update(['rank' => $targetRank]);
            $locked->update(['roster_rank' => $targetRank]);
            $swap->update(['roster_rank' => $currentRank]);

            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties([
                    'team_id' => $locked->team_id,
                    'from_rank' => $currentRank,
                    'to_rank' => $targetRank,
                    'swapped_invitation_id' => $swap->id,
                ])->log('regional manager changed active roster order');
        });
    }

    public function reorderRoster(TeamSelectionImport $selectionImport, Team $team, array $invitationIds, User $actor, array $expectedIds): void
    {
        DB::transaction(function () use ($selectionImport, $team, $invitationIds, $actor, $expectedIds): void {
            Event::query()->lockForUpdate()->findOrFail($selectionImport->event_id);
            $import = TeamSelectionImport::query()->lockForUpdate()->findOrFail($selectionImport->id);
            if (!in_array($import->status, ['draft', 'sent'], true)) {
                throw ValidationException::withMessages(['order' => 'This selection import is no longer active. Refresh and try again.']);
            }
            $lockedTeam = Team::query()->withoutGlobalScopes()->with('category')->lockForUpdate()->findOrFail($team->id);
            abort_unless((int) $lockedTeam->category?->event_id === (int) $import->event_id
                && (int) $lockedTeam->region_id === (int) $import->region_id, 404);
            $selected = TeamSelectionInvitation::query()->where('import_id', $import->id)
                ->where('event_id', $import->event_id)->where('region_id', $import->region_id)
                ->where('team_id', $lockedTeam->id)->whereNotNull('roster_rank')
                ->whereIn('status', [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED])
                ->orderBy('roster_rank')->orderBy('id')->lockForUpdate()->get();
            $currentIds = $selected->pluck('id')->map(fn ($id) => (int) $id)->all();
            $requestedIds = array_map('intval', $invitationIds);
            if (array_map('intval', $expectedIds) !== $currentIds || !$selected->count() || count($requestedIds) !== count(array_unique($requestedIds))
                || collect($requestedIds)->sort()->values()->all() !== collect($currentIds)->sort()->values()->all()) {
                throw ValidationException::withMessages(['order' => 'The selected roster changed. Refresh and try again.']);
            }
            $ranks = $selected->pluck('roster_rank')->map(fn ($rank) => (int) $rank)->all();
            $slots = TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $lockedTeam->id)
                ->whereIn('rank', $ranks)->orderBy('id')->lockForUpdate()->get();
            if (min($ranks) < 1 || count(array_unique($ranks)) !== count($ranks) || $slots->count() !== $selected->count()
                || $selected->contains(fn ($invitation) => $slots->where('rank', $invitation->roster_rank)
                    ->where('player_id', $invitation->player_id)->count() !== 1)) {
                throw ValidationException::withMessages(['order' => 'The roster positions no longer match the selection. Refresh and try again.']);
            }
            $slotsByRank = $slots->keyBy('rank');
            if ($requestedIds === $currentIds) return;
            $invitationsById = $selected->keyBy('id');
            $originalRanks = $selected->mapWithKeys(fn ($invitation) => [$invitation->id => (int) $invitation->roster_rank]);
            $temporaryBase = min(0, (int) TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $lockedTeam->id)->min('rank')) - $selected->count() - 1;
            foreach ($selected as $offset => $invitation) {
                $slotsByRank->get($invitation->roster_rank)->update(['rank' => $temporaryBase + $offset]);
                $invitation->update(['roster_rank' => null]);
            }
            foreach ($requestedIds as $offset => $id) {
                $slotsByRank->get($originalRanks->get($id))->update(['rank' => $ranks[$offset]]);
                $invitationsById->get($id)->update(['roster_rank' => $ranks[$offset]]);
            }
            activity('team-selection')->performedOn($lockedTeam)->causedBy($actor)
                ->withProperties(['event_id' => $import->event_id, 'import_id' => $import->id,
                    'previous_invitation_ids' => $currentIds, 'invitation_ids' => $requestedIds, 'ranks' => $ranks])
                ->log('regional manager reordered selected roster by drag and drop');
        });
    }

    public function restoreDeclinedInvitation(TeamSelectionInvitation $invitation, User $actor): TeamSelectionInvitation
    {
        return $this->restoreInactiveInvitation($invitation, $actor, TeamSelectionInvitation::DECLINED);
    }

    public function restoreWithdrawnInvitation(TeamSelectionInvitation $invitation, User $actor): TeamSelectionInvitation
    {
        return $this->restoreInactiveInvitation($invitation, $actor, TeamSelectionInvitation::WITHDRAWN);
    }

    private function restoreInactiveInvitation(
        TeamSelectionInvitation $invitation,
        User $actor,
        string $expectedStatus
    ): TeamSelectionInvitation
    {
        return DB::transaction(function () use ($invitation, $actor, $expectedStatus): TeamSelectionInvitation {
            $selectionImport = $this->lockInvitationContext($invitation);
            $locked = TeamSelectionInvitation::query()->lockForUpdate()
                ->with(['selectionImport', 'player'])
                ->findOrFail($invitation->id);
            $locked->setRelation('selectionImport', $selectionImport);

            if ($locked->status === TeamSelectionInvitation::INVITED
                && $locked->declined_at
                && $locked->invited_at === null
                && $locked->roster_rank
                && data_get($locked->snapshot_json, 'restoration.kind') === $expectedStatus) {
                return $locked;
            }
            if ($locked->status !== $expectedStatus || ! $locked->vacated_roster_rank) {
                throw ValidationException::withMessages([
                    'restore' => 'Only a '.$expectedStatus.' player with a recorded roster position can be restored.',
                ]);
            }

            if (! in_array($selectionImport->status, ['draft', 'sent'], true)) {
                throw ValidationException::withMessages([
                    'restore' => 'This regional selection is no longer open for roster restoration.',
                ]);
            }
            if ((int) $locked->event_id !== (int) $selectionImport->event_id
                || (int) $locked->region_id !== (int) $selectionImport->region_id) {
                throw ValidationException::withMessages([
                    'restore' => 'The player selection no longer belongs to this event and region import.',
                ]);
            }
            if (($selectionImport->status === 'sent' || $expectedStatus === TeamSelectionInvitation::WITHDRAWN)
                && ! $this->replacementDeadlines($selectionImport)[0]) {
                throw ValidationException::withMessages([
                    'restore' => 'A '.$expectedStatus.' player cannot be restored after the event has started.',
                ]);
            }
            if ($expectedStatus === TeamSelectionInvitation::DECLINED && $locked->order_id) {
                $order = TeamPaymentOrder::query()->lockForUpdate()->find($locked->order_id);
                if ($order && ($order->pay_status || $order->payfast_paid || $order->wallet_debited)) {
                    throw ValidationException::withMessages([
                        'restore' => 'Payment has already been received; use the normal withdrawal and refund workflow.',
                    ]);
                }
            }

            $restoredRank = (int) $locked->vacated_roster_rank;
            $team = Team::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($locked->team_id);
            $teamBelongsToImport = (int) $team->region_id === (int) $selectionImport->region_id
                && $team->category_event_id
                && DB::table('category_events')->where('id', $team->category_event_id)
                    ->where('event_id', $selectionImport->event_id)->exists();
            if (! $teamBelongsToImport) {
                throw ValidationException::withMessages([
                    'restore' => 'The selected team no longer belongs to this event and region import.',
                ]);
            }
            $capacity = max(0, (int) $team->num_team_members);
            if ($capacity < 1 || $restoredRank > $capacity) {
                throw ValidationException::withMessages([
                    'restore' => 'The saved roster position is outside this team’s configured capacity.',
                ]);
            }
            $active = TeamSelectionInvitation::query()->lockForUpdate()
                ->where('import_id', $locked->import_id)
                ->where('team_id', $locked->team_id)
                ->where('roster_rank', '>=', $restoredRank)
                ->whereIn('status', [
                    TeamSelectionInvitation::INVITED,
                    TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                    TeamSelectionInvitation::PAID_CONFIRMED,
                ])
                ->orderByDesc('roster_rank')
                ->get();

            $targetInvitation = $active->first(
                fn (TeamSelectionInvitation $moving): bool => (int) $moving->roster_rank === $restoredRank
            );
            $targetSlot = TeamPlayer::query()->withoutGlobalScopes()->lockForUpdate()
                ->where('team_id', $locked->team_id)
                ->where('rank', $restoredRank)
                ->first();
            $targetSlotOccupied = $targetSlot && (int) $targetSlot->player_id > 0;
            if (($targetInvitation === null) !== ! $targetSlotOccupied
                || ($targetInvitation && (int) $targetSlot->player_id !== (int) $targetInvitation->player_id)) {
                throw ValidationException::withMessages([
                    'restore' => 'The saved roster position no longer matches the active team slot. Refresh and resolve the roster before restoring this player.',
                ]);
            }
            if (! $targetSlotOccupied) {
                $active = collect();
            }

            $overflow = $active->filter(fn (TeamSelectionInvitation $moving): bool => (int) $moving->roster_rank + 1 > $capacity);
            foreach ($overflow as $moving) {
                if ($moving->status !== TeamSelectionInvitation::INVITED
                    || $moving->order_id
                    || $moving->accepted_at
                    || $moving->payment_started_at
                    || $moving->paid_at) {
                    throw ValidationException::withMessages([
                        'restore' => 'Restoring this player would displace a player with payment activity. Resolve that player through the normal payment or withdrawal workflow first.',
                    ]);
                }
            }

            $lastActiveRank = $active->isEmpty() ? $restoredRank - 1 : (int) $active->first()->roster_rank;
            $slots = TeamPlayer::query()->withoutGlobalScopes()->lockForUpdate()
                ->where('team_id', $locked->team_id)
                ->whereBetween('rank', [$restoredRank, max($restoredRank, $lastActiveRank + 1)])
                ->get()
                ->keyBy('rank');

            foreach ($active as $moving) {
                $sourceRank = (int) $moving->roster_rank;
                $sourceSlot = $slots->get($sourceRank);
                if (! $sourceSlot || (int) $sourceSlot->player_id !== (int) $moving->player_id) {
                    throw ValidationException::withMessages([
                        'restore' => 'The roster order changed while the player was being restored. Refresh and try again.',
                    ]);
                }
                if ($overflow->contains('id', $moving->id)) {
                    if ((int) $sourceSlot->pay_status !== 0) {
                        throw ValidationException::withMessages([
                            'restore' => 'Restoring this player would displace a player with payment activity. Resolve that player through the normal payment or withdrawal workflow first.',
                        ]);
                    }
                    $moving->update([
                        'status' => TeamSelectionInvitation::RESERVE,
                        'roster_rank' => null,
                        'invited_at' => null,
                        'response_deadline_override' => null,
                        'payment_deadline_override' => null,
                    ]);
                    activity('team-selection')->performedOn($moving)->causedBy($actor)
                        ->withProperties([
                            'team_id' => $locked->team_id,
                            'capacity' => $capacity,
                            'restored_invitation_id' => $locked->id,
                            'previous_rank' => $sourceRank,
                            'email_queued' => false,
                        ])->log('returned overflow team selection player to reserve');
                    continue;
                }
                $destinationRank = $sourceRank + 1;
                $destinationSlot = $slots->get($destinationRank);
                if (! $destinationSlot) {
                    $destinationSlot = TeamPlayer::create([
                        'team_id' => $locked->team_id,
                        'player_id' => 0,
                        'rank' => $destinationRank,
                        'pay_status' => 0,
                    ]);
                    $slots->put($destinationRank, $destinationSlot);
                }
                app(TeamPaymentService::class)->updateTeamPlayerSlot($destinationSlot, [
                    'player_id' => $sourceSlot->player_id,
                    'pay_status' => $sourceSlot->pay_status,
                ]);
                $moving->update(['roster_rank' => $destinationRank]);
            }

            $restoredSlot = $slots->get($restoredRank);
            if (! $restoredSlot) {
                $restoredSlot = TeamPlayer::create([
                    'team_id' => $locked->team_id,
                    'player_id' => 0,
                    'rank' => $restoredRank,
                    'pay_status' => 0,
                ]);
            }
            app(TeamPaymentService::class)->updateTeamPlayerSlot($restoredSlot, [
                'player_id' => $locked->player_id,
                'pay_status' => 0,
            ]);

            $previousOrderId = $locked->order_id;
            $snapshot = $locked->snapshot_json ?? [];
            $previousOrderIds = collect(data_get($snapshot, 'restoration.previous_order_ids', []))
                ->merge(data_get($snapshot, 'restoration.previous_order_id') ? [data_get($snapshot, 'restoration.previous_order_id')] : [])
                ->merge($previousOrderId ? [$previousOrderId] : [])
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
            $snapshot['restoration'] = [
                'kind' => $expectedStatus,
                'restored_at' => now()->toIso8601String(),
                'restored_by_user_id' => $actor->id,
                'previous_order_id' => $previousOrderId,
                'previous_order_ids' => $previousOrderIds,
                'fresh_registration_required' => $expectedStatus === TeamSelectionInvitation::WITHDRAWN,
            ];
            $locked->update([
                'status' => TeamSelectionInvitation::INVITED,
                'roster_rank' => $restoredRank,
                'order_id' => null,
                'invited_at' => null,
                'accepted_at' => null,
                'payment_started_at' => null,
                'paid_at' => null,
                'response_deadline_override' => null,
                'payment_deadline_override' => null,
                'snapshot_json' => $snapshot,
            ]);

            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties([
                    'team_id' => $locked->team_id,
                    'restored_rank' => $restoredRank,
                    'shifted_invitation_ids' => $active->pluck('id')->all(),
                'previous_declined_at' => $locked->declined_at?->toIso8601String(),
                'previous_decline_reason' => $locked->decline_reason,
                'returned_to_reserve_invitation_ids' => $overflow->pluck('id')->all(),
                'email_queued' => false,
            ])->log($expectedStatus === TeamSelectionInvitation::WITHDRAWN
                ? 'regional manager restored withdrawn team selection player for fresh registration'
                : 'regional manager restored declined team selection player');

            $overflowPlayerIds = $overflow->pluck('player_id')->map(fn ($id) => (int) $id)->all();
            if ($overflowPlayerIds !== []) {
                $overflowSlots = TeamPlayer::query()->withoutGlobalScopes()->lockForUpdate()
                    ->where('team_id', $locked->team_id)
                    ->where('rank', '>', $capacity)
                    ->whereIn('player_id', $overflowPlayerIds)
                    ->get();
                foreach ($overflowSlots as $overflowSlot) {
                    app(TeamPaymentService::class)->updateTeamPlayerSlot($overflowSlot, [
                        'player_id' => 0,
                        'pay_status' => 0,
                    ]);
                }
            }

            return $locked->fresh(['player', 'selectionImport']);
        });
    }

    public function sendRestoredInvitation(TeamSelectionInvitation $invitation, User $actor): string
    {
        return DB::transaction(function () use ($invitation, $actor): string {
            $locked = TeamSelectionInvitation::query()->lockForUpdate()
                ->with(['selectionImport', 'player'])
                ->findOrFail($invitation->id);
            if ($locked->selectionImport?->status !== 'sent'
                || $locked->status !== TeamSelectionInvitation::INVITED
                || ! $locked->roster_rank
                || ! $locked->declined_at
                || $locked->invited_at !== null) {
                throw ValidationException::withMessages([
                    'email' => 'Only a restored, active, unpaid player whose invitation has not yet been re-sent can receive this email.',
                ]);
            }
            if (! $this->replacementDeadlines($locked->selectionImport)[0]) {
                throw ValidationException::withMessages([
                    'email' => 'A restored invitation cannot be sent after the event has started.',
                ]);
            }
            $responseDeadline = $locked->effectiveResponseDeadline();
            $paymentDeadline = $locked->effectivePaymentDeadline();
            if (! $responseDeadline || ! $paymentDeadline) {
                throw ValidationException::withMessages(['email' => 'Set response and payment deadlines before sending this restored invitation.']);
            }
            if (now()->gt($responseDeadline) || now()->gt($paymentDeadline)) {
                throw ValidationException::withMessages(['email' => 'Extend the response and payment deadlines before sending this restored invitation.']);
            }
            $email = $this->contactEmail($locked);
            if (! $email) {
                throw ValidationException::withMessages(['email' => 'This player does not have a valid profile, parent, or linked-account email address.']);
            }

            $log = BulkEmailLog::create([
                'mail_type' => 'team_selection_invitation',
                'related_type' => TeamSelectionInvitation::class,
                'related_id' => $locked->id,
                'recipient_email' => $email,
                'recipient_name' => $locked->player?->full_name,
                'status' => 'queued',
                'payload' => [
                    'kind' => 'invitation',
                    'campaign' => $this->savedCampaignSnapshot($locked->selectionImport),
                    'manual_restored_send' => true,
                ],
                'queued_at' => now(),
            ]);
            $locked->update(['invited_at' => now()]);
            SendTeamSelectionInvitationEmailJob::dispatch($log->id, $locked->event_id);
            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties(['recipient_email' => $email, 'email_log_id' => $log->id])
                ->log('regional manager manually sent restored team selection invitation');

            return $email;
        });
    }

    public function replaceWithSystemPlayer(
        TeamSelectionInvitation $invitation,
        Player $player,
        User $actor,
        string $reason,
    ): TeamSelectionInvitation {
        return DB::transaction(function () use ($invitation, $player, $actor, $reason): TeamSelectionInvitation {
            $selectionImport = $this->lockInvitationContext($invitation);
            $locked = TeamSelectionInvitation::query()->lockForUpdate()
                ->with(['selectionImport', 'team'])->findOrFail($invitation->id);
            $locked->setRelation('selectionImport', $selectionImport);
            if (! in_array($locked->status, [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true)) {
                throw ValidationException::withMessages([
                    'replacement' => 'Only an unpaid selected player can be replaced. Withdraw and process any paid player through the normal refund workflow.',
                ]);
            }

            if (! in_array($selectionImport->status, ['draft', 'sent'], true)) {
                throw ValidationException::withMessages(['replacement' => 'This regional selection is no longer open for player replacement.']);
            }
            if ($selectionImport->invitations()->where('team_id', $locked->team_id)
                ->where('player_id', $player->id)->exists()) {
                throw ValidationException::withMessages(['replacement_player_id' => 'This player is already recorded for this team.']);
            }

            $email = $this->contacts->primaryEmail($player);
            $replacementDeadlines = $selectionImport->status === 'sent'
                ? $this->replacementDeadlines($selectionImport)
                : [null, null];
            if ($selectionImport->status === 'sent' && ! $replacementDeadlines[0]) {
                throw ValidationException::withMessages([
                    'replacement' => 'A new invitation cannot be issued because the event has already started.',
                ]);
            }

            if ($locked->order_id) {
                $order = TeamPaymentOrder::query()->lockForUpdate()->find($locked->order_id);
                if ($order && ($order->pay_status || $order->payfast_paid || $order->wallet_debited)) {
                    throw ValidationException::withMessages(['replacement' => 'Payment has already been received; use the withdrawal and refund workflow.']);
                }
                if ($order) app(TeamPaymentService::class)->closeUnpaidLifecycle($order, $actor);
            }

            $rank = $locked->roster_rank;
            if (! $rank) {
                throw ValidationException::withMessages(['replacement' => 'The selected player no longer occupies an active roster rank.']);
            }
            $slot = TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $locked->team_id)
                ->where('rank', $rank)->where('player_id', $locked->player_id)->lockForUpdate()->first();
            if ($slot) {
                app(TeamPaymentService::class)->updateTeamPlayerSlot($slot, ['player_id' => 0, 'pay_status' => 0]);
            }

            $locked->update([
                'status' => TeamSelectionInvitation::DECLINED,
                'declined_at' => now(),
                'decline_reason' => $reason,
                'declined_by_user_id' => $actor->id,
                'decline_method' => 'regional_manager_custom_profile',
                'payment_started_at' => null,
                'vacated_roster_rank' => $rank,
                'roster_rank' => null,
            ]);

            $replacementRank = $this->closeRosterGap($locked, (int) $rank);
            $replacementSlot = TeamPlayer::query()->withoutGlobalScopes()
                ->where('team_id', $locked->team_id)->where('rank', $replacementRank)->lockForUpdate()->first();
            $replacementSlot ??= TeamPlayer::create([
                'team_id' => $locked->team_id,
                'rank' => $replacementRank,
                'player_id' => 0,
                'pay_status' => 0,
            ]);
            app(TeamPaymentService::class)->updateTeamPlayerSlot($replacementSlot, [
                'player_id' => $player->id,
                'pay_status' => 0,
            ]);

            $replacement = TeamSelectionInvitation::create([
                'import_id' => $selectionImport->id,
                'event_id' => $selectionImport->event_id,
                'region_id' => $selectionImport->region_id,
                'team_id' => $locked->team_id,
                'player_id' => $player->id,
                'ranking_list_id' => $locked->ranking_list_id,
                'ranking_position' => (int) $selectionImport->invitations()->where('team_id', $locked->team_id)->max('ranking_position') + 1,
                'queue_position' => (int) $selectionImport->invitations()->where('team_id', $locked->team_id)->max('queue_position') + 1,
                'total_points' => 0,
                'roster_rank' => $replacementRank,
                'status' => TeamSelectionInvitation::INVITED,
                'promoted_from_id' => $locked->id,
                'invited_at' => null,
                'response_deadline_override' => $replacementDeadlines[0],
                'payment_deadline_override' => $replacementDeadlines[1],
                'snapshot_json' => [
                    'selection_source' => 'manual_system_profile',
                    'player_name' => $player->full_name,
                    'ranking_position' => null,
                    'total_points' => null,
                    'ranking_run_id' => $selectionImport->ranking_run_id,
                    'replaces_invitation_id' => $locked->id,
                    'activation' => ['pending_manual_invitation' => $selectionImport->status === 'sent', 'source' => 'manual_replacement'],
                    'added_by' => $actor->id,
                    'reason' => $reason,
                ],
            ]);
            // Roster replacement never authorises an email. Review its pending invitation separately.
            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties([
                    'replacement_id' => $replacement->id,
                    'replacement_player_id' => $player->id,
                    'replacement_strategy' => 'custom_profile',
                    'vacated_rank' => (int) $rank,
                    'replacement_rank' => $replacementRank,
                    'reason' => $reason,
                ])->log('regional manager replaced selected player with custom system profile');

            return $replacement->fresh('player');
        });
    }

    /**
     * Close a vacated roster position and return the final active rank where a
     * manually selected replacement must be inserted.
     */
    private function closeRosterGap(TeamSelectionInvitation $vacated, int $vacatedRank): int
    {
        $activeBelow = TeamSelectionInvitation::query()->lockForUpdate()
            ->where('import_id', $vacated->import_id)
            ->where('team_id', $vacated->team_id)
            ->where('roster_rank', '>', $vacatedRank)
            ->whereIn('status', [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                TeamSelectionInvitation::PAID_CONFIRMED,
            ])
            ->orderBy('roster_rank')
            ->get();

        if ($activeBelow->isEmpty()) {
            return $vacatedRank;
        }

        $lastRank = (int) $activeBelow->last()->roster_rank;
        $slots = TeamPlayer::query()->withoutGlobalScopes()->lockForUpdate()
            ->where('team_id', $vacated->team_id)
            ->whereBetween('rank', [$vacatedRank, $lastRank])
            ->get()
            ->keyBy('rank');

        foreach ($activeBelow as $moving) {
            $sourceRank = (int) $moving->roster_rank;
            $destinationRank = $sourceRank - 1;
            $sourceSlot = $slots->get($sourceRank);
            $destinationSlot = $slots->get($destinationRank);
            if (! $sourceSlot || ! $destinationSlot
                || (int) $sourceSlot->player_id !== (int) $moving->player_id) {
                throw ValidationException::withMessages([
                    'replacement' => 'The roster order changed while the replacement was being made. Refresh the page and try again.',
                ]);
            }

            app(TeamPaymentService::class)->updateTeamPlayerSlot($destinationSlot, [
                'player_id' => $sourceSlot->player_id,
                'pay_status' => $sourceSlot->pay_status,
            ]);
            $moving->update(['roster_rank' => $destinationRank]);
        }

        return $lastRank;
    }

    public function addSystemPlayerAsReserve(TeamSelectionImport $import, Team $team, Player $player, User $actor, string $reason): TeamSelectionInvitation
    {
        return DB::transaction(function () use ($import, $team, $player, $actor, $reason): TeamSelectionInvitation {
            Event::query()->lockForUpdate()->findOrFail($import->event_id);
            $lockedImport = TeamSelectionImport::query()->lockForUpdate()->findOrFail($import->id);
            if (! in_array($lockedImport->status, ['draft', 'sent'], true)) {
                throw ValidationException::withMessages(['player_id' => 'Players can only be added to the current draft or sent selection.']);
            }

            $lockedTeam = Team::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($team->id);
            if ((int) $lockedTeam->region_id !== (int) $lockedImport->region_id) {
                throw ValidationException::withMessages(['player_id' => 'That team does not belong to this regional selection.']);
            }
            if ($lockedImport->invitations()->where('team_id', $lockedTeam->id)
                ->where('player_id', $player->id)->exists()) {
                throw ValidationException::withMessages(['player_id' => 'This player is already recorded for this team.']);
            }

            $template = $lockedImport->invitations()->where('team_id', $lockedTeam->id)
                ->orderBy('queue_position')->lockForUpdate()->first();
            if (! $template) {
                throw ValidationException::withMessages(['player_id' => 'Import the ranked player list for this team before adding another system profile.']);
            }

            $queuePosition = (int) $lockedImport->invitations()->where('team_id', $lockedTeam->id)->max('queue_position') + 1;
            $rankingPosition = (int) $lockedImport->invitations()->where('team_id', $lockedTeam->id)->max('ranking_position') + 1;
            $invitation = TeamSelectionInvitation::create([
                'import_id' => $lockedImport->id,
                'event_id' => $lockedImport->event_id,
                'region_id' => $lockedImport->region_id,
                'team_id' => $lockedTeam->id,
                'player_id' => $player->id,
                'ranking_list_id' => $template->ranking_list_id,
                'ranking_position' => $rankingPosition,
                'queue_position' => $queuePosition,
                'total_points' => 0,
                'roster_rank' => null,
                'status' => TeamSelectionInvitation::RESERVE,
                'snapshot_json' => [
                    'selection_source' => 'manual_system_profile',
                    'player_name' => $player->full_name,
                    'ranking_position' => null,
                    'total_points' => null,
                    'ranking_run_id' => $lockedImport->ranking_run_id,
                    'added_by' => $actor->id,
                    'reason' => $reason,
                ],
            ]);

            activity('team-selection')->performedOn($invitation)->causedBy($actor)
                ->withProperties([
                    'event_id' => $lockedImport->event_id,
                    'region_id' => $lockedImport->region_id,
                    'team_id' => $lockedTeam->id,
                    'player_id' => $player->id,
                    'queue_position' => $queuePosition,
                    'reason' => $reason,
                ])->log('regional manager added system player profile as reserve');

            return $invitation->fresh('player');
        });
    }

    public function assertRosterEditable(Team $team): void
    {
        $managed = TeamSelectionInvitation::query()
            ->where('team_id', $team->id)
            ->whereHas('selectionImport', fn ($query) => $query->whereIn('status', ['draft', 'sent']))
            ->exists();
        if ($managed) {
            throw ValidationException::withMessages([
                'team' => 'This roster is controlled by a ranking selection import. Use that workflow so invitations, reserves and payments remain synchronized.',
            ]);
        }
    }

    public function updateReplacementMode(TeamSelectionImport $import, bool $automatic, User $actor): TeamSelectionImport
    {
        return DB::transaction(function () use ($import, $automatic, $actor): TeamSelectionImport {
            $locked = TeamSelectionImport::query()->lockForUpdate()->findOrFail($import->id);
            if (! in_array($locked->status, ['draft', 'sent'], true)) {
                throw ValidationException::withMessages([
                    'replacement_mode' => 'Replacement mode can only be changed for an active invitation campaign.',
                ]);
            }

            $before = $locked->auto_replacement_enabled ? 'automatic' : 'manual';
            $locked->update(['auto_replacement_enabled' => $automatic]);
            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties([
                    'before' => $before,
                    'after' => $automatic ? 'automatic' : 'manual',
                ])->log('updated regional reserve replacement mode');

            return $locked->fresh();
        });
    }

    public function extendDeadlines(TeamSelectionImport $import, array $deadlines, User $actor): TeamSelectionImport
    {
        return DB::transaction(function () use ($import, $deadlines, $actor) {
            Event::query()->lockForUpdate()->findOrFail($import->event_id);
            $locked = TeamSelectionImport::query()->with('event')->lockForUpdate()->findOrFail($import->id);
            if ($locked->status !== 'sent') {
                throw ValidationException::withMessages(['import' => 'Deadlines can only be updated after invitations have been sent.']);
            }
            $response = now()->parse($deadlines['response_deadline']);
            $payment = now()->parse($deadlines['payment_deadline']);
            $replacement = now()->parse($deadlines['replacement_payment_deadline'] ?? $deadlines['payment_deadline']);

            $responseUnchanged = $this->submittedDeadlineIsUnchanged($response, $locked->response_deadline);
            $paymentUnchanged = $this->submittedDeadlineIsUnchanged($payment, $locked->payment_deadline);
            $eventStart = $locked->event?->start_date?->copy()->startOfDay();

            if ((! $responseUnchanged && ($response->isPast() || ($locked->response_deadline && $response->lt($locked->response_deadline))))
                || (! $paymentUnchanged && ($payment->isPast() || ($locked->payment_deadline && $payment->lt($locked->payment_deadline))))
                || $payment->lt($response)
                || $replacement->isPast()
                || $replacement->lt($payment)
                || ($eventStart && $replacement->gte($eventStart))) {
                throw ValidationException::withMessages([
                    'deadlines' => 'Changed response and payment deadlines must be extended. The replacement deadline must be in the future, on or after the payment deadline, and before the event starts.',
                ]);
            }

            $before = [
                'response_deadline' => $locked->response_deadline?->toIso8601String(),
                'payment_deadline' => $locked->payment_deadline?->toIso8601String(),
                'replacement_payment_deadline' => $locked->replacement_payment_deadline?->toIso8601String(),
            ];
            $affectedInvitations = $locked->invitations()
                ->where(function ($query) {
                    $query->whereNotNull('promoted_from_id')
                        ->orWhereNotNull('snapshot_json->activation->activated_at');
                })
                ->whereIn('status', [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT])
                ->lockForUpdate()
                ->get();

            $locked->update(['response_deadline' => $response, 'payment_deadline' => $payment, 'replacement_payment_deadline' => $replacement]);
            [$activeReplacementResponse, $activeReplacementPayment] = $this->replacementDeadlines($locked);
            foreach ($affectedInvitations as $invitation) {
                $invitation->update([
                    'response_deadline_override' => $activeReplacementResponse,
                    'payment_deadline_override' => $activeReplacementPayment,
                ]);
            }

            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties([
                    'before' => $before,
                    'after' => [
                        'response_deadline' => $response->toIso8601String(),
                        'payment_deadline' => $payment->toIso8601String(),
                        'replacement_payment_deadline' => $replacement->toIso8601String(),
                    ],
                    'propagated_replacement_deadline' => $activeReplacementPayment?->toIso8601String(),
                    'affected_invitation_ids' => $affectedInvitations->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                    'affected_invitation_count' => $affectedInvitations->count(),
                ])
                ->log('updated regional team invitation deadlines');

            return $locked->fresh();
        });
    }

    public function retryFailedEmails(TeamSelectionImport $import, User $actor): int
    {
        return DB::transaction(function () use ($import, $actor): int {
            $locked = TeamSelectionImport::query()->lockForUpdate()->findOrFail($import->id);
            if ($locked->status !== 'sent') {
                throw ValidationException::withMessages(['email' => 'Failed invitations can only be retried while the response window is open.']);
            }

            $invitations = $locked->invitations()->where('status', TeamSelectionInvitation::INVITED)->get()->keyBy('id');
            $logs = BulkEmailLog::query()
                ->where('mail_type', 'team_selection_invitation')
                ->where('related_type', TeamSelectionInvitation::class)
                ->whereIn('related_id', $invitations->keys())
                ->where('status', 'failed')
                ->lockForUpdate()
                ->get();
            $queued = 0;
            foreach ($logs as $log) {
                $invitation = $invitations->get($log->related_id);
                $email = $invitation ? $this->contactEmail($invitation) : null;
                if (! $email || ($invitation->effectiveResponseDeadline() && now()->gt($invitation->effectiveResponseDeadline()))) {
                    continue;
                }
                $log->update([
                    'recipient_email' => $email,
                    'status' => 'queued',
                    'queued_at' => now(),
                    'failed_at' => null,
                    'error_message' => null,
                ]);
                SendTeamSelectionInvitationEmailJob::dispatch($log->id, $locked->event_id);
                $queued++;
            }
            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties(['retried' => $queued])->log('retried failed regional team invitation emails');

            return $queued;
        });
    }

    public function resendInvitation(TeamSelectionInvitation $invitation, User $actor): string
    {
        return DB::transaction(function () use ($invitation, $actor): string {
            $locked = TeamSelectionInvitation::query()->lockForUpdate()
                ->with(['selectionImport', 'player'])->findOrFail($invitation->id);
            if ($locked->selectionImport?->status !== 'sent' || ! in_array($locked->status, [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
            ], true)) {
                throw ValidationException::withMessages(['email' => 'Only an active unpaid invitation can be resent.']);
            }
            $deadline = $locked->effectiveResponseDeadline();
            if ($deadline && now()->gt($deadline)) {
                throw ValidationException::withMessages(['email' => 'Extend the relevant invitation deadline before resending.']);
            }
            $email = $this->contactEmail($locked);
            if (! $email) {
                throw ValidationException::withMessages(['email' => 'This player does not have a valid profile, parent, or linked-account email address.']);
            }

            $log = BulkEmailLog::create([
                'mail_type' => 'team_selection_invitation',
                'related_type' => TeamSelectionInvitation::class,
                'related_id' => $locked->id,
                'recipient_email' => $email,
                'recipient_name' => $locked->player?->full_name,
                'status' => 'queued',
                'payload' => [
                    'kind' => $locked->promoted_from_id ? 'replacement' : 'invitation',
                    'campaign' => $this->savedCampaignSnapshot($locked->selectionImport),
                    'manual_resend' => true,
                ],
                'queued_at' => now(),
            ]);
            SendTeamSelectionInvitationEmailJob::dispatch($log->id, $locked->event_id);
            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties(['recipient_email' => $email, 'email_log_id' => $log->id])
                ->log('regional manager resent team selection invitation');

            return $email;
        });
    }

    private function submittedDeadlineIsUnchanged(mixed $submitted, mixed $stored): bool
    {
        return $stored && $submitted->format('Y-m-d H:i') === $stored->format('Y-m-d H:i');
    }

    public function savedCampaign(TeamSelectionImport $import): array
    {
        return $this->savedCampaignSnapshot($import);
    }

    /**
     * Expire unanswered or unpaid places and offer the exact vacated roster
     * rank to the next reserve while the replacement window remains open.
     *
     * @return array<int, array<string, mixed>>
     */
    public function processExpiredInvitations(?int $eventId = null, bool $apply = false): array
    {
        $candidates = TeamSelectionInvitation::query()
            ->with(['selectionImport', 'player'])
            ->whereIn('status', [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
            ])
            ->whereHas('selectionImport', fn ($query) => $query->where('status', 'sent'))
            ->when($eventId, fn ($query) => $query->where('event_id', $eventId))
            ->orderBy('id')
            ->get()
            ->filter(function (TeamSelectionInvitation $invitation): bool {
                $event = $invitation->selectionImport?->event;
                if ($event && app(ExternalTeamRosterService::class)->registrationIsOpen($event)) {
                    return false;
                }
                $deadline = $invitation->status === TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT
                    ? $this->paymentDeadline($invitation)
                    : $this->responseDeadline($invitation);

                return $deadline && now()->gt($deadline);
            });

        $rows = [];
        foreach ($candidates as $candidate) {
            $row = [
                'invitation_id' => $candidate->id,
                'player' => $candidate->player?->full_name ?? 'Unknown player',
                'previous_status' => $candidate->status,
                'replacement_id' => null,
            ];
            if ($apply) {
                $row['replacement_id'] = DB::transaction(function () use ($candidate): ?int {
                    $selectionImport = $this->lockInvitationContext($candidate);
                    $locked = TeamSelectionInvitation::query()->lockForUpdate()
                        ->with(['selectionImport', 'player'])->findOrFail($candidate->id);
                    $locked->setRelation('selectionImport', $selectionImport);
                    if (! in_array($locked->status, [
                        TeamSelectionInvitation::INVITED,
                        TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                    ], true)) {
                        return null;
                    }
                    $event = $locked->selectionImport?->event;
                    if ($event && app(ExternalTeamRosterService::class)->registrationIsOpen($event)) {
                        return null;
                    }
                    $deadline = $locked->status === TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT
                        ? $this->paymentDeadline($locked)
                        : $this->responseDeadline($locked);
                    if (! $deadline || now()->lte($deadline)) {
                        return null;
                    }

                    if ($locked->order_id) {
                        $order = TeamPaymentOrder::query()->lockForUpdate()->find($locked->order_id);
                        if ($order && ($order->pay_status || $order->payfast_paid || $order->wallet_debited)) {
                            return null;
                        }
                        if ($order) {
                            app(TeamPaymentService::class)->closeUnpaidLifecycle($order, null);
                        }
                    }

                    $rank = $locked->roster_rank;
                    $slot = TeamPlayer::query()->withoutGlobalScopes()
                        ->where('team_id', $locked->team_id)->where('rank', $rank)
                        ->where('player_id', $locked->player_id)->lockForUpdate()->first();
                    if ($slot) {
                        app(TeamPaymentService::class)->updateTeamPlayerSlot($slot, ['player_id' => 0, 'pay_status' => 0]);
                    }
                    $reason = $locked->status === TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT
                        ? 'Payment deadline expired.'
                        : 'Response deadline expired.';
                    $method = $locked->status === TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT
                        ? 'system_payment_deadline'
                        : 'system_response_deadline';
                    $locked->update([
                        'status' => TeamSelectionInvitation::DECLINED,
                        'declined_at' => now(),
                        'decline_reason' => $reason,
                        'decline_method' => $method,
                        'payment_started_at' => null,
                        'vacated_roster_rank' => $rank,
                        'roster_rank' => null,
                    ]);
                    $replacement = $locked->selectionImport?->auto_replacement_enabled
                        ? $this->promoteNextReserve($locked, $selectionImport, $rank)
                        : null;
                    activity('team-selection')->performedOn($locked)
                        ->withProperties(['replacement_id' => $replacement?->id, 'method' => $method])
                        ->log('expired regional team invitation');

                    return $replacement?->id;
                });
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function promoteNextReserveManually(TeamSelectionInvitation $vacated, User $actor): TeamSelectionInvitation
    {
        return DB::transaction(function () use ($vacated, $actor): TeamSelectionInvitation {
            $selectionImport = $this->lockInvitationContext($vacated);
            $locked = TeamSelectionInvitation::query()->lockForUpdate()
                ->with(['selectionImport', 'player'])->findOrFail($vacated->id);
            $locked->setRelation('selectionImport', $selectionImport);
            if (! in_array($locked->status, [TeamSelectionInvitation::DECLINED, TeamSelectionInvitation::WITHDRAWN], true)
                || ! $locked->vacated_roster_rank) {
                throw ValidationException::withMessages([
                    'replacement' => 'This invitation does not have an open roster vacancy.',
                ]);
            }
            $existing = TeamSelectionInvitation::query()
                ->where('promoted_from_id', $locked->id)
                ->whereIn('status', [
                    TeamSelectionInvitation::INVITED,
                    TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                    TeamSelectionInvitation::PAID_CONFIRMED,
                ])->first();
            if ($existing) {
                return $existing->load('player');
            }

            $replacement = $this->promoteNextReserve($locked, $selectionImport, (int) $locked->vacated_roster_rank, true);
            if (! $replacement) {
                throw ValidationException::withMessages([
                    'replacement' => 'No eligible reserve with a linked email is available, or the event has already started.',
                ]);
            }

            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties([
                    'replacement_id' => $replacement->id,
                    'vacated_rank' => $locked->vacated_roster_rank,
                    'method' => 'manual_reserve_approval',
                ])->log('regional manager manually promoted next reserve');

            return $replacement->fresh('player');
        });
    }

    public function activateReserveInOpenPlace(TeamSelectionInvitation $invitation, User $actor): TeamSelectionInvitation
    {
        return DB::transaction(function () use ($invitation, $actor): TeamSelectionInvitation {
            Event::query()->lockForUpdate()->findOrFail($invitation->event_id);
            $lockedImport = TeamSelectionImport::query()->lockForUpdate()->findOrFail($invitation->import_id);
            if (! in_array($lockedImport->status, ['draft', 'sent'], true)) {
                throw ValidationException::withMessages(['activation' => 'Only the current draft or sent selection can be changed.']);
            }

            $locked = TeamSelectionInvitation::query()->lockForUpdate()
                ->with(['player', 'team'])->findOrFail($invitation->id);
            if ((int) $locked->import_id !== (int) $lockedImport->id
                || $locked->status !== TeamSelectionInvitation::RESERVE) {
                throw ValidationException::withMessages(['activation' => 'Only a current reserve can fill an open team place.']);
            }

            $team = Team::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($locked->team_id);
            $capacity = max(0, (int) $team->num_team_members);
            $activeRanks = TeamSelectionInvitation::query()->lockForUpdate()
                ->where('import_id', $lockedImport->id)
                ->where('team_id', $team->id)
                ->whereIn('status', [
                    TeamSelectionInvitation::INVITED,
                    TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                    TeamSelectionInvitation::PAID_CONFIRMED,
                ])
                ->whereNotNull('roster_rank')
                ->pluck('roster_rank')
                ->map(fn ($rank) => (int) $rank)
                ->all();
            if (count($activeRanks) >= $capacity) {
                throw ValidationException::withMessages(['activation' => 'This team already fills all configured places.']);
            }

            $slots = TeamPlayer::query()->withoutGlobalScopes()->lockForUpdate()
                ->where('team_id', $team->id)
                ->whereBetween('rank', [1, max(1, $capacity)])
                ->get()
                ->keyBy('rank');
            $openRank = null;
            foreach (range(1, $capacity) as $rank) {
                $slot = $slots->get($rank);
                if (! in_array($rank, $activeRanks, true) && (! $slot || (int) $slot->player_id === 0)) {
                    $openRank = $rank;
                    break;
                }
            }
            if (! $openRank) {
                throw ValidationException::withMessages([
                    'activation' => 'No safe empty roster slot is available. Refresh the team and verify its configured places.',
                ]);
            }

            $slot = $slots->get($openRank) ?: TeamPlayer::create([
                'team_id' => $team->id,
                'rank' => $openRank,
                'player_id' => 0,
                'pay_status' => 0,
            ]);
            app(TeamPaymentService::class)->updateTeamPlayerSlot($slot, [
                'player_id' => $locked->player_id,
                'pay_status' => 0,
            ]);

            $replacementDeadlines = $lockedImport->status === 'sent'
                ? $this->replacementDeadlines($lockedImport)
                : [null, null];
            if ($lockedImport->status === 'sent' && ! $replacementDeadlines[0]) {
                throw ValidationException::withMessages([
                    'activation' => 'A reserve cannot be activated because the event has already started.',
                ]);
            }
            $locked->update([
                'status' => TeamSelectionInvitation::INVITED,
                'roster_rank' => $openRank,
                'invited_at' => null,
                'response_deadline_override' => $replacementDeadlines[0],
                'payment_deadline_override' => $replacementDeadlines[1],
                'snapshot_json' => array_replace_recursive($locked->snapshot_json ?? [], [
                    'activation' => [
                        'activated_at' => now()->toIso8601String(),
                        'activated_by_user_id' => $actor->id,
                        'pending_manual_invitation' => $lockedImport->status === 'sent',
                    ],
                ]),
            ]);
            $this->moveFromHelperTeamsToPrimaryTeam($locked, $lockedImport, suppressInvitationMail: true);
            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties([
                    'event_id' => $lockedImport->event_id,
                    'team_id' => $team->id,
                    'player_id' => $locked->player_id,
                    'roster_rank' => $openRank,
                    'email_queued' => false,
                    'pending_manual_invitation' => $lockedImport->status === 'sent',
                ])->log('activated reserve in open regional team place');

            return $locked->fresh('player');
        });
    }

    /**
     * @return array{queued: int, skipped_missing_email: int}
     */
    public function sendPendingActivatedInvitations(
        TeamSelectionImport $import,
        User $actor,
        string $expectedRecipientHash,
        int $expectedRecipientCount,
    ): array
    {
        return DB::transaction(function () use ($import, $actor, $expectedRecipientHash, $expectedRecipientCount): array {
            Event::query()->lockForUpdate()->findOrFail($import->event_id);
            $lockedImport = TeamSelectionImport::query()->lockForUpdate()->findOrFail($import->id);
            if ($lockedImport->status !== 'sent') {
                throw ValidationException::withMessages([
                    'email' => 'Pending newly activated invitations can only be sent from an existing sent campaign.',
                ]);
            }
            if (! $this->replacementDeadlines($lockedImport)[0]) {
                throw ValidationException::withMessages([
                    'email' => 'Pending newly activated invitations cannot be sent after the event has started.',
                ]);
            }

            $pending = TeamSelectionInvitation::query()->lockForUpdate()
                ->with('player')
                ->where('import_id', $lockedImport->id)
                ->where('event_id', $lockedImport->event_id)
                ->where('status', TeamSelectionInvitation::INVITED)
                ->whereNotNull('roster_rank')
                ->whereNull('invited_at')
                ->where('snapshot_json->activation->pending_manual_invitation', true)
                ->orderBy('team_id')->orderBy('roster_rank')->orderBy('id')
                ->get();
            if ($pending->isEmpty()) {
                return ['queued' => 0, 'skipped_missing_email' => 0];
            }

            $campaign = $this->savedCampaignSnapshot($lockedImport);
            $recipientRows = $pending->mapWithKeys(fn (TeamSelectionInvitation $invitation) => [
                $invitation->id => $this->contactEmail($invitation),
            ])->filter();
            $recipientHash = hash('sha256', $recipientRows
                ->map(fn (string $email, int $id) => $id.'|'.mb_strtolower(trim($email)))
                ->sort()->values()->implode("\n"));
            if ($recipientRows->count() !== $expectedRecipientCount || ! hash_equals($recipientHash, $expectedRecipientHash)) {
                throw ValidationException::withMessages([
                    'email' => 'The pending invitation recipient list changed. Review the current recipients and confirm again.',
                ]);
            }
            $queued = 0;
            $missing = 0;
            foreach ($pending as $invitation) {
                $responseDeadline = $invitation->effectiveResponseDeadline();
                $paymentDeadline = $invitation->effectivePaymentDeadline();
                if (! $responseDeadline || ! $paymentDeadline || now()->gt($responseDeadline) || now()->gt($paymentDeadline)) {
                    throw ValidationException::withMessages([
                        'email' => 'Extend the response and payment deadlines before sending the pending invitations.',
                    ]);
                }

                $email = $this->contactEmail($invitation);
                if (! $email) {
                    $missing++;
                    continue;
                }

                $queuedForThisActivation = $this->queueMail(
                    $invitation,
                    $email,
                    'replacement',
                    $campaign,
                    allowHistoricalRepeat: true,
                );
                if (! $queuedForThisActivation) {
                    continue;
                }
                $snapshot = $invitation->snapshot_json ?? [];
                data_set($snapshot, 'activation.pending_manual_invitation', false);
                data_set($snapshot, 'activation.invitation_sent_at', now()->toIso8601String());
                data_set($snapshot, 'activation.invitation_sent_by_user_id', $actor->id);
                $invitation->update([
                    'invited_at' => now(),
                    'snapshot_json' => $snapshot,
                ]);
                $queued++;
            }

            activity('team-selection')->performedOn($lockedImport)->causedBy($actor)
                ->withProperties([
                    'queued' => $queued,
                    'skipped_missing_email' => $missing,
                    'invitation_ids' => $pending->filter(fn (TeamSelectionInvitation $invitation) => $invitation->invited_at !== null)->pluck('id')->all(),
                ])->log('regional manager sent pending activated team selection invitations');

            return ['queued' => $queued, 'skipped_missing_email' => $missing];
        });
    }

    /**
     * @param array<int, int|string> $invitationIds
     * @return array{campaign: array, recipients: array<int, array{id:int,name:string,email:string,status:string,kind:string}>, hash: string}
     */
    public function previewCustomPendingActivatedInvitations(
        TeamSelectionImport $import,
        array $invitationIds,
        string $subject,
        string $message,
        bool $lockForUpdate = false,
        ?string $previewToken = null,
    ): array {
        $resolved = $this->resolveCustomPendingActivatedInvitations($import, $invitationIds, $lockForUpdate);
        $campaign = $this->customCampaignSnapshot($import, $subject, $message);
        $recipients = $resolved->map(fn (TeamSelectionInvitation $invitation): array => [
            'id' => (int) $invitation->id,
            'name' => (string) ($invitation->player?->full_name ?: 'Player'),
            'email' => (string) $this->contactEmail($invitation),
            'status' => (string) $invitation->status,
            'kind' => $this->customEmailKind($invitation),
        ])->values()->all();

        $preview = [
            'campaign' => $campaign,
            'recipients' => $recipients,
            'preview_token' => $previewToken ?: (string) \Illuminate\Support\Str::uuid(),
        ];
        $preview['hash'] = $this->customPendingPreviewHash($campaign, $recipients, $preview['preview_token']);

        return $preview;
    }

    /**
     * @param array<int, int|string> $invitationIds
     * @return array{queued:int}
     */
    public function sendCustomPendingActivatedInvitations(
        TeamSelectionImport $import,
        User $actor,
        array $invitationIds,
        string $subject,
        string $message,
        string $expectedPreviewHash,
        string $previewToken,
    ): array {
        return DB::transaction(function () use ($import, $actor, $invitationIds, $subject, $message, $expectedPreviewHash, $previewToken): array {
            Event::query()->lockForUpdate()->findOrFail($import->event_id);
            $lockedImport = TeamSelectionImport::query()->lockForUpdate()->findOrFail($import->id);
            $preview = $this->previewCustomPendingActivatedInvitations($lockedImport, $invitationIds, $subject, $message, true, $previewToken);
            if (! hash_equals($preview['hash'], $expectedPreviewHash)) {
                throw ValidationException::withMessages([
                    'email_preview' => 'Preview this exact player selection and custom email before sending. Any change requires a new preview.',
                ]);
            }

            $queued = 0;
            foreach ($preview['recipients'] as $recipient) {
                $invitation = TeamSelectionInvitation::query()->lockForUpdate()->findOrFail($recipient['id']);
                $pendingManual = $this->isPendingManualInvitation($invitation);
                $kind = $this->customEmailKind($invitation);
                $customCampaign = [...$preview['campaign'], 'custom_preview_hash' => $expectedPreviewHash];
                if (! $this->queueMail($invitation, $recipient['email'], $kind, $customCampaign, allowHistoricalRepeat: true, customPreviewHash: $expectedPreviewHash)) {
                    continue;
                }
                if ($kind === 'custom_invitation') {
                    $snapshot = $invitation->snapshot_json ?? [];
                    data_set($snapshot, 'activation.custom_campaign_hash', $preview['campaign']['hash']);
                    $updates = ['snapshot_json' => $snapshot];
                    if ($pendingManual) {
                        data_set($snapshot, 'activation.pending_manual_invitation', false);
                        data_set($snapshot, 'activation.invitation_sent_at', now()->toIso8601String());
                        data_set($snapshot, 'activation.invitation_sent_by_user_id', $actor->id);
                        $updates = ['invited_at' => now(), 'snapshot_json' => $snapshot];
                    }
                    $invitation->update($updates);
                }
                $queued++;
            }

            activity('team-selection')->performedOn($lockedImport)->causedBy($actor)
                ->withProperties([
                    'queued' => $queued,
                    'invitation_ids' => collect($preview['recipients'])->pluck('id')->all(),
                    'campaign_hash' => $preview['campaign']['hash'],
                ])->log('regional manager sent custom team selection player emails');

            return ['queued' => $queued];
        });
    }

    /** @param array<int, int|string> $invitationIds */
    private function resolveCustomPendingActivatedInvitations(TeamSelectionImport $import, array $invitationIds, bool $lockForUpdate = false)
    {
        $ids = collect($invitationIds)->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values();
        if ($ids->isEmpty() || $ids->count() !== count($invitationIds)) {
            throw ValidationException::withMessages(['invitation_ids' => 'Select at least one valid active team player.']);
        }
        $import->loadMissing('event');
        if ($import->status !== 'sent' || ! $import->event) {
            throw ValidationException::withMessages(['invitation_ids' => 'Custom player emails are not available for this campaign.']);
        }

        $invitations = TeamSelectionInvitation::query()->with('player')
            ->where('import_id', $import->id)
            ->where('event_id', $import->event_id)
            ->whereIn('id', $ids)
            ->whereIn('status', [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED])
            ->whereNotNull('roster_rank')
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->orderBy('id')->get();
        if ($invitations->count() !== $ids->count() || $invitations->contains(fn ($invitation) => ! $this->contactEmail($invitation))) {
            throw ValidationException::withMessages([
                'invitation_ids' => 'The selected player list is stale or includes an ineligible player. Review the current active team players and try again.',
            ]);
        }
        $registrationOpen = app(ExternalTeamRosterService::class)->registrationIsOpen($import->event);
        if ($invitations->contains(function (TeamSelectionInvitation $invitation) use ($registrationOpen): bool {
            $kind = $this->customEmailKind($invitation);
            if ($kind === 'custom_invitation') {
                return ! $registrationOpen;
            }

            return false;
        })) {
            throw ValidationException::withMessages([
                'invitation_ids' => 'One or more selected players can no longer receive this custom email. Review registration and payment availability, then try again.',
            ]);
        }
        return $invitations;
    }

    private function customCampaignSnapshot(TeamSelectionImport $import, string $subject, string $message): array
    {
        $campaign = $this->savedCampaignSnapshot($import);
        $campaign['subject'] = trim((string) preg_replace('/[\r\n]+/', ' ', $subject));
        $campaign['message'] = trim($message);
        // Custom checked-player emails deliberately carry no structured deadline.
        // Managers may include their own date in the message without reusing a stale campaign cutoff.
        $campaign['response_deadline'] = null;
        $withoutHash = $campaign;
        unset($withoutHash['hash']);
        $campaign['hash'] = hash('sha256', json_encode($withoutHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $campaign;
    }

    public function customEmailKind(TeamSelectionInvitation $invitation): string
    {
        return match ($invitation->status) {
            TeamSelectionInvitation::PAID_CONFIRMED => 'custom_paid_update',
            TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT => $invitation->effectivePaymentDeadline()
                && now()->gt($invitation->effectivePaymentDeadline())
                    ? 'custom_status_update'
                    : 'custom_payment_update',
            default => 'custom_invitation',
        };
    }

    private function isPendingManualInvitation(TeamSelectionInvitation $invitation): bool
    {
        return $invitation->status === TeamSelectionInvitation::INVITED
            && ! $invitation->invited_at
            && (bool) data_get($invitation->snapshot_json, 'activation.pending_manual_invitation');
    }

    /** @param array<int, array{id:int,name:string,email:string,status:string,kind:string}> $recipients */
    private function customPendingPreviewHash(array $campaign, array $recipients, string $previewToken): string
    {
        return hash('sha256', json_encode([
            'campaign_hash' => $campaign['hash'],
            'preview_token' => $previewToken,
            'recipients' => collect($recipients)->map(fn (array $recipient) => [
                'id' => $recipient['id'],
                'email' => mb_strtolower(trim($recipient['email'])),
                'status' => $recipient['status'],
                'kind' => $recipient['kind'],
            ])->values()->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function promoteNextReserve(
        TeamSelectionInvitation $vacated,
        TeamSelectionImport $selectionImport,
        ?int $rank,
        bool $allowDraft = false,
        bool $suppressInvitationMail = false,
    ): ?TeamSelectionInvitation
    {
        // Promotion changes roster state; every replacement email needs a new manual review.
        $suppressInvitationMail = true;
        $statusAllowsReplacement = $selectionImport->status === 'sent' || ($allowDraft && $selectionImport->status === 'draft');
        if (! $rank || ! $statusAllowsReplacement) {
            return null;
        }

        $reserves = TeamSelectionInvitation::query()->lockForUpdate()
            ->with(['player.user', 'player.users'])
            ->where('import_id', $vacated->import_id)
            ->where('team_id', $vacated->team_id)
            ->where('status', TeamSelectionInvitation::RESERVE)
            ->orderBy('queue_position')
            ->get();
        $reserve = $reserves->first(fn (TeamSelectionInvitation $candidate) => $this->contactEmail($candidate)
            && ! $this->hasPaidHelperPlacement($candidate));
        $email = $reserve ? $this->contactEmail($reserve) : null;
        if (! $reserve || ! $email) {
            return null;
        }

        $replacementDeadlines = $selectionImport->status === 'sent'
            ? $this->replacementDeadlines($selectionImport)
            : [null, null];
        if ($selectionImport->status === 'sent' && ! $replacementDeadlines[0]) {
            return null;
        }

        $replacementRank = $this->closeRosterGap($vacated, (int) $rank);
        $replacementSlot = TeamPlayer::query()->withoutGlobalScopes()
            ->where('team_id', $vacated->team_id)
            ->where('rank', $replacementRank)
            ->lockForUpdate()
            ->first();
        if (! $replacementSlot) {
            $replacementSlot = TeamPlayer::create([
                'team_id' => $vacated->team_id,
                'rank' => $replacementRank,
                'player_id' => 0,
                'pay_status' => 0,
            ]);
        }
        app(TeamPaymentService::class)->updateTeamPlayerSlot($replacementSlot, [
            'player_id' => $reserve->player_id,
            'pay_status' => 0,
        ]);
        $snapshot = $reserve->snapshot_json ?? [];
        if ($suppressInvitationMail && $selectionImport->status === 'sent') {
            data_set($snapshot, 'activation.pending_manual_invitation', true);
            data_set($snapshot, 'activation.activated_at', now()->toIso8601String());
            data_set($snapshot, 'activation.source', 'helper_team_cascade');
        }
        $reserve->update([
            'status' => TeamSelectionInvitation::INVITED,
            'roster_rank' => $replacementRank,
            'promoted_from_id' => $vacated->id,
            'invited_at' => $selectionImport->status === 'sent' && ! $suppressInvitationMail ? now() : null,
            'response_deadline_override' => $replacementDeadlines[0],
            'payment_deadline_override' => $replacementDeadlines[1],
            'snapshot_json' => $snapshot,
        ]);
        $this->moveFromHelperTeamsToPrimaryTeam($reserve, $selectionImport, $suppressInvitationMail);
        if ($selectionImport->status === 'sent' && ! $suppressInvitationMail) {
            $this->queueMail($reserve, $email, 'replacement', $this->savedCampaignSnapshot($selectionImport));
        }

        return $reserve;
    }

    private function hasPaidHelperPlacement(TeamSelectionInvitation $primaryReserve): bool
    {
        if (data_get($primaryReserve->snapshot_json, 'selection_source') === 'manual_system_profile') {
            return false;
        }

        return TeamSelectionInvitation::query()
            ->where('import_id', $primaryReserve->import_id)
            ->where('player_id', $primaryReserve->player_id)
            ->where('team_id', '!=', $primaryReserve->team_id)
            ->where('status', TeamSelectionInvitation::PAID_CONFIRMED)
            ->get()
            ->contains(fn (TeamSelectionInvitation $placement) => data_get(
                $placement->snapshot_json,
                'selection_source'
            ) === 'manual_system_profile');
    }

    private function moveFromHelperTeamsToPrimaryTeam(
        TeamSelectionInvitation $primarySelection,
        TeamSelectionImport $selectionImport,
        bool $suppressInvitationMail = false,
    ): void
    {
        if (data_get($primarySelection->snapshot_json, 'selection_source') === 'manual_system_profile') {
            return;
        }

        $primarySelection->loadMissing('team');
        $helperPlacements = TeamSelectionInvitation::query()
            ->lockForUpdate()
            ->where('import_id', $primarySelection->import_id)
            ->where('player_id', $primarySelection->player_id)
            ->where('team_id', '!=', $primarySelection->team_id)
            ->whereIn('status', [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
            ])
            ->get()
            ->filter(fn (TeamSelectionInvitation $placement) => data_get(
                $placement->snapshot_json,
                'selection_source'
            ) === 'manual_system_profile');

        foreach ($helperPlacements as $helper) {
            if ($helper->order_id) {
                $order = TeamPaymentOrder::query()->lockForUpdate()->find($helper->order_id);
                if ($order && ! $order->pay_status && ! $order->payfast_paid && ! $order->wallet_debited) {
                    app(TeamPaymentService::class)->closeUnpaidLifecycle($order, null);
                }
            }

            $rank = $helper->roster_rank;
            $message = 'This player has been put into the real team: '
                .($primarySelection->team?->name ?: 'primary age-group team').'.';
            $helper->update([
                'status' => TeamSelectionInvitation::WITHDRAWN,
                'declined_at' => now(),
                'decline_reason' => $message,
                'decline_method' => 'system_primary_team_promotion',
                'payment_started_at' => null,
                'vacated_roster_rank' => $rank,
                'roster_rank' => null,
            ]);
            if ($rank) {
                $slot = TeamPlayer::query()->withoutGlobalScopes()
                    ->where('team_id', $helper->team_id)
                    ->where('rank', $rank)
                    ->where('player_id', $helper->player_id)
                    ->lockForUpdate()
                    ->first();
                if ($slot) {
                    app(TeamPaymentService::class)->updateTeamPlayerSlot($slot, ['player_id' => 0, 'pay_status' => 0]);
                }
                $replacementRank = $this->closeRosterGap($helper, (int) $rank);
                if ($helper->selectionImport?->auto_replacement_enabled) {
                    $this->promoteNextReserve(
                        $helper,
                        $selectionImport,
                        $replacementRank,
                        allowDraft: true,
                        suppressInvitationMail: $suppressInvitationMail,
                    );
                }
            }

            activity('team-selection')->performedOn($helper)
                ->withProperties([
                    'primary_invitation_id' => $primarySelection->id,
                    'primary_team_id' => $primarySelection->team_id,
                    'helper_team_id' => $helper->team_id,
                    'player_id' => $primarySelection->player_id,
                ])->log('moved helper player into primary regional team');
        }
    }

    private function lockInvitationContext(TeamSelectionInvitation $invitation): TeamSelectionImport
    {
        $event = Event::query()->lockForUpdate()->findOrFail($invitation->event_id);
        $selectionImport = TeamSelectionImport::query()->lockForUpdate()->findOrFail($invitation->import_id);
        abort_unless((int) $selectionImport->event_id === (int) $event->id, 404);

        return $selectionImport;
    }

    private function contactEmail(TeamSelectionInvitation $invitation): ?string
    {
        $player = $invitation->relationLoaded('player') ? $invitation->player : $invitation->player()->first();

        return $this->contacts->primaryEmail($player);
    }

    private function queueMail(
        TeamSelectionInvitation $invitation,
        string $email,
        string $kind,
        array $campaign = [],
        bool $allowHistoricalRepeat = false,
        ?string $customPreviewHash = null,
    ): bool
    {
        if ($customPreviewHash && BulkEmailLog::query()->where([
            'mail_type' => 'team_selection_invitation',
            'related_type' => TeamSelectionInvitation::class,
            'related_id' => $invitation->id,
            'recipient_email' => $email,
            'payload->campaign->custom_preview_hash' => $customPreviewHash,
        ])->exists()) return false;
        if (! $allowHistoricalRepeat) {
            $existing = BulkEmailLog::query()->where([
                'mail_type' => 'team_selection_invitation',
                'related_type' => TeamSelectionInvitation::class,
                'related_id' => $invitation->id,
                'recipient_email' => $email,
            ])->whereIn('status', ['queued', 'sent'])->exists();
            if ($existing) return false;
        }

        $approvedMail = new \App\Mail\TeamSelectionInvitationMail($invitation, $kind, $campaign);
        $renderedHtml = $approvedMail->render();
        $renderedSubject = $approvedMail->envelope()->subject;
        $payload = ['kind' => $kind, 'campaign' => $campaign, 'rendered_html' => $renderedHtml, 'rendered_subject' => $renderedSubject,
            'event_id' => $invitation->event_id, 'recipient_email' => $email, 'recipient_name' => $invitation->player?->full_name,
            'related_type' => TeamSelectionInvitation::class, 'related_id' => $invitation->id];
        $payload['payload_integrity'] = app(\App\Services\InvitationMailSecurity::class)->payloadIntegrity($payload);
        $log = BulkEmailLog::create([
            'mail_type' => 'team_selection_invitation',
            'related_type' => TeamSelectionInvitation::class,
            'related_id' => $invitation->id,
            'recipient_email' => $email,
            'recipient_name' => $invitation->player?->full_name,
            'status' => 'queued',
            'payload' => $payload,
            'queued_at' => now(),
        ]);
        SendTeamSelectionInvitationEmailJob::dispatch($log->id, $invitation->event_id);

        return true;
    }

    public function previewCampaign(TeamSelectionImport $import, array $details): array
    {
        $import->loadMissing(['event.venues', 'region.clothingItems.sizes']);
        $response = now()->parse($details['response_deadline']);
        $payment = now()->parse($details['payment_deadline']);
        $replacement = now()->parse($details['replacement_payment_deadline'] ?? $details['payment_deadline']);

        return $this->campaignSnapshot($import, $details, $response, $payment, $replacement);
    }

    private function campaignSnapshot(
        TeamSelectionImport $import,
        array $details,
        mixed $response,
        mixed $payment,
        mixed $replacement,
    ): array {
        $event = $import->event;
        $region = $import->region;
        $subject = trim((string) preg_replace('/[\r\n]+/', ' ', (string) ($details['email_subject'] ?? 'Platteland team invitation: '.$event?->name)));
        $message = trim((string) ($details['email_message'] ?? 'You have been selected to represent your region. Please respond before the deadline.'));
        $eventInformation = array_key_exists('event_information', $details)
            ? trim((string) $details['event_information'])
            : ($event ? $this->defaultEventInformation($event) : '');
        $replyCandidate = filled($details['reply_to'] ?? null) ? mb_strtolower(trim((string) $details['reply_to'])) : null;
        $replyTo = $replyCandidate && filter_var($replyCandidate, FILTER_VALIDATE_EMAIL) ? $replyCandidate : null;
        $clothingAvailable = $region
            && $region->usesOnlineClothingOrders()
            && (bool) $region->clothing_order
            && $region->clothingItems()
                ->where('price', '>', 0)
                ->whereHas('sizes')
                ->exists();
        $includeClothing = (bool) ($details['include_clothing'] ?? false) && $clothingAvailable;
        $eventVenues = $event
            ? ($event->relationLoaded('venues') ? $event->getRelation('venues') : $event->venues()->get())
            : collect();
        $willPublishEvent = (bool) $event?->published || (bool) ($details['activate_registration'] ?? false);
        $eventDetails = [
            'name' => $event?->name,
            'published' => $willPublishEvent,
            'start_date' => $event?->start_date?->toDateString(),
            'end_date' => $event?->end_date?->toDateString(),
            'entry_fee' => (float) ($event?->entryFee ?? 0),
            'organizer' => $event?->organizer,
            'contact_email' => $event?->email,
            'venues' => $eventVenues->pluck('name')->filter()->values()->all(),
            'venue_notes' => trim((string) $event?->venue_notes),
            'public_url' => $willPublishEvent ? route('events.show', $event) : null,
        ];
        $clothingItems = $includeClothing
            ? $region->clothingItems
                ->filter(fn ($item) => (float) $item->price > 0 && $item->sizes->isNotEmpty())
                ->sortBy('ordering')
                ->map(fn ($item) => [
                    'name' => $item->item_type_name,
                    'price' => $this->clothingPrices->totals((float) $item->price)['total'],
                    'sizes' => $item->sizes->sortBy('ordering')->pluck('size')->filter()->values()->all(),
                ])->values()->all()
            : [];
        $snapshot = [
            'subject' => $subject,
            'message' => $message,
            'event_information' => $eventInformation,
            'reply_to' => $replyTo,
            'response_deadline' => $response->toIso8601String(),
            'payment_deadline' => $payment->toIso8601String(),
            'replacement_payment_deadline' => $replacement->toIso8601String(),
            'include_clothing' => $includeClothing,
            'event' => $eventDetails,
            'clothing_items' => $clothingItems,
        ];
        $recipients = $this->campaignRecipientSnapshot($import);
        $snapshot['recipient_hash'] = hash('sha256', json_encode($recipients, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $snapshot['recipient_count'] = $recipients->whereNotNull('email')->count();
        $snapshot['missing_email_count'] = $recipients->whereNull('email')->count();
        $snapshot['message_hashes'] = $import->invitations()->with(['selectionImport.event', 'region', 'team', 'player'])->where('status', TeamSelectionInvitation::INVITED)->orderBy('id')->get()->mapWithKeys(fn ($invitation) => [$invitation->id => hash('sha256', (new \App\Mail\TeamSelectionInvitationMail($invitation, 'invitation', $snapshot))->render())])->all();
        $snapshot['hash'] = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $snapshot;
    }

    private function campaignRecipientSnapshot(TeamSelectionImport $import): \Illuminate\Support\Collection
    {
        $invitations = $import->relationLoaded('invitations')
            ? $import->invitations
            : $import->invitations()->with(['player.user', 'player.users'])->get();

        return $invitations
            ->where('status', TeamSelectionInvitation::INVITED)
            ->map(fn (TeamSelectionInvitation $invitation): array => [
                'invitation_id' => (int) $invitation->id,
                'player_id' => (int) $invitation->player_id,
                'email' => $this->contactEmail($invitation),
            ])
            ->sortBy('invitation_id')
            ->values();
    }

    private function savedCampaignSnapshot(TeamSelectionImport $import): array
    {
        if (is_array($import->communication_snapshot) && $import->communication_snapshot !== []) {
            return $import->communication_snapshot;
        }

        return [
            'subject' => $import->email_subject,
            'message' => $import->email_message,
            'event_information' => $import->event_information,
            'reply_to' => $import->reply_to,
            'response_deadline' => $import->response_deadline?->toIso8601String(),
            'payment_deadline' => $import->payment_deadline?->toIso8601String(),
            'replacement_payment_deadline' => $import->replacement_payment_deadline?->toIso8601String(),
            'include_clothing' => (bool) $import->include_clothing,
            'hash' => $import->communication_hash,
        ];
    }

    private function responseDeadline(TeamSelectionInvitation $invitation): mixed
    {
        return $invitation->effectiveResponseDeadline();
    }

    private function paymentDeadline(TeamSelectionInvitation $invitation): mixed
    {
        return $invitation->effectivePaymentDeadline();
    }

    private function deadlineBlocksRegistration(TeamSelectionInvitation $invitation, mixed $deadline): bool
    {
        if (! $deadline || now()->lte($deadline)) {
            return false;
        }

        $event = $invitation->selectionImport?->event;

        return ! $event || ! app(ExternalTeamRosterService::class)->registrationIsOpen($event);
    }

    /** @return array{0: mixed, 1: mixed} */
    private function replacementDeadlines(TeamSelectionImport $import): array
    {
        $now = now();
        $minimum = $now->copy()->addHours(24);
        $campaignDeadline = $import->replacement_payment_deadline ?: $import->payment_deadline;
        $deadline = $campaignDeadline && $campaignDeadline->gt($minimum)
            ? $campaignDeadline->copy()
            : $minimum;
        $eventStart = $import->event?->start_date?->copy()->startOfDay();

        if ($eventStart) {
            $latest = $eventStart->copy()->subMinute();
            if ($latest->lte($now)) {
                return [null, null];
            }
            if ($deadline->gt($latest)) {
                $deadline = $latest->copy();
            }
        }

        return [$deadline->copy(), $deadline];
    }
}
