<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationSubDraw extends Model
{
    use HasFactory;
    public function registrations(){
        return $this->belongsTo(Registration::class,'registration_id','id');
    }
    public function sub_draws(){
        return $this->belongsTo(SubDraw::class,'sub_draw_id','id');
    }
}

