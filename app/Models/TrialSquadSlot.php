<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrialSquadSlot extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['reserve' => 'boolean', 'requires_colour' => 'boolean', 'responded_at' => 'datetime'];
    public function draft() { return $this->belongsTo(TrialSquadDraft::class, 'draft_id'); }
    public function player() { return $this->belongsTo(Player::class); }
    public function categoryEvent() { return $this->belongsTo(CategoryEvent::class); }
}
