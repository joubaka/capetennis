<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'Fee'];


    //used


    //not used
    function categoryEvent()
    {
        return $this->belongsTo(CategoryEvent::class,'id','category_id');
    }

    public function events()
    {
        return $this->belongsToMany(Event::class,'category_events');
    }

    public function registrations() : HasManyThrough {
        return $this->hasManyThrough(CategoryEventRegistration::class,CategoryEvent::class);
    }
    public function draws()
    {
        return $this->hasMany(Draw::class,'name','draw_name');
    }
 

}
