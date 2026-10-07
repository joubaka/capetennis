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
        if (! $event->isTeam()) {
            abort_unless($this->access->isEventManager($actor, $event), 403);

            return collect();
        }
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

    /** Current roster slots only; linking a profile replaces the imported contact. */
    public function rosterEntries(Event $event, ?array $regionIds = null): Collection
    {
        $teams = Team::withoutGlobalScopes()
            ->whereHas('category', fn ($q) => $q->where('event_id', $event->id))
            ->when($regionIds !== null, fn ($q) => $q->whereIn('region_id', $regionIds))
            ->with(['category.category', 'team_players.player.user', 'team_players.player.users', 'team_players_no_profile.profile.user', 'team_players_no_profile.profile.users'])->get();
        $rows = collect();
        foreach ($teams as $team) {
            foreach ($team->team_players as $slot) {
                if (! $slot->player) continue;
                $key = 'player:'.$slot->player_id;
                $rows->put($key.':'.$team->id, array_replace($this->row($team, $key, $slot->player->full_name, $this->contacts->emails($slot->player)->all(), (int) $slot->pay_status === 1 ? 'paid_confirmed' : 'not_registered'), ['gender' => $slot->player->gender]));
            }
            foreach ($team->team_players_no_profile as $slot) {
                $key = $slot->player_profile ? 'player:'.$slot->player_profile : 'imported:'.$slot->id;
                if ($rows->has($key.':'.$team->id)) continue;
                $emails = $slot->player_profile ? $this->contacts->emails($slot->profile) : collect([mb_strtolower(trim((string) $slot->email))])->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));
                $rows->put($key.':'.$team->id, array_replace($this->row($team, $key, $slot->profile?->full_name ?? trim($slot->name.' '.$slot->surname), $emails->values()->all(), (int) $slot->pay_status === 1 ? 'paid_confirmed' : 'not_registered'), ['gender' => $slot->profile?->gender]));
            }
        }
        return $rows->values();
    }

    public function entries(Event $event, User $actor): Collection
    {
        $regions = $this->regions($event, $actor);
        if (! $event->isTeam() && ! $event->isInterprovincialTrials()) {
            return $this->registrationEntries($event);
        }
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
                if (! $slot->player_profile && filter_var(trim((string) $slot->email), FILTER_VALIDATE_EMAIL)) $emails->push(mb_strtolower(trim($slot->email)));
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

    /** Keep category-specific statuses until filtering, then deduplicate email messages. */
    private function registrationEntries(Event $event): Collection
    {
        $rows = collect();
        $registrations = $event->registrations()->with(['players.user', 'players.users', 'categoryEvent.category'])->get();
        foreach ($registrations as $registration) {
            $status = str_starts_with((string) $registration->status, 'withdrawn')
                ? 'withdrawn'
                : ((int) $registration->payment_status_id === 1 ? 'paid_confirmed' : 'accepted_pending_payment');
            foreach ($registration->players as $player) {
                $rows->push([
                    'key' => 'player:'.$player->id,
                    'name' => $player->full_name,
                    'emails' => $this->contacts->emails($player)->all(),
                    'status' => $status,
                    'team_id' => null,
                    'region_id' => null,
                    'category' => $registration->categoryEvent?->category?->name ?? '',
                    'category_event_id' => $registration->category_event_id,
                    'team' => '',
                    'nominated' => false,
                    'registered' => true,
                    'invited' => false,
                ]);
            }
        }
        $nominations = EventNomination::with(['player.user', 'player.users', 'categoryEvent.category'])
            ->where('event_id', $event->id)
            ->where(fn ($q) => $q->whereNull('category_event_id')->orWhereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id)))
            ->get();
        foreach ($nominations as $nomination) {
            $key = $nomination->player_id ? 'player:'.$nomination->player_id : 'nominee:'.$nomination->id;
            $emails = $nomination->player ? $this->contacts->emails($nomination->player) : collect();
            if (filter_var($nomination->nominee_email, FILTER_VALIDATE_EMAIL)) {
                $emails->push(mb_strtolower(trim($nomination->nominee_email)));
            }
            $existing = $rows->filter(fn ($row) => $row['key'] === $key && (int) $row['category_event_id'] === (int) $nomination->category_event_id);
            if ($existing->isNotEmpty()) {
                foreach ($existing as $index => $row) {
                    $rows->put($index, [...$row, 'nominated' => true, 'emails' => collect($row['emails'])->concat($emails)->unique()->values()->all()]);
                }
                continue;
            }
            $rows->push([
                'key' => $key,
                'name' => $nomination->display_name,
                'emails' => $emails->unique()->values()->all(),
                'status' => 'not_registered',
                'team_id' => null,
                'region_id' => null,
                'category' => $nomination->categoryEvent?->category?->name ?? 'Nominee',
                'category_event_id' => $nomination->category_event_id,
                'team' => '',
                'nominated' => true,
                'registered' => false,
                'invited' => false,
            ]);
        }

        if ($event->isMasters()) {
            foreach ($this->mastersInvitations($event)->with(['player.user', 'player.users', 'categoryEvent.category'])->get() as $invitation) {
                if (! $invitation->player) continue;
                $key = 'player:'.$invitation->player_id;
                $existing = $rows->filter(fn ($row) => $row['key'] === $key && (int) $row['category_event_id'] === (int) $invitation->category_event_id);
                if ($existing->isNotEmpty()) {
                    foreach ($existing as $index => $row) {
                        $rows->put($index, [...$row, 'invited' => true, 'status' => $row['registered'] ? $row['status'] : $invitation->status]);
                    }
                    continue;
                }
                $rows->push([
                    'key' => $key, 'name' => $invitation->player->full_name,
                    'emails' => $this->contacts->emails($invitation->player)->all(),
                    'status' => $invitation->status, 'team_id' => null, 'region_id' => null,
                    'category' => $invitation->categoryEvent?->category?->name ?? '',
                    'category_event_id' => $invitation->category_event_id,
                    'team' => '', 'nominated' => false, 'registered' => false, 'invited' => true,
                ]);
            }
        }

        return $rows->sortBy([['name', 'asc'], ['category', 'asc']])->values();
    }

    private function mastersInvitations(Event $event): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Models\MastersInvitation::where('event_id', $event->id)
            ->whereHas('batch', fn ($q) => $q->where('event_id', $event->id)->whereIn('status', ['generated', 'ready_for_invitation', 'sent']))
            ->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))
            ->whereIn('status', ['reserve', 'invited', 'accepted_pending_payment', 'paid_confirmed', 'declined', 'withdrawn']);
    }

    public function individualOptions(Event $event, User $actor, string $search): Collection
    {
        $regions = $this->regions($event, $actor);
        if ($search === '') return collect();
        if (! $event->isTeam() && ! $event->isInterprovincialTrials()) {
            $term = '%'.addcslashes(mb_substr(trim($search), 0, 100), '%_\\').'%';
            $registrationIds = $event->registrations()->select('category_event_registrations.registration_id');
            $playerIds = DB::table('player_registrations')->whereIn('registration_id', $registrationIds)->select('player_id');
            $nominations = EventNomination::where('event_id', $event->id)
                ->where(fn ($q) => $q->whereNull('category_event_id')->orWhereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id)));
            $players = Player::where(function ($q) use ($playerIds, $nominations, $event) {
                $q->whereIn('id', $playerIds)->orWhereIn('id', (clone $nominations)->select('player_id'));
                if ($event->isMasters()) $q->orWhereIn('id', $this->mastersInvitations($event)->select('player_id'));
            })
                ->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('surname', 'like', $term))
                ->orderBy('surname')->orderBy('id')->limit(100)->get()
                ->map(fn ($player) => ['key' => 'player:'.$player->id, 'name' => $player->full_name]);
            $nominees = $nominations->whereNull('player_id')
                ->where(fn ($q) => $q->where('nominee_name', 'like', $term)->orWhere('nominee_surname', 'like', $term))
                ->orderBy('nominee_surname')->orderBy('id')->limit(100)->get()
                ->map(fn ($nominee) => ['key' => 'nominee:'.$nominee->id, 'name' => $nominee->display_name]);

            return $players->concat($nominees)->sortBy('name')->take(100)->values();
        }
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
        return ['key' => $key, 'name' => $name, 'emails' => $emails, 'status' => $status, 'gender' => null, 'category_event_id' => $team->category_event_id, 'team_id' => $team->id, 'region_id' => $team->region_id, 'category' => $team->category?->category?->name ?? '', 'team' => $team->name, 'nominated' => false];
    }

    public function plan(Event $event, User $actor, array $options, string $subject, string $body): array
    {
        if (($options['source'] ?? null) === 'roster_region_selection') {
            $regions = $this->regions($event, $actor);
            $region = $regions->firstWhere('id', $options['event_region_id']);
            abort_unless($region, 403);
            $recipients = app(\App\Services\TeamSelection\TeamSelectionEmailAudienceService::class)->resolve($event, $region, $options['selection']);
            return $this->legacyPlan($event, $options, $recipients->map(fn ($recipient) => ['key' => $recipient['player_key'], 'name' => $recipient['name'], 'emails' => filled($recipient['email']) ? [$recipient['email']] : []]), $subject, $body);
        }
        if (($options['source'] ?? null) === 'roster_selection') {
            $this->regions($event, $actor);
            abort_unless($this->managesWholeEvent($event, $actor), 403);
            $recipients = app(\App\Services\TeamSelection\TeamSelectionEmailAudienceService::class)->resolveForEvent($event, $options['selection']);
            return $this->legacyPlan($event, $options, $recipients->map(fn ($recipient) => ['key' => $recipient['player_key'] ?? 'email:'.$recipient['email'], 'player_keys' => $recipient['player_keys'] ?? [$recipient['player_key'] ?? 'email:'.$recipient['email']], 'name' => $recipient['name'], 'emails' => filled($recipient['email']) ? [$recipient['email']] : []]), $subject, $body);
        }
        if (($options['scope'] ?? null) === 'legacy_registered') {
            $this->regions($event, $actor);
            abort_unless($this->managesWholeEvent($event, $actor), 403);
            $rows = $this->registrationEntries($event)->where('registered', true);
            if (! empty($options['category_event_id'])) {
                abort_unless($event->categoryEvents()->whereKey($options['category_event_id'])->exists(), 404);
                $rows = $rows->where('category_event_id', (int) $options['category_event_id']);
            }
            if (! empty($options['registration_id'])) {
                $registration = $event->registrations()->where('registration_id', $options['registration_id'])->firstOrFail();
                $keys = $registration->players->map(fn ($player) => 'player:'.$player->id);
                $rows = $rows->whereIn('key', $keys);
            }
            $rows = $rows->filter(fn ($row) => match ($options['filter'] ?? 'all') {
                'paid' => $row['status'] === 'paid_confirmed',
                'not_registered' => ! in_array($row['status'], ['paid_confirmed', 'withdrawn'], true),
                default => true,
            });
            return $this->legacyPlan($event, $options, $rows, $subject, $body);
        }
        if (($options['scope'] ?? null) === 'direct') {
            $this->regions($event, $actor);
            abort_unless($this->managesWholeEvent($event, $actor), 403);
            abort_unless(filter_var($options['direct_email'] ?? '', FILTER_VALIDATE_EMAIL), 422);
            return $this->legacyPlan($event, $options, collect([['name' => $options['direct_email'], 'emails' => [mb_strtolower($options['direct_email'])]]]), $subject, $body);
        }
        if (! $event->isTeam() && ! $event->isInterprovincialTrials()) {
            $this->regions($event, $actor);
            abort_unless(in_array($options['scope'] ?? null, ($event->isMasters() ? ['all', 'registrations', 'nominations', 'individual', 'invitations'] : ['all', 'registrations', 'nominations', 'individual']), true)
                && ($options['recipients'] ?? null) === 'players', 422);
        }
        if ($event->isTeam() || $event->isInterprovincialTrials()) {
            $this->regions($event, $actor);
            abort_unless(in_array($options['scope'] ?? null, ['all', 'nominations', 'region', 'team', 'individual', 'rankings'], true), 422);
        }
        if (($options['scope'] ?? null) === 'rankings') return $this->rankingPlan($event, $actor, $options, $subject, $body);
        $regions = $this->regions($event, $actor);
        $rows = $this->entries($event, $actor);
        if ($event->isTeam() && in_array($options['scope'] ?? null, ['all', 'region', 'team'], true)) {
            $rosterKeys = $this->rosterEntries($event, $regions->pluck('region_id')->all())->map(fn ($row) => $row['team_id'].':'.$row['key']);
            $rows = $rows->filter(fn ($row) => $rosterKeys->contains(($row['team_id'] ?? '').':'.$row['key']));
        }
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
        } elseif ($scope === 'invitations') {
            $rows = $rows->where('invited', true);
        } elseif ($scope === 'registrations') {
            $rows = $rows->where('registered', true);
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
        $issues = $rows->filter(fn ($row) => empty($row['emails']))->unique('key')->map(fn ($row) => $row['name'].' — no valid email address')->values();
        if (in_array($options['recipients'], ['players', 'both'], true)) {
            // Shared contacts receive one authored message while retaining every player's audit key.
            $byEmail = collect();
            foreach ($rows as $row) foreach ($row['emails'] as $email) $byEmail->put($email, ($byEmail->get($email, collect()))->push($row));
            foreach ($byEmail as $email => $players) {
                $messages->push(['email' => $email, 'name' => $players->first()['name'], 'kind' => 'players', 'player_keys' => $players->pluck('key')->unique()->values()->all(), 'subject' => $subject, 'html' => nl2br(e($body))]);
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
                $messages->push(['email' => mb_strtolower(trim($manager->email)), 'name' => $manager->name, 'kind' => 'manager', 'subject' => $subject, 'html' => nl2br(e($body))]);
            }
        }
        if ($messages->isEmpty()) throw ValidationException::withMessages(['audience' => 'No matching recipient has a valid email address. Choose another audience or add contacts.']);
        $plan = ['recipients' => $messages->sortBy([['email', 'asc'], ['subject', 'asc']])->values()->all(), 'issues' => $issues->sort()->values()->all()];
        // Keep audience changes reviewable without inserting private player details into the email.
        $plan['fingerprint'] = $this->planFingerprint([$event->id, $options, $subject, $body, $plan, $rows->all()]);

        return $plan;
    }

    private function legacyPlan(Event $event, array $options, Collection $rows, string $subject, string $body): array
    {
        $recipients = collect();
        $issues = collect();
        foreach ($rows as $row) {
            if (empty($row['emails'])) $issues->put($row['key'] ?? 'missing:'.$issues->count(), $row['name'].' - no valid email address');
            foreach ($row['emails'] as $email) {
                $previous = $recipients->get($email, []);
                $recipients->put($email, ['email' => $email, 'name' => $row['name'], 'kind' => 'players', 'player_keys' => collect($previous['player_keys'] ?? [])->concat($row['player_keys'] ?? [$row['key'] ?? 'email:'.$email])->unique()->values()->all(), 'subject' => $subject, 'html' => nl2br(e($body))]);
            }
        }
        if ($recipients->isEmpty()) throw ValidationException::withMessages(['audience' => 'No matching recipient has a valid email address.']);
        $plan = ['recipients' => $recipients->sortBy('email')->values()->all(), 'issues' => $issues->sort()->values()->all()];
        $plan['fingerprint'] = $this->planFingerprint([$event->id, $options, $subject, $body, $plan]);
        return $plan;
    }

    private function rankingPlan(Event $event, User $actor, array $options, string $subject, string $body): array
    {
        $audience = app(RegionalRankingMailAudience::class)->resolve($event, $actor, $options);
        $rows = $audience['rows']->filter(fn ($row) => ! $row['excluded']);
        $byEmail = collect();
        foreach ($rows as $row) foreach ($row['emails'] as $email) $byEmail->put($email, $byEmail->get($email, collect())->push($row));
        $recipients = $byEmail->map(function ($players, $email) use ($subject, $body) {
            return ['email' => $email, 'name' => $players->first()['name'], 'kind' => 'players', 'subject' => $subject, 'html' => nl2br(e($body)), 'ranking_review' => $players->values()->all()];
        })->sortBy('email')->values()->all();
        if (! $recipients) throw ValidationException::withMessages(['audience' => 'No matching recipient has a valid email address.']);
        $issues = $rows->filter(fn ($row) => ! $row['emails'])->map(fn ($row) => $row['name'].' — no valid email address')->unique()->sort()->values()->all();
        $plan = ['recipients' => $recipients, 'issues' => $issues];
        $plan['fingerprint'] = $this->planFingerprint([$event->id, $options, $subject, $body, $plan, $audience['sources'], $audience['rows']->all()]);
        return $plan;
    }

    private function planFingerprint(array $value): string
    {
        // MySQL JSON normalises object keys; fingerprint the same semantic plan after persistence.
        $normalise = function (array $items) use (&$normalise): array {
            if (! array_is_list($items)) ksort($items);
            foreach ($items as $key => $item) if (is_array($item)) $items[$key] = $normalise($item);
            return $items;
        };

        return hash('sha256', json_encode($normalise($value), JSON_THROW_ON_ERROR));
    }

    public function rankingOriginal(EventCommunicationBatch $batch): ?EventCommunicationBatch
    {
        $seen = [];
        while (true) {
            abort_if(in_array($batch->id, $seen, true), 422);
            $seen[] = $batch->id;
            if (($batch->options['scope'] ?? null) === 'rankings') return $batch;
            if (($batch->options['source'] ?? null) !== 'retry') return null;
            $batch = EventCommunicationBatch::where('event_id', $batch->event_id)->findOrFail($batch->options['original_batch_id']);
        }
    }

    public function authorizeRankingBatch(EventCommunicationBatch $batch, User $actor): void
    {
        if ($this->rankingOriginal($batch)) abort_unless(app(RegionalRankingMailAudience::class)->canManage($batch->event, $actor), 403);
    }

    private function checkRankingRetry(EventCommunicationBatch $batch, User $actor): void
    {
        $this->authorizeRankingBatch($batch, $actor);
        if ($original = $this->rankingOriginal($batch)) {
            $plan = $this->plan($original->event, $actor, $original->options, $original->subject, $original->body);
            if (! hash_equals($original->fingerprint, $plan['fingerprint'])) throw ValidationException::withMessages(['preview' => 'Ranking recipients changed. Prepare a fresh ranking preview.']);
        }
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
        $this->checkRankingRetry($original, $actor);
        abort_unless($log->status === 'failed' && ! $log->sent_at && ! $log->accepted_at && (int) ($log->payload['event_id'] ?? 0) === (int) $event->id, 422);
        $recipient = ['email' => $log->recipient_email, 'name' => $log->recipient_name, 'kind' => $log->payload['recipient_kind'] ?? 'players', 'player_keys' => $log->payload['player_keys'] ?? [], 'subject' => $log->payload['subject'], 'html' => $log->payload['body']];

        return EventCommunicationBatch::create(['event_id' => $event->id, 'created_by' => $actor->id, 'token' => (string) Str::uuid(), 'subject' => $recipient['subject'], 'body' => '', 'options' => ['source' => 'retry', 'log_id' => $log->id, 'original_batch_id' => $original->id, 'ranking_origin_id' => $this->rankingOriginal($original)?->id], 'recipients' => [$recipient], 'issues' => [], 'fingerprint' => hash('sha256', json_encode($recipient, JSON_THROW_ON_ERROR))]);
    }

    public function previewAnnouncementRetry(Event $event, \App\Models\TeamSelectionRegionAnnouncement $announcement, User $actor): EventCommunicationBatch
    {
        $this->regions($event, $actor);
        abort_unless((int) $announcement->event_id === (int) $event->id, 404);
        $region = EventRegion::where('event_id', $event->id)->where('region_id', $announcement->region_id)->firstOrFail();
        abort_unless($this->access->canManage($actor, $region), 403);
        $logs = $announcement->emailLogs()->where('status', 'failed')->whereNull('accepted_at')->whereNull('sent_at')->orderBy('id')->get();
        if ($logs->isEmpty()) throw ValidationException::withMessages(['preview' => 'There are no failed announcement emails to retry.']);
        $recipients = $logs->map(fn ($log) => ['email' => $log->recipient_email, 'name' => $log->recipient_name, 'kind' => $log->payload['recipient_kind'] ?? 'players', 'player_keys' => $log->payload['player_keys'] ?? [], 'subject' => $log->payload['subject'], 'html' => $log->payload['body'], 'log_id' => $log->id])->all();
        return EventCommunicationBatch::create(['event_id' => $event->id, 'created_by' => $actor->id, 'token' => (string) Str::uuid(), 'subject' => $announcement->title, 'body' => '', 'options' => ['source' => 'announcement_retry', 'regional_announcement_id' => $announcement->id, 'region_id' => $announcement->region_id], 'recipients' => $recipients, 'issues' => [], 'fingerprint' => $this->planFingerprint($recipients)]);
    }

    public function invitationLogs(Event $event, User $actor): \Illuminate\Database\Eloquent\Builder
    {
        $regions = $this->regions($event, $actor);
        if ($event->isInterprovincialTrials()) {
            $ids = \App\Models\InterprovincialTrialInvitation::where('event_id', $event->id)->whereHas('batch', fn ($q) => $q->where('event_id', $event->id))->select('id');

            return BulkEmailLog::where('mail_type', 'interprovincial_trial_invitation')->where('related_type', \App\Models\InterprovincialTrialInvitation::class)->whereIn('related_id', $ids);
        }
        if ($event->isMasters()) {
            $ids = \App\Models\MastersInvitation::where('event_id', $event->id)
                ->whereHas('batch', fn ($query) => $query->where('event_id', $event->id))
                ->whereHas('categoryEvent', fn ($query) => $query->where('event_id', $event->id))->select('id');

            return BulkEmailLog::where('mail_type', 'masters_invitation')
                ->where('related_type', \App\Models\MastersInvitation::class)->whereIn('related_id', $ids);
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
        abort_if(($batch->options['source'] ?? null)==='series_compose',422,'Approve this intent from its series review, which checks every event together.');
        $this->authorizeRankingBatch($batch, $actor);
        $this->regions($batch->event, $actor);
        abort_unless((int) $batch->created_by === (int) $actor->id, 403);

        return DB::transaction(function () use ($batch, $actor, $acknowledgeMissing) {
            $batch = EventCommunicationBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->approved_at) return ['queued' => 0, 'duplicate' => true];
            if ($batch->created_at->lt(now()->subHour())) throw ValidationException::withMessages(['preview' => 'This preview expired. Review a fresh recipient and message preview.']);
            if (($batch->options['source'] ?? null) === 'announcement_retry') {
                $announcement = \App\Models\TeamSelectionRegionAnnouncement::where('event_id', $batch->event_id)->where('region_id', $batch->options['region_id'])->findOrFail($batch->options['regional_announcement_id']);
                $region = EventRegion::where('event_id', $batch->event_id)->where('region_id', $announcement->region_id)->firstOrFail();
                abort_unless($this->access->canManage($actor, $region), 403);
                abort_unless(hash_equals($batch->fingerprint, $this->planFingerprint($batch->recipients)), 422);
                foreach ($batch->recipients as $recipient) {
                    $log = $announcement->emailLogs()->whereKey($recipient['log_id'])->lockForUpdate()->firstOrFail();
                    $current = ['email' => $log->recipient_email, 'name' => $log->recipient_name, 'kind' => $log->payload['recipient_kind'] ?? 'players', 'player_keys' => $log->payload['player_keys'] ?? [], 'subject' => $log->payload['subject'], 'html' => $log->payload['body'], 'log_id' => $log->id];
                    abort_unless($log->status === 'failed' && ! $log->sent_at && ! $log->accepted_at && (int) ($log->payload['event_id'] ?? 0) === (int) $batch->event_id && hash_equals($this->planFingerprint($recipient), $this->planFingerprint($current)), 422);
                    $log->update(['status' => 'queued', 'queued_at' => now(), 'failed_at' => null, 'error_message' => null, 'retry_actor_id' => $actor->id, 'payload' => [...$log->payload, 'origin_batch_id' => $log->payload['origin_batch_id'] ?? $log->payload['event_communication_batch_id'], 'event_communication_batch_id' => $batch->id]]);
                    \App\Jobs\SendBulkEmailJob::dispatch($log->id, true)->afterCommit();
                }
                $batch->update(['status' => 'approved', 'approved_at' => now()]);
                return ['queued' => count($batch->recipients), 'duplicate' => false];
            }
            if (($batch->options['source'] ?? null) === 'invitation_retry') {
                $log = $this->invitationLogs($batch->event, $actor)->whereKey($batch->options['log_id'])->lockForUpdate()->firstOrFail();
                $recipient = $this->invitationRetryRecipient($batch->event, $log);
                abort_unless(hash_equals($batch->fingerprint, hash('sha256', json_encode($recipient, JSON_THROW_ON_ERROR)))
                    && hash_equals($batch->options['payload_fingerprint'], hash('sha256', json_encode($log->payload, JSON_THROW_ON_ERROR))), 422);
                $log->update(['status' => 'queued', 'queued_at' => now(), 'failed_at' => null, 'error_message' => null, 'retry_actor_id' => $actor->id]);
                $batch->update(['status' => 'approved', 'approved_at' => now()]);
                if ($log->mail_type === 'team_selection_invitation') {
                    \App\Jobs\SendTeamSelectionInvitationEmailJob::dispatch($log->id, $batch->event_id)->afterCommit();
                } else {
                    \App\Jobs\SendInterprovincialTrialInvitationEmailJob::dispatch($log->id, $batch->event_id)->afterCommit();
                }

                return ['queued' => 1, 'duplicate' => false];
            }
            if (($batch->options['source'] ?? null) === 'retry') {
                $this->checkRankingRetry($batch, $actor);
                $log = BulkEmailLog::whereKey($batch->options['log_id'])->lockForUpdate()->firstOrFail();
                abort_unless($log->status === 'failed' && ! $log->sent_at && ! $log->accepted_at && (int) ($log->payload['event_id'] ?? 0) === (int) $batch->event_id && (int) ($log->payload['event_communication_batch_id'] ?? 0) === (int) $batch->options['original_batch_id'], 422);
                $recipient = ['email' => $log->recipient_email, 'name' => $log->recipient_name, 'kind' => $log->payload['recipient_kind'] ?? 'players', 'player_keys' => $log->payload['player_keys'] ?? [], 'subject' => $log->payload['subject'], 'html' => $log->payload['body']];
                abort_unless(hash_equals($batch->fingerprint, hash('sha256', json_encode($recipient, JSON_THROW_ON_ERROR))), 422);
                $log->update(['status' => 'queued', 'queued_at' => now(), 'failed_at' => null, 'error_message' => null, 'retry_actor_id' => $actor->id, 'payload' => [...$log->payload, 'origin_batch_id' => $log->payload['origin_batch_id'] ?? $batch->options['original_batch_id'], 'event_communication_batch_id' => $batch->id]]);
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
            $stats = ['queued' => 0, 'failed' => 0, 'skipped' => 0, 'duplicate' => false];
            $related = $batch;
            if (! empty($batch->options['regional_announcement_id'])) {
                $related = \App\Models\TeamSelectionRegionAnnouncement::where('event_id', $batch->event_id)->where('region_id', $batch->options['region_id'])->findOrFail($batch->options['regional_announcement_id']);
                $related->update(['emailed_at' => now()]);
            }
            foreach ($batch->recipients as $recipient) {
                $result = $this->mailer->dispatch($batch->event->isInterprovincialTrials() ? 'trial_communication' : ($batch->event->isTeam() ? 'team_email' : 'bulk_event_mail'), $related, [$recipient], ['event_id' => $batch->event_id, 'created_by' => $actor->id, 'region_id' => ($batch->options['scope'] ?? null) === 'region' ? $batch->options['region_id'] : null, 'team_id' => ($batch->options['scope'] ?? null) === 'team' ? $batch->options['team_id'] : null, 'event_communication_batch_id' => $batch->id, 'subject' => $recipient['subject'], 'body' => $recipient['html'], 'recipient_kind' => $recipient['kind'], 'player_keys' => $recipient['player_keys'] ?? [], 'from_name' => $actor->name, 'reply_to' => $actor->email, 'manual_retry_only' => true], true);
                foreach (['queued', 'failed', 'skipped'] as $outcome) {
                    $stats[$outcome] += $result[$outcome] ?? 0;
                }
            }

            return $stats;
        });
    }
}
