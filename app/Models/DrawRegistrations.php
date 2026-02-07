<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrawRegistrations extends Model
{
    use HasFactory;
    public function registrations()
   
    {
        return $this->belongsTo(Registration::class,'registration_id','id');
    }
}
