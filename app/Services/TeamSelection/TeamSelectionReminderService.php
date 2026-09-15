<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Models\BulkEmailLog;
use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\TeamSelectionInvitation;
use App\Services\BulkMailDispatcher;
use Illuminate\Support\Collection;

final class TeamSelectionReminderService
{
    public function __construct(
        private TeamSelectionContactService $contacts,
        private BulkMailDispatcher $mailer,
    ) {}

    public function summaries(Event $event): array
    {
        $result = [];
        foreach (['registration_clothing', 'incomplete_clothing'] as $kind) {
            foreach (['all', 'registered', 'unregistered'] as $audience) {
                $grouped = $this->recipients($event, $kind, $audience);
                $result[$kind][$audience] = [
                    'emails' => $grouped->count(),
                    'players' => $grouped->sum(fn (array $recipient) => count($recipient['players'])),
                ];
            }
        }

        return $result;
    }

    public function recipientHash(Event $event, string $kind, string $audience): string
    {
        return hash('sha256', $this->recipients($event, $kind, $audience)
            ->map(fn (array $recipient) => [$recipient['email'], collect($recipient['players'])->pluck('invitation_id')->all()])
            ->values()->toJson());
    }

    public function send(Event $event, string $kind, string $audience, string $token, string $expectedHash, $actor): array
    {
        $recipients = $this->recipients($event, $kind, $audience);
        if (! hash_equals($this->recipientHash($event, $kind, $audience), $expectedHash)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'confirm_recipients' => 'The reminder recipient list changed. Review the current counts and confirm again.',
            ]);
        }
        if ($recipients->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['audience' => 'There are no matching players with a valid email address.']);
        }

        $mailType = 'team_selection_'.$kind.'_reminder';
        $stats = ['queued' => 0, 'skipped' => 0, 'players' => 0];
        foreach ($recipients as $recipient) {
            $alreadyQueued = BulkEmailLog::query()->where('mail_type', $mailType)
                ->where('related_type', Event::class)->where('related_id', $event->id)
                ->where('recipient_email', $recipient['email'])
                ->where('payload->send_token', $token)->exists();
            if ($alreadyQueued) {
                $stats['skipped']++;
                continue;
            }
            $content = $this->content($event, $kind, $recipient['players']);
            $sent = $this->mailer->dispatch($mailType, $event, [[
                'email' => $recipient['email'], 'name' => $recipient['name'],
            ]], [
                'subject' => $content['subject'], 'message' => $content['html'],
                'from_name' => $actor->name ?: 'Cape Tennis',
                'reply_to' => $actor->email ?: config('mail.from.address'),
                'send_token' => $token, 'kind' => $kind, 'audience' => $audience,
            ], true);
            $stats['queued'] += (int) $sent['queued'];
            $stats['players'] += count($recipient['players']);
        }

        activity('team-selection')->performedOn($event)->causedBy($actor)->withProperties($stats + [
            'kind' => $kind, 'audience' => $audience, 'send_token' => $token,
        ])->log('sent final team selection reminder');

        return $stats;
    }

    /** @return Collection<int, array{email:string,name:?string,players:array}> */
    public function recipients(Event $event, string $kind, string $audience): Collection
    {
        $invitations = TeamSelectionInvitation::query()
            ->with(['player.user', 'player.users', 'team', 'region', 'selectionImport'])
            ->where('event_id', $event->id)
            ->whereHas('selectionImport', fn ($query) => $query->where('status', 'sent'))
            ->whereIn('status', $this->statuses($audience))
            ->orderBy('id')->get();

        $paidKeys = ClothingOrder::query()->where('event_id', $event->id)
            ->where(fn ($query) => $query->where('pay_status', 1)->orWhere('payfast_paid', true))
            ->get(['team_id', 'player_id'])->map(fn ($order) => $order->team_id.'-'.$order->player_id)->flip();
        $pendingKeys = ClothingOrder::query()->where('event_id', $event->id)
            ->where(fn ($query) => $query->where('pay_status', '!=', 1)->where(fn ($q) => $q->whereNull('payfast_paid')->orWhere('payfast_paid', false)))
            ->get(['team_id', 'player_id'])->map(fn ($order) => $order->team_id.'-'.$order->player_id)->flip();

        if ($kind === 'incomplete_clothing') {
            $invitations = $invitations->filter(function (TeamSelectionInvitation $invitation) use ($paidKeys): bool {
                if ($invitation->status !== TeamSelectionInvitation::PAID_CONFIRMED) return true;
                $key = $invitation->team_id.'-'.$invitation->player_id;
                return ! $paidKeys->has($key) && $invitation->clothing_decision !== 'not_required';
            })->values();
        }

        return $invitations->map(function (TeamSelectionInvitation $invitation) use ($pendingKeys): array {
            $key = $invitation->team_id.'-'.$invitation->player_id;
            return [
                'email' => $this->contacts->primaryEmail($invitation->player),
                'name' => $invitation->player?->full_name,
                'player' => [
                    'invitation_id' => $invitation->id,
                    'name' => $invitation->player?->full_name ?: 'Player',
                    'team' => $invitation->team?->name,
                    'registered' => $invitation->status === TeamSelectionInvitation::PAID_CONFIRMED,
                    'pending_clothing' => $pendingKeys->has($key),
                    'url' => $invitation->status === TeamSelectionInvitation::PAID_CONFIRMED
                        ? route('team-selection.invitations.show', $invitation)
                        : route('team-selection.invitations.show', ['invitation' => $invitation, 'action' => 'pay']),
                ],
            ];
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

    private function content(Event $event, string $kind, array $players): array
    {
        $eventName = e($event->name);
        $subject = $kind === 'incomplete_clothing'
            ? "Final clothing order reminder – {$event->name}"
            : "Final registration reminder – {$event->name}";
        $intro = $kind === 'incomplete_clothing'
            ? "Clothing ordering for <strong>{$eventName}</strong> is closing soon. Please complete the action shown below."
            : "Registration for <strong>{$eventName}</strong> is closing soon. Please complete the action shown below.";
        $rows = collect($players)->map(function (array $player) use ($kind): string {
            $name = e($player['name']);
            $team = e($player['team'] ?: 'Team event');
            if (! $player['registered']) $action = 'Complete registration and payment';
            elseif ($kind === 'incomplete_clothing' && $player['pending_clothing']) $action = 'Review your pending clothing order';
            else $action = 'Order clothing or confirm that no clothing is required';
            $url = e($player['url']);
            return "<li style=\"margin-bottom:14px\"><strong>{$name}</strong> · {$team}<br><a href=\"{$url}\">".e($action).'</a></li>';
        })->implode('');
        $note = $kind === 'registration_clothing'
            ? '<p>Optional clothing can be ordered after event registration and payment are confirmed.</p>'
            : '<p>If you do not require clothing, please use the link and select <strong>No clothing required</strong>.</p>';

        return ['subject' => $subject, 'html' => "<p>{$intro}</p><ul>{$rows}</ul>{$note}<p>Regards<br>Cape Tennis</p>"];
    }
}
