<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampConfiguration extends Model
{
    protected $guarded = [];
    protected $casts = ['divisions' => 'array', 'registration_closes_at' => 'datetime', 'coach_closes_at' => 'datetime'];
    public function event() { return $this->belongsTo(Event::class); }
}
