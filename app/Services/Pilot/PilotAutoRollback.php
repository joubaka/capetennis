<?php

namespace App\Services\Pilot;

use App\Models\Draw;
use App\Models\PilotDrawApproval;
use App\Models\User;
use App\Services\PlatformAuditLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PilotAutoRollbackNotification;

/**
 * PilotAutoRollback
 *
 * Triggered when alert thresholds are breached on a canonical pilot draw.
 * Actions:
 *   1. Downgrade draw.engine_mode from canonical → hybrid
 *   2. Revoke the PilotDrawApproval record
 *   3. Write a platform audit log entry
 *   4. Notify all super-users via notification
 */
class PilotAutoRollback
{
    /**
     * Evaluate all approved pilot draws and auto-rollback any that breach
     * alert thresholds. Returns the list of draw IDs that were rolled back.
     *
     * @return int[]
     */
    public static function evaluateAll(): array
    {
        $snapshot    = PilotMonitor::snapshot();
        $rolledBack  = [];

        foreach ($snapshot['alerts'] as $drawId => $alertMessages) {
            if (static::rollback($drawId, implode('; ', $alertMessages))) {
                $rolledBack[] = $drawId;
            }
        }

        return $rolledBack;
    }

    /**
     * Immediately downgrade a single draw to hybrid and revoke its approval.
     */
    public static function rollback(int $drawId, string $reason): bool
    {
        $draw = Draw::find($drawId);
        if (! $draw) {
            Log::error('[PilotAutoRollback] Draw not found', ['draw_id' => $drawId]);
            return false;
        }

        if ($draw->engine_mode !== 'canonical') {
            // Already downgraded or never canonical — revoke approval only
            PilotDrawApproval::where('draw_id', $drawId)
                ->where('status', PilotDrawApproval::STATUS_APPROVED)
                ->update([
                    'status'     => PilotDrawApproval::STATUS_REVOKED,
                    'revoked_at' => now(),
                    'notes'      => $reason,
                ]);
            return false;
        }

        // ── 1. Downgrade engine mode
        $draw->update(['engine_mode' => 'hybrid']);

        // ── 2. Revoke approval record
        PilotDrawApproval::where('draw_id', $drawId)
            ->where('status', PilotDrawApproval::STATUS_APPROVED)
            ->update([
                'status'     => PilotDrawApproval::STATUS_REVOKED,
                'revoked_at' => now(),
                'notes'      => $reason,
            ]);

        // ── 3. Platform audit log
        PlatformAuditLogger::log(
            PlatformAuditLogger::ENGINE_FALLBACK,
            $draw,
            ['engine_mode' => 'canonical'],
            ['engine_mode' => 'hybrid'],
            [
                'reason'    => 'auto_rollback_threshold',
                'detail'    => $reason,
                'source'    => 'PilotAutoRollback',
            ]
        );

        // ── 4. Notify super-users
        static::notifySuperUsers($draw, $reason);

        Log::critical('[PilotAutoRollback] Draw auto-rolled back to hybrid', [
            'draw_id' => $drawId,
            'reason'  => $reason,
        ]);

        return true;
    }

    // ------------------------------------------------------------------
    // Notification helpers
    // ------------------------------------------------------------------

    private static function notifySuperUsers(Draw $draw, string $reason): void
    {
        try {
            $superUsers = User::where('userType', 'super-user')
                ->orWhere('priviledge', 'super-user')
                ->get();

            if ($superUsers->isEmpty()) {
                Log::warning('[PilotAutoRollback] No super-users found to notify');
                return;
            }

            foreach ($superUsers as $user) {
                try {
                    $user->notify(new PilotAutoRollbackNotification($draw, $reason));
                } catch (\Throwable $e) {
                    Log::error('[PilotAutoRollback] Notification failed', [
                        'user_id' => $user->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('[PilotAutoRollback] Super-user query failed', ['error' => $e->getMessage()]);
        }
    }
}
