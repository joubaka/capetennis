<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'actor_roles' => 'array',
        'before' => 'array',
        'after' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit events cannot be deleted through the application.'));
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
