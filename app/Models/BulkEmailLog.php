<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BulkEmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_type',
        'related_type',
        'related_id',
        'recipient_email',
        'recipient_name',
        'status',
        'error_message',
        'payload',
        'queued_at',
        'sent_at',
        'failed_at',
        'skipped_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'skipped_at' => 'datetime',
    ];

    /**
     * Scope to get only queued emails.
     */
    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    /**
     * Scope to get only sent emails.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope to get only failed emails.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get only skipped emails.
     */
    public function scopeSkipped($query)
    {
        return $query->where('status', 'skipped');
    }

    /**
     * Mark as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $error,
        ]);
    }

    /**
     * Mark as skipped.
     */
    public function markAsSkipped(string $reason): void
    {
        $this->update([
            'status' => 'skipped',
            'skipped_at' => now(),
            'error_message' => $reason,
        ]);
    }

    /**
     * Get the related model (polymorphic).
     */
    public function related()
    {
        return $this->morphTo('related');
    }
}
