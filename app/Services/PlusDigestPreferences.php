<?php

namespace App\Services;

use App\Models\{PlusDigestDelivery, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Schema};

final class PlusDigestPreferences
{
    public function ready(): bool { return Schema::hasTable('plus_digest_preferences'); }

    public function forUser(User $user): object
    {
        return ($this->ready() ? DB::table('plus_digest_preferences')->where('user_id', $user->id)->first() : null)
            ?? (object) ['enabled' => true, 'snoozed_until' => null, 'resume_after' => null, 'needs_baseline' => false];
    }

    public function allows(User $user): bool
    {
        if (! $this->ready()) return false;
        $preference = $this->forUser($user);
        return (bool) $preference->enabled && (! $preference->snoozed_until || Carbon::parse($preference->snoozed_until)->lte(now()));
    }

    public function update(User $user, string $choice): void
    {
        abort_unless($this->ready(), 503, 'Notification preferences are not available yet.');
        abort_unless(in_array($choice, ['daily', 'off', 'snooze7', 'snooze30'], true), 422);
        DB::transaction(function () use ($user, $choice): void {
            $until = match ($choice) { 'snooze7' => now()->addDays(7), 'snooze30' => now()->addDays(30), default => null };
            $previous = DB::table('plus_digest_preferences')->where('user_id', $user->id)->lockForUpdate()->first();
            // Repeated saves of an already active daily preference do not discard new updates.
            $resuming = $choice === 'daily' && $previous && (! $previous->enabled || $previous->needs_baseline
                || ($previous->snoozed_until && Carbon::parse($previous->snoozed_until)->isFuture()));
            DB::table('plus_digest_preferences')->updateOrInsert(['user_id' => $user->id], [
                'enabled' => $choice !== 'off', 'snoozed_until' => $until,
                'resume_after' => $until ?? ($resuming || $choice === 'off' ? now() : $previous?->resume_after),
                'needs_baseline' => $choice !== 'daily' || $resuming || ($previous?->needs_baseline ?? false),
                'created_at' => $previous?->created_at ?? now(), 'updated_at' => now(),
            ]);
            if ($choice !== 'daily' && Schema::hasTable('plus_digest_deliveries')) {
                PlusDigestDelivery::where('user_id', $user->id)->where('status', 'pending')->update(['status' => 'suppressed']);
            }
        }, 3);
    }

    public function permitsChange(User $user, string $key, string $revision): bool
    {
        if (! $this->allows($user)) return false;
        $preference = $this->forUser($user);
        if ($preference->needs_baseline) return false;
        $cutoff = $preference->resume_after;
        return ! $cutoff || DB::table('plus_digest_changes')->where('key', $key)->where('revision', $revision)
            ->where('created_at', '>', $cutoff)->exists();
    }

    /** Capture time cannot prove an edit occurred after a pause; resume silently. */
    public function establishResumeBaseline(User $user): bool
    {
        if (! $this->ready()) return false;
        return DB::transaction(function () use ($user): bool {
            $preference = DB::table('plus_digest_preferences')->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $preference || ! $preference->enabled || ! $preference->needs_baseline
                || ($preference->snoozed_until && Carbon::parse($preference->snoozed_until)->isFuture())) return false;
            DB::table('plus_digest_preferences')->where('user_id', $user->id)->update([
                'needs_baseline' => false, 'resume_after' => now(), 'updated_at' => now(),
            ]);
            return true;
        }, 3);
    }
}
