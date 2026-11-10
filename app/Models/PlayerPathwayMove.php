<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerPathwayMove extends Model
{
    protected $fillable = ['player_id', 'from_stage', 'to_stage', 'started_on', 'recorded_by_user_id'];

    protected function casts(): array
    {
        return ['started_on' => 'immutable_date'];
    }
}
