<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WpCavDouble extends Model
{
    use HasFactory;
    protected $table= 'wp_cav_doubles_15_17';

    public function teams()
    {
        return $this->belongsTo(Team::class,'team_id','id');
    }
}
