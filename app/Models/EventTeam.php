<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventTeam extends Model
{
    use HasFactory;
    public function events()
    {
       return $this->belongsTo(Event::class,'event_id');
    }

    public function teams()
    {
       return $this->belongsTo(Team::class,'team_id');
    }
    

}
