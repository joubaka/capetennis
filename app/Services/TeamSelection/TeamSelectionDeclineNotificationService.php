<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Mail\BulkEventMail;
use App\Models\{BulkEmailLog, EventRegion, TeamSelectionInvitation};
use App\Services\BulkMailDispatcher;

final class TeamSelectionDeclineNotificationService
{
    public function record(TeamSelectionInvitation $invitation): void
    {
        $content = $this->content($invitation);
        if (! $content) {
            activity('team-selection')->performedOn($invitation)
                ->withProperties(['event_id' => $invitation->event_id, 'region_id' => $invitation->region_id])
                ->log('decline notification not queued: regional convenor contact unavailable');
            return;
        }
        app(BulkMailDispatcher::class)->dispatch('team_selection_decline_notification', $invitation,
            [['email' => $content['email'], 'name' => $content['name']]],
            ['event_id' => $invitation->event_id, 'region_id' => $invitation->region_id,
                'created_by' => $invitation->declined_by_user_id, 'system_initiated' => true, 'manual_retry_only' => true,
                'campaign_key' => 'team-selection-decline:'.$invitation->id.':'.$invitation->declined_at?->toIso8601String(),
                'subject' => $content['subject'], 'body' => $content['body'], 'decline_fingerprint' => $content['fingerprint']]);
    }

    private function content(TeamSelectionInvitation $invitation): ?array
    {
        if ($invitation->status !== TeamSelectionInvitation::DECLINED || $invitation->decline_method !== 'authenticated_invitation' || ! $invitation->declined_at) return null;
        $invitation->loadMissing(['selectionImport.event', 'region', 'team.category', 'player']);
        if (! $invitation->player || ! $invitation->selectionImport?->event
            || (int) $invitation->selectionImport->event_id !== (int) $invitation->event_id
            || (int) $invitation->selectionImport->region_id !== (int) $invitation->region_id
            || (int) $invitation->team?->category?->event_id !== (int) $invitation->event_id
            || (int) $invitation->team?->region_id !== (int) $invitation->region_id) return null;
        $region = EventRegion::where('event_id', $invitation->event_id)->where('region_id', $invitation->region_id)->first();
        $manager = $region ? app(RegionManagerAccessService::class)->manager($region) : null;
        $email = mb_strtolower(trim((string) $manager?->email));
        if (! $manager || ! filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $body = '<p>'.e($invitation->player->full_name).' has declined their invitation for '.e($invitation->selectionImport->event->name).'.</p>'
            .'<p>Region: '.e($invitation->region?->region_name).'<br>Team: '.e($invitation->team->name).'<br>Declined: '.e($invitation->declined_at->format('d M Y H:i')).'</p>'
            .(filled($invitation->decline_reason) ? '<p>Reason: '.nl2br(e($invitation->decline_reason)).'</p>' : '')
            .'<p>Please review the team selection and any open place.</p>';
        $content = ['email' => $email, 'name' => $manager->name, 'subject' => $invitation->selectionImport->event->name.' — Team invitation declined', 'body' => $body];
        $content['fingerprint'] = hash('sha256', json_encode([$invitation->id, $invitation->event_id, $invitation->region_id, $invitation->declined_by_user_id, $content], JSON_THROW_ON_ERROR));

        return $content;
    }

    public function authorizedMailable(BulkEmailLog $log): ?BulkEventMail
    {
        $invitation = $log->related_type === TeamSelectionInvitation::class ? TeamSelectionInvitation::find($log->related_id) : null;
        if ($log->mail_type !== 'team_selection_decline_notification' || ! $invitation
            || (int) ($log->payload['event_id'] ?? 0) !== (int) $invitation->event_id
            || (int) ($log->payload['region_id'] ?? 0) !== (int) $invitation->region_id
            || ! ($content = $this->content($invitation)) || $log->recipient_email !== $content['email']
            || ! hash_equals($content['fingerprint'], $log->payload['decline_fingerprint'] ?? '')
            || ($log->payload['subject'] ?? null) !== $content['subject'] || ($log->payload['body'] ?? null) !== $content['body']) return null;

        return (new BulkEventMail($content['subject'], $content['body'], config('mail.from.name', 'Cape Tennis'), config('mail.from.address')))
            ->with('team_decline_notification_log_id', $log->id);
    }
}
