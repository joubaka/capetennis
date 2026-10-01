<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrialReplacementProposal extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['approved_at' => 'datetime'];
    public function draft() { return $this->belongsTo(TrialSquadDraft::class, 'draft_id'); }
    public function source() { return $this->belongsTo(TrialSquadSlot::class, 'source_slot_id'); }
    public function target() { return $this->belongsTo(TrialSquadSlot::class, 'target_slot_id'); }
}
