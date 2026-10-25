<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Models\BulkEmailLog;
use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\TeamSelectionInvitation;
use App\Services\BulkMailDispatcher;
use Illuminate\Support\Collection;

final class TeamSelectionReminderService
{
    public function __construct(
        private TeamSelectionContactService $contacts,
        private BulkMailDispatcher $mailer,
    ) {}

    public function summaries(Event $event, EventRegion $eventRegion): array
    {
        $result = [];
        foreach (['registration_clothing', 'incomplete_clothing'] as $kind) {
            foreach (['all', 'registered', 'unregistered'] as $audience) {
                $grouped = $this->recipients($event, $eventRegion, $kind, $audience);
                $result[$kind][$audience] = [
                    'emails' => $grouped->count(),
                    'players' => $grouped->flatMap(fn (array $recipient) => $recipient['players'])->pluck('invitation_id')->unique()->count(),
                ];
            }
        }

        return $result;
    }

    public function recipientHash(Event $event, EventRegion $eventRegion, string $kind, string $audience, ?int $teamId = null, ?int $playerId = null, bool $locking = false): string
    {
        return hash('sha256', $this->recipients($event, $eventRegion, $kind, $audience, $teamId, $playerId, $locking)
            ->map(fn (array $recipient) => [$recipient['email'], collect($recipient['players'])->pluck('invitation_id')->all()])
            ->values()->toJson());
    }

    /** Read-only content used by the common exact-recipient approval screen. */
    public function previewMessages(Event $event, EventRegion $eventRegion, string $kind, string $audience, ?int $teamId = null, ?int $playerId = null, bool $locking = false): Collection
    {
        return $this->recipients($event, $eventRegion, $kind, $audience, $teamId, $playerId, $locking)->map(function (array $recipient) use ($event, $eventRegion, $kind): array {
            $content = $this->content($event, $kind, $recipient['players'], (bool) $eventRegion->region?->usesOnlineClothingOrders());

            return ['email' => $recipient['email'], 'name' => $recipient['name'], 'kind' => 'players',
                'player_keys' => collect($recipient['players'])->pluck('invitation_id')->map(fn ($id) => 'invitation:'.$id)->all(),
                'subject' => $content['subject'], 'html' => $content['html']];
        });
    }

