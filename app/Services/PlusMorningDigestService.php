<?php

namespace App\Services;

use App\Jobs\SendPlusMorningDigest;
use App\Models\{Announcement, Event, PlusDigestDelivery, User};
use App\Services\Performance\{PlayerPlusEntitlement, PlayerPlusService};
use Illuminate\Support\Facades\{Bus, DB, Schema};

/** Public information only; a Plus purchase never grants an account/player link. */
final class PlusMorningDigestService
{
    public function ready(): bool
    {
        foreach (['plus_digest_runs', 'plus_digest_sources', 'plus_digest_changes', 'plus_digest_accounts', 'plus_digest_seen', 'plus_digest_deliveries'] as $table) {
            if (! Schema::hasTable($table)) return false;
        }
        return true;
    }

    public function eventSnapshot(Event $event): array
    {
        $closesAt = $event->registrationClosesAt()?->timezone('Africa/Johannesburg');
        return ['event_id' => (int) $event->id, 'name' => (string) $event->name,
            'start_date' => $event->start_date?->format('Y-m-d'), 'end_date' => $event->end_date?->format('Y-m-d'),
            'deadline' => $event->deadline, 'registration_closes_at' => $closesAt ? $closesAt->format('Y-m-d H:i').' South African time' : null, 'information' => (string) $event->information,
            'venue_notes' => (string) $event->venue_notes, 'signUp' => (bool) $event->signUp,
            'organizer' => (string) $event->organizer, 'email' => (string) $event->email, 'entry_fee' => $event->entryFee,
            'withdrawal_deadline' => $event->withdrawal_deadline?->toDateTimeString(),
            // Track public metadata, never document contents, private nominees, entries or rosters.
            'documents' => $this->metadata('files', $event->id, ['id', 'name', 'updated_at']),
            'categories' => $this->metadata('category_events', $event->id, ['id', 'category_id', 'entry_fee', 'nominations_published']),
            'venues' => $this->metadata('event_venues', $event->id, ['id', 'venue_id', 'num_courts'])];
    }

    private function metadata(string $table, int $eventId, array $columns): array
    {
        if (! Schema::hasTable($table)) return [];
        $available = array_values(array_intersect($columns, Schema::getColumnListing($table)));
        if (! in_array('id', $available, true)) return [];
        $query = DB::table($table)->where('event_id', $eventId);
        return ['count' => (clone $query)->count(), 'last_updated_at' => in_array('updated_at', $available, true) ? (clone $query)->max('updated_at') : null,
            'rows' => (clone $query)->orderBy('id')->limit(100)->get($available)->map(fn ($row) => (array) $row)->all()];
    }

    public function eventRevision(Event $event): string
    {
        return hash('sha256', json_encode($this->eventSnapshot($event), JSON_THROW_ON_ERROR));
    }

    public function markSeen(User $user, string $key, string $revision): bool
    {
        if (! $this->ready() || ! preg_match('/^announcement:([1-9][0-9]*)$/', $key, $matches)) return false;
        $post = Announcement::whereKey($matches[1])->whereHas('event', fn ($q) => $q->visibleTo(null))->first();
        if (! $post || ! hash_equals($post->readRevision(), $revision)) return false;
        $this->saveSeen($user, $key, $revision);
        return true;
    }

    public function markEventSeen(User $user, Event $event): void
    {
        if ($this->ready() && Event::visibleTo(null)->whereKey($event->id)->exists()) {
            $this->saveSeen($user, 'event:'.$event->id, $this->eventRevision($event));
        }
    }

