<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrialProgramme extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['bank_details'];
    protected $casts = ['bank_details' => 'encrypted', 'participation_fee' => 'decimal:2', 'response_deadline' => 'datetime', 'payment_deadline' => 'datetime', 'withdrawal_deadline' => 'datetime', 'concluded_at' => 'datetime', 'manual_position_categories' => 'array'];
    public function event() { return $this->belongsTo(Event::class); }
    public function currentRun() { return $this->belongsTo(TrialRankingRun::class, 'current_run_id'); }
}
