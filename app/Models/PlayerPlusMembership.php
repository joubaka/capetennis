<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerPlusMembership extends Model
{
    protected $guarded = [];

    protected $casts = ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];

    public function player() { return $this->belongsTo(Player::class); }
    public function order() { return $this->belongsTo(PlayerPlusOrder::class, 'order_id'); }
}