    private function saveSeen(User $user, string $key, string $revision): void
    {
        DB::table('plus_digest_seen')->updateOrInsert(['user_id' => $user->id, 'key' => $key],
            ['revision' => $revision, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function seenRevisions(User $user, array $keys): array
    {
        if (! $this->ready()) return [];
        return DB::table('plus_digest_seen')->where('user_id', $user->id)->whereIn('key', array_slice($keys, 0, 100))
            ->pluck('revision', 'key')->all();
    }

    /** One bounded scan page per host tick; initial installation is a silent baseline. */
    public function scan(int $limit = 100): bool
    {
        if (! $this->ready()) return false;
        return DB::transaction(function () use ($limit): bool {
            DB::table('plus_digest_runs')->insertOrIgnore(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $run = DB::table('plus_digest_runs')->where('id', 1)->lockForUpdate()->first();
            $pageSize = max(1, min(200, $limit));
            $events = $run->events_complete ? collect() : Event::visibleTo(null)->where('id', '>', $run->event_cursor)->orderBy('id')->limit($pageSize)->get();
            foreach ($events as $event) {
                $this->record('event:'.$event->id, $event->id, $this->eventRevision($event), $this->eventSnapshot($event), $run->baselined);
            }
            $posts = $run->announcements_complete ? collect() : Announcement::whereHas('event', fn ($q) => $q->visibleTo(null))
                ->with('event')->where('id', '>', $run->announcement_cursor)->orderBy('id')->limit($pageSize)->get();
            foreach ($posts as $post) $this->record('announcement:'.$post->id, $post->event_id, $post->readRevision(),
                ['event_id' => (int) $post->event_id, 'name' => (string) $post->event->name, 'title' => (string) $post->title,
                    'excerpt' => \Illuminate\Support\Str::limit(strip_tags((string) $post->message), 500)], $run->baselined);
            $eventsComplete = $run->events_complete || $events->count() < $pageSize;
            $postsComplete = $run->announcements_complete || $posts->count() < $pageSize;
            $complete = $eventsComplete && $postsComplete;
            DB::table('plus_digest_runs')->where('id', 1)->update(['event_cursor' => $complete ? 0 : ($events->last()?->id ?? $run->event_cursor),
                'announcement_cursor' => $complete ? 0 : ($posts->last()?->id ?? $run->announcement_cursor),
                'events_complete' => ! $complete && $eventsComplete, 'announcements_complete' => ! $complete && $postsComplete,
                'baselined' => $complete || $run->baselined, 'scan_completed_at' => $complete ? now() : $run->scan_completed_at, 'updated_at' => now()]);
            return $complete;
        }, 3);
    }

    private function record(string $key, int $eventId, string $revision, array $snapshot, bool $notify): void
    {
        $previous = DB::table('plus_digest_sources')->where('key', $key)->first();
        if ($previous && $previous->revision === $revision) return;
        DB::table('plus_digest_sources')->updateOrInsert(['key' => $key], ['event_id' => $eventId, 'revision' => $revision,
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        if ($notify) DB::table('plus_digest_changes')->insert(['key' => $key, 'event_id' => $eventId, 'revision' => $revision,
            'kind' => str_starts_with($key, 'announcement:') ? 'announcement' : ($previous ? 'updated_event' : 'new_event'),
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function eligible(User $user): bool
    {
        return app(PlusDigestPreferences::class)->allows($user) && PlayerPlusService::ready() && filter_var($user->email, FILTER_VALIDATE_EMAIL)
            && app(PlayerPlusEntitlement::class)->discovery($user);
    }

    /** Called after a completed morning scan, never before the 06:00 SAST boundary. */
    public function prepare(int $limit = 50): int
    {
        if (! config('plus_morning_digest.delivery_enabled') || ! $this->ready()) return 0;
        $local = now('Africa/Johannesburg');
        $run = DB::table('plus_digest_runs')->where('id', 1)->first();
        if ($local->format('H:i') < '06:00' || ! $run?->scan_completed_at
            || \Carbon\Carbon::parse($run->scan_completed_at)->lt($local->copy()->startOfDay()->setTime(6, 0))) return 0;
        $date = $local->toDateString();
        $active = app(PlayerPlusEntitlement::class)->activePlayers();
        $users = User::where(function ($q) use ($active) {
            $q->whereIn('id', (clone $active)->whereNotNull('userId')->select('userId'))
                ->orWhereIn('id', DB::table('user_players')->whereIn('player_id', (clone $active)->select('players.id'))->select('user_id'));
        })->whereNotIn('id', DB::table('plus_digest_accounts')->where('prepared_date', $date)->select('user_id'))
            ->orderByRaw('COALESCE((SELECT updated_at FROM plus_digest_accounts WHERE user_id = users.id), ?) ASC', ['2000-01-01'])
            ->orderBy('id')->limit(max(1, min(100, $limit)))->get();
        $count = 0;
        foreach ($users as $user) $count += DB::transaction(function () use ($user, $date): int {
            $account = DB::table('plus_digest_accounts')->where('user_id', $user->id)->lockForUpdate()->first();
            $maximum = (int) DB::table('plus_digest_changes')->max('id');
            if (! $account) {
                DB::table('plus_digest_accounts')->insertOrIgnore(['user_id' => $user->id, 'cursor' => $maximum,
                    'prepared_date' => $date, 'created_at' => now(), 'updated_at' => now()]);
                app(PlusDigestPreferences::class)->establishResumeBaseline($user);
                return 0;
            }
            if ($account->prepared_date === $date) return 0;
            // A process may die after claiming a transport. Its range is uncertain,
            // so consume it atomically rather than automatically sending it again.
            foreach (PlusDigestDelivery::where('user_id', $user->id)->where('status', 'sending')->lockForUpdate()->get() as $uncertain) {
                $uncertain->update(['status' => 'acceptance_unknown']); $this->consume($uncertain);
            }
            $account = DB::table('plus_digest_accounts')->where('user_id', $user->id)->first();
            if (! $this->eligible($user)) {
                DB::table('plus_digest_accounts')->where('user_id', $user->id)->update(['cursor' => $maximum, 'prepared_date' => $date, 'updated_at' => now()]);
                return 0;
            }
            if (app(PlusDigestPreferences::class)->establishResumeBaseline($user)) {
                DB::table('plus_digest_accounts')->where('user_id', $user->id)->update(['cursor' => $maximum, 'prepared_date' => $date, 'updated_at' => now()]);
                return 0;
            }
            $changes = DB::table('plus_digest_changes')->where('id', '>', $account->cursor)->orderBy('id')->limit(100)->get();
            $items = [];
            $cursor = $account->cursor;
            foreach ($changes as $change) {
                $cursor = $change->id;
                $item = ['key' => $change->key, 'revision' => $change->revision, 'kind' => $change->kind, 'snapshot' => json_decode($change->snapshot, true, flags: JSON_THROW_ON_ERROR)];
                if ($item['kind'] === 'updated_event' && DB::table('plus_digest_changes')->where('key', $change->key)
                    ->where('kind', 'new_event')->where('id', '>', $account->cursor)->where('id', '<=', $change->id)->exists()) $item['kind'] = 'new_event';
                if ($this->itemCurrent($user, $item)) $items[$change->key] = $item;
                if (count($items) >= 25) break;
            }
            if ($items) {
                if (config('queue.connections.database.connection') && config('queue.connections.database.connection') !== config('database.default')) {
                    throw new \LogicException('Morning digest requires the application database queue.');
                }
                $delivery = PlusDigestDelivery::create(['user_id' => $user->id, 'digest_date' => $date,
                    'recipient' => mb_strtolower(trim($user->email)), 'items' => array_values($items), 'end_cursor' => $cursor, 'queued_at' => now()]);
                Bus::dispatch((new SendPlusMorningDigest($delivery->id))->onConnection('database')->beforeCommit());
            }
            DB::table('plus_digest_accounts')->where('user_id', $user->id)->update(['cursor' => $items ? $account->cursor : $cursor, 'prepared_date' => $date, 'updated_at' => now()]);
            return $items ? 1 : 0;
        }, 3);
        return $count;
    }

    public function itemCurrent(User $user, array $item): bool
    {
        if (app(PlusEventUpdateService::class)->alreadyDelivered(mb_strtolower(trim((string) $user->email)), $item)) return false;
        if (! app(PlusDigestPreferences::class)->permitsChange($user, $item['key'], $item['revision'])) return false;
        $event = Event::visibleTo(null)->find($item['snapshot']['event_id']);
        if (! $event) return false;
        if (str_starts_with($item['key'], 'announcement:')) {
            $post = Announcement::where('event_id', $event->id)->find(substr($item['key'], 13));
            if (! $post || $post->readRevision() !== $item['revision']) return false;
            if ($item['snapshot']['name'] !== (string) $event->name || $item['snapshot']['title'] !== (string) $post->title
                || ($item['snapshot']['excerpt'] ?? null) !== \Illuminate\Support\Str::limit(strip_tags((string) $post->message), 500)) return false;
        } elseif ($item['key'] !== 'event:'.$event->id || ! in_array($item['kind'], ['new_event', 'updated_event'], true)
            || $this->eventRevision($event) !== $item['revision'] || $this->eventSnapshot($event) !== $item['snapshot']) return false;
        return ! DB::table('plus_digest_seen')->where('user_id', $user->id)->where('key', $item['key'])->where('revision', $item['revision'])->exists();
    }

    public function current(PlusDigestDelivery $delivery): bool
    {
        $user = User::find($delivery->user_id);
        return config('plus_morning_digest.delivery_enabled') && $user && $this->eligible($user)
            && mb_strtolower(trim($user->email)) === $delivery->recipient && count($delivery->items) > 0
            && collect($delivery->items)->every(fn ($item) => $this->itemCurrent($user, $item));
    }

    public function consume(PlusDigestDelivery $delivery): void
    {
        DB::table('plus_digest_accounts')->where('user_id', $delivery->user_id)->where('cursor', '<', $delivery->end_cursor)
            ->update(['cursor' => $delivery->end_cursor, 'updated_at' => now()]);
    }

    /** Duplicate unclaimed jobs are safe: delivery claim is locked exactly once. */
    public function recoverPending(int $limit = 50): void
    {
        if (! $this->ready() || ! config('plus_morning_digest.delivery_enabled')) return;
        foreach (PlusDigestDelivery::whereDate('digest_date', now('Africa/Johannesburg')->toDateString())->where('status', 'pending')
            ->where('queued_at', '<=', now()->subMinutes(15))->orderBy('id')->limit($limit)->get() as $candidate) {
            DB::transaction(function () use ($candidate): void {
                $delivery = PlusDigestDelivery::lockForUpdate()->find($candidate->id);
                if (! $delivery || $delivery->status !== 'pending' || $delivery->queued_at?->gt(now()->subMinutes(15))) return;
                $delivery->update(['queued_at' => now()]);
                Bus::dispatch((new SendPlusMorningDigest($delivery->id))->onConnection('database')->beforeCommit());
            }, 3);
        }
    }
}
