<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeagueRegion extends Model
{
    use HasFactory;
    protected $table = 'league_regions';

    function categories()
    {
        return $this->belongsToMany(LeagueCategory::class,'league', 'league_region_id', 'id');
    }
}
