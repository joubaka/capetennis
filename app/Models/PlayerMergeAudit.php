<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerMergeAudit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'kept_before_snapshot' => 'array',
        'removed_snapshot' => 'array',
        'field_resolutions' => 'array',
        'impact_snapshot' => 'array',
        'change_manifest' => 'array',
        'merged_at' => 'datetime',
    ];

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
