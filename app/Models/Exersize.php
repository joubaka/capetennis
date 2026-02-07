<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exersize extends Model
{
    use HasFactory;
    
    function player()
    {
        return $this->belongsTo(Player::class);
    }

    function exersizeName()
    {
        return $this->belongsTo(ExersizeName::class,'exersize_name_id','id');
    }
}
