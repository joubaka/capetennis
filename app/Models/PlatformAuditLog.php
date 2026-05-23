<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PlatformAuditLog
 *
 * Central audit trail for all governed platform actions.
 *
 * Columns:
 *   user_id, action, subject_type, subject_id,
 *   before (JSON), after (JSON), request_id,
 *   engine_mode, metadata (JSON), created_at
 */
class PlatformAuditLog extends Model
{
    public $timestamps = false;
    protected $table   = 'platform_audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'before',
        'after',
        'request_id',
        'engine_mode',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'before'     => 'array',
        'after'      => 'array',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeForSubject($query, string $type, int|string $id)
    {
        return $query->where('subject_type', $type)->where('subject_id', $id);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
