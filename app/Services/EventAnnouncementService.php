<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\Player;
use App\Services\TeamSelection\TeamSelectionContactService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventAnnouncementService
{
    public function __construct(private readonly TeamSelectionContactService $contacts)
    {
    }

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

    /**
     * Resolve the exact event announcement audience from current nominations and
     * active, paid registrations. Client-submitted addresses are never used.
     *
     * @return Collection<int, array{email:string,name:string}>
     */
    public function recipients(Event $event): Collection
    {
        $event->loadMissing('eventTypeModel');

        $nominatedPlayers = $event->nominations()
            ->with('player.user', 'player.users')
            ->get()
            ->pluck('player');

        $registeredPlayers = $event->registrations()
            ->activeAndPaid()
            ->with('players.user', 'players.users')
            ->get()
            ->flatMap->players;

        $teamPlayers = collect();
        if ($event->isTeam()) {
            $event->loadMissing('regions.teams.players.user', 'regions.teams.players.users');
            $teamPlayers = $event->regions
                ->flatMap->teams
                ->flatMap->players;
        }

        return $nominatedPlayers
            ->merge($registeredPlayers)
            ->merge($teamPlayers)
            ->filter(fn ($player): bool => $player instanceof Player)
            ->unique('id')
            ->map(function (Player $player): ?array {
                $email = $this->contacts->primaryEmail($player);
                if (! $email) {
                    return null;
                }

                $name = trim($player->name.' '.$player->surname);

                return ['email' => $email, 'name' => $name];
            })
            ->filter()
            ->unique('email')
            ->sortBy('email')
            ->values();
    }

    /** @return Collection<int, string> */
    public function recipientEmails(Event $event): Collection
    {
        return $this->recipients($event)->pluck('email')->values();
    }

    public function recipientHash(Event $event): string
    {
        return hash('sha256', $this->recipientEmails($event)->toJson());
    }

    /** @return array{total: int, queued: int, skipped: int, invalid: int, duplicate: int} */
    /**
     * Queue the exact server-derived recipient snapshot already validated by
     * the caller. Do not re-resolve the audience after confirmation.
     *
     * @param  Collection<int, array{email:string,name:string}>  $recipients
     * @return array{total: int, queued: int, skipped: int, invalid: int, duplicate: int}
     */
    public function dispatch(Announcement $announcement, Collection $recipients): array
    {
        $event = $announcement->event;
        if (! $event) {
            return ['total' => 0, 'queued' => 0, 'skipped' => 0, 'invalid' => 0, 'duplicate' => 0];
        }

        return app(BulkMailDispatcher::class)->dispatch(
            mailType: 'event_announcement',
            related: $announcement,
            recipients: $recipients,
            payload: [
                'event_name' => $event->name,
                'title' => $announcement->title,
                'message' => $announcement->message,
            ],
            allowDuplicates: false,
        );
    }
}
