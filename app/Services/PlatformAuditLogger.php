<?php

namespace App\Services;

use App\Models\PlatformAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use App\Support\Audit\AuditContext;
use App\Support\Audit\AuditWriter;

/**
 * PlatformAuditLogger
 *
 * Centralized audit trail for all governed platform actions.
 *
 * Usage:
 *   PlatformAuditLogger::log('draw.generated', $draw, before: null, after: $draw->toArray());
 *   PlatformAuditLogger::log('refund.issued', $cer, before: $old, after: $new, meta: ['reason' => $reason]);
 */
class PlatformAuditLogger
{
    // Canonical action constants
    public const DRAW_GENERATED       = 'draw.generated';
    public const DRAW_LOCKED          = 'draw.locked';
    public const DRAW_UNLOCKED        = 'draw.unlocked';
    public const DRAW_PUBLISHED       = 'draw.published';
    public const DRAW_DELETED         = 'draw.deleted';

    public const PROGRESSION_ADVANCED = 'progression.advanced';
    public const PROGRESSION_RESET    = 'progression.reset';

    public const SCORE_SAVED          = 'score.saved';
    public const SCORE_DELETED        = 'score.deleted';

    public const REFUND_ISSUED        = 'refund.issued';
    public const REFUND_REVERSED      = 'refund.reversed';

    public const WITHDRAWAL_PROCESSED = 'withdrawal.processed';
    public const WITHDRAWAL_REVERSED  = 'withdrawal.reversed';

    public const ENGINE_FALLBACK      = 'engine.fallback';
    public const ENGINE_MODE_CHANGED  = 'engine.mode_changed';

    public const CLEANUP_RUN          = 'cleanup.run';

    public const ADMIN_OVERRIDE       = 'admin.override';
    public const FLAG_CHANGED         = 'flag.changed';

    // ------------------------------------------------------------------

    /**
     * Write a platform audit entry.
     *
     * @param string               $action        Canonical action string (use constants above)
     * @param object|null          $subject       Eloquent model or object with id + class
     * @param mixed                $before        State snapshot before action
     * @param mixed                $after         State snapshot after action
     * @param array                $meta          Extra context (reason, command, ip, etc.)
     * @param int|null             $userId        Override authenticated user
     */
    public static function log(
        string  $action,
        ?object $subject = null,
        mixed   $before  = null,
        mixed   $after   = null,
        array   $meta    = [],
        ?int    $userId  = null,
    ): void {
        try {
            PlatformAuditLog::create([
                'user_id'      => $userId ?? (Auth::id() ?? null),
                'action'       => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => $subject ? ($subject->id ?? null) : null,
                'before'       => is_array($before) ? $before : (is_object($before) ? (array)$before : ($before !== null ? ['value' => $before] : null)),
                'after'        => is_array($after)  ? $after  : (is_object($after)  ? (array)$after  : ($after  !== null ? ['value' => $after]  : null)),
                'request_id'   => app(AuditContext::class)->requestId() ?? Request::header('X-Request-Id') ?? Str::uuid()->toString(),
                'engine_mode'  => config('engine.mode', env('ENGINE_MODE', 'legacy')),
                'metadata'     => !empty($meta) ? $meta : null,
                'created_at'   => now(),
            ]);

            app(AuditWriter::class)->record([
                'category' => 'platform',
                'action' => $action,
                'subject' => $subject,
                'before' => $before,
                'after' => $after,
                'metadata' => array_merge($meta, ['engine_mode' => config('engine.mode', env('ENGINE_MODE', 'legacy'))]),
                'actor_id' => $userId,
            ]);
        } catch (\Throwable $e) {
            // Never let audit logging break the main flow
            \Illuminate\Support\Facades\Log::error('[PlatformAuditLogger] Failed to write audit log', [
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
