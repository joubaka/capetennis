<?php

namespace App\Support;

use App\Models\Event;

/** Public presentation only; registration and authorization remain workflow-owned. */
final class EventTypePresentation
{
    public static function template(Event $event): string
    {
        return $event->isInterprovincialTrials() ? 'interprovincial-trials' : self::view($event);
    }

    public static function view(Event $event): string
    {
        // Preserve family/code precedence over legacy ids. In particular, an
        // Interpro legacy id configured as a team still uses the team layout.
        if ($event->isMasters()) {
            return 'masters';
        }

        if ($event->isTeam()) {
            return 'team';
        }

        if ($event->isCamp()) {
            return 'default';
        }

        return match ((int) $event->eventType) {
            14 => 'masters',
            5 => 'cavaliers_trials',
            9 => 'parentChildDoubles',
            13 => 'interpro',
            6, 1 => 'individual',
            3, 7 => 'team',
            default => 'default',
        };
    }

    public static function venueFixtureKind(Event $event): ?string
    {
        // Trials owns its programme/draw workflow. Retain only the existing
        // legacy shortcut preparation rather than introducing family-based data.
        if ($event->isInterprovincialTrials()) {
            return match ((int) $event->eventType) {
                3 => 'team',
                13 => 'individual',
                default => null,
            };
        }

        return match (self::view($event)) {
            'team' => 'team',
            'interpro' => 'individual',
            default => null,
        };
    }
}
