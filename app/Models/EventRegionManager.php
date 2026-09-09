<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegionManager extends Model
{
    protected $fillable = ['event_id', 'event_region_id', 'region_id', 'user_id', 'assigned_by'];

    public function event() { return $this->belongsTo(Event::class); }
    public function eventRegion() { return $this->belongsTo(EventRegion::class); }
    public function region() { return $this->belongsTo(TeamRegion::class); }
    public function user() { return $this->belongsTo(User::class); }
}
