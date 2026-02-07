<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeriesPoints extends Model
{
    use HasFactory;
    public function series()
    {
        return $this->belongsTo(Series::class);
    }

    public function points()
    {
        return $this->belongsTo(Point::class);
    }
}
