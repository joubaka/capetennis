<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoFolder extends Model
{
    use HasFactory;
    function event()
    {
        return $this->belongsTo(Event::class);
    }
    public function photos()
    {
        return $this->hasMany(Photo::class,'folder_id','id');
    }

}
