<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrialSquadDraft extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['tiers' => 'array', 'needs_review' => 'boolean', 'finalised_at' => 'datetime'];
    public function slots() { return $this->hasMany(TrialSquadSlot::class, 'draft_id'); }
    public function event() { return $this->belongsTo(Event::class); }
    public function rankingRun() { return $this->belongsTo(TrialRankingRun::class, 'ranking_run_id'); }
}
