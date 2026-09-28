<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterprovincialTrialInvitationBatch extends Model
{
    public const DRAFT = 'draft';
    public const REVIEWED = 'reviewed';
    public const QUEUED = 'queued';

    protected $fillable = ['event_id', 'status', 'snapshot_version', 'snapshot_hash', 'created_by_user_id', 'reviewed_by_user_id', 'reviewed_at', 'queued_at'];
    protected $casts = ['reviewed_at' => 'datetime', 'queued_at' => 'datetime'];

    public function event() { return $this->belongsTo(Event::class); }
    public function invitations() { return $this->hasMany(InterprovincialTrialInvitation::class, 'batch_id'); }
}
