<?php
namespace App\Services;

use App\Models\{Announcement, CategoryEventRegistration, Draw, Event, Player, PlusEventUpdateDelivery, PlusEventUpdateState};
use App\Services\Performance\{PlayerPlusEntitlement, PlayerPlusService};
use App\Services\TeamSelection\TeamSelectionContactService;
use Illuminate\Support\Facades\{Bus, DB, Schema};

/** Entered-event public updates; never builds a historical campaign on installation. */
class PlusEventUpdateService
{
    public static function same(array $first, array $second): bool
    {
        $normalize = function (array $value) use (&$normalize): array {
            foreach ($value as &$item) if (is_array($item)) $item = $normalize($item);
            if (! array_is_list($value)) ksort($value);
            return $value;
        };
        return $normalize(json_decode(json_encode($first, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR))
            === $normalize(json_decode(json_encode($second, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
    }
    public function reconcile(int $limit = 50): void
    {
        if (! $this->ready()) return;
        $cursor = (int) \Illuminate\Support\Facades\Cache::get('plus-event-update-cursor', 0);
        $events = Event::where('id', '>', $cursor)->orderBy('id')->limit($limit)->get();
        foreach ($events as $event) {
            $this->record($event);
            if ($state = PlusEventUpdateState::where('event_id', $event->id)->first()) $this->recipients($event, $state);
        }
        \Illuminate\Support\Facades\Cache::forever('plus-event-update-cursor', $events->count() < $limit ? 0 : $events->last()->id);
    }
    public function ready(): bool
    {
        return Schema::hasTable('plus_event_update_states') && Schema::hasTable('plus_event_update_deliveries');
    }

    public function snapshot(Event $event): array
    {
        if (! Event::visibleTo(null)->whereKey($event->id)->exists()) return ['visible' => false];
        // Oversize projections fail closed instead of silently announcing incomplete content.
        if (Draw::where('event_id', $event->id)->count() > 100 || Announcement::where('event_id', $event->id)->count() > 100) return ['visible' => false];
        $draws = [];
        foreach (Draw::where('event_id', $event->id)->where('published', true)->orderBy('id')->get() as $draw) {
            $draws[$draw->id] = app(PlusPublicationNotificationService::class)->snapshot($draw, $event);
        }
        $announcements = [];
        foreach (Announcement::where('event_id', $event->id)->orderBy('id')->get() as $post) {
            $announcements[$post->id] = ['revision' => $post->readRevision(), 'title' => (string) $post->title,
                'excerpt' => \Illuminate\Support\Str::limit(strip_tags((string) $post->message), 500)];
        }
        return json_decode(json_encode(['visible' => true, 'information' => app(PlusMorningDigestService::class)->eventSnapshot($event),
            'information_revision' => app(PlusMorningDigestService::class)->eventRevision($event),
            'draw_revision' => hash('sha256', json_encode($draws, JSON_THROW_ON_ERROR)),
            'announcement_revision' => hash('sha256', json_encode($announcements, JSON_THROW_ON_ERROR)),
            'announcements' => $announcements, 'draws' => $draws], JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }

    public function record(Event $event, bool $baseline = false): void
    {
        if (! $this->ready()) return;
        DB::transaction(function () use ($event, $baseline) {
            $event = Event::whereKey($event->id)->lockForUpdate()->first();
            if (! $event) return;
            $state = PlusEventUpdateState::where('event_id', $event->id)->lockForUpdate()->first();
            if ($baseline && $state) return;
            $snapshot = $this->snapshot($event);
            if (! $state) {
                PlusEventUpdateState::create(['event_id' => $event->id, 'snapshot' => $snapshot, 'changes' => [], 'prepared_at' => now()]);
                return;
            }
            if ($this->same($state->snapshot, $snapshot)) return;
            $changes = $state->prepared_at ? [] : $state->changes;
            foreach (PlusEventUpdateDelivery::where('state_id', $state->id)->where('status', 'pending')->get(['changes']) as $pending) {
                foreach ($pending->changes as $kind => $value) {
                    $changes[$kind] = $kind === 'announcements' ? array_values(array_unique(array_merge($changes[$kind] ?? [], $value)))
                        : (($changes[$kind] ?? false) || $value);
                }
            }
            foreach (['information', 'announcements', 'draws'] as $kind) {
                if (! $this->same($state->snapshot[$kind] ?? [], $snapshot[$kind] ?? [])) {
                    if ($kind === 'announcements') {
                        $changed = array_keys(array_filter($snapshot['announcements'] ?? [], fn ($post, $id) => ! $this->same($state->snapshot['announcements'][$id] ?? [], $post), ARRAY_FILTER_USE_BOTH));
                        $changes[$kind] = array_values(array_unique(array_merge(is_array($changes[$kind] ?? null) ? $changes[$kind] : [], $changed)));
                        // Deletions remain a meaningful public change without repeating older announcements.
                        $changes['announcement_removed'] = ($changes['announcement_removed'] ?? false)
                            || count($state->snapshot['announcements'] ?? []) > count($snapshot['announcements'] ?? []);
                    } else $changes[$kind] = true;
                }
            }
            $visible = $snapshot['visible'];
            $state->update(['generation' => $state->generation + 1, 'snapshot' => $snapshot,
                'changes' => $visible ? $changes : [], 'changed_at' => now(),
                'due_at' => $visible && $changes ? now()->addMinutes(10) : null, 'prepared_at' => null]);
            PlusEventUpdateDelivery::where('state_id', $state->id)->where('status', 'pending')->update(['status' => 'superseded']);
        }, 3);
    }

    public function participants(Event $event): array
    {
        $ids = collect();
        CategoryEventRegistration::active()->whereNull('withdrawn_at')->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))
            ->with('registration.players')->chunkById(100, function ($entries) use (&$ids) {
                foreach ($entries as $entry) $ids = $ids->concat($entry->registration?->players->pluck('id') ?? []);
            });
        // Reuse the canonical active team/competition-replacement roster projection.
        Draw::where('event_id', $event->id)->chunkById(50, function ($draws) use (&$ids) {
            foreach ($draws as $draw) $ids = $ids->concat(app(PlusPublicationNotificationService::class)->participants($draw));
        });
        \App\Models\Team::whereHas('category', fn ($q) => $q->where('event_id', $event->id))->chunkById(50, function ($teams) use (&$ids) {
            foreach ($teams as $team) {
                $roster = app(TeamDrawSideResolver::class)->activeRoster($team);
                $ids = $ids->concat($roster->team_players->pluck('player_id'))->concat($roster->team_players_no_profile->pluck('player_profile')->filter());
            }
        });
        return $ids->unique()->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    public function recipients(Event $event, PlusEventUpdateState $state): array
    {
        if (! PlayerPlusService::ready() || ! app(PlusDigestPreferences::class)->ready()) return [];
        $recipients = []; $denied = [];
        app(PlayerPlusEntitlement::class)->activePlayers()->whereIn('players.id', $this->participants($event))->with(['user', 'users', 'plusMembership', 'plusOverride'])
            ->chunkById(100, function ($players) use (&$recipients, &$denied, $state) {
                foreach ($players as $player) {
                    $membership = $player->plusMembership;
                    $override = $player->plusOverride;
                    $starts = collect([$membership && $membership->starts_at <= now() && $membership->ends_at > now()
                        && $membership->order()->verifiedSettlement()->where('player_id', $player->id)->exists() ? $membership->starts_at : null,
                        $override?->mode === 'grant' && $override->starts_at <= now() && $override->ends_at > now() ? $override->starts_at : null])->filter();
                    if ($state->changed_at && $starts->isNotEmpty() && $starts->every(fn ($date) => \Illuminate\Support\Carbon::parse($date)->gt($state->changed_at))) continue;
                    $users = $player->users->concat($player->user ? [$player->user] : [])->unique('id');
                    if ($users->isEmpty()) continue;
                    $allowed = $users->filter(fn ($user) => app(PlusDigestPreferences::class)->allowsEventUpdate($user, $state));
                    foreach ($users->diff($allowed) as $user) $denied[mb_strtolower(trim((string) $user->email))] = true;
                    foreach (app(TeamSelectionContactService::class)->emails($player) as $email) {
                        $owners = $users->filter(fn ($user) => mb_strtolower(trim((string) $user->email)) === $email);
                        if ($owners->isNotEmpty() ? $owners->count() === $owners->intersect($allowed)->count() : $allowed->count() === $users->count()) {
                            $recipients[$email][] = (int) $player->id;
                        }
                    }
                }
            });
        foreach (array_keys($denied) as $email) unset($recipients[$email]);
        foreach ($recipients as &$ids) { $ids = array_values(array_unique($ids)); sort($ids); }
        ksort($recipients);
        return $recipients;
    }

    public function prepareDue(int $limit = 50): int
    {
        if (! config('plus_event_updates.delivery_enabled') || ! $this->ready()) return 0;
        $count = 0;
        foreach (PlusEventUpdateState::whereNotNull('due_at')->where('due_at', '<=', now())->whereNull('prepared_at')->orderBy('id')->limit($limit)->pluck('id') as $id) {
            $count += DB::transaction(function () use ($id) {
                $candidate = PlusEventUpdateState::find($id);
                $event = $candidate ? Event::whereKey($candidate->event_id)->lockForUpdate()->first() : null;
                $state = PlusEventUpdateState::lockForUpdate()->find($id);
                if (! $event || ! $state || $state->prepared_at || ! $state->due_at || $state->due_at->isFuture()) return 0;
                if (! $this->same($this->snapshot($event), $state->snapshot)) { $this->record($event); return 0; }
                $created = 0;
                foreach ($this->recipients($event, $state) as $recipient => $ids) {
                    $changes = $this->changesFor($state, $recipient, playerIds: $ids);
                    if (! array_filter($changes)) continue;
                    $delivery = PlusEventUpdateDelivery::firstOrCreate(['deduplication_key' => hash('sha256', $state->id.'|'.$state->generation.'|'.$recipient)],
                        ['state_id' => $state->id, 'generation' => $state->generation, 'recipient' => $recipient, 'player_ids' => $ids,
                            'snapshot' => $state->snapshot, 'changes' => $changes]);
                    if (! $delivery->wasRecentlyCreated) continue;
                    if (config('queue.connections.database.connection') && config('queue.connections.database.connection') !== DB::getDefaultConnection()) throw new \LogicException('Event updates require the application database queue.');
                    Bus::dispatch((new \App\Jobs\SendPlusEventUpdate($delivery->id))->onConnection('database')->beforeCommit()); $created++;
                }
                $state->update(['prepared_at' => now()]); return $created;
            }, 3);
        }
        return $count;
    }

    public function current(PlusEventUpdateDelivery $delivery): bool
    {
        if (! config('plus_event_updates.delivery_enabled') || ! $this->ready()) return false;
        $state = PlusEventUpdateState::find($delivery->state_id);
        $event = $state ? Event::find($state->event_id) : null;
        return $event && $state->prepared_at && $state->due_at && ! $state->due_at->isFuture()
            && $state->generation === $delivery->generation && $this->same($state->snapshot, $delivery->snapshot)
            && $this->same($this->changesFor($state, $delivery->recipient, $delivery->id, $delivery->player_ids), $delivery->changes)
            && $this->same($this->snapshot($event), $delivery->snapshot)
            && (bool) array_intersect($this->recipients($event, $state)[$delivery->recipient] ?? [], $delivery->player_ids);
    }

    public function changesFor(PlusEventUpdateState $state, string $recipient, ?int $exclude = null, array $playerIds = []): array
    {
        $changes = $state->changes;
        $controllers = collect(); $addressOwners = collect();
        foreach (Player::whereIn('id', $playerIds)->with(['user', 'users'])->get() as $player) {
            $users = $player->users->concat($player->user ? [$player->user] : [])->unique('id');
            $controllers = $controllers->concat($users);
            $addressOwners = $addressOwners->concat($users->filter(fn ($user) => mb_strtolower(trim((string) $user->email)) === $recipient));
        }
        $controllerIds = ($addressOwners->isNotEmpty() ? $addressOwners : $controllers)->pluck('id')->unique();
        $baselines = DB::table('plus_event_update_baselines')->where('event_id', $state->event_id)->whereIn('user_id', $controllerIds)->pluck('snapshot')
            ->map(fn ($value) => json_decode($value, true, flags: JSON_THROW_ON_ERROR));
        // A resumed/new account receives only revisions newer than its own silent baseline.
        $alreadyBaseline = fn ($key, $value) => $baselines->isNotEmpty() && $baselines->every(fn ($snapshot) => data_get($snapshot, $key) === $value);
        $accepted = fn () => PlusEventUpdateDelivery::where('state_id', $state->id)->where('recipient', $recipient)
            ->when($exclude, fn ($q) => $q->where('id', '!=', $exclude))->whereIn('status', ['sending', 'sent', 'acceptance_unknown']);
        foreach (['information' => 'information_revision', 'draws' => 'draw_revision', 'announcement_removed' => 'announcement_revision'] as $kind => $revision) {
            if (! empty($changes[$kind]) && ($alreadyBaseline($revision, $state->snapshot[$revision] ?? null)
                || $accepted()->where('changes->'.$kind, true)->where('snapshot->'.$revision, $state->snapshot[$revision] ?? null)->exists())) unset($changes[$kind]);
        }
        if (isset($changes['announcements'])) {
            $changes['announcements'] = array_values(array_filter($changes['announcements'], function ($id) use ($accepted, $state, $alreadyBaseline) {
                if (! isset($state->snapshot['announcements'][$id])) return false;
                if ($alreadyBaseline('announcements.'.$id.'.revision', $state->snapshot['announcements'][$id]['revision'])) return false;
                return ! $accepted()->where('snapshot->announcements->'.$id.'->revision', $state->snapshot['announcements'][$id]['revision'])
                    ->whereJsonContains('changes->announcements', $id)->exists();
            }));
            if (! $changes['announcements']) unset($changes['announcements']);
        }
        return array_filter($changes);
    }

    public function alreadyDelivered(string $recipient, array $item): bool
    {
        if (! $this->ready()) return false;
        $stateId = PlusEventUpdateState::where('event_id', $item['snapshot']['event_id'])->value('id');
        if (! $stateId) return false;
        $query = PlusEventUpdateDelivery::where('state_id', $stateId)->where('recipient', $recipient)
            ->whereIn('status', ['sending', 'sent', 'acceptance_unknown']);
        if (str_starts_with($item['key'], 'announcement:')) {
            $id = (int) substr($item['key'], 13);
            return $query->where('snapshot->announcements->'.$id.'->revision', $item['revision'])
                ->whereJsonContains('changes->announcements', $id)->exists();
        }
        return $query->where('snapshot->information_revision', $item['revision'])->where('changes->information', true)->exists();
    }
}
