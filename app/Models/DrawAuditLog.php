<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawAuditLog extends Model
{
    protected $fillable = [
        'draw_id',
        'user_id',
        'action',
        'fixture_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function draw()
    {
        return $this->belongsTo(Draw::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Convenience factory to create an audit entry for the authenticated user.
     */
    public static function record(int $drawId, string $action, ?int $fixtureId = null, array $payload = []): void
    {
        static::create([
            'draw_id'    => $drawId,
            'user_id'    => auth()->id(),
            'action'     => $action,
            'fixture_id' => $fixtureId,
            'payload'    => $payload ?: null,
        ]);
    }
}
