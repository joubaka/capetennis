<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExerciseName extends Model
{
    use HasFactory;

    protected $table = 'exersize_names';

    public function exerciseType()
    {
        return $this->belongsTo(ExerciseType::class, 'exersize_type_id');
    }
}
