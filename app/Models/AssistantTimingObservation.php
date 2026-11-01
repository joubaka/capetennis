<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantTimingObservation extends Model
{
    protected $table = 'event_assistant_timing_observations';

    protected $guarded = ['id'];

    protected $casts = ['verified' => 'boolean', 'interrupted' => 'boolean', 'retired' => 'boolean', 'walkover' => 'boolean', 'actual_minutes' => 'integer', 'actual_start' => 'datetime', 'actual_finish' => 'datetime'];
}
