<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegionRankingSource extends Model
{
    protected $fillable = [
        'event_id', 'event_region_id', 'region_id', 'series_id', 'reserve_count', 'linked_by',
    ];

    protected $casts = ['reserve_count' => 'integer'];

    public function event() { return $this->belongsTo(Event::class); }
    public function eventRegion() { return $this->belongsTo(EventRegion::class); }
    public function region() { return $this->belongsTo(TeamRegion::class); }
    public function series() { return $this->belongsTo(Series::class); }
    public function imports() { return $this->hasMany(TeamSelectionImport::class, 'source_id'); }
}
