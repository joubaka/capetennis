<?php

namespace App\Services\TeamSelection;

use App\Mail\TeamSelectionInvitationMail;
use App\Models\BulkEmailLog;
use App\Models\TeamRegion;
use App\Models\TeamSelectionInvitation;
use App\Services\InvitationMailSecurity;

class TeamSelectionClothingMailPolicy
{
    public function available(int $regionId): bool
    {
        // Never reuse a campaign's loaded region/catalogue after ordering changes.
        return TeamRegion::with('clothingItems.sizes')->find($regionId)?->clothingOrdersAvailable() ?? false;
    }

    public function currentCampaign(int $regionId, array $campaign): array
    {
        if (! $this->available($regionId)) {
            $campaign['include_clothing'] = false;
            $campaign['clothing_items'] = [];
        }

        return $campaign;
    }

    public function includesClothing(array $payload): bool
    {
        return ! (bool) ($payload['campaign']['notification_only'] ?? false)
            && ! in_array($payload['kind'] ?? 'invitation', ['replacement', 'custom_status_update'], true)
            && (bool) ($payload['campaign']['include_clothing'] ?? false);
    }

    /** Derive a new reviewed retry; the original signed snapshot stays untouched. */
    public function retryPayload(BulkEmailLog $log): array
    {
        $payload = $log->payload;
        if (! $this->includesClothing($payload) || $this->available((int) ($payload['region_id'] ?? 0))) {
            return $payload;
        }

        $invitation = TeamSelectionInvitation::with(['selectionImport.event', 'region', 'team', 'player'])
            ->where('event_id', $payload['event_id'])->where('region_id', $payload['region_id'])->findOrFail($log->related_id);
        $payload['campaign']['include_clothing'] = false;
        $payload['campaign']['clothing_items'] = [];
        $mail = new TeamSelectionInvitationMail($invitation, $payload['kind'] ?? 'invitation', $payload['campaign']);
        $payload['rendered_html'] = $mail->render();
        $payload['rendered_subject'] = $mail->envelope()->subject;
        $payload['retry_of_log_id'] = $log->id;
        $payload['payload_integrity'] = app(InvitationMailSecurity::class)->payloadIntegrity($payload);

        return $payload;
    }
}
