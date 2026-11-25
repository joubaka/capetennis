<?php

namespace App\Support;

use App\Models\Event;

/** Workflow capabilities are independent of the competition's base family. */
final class EventCapabilities
{
    public static function nominations(Event $event): bool
    {
        return $event->isInterprovincialTrials() || $event->isTeam()
            || EventTypePresentation::template($event) === 'cavaliers_trials';
    }

    public static function invitations(Event $event): bool
    {
        return self::nominations($event) || $event->isMasters() || $event->isCamp();
    }

    public static function clothing(Event $event): bool
    {
        return ! $event->isInterprovincialTrials()
            && ($event->isTeam() || $event->requiresEventScopedRegionTeams());
    }

    public static function teamPayments(Event $event): bool
    {
        return $event->isTeam() || $event->isInterprovincialTrials()
            || $event->requiresEventScopedRegionTeams();
    }
}
