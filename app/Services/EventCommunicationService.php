<?php

namespace App\Services;

use App\Models\{BulkEmailLog, Event, EventCommunicationBatch, EventNomination, EventRegion, NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamSelectionImport, TeamSelectionInvitation, User};
use App\Services\TeamSelection\{RegionManagerAccessService, TeamSelectionContactService};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Communications are independent of registration, payment and publication gates. */
class EventCommunicationService
{
    public function __construct(private RegionManagerAccessService $access, private TeamSelectionContactService $contacts, private BulkMailDispatcher $mailer) {}

    public function regions(Event $event, User $actor): Collection
    {
        if ($event->isInterprovincialTrials()) {
            app(\App\Services\InterprovincialTrials\TrialProgrammeService::class)->authorize($event, $actor);

            return EventRegion::with(['events', 'region'])->where('event_id', $event->id)->get();
        }
        abort_unless($event->isTeam(), 404);
        $regions = EventRegion::with(['events', 'region'])->where('event_id', $event->id)->get();
        $allowed = $regions->filter(fn ($region) => $this->access->canManage($actor, $region));
        abort_unless($this->access->isEventManager($actor, $event) || $allowed->isNotEmpty(), 403);

        return $allowed->values();
    }

    public function managesWholeEvent(Event $event, User $actor): bool
    {
        return $event->isInterprovincialTrials()
            ? app(\App\Services\InterprovincialTrials\TrialProgrammeService::class)->canManage($event, $actor)
            : $this->access->isEventManager($actor, $event);
    }

