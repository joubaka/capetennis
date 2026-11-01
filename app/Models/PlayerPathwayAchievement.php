<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerPathwayAchievement extends Model
{
    public $timestamps = false;

    protected $fillable = ['player_id', 'stage', 'milestone', 'verified_by_user_id', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime'];
    }
}
