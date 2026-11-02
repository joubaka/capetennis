<?php

namespace App\Services\Performance;

use App\Models\{AuditEvent, Player, PlayerPlusAccessOverride, User};
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Illuminate\Validation\ValidationException;

class PlayerPlusManagement
{
    public static function ready(): bool
    {
        return PlayerPlusService::ready() && Schema::hasTable('player_plus_access_overrides');
    }

    public function history(Player $player)
    {
        return AuditEvent::where('subject_type', Player::class)->where('subject_id', (string) $player->id)
            ->where('action', 'player-plus.access-changed')->latest('id');
    }

    public function change(Player $player, User $actor, array $input): void
    {
        abort_unless($actor->hasRole('super-user'), 403);
        abort_unless(self::ready(), 503);
        $data = Validator::make($input, [
            'action' => ['required', 'in:grant,suspend,restore,inherit'],
            'version' => ['required', 'integer', 'min:0'], 'request_token' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:10', 'max:1500'], 'confirm' => ['accepted'],
            'starts_at' => ['exclude_unless:action,grant', 'required', 'date_format:Y-m-d\TH:i', 'after_or_equal:2000-01-01'],
            'ends_at' => ['exclude_unless:action,grant', 'required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
        ])->validate();
        if (mb_strlen(trim($data['reason'])) < 10) throw ValidationException::withMessages(['reason' => 'Enter a meaningful reason of at least 10 characters.']);
        if ($data['action'] === 'grant' && (\Carbon\CarbonImmutable::parse($data['ends_at'])->lte(now()) || \Carbon\CarbonImmutable::parse($data['ends_at'])->year > 2037)) {
            throw ValidationException::withMessages(['ends_at' => 'Choose a future end time before 2038.']);
        }
        DB::transaction(function () use ($player, $actor, $data): void {
            Player::whereKey($player->id)->lockForUpdate()->firstOrFail();
            if ($this->history($player)->where('actor_id', $actor->id)->where('metadata->operation_id', hash('sha256', $data['request_token']))->exists()) return;
            $override = PlayerPlusAccessOverride::firstOrNew(['player_id' => $player->id]);
            if ((int) ($override->version ?? 0) !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'Access changed since this page was opened. Reload and review before saving.']);
            }
            $before = $override->only(['mode', 'starts_at', 'ends_at', 'version']);
            if ($data['action'] === 'grant') {
                $override->fill(['mode' => 'grant', 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at']]);
            } elseif ($data['action'] === 'suspend') {
                $override->mode = 'suspended';
            } elseif ($data['action'] === 'restore') {
                $override->mode = $override->starts_at && $override->ends_at ? 'grant' : 'inherit';
            } else {
                $override->fill(['mode' => 'inherit', 'starts_at' => null, 'ends_at' => null]);
            }
            $override->version = (int) ($override->version ?? 0) + 1;
            $override->save();
            $audit = app(AuditWriter::class)->record(['category' => 'business', 'action' => 'player-plus.access-changed',
                'subject' => $player, 'actor' => $actor, 'reason' => trim($data['reason']), 'before' => $before,
                'after' => $override->only(['mode', 'starts_at', 'ends_at', 'version']),
                'metadata' => ['operation_id' => hash('sha256', $data['request_token']), 'operation' => $data['action'], 'financial_state_changed' => false]], true);
            if (! $audit) throw new \RuntimeException('Access could not be audited. No change was saved.');
        }, 3);
    }
}
