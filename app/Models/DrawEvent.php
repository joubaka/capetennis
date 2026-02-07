<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrawEvent extends Model
{
    use HasFactory;

    public function draws()
   
    {
        return $this->belongsTo(Draw::class,'draw_id','id');
    }
    public function events()
   
    {
        return $this->belongsTo(Event::class,'event_id','id');
    }

   
}
