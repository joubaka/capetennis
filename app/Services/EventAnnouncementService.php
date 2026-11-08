<?php

namespace App\Services;

use App\Jobs\QueueEventAnnouncementEmails;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\EventCommunicationBatch;
use App\Models\Player;
use App\Models\User;
use App\Services\TeamSelection\TeamSelectionContactService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventAnnouncementService
{
    public const EMAIL_DELAY_MINUTES = 3;

    /** Queue staff copies separately so participant counts remain unchanged. */
    public function dispatchCopies(\App\Models\Announcement|\App\Models\TeamSelectionRegionAnnouncement $announcement, User $actor, ?Collection $scheduledRecipients = null): array
    {
        $event = $announcement->event;
        $recipients = collect();
        try {
            $users = $event->admins()->get()->push($actor)->merge(
                \App\Models\User::whereHas('roles', fn ($query) => $query->where('name', 'super-user')->where('guard_name', 'web'))->get()
            );
            $recipients = $users->map(fn ($user) => ['email' => strtolower(trim((string) $user->email)), 'name' => $user->name])->unique('email')->values();
            // A participant email already contains the same announcement. Do not send it twice.
            $alreadyQueued = \App\Models\BulkEmailLog::where('mail_type', 'event_announcement')
                ->where('related_type', get_class($announcement))->where('related_id', $announcement->id)
                ->whereIn('status', ['queued', 'sending', 'sent', 'acceptance_unknown'])->pluck('recipient_email');
            $alreadyQueued = $alreadyQueued->merge($scheduledRecipients?->pluck('email') ?? []);
            $recipients = $recipients->reject(fn ($recipient) => $alreadyQueued->contains($recipient['email']))->values();

            if ($recipients->isEmpty()) {
                return ['total' => 0, 'queued' => 0, 'skipped' => 0, 'invalid' => 0, 'duplicate' => 0, 'failed' => 0];
            }
            $regionContext = $announcement instanceof \App\Models\TeamSelectionRegionAnnouncement
                ? ['region_id' => $announcement->region_id, 'event_region_id' => $announcement->event_region_id]
                : [];
            $subject = ($announcement->title ?: 'Announcement').' – '.$event->name;
            $html = (new \App\Mail\AnnouncementMail([
                'event' => $event->name, 'title' => $announcement->title,
                'message' => '<h2>'.e($announcement->title ?: 'Announcement').'</h2>'.$announcement->message,
            ]))->render();
            $batch = \App\Models\EventCommunicationBatch::create([
                'event_id' => $event->id, 'created_by' => $actor->id, 'token' => (string) \Illuminate\Support\Str::uuid(),
                'subject' => $subject, 'body' => $announcement->message,
                'options' => ['source' => 'announcement_staff_copy', 'announcement_id' => $announcement->id, 'announcement_type' => get_class($announcement), ...$regionContext],
                'recipients' => $recipients->map(fn ($recipient) => [...$recipient, 'kind' => 'admin_copy', 'subject' => $subject, 'html' => $html])->all(),
                'issues' => [], 'fingerprint' => hash('sha256', $recipients->toJson()),
                'status' => 'approved', 'approved_at' => now(),
            ]);
            return app(BulkMailDispatcher::class)->dispatch('event_announcement', $announcement, $recipients, [
                ...$regionContext,
                'event_id' => $event->id, 'created_by' => $actor->id, 'recipient_kind' => 'admin_copy',
                'event_communication_batch_id' => $batch->id, 'subject' => $subject, 'body' => $html,
                'manual_retry_only' => true, 'event_name' => $event->name,
                'title' => $announcement->title,
                'message' => '<h2>'.e($announcement->title ?: 'Announcement').'</h2>'.$announcement->message,
            ]);
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning('Announcement staff copies could not be queued.', ['event_id' => $event->id, 'announcement_id' => $announcement->id]);
            return ['total' => $recipients->count(), 'queued' => 0, 'skipped' => 0, 'invalid' => 0, 'duplicate' => 0, 'failed' => max(1, $recipients->count())];
        }
    }

    public function copyFeedback(array $stats): string
    {
        $message = ' Staff copies: '.($stats['queued'] ?? 0).' queued.';
        if (($stats['failed'] ?? 0) || ($stats['skipped'] ?? 0)) {
            $message .= ' '.($stats['skipped'] ?? 0).' skipped; '.($stats['failed'] ?? 0).' failed. Review the event Email Log.';
        }
        return $message;
    }

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
    private function audiencePlayers(Event $event): Collection
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
            $teamIds = \App\Models\TeamSelectionInvitation::where('event_id', $event->id)->select('team_id');
            $teamPlayers = \App\Models\Team::withoutGlobalScopes()->where(function ($q) use ($event, $teamIds) {
                $q->whereHas('category', fn ($category) => $category->where('event_id', $event->id))->orWhereIn('id', $teamIds);
            })->with('players.user', 'players.users')->get()->flatMap->players;
        }

        return $nominatedPlayers
            ->merge($registeredPlayers)
            ->merge($teamPlayers)
            ->filter(fn ($player): bool => $player instanceof Player)
            ->unique('id')->values();
    }

    /** Resolve contacts and exclusions together before recipient confirmation. */
    public function audienceSnapshot(Event $event): array
    {
        if ($event->isTeam()) {
            $rows = app(EventCommunicationService::class)->rosterEntries($event);
            return [
                'recipients' => $rows->flatMap(fn ($row) => collect($row['emails'])->map(fn ($email) => ['email' => $email, 'name' => $row['name'], 'player_key' => $row['key']]))->groupBy('email')->map(fn ($matches) => [...$matches->first(), 'player_keys' => $matches->pluck('player_key')->unique()->values()->all()])->sortBy('email')->values(),
                'excluded' => $rows->filter(fn ($row) => empty($row['emails']))->unique('key')->map(fn ($row) => ['email' => '', 'name' => $row['name'], 'player_key' => $row['key']])->values(),
            ];
        }
        $players = $this->audiencePlayers($event)->map(fn (Player $player) => [
            'emails' => $this->contacts->emails($player), 'name' => trim($player->name.' '.$player->surname),
            'player_key' => 'player:'.$player->id,
        ]);
        return [
            'recipients' => $players->flatMap(fn ($row) => $row['emails']->map(fn ($email) => [
                'email' => $email, 'name' => $row['name'], 'player_key' => $row['player_key'],
            ]))->groupBy('email')->map(fn ($matches) => [
                ...$matches->first(), 'player_keys' => $matches->pluck('player_key')->sort()->values()->all(),
            ])->sortBy('email')->values(),
            'excluded' => $players->filter(fn ($row) => $row['emails']->isEmpty())->map(fn ($row) => ['name' => $row['name'], 'email' => ''])->values(),
        ];
    }

    public function recipients(Event $event): Collection
    {
        return $this->audienceSnapshot($event)['recipients'];
    }

    /** @return Collection<int, string> */
    public function recipientEmails(Event $event): Collection
    {
        return $this->recipients($event)->pluck('email')->values();
    }

    public function recipientHash(Event $event): string
    {
        return $this->hashRecipients($this->recipients($event));
    }

    public function hashRecipients(Collection $recipients): string
    {
        return hash('sha256', $recipients->map(fn (array $recipient) => [
            $recipient['email'], collect($recipient['player_keys'] ?? [])->sort()->values()->all(),
        ])->toJson());
    }

    public function schedule(Announcement $announcement, Collection $recipients, User $actor, Collection $excluded): EventCommunicationBatch
    {
        $sendAt = now()->addMinutes(self::EMAIL_DELAY_MINUTES);

        return DB::transaction(function () use ($announcement, $recipients, $actor, $excluded, $sendAt): EventCommunicationBatch {
            $batch = $this->createBatch($announcement, $recipients, $actor, $excluded, [
                'scheduled_for' => $sendAt->toIso8601String(),
                'excluded_recipients' => $excluded->all(),
            ]);
            // Both records use the application database and commit together.
            Bus::dispatch((new QueueEventAnnouncementEmails($batch->id))
                ->onConnection('database')->delay($sendAt)->beforeCommit());

            return $batch;
        });
    }

    public function dispatchScheduled(int $batchId): void
    {
        DB::transaction(function () use ($batchId): void {
            $batch = EventCommunicationBatch::where('options->source', 'announcement')
                ->whereIn('status', ['scheduled', 'failed'])->lockForUpdate()->find($batchId);
            if (! $batch || empty($batch->options['scheduled_for']) || now()->lt(Carbon::parse($batch->options['scheduled_for']))) {
                return;
            }

            $announcement = Announcement::where('event_id', $batch->event_id)->lockForUpdate()->find($batch->options['announcement_id']);
            $event = $announcement?->event;
            $actor = User::find($batch->created_by);
            $recipients = collect($batch->recipients);
            $reason = match (true) {
                ! $event => 'The announcement was hidden or removed before participant emails were queued.',
                ! $actor || $actor->cannot('event.manage', $event) => 'The sender no longer has permission to manage this event.',
                ! $this->scheduledRecipientsStillMatch($event, $recipients) => 'The confirmed recipient list changed before sending. Review the current recipients in Communications.',
                default => null,
            };
            if ($reason !== null) {
                $batch->update(['status' => 'cancelled', 'options' => [...$batch->options, 'announcement_mail_issue' => $reason]]);
                return;
            }

            $this->dispatchBatch($announcement, $batch, $recipients, collect($batch->options['excluded_recipients']));
            $batch->update(['status' => 'approved', 'options' => collect($batch->options)->except('announcement_mail_issue')->all()]);
        });
    }

    private function scheduledRecipientsStillMatch(Event $event, Collection $recipients): bool
    {
        $current = $this->recipients($event)->keyBy('email');

        return $recipients->every(function (array $reviewed) use ($current): bool {
            $matching = $current->get($reviewed['email']);

            return $matching !== null
                && collect($reviewed['player_keys'] ?? [])->sort()->values()->all()
                    === collect($matching['player_keys'] ?? [])->sort()->values()->all();
        });
    }

    /** @return array{total: int, queued: int, skipped: int, invalid: int, duplicate: int} */
    /**
     * Queue the exact server-derived recipient snapshot already validated by
     * the caller. Do not re-resolve the audience after confirmation.
     *
     * @param  Collection<int, array{email:string,name:string}>  $recipients
     * @return array{total: int, queued: int, skipped: int, invalid: int, duplicate: int}
     */
    public function dispatch(Announcement $announcement, Collection $recipients, ?\App\Models\User $actor = null, ?Collection $excluded = null): array
    {
        $event = $announcement->event;
        if (! $event) {
            return ['total' => 0, 'queued' => 0, 'skipped' => 0, 'invalid' => 0, 'duplicate' => 0];
        }

        $excluded ??= collect();
        $batch = $this->createBatch($announcement, $recipients, $actor, $excluded);

        return $this->dispatchBatch($announcement, $batch, $recipients, $excluded);
    }

    private function createBatch(Announcement $announcement, Collection $recipients, ?User $actor, Collection $excluded, array $options = []): EventCommunicationBatch
    {
        $event = $announcement->event;
        $subject = $announcement->title.' – '.$event->name;
        $scheduled = isset($options['scheduled_for']);
        $html = $scheduled ? null : (new AnnouncementMail([
            'event' => $event->name, 'title' => $announcement->title, 'message' => $announcement->message,
        ]))->render();

        return EventCommunicationBatch::create([
            'event_id' => $event->id, 'created_by' => $actor?->id, 'token' => (string) Str::uuid(),
            'subject' => $subject, 'body' => $announcement->message,
            'options' => ['source' => 'announcement', 'announcement_id' => $announcement->id,
                'title' => $announcement->title, 'event_name' => $event->name, ...$options],
            'recipients' => $recipients->map(fn ($recipient) => [...$recipient, 'kind' => 'players', 'subject' => $subject,
                ...($html === null ? [] : ['html' => $html])])->all(),
            'issues' => $excluded->pluck('name')->map(fn ($name) => $name.' — no valid email address')->all(),
            'fingerprint' => hash('sha256', $recipients->toJson()), 'status' => $scheduled ? 'scheduled' : 'approved',
            'approved_at' => now(),
        ]);
    }

    private function dispatchBatch(Announcement $announcement, EventCommunicationBatch $batch, Collection $recipients, Collection $excluded): array
    {
        $eventName = $batch->options['event_name'];
        $title = $batch->options['title'];
        $html = $batch->recipients[0]['html'] ?? self::renderBatch($batch);
        $stats = ['total' => 0, 'queued' => 0, 'skipped' => 0, 'invalid' => 0, 'duplicate' => 0, 'failed' => 0];
        foreach ($recipients->concat($excluded) as $recipient) {
            $result = app(BulkMailDispatcher::class)->dispatch('event_announcement', $announcement, [$recipient], [
                'event_id' => $batch->event_id, 'created_by' => $batch->created_by,
                'manual_retry_only' => true, 'event_communication_batch_id' => $batch->id,
                'recipient_kind' => 'players', 'player_keys' => $recipient['player_keys'] ?? (isset($recipient['player_key']) ? [$recipient['player_key']] : []),
                'event_name' => $eventName, 'title' => $title,
                'message' => $batch->body, 'subject' => $batch->subject, 'body' => $html,
            ], atomicDatabaseQueue: isset($batch->options['scheduled_for']));
            foreach ($stats as $key => $count) $stats[$key] += $result[$key] ?? 0;
        }
        return $stats;
    }

    public static function renderBatch(EventCommunicationBatch $batch): string
    {
        return (new AnnouncementMail([
            'event' => $batch->options['event_name'],
            'title' => $batch->options['title'],
            'message' => $batch->body,
        ]))->render();
    }
}
