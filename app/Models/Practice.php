<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use SebastianBergmann\Timer\Duration;

class Practice extends Model
{
    use HasFactory;

    function practice_type() {
        return $this->belongsTo(PracticeType::class);
    }

    function duration() {
        return $this->belongsTo(PracticeDuration::class);
    }
}
