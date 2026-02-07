<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventRegion extends Model
{
    use HasFactory;
    
    public function events()
    {
       return $this->belongsTo(Event::class,'event_id');
    }
    public function region(){
        return $this->belongsTo(TeamRegion::class,'region_id');
     }
    
     
}
