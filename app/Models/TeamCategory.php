<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamCategory extends Model
{
    use HasFactory;
    protected $table ='team_categories';

    public function event()
    {
       
        return $this->belongsTo(Event::class,'eventId','id');
    }

    public function draws()
    {
        return $this->hasManyThrough(Draw::class,Event::class);
    }

    
}
