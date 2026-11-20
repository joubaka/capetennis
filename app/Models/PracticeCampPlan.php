<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PracticeCampPlan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'candidates' => 'array',
        'dates_provisional' => 'boolean',
        'entry_fee' => 'decimal:2',
        'capacity' => 'integer',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
