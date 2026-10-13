<?php

namespace App\Services;

use App\Models\{BulkEmailLog, Event, SelectionPreviewGrant, SelectionPreviewShareBatch, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SelectionPreviewShareService
{
    public function canView(Event $event, User $actor): bool
    {
        return $actor->can('event.manage', $event) || SelectionPreviewGrant::where('event_id', $event->id)->where('user_id', $actor->id)->whereNull('revoked_at')->exists();
    }

    public function share(Event $event, User $actor, array $input): array
    {
        abort_unless($actor->can('event.manage', $event), 403);
        $ids = array_map('intval', $input['recipient_ids']); sort($ids);
        if (count($ids) < 1 || count($ids) > 30 || count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['recipient_ids' => 'Choose between 1 and 30 distinct system accounts.']);
        }
        $age = isset($input['age']) ? (int) $input['age'] : null;
        $channel = $input['channel'] ?? 'email';
        if (! in_array($channel, ['email', 'whatsapp'], true)) throw ValidationException::withMessages(['channel' => 'Choose email or WhatsApp.']);
        $fingerprint = hash('sha256', json_encode($channel === 'email' ? [$ids, $age] : [$ids, $age, 'whatsapp'], JSON_THROW_ON_ERROR));
        $batch = DB::transaction(function () use ($event, $actor, $input, $ids, $age, $fingerprint, $channel) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $existing = SelectionPreviewShareBatch::where('event_id', $event->id)->where('actor_id', $actor->id)->where('request_key', $input['request_key'])->first();
            if ($existing) { abort_unless(hash_equals($existing->fingerprint, $fingerprint), 409, 'This share request was already used for different recipients.'); return $existing; }
            $users = User::whereIn('id', $ids)->get(['id', 'name', 'email']);
            if ($users->count() !== count($ids) || $users->contains(fn ($user) => ! filter_var($user->email, FILTER_VALIDATE_EMAIL))
                || $users->map(fn ($user) => strtolower(trim($user->email)))->unique()->count() !== $users->count()) {
                throw ValidationException::withMessages(['recipient_ids' => 'Choose existing accounts with valid, distinct email addresses.']);
            }
            if ($age !== null) app(TeamSelectionPreviewService::class)->preview($event, $age);
            $batch = SelectionPreviewShareBatch::create(['event_id' => $event->id, 'actor_id' => $actor->id, 'request_key' => $input['request_key'],
                'fingerprint' => $fingerprint, 'recipient_ids' => $ids, 'age' => $age, 'event_name' => $event->name]);
            $content = $this->content($batch);
            foreach ($users as $user) {
                $grant = SelectionPreviewGrant::firstOrNew(['event_id' => $event->id, 'user_id' => $user->id]);
                $grant->granted_by = $actor->id; $grant->revoked_at = null; $grant->save();
                DB::table('selection_preview_access_events')->insert(['grant_id' => $grant->id, 'actor_id' => $actor->id, 'action' => $channel === 'email' ? 'shared' : 'shared_link', 'created_at' => now()]);
                if ($channel === 'whatsapp') continue;
                app(BulkMailDispatcher::class)->dispatch('selection_preview_share', $event, [$user], [
                    'event_id' => $event->id, 'created_by' => $actor->id, 'recipient_kind' => 'system-user', 'recipient_user_id' => $user->id,
                    'preview_share_batch_id' => $batch->id, 'campaign_key' => 'selection-preview:'.$batch->id,
                    ...$content,
                ]);
            }
            return $batch;
        });
        $logs = BulkEmailLog::where('mail_type', 'selection_preview_share')->where('payload->preview_share_batch_id', $batch->id)->get(['status', 'payload']);
        $previewUrl = $this->content($batch)['preview_url'];
        return ['batch_id' => $batch->id, 'recipients' => count($batch->recipient_ids), 'channel' => $channel, 'preview_url' => $previewUrl,
            'whatsapp_url' => $channel === 'whatsapp' ? 'https://wa.me/?text='.rawurlencode('Private selection preview: '.$batch->event_name.' '.$previewUrl.' Sign in with your shared Cape Tennis account to view and discuss selections.') : null,
            'queued' => $logs->filter(fn ($log) => $log->status === 'queued' && ($log->payload['queue_state'] ?? null) === 'enqueued')->count(),
            'prepared' => $logs->filter(fn ($log) => $log->status === 'queued' && ($log->payload['queue_state'] ?? null) !== 'enqueued')->count(),
            'accepted' => $logs->where('status', 'sent')->count(), 'failed' => $logs->where('status', 'failed')->count(),
            'sending' => $logs->where('status', 'sending')->count(), 'skipped' => $logs->where('status', 'skipped')->count(), 'unknown' => $logs->where('status', 'acceptance_unknown')->count()];
    }

    public function content(SelectionPreviewShareBatch $batch): array
    {
        $path = route('backend.selection-preview.show', ['event' => $batch->event_id, 'age' => $batch->age], false);
        $url = rtrim(config('app.url'), '/').$path;
        return ['preview_url' => $url, 'subject' => 'Private selection preview: '.$batch->event_name,
            'body' => '<p>You have been given read-only access to the private selection preview for '.e($batch->event_name).'.</p><p><a href="'.e($url).'">Open Preview Selections</a></p><p>Sign in with the Cape Tennis account receiving this link. You can view the current private preview; this does not grant editing or publication access.</p>'];
    }

    public function authorizedMailable(BulkEmailLog $log): ?\App\Mail\BulkEventMail
    {
        $payload = $log->payload;
        $batch = SelectionPreviewShareBatch::find($payload['preview_share_batch_id'] ?? 0);
        $batchIds = $batch ? array_map('intval', $batch->recipient_ids) : []; sort($batchIds);
        $user = User::find($payload['recipient_user_id'] ?? 0);
        if ($log->mail_type !== 'selection_preview_share' || ! $batch || ! $user || ! hash_equals($batch->fingerprint, hash('sha256', json_encode([$batchIds, $batch->age === null ? null : (int) $batch->age], JSON_THROW_ON_ERROR)))
            || (int) $batch->event_id !== (int) ($payload['event_id'] ?? 0)
            || $log->related_type !== Event::class || (int) $log->related_id !== (int) $batch->event_id
            || ! in_array((int) $user->id, array_map('intval', $batch->recipient_ids), true)
            || strtolower(trim($user->email)) !== strtolower(trim($log->recipient_email))
            || ! SelectionPreviewGrant::where('event_id', $batch->event_id)->where('user_id', $user->id)->whereNull('revoked_at')->exists()) return null;
        $content = $this->content($batch);
        return (new \App\Mail\BulkEventMail($content['subject'], $content['body'], config('mail.from.name', 'Cape Tennis'), config('mail.from.address')))
            ->with('selection_preview_log_id', $log->id);
    }

    public function revoke(Event $event, User $actor, int $userId): void
    {
        abort_unless($actor->can('event.manage', $event), 403);
        DB::transaction(function () use ($event, $actor, $userId) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $grant = SelectionPreviewGrant::where('event_id', $event->id)->where('user_id', $userId)->lockForUpdate()->firstOrFail();
            if ($grant->revoked_at !== null) return;
            $grant->revoked_at = now(); $grant->save();
            DB::table('selection_preview_access_events')->insert(['grant_id' => $grant->id, 'actor_id' => $actor->id, 'action' => 'revoked', 'created_at' => now()]);
        });
    }
}
