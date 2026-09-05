<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventAnnouncementService
{
    /**
     * Build the editable announcement shown after venue allocation.
     *
     * @return array{title: string, message: string, assignments: Collection, recipient_count: int}
     */
    public function venueAssignmentDraft(Event $event): array
    {
        $event->loadMissing('draws.venues');

        $allocationRows = DB::table('draw_venue_court_allocations')
            ->whereIn('draw_id', $event->draws->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => $row->draw_id.'|'.$row->venue_id);

        $activeCourts = DB::table('event_venue_courts')
            ->where('event_id', $event->id)
            ->where('active', true)
            ->orderBy('id')
            ->get()
            ->groupBy('venue_id');

        $assignments = $event->draws->map(function ($draw) use ($allocationRows, $activeCourts) {
            $venues = $draw->venues->map(function ($venue) use ($draw, $allocationRows, $activeCourts) {
                $labels = ($allocationRows[$draw->id.'|'.$venue->id] ?? collect())
                    ->pluck('court_label')
                    ->map(fn ($label) => (string) $label);

                // Older allocations represent "all courts" with no explicit rows.
                if ($labels->isEmpty()) {
                    $labels = ($activeCourts[$venue->id] ?? collect())
                        ->pluck('label')
                        ->map(fn ($label) => (string) $label);
                }

                if ($labels->isEmpty()) {
                    $labels = collect(range(1, max(1, (int) ($venue->pivot->num_courts ?? 1))))
                        ->map(fn ($label) => (string) $label);
                }

                return [
                    'name' => $venue->name,
                    'courts' => $labels->unique()->values(),
                ];
            })->values();

            return [
                'name' => $draw->drawName,
                'venues' => $venues,
            ];
        })->filter(fn ($assignment) => $assignment['venues']->isNotEmpty())->values();

        return [
            'title' => 'Court venues assigned',
            'message' => view('backend.schedule.partials.venue-announcement-draft', [
                'event' => $event,
                'assignments' => $assignments,
            ])->render(),
            'assignments' => $assignments,
            'recipient_count' => $this->recipientEmails($event)->count(),
        ];
    }

    /** @return Collection<int, string> */
    public function recipientEmails(Event $event): Collection
    {
        $event->loadMissing('eventTypeModel');

        if ($event->isTeam()) {
            $event->loadMissing('regions.teams.players');
            $emails = $event->regions
                ->flatMap->teams
                ->flatMap->players
                ->pluck('email');
        } else {
            $emails = $event->registrations()
                ->activeAndPaid()
                ->with('players:id,email')
                ->get()
                ->flatMap->players
                ->pluck('email');
        }

        return $emails
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values();
    }

    /** @return array{total: int, queued: int, skipped: int, invalid: int, duplicate: int} */
    public function dispatch(Announcement $announcement): array
    {
        $event = $announcement->event;
        if (! $event) {
            return ['total' => 0, 'queued' => 0, 'skipped' => 0, 'invalid' => 0, 'duplicate' => 0];
        }

        return app(BulkMailDispatcher::class)->dispatch(
            mailType: 'event_announcement',
            related: $announcement,
            recipients: $this->recipientEmails($event),
            payload: [
                'event_name' => $event->name,
                'title' => $announcement->title,
                'message' => $announcement->message,
            ],
            allowDuplicates: false,
        );
    }
}