    public function send(Event $event, EventRegion $eventRegion, string $kind, string $audience, string $token, string $expectedHash, $actor, ?int $communicationBatchId = null, ?int $teamId = null, ?int $playerId = null, array $approvedMessages = []): array
    {
        $usesRegionalClothing = (bool) $eventRegion->region?->usesOnlineClothingOrders();
        if ($kind === 'incomplete_clothing' && ! $usesRegionalClothing) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'kind' => 'Clothing reminders are not available because this region does not use clothing ordering.',
            ]);
        }

        $recipients = $this->recipients($event, $eventRegion, $kind, $audience, $teamId, $playerId, $communicationBatchId !== null);
        if (! hash_equals($this->recipientHash($event, $eventRegion, $kind, $audience, $teamId, $playerId, $communicationBatchId !== null), $expectedHash)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'confirm_recipients' => 'The reminder recipient list changed. Review the current counts and confirm again.',
            ]);
        }
        if ($recipients->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['audience' => 'There are no matching players with a valid email address.']);
        }

        $mailType = 'team_selection_'.$kind.'_reminder';
        $stats = ['total' => 0, 'queued' => 0, 'skipped' => 0, 'invalid' => 0, 'duplicate' => 0, 'failed' => 0, 'players' => 0];
        $queuedPlayers = [];
        foreach ($recipients as $recipient) {
            $content = $this->content($event, $kind, $recipient['players'], $usesRegionalClothing);
            if ($communicationBatchId !== null) {
                $approved = collect($approvedMessages)->firstWhere('email', $recipient['email']);
                if (! $approved || $approved['subject'] !== $content['subject'] || $approved['html'] !== $content['html']) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['preview' => 'Reminder details changed. Review a fresh exact preview.']);
                }
            }
            $sent = $this->mailer->dispatch($mailType, $event, [[
                'email' => $recipient['email'], 'name' => $recipient['name'],
            ]], [
                'subject' => $content['subject'], 'message' => $content['html'],
                'from_name' => $actor->name ?: 'Cape Tennis',
                'reply_to' => $actor->email ?: config('mail.from.address'),
                'campaign_key' => $token, 'send_token' => $token, 'kind' => $kind, 'audience' => $audience,
                'event_region_id' => $eventRegion->id, 'region_id' => $eventRegion->region_id,
                'event_id' => $event->id, 'created_by' => $actor->id,
                'event_communication_batch_id' => $communicationBatchId, 'recipient_kind' => 'players',
                'player_keys' => collect($recipient['players'])->pluck('invitation_id')->map(fn ($id) => 'invitation:'.$id)->all(),
            ], false);
            foreach (['total', 'queued', 'skipped', 'invalid', 'duplicate', 'failed'] as $key) $stats[$key] += (int) $sent[$key];
            if ($sent['queued'] > 0) {
                foreach ($recipient['players'] as $player) $queuedPlayers[$player['invitation_id']] = true;
            }
        }
        $stats['players'] = count($queuedPlayers);

        activity('team-selection')->performedOn($event)->causedBy($actor)->withProperties($stats + [
            'kind' => $kind, 'audience' => $audience, 'send_token' => $token,
            'event_region_id' => $eventRegion->id, 'region_id' => $eventRegion->region_id,
        ])->log('sent regional final team selection reminder');

        $stats['severity'] = $stats['queued'] === 0 ? 'error' : (($stats['skipped'] || $stats['failed']) ? 'warning' : 'success');
        return $stats;
    }

    /** @return Collection<int, array{email:string,name:?string,players:array}> */
    public function recipients(Event $event, EventRegion $eventRegion, string $kind, string $audience, ?int $teamId = null, ?int $playerId = null, bool $locking = false): Collection
    {
        $usesRegionalClothing = (bool) $eventRegion->region?->usesOnlineClothingOrders();
        if ($kind === 'incomplete_clothing' && ! $usesRegionalClothing) {
            return collect();
        }

        if ($kind === 'registration_clothing' && ! $usesRegionalClothing) {
            if ($audience === 'registered') {
                return collect();
            }
            if ($audience === 'all') {
                $audience = 'unregistered';
            }
        }

        $current = fn ($query) => $locking ? $query->lockForUpdate() : $query;
        $invitations = $current(TeamSelectionInvitation::query())
            ->with(['player' => $current, 'player.user' => $current, 'player.users' => $current, 'team' => $current, 'region' => $current, 'selectionImport' => $current])
            ->where('event_id', $event->id)
            ->where('region_id', $eventRegion->region_id)
            ->when($teamId, fn ($query) => $query->where('team_id', $teamId))
            ->when($playerId, fn ($query) => $query->where('player_id', $playerId))
            ->whereHas('selectionImport', fn ($query) => $query->where('status', 'sent'))
            ->whereIn('status', $this->statuses($audience))
            ->orderBy('id')->get();

        $regionTeamIds = $invitations->pluck('team_id')->filter()->unique();
        $paidKeys = $current(ClothingOrder::query())->where('event_id', $event->id)->whereIn('team_id', $regionTeamIds)
            ->where(fn ($query) => $query->where('pay_status', 1)->orWhere('payfast_paid', true))
            ->get(['team_id', 'player_id'])->map(fn ($order) => $order->team_id.'-'.$order->player_id)->flip();
        $pendingKeys = $current(ClothingOrder::query())->where('event_id', $event->id)->whereIn('team_id', $regionTeamIds)
            ->where(fn ($query) => $query->where('pay_status', '!=', 1)->where(fn ($q) => $q->whereNull('payfast_paid')->orWhere('payfast_paid', false)))
            ->get(['team_id', 'player_id'])->map(fn ($order) => $order->team_id.'-'.$order->player_id)->flip();

        if ($kind === 'incomplete_clothing') {
            $invitations = $invitations->filter(function (TeamSelectionInvitation $invitation) use ($paidKeys): bool {
                if ($invitation->status !== TeamSelectionInvitation::PAID_CONFIRMED) return true;
                $key = $invitation->team_id.'-'.$invitation->player_id;
                return ! $paidKeys->has($key) && $invitation->clothing_decision !== 'not_required';
            })->values();
        }

        return $invitations->flatMap(function (TeamSelectionInvitation $invitation) use ($pendingKeys, $event) {
            $key = $invitation->team_id.'-'.$invitation->player_id;
            $primary = $this->contacts->primaryEmail($invitation->player);
            return $this->contacts->emails($invitation->player)->map(fn ($email) => [
                'email' => $email,
                'name' => $invitation->player?->full_name,
                'player' => [
                    'invitation_id' => $invitation->id,
                    'name' => $invitation->player?->full_name ?: 'Player',
                    'team' => $invitation->team?->name,
                    'registered' => $invitation->status === TeamSelectionInvitation::PAID_CONFIRMED,
                    'pending_clothing' => $pendingKeys->has($key),
                    'notification_only' => $email !== $primary,
                    'url' => $email !== $primary ? route('events.show', $event) : ($invitation->status === TeamSelectionInvitation::PAID_CONFIRMED
                        ? route('team-selection.invitations.show', $invitation)
                        : route('team-selection.invitations.show', ['invitation' => $invitation, 'action' => 'pay'])),
                ],
            ]);
        })->filter(fn (array $row) => filled($row['email']))
            ->groupBy('email')->map(function (Collection $rows): array {
                return [
                    'email' => $rows->first()['email'], 'name' => $rows->first()['name'],
                    'players' => $rows->pluck('player')->values()->all(),
                ];
            })->sortKeys()->values();
    }

    private function statuses(string $audience): array
    {
        return match ($audience) {
            'registered' => [TeamSelectionInvitation::PAID_CONFIRMED],
            'unregistered' => [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT],
            default => [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED],
        };
    }

    private function content(Event $event, string $kind, array $players, bool $usesRegionalClothing): array
    {
        $eventName = e($event->name);
        $subject = $kind === 'incomplete_clothing'
            ? "Final clothing order reminder – {$event->name}"
            : "Final registration reminder – {$event->name}";
        $intro = $kind === 'incomplete_clothing'
            ? "Clothing ordering for <strong>{$eventName}</strong> is closing soon. Please complete the action shown below."
            : "Registration for <strong>{$eventName}</strong> is closing soon. Please complete the action shown below.";
        $rows = collect($players)->map(function (array $player) use ($kind, $usesRegionalClothing): string {
            $name = e($player['name']);
            $team = e($player['team'] ?: 'Team event');
            if ($player['notification_only'] ?? false) $action = 'View event update';
            elseif (! $player['registered']) $action = 'Complete registration and payment';
            elseif ($kind === 'incomplete_clothing' && $player['pending_clothing']) $action = 'Review your pending clothing order';
            else $action = $usesRegionalClothing ? 'Order clothing or confirm that no clothing is required' : 'Review registration';
            $url = e($player['url']);
            return "<li style=\"margin-bottom:14px\"><strong>{$name}</strong> · {$team}<br><a href=\"{$url}\">".e($action).'</a></li>';
        })->implode('');
        $note = $kind === 'registration_clothing' && $usesRegionalClothing
            ? '<p>Optional clothing can be ordered after event registration and payment are confirmed.</p>'
            : ($kind === 'incomplete_clothing'
                ? '<p>If you do not require clothing, please use the link and select <strong>No clothing required</strong>.</p>'
                : '');

        return ['subject' => $subject, 'html' => "<p>{$intro}</p><ul>{$rows}</ul>{$note}<p>Regards<br>Cape Tennis</p>"];
    }
}
