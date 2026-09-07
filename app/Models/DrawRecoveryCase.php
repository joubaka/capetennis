<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawRecoveryCase extends Model
{
    protected $fillable = [
        'draw_id', 'source_fixture_id', 'requested_by', 'approved_by', 'restored_by',
        'operation', 'status', 'reason', 'impact', 'preview_fingerprint',
        'before_snapshot_id', 'after_snapshot_id', 'restore_reason', 'approved_at', 'applied_at', 'restored_at',
    ];

    protected $casts = [
        'impact' => 'array',
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
        'restored_at' => 'datetime',
    ];

    public function draw()
    {
        return $this->belongsTo(Draw::class);
    }

    public function beforeSnapshot()
    {
        return $this->belongsTo(DrawRecoverySnapshot::class, 'before_snapshot_id');
    }

    public function afterSnapshot()
    {
        return $this->belongsTo(DrawRecoverySnapshot::class, 'after_snapshot_id');
    }
}
