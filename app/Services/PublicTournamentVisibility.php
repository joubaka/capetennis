<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\Event;
use App\Models\User;

final class PublicTournamentVisibility
{
    public function eventIsVisible(Event $event, ?User $user = null): bool
    {
        return Event::query()
            ->visibleTo($user)
            ->whereKey($event->getKey())
            ->exists();
    }

    public function ensureEventIsVisible(Event $event, ?User $user = null): void
    {
        abort_unless($this->eventIsVisible($event, $user), 404);
    }

    public function drawIsVisible(Draw $draw, ?User $user = null): bool
    {
        if (! $this->eventIsVisible($draw->event, $user)) {
            return false;
        }

        return (bool) $draw->published || ($user?->can('view', $draw) ?? false);
    }

    public function ensureDrawIsVisible(Draw $draw, ?User $user = null): void
    {
        abort_unless($this->drawIsVisible($draw, $user), 403, 'This draw has not been published yet.');
    }

    public function publishedDrawsFor(Event $event, bool $scheduleRequired = false)
    {
        return $event->draws()
            ->where('published', true)
            ->when($scheduleRequired, fn ($query) => $query->where('oop_published', true));
    }
}
