<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoalName extends Model
{
    use HasFactory;

    function goal_type(){
        return $this->belongsTo(GoalType::class,'goal_type_id','id');
    }
}
