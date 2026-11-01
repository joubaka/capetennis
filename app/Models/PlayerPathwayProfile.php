<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerPathwayProfile extends Model
{
    protected $fillable = ['player_id', 'stage', 'wilson_division', 'recorded_by_user_id'];
}
