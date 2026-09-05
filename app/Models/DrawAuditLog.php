<?php

namespace App\Models;

use App\Support\Audit\AuditWriter;
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
        if (session()->has('venue_scoring.operator')) {
            $payload['operator'] ??= session('venue_scoring.operator');
        }

        $legacy = static::create([
            'draw_id'    => $drawId,
            'user_id'    => auth()->id(),
            'action'     => $action,
            'fixture_id' => $fixtureId,
            'payload'    => $payload ?: null,
        ]);

        app(AuditWriter::class)->record([
            'category' => 'draw',
            'action' => 'draw.'.$action,
            'subject_type' => Draw::class,
            'subject_id' => $drawId,
            'after' => $payload ?: null,
            'metadata' => [
                'fixture_id' => $fixtureId,
                'legacy_draw_audit_id' => $legacy->id,
            ],
        ], true);
    }
}