    public function entries(Event $event, User $actor): Collection
    {
        $regions = $this->regions($event, $actor);
        $rows = collect();
        $imports = TeamSelectionImport::where('event_id', $event->id)->whereIn('status', ['draft', 'sent'])->orderBy('id')->get()->groupBy('region_id')->map(fn ($group) => $group->last()->id);
        $invitations = TeamSelectionInvitation::with(['player.user', 'player.users', 'team.category.category'])->where('event_id', $event->id)->whereIn('import_id', $imports)->get()->keyBy(fn ($i) => $i->team_id.':'.$i->player_id);
        $teams = Team::withoutGlobalScopes()->whereHas('category', fn ($q) => $q->where('event_id', $event->id))->whereIn('region_id', $regions->pluck('region_id'))->with(['category.category', 'team_players.player.user', 'team_players.player.users', 'team_players_no_profile.profile.user', 'team_players_no_profile.profile.users'])->get();
        foreach ($teams as $team) {
            foreach ($team->team_players as $slot) {
                if (! $slot->player) continue;
                $invitation = $invitations->get($team->id.':'.$slot->player_id);
                $status = in_array($invitation?->status, ['declined', 'withdrawn'], true) ? $invitation->status : ((int) $slot->pay_status === 1 ? 'paid_confirmed' : ($invitation?->status ?? 'not_registered'));
                $rows->put('player:'.$slot->player_id.':'.$team->id, $this->row($team, 'player:'.$slot->player_id, $slot->player->full_name, $this->contacts->emails($slot->player)->all(), $status));
            }
            foreach ($team->team_players_no_profile as $slot) {
                $emails = $slot->profile ? $this->contacts->emails($slot->profile) : collect();
                if (filter_var($slot->email, FILTER_VALIDATE_EMAIL)) $emails->push(mb_strtolower(trim($slot->email)));
                $key = $slot->player_profile ? 'player:'.$slot->player_profile : 'imported:'.$slot->id;
                if ($rows->has($key.':'.$team->id)) continue;
                $rows->put($key.':'.$team->id, $this->row($team, $key, trim($slot->name.' '.$slot->surname), $emails->unique()->all(), (int) $slot->pay_status === 1 ? 'paid_confirmed' : 'not_registered'));
            }
        }
        foreach ($invitations as $invitation) {
            if (! $invitation->team || ! $teams->contains('id', $invitation->team_id) || ! $invitation->player) continue;
            $key = 'player:'.$invitation->player_id.':'.$invitation->team_id;
            if (! $rows->has($key)) $rows->put($key, $this->row($invitation->team, 'player:'.$invitation->player_id, $invitation->player->full_name, $this->contacts->emails($invitation->player)->all(), $invitation->status));
        }
        // Unpublished nominations are still eligible communication recipients.
        $fullEvent = $this->managesWholeEvent($event, $actor);
        $teamCategories = $teams->pluck('category_event_id')->unique();
        $nominations = EventNomination::with(['player.user', 'player.users', 'categoryEvent.category'])->where('event_id', $event->id)->when(! $fullEvent, fn ($q) => $q->whereIn('category_event_id', $teamCategories))->get();
        $paidPlayerIds = $event->registrations()->activeAndPaid()->with('registration.players')->get()->flatMap(fn ($entry) => $entry->registration?->players?->pluck('id') ?? collect())->unique();
        foreach ($nominations as $nomination) {
            $key = $nomination->player_id ? 'player:'.$nomination->player_id : 'nominee:'.$nomination->id;
            $existing = $rows->filter(fn ($row) => $row['key'] === $key);
            if ($existing->isNotEmpty()) {
                foreach ($existing as $id => $row) {
                    $emails = collect($row['emails']);
                    if (filter_var($nomination->nominee_email, FILTER_VALIDATE_EMAIL)) $emails->push(mb_strtolower(trim($nomination->nominee_email)));
                    $rows->put($id, [...$row, 'nominated' => true, 'emails' => $emails->unique()->values()->all()]);
                }
                continue;
            }
            // A category can span multiple regions. Unassigned nominees belong only in the event-manager audience.
            if (! $fullEvent) continue;
            $emails = $nomination->player ? $this->contacts->emails($nomination->player) : collect();
            if (filter_var($nomination->nominee_email, FILTER_VALIDATE_EMAIL)) $emails->push(mb_strtolower(trim($nomination->nominee_email)));
            $rows->put($key, ['key' => $key, 'name' => $nomination->display_name, 'emails' => $emails->unique()->values()->all(), 'status' => $paidPlayerIds->contains($nomination->player_id) ? 'paid_confirmed' : 'not_registered', 'team_id' => null, 'region_id' => null, 'category' => $nomination->categoryEvent?->category?->name ?? 'Nominee', 'team' => '', 'nominated' => true]);
        }

        return $rows->sortBy([['team', 'asc'], ['name', 'asc']])->values();
    }

