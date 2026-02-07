<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubDraw extends Model
{
    use HasFactory;
    //hier onder nog reg maak
    public function registrations()
    {
        return $this->belongsToMany(Registration::class,'registration_sub_draws','sub_draw_id','registration_id');
    }

    public function draws()
    {
        return $this->belongsTo(Draw::class,'draw_id','id');
    }
}
