<?php

namespace App\Services\Masters;

use App\Models\BulkEmailLog;
use App\Models\MastersInvitation;

class MastersInvitationMailEligibility
{
    public function reason(BulkEmailLog $log, ?MastersInvitation $invitation, int $eventId): ?string
    {
        if ($log->mail_type !== 'masters_invitation'
            || $log->related_type !== MastersInvitation::class
            || !$invitation
            || (int) $log->related_id !== (int) $invitation->id
            || (int) ($log->payload['invitation_id'] ?? 0) !== (int) $invitation->id
            || !$invitation->batch?->event
            || (int) $invitation->batch->event_id !== $eventId) {
            return 'Masters email does not match its invitation and event.';
        }

        $kind = $log->payload['kind'] ?? 'invitation';
        if (!in_array($kind, ['invitation', 'replacement', 'confirmed', 'declined', 'withdrawn'], true)) {
            return 'Unknown Masters email kind.';
        }

        if (in_array($kind, ['invitation', 'replacement'], true)) {
            if ($invitation->status !== MastersInvitation::INVITED) {
                return 'The player is no longer awaiting an invitation response.';
            }

            $deadline = $kind === 'replacement' || $invitation->replacement_sent_at
                ? $invitation->batch->replacement_payment_deadline
                : $invitation->batch->response_deadline;
            if (!$deadline || $deadline->isPast()) {
                return 'The invitation response deadline is missing or has passed.';
            }
        }

        return null;
    }
}