    public function individualOptions(Event $event, User $actor, string $search): Collection
    {
        $regions = $this->regions($event, $actor);
        if ($search === '') return collect();
        $teamIds = Team::withoutGlobalScopes()->whereHas('category', fn ($q) => $q->where('event_id', $event->id))->whereIn('region_id', $regions->pluck('region_id'))->select('id');
        $playerIds = TeamPlayer::withoutGlobalScopes()->whereIn('team_id', clone $teamIds)->select('player_id');
        $importedIds = NoProfileTeamPlayer::whereIn('team_id', clone $teamIds)->whereNotNull('player_profile')->select('player_profile');
        $invitationIds = TeamSelectionInvitation::where('event_id', $event->id)->whereIn('team_id', clone $teamIds)->select('player_id');
        $fullEvent = $this->managesWholeEvent($event, $actor);
        $term = '%'.addcslashes($search, '%_\\').'%';
        $players = Player::where(function ($q) use ($playerIds, $importedIds, $invitationIds, $fullEvent, $event) {
            $q->whereIn('id', $playerIds)->orWhereIn('id', $importedIds)->orWhereIn('id', $invitationIds);
            if ($fullEvent) $q->orWhereIn('id', EventNomination::where('event_id', $event->id)->select('player_id'));
        })->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('surname', 'like', $term))->orderBy('surname')->limit(100)->get()->map(fn ($p) => ['key' => 'player:'.$p->id, 'name' => $p->full_name]);
        $unlinked = NoProfileTeamPlayer::whereIn('team_id', $teamIds)->whereNull('player_profile')->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('surname', 'like', $term))->limit(100)->get()->map(fn ($p) => ['key' => 'imported:'.$p->id, 'name' => trim($p->name.' '.$p->surname)]);
        $nominees = $fullEvent ? EventNomination::where('event_id', $event->id)->whereNull('player_id')->where(fn ($q) => $q->where('nominee_name', 'like', $term)->orWhere('nominee_surname', 'like', $term))->limit(100)->get()->map(fn ($p) => ['key' => 'nominee:'.$p->id, 'name' => $p->display_name]) : collect();

        return $players->concat($unlinked)->concat($nominees)->sortBy('name')->take(100)->values();
    }

    private function row(Team $team, string $key, string $name, array $emails, string $status): array
    {
        return ['key' => $key, 'name' => $name, 'emails' => $emails, 'status' => $status, 'team_id' => $team->id, 'region_id' => $team->region_id, 'category' => $team->category?->category?->name ?? '', 'team' => $team->name, 'nominated' => false];
    }

    public function plan(Event $event, User $actor, array $options, string $subject, string $body): array
    {
        $regions = $this->regions($event, $actor);
        $rows = $this->entries($event, $actor);
        $scope = $options['scope'];
        if ($scope === 'region') {
            abort_unless($regions->contains('region_id', (int) $options['region_id']), 403);
            $rows = $rows->where('region_id', (int) $options['region_id']);
        } elseif ($scope === 'team') {
            $team = Team::withoutGlobalScopes()->with('category')->findOrFail($options['team_id']);
            abort_unless((int) $team->category?->event_id === (int) $event->id && $regions->contains('region_id', $team->region_id), 404);
            $rows = $rows->where('team_id', $team->id);
        } elseif ($scope === 'individual') {
            $rows = $rows->where('key', $options['individual_key']);
            abort_if($rows->isEmpty(), 404);
        } elseif ($scope === 'nominations') {
            $rows = $rows->where('nominated', true);
        }
        $filter = $options['filter'];
        $rows = $rows->filter(fn ($row) => match ($filter) {
            'not_registered' => ! in_array($row['status'], ['paid_confirmed', 'declined', 'withdrawn'], true),
            'paid' => $row['status'] === 'paid_confirmed',
            'payment_pending' => $row['status'] === 'accepted_pending_payment',
            'declined' => $row['status'] === 'declined',
            'withdrawn' => $row['status'] === 'withdrawn',
            'reserves' => $row['status'] === 'reserve',
            default => true,
        })->values();
        $messages = collect();
        $issues = $rows->filter(fn ($row) => empty($row['emails']))->map(fn ($row) => $row['name'].' — no valid email address')->values();
        if (in_array($options['recipients'], ['players', 'both'], true)) {
            // Group siblings' personalised sections without dropping a player's information.
            $byEmail = collect();
            foreach ($rows as $row) foreach ($row['emails'] as $email) $byEmail->put($email, ($byEmail->get($email, collect()))->push($row));
            foreach ($byEmail as $email => $players) {
                $text = $body."\n\nPlayer details:\n".$players->map(fn ($p) => $p['name'].' — '.$p['team'].' — '.Str::headline($p['status']))->unique()->implode("\n");
                $messages->push(['email' => $email, 'name' => $players->first()['name'], 'kind' => 'players', 'subject' => $subject, 'html' => nl2br(e($text))]);
            }
        }
        if (in_array($options['recipients'], ['managers', 'both'], true)) {
            foreach ($rows->whereNotNull('team_id')->groupBy('team_id') as $teamRows) {
                $first = $teamRows->first();
                $region = $regions->firstWhere('region_id', $first['region_id']);
                $manager = $region ? $this->access->manager($region) : null;
                if (! $manager || ! filter_var($manager->email, FILTER_VALIDATE_EMAIL)) {
                    $issues->push($first['team'].' — no regional manager with a valid email address');
                    continue;
                }
                $text = $body."\n\nTeam summary: ".$first['team']."\n".$teamRows->map(fn ($p) => $p['name'].' — '.Str::headline($p['status']))->implode("\n");
                $messages->push(['email' => mb_strtolower(trim($manager->email)), 'name' => $manager->name, 'kind' => 'manager', 'subject' => Str::limit($subject.' — '.$first['team'], 250, ''), 'html' => nl2br(e($text))]);
            }
        }
        if ($messages->isEmpty()) throw ValidationException::withMessages(['audience' => 'No matching recipient has a valid email address. Choose another audience or add contacts.']);
        $plan = ['recipients' => $messages->sortBy([['email', 'asc'], ['subject', 'asc']])->values()->all(), 'issues' => $issues->unique()->sort()->values()->all()];
        $plan['fingerprint'] = hash('sha256', json_encode([$event->id, $options, $subject, $body, $plan], JSON_THROW_ON_ERROR));

        return $plan;
    }

    public function preview(Event $event, User $actor, array $options, string $subject, string $body): EventCommunicationBatch
    {
        $plan = $this->plan($event, $actor, $options, $subject, $body);

        return EventCommunicationBatch::create(['event_id' => $event->id, 'created_by' => $actor->id, 'token' => (string) Str::uuid(), 'subject' => $subject, 'body' => $body, 'options' => $options, ...$plan]);
    }

    public function draftFixed(Event $event, string $sourceKey, array $recipients, string $subject): EventCommunicationBatch
    {
        return EventCommunicationBatch::firstOrCreate(['source_key' => $sourceKey], ['event_id' => $event->id, 'created_by' => null, 'token' => (string) Str::uuid(), 'subject' => $subject, 'body' => '', 'options' => ['source' => 'transaction'], 'recipients' => $recipients, 'issues' => [], 'fingerprint' => hash('sha256', json_encode($recipients, JSON_THROW_ON_ERROR)), 'status' => 'draft']);
    }

    public function previewDraft(EventCommunicationBatch $draft, User $actor): EventCommunicationBatch
    {
        $this->regions($draft->event, $actor);
        abort_unless($this->managesWholeEvent($draft->event, $actor) && $draft->options['source'] === 'transaction', 403);

        return EventCommunicationBatch::create(['event_id' => $draft->event_id, 'created_by' => $actor->id, 'token' => (string) Str::uuid(), 'subject' => $draft->subject, 'body' => '', 'options' => ['source' => 'transaction', 'draft_id' => $draft->id], 'recipients' => $draft->recipients, 'issues' => [], 'fingerprint' => $draft->fingerprint]);
    }

    public function previewRetry(Event $event, BulkEmailLog $log, User $actor): EventCommunicationBatch
    {
        $this->regions($event, $actor);
        if (in_array($log->mail_type, ['team_selection_invitation', 'interprovincial_trial_invitation'], true)) {
            $log = $this->invitationLogs($event, $actor)->whereKey($log->id)->firstOrFail();
            $recipient = $this->invitationRetryRecipient($event, $log);

            return EventCommunicationBatch::create(['event_id' => $event->id, 'created_by' => $actor->id, 'token' => (string) Str::uuid(), 'subject' => $recipient['subject'], 'body' => '', 'options' => ['source' => 'invitation_retry', 'log_id' => $log->id, 'payload_fingerprint' => hash('sha256', json_encode($log->payload, JSON_THROW_ON_ERROR))], 'recipients' => [$recipient], 'issues' => [], 'fingerprint' => hash('sha256', json_encode($recipient, JSON_THROW_ON_ERROR))]);
        }
        $original = EventCommunicationBatch::where('event_id', $event->id)->where('created_by', $actor->id)->findOrFail($log->payload['event_communication_batch_id'] ?? 0);
        abort_unless($log->status === 'failed' && ! $log->sent_at && (int) ($log->payload['event_id'] ?? 0) === (int) $event->id, 422);
        $recipient = ['email' => $log->recipient_email, 'name' => $log->recipient_name, 'kind' => $log->payload['recipient_kind'] ?? 'players', 'subject' => $log->payload['subject'], 'html' => $log->payload['body']];

        return EventCommunicationBatch::create(['event_id' => $event->id, 'created_by' => $actor->id, 'token' => (string) Str::uuid(), 'subject' => $recipient['subject'], 'body' => '', 'options' => ['source' => 'retry', 'log_id' => $log->id, 'original_batch_id' => $original->id], 'recipients' => [$recipient], 'issues' => [], 'fingerprint' => hash('sha256', json_encode($recipient, JSON_THROW_ON_ERROR))]);
    }

    public function invitationLogs(Event $event, User $actor): \Illuminate\Database\Eloquent\Builder
    {
        $regions = $this->regions($event, $actor);
        if ($event->isInterprovincialTrials()) {
            $ids = \App\Models\InterprovincialTrialInvitation::where('event_id', $event->id)->whereHas('batch', fn ($q) => $q->where('event_id', $event->id))->select('id');

            return BulkEmailLog::where('mail_type', 'interprovincial_trial_invitation')->where('related_type', \App\Models\InterprovincialTrialInvitation::class)->whereIn('related_id', $ids);
        }
        $ids = TeamSelectionInvitation::where('event_id', $event->id)
            ->whereHas('selectionImport', fn ($q) => $q->where('event_id', $event->id)->whereColumn('team_selection_imports.region_id', 'team_selection_invitations.region_id'))
            ->whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))
            ->whereHas('team', fn ($q) => $q->whereColumn('teams.region_id', 'team_selection_invitations.region_id'))
            ->when(! $this->managesWholeEvent($event, $actor), fn ($q) => $q->whereIn('region_id', $regions->pluck('region_id')))->select('id');

        return BulkEmailLog::where('mail_type', 'team_selection_invitation')->where('related_type', TeamSelectionInvitation::class)->whereIn('related_id', $ids);
    }

    private function invitationRetryRecipient(Event $event, BulkEmailLog $log): array
    {
        abort_unless($log->status === 'failed' && ! $log->sent_at && ! $log->accepted_at, 422);
        $payload = $log->payload;
        if (empty($payload['rendered_html']) || empty($payload['rendered_subject'])) {
            throw ValidationException::withMessages(['preview' => 'This legacy invitation has no exact saved email. Prepare a fresh invitation preview.']);
        }
        abort_unless(app(InvitationMailSecurity::class)->logMatchesSignedSnapshot($log, $event->id), 422);

        return ['email' => $log->recipient_email, 'name' => $log->recipient_name, 'kind' => 'invitation', 'subject' => $payload['rendered_subject'], 'html' => $payload['rendered_html']];
    }

    public function approve(EventCommunicationBatch $batch, User $actor, bool $acknowledgeMissing): array
    {
        $this->regions($batch->event, $actor);
        abort_unless((int) $batch->created_by === (int) $actor->id, 403);

        return DB::transaction(function () use ($batch, $actor, $acknowledgeMissing) {
            $batch = EventCommunicationBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->approved_at) return ['queued' => 0, 'duplicate' => true];
            if ($batch->created_at->lt(now()->subHour())) throw ValidationException::withMessages(['preview' => 'This preview expired. Review a fresh recipient and message preview.']);
            if (($batch->options['source'] ?? null) === 'invitation_retry') {
                $log = $this->invitationLogs($batch->event, $actor)->whereKey($batch->options['log_id'])->lockForUpdate()->firstOrFail();
                $recipient = $this->invitationRetryRecipient($batch->event, $log);
                abort_unless(hash_equals($batch->fingerprint, hash('sha256', json_encode($recipient, JSON_THROW_ON_ERROR)))
                    && hash_equals($batch->options['payload_fingerprint'], hash('sha256', json_encode($log->payload, JSON_THROW_ON_ERROR))), 422);
                $log->update(['status' => 'queued', 'queued_at' => now(), 'failed_at' => null, 'error_message' => null]);
                $batch->update(['status' => 'approved', 'approved_at' => now()]);
                if ($log->mail_type === 'team_selection_invitation') {
                    \App\Jobs\SendTeamSelectionInvitationEmailJob::dispatch($log->id, $batch->event_id)->afterCommit();
                } else {
                    \App\Jobs\SendInterprovincialTrialInvitationEmailJob::dispatch($log->id, $batch->event_id)->afterCommit();
                }

                return ['queued' => 1, 'duplicate' => false];
            }
            if (($batch->options['source'] ?? null) === 'retry') {
                $log = BulkEmailLog::whereKey($batch->options['log_id'])->lockForUpdate()->firstOrFail();
                abort_unless($log->status === 'failed' && ! $log->sent_at && (int) ($log->payload['event_id'] ?? 0) === (int) $batch->event_id && (int) ($log->payload['event_communication_batch_id'] ?? 0) === (int) $batch->options['original_batch_id'], 422);
                $recipient = ['email' => $log->recipient_email, 'name' => $log->recipient_name, 'kind' => $log->payload['recipient_kind'] ?? 'players', 'subject' => $log->payload['subject'], 'html' => $log->payload['body']];
                abort_unless(hash_equals($batch->fingerprint, hash('sha256', json_encode($recipient, JSON_THROW_ON_ERROR))), 422);
                $log->update(['status' => 'queued', 'queued_at' => now(), 'failed_at' => null, 'error_message' => null, 'payload' => [...$log->payload, 'origin_batch_id' => $log->payload['origin_batch_id'] ?? $batch->options['original_batch_id'], 'event_communication_batch_id' => $batch->id]]);
                $batch->update(['status' => 'approved', 'approved_at' => now()]);
                \App\Jobs\SendBulkEmailJob::dispatch($log->id, true)->afterCommit();

                return ['queued' => 1, 'duplicate' => false];
            }
            $draft = null;
            if (($batch->options['source'] ?? null) === 'transaction') {
                abort_unless($this->managesWholeEvent($batch->event, $actor), 403);
                $draft = EventCommunicationBatch::where('event_id', $batch->event_id)->where('status', 'draft')->whereKey($batch->options['draft_id'])->lockForUpdate()->first();
                if (! $draft) throw ValidationException::withMessages(['preview' => 'This draft was already approved. No email was queued again.']);
                $plan = ['fingerprint' => $draft->fingerprint];
            } else {
                $plan = $this->plan($batch->event, $actor, $batch->options, $batch->subject, $batch->body);
            }
            if (! hash_equals($batch->fingerprint, $plan['fingerprint'])) throw ValidationException::withMessages(['preview' => 'Recipients or player details changed. Review a fresh preview before sending.']);
            if ($batch->issues && ! $acknowledgeMissing) throw ValidationException::withMessages(['acknowledge_missing' => 'Acknowledge the missing contacts shown in the preview.']);
            $batch->update(['status' => 'approved', 'approved_at' => now()]);
            $draft?->update(['status' => 'approved', 'approved_at' => now()]);
            $queued = 0;
            foreach ($batch->recipients as $recipient) {
                $result = $this->mailer->dispatch($batch->event->isInterprovincialTrials() ? 'trial_communication' : 'team_email', $batch, [$recipient], ['event_id' => $batch->event_id, 'event_communication_batch_id' => $batch->id, 'subject' => $recipient['subject'], 'body' => $recipient['html'], 'recipient_kind' => $recipient['kind'], 'from_name' => $actor->name, 'reply_to' => $actor->email, 'manual_retry_only' => true], true);
                $queued += $result['queued'];
            }

            return ['queued' => $queued, 'duplicate' => false];
        });
    }
}
