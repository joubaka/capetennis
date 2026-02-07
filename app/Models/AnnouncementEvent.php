<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnouncementEvent extends Model
{
    use HasFactory;

    public function announcements(){

        return $this->belongsTo(Announcement::class,'announcement_id','id');

    }

    public function events(){
        return $this->belongsTo(Event::class,'event_id','id');
    }
}
