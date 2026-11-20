<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampNominee extends Model
{
    protected $guarded = [];
    protected $hidden = ['token_hash', 'token_encrypted'];
    protected $casts = ['approved_at' => 'datetime', 'declined_at' => 'datetime', 'token_encrypted' => 'encrypted'];
    public function event() { return $this->belongsTo(Event::class); }
    public function player() { return $this->belongsTo(Player::class); }
    public function categoryEvent() { return $this->belongsTo(CategoryEvent::class); }
    public function getDisplayNameAttribute(): string { return $this->player?->full_name ?? ''; }
}
