<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrawVenue extends Model
{
    use HasFactory;
    public function venue()
    {
        return $this->belongsTo(Venues::class,'venue_id','id');
    }
    public function draw()
    {
        return $this->belongsTo(Draw::class,'draw_id','id');
    }
}
