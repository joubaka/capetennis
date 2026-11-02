<?php

namespace App\Services\Assistant;

use App\Models\{CapesterSupportRequest, Event, User};
use Illuminate\Support\Facades\{DB, Gate};

final class CapesterSupportQueue
{
    public function submit(User $actor, Event $event, array $data): CapesterSupportRequest
    {
        return DB::transaction(function () use ($actor, $event, $data) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $existing = CapesterSupportRequest::where('uuid', $data['submission_uuid'])->first();
            if ($existing) {
                abort_unless((int) $existing->user_id === (int) $actor->id && (int) $existing->event_id === (int) $event->id
                    && $existing->kind === $data['kind'] && $existing->capester_description === $data['capester_description'], 409);
                return $existing;
            }
            abort_if(CapesterSupportRequest::where('user_id', $actor->id)->whereIn('status', ['pending', 'leased'])->count() >= 20, 429, 'Your support queue is full.');
            return CapesterSupportRequest::create(['uuid' => $data['submission_uuid'], 'event_id' => $event->id,
                'user_id' => $actor->id, 'kind' => $data['kind'], 'capester_description' => $data['capester_description'], 'status' => 'pending']);
        });
    }

    public function claim(User $actor, int $tokenId): ?array
    {
        return DB::transaction(function () use ($actor, $tokenId) {
            $candidates = CapesterSupportRequest::where('user_id', $actor->id)
                ->where(function ($query) {
                    $query->where('status', 'pending')->orWhere(function ($query) {
                        $query->where('status', 'leased')->where('lease_expires_at', '<=', now());
                    });
                })->when(! app()->environment(['local', 'testing']), fn ($query) => $query->where('kind', 'support'))
                ->orderBy('id')->limit(20)->lockForUpdate()->get();
            foreach ($candidates as $item) {
                $event = Event::find($item->event_id);
                if (! $event || ! Gate::forUser($actor)->allows('event.manage', $event)) {
                    continue;
                }
                $token = bin2hex(random_bytes(32));
                $item->update(['status' => 'leased', 'receiver_token_id' => $tokenId,
                    'lease_token_hash' => hash('sha256', $token), 'lease_expires_at' => now()->addMinutes(5)]);
                return ['uuid' => $item->uuid, 'kind' => $item->kind, 'event_id' => (int) $item->event_id,
                    'capester_description' => $item->capester_description, 'token' => $token];
            }
            return null;
        });
    }

    public function acknowledge(User $actor, int $tokenId, string $uuid, string $token, string $result): void
    {
        DB::transaction(function () use ($actor, $tokenId, $uuid, $token, $result) {
            $item = CapesterSupportRequest::where('uuid', $uuid)->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
            $event = Event::findOrFail($item->event_id);
            abort_unless(Gate::forUser($actor)->allows('event.manage', $event), 403);
            abort_if($item->kind === 'hello' && ! app()->environment(['local', 'testing']), 403);
            abort_unless((int) $item->receiver_token_id === $tokenId
                && hash_equals((string) $item->lease_token_hash, hash('sha256', $token)), 409);
            abort_unless(($item->kind === 'support' && $result === 'received')
                || ($item->kind === 'hello' && in_array($result, ['hello_passed', 'hello_failed'], true)), 422);
            if ($item->acknowledged_at) {
                abort_unless($item->status === $result, 409);
                return;
            }
            abort_unless($item->lease_expires_at?->isFuture(), 409);
            abort_unless($item->status === 'leased', 409);
            $item->update(['status' => $result, 'acknowledged_at' => now()]);
        });
    }
}
