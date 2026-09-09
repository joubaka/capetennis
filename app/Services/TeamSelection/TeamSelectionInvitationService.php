<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Domain\Payments\Services\TeamPaymentService;
use App\Domain\Teams\Services\ExternalTeamRosterService;
use App\Jobs\SendTeamSelectionInvitationEmailJob;
use App\Models\BulkEmailLog;
use App\Models\TeamPaymentOrder;
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
    public function authorizePlayer(TeamSelectionInvitation $invitation, User $user): void
    {
        $player = $invitation->relationLoaded('player') ? $invitation->player : $invitation->player()->first();
        if (! $player || ((int) $player->userId !== (int) $user->id
            && ! $player->users()->whereKey($user->id)->exists())) {
            throw new AuthorizationException('This team invitation is not linked to your account.');
        }
    }

    public function send(TeamSelectionImport $import, array $deadlines, User $actor): array
    {
        return DB::transaction(function () use ($import, $deadlines, $actor) {
            $locked = TeamSelectionImport::query()->lockForUpdate()->with(['event', 'invitations.team'])->findOrFail($import->id);
            if ($locked->status === 'sent') {
                throw ValidationException::withMessages(['import' => 'These invitations have already been sent.']);
            }

            $response = now()->parse($deadlines['response_deadline']);
            $payment = now()->parse($deadlines['payment_deadline']);
            if ($response->isPast() || $payment->isPast() || $payment->lt($response)) {
                throw ValidationException::withMessages(['deadlines' => 'Use future deadlines with payment on or after the response deadline.']);
            }
            if (! app(ExternalTeamRosterService::class)->registrationIsOpen($locked->event)) {
                throw ValidationException::withMessages(['event' => 'Publish the event and open team registration before sending invitations.']);
            }

            $selected = $locked->invitations->where('status', TeamSelectionInvitation::INVITED);
            if ($selected->isEmpty()) {
                throw ValidationException::withMessages(['invitations' => 'There are no selected players to invite.']);
            }
            $unpublished = $selected->pluck('team')->filter(fn ($team) => ! $team?->published)->pluck('name')->unique()->values();
            if ($unpublished->isNotEmpty()) {
                throw ValidationException::withMessages(['teams' => 'Publish these teams before sending: '.$unpublished->implode(', ').'.']);
            }

            $candidates = $locked->invitations->whereIn('status', [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::RESERVE,
            ]);
            $missing = $candidates->filter(fn ($invitation) => ! $this->contactEmail($invitation));
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'email' => $missing->count().' selected or reserve player(s) do not have a linked account with a valid email address. Link their accounts before sending any invitations.',
                ]);
            }

            $campaign = $this->campaignSnapshot($locked, $deadlines, $response, $payment);

            $stats = ['selected' => $selected->count(), 'queued' => 0, 'missing_email' => 0];
            foreach ($selected as $invitation) {
                $email = $this->contactEmail($invitation);
                $invitation->update(['invited_at' => now()]);
                $this->queueMail($invitation, $email, 'invitation', $campaign);
                $stats['queued']++;
            }

            $locked->update([
                'response_deadline' => $response,
                'payment_deadline' => $payment,
                'email_subject' => $campaign['subject'],
                'email_message' => $campaign['message'],
                'event_information' => $campaign['event_information'],
                'reply_to' => $campaign['reply_to'],
                'include_clothing' => $campaign['include_clothing'],
                'communication_hash' => $campaign['hash'],
                'prepared_by' => $actor->id,
                'prepared_at' => now(),
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties($stats + [
                    'response_deadline' => $response->toIso8601String(),
                    'payment_deadline' => $payment->toIso8601String(),
                    'communication_hash' => $campaign['hash'],
                    'include_clothing' => $campaign['include_clothing'],
                ])
                ->log('sent regional team selection invitations');

            return $stats;
        });
    }

    public function accept(TeamSelectionInvitation $invitation, User $user): TeamSelectionInvitation
    {
        $this->authorizePlayer($invitation->loadMissing('player'), $user);

        return DB::transaction(function () use ($invitation, $user) {
            $locked = TeamSelectionInvitation::query()->lockForUpdate()
                ->with(['selectionImport.event', 'team', 'player'])->findOrFail($invitation->id);
            if ($locked->selectionImport->status !== 'sent' || $locked->status !== TeamSelectionInvitation::INVITED) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is not available for acceptance.']);
            }
            if ($locked->selectionImport->response_deadline && now()->gt($locked->selectionImport->response_deadline)) {
                throw ValidationException::withMessages(['invitation' => 'The response deadline has passed.']);
            }
            app(ExternalTeamRosterService::class)->assertCanRegister(
                $user, $locked->selectionImport->event, $locked->team, $locked->player
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
        $invitation = TeamSelectionInvitation::query()
            ->where('event_id', $order->event_id)
            ->where('team_id', $order->team_id)
            ->where('player_id', $order->player_id)
            ->where('status', TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT)
            ->latest('id')
            ->first();
        if ($invitation && ! $invitation->order_id) {
            $invitation->update(['order_id' => $order->id]);
        }

        return $invitation?->fresh();
    }

    public function assertPaymentOpen(int $eventId, int $teamId, int $playerId): void
    {
        $invitation = TeamSelectionInvitation::query()->with('selectionImport')
            ->where('event_id', $eventId)->where('team_id', $teamId)->where('player_id', $playerId)
            ->whereIn('status', [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT])
            ->latest('id')->first();
        if (! $invitation) return;
        if ($invitation->status === TeamSelectionInvitation::INVITED) {
            throw ValidationException::withMessages(['invitation' => 'Open your team invitation and accept the place before starting payment.']);
        }
        if ($invitation->selectionImport?->payment_deadline && now()->gt($invitation->selectionImport->payment_deadline)) {
            throw ValidationException::withMessages(['payment' => 'The payment deadline for this team invitation has passed.']);
        }
    }

    public function confirmPaidOrder(TeamPaymentOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $invitation = TeamSelectionInvitation::query()->lockForUpdate()
                ->where(function ($query) use ($order) {
                    $query->where('order_id', $order->id)
                        ->orWhere(fn ($fallback) => $fallback
                            ->where('event_id', $order->event_id)
                            ->where('team_id', $order->team_id)
                            ->where('player_id', $order->player_id));
                })
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

    public function decline(TeamSelectionInvitation $invitation, User $user, ?string $reason): ?TeamSelectionInvitation
    {
        $this->authorizePlayer($invitation->loadMissing('player'), $user);

        return DB::transaction(function () use ($invitation, $user, $reason) {
            $locked = TeamSelectionInvitation::query()->lockForUpdate()->with('selectionImport')->findOrFail($invitation->id);
            if ($locked->selectionImport->status !== 'sent' || ! in_array($locked->status, [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
            ], true)) {
                throw ValidationException::withMessages(['invitation' => 'Only an unpaid invitation can be declined.']);
            }
            $deadline = $locked->status === TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT
                ? $locked->selectionImport->payment_deadline
                : $locked->selectionImport->response_deadline;
            if ($deadline && now()->gt($deadline)) {
                throw ValidationException::withMessages(['invitation' => 'The invitation deadline has passed.']);
            }
            if ($locked->order_id) {
                $order = TeamPaymentOrder::query()->lockForUpdate()->find($locked->order_id);
                if ($order && ! $order->pay_status && ! $order->payfast_paid && ! $order->wallet_debited) {
                    app(TeamPaymentService::class)->cancelPayment($order);
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
                'roster_rank' => null,
            ]);
            $slot = TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $locked->team_id)
                ->where('rank', $rank)->where('player_id', $locked->player_id)->lockForUpdate()->first();
            if ($slot) app(TeamPaymentService::class)->updateTeamPlayerSlot($slot, ['player_id' => 0, 'pay_status' => 0]);

            $reserve = $this->promoteNextReserve($locked, $rank);
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

    public function markWithdrawn(int $eventId, int $teamId, int $playerId, ?User $actor = null): ?TeamSelectionInvitation
    {
        return DB::transaction(function () use ($eventId, $teamId, $playerId, $actor) {
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

            if ($invitation->order_id) {
                $order = TeamPaymentOrder::query()->lockForUpdate()->find($invitation->order_id);
                if ($order && ! $order->pay_status && ! $order->payfast_paid && ! $order->wallet_debited) {
                    app(TeamPaymentService::class)->cancelPayment($order);
                }
            }

            $rank = $invitation->roster_rank;
            $invitation->update([
                'status' => TeamSelectionInvitation::WITHDRAWN,
                'declined_at' => now(),
                'decline_reason' => 'Player withdrew from the team event.',
                'roster_rank' => null,
            ]);
            $reserve = $this->promoteNextReserve($invitation, $rank);

            $activity = activity('team-selection')->performedOn($invitation)
                ->withProperties(['replacement_id' => $reserve?->id, 'event_id' => $eventId, 'team_id' => $teamId]);
            if ($actor) {
                $activity->causedBy($actor);
            }
            $activity->log('withdrew from regional team selection');

            return $reserve?->fresh();
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

    public function extendDeadlines(TeamSelectionImport $import, array $deadlines, User $actor): TeamSelectionImport
    {
        return DB::transaction(function () use ($import, $deadlines, $actor) {
            $locked = TeamSelectionImport::query()->lockForUpdate()->findOrFail($import->id);
            if ($locked->status !== 'sent') {
                throw ValidationException::withMessages(['import' => 'Deadlines can only be extended after invitations have been sent.']);
            }
            $response = now()->parse($deadlines['response_deadline']);
            $payment = now()->parse($deadlines['payment_deadline']);
            if ($response->isPast() || $payment->isPast() || $payment->lt($response)
                || ($locked->response_deadline && $response->lt($locked->response_deadline))
                || ($locked->payment_deadline && $payment->lt($locked->payment_deadline))) {
                throw ValidationException::withMessages(['deadlines' => 'Deadlines may only be extended, with payment on or after the response deadline.']);
            }
            $locked->update(['response_deadline' => $response, 'payment_deadline' => $payment]);
            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties(['response_deadline' => $response->toIso8601String(), 'payment_deadline' => $payment->toIso8601String()])
                ->log('extended regional team invitation deadlines');

            return $locked->fresh();
        });
    }

    public function retryFailedEmails(TeamSelectionImport $import, User $actor): int
    {
        return DB::transaction(function () use ($import, $actor): int {
            $locked = TeamSelectionImport::query()->lockForUpdate()->findOrFail($import->id);
            if ($locked->status !== 'sent' || ($locked->response_deadline && now()->gt($locked->response_deadline))) {
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
                if (! $email) {
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

    private function promoteNextReserve(TeamSelectionInvitation $vacated, ?int $rank): ?TeamSelectionInvitation
    {
        $selectionImport = $vacated->selectionImport;
        if (! $rank || ! $selectionImport || $selectionImport->status !== 'sent'
            || ($selectionImport->response_deadline && now()->gt($selectionImport->response_deadline))) {
            return null;
        }

        $reserve = TeamSelectionInvitation::query()->lockForUpdate()
            ->where('import_id', $vacated->import_id)
            ->where('team_id', $vacated->team_id)
            ->where('status', TeamSelectionInvitation::RESERVE)
            ->orderBy('queue_position')
            ->first();
        $email = $reserve ? $this->contactEmail($reserve) : null;
        if (! $reserve || ! $email) {
            return null;
        }

        $replacementSlot = TeamPlayer::query()->withoutGlobalScopes()
            ->where('team_id', $vacated->team_id)
            ->where('rank', $rank)
            ->lockForUpdate()
            ->first();
        if (! $replacementSlot) {
            $replacementSlot = TeamPlayer::create([
                'team_id' => $vacated->team_id,
                'rank' => $rank,
                'player_id' => 0,
                'pay_status' => 0,
            ]);
        }
        if ((int) $replacementSlot->player_id > 0) {
            return null;
        }
        app(TeamPaymentService::class)->updateTeamPlayerSlot($replacementSlot, [
            'player_id' => $reserve->player_id,
            'pay_status' => 0,
        ]);
        $reserve->update([
            'status' => TeamSelectionInvitation::INVITED,
            'roster_rank' => $rank,
            'promoted_from_id' => $vacated->id,
            'invited_at' => now(),
        ]);
        $this->queueMail($reserve, $email, 'replacement', $this->savedCampaignSnapshot($selectionImport));

        return $reserve;
    }

    private function contactEmail(TeamSelectionInvitation $invitation): ?string
    {
        $player = $invitation->relationLoaded('player') ? $invitation->player : $invitation->player()->first();
        if (! $player) {
            return null;
        }

        $emails = collect();
        if ($player->userId) {
            $emails->push(User::query()->whereKey($player->userId)->value('email'));
        }
        $emails = $emails->merge($player->users()->pluck('email'));
        $email = $emails->first(fn ($candidate) => filter_var($candidate, FILTER_VALIDATE_EMAIL));

        return $email ? mb_strtolower(trim((string) $email)) : null;
    }

    private function queueMail(
        TeamSelectionInvitation $invitation,
        string $email,
        string $kind,
        array $campaign = [],
    ): void
    {
        $existing = BulkEmailLog::query()->where([
            'mail_type' => 'team_selection_invitation',
            'related_type' => TeamSelectionInvitation::class,
            'related_id' => $invitation->id,
            'recipient_email' => $email,
        ])->whereIn('status', ['queued', 'sent'])->exists();
        if ($existing) return;

        $log = BulkEmailLog::create([
            'mail_type' => 'team_selection_invitation',
            'related_type' => TeamSelectionInvitation::class,
            'related_id' => $invitation->id,
            'recipient_email' => $email,
            'recipient_name' => $invitation->player?->full_name,
            'status' => 'queued',
            'payload' => ['kind' => $kind, 'campaign' => $campaign],
            'queued_at' => now(),
        ]);
        SendTeamSelectionInvitationEmailJob::dispatch($log->id, $invitation->event_id);
    }

    public function previewCampaign(TeamSelectionImport $import, array $details): array
    {
        $import->loadMissing(['event', 'region.clothingItems.sizes']);
        $response = now()->parse($details['response_deadline']);
        $payment = now()->parse($details['payment_deadline']);

        return $this->campaignSnapshot($import, $details, $response, $payment);
    }

    private function campaignSnapshot(
        TeamSelectionImport $import,
        array $details,
        mixed $response,
        mixed $payment,
    ): array {
        $event = $import->event;
        $region = $import->region;
        $subject = trim((string) ($details['email_subject'] ?? 'Platteland team invitation: '.$event?->name));
        $message = trim((string) ($details['email_message'] ?? 'You have been selected to represent your region. Please respond before the deadline.'));
        $eventInformation = trim((string) ($details['event_information'] ?? strip_tags((string) $event?->information)));
        $replyTo = filled($details['reply_to'] ?? null) ? mb_strtolower(trim((string) $details['reply_to'])) : null;
        $clothingAvailable = $region
            && $region->usesOnlineClothingOrders()
            && (bool) $region->clothing_order
            && $region->clothingItems()
                ->where('price', '>', 0)
                ->whereHas('sizes')
                ->exists();
        $includeClothing = (bool) ($details['include_clothing'] ?? false) && $clothingAvailable;
        $snapshot = [
            'subject' => $subject,
            'message' => $message,
            'event_information' => $eventInformation,
            'reply_to' => $replyTo,
            'response_deadline' => $response->toIso8601String(),
            'payment_deadline' => $payment->toIso8601String(),
            'include_clothing' => $includeClothing,
        ];
        $snapshot['hash'] = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $snapshot;
    }

    private function savedCampaignSnapshot(TeamSelectionImport $import): array
    {
        return [
            'subject' => $import->email_subject,
            'message' => $import->email_message,
            'event_information' => $import->event_information,
            'reply_to' => $import->reply_to,
            'response_deadline' => $import->response_deadline?->toIso8601String(),
            'payment_deadline' => $import->payment_deadline?->toIso8601String(),
            'include_clothing' => (bool) $import->include_clothing,
            'hash' => $import->communication_hash,
        ];
    }
}
