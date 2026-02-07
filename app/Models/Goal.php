<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    use HasFactory;

    function names() {
        return $this->belongsToMany(GoalName::class,'goal_goal_names');
    }

  
}
