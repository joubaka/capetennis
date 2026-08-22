<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Carbon;

/**
 * Projects the existing event fields into one stable operational lifecycle.
 * This is intentionally read-only; mutations remain in the existing
 * event/draw/publication services and controllers.
 */
class EventLifecycleService
{
    public function snapshot(Event $event, ?Carbon $now = null): array
    {
        $now ??= now();
        $state = $this->state($event, $now);

        return [
            'state' => $state,
            'label' => str_replace('_', ' ', ucfirst($state)),
            'published' => (bool) $event->published,
            'entries_open' => $state === 'published_open',
            'withdrawals_open' => $event->canWithdraw(),
            'results_published' => (bool) $event->results_published,
            'start_date' => $event->start_date?->toDateString(),
            'end_date' => $event->end_date?->toDateString(),
        ];
    }

    public function state(Event $event, Carbon $now): string
    {
        $status = strtolower((string) ($event->status ?? ''));
        if (in_array($status, ['archived', 'archive'], true)) {
            return 'archived';
        }
        if (in_array($status, ['completed', 'complete'], true)) {
            return 'completed';
        }
        if ($event->results_published) {
            return 'results_published';
        }
        if ($event->start_date && $event->end_date
            && $now->toDateString() >= $event->start_date->toDateString()
            && $now->toDateString() <= $event->end_date->toDateString()) {
            return 'live';
        }

        $registrationClosesAt = $event->registrationClosesAt();
        if ($event->published && $event->signUp
            && (! $registrationClosesAt || $now->lt($registrationClosesAt))) {
            return 'published_open';
        }
        if ($event->published) {
            return 'entries_closed';
        }

        return 'draft';
    }
}
