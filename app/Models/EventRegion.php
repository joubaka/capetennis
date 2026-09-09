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

    public function rankingSource()
    {
        return $this->hasOne(EventRegionRankingSource::class, 'event_region_id');
    }

    public function managerAssignment()
    {
        return $this->hasOne(EventRegionManager::class, 'event_region_id');
    }

    public function announcements()
    {
        return $this->hasMany(TeamSelectionRegionAnnouncement::class, 'event_region_id')->latest();
    }
    
     
}
