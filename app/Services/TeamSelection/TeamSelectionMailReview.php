<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Mail\TeamSelectionInvitationMail;
use App\Models\{Event, EventRegion, TeamSelectionImport, TeamSelectionInvitation, User};
use Illuminate\Validation\ValidationException;

/** Bridges lifecycle mail to the common review without turning it into custom mail. */
final class TeamSelectionMailReview
{
    public function plan(Event $event, User $actor, array $options, bool $locking = false): array
    {
        abort_unless($event->isTeam() && app(RegionManagerAccessService::class)->isEventManager($actor, $event), 403);
        $current = fn ($query) => $locking ? $query->lockForUpdate() : $query;
        if ($locking) $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
        $action = $options['lifecycle_action'] ?? '';
        if ($action === 'reminder') {
            $region = $current(EventRegion::with(['region' => $current])->where('event_id', $event->id))->findOrFail($options['event_region_id']);
            abort_unless(in_array($options['reminder_kind'], ['registration_clothing', 'incomplete_clothing'], true)
                && in_array($options['reminder_audience'], ['all', 'registered', 'unregistered'], true), 422);
            $service = app(TeamSelectionReminderService::class);
            $teamId = $options['team_id'] ?? null;
            $playerId = $options['player_id'] ?? null;
            if ($teamId) abort_unless(\App\Models\Team::withoutGlobalScopes()->whereKey($teamId)->where('region_id', $region->region_id)->whereHas('category', fn ($q) => $q->where('event_id', $event->id))->exists(), 404);
            $messages = $service->previewMessages($event, $region, $options['reminder_kind'], $options['reminder_audience'], $teamId, $playerId, $locking)->all();
            $state = ['recipient_hash' => $service->recipientHash($event, $region, $options['reminder_kind'], $options['reminder_audience'], $teamId, $playerId, $locking), 'approved_messages' => $messages];
            $issues = [];
        } else {
            abort_unless(in_array($action, ['pending', 'resend', 'restored'], true), 422);
            $import = $current(TeamSelectionImport::where('event_id', $event->id)->where('status', 'sent'))->findOrFail($options['selection_import_id']);
            $import->setRelation('event', $event);
            abort_if(TeamSelectionImport::where('event_id', $event->id)->where('region_id', $import->region_id)->where('id', '>', $import->id)->exists(), 422);
            $query = $current($import->invitations()->with(['player' => $current, 'player.user' => $current, 'player.users' => $current, 'team' => $current, 'region' => $current]))
                ->where('event_id', $event->id)->where('region_id', $import->region_id)
                ->whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))
                ->whereHas('team', fn ($q) => $q->where('region_id', $import->region_id));
            if ($action === 'pending') {
                $query->where('status', TeamSelectionInvitation::INVITED)->whereNotNull('roster_rank')->whereNull('invited_at')
                    ->where('snapshot_json->activation->pending_manual_invitation', true);
                if (! empty($options['invitation_id'])) $query->whereKey($options['invitation_id']);
            } else {
                $query->whereKey($options['invitation_id'])->whereIn('status', [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT]);
                if ($action === 'restored') $query->where('status', TeamSelectionInvitation::INVITED)->whereNotNull('roster_rank')->whereNotNull('declined_at')->whereNull('invited_at');
            }
            $invitations = $query->orderBy('id')->get();
            if (in_array($action, ['pending', 'restored'], true) && $event->start_date && now()->gte($event->start_date)) {
                throw ValidationException::withMessages(['email' => 'Invitations cannot be sent after the event has started.']);
            }
            $campaign = app(TeamSelectionInvitationService::class)->savedCampaign($import);
            if ($action === 'pending') $campaign = app(TeamSelectionClothingMailPolicy::class)->currentCampaign((int) $import->region_id, $campaign);
            $messages = [];
            $issues = [];
            $contacts = [];
            $state = ['campaign' => $campaign, 'invitations' => []];
            foreach ($invitations as $invitation) {
                $invitation->setRelation('selectionImport', $import);
                $deadline = $invitation->effectiveResponseDeadline();
                $paymentDeadline = $invitation->effectivePaymentDeadline();
                if (in_array($action, ['pending', 'restored'], true) && (! $deadline || ! $paymentDeadline)) {
                    throw ValidationException::withMessages(['email' => 'Set response and payment deadlines before reviewing these invitations.']);
                }
                if (($deadline && now()->gt($deadline)) || ($action !== 'resend' && $paymentDeadline && now()->gt($paymentDeadline))) {
                    throw ValidationException::withMessages(['email' => 'Extend the relevant invitation deadlines before reviewing this email.']);
                }
                $state['invitations'][] = [$invitation->id, $invitation->team_id, $invitation->player_id, $invitation->status,
                    $invitation->roster_rank, $invitation->invited_at?->toIso8601String(), $deadline?->toIso8601String(), $paymentDeadline?->toIso8601String(), $invitation->snapshot_json];
                $email = app(TeamSelectionContactService::class)->primaryEmail($invitation->player);
                if (! $email) { $issues[] = $invitation->player?->full_name.' — no valid email address'; continue; }
                $contacts[$invitation->id] = mb_strtolower(trim($email));
                $kind = $action === 'pending' ? 'replacement' : ($action === 'restored' ? 'invitation' : $invitation->invitationMailKind());
                $mail = new TeamSelectionInvitationMail($invitation, $kind, $campaign);
                $messages[] = ['email' => $email, 'name' => $invitation->player?->full_name, 'kind' => 'players',
                    'player_keys' => ['player:'.$invitation->player_id], 'subject' => $mail->envelope()->subject, 'html' => $mail->render()];
                $state['approved_messages'][$invitation->id] = end($messages);
                // Additional contacts see the same notification-only template
                // used by queueMail; its only link is a published event URL.
                $contactEmails = app(TeamSelectionContactService::class)->emails($invitation->player);
                $state['contact_emails'][$invitation->id] = $contactEmails->all();
                foreach ($contactEmails->intersect(collect([$invitation->player?->email_2, $invitation->player?->email_3])
                    ->map(fn ($value) => mb_strtolower(trim((string) $value))))->reject(fn ($contact) => $contact === $email) as $contact) {
                    $copy = new TeamSelectionInvitationMail($invitation, $kind, [...$campaign, 'notification_only' => true]);
                    $messages[] = ['email' => $contact, 'name' => $invitation->player?->full_name, 'kind' => 'players',
                        'player_keys' => ['player:'.$invitation->player_id], 'subject' => $copy->envelope()->subject, 'html' => $copy->render()];
                    $state['approved_messages'][$invitation->id]['contact_copies'][$contact] = end($messages);
                }
            }
            $state['recipient_hash'] = hash('sha256', collect($contacts)->map(fn ($email, $id) => $id.'|'.$email)->sort()->values()->implode("\n"));
            $state['recipient_count'] = count($contacts);
        }
        if (! $messages) throw ValidationException::withMessages(['email' => 'There are no matching active players with a valid email address.']);

        return ['recipients' => $messages, 'issues' => $issues, 'lifecycle_state' => $state];
    }

    public function send(Event $event, User $actor, array $options, array $state, string $token, int $batchId): array
    {
        $action = $options['lifecycle_action'];
        if ($action === 'reminder') {
            $region = EventRegion::with(['region' => fn ($q) => $q->lockForUpdate()])->where('event_id', $event->id)->lockForUpdate()->findOrFail($options['event_region_id']);

            return app(TeamSelectionReminderService::class)->send($event, $region, $options['reminder_kind'], $options['reminder_audience'], $token, $state['recipient_hash'], $actor, $batchId, $options['team_id'] ?? null, $options['player_id'] ?? null, $state['approved_messages']);
        }
        $import = TeamSelectionImport::where('event_id', $event->id)->lockForUpdate()->findOrFail($options['selection_import_id']);
        $service = app(TeamSelectionInvitationService::class);
        if ($action === 'pending') return $service->sendPendingActivatedInvitations($import, $actor, $state['recipient_hash'], $state['recipient_count'], $batchId, $state['approved_messages'], isset($options['invitation_id']) ? [$options['invitation_id']] : []);
        $invitation = $import->invitations()->where('event_id', $event->id)->lockForUpdate()->findOrFail($options['invitation_id']);
        if ($action === 'restored') $service->sendRestoredInvitation($invitation, $actor, $batchId, $state['approved_messages'][$invitation->id]);
        else $service->resendInvitation($invitation, $actor, $batchId, $state['approved_messages'][$invitation->id]);

        return ['queued' => 1];
    }
}
