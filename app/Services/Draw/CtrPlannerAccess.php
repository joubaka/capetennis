<?php

namespace App\Services\Draw;

use App\Models\{Event, User};

final class CtrPlannerAccess
{
    public function manages(?User $actor, Event $event): bool
    {
        return $actor !== null && (int) $event->eventType === \App\Models\EventType::INDIVIDUAL
            && ($actor->hasRole('super-user') || $actor->is_event_admin($event->id)
                || (!$actor->is_event_score_keeper($event->id) && $actor->is_convenor($event->id)));
    }

    /** This ability permits only the new, private programme created by this planner. */
    public function authorize(User $actor, Event $event, bool $requireCertified = true): void
    {
        abort_unless($this->manages($actor, $event), 403);
        abort_unless(app(CtrCertificationStatus::class)->plannerAvailable(), 503,
            'CTR planner setup is pending. Ask an administrator to apply the reviewed CTR planner migration.');
        abort_if($requireCertified && !$event->ctrCertification()->where('certified', true)->exists(), 403,
            'This planner is available for CTR Certified events.');
    }
}
