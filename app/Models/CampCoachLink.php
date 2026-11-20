<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampCoachLink extends Model
{
    protected $guarded = [];
    protected $hidden = ['token_hash'];
    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    public function event() { return $this->belongsTo(Event::class); }
}
