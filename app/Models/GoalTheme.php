<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoalTheme extends Model
{
    use HasFactory;

    function goal_types(){
        return $this->hasMany(GoalType::class);
    }

}
