<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    use HasFactory;

    protected $table = 'exercises';

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function exerciseName()
    {
        return $this->belongsTo(ExerciseName::class, 'exersize_name_id', 'id');
    }
}
